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
 * This file contains the autoloader function that PHP uses to find and
 * load all Thalliums - and also other vendors - clases.
 *
 * @license AGPL3
 * @copyright 2015-2016 Andreas Unterkircher <unki@netshadow.net>
 * @author Andreas Unterkircher <unki@netshadow.net>
 */

/**
 * generic autoloader
 *
 * @param string $class
 * @return void
 * @throws \Exception
 */
function autoload($class)
{
    if (!isset($class) || empty($class) || !is_string($class)) {
        throw new \Exception(__METHOD__ .'(), $class parameter is invalid!');
        return;
    }

    if (!defined('APP_BASE')) {
        throw new \Exception(__METHOD__ .'(), constant APP_BASE is not defined!');
        return;
    }

    $prefixes = array(
        'Thallium',
    );

    $class = str_replace("\\", "/", $class);
    $parts = explode('/', $class);

    if (!is_array($parts) || empty($parts)) {
        throw new \Exception(__METHOD__ .'(), $class explode() failed!');
        return;
    }

    # only take care outloading of our namespace
    if (!in_array($parts[0], $prefixes)) {
        return;
    }

    $filename = APP_BASE;
    $filename.= "/vendor/";

    $filename.= implode('/', $parts);
    $filename.= '.php';

    if (!file_exists($filename)) {
        throw new \Exception(sprintf('%s(), file %s not found!', __METHOD__, $filename));
        return;
    }

    if (!is_readable($filename)) {
        throw new \Exception(sprintf('%s(), file %s not readable!', __METHOD__, $filename));
        return;
    }

    if (!require_once($filename)) {
        throw new \Exception(sprintf('%s(), require() failed on file %s!', __METHOD__, $filename));
        return;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
