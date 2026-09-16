# Continuous Integration

SourceSlate is designed to make generated documentation enforceable rather than advisory.

## Recommended pipeline

A release-grade pipeline should execute these steps in order:

```bash
composer validate --strict
composer install --no-interaction --prefer-dist
composer lint
composer test
php tools/release_audit.php --json
php bin/sourceslate doctor --json
php bin/sourceslate build . --config=sourceslate.yaml --output=.sourceslate-ci-docs
php bin/sourceslate build . --config=sourceslate.yaml --output=.sourceslate-ci-docs --check
```

The first build publishes the expected documentation. `--check` renders the same project into an isolated staging directory and compares the complete generated tree with the published documentation. A difference exits with diagnostic `SS-DOC-0050` and status code `50`.

`composer check` runs the package metadata validation, PHP syntax validation, PHPUnit suite, and repository release audit as one local quality gate.

## SourceSlate repository matrix

The SourceSlate repository validates:

- PHP 8.3 on Ubuntu, Windows, and macOS
- PHP 8.4 on Ubuntu, Windows, and macOS
- strict Composer metadata validation
- dependency installation from the lockfile
- PHP syntax validation across source, tests, tools, binaries, and examples
- PHPUnit integration/regression tests
- machine-readable release-critical repository audit
- `doctor --json`
- deterministic documentation generation and `--check`

Two additional smoke jobs exercise distribution and remote behavior:

1. **Composer global install smoke** — registers the checked-out package as a Composer path repository, installs it into a clean global Composer home, and runs `sourceslate --version` through the globally installed binary.
2. **Public HTTPS remote build smoke** — runs SourceSlate against `https://github.com/phpwalter/SourceSlate.git` over real HTTPS Git transport and verifies generated output exists.

These jobs prove repository packaging and public-network behavior. They do not replace Packagist publication verification or private HTTPS/SSH credential-path testing.

## Consumer repository example

```yaml
name: SourceSlate

on:
  push:
  pull_request:

jobs:
  docs:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          coverage: none
      - run: composer install --no-interaction --prefer-dist
      - run: vendor/bin/sourceslate doctor --json
      - run: vendor/bin/sourceslate build . --output=.sourceslate-docs
      - run: vendor/bin/sourceslate build . --output=.sourceslate-docs --check
```

If documentation is intentionally changed, regenerate it and review the resulting tree before publication or commit.

## Remote repositories in CI

Remote source builds must always supply `--output`. For deterministic CI, pin a tag or full commit SHA rather than an independently moving branch when the exact source state matters.

```bash
sourceslate https://github.com/vendor/project.git \
  --ref=0123456789abcdef0123456789abcdef01234567 \
  --output=./docs/vendor-project \
  --ci
```

Do not place credentials in the command line. Configure Git authentication through the runner environment, SSH agent, or provider credential helper. SourceSlate redacts credential-bearing repository URLs before cache metadata is persisted or displayed.

## Cache validation in CI

For workflows that intentionally preserve the SourceSlate Git cache between runs:

```bash
sourceslate cache:list --json
sourceslate cache:verify --json
```

Do not persist the cache merely to accelerate small projects unless the added lifecycle complexity is justified. A disposable CI environment can use a fresh cache safely.

## Release workflow

Version tags matching `v*.*.*` invoke the release workflow. Before packaging, it verifies that the tag matches `SourceSlate\Version::VERSION`, runs the full Composer quality gate, checks the runtime environment, builds and deterministically verifies documentation, and creates a Composer distribution archive.

Do not tag `v1.0.0` until the external acceptance gates in `docs/release/1.0-acceptance.md` have also been observed passing.

## Exit codes and JSON

Automation should rely on exit codes and structured JSON rather than scraping formatted console text. Build, doctor, cache verification, and cache maintenance commands expose machine-readable output for CI use.

Stable diagnostic codes are documented in `docs/reference/diagnostics.md`.
