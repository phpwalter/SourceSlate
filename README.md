# SourceSlate

SourceSlate is a modern static documentation generator for PHP source code and PHPDoc. It is designed as a straightforward alternative to legacy documentation generators: point it at a local PHP codebase or a remote Git repository and generate interconnected, searchable documentation with links back to source.

## Status

SourceSlate is under active development. The current foundation includes deterministic PHP source discovery, PHPDoc parsing through PHPStan's grammar, semantic tag handlers, a renderer-neutral documentation model, YAML configuration, an initial static HTML renderer, deterministic staging/publishing, remote Git source resolution, and a persistent Git repository cache.

## Requirements

- PHP 8.3 or newer
- Composer
- Git when documenting remote repositories

## Development installation

```powershell
cd L:\var\www\SourceSlate
composer install
```

To make the development checkout callable from anywhere on Windows, create a wrapper such as `L:\bin\sourceslate.cmd`:

```bat
@echo off
php L:\var\www\SourceSlate\bin\sourceslate %*
```

Add `L:\bin` to your user `PATH`.

## Local usage

The preferred zero-configuration form is:

```powershell
cd L:\var\www\SomePhpProject
sourceslate .
```

The path-first form is equivalent to the explicit build command:

```powershell
sourceslate build .
```

You can also target another local project directly:

```powershell
sourceslate L:\var\www\AnotherProject
```

which is equivalent to:

```powershell
sourceslate build L:\var\www\AnotherProject
```

SourceSlate reads `sourceslate.yaml` when present and otherwise uses zero-configuration defaults.

## Remote Git repositories

SourceSlate can document a remote Git repository directly. Remote builds require an explicit output directory because the checked-out source workspace is cache-managed and must never be used as the publication destination.

```powershell
sourceslate https://github.com/vendor/project.git --output .\docs-generated
```

Select a branch, tag, or ref explicitly when required:

```powershell
sourceslate https://github.com/vendor/project.git --branch develop --output .\docs-generated
sourceslate https://github.com/vendor/project.git --tag v2.4.0 --output .\docs-generated
sourceslate https://github.com/vendor/project.git --ref 0123456789abcdef --output .\docs-generated
```

For a monorepo or repository whose PHP project lives below the repository root, use `--source-path`:

```powershell
sourceslate https://github.com/vendor/monorepo.git --source-path packages/api --output .\docs-api
```

SourceSlate resolves the requested Git ref to a commit SHA and records the resolved source state in its build metadata. This makes repeated builds auditable and allows callers to compare the commit SHA used for documentation generation.

### Remote-source controls

```powershell
sourceslate <repository> --output .\docs --refresh
sourceslate <repository> --output .\docs --offline
sourceslate <repository> --output .\docs --recurse-submodules
sourceslate <repository> --output .\docs --git-timeout 120
sourceslate <repository> --output .\docs --dry-run
sourceslate <repository> --output .\docs --json
sourceslate <repository> --output .\docs --ci
```

`--offline` prohibits remote access and succeeds only when the requested repository/ref can be satisfied from the persistent cache. `--refresh` forces remote revalidation when supported. `--source-type=local|git` can be used when automatic source interpretation is ambiguous.

## Build behavior

Useful build controls include:

```powershell
sourceslate build . --config sourceslate.yaml
sourceslate build . --output .\docs-generated
sourceslate build . --force-output
sourceslate build . --dry-run
sourceslate build . --json
sourceslate build . --ci
sourceslate build . --check
sourceslate build . --update-source
```

`--check` renders into a staging area and exits unsuccessfully if the generated documentation differs from the currently published output. This mode is intended for CI drift enforcement.

`--force-output` allows SourceSlate to replace a non-empty output directory that is not already SourceSlate-managed. Use it deliberately; the output guard otherwise refuses to overwrite an unrelated directory.

`--update-source` is not permitted for remote Git sources. Source-header mutation remains reserved by the 1.0 contract and is not enabled in this foundation build.

## Persistent Git cache

Remote repositories are cloned into a persistent SourceSlate cache so subsequent runs can reuse repository data rather than performing a fresh clone for every build. Cache entries include repository identity and source-state metadata and are protected by locking while active.

The cache can be inspected and maintained with:

```powershell
sourceslate cache:list
sourceslate cache:info
sourceslate cache:verify
sourceslate cache:repair
sourceslate cache:prune --dry-run
sourceslate cache:prune --older-than 90d
sourceslate cache:prune --older-than 30d --max-size 10GB
sourceslate cache:clear
```

`cache:prune` skips cache entries that are actively locked by another SourceSlate operation. Use `--dry-run` before destructive maintenance when you want to review the selected entries first.

## Configuration precedence

Configuration is merged deterministically from lowest to highest precedence:

1. built-in defaults
2. user-level SourceSlate configuration
3. repository-root `sourceslate.yaml`
4. source-path `sourceslate.yaml` when the selected source is below the repository root
5. the file referenced by `SOURCESLATE_CONFIG`
6. the explicit `--config` file

This lets a repository define normal documentation behavior while still allowing user, environment, and invocation-specific overrides.

Repository-controlled configuration is intentionally restricted from defining security-sensitive sections such as `git`, `cache`, `security`, or `credentials`. Those values must come from trusted user/environment/explicit configuration rather than an untrusted repository checkout.

### User configuration locations

SourceSlate looks for the user configuration in the platform-standard location:

- Windows: `%APPDATA%\SourceSlate\config.yaml`
- macOS: `~/Library/Application Support/SourceSlate/config.yaml`
- Linux/Unix: `~/.config/sourceslate/config.yaml`

See `sourceslate.example.yaml` for the current repository configuration shape.

## Diagnostics

Run:

```powershell
sourceslate doctor
```

for an environment diagnostic, including prerequisites needed by the current SourceSlate installation.

## PHPDoc architecture

SourceSlate parses each PHPDoc block once through `phpstan/phpdoc-parser`. A tag dispatcher then routes parsed tags to semantic handlers. Individual handlers do not reimplement PHPDoc or PHPStan type grammar.

Initial semantic handlers include:

- `@param`
- `@return`
- `@throws`
- `@sourceslate`

Unknown tags are preserved losslessly for rendering and future semantic support.

## Documentation model

SourceSlate is intentionally layered:

```text
PHP source
   |
   v
nikic/php-parser
   |
   +--> native declarations and source structure
   |
   v
PHPStan PHPDoc parser
   |
   v
semantic tag handlers
   |
   v
renderer-neutral documentation model
   |
   v
static HTML renderer
```

This separation allows future diagnostics, validation, additional output formats, richer relationship analysis, and alternate source providers without coupling the parser directly to HTML or Git.

## License

The repository is currently marked proprietary while the public release license is being finalized.
