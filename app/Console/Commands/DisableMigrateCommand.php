<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DisableMigrateCommand extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'migrate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Disabled database migration command';

    /**
     * Configure the command to ignore validation errors for any flags/options.
     */
    protected function configure()
    {
        parent::configure();
        $this->ignoreValidationErrors();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Database migrations are disabled for this project.');
        return 0;
    }
}
