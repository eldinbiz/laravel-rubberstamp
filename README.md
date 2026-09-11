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

### DocTest Suite Overview

Run the root command for an overview of the DocTest suite:

```bash
php artisan doctest
```

### Running Tests with Automated Corporate Documentation

Execute Pest tests with automatic config/view cache clearing, `doctest-reports/test-log/` logging, and print-ready HTML & Markdown report compilation:

```bash
# Run tests and generate corporate audit reports
php artisan doctest:features

# Customize Document ID prefix and associate SOP policy code
php artisan doctest:features --document-id="UAT-TEST" --sop="SOP-DEV-002"

# Specify author and dynamic sign-off roles
php artisan doctest:features --author="Jane Doe" \
    --reviewed-by="Mr Smith,QA Engineer|Taylor,QA Lead|QA Head" \
    --approved-by="CTO" \
    --acknowledged-by="Product Owner"

# Launch interactive terminal setup wizard
php artisan doctest:features -i

# Run tests without generating documentation reports
php artisan doctest:features --no-doc

# Backward-compatible aliases
php artisan doctest:feature
php artisan test:features
php artisan test:feature
```

### Running Browser Tests with Visual Snapshot Documentation

Execute Pest browser tests with Playwright environment health checks, hot-reloading protection, and snapshot documentation embedded directly as base64 into the single-file HTML report:

```bash
# Run environment health check
php artisan doctest:browser --doctor

# Run browser tests with audit reports and embedded visual screenshots
php artisan doctest:browser

# Target specific test file with interactive sign-off setup
php artisan doctest:browser tests/Browser/LoginTest.php -i

# Backward-compatible alias
php artisan test:browser
```

### Standalone Documentation Compiler

Generate or re-compile corporate HTML and Markdown reports from existing test logs without re-running test suites:

```bash
# Compile documentation for the latest test run
php artisan doctest:document

# Compile documentation for a specific run or log file
php artisan doctest:document test_20260910_031304

# Interactively configure metadata for existing run
php artisan doctest:document test_20260910_031304 -i

# Alias
php artisan test:document
```

### Available Documentation Options

| Option | Description | Example |
| :--- | :--- | :--- |
| `--document-id=` | Custom Document ID prefix (defaults to `DOC-TEST-`) | `--document-id="UAT-TEST"` |
| `--sop=` | SOP policy or RFC ticket code (defaults to `N/A`) | `--sop="SOP-QA-001"` |
| `--author=` | Tester / author name (defaults to `git config user.name`) | `--author="Eldin Akbar"` |
| `--reviewed-by=` | Pipe-separated reviewers/roles (`Name,Role` or `Role`) | `--reviewed-by="Mr Smith,QA Engineer\|QA Head"` |
| `--approved-by=` | Pipe-separated approvers/roles (`Name,Role` or `Role`) | `--approved-by="Jane,QA Lead\|Technical Lead"` |
| `--acknowledged-by=` | Pipe-separated acknowledgers/roles (`Name,Role` or `Role`) | `--acknowledged-by="Bob,Project Manager"` |
| `-i`, `--interactive` | Interactively configure metadata & sign-offs before execution | `php artisan doctest:features -i` |
| `--no-doc` | Skip corporate documentation generation | `php artisan doctest:features --no-doc` |

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
