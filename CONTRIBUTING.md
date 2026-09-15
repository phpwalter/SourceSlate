# Contributing to SourceSlate

SourceSlate favors deterministic behavior, explicit failure, and regression coverage for every corrected defect.

## Development setup

```bash
git clone https://github.com/phpwalter/SourceSlate.git
cd SourceSlate
composer install
composer test
php bin/sourceslate doctor
```

PHP 8.3 is the minimum supported runtime. Changes should also remain valid on PHP 8.4.

## Change discipline

- Keep source parsing separate from rendering.
- Keep PHPDoc parsing centralized; add semantic behavior through tag handlers.
- Do not discard unknown PHPDoc metadata.
- Do not make remote cached source mutable.
- Use staging-and-swap semantics for destructive filesystem replacement.
- Acquire cache locks in the documented order: maintenance, repository operation, fetch.
- Avoid environment-dependent ordering in generated output.
- Do not introduce timestamps or random identifiers into generated documentation unless they are explicitly excluded from deterministic comparison.

## Tests

Every behavior change should include a focused regression test. Prefer local temporary Git repositories over network access in tests.

Run:

```bash
composer validate --strict
composer test
php bin/sourceslate build . --config=sourceslate.example.yaml
php bin/sourceslate build . --config=sourceslate.example.yaml --check
```

## Security-sensitive changes

Changes involving paths, symlinks, Git URLs, credentials, HTML generation, cache replacement, or source mutation require explicit negative tests.

Generated data must never be inserted into browser HTML through unescaped string interpolation. Use escaped server-side HTML or DOM APIs such as `textContent`.

## Pull requests

A pull request should state:

1. the invariant or behavior being changed;
2. the implementation approach;
3. new or changed diagnostics;
4. regression coverage;
5. compatibility or migration impact.

Do not combine unrelated refactors with behavioral fixes when they can be reviewed independently.

## Generated documentation

If a change alters generated output, regenerate documentation and run `--check`. Deterministic differences should be intentional and reviewable.
