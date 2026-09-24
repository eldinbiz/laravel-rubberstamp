# Release Notes

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
