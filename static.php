<?php

/**
 * This file is part of Thallium.
 *
 * Thallium, a PHP-based framework for web applications.
 * Copyright (C) <2015> <Andreas Unterkircher>
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

define('APP_BASE', __DIR__);

define('SIGN_TOP_LEFT', 1);
define('SIGN_TOP_CENTER', 2);
define('SIGN_TOP_RIGHT', 3);
define('SIGN_MIDDLE_LEFT', 4);
define('SIGN_MIDDLE_CENTER', 5);
define('SIGN_MIDDLE_RIGHT', 6);
define('SIGN_BOTTOM_LEFT', 7);
define('SIGN_BOTTOM_CENTER', 8);
define('SIGN_BOTTOM_RIGHT', 9);

if (!constant('LOG_ERR')) {
    define('LOG_ERR', 1);
}
if (!constant('LOG_WARNING')) {
    define('LOG_WARNING', 2);
}
if (!constant('LOG_INFO')) {
    define('LOG_INFO', 3);
}
if (!constant('LOG_DEBUG')) {
    define('LOG_DEBUG', 4);
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
