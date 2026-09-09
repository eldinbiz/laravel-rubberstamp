<?php

declare(strict_types=1);

namespace UnitTesterDocumenter\UnitTesterDocumenter\Console\Commands;

use Illuminate\Console\Command;

class UnitTesterDocumenterCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'unit-tester-documenter:placeholder';

    /**
     * The command description.
     */
    protected $description = 'Placeholder Artisan command shipped by the package unit-tester-documenter.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('UnitTesterDocumenter placeholder command executed.');

        return self::SUCCESS;
    }
}
