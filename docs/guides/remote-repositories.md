# Remote Repositories

SourceSlate can document a local project or a remote Git repository.

## Basic use

```bash
sourceslate https://github.com/vendor/project.git --output ./docs/project
```

Remote sources require an explicit `--output` path. SourceSlate never writes generated documentation into its own persistent Git cache.

## Selecting a revision

Use exactly one selector:

```bash
sourceslate https://github.com/vendor/project.git --branch main --output ./docs/project
sourceslate https://github.com/vendor/project.git --tag v1.4.0 --output ./docs/project
sourceslate https://github.com/vendor/project.git --ref 0123456789abcdef0123456789abcdef01234567 --output ./docs/project
```

Short commit prefixes are accepted only when they resolve unambiguously. A name that exists as both a branch and a tag is rejected; use `--branch` or `--tag` to state intent.

## Monorepositories

`--source-path` selects a subtree after the repository has been resolved:

```bash
sourceslate https://github.com/vendor/monorepo.git \
  --branch main \
  --source-path packages/api \
  --output ./docs/api
```

The selected path must remain inside the resolved worktree.

## Persistent cache

Remote repositories are stored in the SourceSlate cache. Subsequent runs fetch into the existing bare repository and reuse clean commit-addressed worktrees.

Use `--offline` to prohibit network access. An offline build succeeds only when the requested repository and revision can be resolved from cached state.

`--refresh` forces remote revalidation. If a non-authentication network failure occurs after a usable commit has already been resolved locally, SourceSlate may continue with cached state and reports `cached-unverified`.

## Cache maintenance

```bash
sourceslate cache:list
sourceslate cache:info https://github.com/vendor/project.git
sourceslate cache:verify
sourceslate cache:prune --older-than 90d
sourceslate cache:prune --older-than 30d --max-size 10GB
sourceslate cache:repair https://github.com/vendor/project.git --yes
sourceslate cache:clear --yes
```

`cache:repair` is transactional: a candidate bare repository is cloned and verified before it replaces the current cache entry.

## Submodules

Use `--recurse-submodules` when documentation requires source stored in Git submodules. SourceSlate does not recurse into submodules unless explicitly requested.

## Credentials

SourceSlate relies on Git's normal credential mechanisms. Credentials embedded in transport URLs are redacted before cache identity metadata is emitted. Prefer credential managers, SSH agents, or provider-specific credential helpers over URLs containing secrets.
