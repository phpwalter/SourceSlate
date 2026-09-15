# Basic SourceSlate Example

From the repository root:

```bash
php bin/sourceslate build examples/basic --config=examples/basic/sourceslate.yaml
```

The generated site is written to `examples/basic/docs` unless an explicit `--output` path is supplied.

To verify deterministic output after generation:

```bash
php bin/sourceslate build examples/basic --config=examples/basic/sourceslate.yaml --check
```

To preview source-header changes without modifying `src/ExampleService.php`:

```bash
php bin/sourceslate build examples/basic \
  --config=examples/basic/sourceslate.yaml \
  --update-source \
  --dry-run \
  --json
```

The example intentionally contains classes, interfaces, traits, enums, constants, promoted constructor properties, default parameter values, top-level functions, and common PHPDoc tags.
