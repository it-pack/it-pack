<?php

declare(strict_types=1);

use ItPack\ConfigureItExplorer\ExplorerApplication;
use ItPack\ConfigureItExplorer\Graph\FromFileGraphProvider;
use ItPack\ConfigureItExplorer\Server\Request;

/**
 * Front controller for the ConfigureIt read-only web explorer.
 *
 * Serve with the bundled runner:
 *   ./bin/configure-it-explorer
 * then open http://127.0.0.1:8080 (override host with CONFIGURE_IT_EXPLORER_HOST).
 *
 * This file is intentionally tiny: it builds the application, turns the PHP
 * request globals into a {@see Request}, and emits the produced {@see Response}.
 * All behaviour lives behind the application object so it is unit-testable
 * without a running server.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = ExplorerApplication::bootstrap(new FromFileGraphProvider());

$app->handle(Request::fromGlobals())->emit();
