# Troubleshooting SourceSlate

This guide maps common failure modes to the diagnostic commands and stable error codes exposed by SourceSlate 1.0.

## Start with `doctor`

Run:

```bash
sourceslate doctor --json
```

Use the JSON form in CI and automation. It validates PHP, required extensions, `proc_open`, Git, cache and lock locations, cache debris, shallow cache-entry integrity, project/configuration resolution, source paths, output writability, and output symlink safety.

For repository-object integrity, run:

```bash
sourceslate cache:verify --json
```

`doctor` is intentionally lightweight; `cache:verify` performs the deeper Git checks.

## Build fails with an output diagnostic

`SS-OUT-0040` means the output path is invalid, unreadable, or not a directory. Confirm the parent path exists or is writable.

`SS-OUT-0041` means SourceSlate refused to replace unmanaged content or detected an unsafe remote-output location. Use a dedicated documentation directory. `--force-output` permits replacement of unmanaged output but does not bypass symlink or remote-cache safety checks.

If a build was interrupted during publication, the next `BuildStagingArea` initialization recovers the newest last-known-good backup when the destination is missing. Do not manually delete `.sourceslate-previous-*` directories before recovery unless you have verified their contents.

## Remote repository failures

`SS-GIT-0020` — Git is unavailable on `PATH`.

`SS-GIT-0021` — the repository cannot be obtained, including an offline cache miss.

`SS-GIT-0022` — authentication failed. Verify the credentials managed by your normal Git credential helper or SSH agent. SourceSlate does not persist passwords, tokens, or private keys in cache metadata.

`SS-GIT-0023` — the requested revision cannot be resolved.

`SS-GIT-0108` — a plain `--ref` matches both a branch and a tag. Use `--branch` or `--tag` explicitly.

`SS-GIT-0109` — a shortened commit SHA is ambiguous. Provide a longer or full 40-character SHA.

Use `--offline` only after the repository and requested revision are present in SourceSlate's persistent cache.

## Cache failures

Inspect cache state:

```bash
sourceslate cache:list --json
sourceslate cache:verify --json
```

Repair a known repository only after verification identifies a damaged entry:

```bash
sourceslate cache:repair https://example.invalid/org/repo.git --yes --json
```

Repair is transactional: the replacement is cloned and validated before it replaces the existing cache entry. Active entries are protected from destructive maintenance.

Remove stale interrupted-repair debris and released lock files with:

```bash
sourceslate cache:clean --older-than=24h --dry-run --json
sourceslate cache:clean --older-than=24h --json
```

## Source-header update failures

`--update-source` is local-only. Remote Git worktrees are immutable from the user's perspective.

`SS-SRC-0015` means documentation output is outside the local project tree. SourceSlate refuses to write an absolute host filesystem path into `@sourceslate` metadata. Put documentation inside the project when source-header mutation is enabled.

Source updates are atomic and idempotent. If a second identical build reports zero source-header changes, that is expected.

## Documentation is reported stale

`SS-DOC-0050` is returned by:

```bash
sourceslate build . --check
```

It means a deterministic staging render differs from the published output. Rebuild the documentation, review the generated changes, and rerun `--check`.

## Configuration failures

Use:

```bash
sourceslate config:show .
```

to inspect the resolved configuration after precedence is applied. Precedence is:

1. explicit `--config`
2. `SOURCESLATE_CONFIG`
3. source-path `sourceslate.yaml`
4. repository-root `sourceslate.yaml`
5. user configuration
6. defaults

Repository-controlled configuration is intentionally restricted from defining credential/security/cache ownership settings.

## Collecting a reproducible report

For a useful issue report, capture:

```bash
sourceslate --version
sourceslate doctor --json
sourceslate config:show .
sourceslate cache:verify --json
```

Also include the exact SourceSlate command, operating system, PHP version, and the relevant stable diagnostic code. Never include access tokens, passwords, SSH private keys, or credential-helper output.
