# RubberStamp

This repository is a Laravel package. Keep the package focused, idiomatic, and easy for Laravel developers to install, test, and maintain.

## AI Assistant Directives

### 1. Pre-Planning Clarification & Discovery First
- Before creating any plan, if requirements, architectural choices, scope, or design preferences are underspecified or ambiguous, **ask all clarifying questions beforehand** (using the `ask_question` tool or concise targeted questions in chat).
- Never make silent assumptions on ambiguous requirements. Resolve all uncertainties with the user before proceeding to the planning phase.

### 2. Default Mode: Implementation & Fix Plans as Dedicated Artifacts
- **Always operate in Planning / Consultation mode by default.**
- **Do NOT modify code files directly** on the initial turn unless explicitly instructed to implement immediately.
- **Never dump full implementation or bug-fix plans in the chat response.**
- All implementation plans, architectural proposals, and bug-fix remediation plans MUST be created as a dedicated Markdown artifact (`<feature_or_bug>_plan.md`) in the artifacts directory using `write_to_file` with:
  ```json
  "ArtifactMetadata": {
    "UserFacing": true,
    "RequestFeedback": true,
    "Summary": "Brief overview of what the plan accomplishes"
  }
  ```
  *(Setting `RequestFeedback: true` triggers the interactive IDE **"Proceed"** button above the artifact viewer).*
- The plan artifact must structure the proposal clearly:
  1. **Overview & Objective**: What will be accomplished.
  2. **Clarifications & Design Decisions**: Summary of resolved user answers and architectural choices.
  3. **Proposed Architecture / Changes**: Files and components affected.
  4. **Step-by-Step Implementation Plan**: The exact sequence of edits or additions to be made.
  5. **Verification & Testing Plan**: How the changes will be validated.
- **Chat Response Restriction**: Keep the chat response strictly to a concise 1–2 sentence summary linking to the artifact (`[plan_name.md](file:///...)`) and prompting the user to review the artifact and click "Proceed" (or reply with confirmation).

### 3. Execution / Implementation Mode
- **Only proceed with code modifications** when the user explicitly orders or approves implementation (e.g., clicking the interactive *"Proceed"* button or replying *"Implement the plan"*, *"Proceed with changes"*, *"Go ahead and code it"*).
- When explicitly authorized to implement, execute the approved plan systematically.

### 4. Bug Fixing & Diagnostic Mode Protocol
- **No Immediate Code Changes on Bug Reports:** When investigating reported bugs, defects, unexpected behavior, or test failures, do NOT immediately apply code fixes.
- **Read-Only Diagnosis First:**
  1. Inspect relevant log files, stack traces, and source code.
  2. Explain the **Root Cause** of why the failure or defect occurs.
  3. If there are ambiguities in how to remediate, ask clarifying questions first.
  4. Present the remediation plan as a dedicated Markdown artifact (`<bug_name>_fix_plan.md`) with `RequestFeedback: true`.
- **Wait for Confirmation:** Always wait for explicit user approval (e.g., clicking *"Proceed"* or replying *"Apply the fix"*) before modifying any files.

## Package Conventions

- Use Laravel-native package APIs and the existing service provider shape before adding abstractions.
- Keep package names, namespaces, Composer metadata, publish tags, documentation, and examples aligned with `eldinbiz/laravel-rubberstamp`.
- Add only the files and dependencies needed for the package behavior being implemented.
- Prefer explicit Laravel package code over helper abstractions unless the extension point is real.
- Keep tests focused on observable package behavior through public APIs, service provider wiring, commands, routes, published resources, and documentation promises.

## Quick Commands

- Full validation: `composer test`
- Formatting check: `composer lint:check`
- Static analysis: `composer analyse`
- Pest tests: `composer test:unit`
- Workbench build: `composer build`
- Workbench server: `composer serve`

## Local Skills

- `package-scaffold`: use when adding package capabilities or wiring them through the service provider, including commands, migrations, routes, config, views, translations, assets, middleware, publish tags, workbench files, and console-only behavior.
- `package-testing`: use when adding or changing package tests with Pest 4/5 and Orchestra Testbench.
- `package-release`: use when preparing changelog, release notes, tags, or GitHub release workflow changes.
- `package-compatibility`: use when reviewing code, dependencies, or CI against the PHP and Laravel support matrix.
- `package-generate-skill`: use when updating the bundled Boost skill from the package implementation, README, and examples.
