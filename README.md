<div align="center">
    <h1>RubberStamp</h1>
    <p><em>Helping You, corporate slave programmer, to make Test Report your manager will blindly sign without reading.</em></p>
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

You just wrote pristine Pest tests, your CI pipeline is glowing green, and your commit history is flawless. Too bad none of that matters to your managers in the corporate world.

Automated test results would happily live in Git history, a Confluence page, a Microsoft Loop workspace, or a Markdown file in your repo. But as a corporate slave developer surviving in an enterprise compliance dungeon, you are required to produce **formal, printable test reports**—complete with document control numbers, SOP tracking codes, and multi-tier managerial sign-off blocks that someone will inevitably print onto paper, give hand signature, place into a binder, and never see the light again.

**RubberStamp** is a laravel package that exists so you never have to spend your Friday afternoon manually copy-pasting terminal output, cropping browser screenshots, and assembling the test document.

With Artisan command, RubberStamp runs your Pest tests, flushes configuration and view caches, records audit logs, captures visual browser snapshots, and automatically compiles a **print-ready, single-file HTML test report** complete with sign-off blocks. Hand over the test report your manager will "review them" blindly and sign, feeling that they've achieved something big in project management.

---

## Key Features

- **Audit-Ready HTML Reports**: Generates self-contained, print-ready corporate documentation complete with Document IDs, revision codes, execution durations, and test suite breakdowns.
- **Customizable Reports**: Personalize report branding with your company name, corporate logo, confidentiality classification (e.g., `CONFIDENTIAL`, `INTERNAL USE ONLY`), custom Document ID prefixes, and even swap in custom Blade report templates (`report_view`) via configuration or environment variables.
- **Formal Sign-Off Matrices**: Dynamically append structured sign-off sheets for Testers, Reviewers, Approvers, and Acknowledgers directly into the report.
- **Zero-Configuration Browser Snapshots**: Executes Pest browser tests and automatically captures visual snapshots—embedded directly as base64 images into the standalone audit report without modifying host application test classes.
- **Interactive Terminal Wizard**: Configure audit metadata, SOP references, and reviewer names on the fly before running tests using the `-i` flag.
- **Playwright Environment Doctor**: Built-in pre-flight diagnostic check (`--doctor`) for Playwright browsers, Node dependencies, and container environments.
- **Timestamped Execution Logs**: Streams real-time test runs into `doctest-reports/test-log/` or `doctest-reports/browser-test-log/` for complete auditability.
- **Standalone Document Compiler**: Recompile reports anytime from existing test logs without re-executing suites (`php artisan rubberstamp:document`).
- **Clean Up Your Test Results Log**: Automated artifact pruning with customizable retention periods and dry-run safety (`php artisan rubberstamp:prune`).

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

#### Main Command

```bash
php artisan rubberstamp:features [target] [options]
```

#### Arguments & Options

- **`target`** *(argument)*: Optional test file or directory path to execute. If omitted, executes all suites defined in your `phpunit.xml`.
  - *Example*: `php artisan rubberstamp:features tests/Feature/OrderProcessingTest.php`
- **`--selected-test-suite`**: Interactively select one or more test suites or test classes to execute using terminal checkboxes (`Laravel\Prompts\multiselect`).
  - *Example*: `php artisan rubberstamp:features --selected-test-suite`
- **`--document-id=`**: Custom Document ID prefix for the generated corporate audit report. (Defaults to `DOC-TEST-` or `RUBBERSTAMP_DOCUMENT_ID_PREFIX`).
  - *Example*: `--document-id="UAT-ERP"`
- **`--sop=`**: Associated Standard Operating Procedure (SOP) policy or RFC ticket code. (Defaults to `N/A` or `RUBBERSTAMP_SOP`).
  - *Example*: `--sop="SOP-DEV-002"`
- **`--author=`**: Tester or author name. (Defaults to Git configured `user.name` or `RUBBERSTAMP_AUTHOR`).
  - *Example*: `--author="Jane Doe"`
- **`--reviewed-by=`**: Pipe-separated list of reviewer names and roles (`Name,Role` or `Role`).
  - *Example*: `--reviewed-by="Mr Smith,QA Engineer|Taylor,QA Lead|QA Head"`
- **`--approved-by=`**: Pipe-separated list of approver names and roles (`Name,Role` or `Role`).
  - *Example*: `--approved-by="Jane,Technical Lead|CTO"`
- **`--acknowledged-by=`**: Pipe-separated list of acknowledger names and roles (`Name,Role` or `Role`).
  - *Example*: `--acknowledged-by="Bob,Product Owner"`
- **`-i`**, **`--interactive`**: Launch an interactive terminal wizard to configure audit metadata and sign-offs before test execution.
  - *Example*: `php artisan rubberstamp:features -i`
- **`--no-doc`**: Run tests and stream output to log files, but skip generating the corporate HTML documentation report.
  - *Example*: `php artisan rubberstamp:features --no-doc`
- **`--skip-clear`**: Skip automated pre-test cache clearing (`config:clear` and `view:clear`).
  - *Example*: `php artisan rubberstamp:features --skip-clear`
- **`--pest-path=`**: Custom path to the Pest test runner binary.
  - *Example*: `--pest-path="vendor/bin/pest"`

---

### Running Browser Tests (`rubberstamp:browser`)

Run Pest browser tests with Playwright environment health checks, Vite hot-reload protection, and automated visual snapshot capture embedded as base64 images into the audit report.

> [!NOTE]
> **Zero-Configuration Browser Testing**:
> Unlike typical browser testing setups, you **do not** need to add traits like `CapturesBrowserSnapshots` or edit `tests/TestCase.php` / `tests/Pest.php` in your host application. The `rubberstamp:browser` command automatically boots snapshot hooks and page trackers at runtime.

#### Main Command

```bash
php artisan rubberstamp:browser [target] [options]
```

#### Arguments & Options

- **`target`** *(argument)*: Optional browser test file or directory path to execute. (Defaults to `tests/Browser`).
  - *Example*: `php artisan rubberstamp:browser tests/Browser/LoginFlowTest.php`
- **`--selected-test-suite`**: Interactively select one or more browser test classes or suites to execute using terminal checkboxes.
  - *Example*: `php artisan rubberstamp:browser --selected-test-suite`
- **`--doctor`**, **`--check`**: Run pre-flight Playwright environment, Node dependencies, and Chromium binary diagnostics only without running tests.
  - *Example*: `php artisan rubberstamp:browser --doctor`
- **`--skip-health-check`**: Skip the pre-flight environment health check and run browser tests directly.
  - *Example*: `php artisan rubberstamp:browser --skip-health-check`
- **`--document-id=`**: Custom Document ID prefix for the generated browser audit report. (Defaults to `DOC-TEST-` or `RUBBERSTAMP_DOCUMENT_ID_PREFIX`).
  - *Example*: `--document-id="UAT-BROWSER"`
- **`--sop=`**: Associated Standard Operating Procedure (SOP) policy or RFC ticket code. (Defaults to `N/A` or `RUBBERSTAMP_SOP`).
  - *Example*: `--sop="SOP-UI-004"`
- **`--author=`**: Tester or author name. (Defaults to Git configured `user.name` or `RUBBERSTAMP_AUTHOR`).
  - *Example*: `--author="Jane Doe"`
- **`--reviewed-by=`**: Pipe-separated list of reviewer names and roles (`Name,Role` or `Role`).
  - *Example*: `--reviewed-by="Mr Smith,QA Engineer|Taylor,QA Lead"`
- **`--approved-by=`**: Pipe-separated list of approver names and roles (`Name,Role` or `Role`).
  - *Example*: `--approved-by="Jane,Technical Lead|CTO"`
- **`--acknowledged-by=`**: Pipe-separated list of acknowledger names and roles (`Name,Role` or `Role`).
  - *Example*: `--acknowledged-by="Bob,Product Owner"`
- **`-i`**, **`--interactive`**: Launch an interactive terminal wizard to configure audit metadata and sign-offs before test execution.
  - *Example*: `php artisan rubberstamp:browser -i`
- **`--no-doc`**: Run browser tests but skip generating the corporate HTML documentation report.
  - *Example*: `php artisan rubberstamp:browser --no-doc`
- **`--pest-path=`**: Custom path to the Pest test runner binary.
  - *Example*: `--pest-path="vendor/bin/pest"`

---

### Standalone Documentation Compiler (`rubberstamp:document`)

Generate or re-compile corporate HTML reports from existing test logs without re-running test suites. Useful when you need to regenerate an audit report with updated SOP codes or sign-off names.

```bash
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

#### Available Pruning Options

- **`-a`**, **`--all`**: Prune all test artifacts regardless of age (prompts two-step confirmation).
  - *Example*: `php artisan rubberstamp:prune -a`
- **`--hours=`**: Prune artifacts older than specified hours.
  - *Example*: `--hours=24`
- **`--days=`**: Prune artifacts older than specified days.
  - *Example*: `--days=14`
- **`--keep=`**: Keep the latest N test runs and prune older ones.
  - *Example*: `--keep=5`
- **`--type=`**: Limit pruning to a specific type (`all`, `reports`, `logs`, `snapshots`).
  - *Example*: `--type=reports`
- **`--dry-run`**: Simulate pruning and display matching files without deleting.
  - *Example*: `--dry-run`
- **`--force`**: Force deletion without confirmation prompt.
  - *Example*: `--force`

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
