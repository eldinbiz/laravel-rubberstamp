---
name: rubberstamp-development
description: >
  Configure and apply the RubberStamp (eldinbiz/laravel-rubberstamp) testing and corporate report package in Laravel applications.
license: MIT
metadata:
  author: Eldin Akbar
---

# RubberStamp Development

Use this skill when a Laravel application integrates, runs tests, or generates corporate audit reports with the `eldinbiz/laravel-rubberstamp` package.

## Primary Goal

Run automated Pest unit, feature, and browser tests with real-time logging, Playwright self-health diagnostics, zero-configuration visual snapshots, and print-ready corporate HTML compliance documentation.

## Workflow

1. **Install Package**:
   Install via Composer:
   ```bash
   composer require eldinbiz/laravel-rubberstamp --dev
   ```
2. **Publish Configuration (Optional)**:
   Publish package config or customizable Blade view if custom branding or directories are needed.
3. **Verify Browser Environment**:
   Run `php artisan rubberstamp:browser --doctor` before executing browser tests in new or CI environments.
4. **Execute Suites with Compliance Metadata**:
   Run `rubberstamp:features` or `rubberstamp:browser` with required SOP codes, document IDs, and sign-off matrices (`--sop`, `--document-id`, `--reviewed-by`, `--approved-by`, `--acknowledged-by`).
5. **Diagnose Failures via Real-Time Logs**:
   When tests fail, inspect the timestamped logs under `doctest-reports/test-log/` or `doctest-reports/browser-test-log/`, and check visual snapshots under `doctest-reports/browser-test-log/<run>/<Suite>/<test_slug>.png`.
6. **Regenerate Reports or Prune Artifacts**:
   - Re-compile reports without re-running tests using `php artisan rubberstamp:document`.
   - Maintain disk space by scheduling or running `php artisan rubberstamp:prune`.

## Available Commands

Always use the canonical `rubberstamp:*` commands:

1. **Test Suite Overview**:
   ```bash
   php artisan rubberstamp
   ```

2. **Run Unit & Feature Tests**:
   ```bash
   php artisan rubberstamp:features [target] [options]
   ```
   - Automatically flushes `config` and `view` cache prior to execution (skip with `--skip-clear`).
   - Streams output in real-time to `doctest-reports/test-log/test_<timestamp>.log`.
   - Compiles print-ready corporate HTML audit reports to `doctest-reports/test_<timestamp>.html`.
   - Flags:
     - `--selected-test-suite`: Interactively select one or more test suites or test classes to execute using terminal checkboxes.
     - `--document-id="DOC-TEST-"`: Document control ID prefix.
     - `--sop="SOP-DEV-001"`: SOP tracking policy or RFC code.
     - `--author="Jane Doe"`: Tester or author name (defaults to Git `user.name`).
     - `--reviewed-by="Name,Role|Role"`: Pipe-separated list of reviewer roles/names.
     - `--approved-by="Name,Role|Role"`: Pipe-separated list of approver roles/names.
     - `--acknowledged-by="Name,Role|Role"`: Pipe-separated list of acknowledger roles/names.
     - `-i`, `--interactive`: Launch interactive terminal wizard for metadata.
     - `--no-doc`: Stream logs but skip HTML report generation.
     - `--pest-path="vendor/bin/pest"`: Custom path to Pest binary.

3. **Run Playwright Browser Tests**:
   ```bash
   php artisan rubberstamp:browser [target] [options]
   ```
   - **Zero-Configuration**: Does not require adding traits or modifying `tests/TestCase.php`—hooks boot automatically at runtime.
   - Pre-flight diagnostic check: `--doctor` (or `--check`) verifies Playwright, Node, and Chromium binaries.
   - `--selected-test-suite`: Interactively select one or more browser test classes or suites using terminal checkboxes.
   - `--skip-health-check`: Skip pre-flight doctor checks for faster repeated runs.
   - Captures isolated per-test visual snapshots into `doctest-reports/browser-test-log/<run>/<Suite>/<slug>.png`.
   - Embeds visual evidence as base64 images directly into the standalone HTML report (`doctest-reports/browser_test_<timestamp>.html`).
   - Accepts all metadata and sign-off flags (`--document-id`, `--sop`, `--author`, `--reviewed-by`, `--approved-by`, `--acknowledged-by`, `-i`, `--no-doc`).

4. **Standalone Document Compiler**:
   ```bash
   php artisan rubberstamp:document [run] [options]
   ```
   - Re-compiles HTML documentation from existing test logs without re-executing test suites.
   - Accepts specific run names (e.g. `test_20260910_031304`) or defaults to the latest run.
   - Supports updating metadata and sign-offs (`--document-id`, `--sop`, `--approved-by`, `-i`).

5. **Prune Old Artifacts**:
   ```bash
   php artisan rubberstamp:prune [options]
   ```
   - Retain latest runs: `--keep=10`.
   - Purge by age: `--hours=48` or `--days=7`.
   - Filter by artifact type: `--type=all|reports|logs|snapshots`.
   - Preview without deleting: `--dry-run`.
   - Non-interactive force deletion: `--force`.
   - Prune all artifacts: `-a` or `--all --force`.

## Configuration & Publishing

Publish package configuration:
```bash
php artisan vendor:publish --tag="rubberstamp-config"
```

Publish customizable HTML report Blade template:
```bash
php artisan vendor:publish --tag="rubberstamp-views"
```

Publish all package resources:
```bash
php artisan vendor:publish --tag="rubberstamp"
```

### Key Environment Variables

- `RUBBERSTAMP_COMPANY_NAME`: Organization name displayed on report headers.
- `RUBBERSTAMP_CLASSIFICATION`: Security classification (default: `INTERNAL USE ONLY`).
- `RUBBERSTAMP_DOCUMENT_ID_PREFIX`: Custom Document ID prefix (default: `DOC-TEST-`).
- `RUBBERSTAMP_SOP`: Default SOP reference code (default: `N/A`).
- `RUBBERSTAMP_AUTHOR_NAME`: Default author / tester name.
- `RUBBERSTAMP_LOGO_PATH`: Path to company logo image embedded in report headers.
- `RUBBERSTAMP_REPORT_VIEW`: Custom Blade view template (default: `rubberstamp::report`).
- `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH`: Custom Chromium binary path for Playwright.

## References

- Configuration: `config/rubberstamp.php`
- Default Reports Directory: `doctest-reports/`
- Unit/Feature Test Logs: `doctest-reports/test-log/test_<timestamp>.log`
- Browser Test Logs: `doctest-reports/browser-test-log/browser_test_<timestamp>.log`
- Browser Snapshots: `doctest-reports/browser-test-log/<run>/<Suite>/<test_case_slug>.png`
- Published Views: `resources/views/vendor/rubberstamp/report.blade.php`

## Examples

### Run Feature Tests with Formal Sign-Off Matrix Non-Interactively
```bash
php artisan rubberstamp:features tests/Feature \
    --document-id="UAT-ERP-2026" \
    --sop="SOP-REL-004" \
    --author="Jane Doe" \
    --reviewed-by="Alex Smith,QA Engineer|Sam Taylor,QA Lead" \
    --approved-by="Jordan,Engineering Director|CTO" \
    --acknowledged-by="Morgan,Product Owner"
```

### Validate Playwright Browser Environment Before Testing
```bash
php artisan rubberstamp:browser --doctor
```

### Run Specific Browser Test Suite
```bash
php artisan rubberstamp:browser tests/Browser/OrderFlowTest.php \
    --document-id="BROWSER-UAT" \
    --sop="SOP-UI-002"
```

### Re-Compile Report with Updated Approvers Without Re-Running Tests
```bash
php artisan rubberstamp:document test_20260918_100000 \
    --approved-by="New Lead,Technical Lead"
```

### Prune Old Test Snapshots and Logs Safely
```bash
# Preview what would be pruned
php artisan rubberstamp:prune --days=14 --dry-run

# Force prune snapshots older than 48 hours
php artisan rubberstamp:prune --hours=48 --type=snapshots --force
```

## Anti-Patterns

- **Modifying `tests/TestCase.php` or `tests/Pest.php` for Browser Snapshots**: Do NOT manually add traits (like `CapturesBrowserSnapshots`) or register hooks in application test bases. `rubberstamp:browser` automatically injects hooks and page watchers at runtime.
- **Running Bare `pest` for Compliance Audits**: Do NOT run `vendor/bin/pest` directly when audit-ready HTML reports, sign-off blocks, or timestamped test logs are required for compliance or QA review. Use `rubberstamp:features` or `rubberstamp:browser`.
- **Re-Running Test Suites to Update Signers or Metadata**: Do NOT re-execute full test suites solely to update SOP numbers or signer names. Use `rubberstamp:document [run]` with new options.
- **Manually Deleting Log / Snapshot Folders**: Do NOT delete files directly from `doctest-reports/` using filesystem tools. Use `rubberstamp:prune` to ensure paired logs, snapshots, and reports are cleaned up safely.
- **Using Interactive Flag (`-i`) in CI/Automated Pipelines**: Do NOT pass `-i` or `--interactive` in CI scripts or automated AI workflows; supply explicit CLI flags (`--document-id`, `--sop`, `--approved-by`) instead.
