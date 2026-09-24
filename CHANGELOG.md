# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## v4.1.3 - 2026-09-24

<!-- Release notes generated using configuration in .github/release.yml at 4.x -->
### What's Changed

#### Other Changes

* build(deps): bump zizmorcore/zizmor-action from 0.6.3 to 0.6.4 in the github-actions group by @dependabot[bot] in https://github.com/relaticle/flowforge/pull/186
* build(deps): bump the npm-minor-patch group in /docs with 2 updates by @dependabot[bot] in https://github.com/relaticle/flowforge/pull/187
* build(deps): bump @unhead/vue from 3.4.0 to 3.4.1 in /docs in the npm-minor-patch group by @dependabot[bot] in https://github.com/relaticle/flowforge/pull/189
* fix(board): render page header actions with headerToolbar() [4.x] by @ManukMinasyan in https://github.com/relaticle/flowforge/pull/190

**Full Changelog**: https://github.com/relaticle/flowforge/compare/v4.1.2...v4.1.3

## v4.0.12 - 2026-05-12

### What's Changed

#### Other Changes

- ci: make release workflow idempotent [4.x] by @ManukMinasyan in https://github.com/relaticle/flowforge/pull/117
- build(deps): bump the npm_and_yarn group across 2 directories with 1 update by @dependabot[bot] in https://github.com/relaticle/flowforge/pull/118
- build(deps): bump ip-address from 10.1.0 to 10.2.0 in /docs in the npm_and_yarn group across 1 directory by @dependabot[bot] in https://github.com/relaticle/flowforge/pull/120
- build(deps): bump the npm_and_yarn group across 2 directories with 4 updates by @dependabot[bot] in https://github.com/relaticle/flowforge/pull/121
- build(deps): bump nuxt-og-image from 6.3.7 to 6.5.0 in /docs in the npm_and_yarn group across 1 directory by @dependabot[bot] in https://github.com/relaticle/flowforge/pull/122
- build(deps): bump fast-uri from 3.1.0 to 3.1.2 in /docs in the npm_and_yarn group across 1 directory by @dependabot[bot] in https://github.com/relaticle/flowforge/pull/123
- fix: pass 'column' argument to CreateAction mutateDataUsing by @eduardoribeirodev in https://github.com/relaticle/flowforge/pull/124

**Full Changelog**: https://github.com/relaticle/flowforge/compare/v4.0.11...v4.0.12

## v4.0.11 - 2026-04-25

### What's Changed

#### Other Changes

- Add enum helper to column by @paulhennell in https://github.com/relaticle/flowforge/pull/100
- Adding filament style hidden option to columns by @paulhennell in https://github.com/relaticle/flowforge/pull/101
- build(deps): bump dependabot/fetch-metadata from 3.0.0 to 3.1.0 by @dependabot[bot] in https://github.com/relaticle/flowforge/pull/115
- 4.x enum helper by @cheesegrits in https://github.com/relaticle/flowforge/pull/116

### New Contributors

- @paulhennell made their first contribution in https://github.com/relaticle/flowforge/pull/100

**Full Changelog**: https://github.com/relaticle/flowforge/compare/v4.0.10...v4.0.11

## v4.0.10 - 2026-04-17

### What's Changed

#### Other Changes

- build(deps-dev): bump follow-redirects from 1.15.11 to 1.16.0 in the npm_and_yarn group across 1 directory by @dependabot[bot] in https://github.com/relaticle/flowforge/pull/111
- build(deps): bump hono from 4.12.12 to 4.12.14 in /docs in the npm_and_yarn group across 1 directory by @dependabot[bot] in https://github.com/relaticle/flowforge/pull/112
- fix: correct top-drop position to stay at top [4.x] by @ManukMinasyan in https://github.com/relaticle/flowforge/pull/114

**Full Changelog**: https://github.com/relaticle/flowforge/compare/v4.0.9...v4.0.10

## v4.0.9 - 2026-04-10

### Fixed

- Improve drag-and-drop scroll sensitivity in columns

### Docs

- Remove redundant introduction page from getting-started

## v4.0.8 - 2026-03-16

### Fixes

- Fix nested `fi-page-header-main-ctn` wrapper causing board page headings to sit lower than resource list pages
- Persist search query in URL (`?search=`) matching existing filter URL persistence
- Remove redundant "remove all filters" button from indicator row (Reset in dropdown handles this)
- Use `gap-x-5` instead of `gap-5` on board column container

## v4.0.7 - 2026-03-16

### What's Changed

#### New Features

- **Header Toolbar**: New `headerToolbar()` method renders filter/search controls inline with the page title for a compact board layout. Supports Dropdown and Modal filter layouts. Disabled by default for backward compatibility.

#### Bug Fixes

- Fix filter dropdown styling by using native Filament CSS context (`fi-ta-ctn`) instead of inline JS workaround
- Add proper Modal/slide-over support to header toolbar
- Respect `TablesRenderHook::FILTER_INDICATORS` in header toolbar

#### Documentation

- Add `headerToolbar()` to API reference and customization guide

**Full Changelog**: https://github.com/relaticle/flowforge/compare/v4.0.6...v4.0.7

## v4.0.5 - 2026-03-06

### Fixed

- Cast record ID to string in `formatBoardRecord()` to prevent JavaScript precision loss with large integer IDs (snowflakes) (#88, #92)

## v4.0.4 - 2026-03-01

### What's Changed

* fix: support standalone Filament usage without panel dependency by @ManukMinasyan in https://github.com/relaticle/flowforge/pull/86

**Full Changelog**: https://github.com/relaticle/flowforge/compare/v4.0.3...v4.0.4

## v4.0.2 - 2026-01-28

### Fixed

- Fixed card ID attributes not being present for drag operations, preventing potential errors during card moves

## v4.0.1 - 2026-01-28

### Bug Fixes

- Import DB facade for database operations in HasBoardRecords (#80)

## [4.0.0] - 2026-01-17

### Breaking Changes

- **Filament Version**: Now requires Filament 5.x (was 4.x)

### Added

- Full compatibility with Filament 5.x

### Migration

See [Upgrading Guide](docs/content/1.getting-started/4.upgrading.md) for migration instructions from v3.x.


---

## [3.0.0] - 2025-12-26

### Breaking Changes

- **Position column type changed** from `VARCHAR` to `DECIMAL(20,10)`
- **New dependency**: `ext-bcmath` PHP extension required
- **Removed**: `Rank.php` service (Lexorank algorithm)
- **Laravel version**: Now requires Laravel 12+

### Added

- `DecimalPosition` service with BCMath-based position calculations
- `PositionRebalancer` service for automatic gap management
- Cryptographic jitter (±5%) prevents concurrent insertion collisions
- Auto-rebalancing when gap falls below 0.0001
- Retry mechanism with exponential backoff (50ms, 100ms, 200ms)
- `MaxRetriesExceededException` for conflict handling
- `flowforge:diagnose-positions` command - detect gaps, inversions, duplicates
- `flowforge:rebalance-positions` command - redistribute positions evenly
- Support for custom primary keys via `getKeyName()`
- Comprehensive logging of rebalancing operations
- `UPGRADE.md` migration guide for v2.x users

### Changed

- Position algorithm from Lexorank (string) to DecimalPosition (decimal)
- Blueprint macro `flowforgePositionColumn()` now creates `DECIMAL(20,10)`
- `flowforge:repair-positions` command now interactive with multiple strategies

### Removed

- `Rank.php` service
- String-based position calculations
- Binary collation requirements

### Migration

See [UPGRADE.md](UPGRADE.md) for detailed migration instructions from v2.x.


---

## [2.1.0] - Previous stable release

See [v2.x branch](https://github.com/Relaticle/flowforge/tree/2.x) for v2.x changelog.


---

## 0.2.1 - 2025-05-29

### What's Changed

* Bump dependabot/fetch-metadata from 2.3.0 to 2.4.0 by @dependabot in https://github.com/Relaticle/flowforge/pull/10
* Fix empty translation file causing array_replace_recursive() error by @vasilGerginski in https://github.com/Relaticle/flowforge/pull/13

### New Contributors

* @dependabot made their first contribution in https://github.com/Relaticle/flowforge/pull/10
* @vasilGerginski made their first contribution in https://github.com/Relaticle/flowforge/pull/13

**Full Changelog**: https://github.com/Relaticle/flowforge/compare/0.2.0...0.2.1

## 0.2.0 - 2025-04-22

**Full Changelog**: https://github.com/Relaticle/flowforge/compare/0.1.9...0.2.0

## 0.1.9 - 2025-04-16

**Full Changelog**: https://github.com/Relaticle/flowforge/compare/0.1.7...0.1.9

**Full Changelog**: https://github.com/Relaticle/flowforge/compare/0.1.7...0.1.9

## [Unreleased]

### Added

- Enhanced developer experience with improved documentation
- New QUICK-START.md guide for rapid onboarding
- New DEVELOPMENT.md guide for contributors
- Restructured README.md with better organization and examples
- Model existence validation in generator command
- Detailed troubleshooting section with common solutions
- Comprehensive examples for all configuration options
- Clear distinction between required and optional methods
- Added read-only board implementation examples
- Added separate stub files for create and edit actions

### Changed

- Completely redesigned code generation approach for true minimalism
- Removed all PHPDocs from generated files for cleaner code
- Radically simplified MakeKanbanBoardCommand to only ask for board name and model
- Removed all interactive prompts for configuration options
- Always generates a minimal read-only board as starting point
- Reduced comments and unnecessary code in generated files
- Enhanced stub templates for minimal, clean implementation
- Reorganized documentation with clearer structure
- Improved error messages and validation in code generator
- Clarified that createAction() and editAction() methods are optional
- Made generated code reflect the optional nature of interactive features
- Simplified documentation for minimal implementation
- Improved modularity by separating method templates into dedicated files
- Adopted a true "convention over configuration" approach for better DX

## [1.0.0] - 2023-04-XX

### Added

- Initial release
