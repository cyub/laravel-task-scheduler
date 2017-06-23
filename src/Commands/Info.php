<?php

namespace Tink\Scheduler\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Illuminate\Support\Facades\Config;

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
    	$this->info("config info");
    }

    protected function displayStatsInfoAboutScheduler()
    {
    	$this->info("stats info");
    }

    protected function loadSchedulerConfig()
    {
    	$this->scheduler_config = Config::get('scheduler');
    }

}