<?php

declare(strict_types=1);

namespace Eldinbiz\RubberStamp\Console\Commands;

use Eldinbiz\RubberStamp\Console\Concerns\RendersHeader;
use Illuminate\Console\Command;

class RubberStampCommand extends Command
{
    use RendersHeader;

    /**
     * The command signature.
     */
    protected $signature = 'rubberstamp';

    /**
     * The command description.
     */
    protected $description = 'Run automated tests and generate documentation reports.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->renderHeader();

        $this->line(' <fg=white;options=bold>Available RubberStamp Commands:</>');
        $this->line('  <fg=cyan>▸</> <fg=green>php artisan rubberstamp:features</>   Run Pest tests with auto-documentation and test-log/ logging');
        $this->line('  <fg=cyan>▸</> <fg=green>php artisan rubberstamp:browser</>    Run browser tests with Playwright diagnostics & snapshots');
        $this->line('  <fg=cyan>▸</> <fg=green>php artisan rubberstamp:document</>   Compile corporate HTML reports from test logs');
        $this->line('  <fg=cyan>▸</> <fg=green>php artisan rubberstamp:prune</>      Prune old test logs, corporate reports, and snapshots');
        $this->newLine();
        $this->line(' Run any command with <comment>--help</comment> for target options and flags.');
        $this->newLine();

        return self::SUCCESS;
    }
}
