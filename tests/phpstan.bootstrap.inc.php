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

if (file_exists($base . '/vendor/autoload.php') == TRUE)
{
    // Load composer autoloader.
    $autoloadFile = $base . '/vendor/autoload.php';
}
else
{
    // Load decomposer autoloade.
    $autoloadFile = $base . '/decomposer.autoload.inc.php';
}

require_once $autoloadFile;

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
