# SourceSlate Security Model

SourceSlate reads source code, may clone remote Git repositories, generates static documentation, and can optionally update local PHP source headers. Those operations cross several trust boundaries. SourceSlate 1.0 therefore defaults to explicit failure instead of guessing.

## Trust boundaries

SourceSlate treats these inputs as potentially untrusted:

- PHP source code and PHPDoc text
- repository-controlled `sourceslate.yaml`
- remote repository URLs and refs
- generated symbol names and descriptions
- existing output directories
- persistent cache metadata and cached worktrees

The local user environment, explicit CLI options, and user-level configuration are trusted at a higher level than repository-controlled configuration.

## Credential handling

SourceSlate delegates Git authentication to Git itself. HTTPS credential helpers and SSH agents remain responsible for secrets.

SourceSlate does not intentionally persist passwords, personal access tokens, SSH private keys, or credential-helper output in generated documentation or Git cache metadata. Repository URLs are normalized and credentials are redacted before metadata is persisted or displayed.

Do not place secrets directly in repository URLs in shell history, CI logs, or configuration files.

## Repository configuration restrictions

Repository-controlled configuration cannot override security-sensitive top-level settings such as credential, cache, or security ownership controls. Those settings belong to user/explicit configuration layers.

This prevents a repository being documented from silently redirecting SourceSlate's cache or credential-related behavior.

## Output safety

SourceSlate refuses to publish into a non-directory path and refuses to replace unmanaged non-empty output unless `--force-output` is supplied.

`--force-output` is not a general safety bypass. Symlinked output paths and symlinked path components remain rejected to prevent publication outside the intended filesystem boundary.

Remote Git output is forbidden inside the cached source worktree or SourceSlate cache.

Publication uses staging plus replacement semantics. Existing documentation is moved to a temporary last-known-good backup before the new tree is published. If publication fails, SourceSlate attempts to restore the previous tree. If restoration also fails, the backup path is preserved for manual recovery.

## Generated HTML and JavaScript

Source/PHPDoc text is escaped before server-side HTML insertion.

Client-side search does not inject generated symbol data through `innerHTML`. Search entries are created as DOM nodes and populated through text properties. This prevents generated names or PHPDoc summaries from becoming executable markup.

Generated documentation remains static and does not execute PHP source code.

## Git cache integrity

Cache entries are keyed by canonical repository identity. Maintenance operations use cache-wide and per-repository locks.

`cache:verify` checks:

- metadata structure and supported schema
- metadata/cache-directory identity consistency
- bare repository presence
- Git object integrity
- cached `origin` identity
- cached worktree validity

`cache:repair` builds a replacement beside the current entry, validates it, and only then swaps it into place. Active entries cannot be repaired, pruned, or cleared.

## Remote source immutability

SourceSlate never permits `--update-source` against a remote Git source. Cached remote worktrees are documentation inputs, not user-editable project trees.

Dirty cached worktrees are not reused for future resolution. A clean worktree for the requested commit is prepared instead.

## Local source mutation

Local `--update-source` is opt-in or explicitly enabled by trusted configuration. Updates are idempotent and use temporary/backup files for atomic replacement.

If documentation output is outside the project root, SourceSlate rejects source-header mutation with `SS-SRC-0015` rather than writing an absolute host path into source files.

## Submodules

Recursive submodule checkout is opt-in. SourceSlate passes submodule resolution to Git and therefore inherits Git's transport and authentication policy. Do not weaken Git protocol restrictions globally merely to make an untrusted repository's submodules resolve.

## Recommended CI posture

For CI:

```bash
composer install --no-interaction --prefer-dist
composer check
php bin/sourceslate doctor --json
php bin/sourceslate build . --config=sourceslate.example.yaml --output=.sourceslate-ci-docs
php bin/sourceslate build . --config=sourceslate.example.yaml --output=.sourceslate-ci-docs --check
```

Use read-only repository credentials whenever possible. Keep SourceSlate cache locations outside published artifacts, and never upload credential-helper files or SSH material as build artifacts.

## Reporting security issues

Security reports should include the SourceSlate version, operating system, PHP version, exact diagnostic code, and a minimal reproducer that contains no production secrets. Avoid attaching real credentials or private repository contents unless a secure disclosure channel has been explicitly established.
