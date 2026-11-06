<?php

/**
 * AExO MCP SDK - Test Bootstrap
 *
 * Initializes test environment, autoloaders, and fixtures.
 */

declare(strict_types=1);

// Require Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Set error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define test constants
const TEST_ROOT = __DIR__;
const TEST_FIXTURES = TEST_ROOT . '/Support/Fixture';


require_once TEST_FIXTURES.'/SDK/sample_free_function.php';
