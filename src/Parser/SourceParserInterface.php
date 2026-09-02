<?php

/**
 * @file SourceParserInterface.php
 * @path src/Parser/SourceParserInterface.php
 * @version 1.0.0
 * @date 2026-09-02
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Defines the parser boundary between PHP source analysis and the documentation model.
 */

declare(strict_types=1);

namespace SourceSlate\Parser;

use SourceSlate\Configuration\Configuration;
use SourceSlate\Model\ProjectDocumentation;

/**
 * Converts PHP source code into a renderer-independent documentation model.
 *
 * Implementations may parse syntax and PHPDoc but must not emit HTML or mutate
 * source files. Identical source and configuration should produce equivalent
 * documentation models.
 */
interface SourceParserInterface
{
    /**
     * Parses a project into normalized documentation data.
     *
     * @param non-empty-string $projectRoot Existing project root.
     * @param Configuration $configuration Normalized build configuration.
     *
     * @return ProjectDocumentation Renderer-independent project model.
     */
    public function parse(string $projectRoot, Configuration $configuration): ProjectDocumentation;
}
