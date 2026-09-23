<?php

declare(strict_types=1);

use Eldinbiz\RubberStamp\RubberStamp;

it('resolves the singleton', function () {
    expect(app(RubberStamp::class))->toBeInstanceOf(RubberStamp::class);
});

it('returns the same instance from the container', function () {
    expect(app(RubberStamp::class))->toBe(app(RubberStamp::class));
});

it('merges the package config', function () {
    expect(config('rubberstamp.results_dir'))->toBe('doctest-reports/browser-test-log')
        ->and(config('rubberstamp.test_log_dir'))->toBe('doctest-reports/test-log');
});

it('loads the package views', function () {
    expect(view()->exists('rubberstamp::report'))->toBeTrue()
        ->and(view()->exists('rubberstamp::partials.header'))->toBeTrue();
});

it('registers the artisan command', function () {
    $this->artisan('rubberstamp')
        ->expectsOutputToContain('RubberStamp Testing Suite')
        ->assertSuccessful();
});
