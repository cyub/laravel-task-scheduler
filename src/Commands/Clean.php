<?php

namespace Tink\Scheduler\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class Clean extends Command
{
    protected $name = 'scheduler:clean';

    protected $description = 'clean scheduler cache';

    public function fire()
    {
    	$this->info('Starting clean scheduler cache');

        Cache::forget(Dispatch::CACHE_KEY_LAST_SCHEDULE_GENERATE_AT);
        Cache::forget(Dispatch::CACHE_KEY_LAST_HISTORY_CLEANUP_AT);

        $this->info('Successfully clean');
    }

}