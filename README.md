<div align="center">
    <h1>Unit Tester Documenter</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/vendor-name/unit-tester-documenter"><img src="https://img.shields.io/packagist/v/vendor-name/unit-tester-documenter.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/vendor-name/unit-tester-documenter"><img src="https://img.shields.io/packagist/php-v/vendor-name/unit-tester-documenter.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/vendor-name/unit-tester-documenter"><img src="https://badge.laravel.cloud/badge/vendor-name/unit-tester-documenter?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/vendor-name/unit-tester-documenter/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/vendor-name/unit-tester-documenter/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/vendor-name/unit-tester-documenter"><img src="https://img.shields.io/packagist/dt/vendor-name/unit-tester-documenter.svg?style=flat-square" alt="Total Downloads"></a>
</p>



## Installation

You can install the package via Composer:

```bash
composer require vendor-name/unit-tester-documenter
```

You may publish all of the package's resources at once:

```bash
php artisan vendor:publish --tag="unit-tester-documenter"
```

Or, you may publish each resource individually:

### Publishing the Configuration File

```bash
php artisan vendor:publish --tag="unit-tester-documenter-config"
```

### Publishing and Running the Migrations

```bash
php artisan vendor:publish --tag="unit-tester-documenter-migrations"
php artisan migrate
```

### Publishing the Views

```bash
php artisan vendor:publish --tag="unit-tester-documenter-views"
```

### Publishing the Translations

```bash
php artisan vendor:publish --tag="unit-tester-documenter-lang"
```

### Publishing the Public Assets

```bash
php artisan vendor:publish --tag="unit-tester-documenter-assets"
```

## Usage

### Running Tests with Timestamped Documentation

Execute Pest tests with automatic config/view cache clearing and timestamped logging in `.pest/`:

```bash
# Run all configured test suites (via phpunit.xml)
php artisan test:features

# Run specific test file or directory
php artisan test:features tests/Feature
php artisan test:features tests/Feature/ExampleTest.php

# Pass through any Pest arguments
php artisan test:features --filter=example --parallel

# Alias
php artisan test:feature
```

### Running Browser Tests with Playwright Diagnostics

Execute Pest browser tests with Playwright environment health checks, hot-reloading protection, and snapshot documentation:

```bash
# Run environment health check
php artisan test:browser --doctor

# Run browser tests
php artisan test:browser
php artisan test:browser tests/Browser/RoleManagementTest.php
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Unit Tester Documenter! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Vendor Name](https://github.com/vendor-name)
- [All Contributors](../../contributors)

## License

Unit Tester Documenter is open-sourced software licensed under the [MIT license](LICENSE.md).
