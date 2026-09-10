<?php

declare(strict_types=1);

namespace UnitTesterDocumenter\UnitTesterDocumenter\Console\Commands;

use Illuminate\Console\Command;

class UnitTesterDocumenterCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'doctest';

    /**
     * The command description.
     */
    protected $description = 'Run automated tests with documentation and diagnostics (DocTest suite).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>=================================================================</>');
        $this->line('<fg=cyan;options=bold>                   DocTest Testing Suite                         </>');
        $this->line('<fg=cyan;options=bold>=================================================================</>');
        $this->newLine();
        $this->line(' Available DocTest Commands:');
        $this->line('  <fg=green>php artisan doctest:features</>   Run Pest tests with cache clearing and .pest/ logging');
        $this->line('  <fg=green>php artisan doctest:browser</>    Run browser tests with Playwright diagnostics & snapshots');
        $this->newLine();
        $this->line(' Run any command with <comment>--help</comment> for target options and flags.');
        $this->line('<fg=cyan;options=bold>=================================================================</>');

        return self::SUCCESS;
    }
}
