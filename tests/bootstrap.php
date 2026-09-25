<?php
/**
 * PHPUnit bootstrap — loads Brain\Monkey stubs + plugin files.
 *
 * @package Safari_Travel\Tests
 */

declare(strict_types=1);

// ── Composer autoloader ────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/vendor/autoload.php';

// ── Brain\Monkey WordPress stubs ───────────────────────────────────────────
use Brain\Monkey;
use Brain\Monkey\Functions;

// Suppress constant-already-defined notices when re-running.
if (! defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/tests/_stubs/');
}
if (! defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

// ── Plugin constants ───────────────────────────────────────────────────────
define('SAFARI_LEADS_VERSION', '1.0.0');
define('SAFARI_LEADS_FILE', dirname(__DIR__) . '/plugins/safari-leads/safari-leads.php');
define('SAFARI_LEADS_DIR', dirname(__DIR__) . '/plugins/safari-leads/');
define('SAFARI_LEADS_URL', 'http://localhost/wp-content/plugins/safari-leads/');

define('SAFARI_CORE_VERSION', '1.0.0');
define('SAFARI_CORE_FILE', dirname(__DIR__) . '/plugins/safari-core/safari-core.php');
define('SAFARI_CORE_DIR', dirname(__DIR__) . '/plugins/safari-core/');

// ── Global test setup per test ─────────────────────────────────────────────
// PHPUnit TestCase classes using Brain\Monkey should call setUp/tearDown via trait.
// See: https://github.com/Brain-WP/BrainMonkey#phpunit-integration

// Stub WP_Error for unit tests.
if (! class_exists('WP_Error')) {
    require_once __DIR__ . '/_stubs/class-wp-error.php';
}
