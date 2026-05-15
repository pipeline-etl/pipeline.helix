<?php

/**
 * PHPStan bootstrap file.
 *
 * Set include path and initialize autoloader.
 *
 * SPDX-FileCopyrightText: Copyright 2025 Framna Netherlands B.V., Zwolle, The Netherlands
 * SPDX-License-Identifier: MIT
 */

$base = __DIR__ . '/..';

require_once $base . '/vendor/autoload.php';

// Define application config lookup path
$paths = [
    get_include_path(),
    $base . '/config',
    $base . '/src',
];

set_include_path(
    implode(':', $paths)
);

?>
