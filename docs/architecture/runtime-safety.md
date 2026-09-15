# SourceSlate Runtime Safety

## Build publication

SourceSlate renders documentation into a staging directory before publishing it. The destination is never written incrementally.

Publication uses a destination-scoped lock. Under that lock SourceSlate:

1. recovers an interrupted prior publication when necessary;
2. preserves the current published tree as a temporary backup;
3. atomically renames the completed staging tree into place;
4. restores the previous tree when publication fails;
5. removes obsolete backup trees after success.

Staging directories carry ownership metadata. Fresh owned staging directories are preserved; abandoned, malformed, or stale staging directories are reclaimed.

## Git cache lock hierarchy

Cache concurrency uses a fixed lock order:

1. cache maintenance lock;
2. repository operation lock;
3. repository fetch lock.

The cache maintenance lock is shared for ordinary repository operations and exclusive for whole-cache deletion. Repository operation locks are stable even while a repository directory is replaced because they live in a sibling lock namespace outside the replaceable cache root.

This prevents lock-order inversion and prevents `cache:repair`, `cache:prune`, source resolution, and `cache:clear` from racing destructive filesystem operations.

## Metadata durability

Cache metadata is written to a temporary sibling file, flushed, `fsync()`ed when supported, and renamed over the canonical metadata file. A failed write therefore cannot leave a partially written `metadata.json` as the published state.

## Output boundary

Output directories must be ordinary directories. Symbolic-link output paths and symbolic-link ancestors are rejected, including when `--force-output` is present. Remote Git output is additionally forbidden inside the cached source workspace or SourceSlate cache.

## Recovery principle

SourceSlate favors the last known-good state. Operations that replace documentation or cache state first build and validate a candidate, then swap it into place. Failures before the swap leave the previous state unchanged; failures during the swap attempt restoration before returning an error.
