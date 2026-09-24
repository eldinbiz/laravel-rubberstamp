<div align="center">
    <img src="arts/logo.png" alt="RubberStamp" width="600">
    <h1>RubberStamp</h1>
    <p><em>Helping Me, corporate slave programmer, to make Test Report the manager will blindly sign without reading.</em></p>
</div>

<p align="center">
    <img src="https://img.shields.io/badge/Laravel-%5E12.0-FF2D20?style=flat-square&logo=laravel" alt="Laravel ^12.0">
    <img src="https://img.shields.io/badge/PHP-%5E8.3-777BB4?style=flat-square&logo=php" alt="PHP ^8.3">
    <img src="https://img.shields.io/badge/Pest-%5E4.6-FF8000?style=flat-square" alt="Pest ^4.6">
    <img src="https://img.shields.io/badge/Tests-passing-44cc11?style=flat-square&logo=githubactions&logoColor=white" alt="Tests passing">
    <img src="https://img.shields.io/badge/Security_Audit-pending-dfb317?style=flat-square" alt="Security Audit pending">
    <img src="https://img.shields.io/badge/Vulnerability_Scan-scheduled-blue?style=flat-square" alt="Vulnerability Scan scheduled">
    <img src="https://img.shields.io/badge/Composer_Audit-passed-green?style=flat-square&logo=composer" alt="Composer Audit passed">
</p>

## Overview

In the corporate compliance dungeon, pristine Pest tests and green CI pipelines mean nothing. 
Management demands formal, printable test reports—complete with SOP codes, document IDs, and multi-tier sign-off blocks destined to rot in a physical binder.

**RubberStamp** is a Laravel package I vibe-coded so that I never have to spend my Friday afternoon manually copy-pasting terminal output, cropping browser screenshots, and assembling test documents.
With a single Artisan command, RubberStamp flushes caches, executes your Pest and browser test suites, captures visual snapshots, and compiles a print-ready single-file HTML test report.

Hand it over, let management blindly "review" and sign it, and get back to writing real code and building meaningful products.

---

## Key Features

- **Print-Ready HTML Reports**: Generates self-contained, print-ready corporate documentation complete with Document IDs, revision codes, execution durations, and test suite breakdowns.
- **Customizable Reports**: Personalize report branding with your company name, corporate logo, confidentiality classification (e.g., `CONFIDENTIAL`, `INTERNAL USE ONLY`), custom Document ID prefixes, and even swap in custom Blade report templates (`report_view`) via configuration or environment variables.
- **Sign-Off Matrices**: Dynamically append structured sign-off sheets for Testers, Reviewers, Approvers, and Acknowledgers directly into the report.
- **Browser Snapshots**: Executes Pest browser tests and automatically captures visual snapshots—embedded directly as base64 images into the standalone audit report without modifying host application test classes.
- **Interactive Terminal Wizard**: Configure audit metadata, SOP references, and reviewer names on the fly before running tests using the `-i` flag.
- **Self-healthcheck**: Built-in diagnostic check (`--doctor`) for Playwright browsers, Node dependencies, and container environments prior to doing browser tests.
- **Timestamped Execution Logs**: Streams real-time test runs into `doctest-reports/test-log/` or `doctest-reports/browser-test-log/` for complete auditability.
- **Standalone Document Compiler**: Recompile reports anytime from existing test logs without re-executing suites (`php artisan rubberstamp:document`).
- **Clean Up Your Test Results Log**: Artifact pruning with customizable retention periods and dry-run safety (`php artisan rubberstamp:prune`).

---

## Installation

Install the package via Composer:

```bash
composer require eldinbiz/laravel-rubberstamp
```

### Configuration (Optional)

The package works completely out of the box with zero configuration. If you wish to customize output directories, retention policies, Chromium paths, or default sign-off roles, publish the configuration file:

```bash
php artisan vendor:publish --tag="rubberstamp-config"
```

---

## Usage

### RubberStamp Suite Overview

Run the root command for a quick summary of all available RubberStamp commands:

```bash
php artisan rubberstamp
```

---

### Running Feature & Unit Tests (`rubberstamp:features`)

Run Pest unit and feature tests with automatic configuration/view cache clearing, timestamped execution logging, and automated corporate HTML report generation.

```bash
# View all available arguments and options
php artisan rubberstamp:features --help

# Run all feature and unit tests
php artisan rubberstamp:features

# Run a specific test file or directory
php artisan rubberstamp:features tests/Feature/OrderProcessingTest.php

# Interactively select test suites to execute
php artisan rubberstamp:features --selected-test-suite

# Launch interactive wizard to configure metadata and sign-offs
php artisan rubberstamp:features -i

# Run tests and attach corporate sign-off matrix
php artisan rubberstamp:features \
    --document-id="UAT-ERP" \
    --sop="SOP-DEV-002" \
    --author="Jane Doe" \
    --reviewed-by="Mr Smith,QA Engineer|Taylor,QA Lead" \
    --approved-by="Jane,Technical Lead|CTO" \
    --acknowledged-by="Bob,Product Owner"
```

---

### Running Browser Tests (`rubberstamp:browser`)

Run Pest browser tests with Playwright environment health checks, Vite hot-reload protection, and automated visual snapshot capture embedded as base64 images into the audit report.

> [!NOTE]
> **Zero-Configuration Browser Testing**:
> Unlike typical browser testing setups, you **do not** need to add traits like `CapturesBrowserSnapshots` or edit `tests/TestCase.php` / `tests/Pest.php` in your host application. The `rubberstamp:browser` command automatically boots snapshot hooks and page trackers at runtime.

```bash
# View all available arguments and options
php artisan rubberstamp:browser --help

# Run all browser tests
php artisan rubberstamp:browser

# Run a specific browser test file
php artisan rubberstamp:browser tests/Browser/LoginFlowTest.php

# Interactively select browser test suites
php artisan rubberstamp:browser --selected-test-suite

# Run pre-flight Playwright and Chromium diagnostics only
php artisan rubberstamp:browser --doctor

# Launch interactive metadata wizard
php artisan rubberstamp:browser -i

# Run browser tests with custom document ID and SOP tracking
php artisan rubberstamp:browser \
    --document-id="UAT-BROWSER" \
    --sop="SOP-UI-004" \
    --approved-by="Jane,Technical Lead|CTO"
```

---

### Standalone Documentation Compiler (`rubberstamp:document`)

Generate or re-compile corporate HTML reports from existing test logs without re-running test suites. Useful when you need to regenerate an audit report with updated SOP codes or sign-off names.

```bash
# View all available arguments and options
php artisan rubberstamp:document --help

# Compile documentation for the latest test run
php artisan rubberstamp:document

# Compile documentation for a specific run or log file
php artisan rubberstamp:document test_20260910_031304

# Interactively configure metadata and sign-offs for an existing run
php artisan rubberstamp:document test_20260910_031304 -i

# Customize metadata directly via options
php artisan rubberstamp:document test_20260910_031304 \
    --document-id="AUDIT-2026" \
    --sop="SOP-AUDIT-001" \
    --approved-by="Compliance Lead"
```

---

### Pruning Test Artifacts & Logs (`rubberstamp:prune`)

Purge obsolete test execution logs, generated corporate HTML reports, and browser visual snapshots to reclaim disk space:

```bash
# View all available arguments and options
php artisan rubberstamp:prune --help

# Prune artifacts older than the default retention period (7 days)
php artisan rubberstamp:prune

# Dry run: preview candidate items and reclaimed disk space without deleting
php artisan rubberstamp:prune --dry-run

# Prune artifacts older than 48 hours
php artisan rubberstamp:prune --hours=48 --force

# Retain only the latest 10 test runs and prune older ones
php artisan rubberstamp:prune --keep=10 --force

# Prune only specific artifact types ('reports', 'logs', 'snapshots')
php artisan rubberstamp:prune --type=snapshots --force

# Prune all test artifacts (prompts two-step confirmation unless --force is used)
php artisan rubberstamp:prune -a
php artisan rubberstamp:prune --all --force
```

---

## Customizing the Report View

You can customize or replace the default report template.

To publish the default Blade template into your application's `resources/views/vendor/rubberstamp`:

```bash
php artisan vendor:publish --tag="rubberstamp-views"
```

Or configure your own custom Blade view in `config/rubberstamp.php`:

```php
'report_view' => 'reports.corporate',
```

---

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to RubberStamp! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Taylor Otwell and the Laravel Teams](https://github.com/laravel/laravel)
- [Nuno Maduro](https://github.com/pestphp/pest)

## License

RubberStamp is open-sourced software licensed under the [MIT license](LICENSE.md).
