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
    expect(config('rubberstamp.placeholder'))->toBe('default');
});

it('loads the package translations', function () {
    expect(trans('rubberstamp::messages.placeholder'))->toBe('RubberStamp placeholder translation.');
});

it('loads the package views', function () {
    expect(view()->exists('rubberstamp::placeholder'))->toBeTrue();
});

it('registers the artisan command', function () {
    $this->artisan('rubberstamp')
        ->expectsOutputToContain('RubberStamp Testing Suite')
        ->assertSuccessful();
});
