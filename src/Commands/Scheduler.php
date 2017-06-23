<?php

namespace Tink\Scheduler\Commands;

use Illuminate\Console\Command;

class Scheduler extends Command 
{

	protected $name = 'scheduler';

	protected $description = 'informartion about scheduler';

	public function fire()
	{
		$this->info($this->description);
	}
}