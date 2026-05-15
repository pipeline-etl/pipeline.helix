<?php

/**
 * PHPUnit bootstrap file.
 *
 * Set include path and initialize autoloader.
 *
 * SPDX-FileCopyrightText: Copyright 2025 Framna Netherlands B.V., Zwolle, The Netherlands
 * SPDX-License-Identifier: MIT
 */

$base = __DIR__ . '/../..';

// Define application config lookup path
$paths = [
    get_include_path(),
    $base . '/src',
];

set_include_path(
    implode(':', $paths)
);

require_once $base . '/vendor/autoload.php';

if (defined('TEST_STATICS') === FALSE)
{
    define('TEST_STATICS', __DIR__ . '/statics');
}

?>
