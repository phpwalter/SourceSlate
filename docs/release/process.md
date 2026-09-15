# Release Process

## 1. Prepare the candidate

Create a release branch from the accepted integration tip. Update the changelog and release notes, but do not mark the version final until the acceptance gate is green.

## 2. Validate locally

```bash
composer validate --strict
composer install --no-interaction --prefer-dist
composer test
php bin/sourceslate doctor --json
php bin/sourceslate build . --config=sourceslate.example.yaml
php bin/sourceslate build . --config=sourceslate.example.yaml --check
```

Run the benchmark harness and retain the result with the release evidence when a performance-sensitive change is included:

```bash
php tools/benchmark.php . /tmp/sourceslate-benchmark sourceslate.example.yaml
```

## 3. Validate CI

The release candidate must pass Linux and Windows on supported PHP versions. A failure on one operating system is a release blocker even when the other matrix jobs pass.

## 4. Run the acceptance gate

Review `docs/release/1.0-acceptance.md`. Every required item must be checked before `v1.0.0`.

## 5. Version and tag

SourceSlate uses Semantic Versioning after 1.0:

- `MAJOR`: incompatible CLI, configuration, cache-schema, or documented API changes;
- `MINOR`: backwards-compatible features;
- `PATCH`: backwards-compatible fixes.

Create an annotated version tag only after the release commit has passed CI:

```bash
git tag -a v1.0.0 -m "SourceSlate 1.0.0"
git push origin v1.0.0
```

The tag triggers the release workflow, which validates the package and creates a Composer distribution archive.

## 6. Packagist

Packagist should track the GitHub repository. Version tags are the package release source; no separate package contents should be maintained by hand.

After the tag is visible, verify a clean installation:

```bash
composer create-project --no-install some/empty-project /tmp/sourceslate-smoke
composer global require phpwalter/sourceslate:^1.0
sourceslate --version
sourceslate doctor
```

Use an isolated Composer home for automated release smoke tests where practical.

## 7. Post-release

- verify release archive availability;
- verify Packagist version visibility;
- verify installation instructions against a clean environment;
- open the next `Unreleased` changelog section;
- record any known non-blocking limitations in release notes.

Never retag an existing published version. Correct a release with a new patch version.
