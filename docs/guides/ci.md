# Continuous Integration

SourceSlate is designed to make generated documentation enforceable rather than advisory.

## Recommended pipeline

A release-grade pipeline should execute these steps in order:

```bash
composer validate --strict
composer install --no-interaction --prefer-dist
composer test
php bin/sourceslate doctor --json
php bin/sourceslate build . --config=sourceslate.yaml
php bin/sourceslate build . --config=sourceslate.yaml --check
```

The first build publishes the expected documentation. `--check` renders the same project into an isolated staging directory and compares the complete generated tree with the published documentation. A difference exits with diagnostic `SS-DOC-0050` and status code `50`.

## GitHub Actions

The repository CI matrix validates both PHP 8.3 and PHP 8.4 on Linux and Windows. The workflow deliberately uses the same CLI entry point users invoke locally.

For consumer repositories, a minimal job is:

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
      - run: vendor/bin/sourceslate build . --check
```

If documentation is intentionally changed, regenerate it locally and commit the resulting tree.

## Remote repositories in CI

Remote source builds must always supply `--output`. For deterministic CI, pin a tag or full commit SHA rather than an independently moving branch.

```bash
sourceslate https://github.com/vendor/project.git \
  --ref 0123456789abcdef0123456789abcdef01234567 \
  --output ./docs/vendor-project \
  --ci
```

Do not place credentials in the command line. Configure Git authentication through the runner environment or provider credential helper.

## Exit codes

CI should rely on SourceSlate exit codes rather than parsing human-readable messages. JSON mode is available where machine-readable diagnostics are required.
