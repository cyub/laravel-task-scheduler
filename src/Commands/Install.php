<?php

namespace Tink\Scheduler\Commands;

use Illuminate\Console\Command;
use Scheduler\SchedulerServiceProvider;

class Install extends Command 
{
	protected $name = 'scheduler:install';

	protected $description = 'install scheduler';

	public function fire()
	{
		$this->info('Publish scheduler database, config files');
		$this->call('vendor:publish', ['--provider' => SchedulerServiceProvider::class]);

		$this->info('Migrate scheduler database table');
		$this->call('migrate', ['--path' => './database/migrations/scheduler']);

		$this->info('Successfully installed');
	}
	
}