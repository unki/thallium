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

$csp_string = <<<'EOT'
default-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; font-src 'self' 'unsafe-inline' data: fonts.googleapis.com fonts.gstatic.com https://fonts.gstatic.com; style-src 'self' 'unsafe-inline' fonts.googleapis.com https://fonts.googleapis.com fonts.gstatic.com;
EOT;

header("X-Frame-Options: DENY");
header("Content-Security-Policy: {$csp_string}"); // FF 23+ Chrome 25+ Safari 7+ Opera 19+
header("X-Content-Security-Policy: {$csp_string}"); // IE 10+
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");

require_once '../main.php';

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
