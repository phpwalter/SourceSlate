<?php

declare(strict_types=1);

namespace SourceSlate\Model;

final class SymbolIndex
{
    /** @var array<string,list<SymbolReference>> */
    private array $byName = [];

    private function __construct()
    {
    }

    public static function fromProject(ProjectDocumentation $project): self
    {
        $index = new self();

        foreach ($project->files as $file) {
            foreach ($file->types as $type) {
                $typeUrl = self::typePath($type);
                $index->add(new SymbolReference($type->kind, $type->fullyQualifiedName, $typeUrl, $type->sourcePath, $type->line));
                $index->addAlias($type->name, $type->fullyQualifiedName);

                foreach ($type->methods as $method) {
                    $qualified = $type->fullyQualifiedName . '::' . $method->name . '()';
                    $index->add(new SymbolReference('method', $qualified, $typeUrl . '#method-' . rawurlencode(strtolower($method->name)), $type->sourcePath, $method->line));
                }
                foreach ($type->properties as $property) {
                    $qualified = $type->fullyQualifiedName . '::$' . $property->name;
                    $index->add(new SymbolReference('property', $qualified, $typeUrl . '#property-' . strtolower($property->name), $type->sourcePath, $type->line));
                }
                foreach ($type->constants as $constant) {
                    $qualified = $type->fullyQualifiedName . '::' . $constant->name;
                    $index->add(new SymbolReference('constant', $qualified, $typeUrl . '#constant-' . strtolower($constant->name), $type->sourcePath, $type->line));
                }
                foreach ($type->enumCases as $case) {
                    $qualified = $type->fullyQualifiedName . '::' . $case->name;
                    $index->add(new SymbolReference('enum-case', $qualified, $typeUrl . '#case-' . strtolower($case->name), $type->sourcePath, $type->line));
                }
            }

            foreach ($file->functions as $function) {
                $qualified = $function->fullyQualifiedName . '()';
                $index->add(new SymbolReference(
                    'function',
                    $qualified,
                    'functions/index.html#function-' . rawurlencode(strtolower($function->fullyQualifiedName)),
                    $function->sourcePath,
                    $function->line,
                ));
            }
        }

        foreach ($index->byName as &$references) {
            usort($references, static fn (SymbolReference $a, SymbolReference $b): int => $a->qualifiedName <=> $b->qualifiedName);
        }
        unset($references);

        return $index;
    }

    public function resolve(string $reference, ?string $contextNamespace = null): ?SymbolReference
    {
        $normalized = $this->normalize($reference);
        if ($normalized === '') {
            return null;
        }

        $candidates = $this->byName[strtolower($normalized)] ?? [];
        if (count($candidates) === 1) {
            return $candidates[0];
        }
        if (count($candidates) > 1) {
            return null;
        }

        if ($contextNamespace !== null && $contextNamespace !== '' && !str_contains($normalized, '\\')) {
            $qualified = trim($contextNamespace, '\\') . '\\' . $normalized;
            $candidates = $this->byName[strtolower($qualified)] ?? [];
            return count($candidates) === 1 ? $candidates[0] : null;
        }

        return null;
    }

    /** @return list<SymbolReference> */
    public function matches(string $reference): array
    {
        return $this->byName[strtolower($this->normalize($reference))] ?? [];
    }

    private function add(SymbolReference $reference): void
    {
        $key = strtolower($reference->qualifiedName);
        $this->byName[$key][] = $reference;

        $withoutCall = str_ends_with($reference->qualifiedName, '()')
            ? substr($reference->qualifiedName, 0, -2)
            : $reference->qualifiedName;
        $this->byName[strtolower($withoutCall)][] = $reference;
    }

    private function addAlias(string $alias, string $qualifiedName): void
    {
        $matches = $this->byName[strtolower($qualifiedName)] ?? [];
        foreach ($matches as $reference) {
            $this->byName[strtolower($alias)][] = $reference;
        }
    }

    private function normalize(string $reference): string
    {
        $value = trim($reference);
        if ($value === '') {
            return '';
        }
        return ltrim($value, '\\');
    }

    private static function typePath(TypeDocumentation $type): string
    {
        $directory = match ($type->kind) {
            'interface' => 'interfaces',
            'trait' => 'traits',
            'enum' => 'enums',
            default => 'classes',
        };
        return $directory . '/' . str_replace('\\', '/', $type->fullyQualifiedName) . '.html';
    }
}
