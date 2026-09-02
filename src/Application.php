<?php

/**
 * @file Application.php
 * @path src/Application.php
 * @version 1.0.0
 * @date 2026-09-02
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Defines the SourceSlate console application and registers the supported CLI commands.
 */

declare(strict_types=1);

namespace SourceSlate;

use SourceSlate\Command\BuildCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

/**
 * Boots the SourceSlate command-line application.
 *
 * The application owns CLI command registration only. Parsing, documentation
 * modeling, rendering, and source mutation remain delegated to their respective
 * subsystems.
 */
final class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('SourceSlate', '0.1.0-dev');

        $this->add(new BuildCommand());
        $this->setDefaultCommand('build', false);
    }
}
