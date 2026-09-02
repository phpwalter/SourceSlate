# SourceSlate

SourceSlate is a modern static documentation generator for PHP source code and PHPDoc.

Its goal is straightforward: point SourceSlate at a PHP codebase and generate interconnected, searchable documentation with links back to both local source views and repository source files. Source mutation is opt-in, and future releases will support writing `@sourceslate` links into PHP file headers.

## Status

SourceSlate is in early foundation development. The current executable path can discover PHP source files, parse class-like declarations through `nikic/php-parser`, build a renderer-independent documentation model, and generate a searchable Material Design 3 static HTML overview.

## Requirements

- PHP 8.3+
- Composer

## Install

```bash
composer install
```

## Usage

Zero-configuration build:

```bash
bin/sourceslate .
```

Equivalent explicit command:

```bash
bin/sourceslate build .
```

Use an explicit YAML configuration:

```bash
bin/sourceslate build . --config=sourceslate.yaml
```

Reserved 1.0 command options:

```bash
bin/sourceslate build . --update-source
bin/sourceslate build . --check
```

The foundation build recognizes these options but deliberately does not mutate source or enforce validation yet.

## Configuration

SourceSlate uses YAML when configuration is needed. Without `sourceslate.yaml`, it discovers a conventional source root (`src`, `app`, or `lib`) and writes generated documentation to `docs`.

See `sourceslate.example.yaml` for the initial configuration contract.

## Architecture

SourceSlate keeps parsing, modeling, relationship resolution, rendering, and source mutation separate:

```text
PHP source
    |
    v
Source parser
    |
    v
Documentation model
    |
    v
Relationship resolver
    |
    v
Renderer
    |
    v
Static HTML + search + source views
```

The parser never emits HTML, and the renderer never parses PHP. This boundary allows future validation and additional output formats without replacing the core parser.

See `docs/ARCHITECTURE.md` for the detailed 1.0 direction.

## Planned 1.0 scope

- PHP 8.3+ source parsing
- PHPDoc and PHPStan-compatible type parsing
- Preserve and render unknown PHPDoc tags
- Namespaces, classes, interfaces, traits, enums, functions, constants, and files
- `extends`, `implements`, trait usage, typed member relationships, `@see`, and `@link`
- Static HTML output similar in information architecture to phpDocumentor
- Material Design 3 theme with CSS design-token overrides
- Client-side navigation and search
- Local source browser and repository source links
- Optional `@sourceslate docs/...` file-header updates
- Incremental builds
- CI/check mode
- Diagnostics for native/PHPDoc type conflicts

Deeper behavioral static analysis and full documentation-governance validation are intentionally deferred until the generator core is stable.

## Tests

```bash
composer test
```

## Source annotation contract

When source-header mutation is enabled, SourceSlate will use the explicit tag form:

```php
@sourceslate docs/classes/Authentication/AuthenticationProvider.html
```

Source modification will remain opt-in.
