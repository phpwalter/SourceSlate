# Installation

## Requirements

SourceSlate requires:

- PHP 8.3 or newer;
- Composer 2;
- Git for remote-repository documentation;
- the PHP `json` and `mbstring` extensions;
- `proc_open` enabled when Git operations are required.

Run `sourceslate doctor` after installation to verify the runtime.

## Global Composer installation

Once the package is published to Packagist:

```bash
composer global require phpwalter/sourceslate
```

Ensure Composer's global `vendor/bin` directory is on `PATH`, then verify:

```bash
sourceslate --version
sourceslate doctor
```

On Windows, the Composer global bin directory is commonly exposed by Composer itself. If `sourceslate` is not found, inspect:

```powershell
composer global config bin-dir --absolute
```

and add that directory to the user `PATH`.

## Project-local installation

```bash
composer require --dev phpwalter/sourceslate
vendor/bin/sourceslate .
```

Project-local installation is useful when a repository needs a pinned SourceSlate version in CI.

## Development installation

```bash
git clone https://github.com/phpwalter/SourceSlate.git
cd SourceSlate
composer install
php bin/sourceslate doctor
php bin/sourceslate .
```

## Upgrading

Global install:

```bash
composer global update phpwalter/sourceslate
```

Project-local install:

```bash
composer update phpwalter/sourceslate
```

Review the changelog before crossing a major version. Cache metadata carries a schema version; future incompatible cache migrations will be called out explicitly in release notes.

## Uninstalling

```bash
composer global remove phpwalter/sourceslate
```

or, for a project-local dependency:

```bash
composer remove --dev phpwalter/sourceslate
```

The persistent remote-repository cache is not automatically deleted during Composer uninstall. Use `sourceslate cache:clear --yes` before uninstalling if the cache should also be removed.
