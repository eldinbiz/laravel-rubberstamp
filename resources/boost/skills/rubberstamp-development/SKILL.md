---
name: rubberstamp-development
description: >
  Configure and apply the RubberStamp (eldinbiz/laravel-rubberstamp) testing and corporate report package in Laravel applications.
license: MIT
metadata:
  author: Eldin Akbar
---

# RubberStamp Development

Use this skill when a Laravel application integrates or runs tests with the `eldinbiz/laravel-rubberstamp` package.

## Primary Goal

Run automated Pest unit, feature, and browser tests with real-time logging, Playwright self-health diagnostics, automated visual snapshot evidence, and corporate HTML compliance documentation.

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
   - Automatically clears `config` and `view` cache prior to execution.
   - Logs output in real-time to `doctest-reports/test-log/`.
   - Compiles print-ready corporate HTML audit reports to `doctest-reports/`.
   - Pass `-i` for interactive sign-off authoring, or `--no-doc` to skip report generation.

3. **Run Playwright Browser Tests**:
   ```bash
   php artisan rubberstamp:browser [target] [options]
   ```
   - Performs automatic pre-flight Playwright/Node/Chromium health diagnostics (`--doctor`).
   - Captures isolated per-test visual snapshots into `doctest-reports/browser-test-log/<run>/<Suite>/<slug>.png`.
   - Embeds visual evidence directly into corporate HTML audit reports.

4. **Standalone Document Compiler**:
   ```bash
   php artisan rubberstamp:document [run] [options]
   ```
   - Re-compiles HTML documentation from existing test logs without re-executing suites.

5. **Prune Old Artifacts**:
   ```bash
   php artisan rubberstamp:prune [options]
   ```
   - Retains latest runs (e.g. `--keep=10`) or purges artifacts older than N days/hours (`--days=7`, `--hours=48`).

## Configuration & Publishing

Publish package configuration:
```bash
php artisan vendor:publish --tag="rubberstamp-config"
```

Publish customizable HTML report Blade template:
```bash
php artisan vendor:publish --tag="rubberstamp-views"
```
