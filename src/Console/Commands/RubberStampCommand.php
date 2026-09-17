<?php

declare(strict_types=1);

namespace Eldinbiz\RubberStamp\Console\Commands;

use Illuminate\Console\Command;

class RubberStampCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'rubberstamp';

    /**
     * The command description.
     */
    protected $description = 'Run automated tests with documentation and diagnostics (RubberStamp suite).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>=================================================================</>');
        $this->line('<fg=cyan;options=bold>                 RubberStamp Testing Suite                       </>');
        $this->line('<fg=cyan;options=bold>=================================================================</>');
        $this->newLine();
        $this->line(' Available RubberStamp Commands:');
        $this->line('  <fg=green>php artisan rubberstamp:features</>   Run Pest tests with auto-documentation and test-log/ logging');
        $this->line('  <fg=green>php artisan rubberstamp:browser</>    Run browser tests with Playwright diagnostics & snapshots');
        $this->line('  <fg=green>php artisan rubberstamp:document</>   Compile corporate HTML reports from test logs');
        $this->line('  <fg=green>php artisan rubberstamp:prune</>      Prune old test logs, corporate reports, and snapshots');
        $this->newLine();
        $this->line(' Run any command with <comment>--help</comment> for target options and flags.');
        $this->line('<fg=cyan;options=bold>=================================================================</>');

        return self::SUCCESS;
    }
}
