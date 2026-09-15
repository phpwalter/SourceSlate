# Changelog

All notable SourceSlate changes are documented here.

SourceSlate follows Semantic Versioning once `1.0.0` is released. During the pre-1.0 period, minor releases may introduce contract changes when they are documented here and in release notes.

## [Unreleased]

### Added

- persistent remote Git repository cache with offline reuse;
- branch, tag, full SHA, and abbreviated SHA resolution;
- transaction-safe cache repair and cache verification;
- cache prune, cleanup, information, and clear commands;
- cache-wide and repository-specific concurrency locks;
- atomic cache metadata writes;
- atomic generated-document publication and interrupted-publication recovery;
- local `@sourceslate` source-header updates with dry-run support;
- deterministic documentation comparison through `--check`;
- PHPDoc semantic tag dispatch with unknown-tag preservation;
- project symbol indexing and ambiguity-safe resolution;
- static documentation for namespaces, types, members, functions, and source files;
- generated search index and keyboard-accessible client search;
- responsive light/dark Material-based documentation theme;
- resolved configuration inspection via `config:show`;
- expanded `doctor --json` diagnostics;
- Linux/Windows PHP 8.3/8.4 CI matrix;
- reproducibility regression testing and benchmark tooling;
- Composer release archive workflow;
- installation, configuration, remote repository, CI, PHPDoc, diagnostics, runtime-safety, and acceptance documentation.

### Security

- remote outputs cannot target cached source worktrees or the SourceSlate cache;
- symbolic-link output paths are rejected;
- generated HTML text is escaped;
- search results are created through DOM APIs rather than `innerHTML` interpolation;
- repository credentials are redacted from cache identity metadata.

## Versioning policy

- Patch releases fix defects without intentionally changing documented contracts.
- Minor releases add backwards-compatible features.
- Major releases may change configuration, CLI, cache schema, or generated-output contracts.
- Diagnostic identifiers are treated as automation-facing contracts and should not be repurposed.
- Cache metadata schema changes require explicit compatibility or migration behavior.
