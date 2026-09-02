<?php

declare(strict_types=1);

namespace SourceSlate\Renderer;

use RuntimeException;
use SourceSlate\Model\FileDocumentation;
use SourceSlate\Model\FunctionDocumentation;
use SourceSlate\Model\MethodDocumentation;
use SourceSlate\Model\ProjectDocumentation;
use SourceSlate\Model\TypeDocumentation;
use SourceSlate\PhpDoc\Model\PhpDocBlock;

final class HtmlRenderer implements RendererInterface
{
    public function render(ProjectDocumentation $project, string $outputDirectory): void
    {
        $this->mkdir($outputDirectory);
        $this->mkdir($outputDirectory . DIRECTORY_SEPARATOR . 'assets');

        $types = [];
        $functions = [];
        $namespaces = [];
        foreach ($project->files as $file) {
            foreach ($file->types as $type) {
                $types[] = $type;
                $namespaces[$type->namespace][] = $type;
            }
            foreach ($file->functions as $function) {
                $functions[] = $function;
            }
        }

        usort($types, static fn (TypeDocumentation $a, TypeDocumentation $b): int => $a->fullyQualifiedName <=> $b->fullyQualifiedName);
        usort($functions, static fn (FunctionDocumentation $a, FunctionDocumentation $b): int => $a->fullyQualifiedName <=> $b->fullyQualifiedName);
        ksort($namespaces, SORT_STRING);

        $this->writeIndex($project, $types, $functions, $namespaces, $outputDirectory);
        $this->writeNamespacePages($project, $namespaces, $outputDirectory);
        $this->writeTypePages($project, $types, $outputDirectory);
        $this->writeFunctionsPage($project, $functions, $outputDirectory);
        $this->writeSourcePages($project, $outputDirectory);
        $this->writeSearchIndex($types, $functions, $outputDirectory);
        $this->writeAssets($outputDirectory);
    }

    /** @param list<TypeDocumentation> $types @param list<FunctionDocumentation> $functions @param array<string,list<TypeDocumentation>> $namespaces */
    private function writeIndex(ProjectDocumentation $project, array $types, array $functions, array $namespaces, string $outputDirectory): void
    {
        $namespaceItems = '';
        foreach ($namespaces as $namespace => $members) {
            $label = $namespace !== '' ? $namespace : '(global)';
            $namespaceItems .= sprintf('<li><a href="%s">%s</a><span>%d types</span></li>', $this->escape($this->namespacePath($namespace)), $this->escape($label), count($members));
        }

        $typeRows = '';
        foreach ($types as $type) {
            $typeRows .= sprintf('<tr><td>%s</td><td><a href="%s"><code>%s</code></a></td><td>%s</td></tr>', $this->escape($type->kind), $this->escape($this->typePath($type)), $this->escape($type->fullyQualifiedName), $this->escape($type->phpDoc?->summary ?? ''));
        }

        $body = sprintf(
            '<section class="hero"><p class="eyebrow">SourceSlate API Reference</p><h1>%s</h1><p class="lede">%d files · %d types · %d functions · %d namespaces</p></section><div class="grid"><section class="card"><h2>Namespaces</h2><ul class="nav-list">%s</ul></section><section class="card span-2"><h2>Types</h2><div class="table-wrap"><table><thead><tr><th>Kind</th><th>Type</th><th>Summary</th></tr></thead><tbody>%s</tbody></table></div><p><a href="functions/index.html">Browse functions</a></p></section></div>',
            $this->escape($project->name), count($project->files), count($types), count($functions), count($namespaces), $namespaceItems, $typeRows
        );

        $this->write($outputDirectory . DIRECTORY_SEPARATOR . 'index.html', $this->page($project->name, $project, $body, ''));
    }

    /** @param array<string,list<TypeDocumentation>> $namespaces */
    private function writeNamespacePages(ProjectDocumentation $project, array $namespaces, string $outputDirectory): void
    {
        foreach ($namespaces as $namespace => $types) {
            $rows = '';
            foreach ($types as $type) {
                $rows .= sprintf('<tr><td>%s</td><td><a href="../%s"><code>%s</code></a></td><td>%s</td></tr>', $this->escape($type->kind), $this->escape($this->typePath($type)), $this->escape($type->fullyQualifiedName), $this->escape($type->phpDoc?->summary ?? ''));
            }
            $label = $namespace !== '' ? $namespace : '(global)';
            $body = sprintf('<p class="eyebrow">Namespace</p><h1>%s</h1><section class="card"><table><thead><tr><th>Kind</th><th>Type</th><th>Summary</th></tr></thead><tbody>%s</tbody></table></section>', $this->escape($label), $rows);
            $path = $outputDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->namespacePath($namespace));
            $this->mkdir(dirname($path));
            $this->write($path, $this->page($label, $project, $body, '../'));
        }
    }

    /** @param list<TypeDocumentation> $types */
    private function writeTypePages(ProjectDocumentation $project, array $types, string $outputDirectory): void
    {
        foreach ($types as $type) {
            $sections = $this->renderMemberSections($type);
            $body = sprintf(
                '<p class="eyebrow">%s</p><h1><code>%s</code></h1><p class="namespace">%s</p>%s<section class="card"><h2>Declaration</h2><p><a href="%s#L%d"><code>%s:%d</code></a></p></section>%s',
                $this->escape(ucfirst($type->kind)), $this->escape($type->name), $this->escape($type->namespace !== '' ? $type->namespace : '(global namespace)'), $this->renderPhpDoc($type->phpDoc), $this->escape($this->relativeSourceFromType($type)), $type->line, $this->escape($type->sourcePath), $type->line, $sections
            );
            $relative = $this->typePath($type);
            $path = $outputDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $this->mkdir(dirname($path));
            $this->write($path, $this->page($type->fullyQualifiedName, $project, $body, str_repeat('../', substr_count($relative, '/'))));
        }
    }

    private function renderMemberSections(TypeDocumentation $type): string
    {
        $html = '';

        if ($type->constants !== []) {
            $html .= '<section class="card"><h2>Constants</h2><div class="member-list">';
            foreach ($type->constants as $constant) {
                $html .= sprintf('<article id="constant-%s"><h3><code>%s const %s</code></h3>%s</article>', $this->escape(strtolower($constant->name)), $this->escape($constant->visibility), $this->escape($constant->name), $this->renderPhpDoc($constant->phpDoc));
            }
            $html .= '</div></section>';
        }

        if ($type->enumCases !== []) {
            $html .= '<section class="card"><h2>Enum cases</h2><div class="member-list">';
            foreach ($type->enumCases as $case) {
                $value = $case->value !== null ? ' = ' . $case->value : '';
                $html .= sprintf('<article id="case-%s"><h3><code>case %s%s</code></h3>%s</article>', $this->escape(strtolower($case->name)), $this->escape($case->name), $this->escape($value), $this->renderPhpDoc($case->phpDoc));
            }
            $html .= '</div></section>';
        }

        if ($type->properties !== []) {
            $html .= '<section class="card"><h2>Properties</h2><div class="member-list">';
            foreach ($type->properties as $property) {
                $signature = trim($property->visibility . ($property->static ? ' static' : '') . ($property->readonly ? ' readonly' : '') . ' ' . ($property->type !== null ? $property->type . ' ' : '') . '$' . $property->name);
                $html .= sprintf('<article id="property-%s"><h3><code>%s</code></h3>%s</article>', $this->escape(strtolower($property->name)), $this->escape($signature), $this->renderPhpDoc($property->phpDoc));
            }
            $html .= '</div></section>';
        }

        $html .= '<section class="card"><h2>Methods</h2><div class="member-list">';
        if ($type->methods === []) {
            $html .= '<p class="muted">No methods declared.</p>';
        } else {
            foreach ($type->methods as $method) {
                $html .= $this->methodRow($method, $type);
            }
        }
        $html .= '</div></section>';

        return $html;
    }

    /** @param list<FunctionDocumentation> $functions */
    private function writeFunctionsPage(ProjectDocumentation $project, array $functions, string $outputDirectory): void
    {
        $items = '';
        foreach ($functions as $function) {
            $signature = sprintf('function %s(%s)%s', $function->fullyQualifiedName, implode(', ', $function->parameters), $function->returnType !== null ? ': ' . $function->returnType : '');
            $items .= sprintf('<article class="method" id="function-%s"><h2><code>%s</code></h2>%s<p><a href="../%s#L%d">%s:%d</a></p></article>', $this->escape(rawurlencode(strtolower($function->fullyQualifiedName))), $this->escape($signature), $this->renderPhpDoc($function->phpDoc), $this->escape($this->sourcePath($function->sourcePath)), $function->line, $this->escape($function->sourcePath), $function->line);
        }
        $body = '<p class="eyebrow">Functions</p><h1>Functions</h1><section class="card">' . ($items !== '' ? $items : '<p class="muted">No top-level functions found.</p>') . '</section>';
        $path = $outputDirectory . DIRECTORY_SEPARATOR . 'functions' . DIRECTORY_SEPARATOR . 'index.html';
        $this->mkdir(dirname($path));
        $this->write($path, $this->page('Functions', $project, $body, '../'));
    }

    private function writeSourcePages(ProjectDocumentation $project, string $outputDirectory): void
    {
        foreach ($project->files as $file) {
            $lines = preg_split('/\R/', $file->sourceCode) ?: [];
            $rendered = '';
            foreach ($lines as $index => $line) {
                $n = $index + 1;
                $rendered .= sprintf('<span class="source-line" id="L%d"><a class="line-number" href="#L%d">%d</a><code>%s</code></span>', $n, $n, $n, $this->escape($line));
            }
            $relative = $this->sourcePath($file->path);
            $path = $outputDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $this->mkdir(dirname($path));
            $body = sprintf('<p class="eyebrow">Source</p><h1><code>%s</code></h1><pre class="source-browser">%s</pre>', $this->escape($file->path), $rendered);
            $this->write($path, $this->page($file->path, $project, $body, str_repeat('../', substr_count($relative, '/'))));
        }
    }

    /** @param list<TypeDocumentation> $types @param list<FunctionDocumentation> $functions */
    private function writeSearchIndex(array $types, array $functions, string $outputDirectory): void
    {
        $items = [];
        foreach ($types as $type) {
            $items[] = ['kind' => $type->kind, 'qualifiedName' => $type->fullyQualifiedName, 'summary' => $type->phpDoc?->summary, 'url' => $this->typePath($type)];
            foreach ($type->properties as $property) {
                $items[] = ['kind' => 'property', 'qualifiedName' => $type->fullyQualifiedName . '::$' . $property->name, 'summary' => $property->phpDoc?->summary, 'url' => $this->typePath($type) . '#property-' . strtolower($property->name)];
            }
            foreach ($type->constants as $constant) {
                $items[] = ['kind' => 'constant', 'qualifiedName' => $type->fullyQualifiedName . '::' . $constant->name, 'summary' => $constant->phpDoc?->summary, 'url' => $this->typePath($type) . '#constant-' . strtolower($constant->name)];
            }
            foreach ($type->enumCases as $case) {
                $items[] = ['kind' => 'enum-case', 'qualifiedName' => $type->fullyQualifiedName . '::' . $case->name, 'summary' => $case->phpDoc?->summary, 'url' => $this->typePath($type) . '#case-' . strtolower($case->name)];
            }
            foreach ($type->methods as $method) {
                $items[] = ['kind' => 'method', 'qualifiedName' => $type->fullyQualifiedName . '::' . $method->name . '()', 'summary' => $method->phpDoc?->summary, 'url' => $this->typePath($type) . '#method-' . rawurlencode(strtolower($method->name))];
            }
        }
        foreach ($functions as $function) {
            $items[] = ['kind' => 'function', 'qualifiedName' => $function->fullyQualifiedName . '()', 'summary' => $function->phpDoc?->summary, 'url' => 'functions/index.html#function-' . rawurlencode(strtolower($function->fullyQualifiedName))];
        }

        $json = json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $assets = $outputDirectory . DIRECTORY_SEPARATOR . 'assets';
        $this->write($assets . DIRECTORY_SEPARATOR . 'search-index.json', $json . PHP_EOL);
        $this->write($assets . DIRECTORY_SEPARATOR . 'search-index.js', 'window.SOURCE_SLATE_SEARCH_INDEX = ' . $json . ';' . PHP_EOL);
    }

    private function writeAssets(string $outputDirectory): void
    {
        $assets = $outputDirectory . DIRECTORY_SEPARATOR . 'assets';
        $themePath = dirname(__DIR__, 2) . '/resources/theme/material.css';
        if (!copy($themePath, $assets . DIRECTORY_SEPARATOR . 'material.css')) {
            throw new RuntimeException('Unable to copy the Material Design 3 theme.');
        }
        $this->write($assets . DIRECTORY_SEPARATOR . 'sourceslate.js', "(() => { const input=document.querySelector('[data-sourceslate-search]'); const results=document.querySelector('[data-search-results]'); if(!input||!results||!Array.isArray(window.SOURCE_SLATE_SEARCH_INDEX))return; input.addEventListener('input',()=>{ const q=input.value.trim().toLowerCase(); if(q.length<2){results.hidden=true;results.innerHTML='';return;} const m=window.SOURCE_SLATE_SEARCH_INDEX.filter(i=>[i.qualifiedName,i.summary].filter(Boolean).join(' ').toLowerCase().includes(q)).slice(0,20); results.innerHTML=m.map(i=>`<a href=\"${i.url}\"><strong>${i.qualifiedName}</strong><span>${i.kind}</span></a>`).join(''); results.hidden=m.length===0; }); })();\n");
    }

    private function methodRow(MethodDocumentation $method, TypeDocumentation $type): string
    {
        $signature = sprintf('%s%s function %s(%s)%s', $method->visibility, $method->static ? ' static' : '', $method->name, implode(', ', $method->parameters), $method->returnType !== null ? ': ' . $method->returnType : '');
        return sprintf('<article class="method" id="method-%s"><h3><code>%s</code></h3>%s<p class="source-ref"><a href="%s#L%d">%s:%d</a></p></article>', $this->escape(rawurlencode(strtolower($method->name))), $this->escape($signature), $this->renderPhpDoc($method->phpDoc), $this->escape($this->relativeSourceFromType($type)), $method->line, $this->escape($type->sourcePath), $method->line);
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
                $value = trim(implode(' ', array_filter([$tag->type, $tag->subject, $tag->description, !$tag->known ? $tag->rawValue : null])));
                $html .= '<dt>@' . $this->escape($tag->name) . '</dt><dd><code>' . $this->escape($value) . '</code></dd>';
            }
            $html .= '</dl>';
        }
        return $html;
    }

    private function page(string $title, ProjectDocumentation $project, string $body, string $prefix): string
    {
        return sprintf('<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>%s — SourceSlate</title><link rel="stylesheet" href="%sassets/material.css"></head><body><header class="topbar"><a class="brand" href="%sindex.html">SourceSlate</a><span class="project-name">%s</span><div class="search-shell"><input data-sourceslate-search type="search" placeholder="Search documentation"><div class="search-results" data-search-results hidden></div></div></header><main class="layout"><aside class="sidebar"><nav><a href="%sindex.html">Overview</a><a href="%sfunctions/index.html">Functions</a></nav></aside><article class="content">%s</article></main><script src="%sassets/search-index.js"></script><script src="%sassets/sourceslate.js"></script></body></html>', $this->escape($title), $prefix, $prefix, $this->escape($project->name), $prefix, $prefix, $body, $prefix, $prefix);
    }

    private function typePath(TypeDocumentation $type): string
    {
        $directory = match ($type->kind) {'interface' => 'interfaces', 'trait' => 'traits', 'enum' => 'enums', default => 'classes'};
        return $directory . '/' . str_replace('\\', '/', $type->fullyQualifiedName) . '.html';
    }

    private function namespacePath(string $namespace): string
    {
        return 'namespaces/' . ($namespace !== '' ? str_replace('\\', '/', $namespace) : '_global') . '.html';
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
