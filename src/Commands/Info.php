<?php

namespace Tink\Scheduler\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Helper\TableCell;

class Info extends Command
{
    protected $name = 'scheduler:info';

    protected $description = 'the info of scheduler(config|stats)';

    protected $scheduler_config;

    protected function getArguments() {
    	return [
    		['flag', InputArgument::OPTIONAL, 'info of scheduler to dispaly', 'config']
    	];
    }

    public function fire()
    {
        $flag = ucfirst(strtolower($this->argument('flag')));
        $method = "display{$flag}InfoAboutScheduler";

        if (!method_exists($this, $method)) {
        	$this->error("no $flag info about scheduler");
        	return;
        }

        $this->info("display $flag info about scheduler");
        $this->loadSchedulerConfig();

        return call_user_func([$this, $method]);
    }


    protected function displayConfigInfoAboutScheduler()
    {
        $headers = ['Job_code', 'Cron', 'Run params', 'Description'];
        $rows = [];

        foreach ($this->scheduler_config['schedules'] as $jobCode => $schedule) {
            $row[] = $jobCode;
            $row[] = $schedule['schedule']['cron_expr'];
            $row[] = implode([
                'class:' . $schedule['run']['class'],
                'func:' . $schedule['run']['function']
                ], PHP_EOL);
            $row[] = $schedule['description'];

            $rows[] = $row;
        }
        $this->table($headers, $rows);
    }

    protected function displayStatsInfoAboutScheduler()
    {
        $headers = ['Job_code', 'Count(success|error|running|missed|pending|total)', 'Time_consumed(max|min|avg)', 'Latest_success_at'];
        $rows = [];

        $jobCodes = $this->getJobcodes();
        foreach ($jobCodes as $item) {
            $jobCode = $item->job_code;

            $row[] = $jobCode;
            $row[] = implode($this->getStatusCountByJobCode($jobCode), '|');
            $row[] = implode($this->getTimeConsumedByJobCode($jobCode), '|');
            $row[] = $this->getLastestSuccessExecuteAt($jobCode);

            $rows[] = $row;
        }
        $this->table($headers, $rows);
    }

    protected function getJobcodes()
    {
        return DB::table($this->scheduler_config['schedule_table_name'])
            ->select('job_code')
            ->groupBy('job_code')
            ->get();
    }

    protected function getStatusCountByJobCode($jobCode)
    {
        static $statusCode = [
            'success' => Dispatch::STATUS_SUCCESS,
            'error' => Dispatch::STATUS_ERROR,
            'running' => Dispatch::STATUS_RUNNING,
            'missed' => Dispatch::STATUS_MISSED,
            'pending' => Dispatch::STATUS_PENDING,
        ];

        $countSqlSegments = '';
        foreach ($statusCode as $status => $code) {
            $countSqlSegments .= "COUNT(CASE WHEN STATUS = '{$code}' THEN 1 ELSE NULL END) AS {$status}, ";
        }
        
        $sql = <<<EOD
SELECT {$countSqlSegments} COUNT(*) AS total 
FROM `{$this->scheduler_config['schedule_table_name']}`
WHERE job_code='$jobCode';
EOD;
        return (array)DB::select($sql)[0];
    }

    protected function getTimeConsumedByJobCode($jobCode)
    {
        $status = Dispatch::STATUS_SUCCESS;
        $sql = <<<EOD
SELECT MAX(finished_at - executed_at) AS max_time_consumed, MIN(finished_at - executed_at) AS min_time_consumed, AVG(finished_at - executed_at) AS avg_time_consumed
FROM  `{$this->scheduler_config['schedule_table_name']}`
WHERE status IN ('$status')    
EOD;
        return (array)DB::select($sql)[0];
    }

    protected function getLastestSuccessExecuteAt($jobCode)
    {
        $status = Dispatch::STATUS_SUCCESS;
        $sql = <<<EOD
SELECT MAX(finished_at) AS last_success_at
FROM `{$this->scheduler_config['schedule_table_name']}`
WHERE status = '$status'
    AND job_code = '$jobCode';        
EOD;
        return DB::select($sql)[0]->last_success_at;
    }

    protected function loadSchedulerConfig()
    {
    	$this->scheduler_config = Config::get('scheduler');
    }

}