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

namespace Thallium\Controllers;

class SessionController extends DefaultController
{
    public function __construct()
    {
        if (!empty(session_id())) {
            return true;
        }

        if (!session_start()) {
            static::raiseError("Failed to initialize session!");
            return false;
        }
    }

    public function getOnetimeIdentifierId($name)
    {
        if (isset($this->$name) && !empty($this->$name)) {
            return $this->$name;
        }

        global $thallium;

        if (!($guid = $thallium->createGuid())) {
            static::raiseError(get_class($thallium) .'::createGuid() returned false!');
            return false;
        }

        if (empty($guid) || !$thallium->isValidGuidSyntax($guid)) {
            static::raiseError(get_class($thallium) .'::createGuid() returned an invalid GUID');
            return false;
        }

        $this->$name = $guid;
        return $this->$name;
    }

    public function getSessionId()
    {
        return session_id();
    }

    public function getVariable($key)
    {
        if (!isset($key) || empty($key) || !is_string($key)) {
            static::raiseError(__METHOD__ .'(), $key parameter is invalid!');
            return false;
        }

        if (!isset($_SESSION[$key])) {
            return false;
        }

        return $_SESSION[$key];
    }

    public function setVariable($key, $value)
    {
        if (!isset($key) || empty($key) || !is_string($key) ||
            !isset($value) || (!is_string($value) && !is_numeric($value))
        ) {
            static::raiseError(__METHOD__ .'(), $key and/or $value parameters are invalid!');
            return false;
        }

        $_SESSION[$key] = $value;
        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
