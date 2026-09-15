# PHPDoc Support

SourceSlate parses PHPDoc centrally and dispatches recognized tags to semantic handlers. Unknown tags remain available as raw values instead of being discarded.

## Core callable tags

| Tag | Parsed fields |
| --- | --- |
| `@param` | type, parameter subject, description |
| `@return` | type, description |
| `@throws` | exception type, description |

## Type and member metadata

SourceSlate recognizes these tags as structured or known metadata:

- `@var`
- `@property`
- `@property-read`
- `@property-write`
- `@method`
- `@template`
- `@template-covariant`
- `@template-contravariant`
- `@extends`
- `@implements`
- `@use`
- `@mixin`

Native PHP types remain authoritative for native declarations. PHPDoc can provide additional generic or dynamic metadata that PHP syntax cannot express.

## Documentation metadata

Known descriptive tags include:

- `@deprecated`
- `@since`
- `@see`
- `@link`
- `@example`
- `@internal`
- `@inheritDoc`
- `@author`
- `@copyright`
- `@license`
- `@version`
- `@package`
- `@subpackage`
- `@api`

## SourceSlate links

`@sourceslate` identifies the generated documentation location associated with a local source file.

Example:

```php
/**
 * @sourceslate docs/classes/Acme/Service.html
 */
```

When source-header updates are enabled, SourceSlate replaces an existing tag rather than adding a duplicate. Existing PHPDoc text is preserved.

## Unknown tags

SourceSlate intentionally preserves unknown PHPDoc tags. A documentation generator should not destroy metadata simply because the current version does not understand it.

An unknown tag is rendered with its original tag name and raw value. Future handlers can therefore add semantics without requiring the original source to be reconstructed.

## Multiline descriptions

The PHPDoc parser retains the block summary and longer description separately. Tag descriptions are retained by the underlying PHPDoc AST and normalized into SourceSlate's tag model.

## Reference resolution

The project symbol index can resolve:

- fully-qualified types;
- same-namespace short type names;
- methods;
- properties;
- constants;
- enum cases;
- top-level functions.

Ambiguous short names intentionally remain unresolved rather than selecting an arbitrary symbol.
