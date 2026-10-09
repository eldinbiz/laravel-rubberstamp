# Release Notes

## [v0.2.4](https://github.com/eldinbiz/laravel-rubberstamp/compare/v0.2.3...v0.2.4) - 2026-10-09

### Added
- **Laravel Boost-Style CLI Header**: Added `RendersHeader` concern to render a high-resolution block ASCII logo with an ANSI 256 gradient (`RUBBER` in cyan-to-blue, `STAMP` in teal-to-emerald), a high-contrast badge pill, and bullet arrow indicators (`▸`) for available commands.
- **Automated Fallback**: Gracefully handles non-ANSI and redirected output (`--no-ansi`, `isDecorated()`) without raw escape sequence artifacts.
- **Feature Test Coverage**: Added `RubberStampCommandTest` verifying header rendering, badge text, available commands index, and clean `--no-ansi` execution.

### Fixed
- **Test Suite Host Isolation**: Added `--skip-orphan-check` to interactive browser command feature tests in `TestBrowserCommandTest`, preventing test flakes caused by active background processes on the developer machine.
- **PHPStan Static Analysis**: Resolved `booleanAnd.leftAlwaysTrue` static analysis error on line 404 of `BrowserProcessSanitizer` by eliminating redundant `$isNode` check following prior guard clauses.
- **Command Description Accuracy**: Updated `RubberStampCommand` description to accurately identify the package as an automated test report generator.

## [v0.2.3](https://github.com/eldinbiz/laravel-rubberstamp/compare/v0.2.2...v0.2.3) - 2026-10-09

### Fixed
- **Post-Run Sanitizer Ordering**: Reordered `TestBrowserCommand` cleanup sequence so `detectLingeringProcesses()` and `killProcesses()` execute before `cleanStaleTempFiles()` unlinks `.temp/playwright-server.json`.
- **Dynamic Port Recognition**: Lowered ephemeral test port threshold from `50000` to `49152` to conform with IANA and Windows TCP dynamic port allocation standards (`49152–65535`).
- **Orphaned Process Sweep**: Made supplementary process detection across Node workers and headless Chromium instances unconditional on Windows.
- **Unit Test Coverage**: Added tests in `BrowserProcessSanitizerTest` verifying ephemeral port boundary handling and orphaned process filtering.

### Documentation
- **Laravel Boost Skill**: Updated `resources/boost/skills/rubberstamp-development/SKILL.md` with guidelines for writing flake-free browser tests under Playwright, covering deterministic invariants across redirects, Livewire debouncing/settling, and strict `data-testid` locator scoping.

## [v0.1.6](https://github.com/eldinbiz/laravel-rubberstamp/compare/v0.1.5...v0.1.6) - 2026-09-24

### Added
- **Pre-Flight Process Sanitizer**: Added `BrowserProcessSanitizer` to detect orphaned Node, Playwright, and Chromium child processes holding socket locks before executing browser tests.
- **PowerShell-Free Native Architecture**: Process and socket inspection uses standard Win32 utilities (`netstat`, `tasklist`, `taskkill`) on Windows and POSIX utilities (`ps`, `lsof`, `kill -9`) on Linux/macOS with zero PowerShell dependencies, preventing AppLocker blocks in enterprise environments.
- **Interactive Feedback Table**: Surfaces detected lingering processes with an ASCII table showing PID, Process Name, Port, Repository Scope, and Command Line with prompt to terminate or cleanly abort.
- **CLI Options for Automation**:
  - Added `--force-kill-orphans` for non-interactive auto-cleanup in CI/CD pipelines.
  - Added `--skip-orphan-check` to bypass the pre-flight sanitizer if needed.
- **Process Tagging**: Injects `-d rubberstamp.tag=[<app>][rubberstamp]-browser-test` runtime directive into Pest CLI process for unequivocal process identification.
- **Laravel Boost Skill**: Updated `resources/boost/skills/rubberstamp-development/SKILL.md` with process sanitizer documentation, CI/CD recipes, and orphan prevention anti-patterns.


## [v0.1.0](https://github.com/eldinbiz/laravel-rubberstamp/compare/...v0.1.0) - 202x-xx-xx

Initial pre-release.
