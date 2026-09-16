# SourceSlate

SourceSlate is a deterministic static documentation generator for PHP source code and PHPDoc. Point it at a local PHP codebase or a remote Git repository and it produces interconnected, searchable static documentation with source links, symbol relationships, PHPDoc metadata, and reproducible output.

SourceSlate 1.0 targets PHP 8.3 and PHP 8.4 and is designed for local development, CI, monorepos, and cached remote-repository documentation builds.

## Requirements

- PHP 8.3 or newer
- Composer
- Git when documenting remote repositories

## Installation

During development:

```powershell
cd L:\var\www\SourceSlate
composer install
php bin\sourceslate --version
```

The Composer package exposes the `sourceslate` binary, so the intended installed form is:

```bash
composer global require phpwalter/sourceslate
sourceslate --version
```

Until the package is published or refreshed on Packagist, use the development checkout or a Composer path repository. See `docs/guides/installation.md` for Windows, Linux, macOS, global Composer, and upgrade guidance.

## Local usage

The preferred zero-configuration form is:

```bash
sourceslate .
```

It is equivalent to:

```bash
sourceslate build .
```

You can target another local project directly:

```bash
sourceslate /path/to/project
```

SourceSlate reads `sourceslate.yaml` when present and otherwise applies deterministic defaults.

Useful local controls include:

```bash
sourceslate . --config=sourceslate.yaml
sourceslate . --output=docs-generated
sourceslate . --dry-run
sourceslate . --json
sourceslate . --ci
sourceslate . --check
sourceslate . --update-source
```

`--check` renders into staging and fails when the deterministic generated tree differs from the currently published output.

`--update-source` updates local PHP source headers with portable `@sourceslate` documentation links. Updates are atomic and idempotent. Source mutation is forbidden for remote Git sources, and the documentation output must remain inside the local project tree when source-header updates are enabled.

## Remote Git repositories

Remote builds require an explicit output directory because cached worktrees are documentation inputs and are never publication destinations.

```bash
sourceslate https://github.com/vendor/project.git --output=docs-generated
```

Select source state explicitly when needed:

```bash
sourceslate https://github.com/vendor/project.git --branch=develop --output=docs-generated
sourceslate https://github.com/vendor/project.git --tag=v2.4.0 --output=docs-generated
sourceslate https://github.com/vendor/project.git --ref=0123456789abcdef0123456789abcdef01234567 --output=docs-generated
```

For monorepos:

```bash
sourceslate https://github.com/vendor/monorepo.git \
  --source-path=packages/api \
  --output=docs-api
```

Remote-source controls include:

```bash
sourceslate <repository> --output=docs --refresh
sourceslate <repository> --output=docs --offline
sourceslate <repository> --output=docs --recurse-submodules
sourceslate <repository> --output=docs --git-timeout=120
sourceslate <repository> --output=docs --dry-run
sourceslate <repository> --output=docs --json
```

SourceSlate resolves the requested ref to a commit SHA and records that state in build/cache metadata. Plain ref names that collide between a branch and tag are rejected rather than guessed; use `--branch` or `--tag` to disambiguate.

See `docs/guides/remote-repositories.md` and `docs/guides/monorepos.md`.

## Persistent Git cache

Remote repositories are stored in a persistent canonical repository cache so repeated builds can reuse existing objects and clean worktrees.

Cache commands:

```bash
sourceslate cache:list --json
sourceslate cache:info <repository>
sourceslate cache:verify --json
sourceslate cache:repair <repository> --yes --json
sourceslate cache:prune --older-than=90d --dry-run --json
sourceslate cache:clean --older-than=24h --dry-run --json
sourceslate cache:clear --yes --json
```

Cache operations use maintenance and repository locks. Active entries are protected from destructive repair, prune, and clear operations. `cache:verify` validates metadata identity, bare repository integrity, recorded origin identity, and cached worktrees. Repair stages and verifies a replacement before swapping it into place.

## Configuration precedence

Configuration is merged from lowest to highest precedence:

1. built-in defaults
2. user-level SourceSlate configuration
3. repository-root `sourceslate.yaml`
4. source-path `sourceslate.yaml`
5. `SOURCESLATE_CONFIG`
6. explicit `--config`

Repository-controlled configuration cannot define security-sensitive top-level sections such as `git`, `cache`, `security`, or `credentials`.

User configuration locations:

- Windows: `%APPDATA%\SourceSlate\config.yaml`
- macOS: `~/Library/Application Support/SourceSlate/config.yaml`
- Linux/Unix: `~/.config/sourceslate/config.yaml`

Inspect the resolved configuration with:

```bash
sourceslate config:show .
```

See `sourceslate.example.yaml` and `docs/reference/configuration.md`.

## Diagnostics

Run:

```bash
sourceslate doctor
sourceslate doctor --json
```

`doctor` checks the PHP/runtime environment, Git, cache and lock paths, shallow cache health, project/configuration/source paths, and output safety. Use `cache:verify --json` for deeper Git cache integrity checks.

Stable diagnostics are documented in `docs/reference/diagnostics.md`.

## PHP and PHPDoc coverage

SourceSlate models:

- classes, interfaces, traits, and enums
- top-level functions and methods
- properties, including promoted and readonly properties
- constants and enum cases
- nullable, union, and intersection native types
- parameter defaults
- native PHP attributes and their arguments
- source locations and source pages

PHPDoc is parsed once through `phpstan/phpdoc-parser` and routed through semantic tag handlers. Standard metadata includes parameters, return values, exceptions, variables/properties/methods, templates, inheritance/deprecation/see-style tags, and SourceSlate metadata. Unsupported tags are retained losslessly through the unknown-tag fallback.

See `docs/reference/phpdoc.md`.

## Symbol resolution and generated site

SourceSlate builds a deterministic project-wide symbol index. It resolves fully qualified references and unambiguous same-namespace short names for types, methods, properties, constants, enum cases, and functions. Ambiguous short names are never resolved by guesswork.

The generated site includes:

- project overview
- namespace pages
- class/interface/trait/enum pages
- function and member documentation
- source browser pages with line anchors
- internal inheritance/trait/interface and `@see` links when unambiguous
- deterministic search index
- keyboard-usable client-side search
- responsive light/dark/system styling

Generated text is escaped. Client-side search creates DOM nodes and assigns generated text through text properties rather than interpolating it into executable HTML.

## Determinism and publication safety

Documentation is rendered into a staging tree before publication. When replacing existing documentation, SourceSlate preserves the prior tree until the new staging tree has been published successfully.

If publication fails, SourceSlate attempts to restore the last-known-good tree. If automatic restoration also fails, the backup path is preserved for manual recovery.

The regression suite includes full-tree reproducibility and forced publication-failure tests. `tools/benchmark.php` provides parse/render/total timing and peak-memory measurements.

## CI and release quality

The repository CI matrix covers PHP 8.3 and 8.4 on Linux, Windows, and macOS. It validates Composer metadata, PHP syntax, PHPUnit tests, release-critical repository invariants, `doctor --json`, a documentation build, and deterministic `--check`.

A separate clean Composer-global installation smoke job verifies package/bin wiring from the checked-out repository.

Useful local quality gate:

```bash
composer check
```

Release readiness is documented in `docs/release/1.0-acceptance.md`. Repository coverage is intentionally separated from external validation such as Packagist publication and real private HTTPS/SSH credential paths.

## Documentation

- Architecture: `docs/ARCHITECTURE.md`
- Installation: `docs/guides/installation.md`
- CI: `docs/guides/ci.md`
- Remote repositories: `docs/guides/remote-repositories.md`
- Monorepos: `docs/guides/monorepos.md`
- Troubleshooting: `docs/guides/troubleshooting.md`
- Security model: `docs/guides/security.md`
- Configuration reference: `docs/reference/configuration.md`
- PHPDoc reference: `docs/reference/phpdoc.md`
- Diagnostics: `docs/reference/diagnostics.md`
- Release process: `docs/release/process.md`
- 1.0 acceptance gate: `docs/release/1.0-acceptance.md`

## Contributing

See `CONTRIBUTING.md`. SourceSlate favors deterministic behavior, explicit failure, lossless source metadata, local-only tests for Git behavior where possible, and focused regression coverage for corrected defects.

## License

The repository is currently marked proprietary while the public release license is being finalized.
