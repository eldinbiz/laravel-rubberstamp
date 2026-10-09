<?php

declare(strict_types=1);

it('renders stylized rubberstamp header, badge, and available commands', function () {
    $this->artisan('rubberstamp')
        ->expectsOutputToContain('RubberStamp :: Automated Test Report Generator')
        ->expectsOutputToContain('Automated test execution, diagnostic logs, and report generation for Pest.')
        ->expectsOutputToContain('Available RubberStamp Commands:')
        ->expectsOutputToContain('php artisan rubberstamp:features')
        ->expectsOutputToContain('php artisan rubberstamp:browser')
        ->expectsOutputToContain('php artisan rubberstamp:document')
        ->expectsOutputToContain('php artisan rubberstamp:prune')
        ->assertSuccessful();
});

it('supports non-ansi execution cleanly', function () {
    $this->artisan('rubberstamp', ['--no-ansi' => true])
        ->expectsOutputToContain('RubberStamp :: Automated Test Report Generator')
        ->expectsOutputToContain('Available RubberStamp Commands:')
        ->assertSuccessful();
});
