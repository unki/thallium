<?php

/**
 * This file is part of Thallium.
 *
 * Thallium, a PHP-based framework for web applications.
 * Copyright (C) <2015-2016> <Andreas Unterkircher>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.

 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 */

/**
 * This file is the starting point for an application.
 *
 * It registers the autoloader and initializes the applications MainController -
 * which then takes over control.
 *
 * Usually this script is kicked by public/index.php. index.php is the only
 * script that needs to be accessible via the web server. Everything else that
 * belongs to Thallium, or any application that is based on it, should be out
 * of the document root and not accessible via the web server.
 *
 * @license AGPL3
 * @copyright 2015-2016 Andreas Unterkircher <unki@netshadow.net>
 * @author Andreas Unterkircher <unki@netshadow.net>
 */

declare(strict_types=1);

require_once 'vendor/Thallium/static.php';
require_once 'vendor/autoload.php';

spl_autoload_register("autoload");

$mode = null;

try {
    $thallium = new \Thallium\Controllers\MainController($mode);
} catch (\Exception $e) {
    print $e->getMessage();
    exit(1);
}

if (!is_null($mode)) {
    exit(0);
}

if (!$thallium->startup()) {
    exit(1);
}

exit(0);

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
