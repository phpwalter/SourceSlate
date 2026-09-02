<?php

/**
 * @file HtmlRenderer.php
 * @path src/Renderer/HtmlRenderer.php
 * @version 1.0.0
 * @date 2026-09-02
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Renders SourceSlate documentation as an interconnected static Material Design 3 site.
 */

declare(strict_types=1);

namespace SourceSlate\Renderer;

use RuntimeException;
use SourceSlate\Model\FileDocumentation;
use SourceSlate\Model\MethodDocumentation;
use SourceSlate\Model\ProjectDocumentation;
use SourceSlate\Model\TypeDocumentation;
use SourceSlate\PhpDoc\Model\PhpDocBlock;

/**
 * Produces static project, namespace, type, source-browser, and search artifacts.
 *
 * All generated content is written beneath the configured output directory.
 * Project-derived text is HTML-escaped before insertion into pages.
 */
final class HtmlRenderer implements RendererInterface
{
    public function render(ProjectDocumentation $project, string $outputDirectory): void
    {
        $this->mkdir($outputDirectory);
        $this->mkdir($outputDirectory . DIRECTORY_SEPARATOR . 'assets');

        $types = [];
        $namespaces = [];
        foreach ($project->files as $file) {
            foreach ($file->types as $type) {
                $types[] = $type;
                $namespaces[$type->namespace][] = $type;
            }
        }

        usort($types, static fn (TypeDocumentation $a, TypeDocumentation $b): int => $a->fullyQualifiedName <=> $b->fullyQualifiedName);
        ksort($namespaces, SORT_STRING);

        $this->writeIndex($project, $types, $namespaces, $outputDirectory);
        $this->writeNamespacePages($project, $namespaces, $outputDirectory);
        $this->writeTypePages($project, $types, $outputDirectory);
        $this->writeSourcePages($project->files, $outputDirectory);
        $this->writeSearchIndex($project, $types, $outputDirectory);
        $this->writeAssets($outputDirectory);
    }

    /**
     * @param list<TypeDocumentation> $types
     * @param array<string, list<TypeDocumentation>> $namespaces
     */
    private function writeIndex(ProjectDocumentation $project, array $types, array $namespaces, string $outputDirectory): void
    {
        $namespaceItems = '';
        foreach ($namespaces as $namespace => $members) {
            $label = $namespace !== '' ? $namespace : '(global)';
            $namespaceItems .= sprintf(
                '<li><a href="%s">%s</a><span>%d types</span></li>',
                $this->escape($this->namespacePath($namespace)),
                $this->escape($label),
                count($members),
            );
        }

        $typeRows = '';
        foreach ($types as $type) {
            $typeRows .= sprintf(
                '<tr data-search-row><td><span class="kind kind-%s">%s</span></td><td><a href="%s"><code>%s</code></a></td><td><a href="%s#L%d"><code>%s:%d</code></a></td></tr>',
                $this->escape($type->kind),
                $this->escape($type->kind),
                $this->escape($this->typePath($type)),
                $this->escape($type->fullyQualifiedName),
                $this->escape($this->sourcePath($type->sourcePath)),
                $type->line,
                $this->escape($type->sourcePath),
                $type->line,
            );
        }

        $body = sprintf(
            '<section class="hero"><p class="eyebrow">SourceSlate API Reference</p><h1>%s</h1><p class="lede">%d PHP files · %d documented types · %d namespaces</p></section><div class="grid"><section class="card"><h2>Namespaces</h2><ul class="nav-list">%s</ul></section><section class="card span-2"><h2>Types</h2><div class="table-wrap"><table><thead><tr><th>Kind</th><th>Type</th><th>Source</th></tr></thead><tbody>%s</tbody></table></div></section></div>',
            $this->escape($project->name),
            count($project->files),
            count($types),
            count($namespaces),
            $namespaceItems,
            $typeRows,
        );

        $this->write($outputDirectory . DIRECTORY_SEPARATOR . 'index.html', $this->page($project->name, $project, $body, ''));
    }

    /**
     * @param array<string, list<TypeDocumentation>> $namespaces
     */
    private function writeNamespacePages(ProjectDocumentation $project, array $namespaces, string $outputDirectory): void
    {
        foreach ($namespaces as $namespace => $types) {
            $rows = '';
            foreach ($types as $type) {
                $rows .= sprintf(
                    '<tr data-search-row><td>%s</td><td><a href="../%s"><code>%s</code></a></td><td>%s</td></tr>',
                    $this->escape($type->kind),
                    $this->escape($this->typePath($type)),
                    $this->escape($type->fullyQualifiedName),
                    $this->escape($type->phpDoc?->summary ?? ''),
                );
            }

            $label = $namespace !== '' ? $namespace : '(global)';
            $body = sprintf(
                '<p class="eyebrow">Namespace</p><h1>%s</h1><section class="card"><div class="table-wrap"><table><thead><tr><th>Kind</th><th>Type</th><th>Summary</th></tr></thead><tbody>%s</tbody></table></div></section>',
                $this->escape($label),
                $rows,
            );

            $path = $outputDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->namespacePath($namespace));
            $this->mkdir(dirname($path));
            $this->write($path, $this->page($label, $project, $body, '../'));
        }
    }

    /**
     * @param list<TypeDocumentation> $types
     */
    private function writeTypePages(ProjectDocumentation $project, array $types, string $outputDirectory): void
    {
        foreach ($types as $type) {
            $relationships = '';
            if ($type->extends !== []) {
                $relationships .= '<dt>Extends</dt><dd>' . $this->codeList($type->extends) . '</dd>';
            }
            if ($type->implements !== []) {
                $relationships .= '<dt>Implements</dt><dd>' . $this->codeList($type->implements) . '</dd>';
            }
            if ($type->traits !== []) {
                $relationships .= '<dt>Uses</dt><dd>' . $this->codeList($type->traits) . '</dd>';
            }

            $methodRows = '';
            foreach ($type->methods as $method) {
                $methodRows .= $this->methodRow($method, $type);
            }

            $doc = $this->renderPhpDoc($type->phpDoc);
            $body = sprintf(
                '<p class="eyebrow">%s</p><h1><code>%s</code></h1><p class="namespace">%s</p>%s<section class="card"><h2>Declaration</h2><dl class="facts"><dt>Source</dt><dd><a href="%s#L%d"><code>%s:%d</code></a></dd>%s</dl></section><section class="card"><h2>Methods</h2>%s</section>',
                $this->escape(ucfirst($type->kind)),
                $this->escape($type->name),
                $this->escape($type->namespace !== '' ? $type->namespace : '(global namespace)'),
                $doc,
                $this->escape($this->relativeSourceFromType($type)),
                $type->line,
                $this->escape($type->sourcePath),
                $type->line,
                $relationships,
                $methodRows !== '' ? '<div class="method-list">' . $methodRows . '</div>' : '<p class="muted">No methods declared.</p>',
            );

            $relativePath = $this->typePath($type);
            $path = $outputDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $this->mkdir(dirname($path));
            $depth = substr_count($relativePath, '/');
            $prefix = str_repeat('../', $depth);
            $this->write($path, $this->page($type->fullyQualifiedName, $project, $body, $prefix));
        }
    }

    /**
     * @param list<FileDocumentation> $files
     */
    private function writeSourcePages(array $files, string $outputDirectory): void
    {
        foreach ($files as $file) {
            $lines = preg_split('/\R/', $file->sourceCode) ?: [];
            $rendered = '';
            foreach ($lines as $index => $line) {
                $number = $index + 1;
                $rendered .= sprintf(
                    '<span class="source-line" id="L%d"><a class="line-number" href="#L%d">%d</a><code>%s</code></span>',
                    $number,
                    $number,
                    $number,
                    $this->escape($line),
                );
            }

            $relativePath = $this->sourcePath($file->path);
            $path = $outputDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $this->mkdir(dirname($path));
            $body = sprintf('<p class="eyebrow">Source</p><h1><code>%s</code></h1><pre class="source-browser">%s</pre>', $this->escape($file->path), $rendered);
            $depth = substr_count($relativePath, '/');
            $this->write($path, $this->page($file->path, new ProjectDocumentation('SourceSlate', []), $body, str_repeat('../', $depth)));
        }
    }

    /**
     * @param list<TypeDocumentation> $types
     */
    private function writeSearchIndex(ProjectDocumentation $project, array $types, string $outputDirectory): void
    {
        $items = [];
        foreach ($types as $type) {
            $items[] = [
                'kind' => $type->kind,
                'name' => $type->name,
                'qualifiedName' => $type->fullyQualifiedName,
                'namespace' => $type->namespace,
                'summary' => $type->phpDoc?->summary,
                'url' => $this->typePath($type),
            ];

            foreach ($type->methods as $method) {
                $items[] = [
                    'kind' => 'method',
                    'name' => $method->name,
                    'qualifiedName' => $type->fullyQualifiedName . '::' . $method->name . '()',
                    'namespace' => $type->namespace,
                    'summary' => $method->phpDoc?->summary,
                    'url' => $this->typePath($type) . '#method-' . rawurlencode(strtolower($method->name)),
                ];
            }
        }

        $json = json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->write($outputDirectory . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'search-index.json', $json . PHP_EOL);
        $this->write(
            $outputDirectory . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'search-index.js',
            'window.SOURCE_SLATE_SEARCH_INDEX = ' . $json . ';' . PHP_EOL,
        );
    }

    private function writeAssets(string $outputDirectory): void
    {
        $assets = $outputDirectory . DIRECTORY_SEPARATOR . 'assets';
        $themePath = dirname(__DIR__, 2) . '/resources/theme/material.css';
        if (!copy($themePath, $assets . DIRECTORY_SEPARATOR . 'material.css')) {
            throw new RuntimeException('Unable to copy the Material Design 3 theme.');
        }

        $script = <<<'JS'
(() => {
    const input = document.querySelector('[data-sourceslate-search]');
    const results = document.querySelector('[data-search-results]');
    if (!input || !results || !Array.isArray(window.SOURCE_SLATE_SEARCH_INDEX)) return;

    input.addEventListener('input', () => {
        const query = input.value.trim().toLowerCase();
        if (query.length < 2) {
            results.hidden = true;
            results.innerHTML = '';
            return;
        }

        const matches = window.SOURCE_SLATE_SEARCH_INDEX
            .filter(item => [item.qualifiedName, item.summary, item.namespace].filter(Boolean).join(' ').toLowerCase().includes(query))
            .slice(0, 20);

        results.innerHTML = matches.map(item => `<a href="${item.url}"><strong>${item.qualifiedName}</strong><span>${item.kind}</span></a>`).join('');
        results.hidden = matches.length === 0;
    });
})();
JS;
        $this->write($assets . DIRECTORY_SEPARATOR . 'sourceslate.js', $script . PHP_EOL);
    }

    private function methodRow(MethodDocumentation $method, TypeDocumentation $type): string
    {
        $static = $method->static ? ' static' : '';
        $return = $method->returnType !== null ? ': ' . $method->returnType : '';
        $signature = sprintf('%s%s function %s(%s)%s', $method->visibility, $static, $method->name, implode(', ', $method->parameters), $return);

        return sprintf(
            '<article class="method" id="method-%s"><h3><code>%s</code></h3>%s<p class="source-ref"><a href="%s#L%d">%s:%d</a></p></article>',
            $this->escape(rawurlencode(strtolower($method->name))),
            $this->escape($signature),
            $this->renderPhpDoc($method->phpDoc),
            $this->escape($this->relativeSourceFromType($type)),
            $method->line,
            $this->escape($type->sourcePath),
            $method->line,
        );
    }

    private function renderPhpDoc(?PhpDocBlock $doc): string
    {
        if ($doc === null) {
            return '<p class="muted">No PHPDoc description.</p>';
        }

        $html = '';
        if ($doc->summary !== null) {
            $html .= '<p class="summary">' . $this->escape($doc->summary) . '</p>';
        }
        if ($doc->description !== null) {
            $html .= '<p>' . nl2br($this->escape($doc->description)) . '</p>';
        }
        if ($doc->tags !== []) {
            $html .= '<dl class="tags">';
            foreach ($doc->tags as $tag) {
                $value = trim(implode(' ', array_filter([$tag->type, $tag->subject, $tag->description, $tag->rawValue !== '' && !$tag->known ? $tag->rawValue : null])));
                $html .= '<dt>@' . $this->escape($tag->name) . '</dt><dd><code>' . $this->escape($value) . '</code></dd>';
            }
            $html .= '</dl>';
        }

        return $html;
    }

    /** @param list<string> $values */
    private function codeList(array $values): string
    {
        return implode(', ', array_map(fn (string $value): string => '<code>' . $this->escape($value) . '</code>', $values));
    }

    private function page(string $title, ProjectDocumentation $project, string $body, string $prefix): string
    {
        return sprintf(
            '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>%s — SourceSlate</title><link rel="stylesheet" href="%sassets/material.css"></head><body><header class="topbar"><a class="brand" href="%sindex.html">SourceSlate</a><span class="project-name">%s</span><div class="search-shell"><input data-sourceslate-search type="search" placeholder="Search documentation" aria-label="Search documentation"><div class="search-results" data-search-results hidden></div></div></header><main class="layout"><aside class="sidebar"><nav><a href="%sindex.html">Overview</a><a href="%sindex.html#namespaces">Namespaces</a><a href="%sindex.html#types">Types</a></nav></aside><article class="content">%s</article></main><script src="%sassets/search-index.js"></script><script src="%sassets/sourceslate.js"></script></body></html>',
            $this->escape($title),
            $prefix,
            $prefix,
            $this->escape($project->name),
            $prefix,
            $prefix,
            $prefix,
            $body,
            $prefix,
            $prefix,
        );
    }

    private function typePath(TypeDocumentation $type): string
    {
        $directory = match ($type->kind) {
            'interface' => 'interfaces',
            'trait' => 'traits',
            'enum' => 'enums',
            default => 'classes',
        };

        return $directory . '/' . str_replace('\\', '/', $type->fullyQualifiedName) . '.html';
    }

    private function namespacePath(string $namespace): string
    {
        $name = $namespace !== '' ? str_replace('\\', '/', $namespace) : '_global';

        return 'namespaces/' . $name . '.html';
    }

    private function sourcePath(string $sourcePath): string
    {
        return 'source/' . str_replace('\\', '/', $sourcePath) . '.html';
    }

    private function relativeSourceFromType(TypeDocumentation $type): string
    {
        return str_repeat('../', substr_count($this->typePath($type), '/')) . $this->sourcePath($type->sourcePath);
    }

    private function mkdir(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create output directory: %s', $directory));
        }
    }

    private function write(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException(sprintf('Unable to write generated file: %s', $path));
        }
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
