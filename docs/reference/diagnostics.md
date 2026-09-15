# Diagnostic Codes

SourceSlate diagnostics use stable identifiers so CI and automation do not need to parse prose.

## Configuration

| Code | Meaning |
| --- | --- |
| `SS-CFG-0001` | Configuration could not be resolved or validated. |
| `SS-CFG-0011` | Invalid Git timeout configuration. |

## Source and Git

| Code | Meaning |
| --- | --- |
| `SS-SRC-0010` | Requested source mutation is not permitted for the resolved source type. |
| `SS-SRC-0012` | Invalid source path or attempted escape from the repository worktree. |
| `SS-GIT-0020` | Git executable is unavailable. |
| `SS-GIT-0021` | Repository is unavailable or an offline cache miss occurred. |
| `SS-GIT-0022` | Git authentication failed. |
| `SS-GIT-0023` | Requested revision could not be resolved. |
| `SS-GIT-0025` | General Git operation failure. |
| `SS-GIT-0026` | Git command timeout. |
| `SS-GIT-0108` | Plain ref name is ambiguous between branch and tag. |
| `SS-GIT-0109` | Abbreviated commit SHA is ambiguous. |

## Output and documentation

| Code | Meaning |
| --- | --- |
| `SS-OUT-0040` | Invalid output path or missing required remote output. |
| `SS-OUT-0041` | Unsafe or unmanaged output path. |
| `SS-OUT-0042` | Output path traverses a symbolic link. |
| `SS-DOC-0050` | Published documentation differs from a deterministic staged build. |

## Cache

| Code | Meaning |
| --- | --- |
| `SS-CACHE-0010` | Destructive cache operation requires explicit confirmation. |
| `SS-CACHE-0011` | Invalid cache age expression. |
| `SS-CACHE-0012` | Invalid cache size expression. |
| `SS-CACHE-0024` | Cache filesystem mutation failed. |
| `SS-CACHE-0025` | Cache entry is active and cannot be destructively modified. |
| `SS-CACHE-0201` | Cached bare repository is missing. |
| `SS-CACHE-0202` | Cache metadata is missing, malformed, incompatible, or inconsistent. |
| `SS-CACHE-0203` | Cache verification failed. |
| `SS-CACHE-0404` | Requested repository is not present in cache. |

## Doctor

| Code | Meaning |
| --- | --- |
| `SS-DOC-1001` | Unsupported PHP version. |
| `SS-DOC-1002` | Required PHP extension is missing. |
| `SS-DOC-1003` | `proc_open` is unavailable. |
| `SS-DOC-1004` | Git is unavailable. |
| `SS-DOC-1005` | Cache location is not writable. |
| `SS-DOC-1006` | Lock location is not writable. |
| `SS-DOC-1007` | Repair/cache debris requires cleanup or inspection. |

## Internal failures

`SS-INT-0001` represents an unexpected internal error that was not already mapped to a more specific SourceSlate diagnostic. Treat it as a defect or unsupported environmental failure rather than a user-input validation error.
