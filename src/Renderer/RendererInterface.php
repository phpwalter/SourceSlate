<?php

/**
 * @file RendererInterface.php
 * @path src/Renderer/RendererInterface.php
 * @version 1.0.0
 * @date 2026-09-02
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Defines the output boundary for rendering normalized SourceSlate documentation.
 */

declare(strict_types=1);

namespace SourceSlate\Renderer;

use SourceSlate\Model\ProjectDocumentation;

/**
 * Renders a normalized documentation model into an output representation.
 */
interface RendererInterface
{
    /**
     * Renders documentation to the configured output directory.
     *
     * @param ProjectDocumentation $project Renderer-independent project model.
     * @param non-empty-string $outputDirectory Destination directory.
     */
    public function render(ProjectDocumentation $project, string $outputDirectory): void;
}
