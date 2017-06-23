<?php

namespace Tink\Scheduler\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Exception;

class Dispatch extends Command
{
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_SUCCESS = 'success';
    const STATUS_MISSED  = 'missed';
    const STATUS_ERROR   = 'error';

    const CACHE_KEY_LAST_SCHEDULE_GENERATE_AT   = 'cron_last_schedule_generate_at';
    const CACHE_KEY_LAST_HISTORY_CLEANUP_AT     = 'cron_last_history_cleanup_at';

    protected $name = 'scheduler:dispatch';
    protected $description = 'dispatch and run task';

    protected $cron_enable;
    protected $cron_schedule_table_name;
    protected $cron_schedule_generate_every;
    protected $cron_schedule_ahead_for;
    protected $cron_schedule_lifetime;
    protected $cron_history_cleanup_every;
    protected $cron_history_success_lifetime;
    protected $cron_history_failure_lifetime;
    protected $cron_schedule;


    public function __construct()
    {
        parent::__construct();

        $this->cron_enable = Config::get('scheduler.enable', true);

        $this->cron_schedule_table_name = Config::get('scheduler.schedule_table_name', 'cron_schedule');
        $this->cron_schedule_generate_every  = Config::get('scheduler.schedule_generate_every', 15);
        $this->cron_schedule_ahead_for       = Config::get('scheduler.schedule_ahead_for', 20);
        $this->cron_schedule_lifetime        = Config::get('scheduler.schedule_lifetime', 15);
        $this->cron_history_cleanup_every    = Config::get('scheduler.history_cleanup_every', -1);
        $this->cron_history_success_lifetime = Config::get('scheduler.history_success_lifetime', 60);
        $this->cron_history_failure_lifetime = Config::get('scheduler.history_failure_lifetime', 60);

        $this->cron_schedules = Config::get('scheduler.schedules', []);
    }

    public function fire()
    {
        if ($this->cron_enable === false) {
            $this->info('schedule disable');
            return;
        }
        $this->info("Starting dispatch jobs to run...");

        $schedules = $this->getPendingSchedules();
        $scheduleLifetime = $this->cron_schedule_lifetime * 60;
        $now = time();

        foreach ($schedules as $schedule) {
            $schedule = (array)$schedule;
            $jobConfig = $this->cron_schedules[$schedule['job_code']];
            if (!$jobConfig || !$jobConfig['run']) {
                continue;
            }

            $runConfig = $jobConfig['run'];
            $time = strtotime($schedule['scheduled_at']);
            if ($time > $now) {
                continue;
            }

            try {
                $errorStatus = self::STATUS_ERROR;
                $errorMessage = 'Unknown error.';

                if ($time < $now - $scheduleLifetime) {
                    $errorStatus = self::STATUS_MISSED;
                    throw new Exception('Too late for the schedule.');
                }

                $class = false;
                $function = false;
                $params = '';

                if (!empty($runConfig['class'])) {
                    $class = $runConfig['class'];
                }

                if (!empty($runConfig['function'])) {
                    $function = $runConfig['function'];
                }

                if (!empty($runConfig['params'])) {
                    $params = $runConfig['params'];
                }

                if ($class === false AND $function === false) {
                    throw new Exception('No cron schedule function found.');
                }

                $result = DB::table($this->cron_schedule_table_name)
                    ->where(['schedule_id' => $schedule['schedule_id'], 'status' => self::STATUS_PENDING])
                    ->update(['status' => self::STATUS_RUNNING]);

                if (!$result) {
                    continue;
                }

                $this->updateSchedule($schedule['schedule_id'], [
                    'status'        => self::STATUS_RUNNING,
                    'executed_at'   => strftime('%Y-%m-%d %H:%M:%S', time())
                ]);

                if ( !class_exists($class)) {
                    throw new Exception('No cron schedule class found:' . $class);
                }
                $this->info("Run {$schedule['job_code']}...");
                $SCHEDULE = new $class;
                $SCHEDULE->$function($params);

                $this->updateSchedule($schedule['schedule_id'], array(
                    'status'        => self::STATUS_SUCCESS,
                    'finished_at'   => strftime('%Y-%m-%d %H:%M:%S', time())
                ));

            } catch (Exception $e) {
                $this->updateSchedule($schedule['schedule_id'], array(
                    'status'    => $errorStatus,
                    'messages'  => $e->__toString()
                ));
            }
        }
        
        $this->generate();
        $this->cleanup();
        
        $this->info('Successfully dispatched jobs completed');
    }

    public function getPendingSchedules()
    {
        return DB::table($this->cron_schedule_table_name)
            ->where(['status' => self::STATUS_PENDING])
            ->get()
            ->toArray();
    }

    public function generate()
    {
        if (!$this->cron_schedules) {
            return;
        }

        $lastRun = Cache::get(self::CACHE_KEY_LAST_SCHEDULE_GENERATE_AT);
        if ($lastRun > time() - $this->cron_schedule_generate_every * 60) {
            return;
        }

        $schedules = $this->getPendingSchedules();
        $exists = [];
        foreach ($schedules as $schedule) {
            $schedule = (array)$schedule;
            $exists[$schedule['job_code'].'/'.$schedule['scheduled_at']] = 1;
        }

        
        $scheduleAheadFor = $this->cron_schedule_ahead_for * 60;
        $schedule = [];
        foreach ($this->cron_schedules as $jobCode => $jobConfig) {
            $cronExpr = null;
            if (empty($cronExpr) && $jobConfig['schedule']['cron_expr']) {
                $cronExpr = (string)$jobConfig['schedule']['cron_expr'];
            }
            if (!$cronExpr) {
                continue;
            }
            $now = time();
            $timeAhead = $now + $scheduleAheadFor;

            $schedule = [];
            $schedule['job_code'] = $jobCode;
            $schedule['status'] = self::STATUS_PENDING;
            $cronExprArr = preg_split('#\s+#', $cronExpr, null, PREG_SPLIT_NO_EMPTY);
            if (count($cronExprArr)<5 || count($cronExprArr)>6) {
                throw new Exception('Cron_schedule Invalid cron expression: ' . $cronExpr);
                
            }

            for ($time = $now; $time < $timeAhead; $time += 60) {
                $ts = strftime('%Y-%m-%d %H:%M:00', $time);
                if ( !empty($exists[$jobCode.'/'.$ts])) {
                    // already scheduled
                    continue;
                }

                if (!is_numeric($time)) {
                    $time = strtotime($time);
                }

                $d = getdate($time);

                $match = $this->matchCronExpression($cronExprArr[0], $d['minutes'])
                    && $this->matchCronExpression($cronExprArr[1], $d['hours'])
                    && $this->matchCronExpression($cronExprArr[2], $d['mday'])
                    && $this->matchCronExpression($cronExprArr[3], $d['mon'])
                    && $this->matchCronExpression($cronExprArr[4], $d['wday']);
                if ($match) {
                    $schedule['created_at'] = strftime('%Y-%m-%d %H:%M:%S', time());
                    $schedule['scheduled_at'] = strftime('%Y-%m-%d %H:%M', $time);
                    $this->insertSchedule($schedule);
                } else {
                    continue;
                }
            }
        }

        Cache::forever(self::CACHE_KEY_LAST_SCHEDULE_GENERATE_AT, time());
    }

    public function cleanup()
    {
        if ($this->cron_history_cleanup_every == -1) {
            return;
        }

        $lastCleanup = Cache::get(self::CACHE_KEY_LAST_HISTORY_CLEANUP_AT);
        if ($lastCleanup > time() - $this->cron_history_cleanup_every * 60) {
            return $this;
        }

        $history = DB::table($this->cron_schedule_table_name)
            ->whereIn('status', [
                self::STATUS_SUCCESS,
                self::STATUS_MISSED,
                self::STATUS_ERROR
            ])->get();

        $historyLifetimes = [
            self::STATUS_SUCCESS => $this->cron_history_success_lifetime * 60,
            self::STATUS_MISSED => $this->cron_history_failure_lifetime * 60,
            self::STATUS_ERROR => $this->cron_history_failure_lifetime * 60,
        ];

        $now = time();
        foreach ($history as $record) {
            $record = (array)$record;
            if ((strtotime($record['executed_at']) > 0) &&
                (strtotime($record['executed_at']) < $now-$historyLifetimes[$record['status']])) {
                $this->deleteSchedule($record['schedule_id']);
            }
        }

        Cache::forever(self::CACHE_KEY_LAST_HISTORY_CLEANUP_AT, time());
    }

    public function matchCronExpression($expr, $num)
    {
        // handle ALL match
        if ($expr==='*') {
            return true;
        }

        // handle multiple options
        if (strpos($expr, ',')!==false) {
            foreach (explode(',',$expr) as $e) {
                if ($this->matchCronExpression($e, $num)) {
                    return true;
                }
            }
            return false;
        }

        // handle modulus
        if (strpos($expr,'/')!==false) {
            $e = explode('/', $expr);
            if (count($e)!==2) {
                throw new Exception("Cron_schedule Invalid cron expression, expecting 'match/modulus': " . $expr);
            }
            if (!is_numeric($e[1])) {
                throw new Exception("Cron_schedule Invalid cron expression, expecting numeric modulus: " . $expr);
            }
            $expr = $e[0];
            $mod = $e[1];
        } else {
            $mod = 1;
        }

        // handle all match by modulus
        if ($expr === '*') {
            $from = 0;
            $to = 60;
        }
        // handle range
        elseif (strpos($expr,'-')!==false) {
            $e = explode('-', $expr);
            if (count($e)!==2) {
                throw new Exception("Cron_schedule Invalid cron expression, expecting 'from-to' structure: " . $expr);
            }

            $from = $this->getNumeric($e[0]);
            $to = $this->getNumeric($e[1]);
        }
        // handle regular token
        else {
            $from = $this->getNumeric($expr);
            $to = $from;
        }

        if ($from===false || $to===false) {
            throw new Exception("Cron_schedule Invalid cron expression: " . $expr);
        }

        return ($num>=$from) && ($num<=$to) && ($num%$mod===0);
    }

    public function getNumeric($value)
    {
        static $data = array(
            'jan'=>1, 'feb'=>2, 'mar'=>3, 'apr'=>4, 'may'=>5, 'jun'=>6,
            'jul'=>7, 'aug'=>8, 'sep'=>9, 'oct'=>10, 'nov'=>11, 'dec'=>12,
            'sun'=>0, 'mon'=>1, 'tue'=>2, 'wed'=>3, 'thu'=>4, 'fri'=>5, 'sat'=>6,
        );

        if (is_numeric($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower(substr($value,0,3));
            if (isset($data[$value])) {
                return $data[$value];
            }
        }

        return false;
    }

    public function insertSchedule($schedule)
    {
        return DB::table($this->cron_schedule_table_name)->insertGetId($schedule);
    }

    public function updateSchedule($schedule_id, $schedule_data)
    {
        return DB::table($this->cron_schedule_table_name)->where(['schedule_id' => $schedule_id])->update($schedule_data);
    }

    public function deleteSchedule($schedule_id)
    {
        return DB::table($this->cron_schedule_table_name)->where(['schedule_id' => $schedule_id])->delete();
    }

}