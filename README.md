# SourceSlate

SourceSlate is a modern static documentation generator for PHP source code and PHPDoc. It is designed as a straightforward alternative to legacy documentation generators: point it at a PHP codebase and generate interconnected, searchable documentation with links back to source.

## Status

SourceSlate is under active development. The current foundation includes deterministic PHP source discovery, PHPDoc parsing through PHPStan's grammar, semantic tag handlers, a renderer-neutral documentation model, YAML configuration, and an initial static HTML renderer.

## Requirements

- PHP 8.3 or newer
- Composer

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

## Usage

The preferred zero-configuration form is:

```powershell
cd L:\var\www\SomePhpProject
sourceslate .
```

The path-first form is equivalent to the explicit build command:

```powershell
sourceslate build .
```

You can also target another project directly:

```powershell
sourceslate L:\var\www\AnotherProject
```

which is equivalent to:

```powershell
sourceslate build L:\var\www\AnotherProject
```

SourceSlate reads `sourceslate.yaml` when present and otherwise uses zero-configuration defaults.

### Build options

```powershell
sourceslate build . --config sourceslate.yaml
sourceslate build . --update-source
sourceslate build . --check
```

`--update-source` and `--check` are reserved by the 1.0 contract; the current foundation reports their status without performing incomplete mutation or validation behavior.

## PHPDoc architecture

SourceSlate parses each PHPDoc block once through `phpstan/phpdoc-parser`. A tag dispatcher then routes parsed tags to semantic handlers. Individual handlers do not reimplement PHPDoc or PHPStan type grammar.

Initial semantic handlers include:

- `@param`
- `@return`
- `@throws`
- `@sourceslate`

Unknown tags are preserved losslessly for rendering and future semantic support.

## Configuration

An optional `sourceslate.yaml` can override project discovery and rendering defaults. See `sourceslate.example.yaml` for the current configuration shape.

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

This separation allows future diagnostics, validation, additional output formats, and richer relationship analysis without coupling the parser directly to HTML.

## License

The repository is currently marked proprietary while the public release license is being finalized.
