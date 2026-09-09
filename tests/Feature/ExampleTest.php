<?php

declare(strict_types=1);

use UnitTesterDocumenter\UnitTesterDocumenter\UnitTesterDocumenter;

it('resolves the singleton', function () {
    expect(app(UnitTesterDocumenter::class))->toBeInstanceOf(UnitTesterDocumenter::class);
});

it('returns the same instance from the container', function () {
    expect(app(UnitTesterDocumenter::class))->toBe(app(UnitTesterDocumenter::class));
});

it('merges the package config', function () {
    expect(config('unit-tester-documenter.placeholder'))->toBe('default');
});

it('loads the package translations', function () {
    expect(trans('unit-tester-documenter::messages.placeholder'))->toBe('UnitTesterDocumenter placeholder translation.');
});

it('loads the package views', function () {
    expect(view()->exists('unit-tester-documenter::placeholder'))->toBeTrue();
});

it('registers the artisan command', function () {
    $this->artisan('unit-tester-documenter:placeholder')
        ->expectsOutputToContain('UnitTesterDocumenter placeholder command executed.')
        ->assertSuccessful();
});
