# Release Notes

## [v0.4.0](https://github.com/eldinbiz/laravel-rubberstamp/compare/v0.3.1...v0.4.0) - 2026-10-14

### Added
- **Real-Time Scenario Streaming Protocol**: Introduced `RealTimeProgressTracker` to stream test lifecycle events (`START`, `END`) over `php://stderr` with base64-encoded JSON payloads. Emits zero temporary disk files, keeping developer workspaces and CI runners completely pristine.
- **Progressive Scenario Checklist & Transient Ticker**: Renders an animated in-place ticker (`⏳ <testName> ...`) during scenario execution and live completion timers (`✓ <testName> ... 4.20s`), automatically clamped to terminal boundaries to prevent multi-row wrapping.
- **Terminal Cursor Rewinding & Native Pest Formatting**: Uses ANSI cursor rewinding (`\x1b[1A\x1b[2K`) on class/suite conclusion boundaries (`PASS`/`FAIL` or blank line gap) to cleanly erase transient live checklist lines, allowing Pest's native Collision output (green banner, timings, and assertion statistics) to print with authentic spacing.
- **Pre-Launch Socket Deadlock Diagnostics**: Added automatic differentiation between test scenario actionability deadlocks (e.g. Playwright element or scroll lockups) and pre-launch Playwright Node server socket deadlocks (`TIME_WAIT` port collision on rapid consecutive runs), displaying targeted diagnostic banners and reproduction commands.

### Fixed
- **Development Tool Protection**: Strengthened canonical path normalization in `BrowserProcessSanitizer` to guarantee that active developer processes (`composer dev`, Vite, `php artisan serve`, queue listeners) are never identified as orphaned test runners.
- **Pest Suite Spacing**: Preserved Pest's authentic blank line gaps between test classes by triggering live stream rewind immediately on class gap boundaries.
- **Static Analysis Compliance**: Encapsulated console streaming state inside a strongly-typed anonymous state object, resolving PHPStan Level 7 closure scope errors.

## [v0.2.6](https://github.com/eldinbiz/laravel-rubberstamp/compare/v0.2.5...v0.2.6) - 2026-10-12

### Added
- **Streaming Idle Watchdog & Countdown**: Introduced an incremental output watchdog loop in `rubberstamp:browser` (configurable via `config('rubberstamp.idle_timeout')` or `--idle-timeout=<seconds>`, defaulting to 90s). Includes a subtle, single-line in-place countdown indicator (`Waiting for test output... (85s until idle timeout)`) that appears after 5s of silence and cleanly erases upon receiving test output. Automatically terminates deadlocked Playwright/Chromium startup attempts with exit code 124 and reaps orphaned socket locks.

### Fixed
- **Actionable Idle Timeout Guidance**: Enhanced the idle watchdog timeout banner in `rubberstamp:browser` to provide clear developer guidance when slow test suites exceed 90 seconds without emitting output. The error message clarifies potential causes (startup deadlock vs. slow test execution) and renders copy-pasteable syntax for `--idle-timeout=<seconds>` (or `0` to disable) and the `RUBBERSTAMP_IDLE_TIMEOUT` `.env` variable; updated the `{--idle-timeout=}` Artisan signature description to explicitly state the default 90s value.
- **Active Test Session Concurrency Locking**: Wired runtime directive inspection (`-d rubberstamp.tag=[<app>][rubberstamp]-browser-test`), relative `artisan` command line recognition, and active session state locking (`rubberstamp-session.json`) into `BrowserProcessSanitizer` across Windows CIM and POSIX process discovery. Instantly detects active or orphaned test sessions across multiple terminal windows before they can conflict on ports or corrupt test artifacts, while maintaining strict immunity for developer servers (`php artisan serve`, `php artisan queue:listen`, etc.).

## [v0.2.5](https://github.com/eldinbiz/laravel-rubberstamp/compare/v0.2.4...v0.2.5) - 2026-10-09

### Fixed
- **Playwright Browser Worker Path Detection**: Enhanced `BrowserProcessSanitizer` to identify all Playwright browser workers (including GPU and renderer child processes) by binary installation path (`ms-playwright` and `chromium_headless_shell`). Prevents orphaned helper processes from holding GPU/file locks across consecutive test runs while strictly preserving user Google Chrome instances.
- **Development Tool Protection**: Eliminated false-positive detection of portless `node.exe` processes (such as `npx concurrently`, `npm run dev`, `vite`, and build watchers). Lingering process cleanup now exclusively relies on high-fidelity CIM command-line inspection and the recorded test port from `playwright-server.json`, preventing accidental termination of `composer dev` processes.
- **Unit & Feature Test Coverage**: Added tests in `BrowserProcessSanitizerTest` ensuring CLI dev runners/watchers are ignored, tagged Pest runners and sibling browser sessions are identified, self PID is immune, and `ms-playwright` browser workers are reaped; updated `TestBrowserCommandTest` to assert `--idle-timeout` configuration and options.

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
