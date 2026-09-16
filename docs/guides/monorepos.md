# Using SourceSlate in Monorepos

SourceSlate can document an entire repository or a selected source subtree. In a monorepo, the important distinction is between the **repository root** and the **documentation source root**.

## Repository root versus source path

The repository root is the enclosing Git/project boundary. It is used for repository-level configuration and source identity.

The source path is the subtree SourceSlate parses. Select it with:

```bash
sourceslate . --source-path=packages/payments
```

For a remote repository:

```bash
sourceslate https://github.com/example/platform.git \
  --branch=main \
  --source-path=packages/payments \
  --output=docs/payments
```

`--source-path` is containment-checked and cannot escape the resolved repository worktree.

## Configuration precedence in a monorepo

SourceSlate resolves configuration in this order:

1. explicit `--config`
2. `SOURCESLATE_CONFIG`
3. selected source-path `sourceslate.yaml`
4. repository-root `sourceslate.yaml`
5. user configuration
6. defaults

This permits a repository to define shared defaults while an individual package overrides documentation-specific settings.

Example repository layout:

```text
platform/
  sourceslate.yaml
  packages/
    billing/
      sourceslate.yaml
      src/
    identity/
      src/
```

If `packages/billing` is selected as the source path, `packages/billing/sourceslate.yaml` takes precedence over the repository-root file for allowed project settings.

## Recommended output layout

Keep package output deterministic and non-overlapping:

```text
docs/
  billing/
  identity/
  shared/
```

Example:

```bash
sourceslate . --source-path=packages/billing --output=docs/billing
sourceslate . --source-path=packages/identity --output=docs/identity
```

Do not point multiple simultaneous builds at the same output directory. Publication is serialized per destination, but independently generated package sites should have independent output roots.

## CI matrix example

A package matrix avoids one giant documentation job:

```yaml
strategy:
  matrix:
    package:
      - billing
      - identity
      - shared

steps:
  - uses: actions/checkout@v4
  - run: composer install --no-interaction --prefer-dist
  - run: >-
      php bin/sourceslate build .
      --source-path=packages/${{ matrix.package }}
      --output=.sourceslate/${{ matrix.package }}
  - run: >-
      php bin/sourceslate build .
      --source-path=packages/${{ matrix.package }}
      --output=.sourceslate/${{ matrix.package }}
      --check
```

## Remote cache behavior

A remote monorepo is cloned once per canonical repository identity into SourceSlate's persistent cache. Different `--source-path` selections can reuse the same resolved repository/worktree when the commit is unchanged and the cached worktree is clean.

This is intentional: cache identity is repository-based, not package-based.

## Source-header updates

`--update-source` is local-only and documentation output must remain inside the local project root.

For package-specific documentation, use an in-repository output path such as:

```bash
sourceslate . \
  --source-path=packages/billing \
  --output=docs/billing \
  --update-source
```

The generated `@sourceslate` link remains project-relative and portable.

## Multiple package entry points

SourceSlate scans all configured PHP source paths beneath the selected source root. A package-level configuration can therefore include more than one tree:

```yaml
project:
  name: Billing
source:
  paths:
    - src
    - contracts
  exclude:
    - fixtures
output:
  path: ../../../docs/billing
```

When `source_headers.update` is enabled, ensure the resolved output remains inside the repository/project boundary or the build will fail with `SS-SRC-0015`.

## Namespace collisions

The symbol index is project-wide for each SourceSlate build. If two selected source trees expose the same short symbol name, short-name resolution may be ambiguous. Fully qualified PHP names remain deterministic.

For large monorepos, prefer namespace conventions that mirror package ownership and use fully qualified references in `@see` tags when short names could collide.

## When to build one site versus many

Build one site when packages share a coherent API surface and cross-package symbol navigation is important.

Build separate sites when packages are released independently, have separate audiences, or have conflicting short symbol names/configuration requirements.

The build topology should follow the ownership and release boundary of the code, not merely the repository layout.
