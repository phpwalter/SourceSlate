# Configuration Reference

SourceSlate reads YAML configuration from multiple scopes. Later sources override earlier sources:

1. built-in defaults;
2. user configuration;
3. repository-root `sourceslate.yaml`;
4. source-path `sourceslate.yaml` for monorepo subtrees;
5. `SOURCESLATE_CONFIG`;
6. explicit `--config`.

Repository-controlled configuration cannot define security-sensitive `git`, `cache`, `security`, or `credentials` sections.

## Example

```yaml
project:
  name: Example API

source:
  paths:
    - src
  exclude:
    - vendor
    - var/cache

output:
  path: docs

source_headers:
  update: false
```

## `project.name`

Human-readable project name shown in the generated documentation. When omitted, SourceSlate uses the project directory name.

The value must not be blank.

## `source.paths`

A non-empty list of source directories relative to the resolved project root.

If omitted, SourceSlate looks for `src`, `app`, then `lib`. If none exist, it uses the project root.

## `source.exclude`

Paths that should not be scanned. Paths are interpreted relative to the project root. The default is:

```yaml
source:
  exclude:
    - vendor
```

## `output.path`

Generated documentation location. The default is `docs`.

Remote Git builds require an explicit CLI `--output` path even if configuration defines an output location. This prevents a remote repository from choosing where generated files are written on the caller's machine.

## `source_headers.update`

When true for a local source tree, SourceSlate maintains an idempotent `@sourceslate` link in each documented PHP source file.

Remote cached sources are immutable and reject source-header mutation.

## Environment variables

### `SOURCESLATE_CONFIG`

Path to a trusted configuration file that overrides repository configuration.

### `SOURCESLATE_CACHE_DIR`

Overrides the persistent Git repository cache root.

### `SOURCESLATE_GIT_TIMEOUT`

Git command timeout in seconds. The value must be a positive integer. `--git-timeout` has higher precedence.

## Inspecting the resolved configuration

```bash
sourceslate config:show .
sourceslate config:show . --json
sourceslate config:show . --config=/path/to/sourceslate.yaml
```

Use this command when diagnosing precedence or environment-specific configuration differences.
