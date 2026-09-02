# SourceSlate 1.0 Architecture

## Purpose

SourceSlate is a static documentation generator for PHP source code and PHPDoc. Version 1.0 prioritizes simple operation, deterministic output, interconnected API reference pages, source navigation, and a modern Material Design 3 presentation layer.

## Core boundaries

1. **Configuration** resolves YAML and zero-configuration defaults.
2. **Parser** reads PHP source and PHPDoc and produces normalized documentation data.
3. **Model** contains renderer-independent project, file, type, member, relationship, PHPDoc, and source-location objects.
4. **Relationship resolver** links declared structural relationships after parsing.
5. **Diagnostics** records conflicts and unsupported states without silently rewriting meaning.
6. **Renderer** produces static HTML, search indexes, source-browser pages, and assets.
7. **Source header writer** is an explicit opt-in mutator responsible only for `@sourceslate` header links.

## 1.0 relationship scope

SourceSlate will resolve namespace membership, inheritance, interface implementation, trait usage, constructor and method parameter types, return types, property types, thrown exceptions, `@see`, `@link`, and PHPStan template/generic relationships.

Runtime call graphs, service-container inference, object-construction graphs, and behavioral dependency analysis are deferred.

## Output model

The generated site will expose Overview, Namespaces, Classes, Interfaces, Traits, Enums, Functions, Constants, Files, Deprecated, and Search views. Type pages will include declaration, description, inheritance, interfaces, traits, constants, properties, methods, inherited members, PHPDoc tags, source links, and related types.

## Source links

Where repository metadata is available, SourceSlate will emit both local generated source-browser links and repository links with line anchors.

## PHPDoc behavior

Known PHPDoc tags receive semantic treatment. Unknown tags are preserved and rendered. PHPStan-style type expressions are first-class. Native/PHPDoc contradictions produce diagnostics; SourceSlate does not silently choose one contract over the other.

## Mutation policy

Documentation generation is read-only by default. Source mutation requires an explicit option and will write the canonical form:

```text
@sourceslate docs/classes/Authentication/AuthenticationProvider.html
```

## Presentation

The default renderer uses Material Design 3 concepts and CSS custom properties so consumers can replace visual tokens without modifying the documentation model or parser.

## Determinism

Source discovery and model ordering must be deterministic. Identical source, configuration, and SourceSlate version must produce semantically equivalent generated documentation.
