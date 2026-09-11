<?php

/**
 * @file Application.php
 * @path src/Application.php
 * @version 1.3.0
 * @date 2026-09-11
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
use SourceSlate\Command\Cache\CacheClearCommand;
use SourceSlate\Command\Cache\CacheInfoCommand;
use SourceSlate\Command\Cache\CacheListCommand;
use SourceSlate\Command\Cache\CachePruneCommand;
use SourceSlate\Command\Cache\CacheRepairCommand;
use SourceSlate\Command\Cache\CacheVerifyCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

final class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('SourceSlate', '0.1.0-dev');

        $this->add(new BuildCommand());
        $this->add(new CacheListCommand());
        $this->add(new CacheInfoCommand());
        $this->add(new CacheVerifyCommand());
        $this->add(new CacheRepairCommand());
        $this->add(new CachePruneCommand());
        $this->add(new CacheClearCommand());
        $this->setDefaultCommand('build', false);
    }
}
