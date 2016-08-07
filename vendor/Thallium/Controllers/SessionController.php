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

namespace Thallium\Controllers;

/**
 * SessionController handles the PHP session information
 *
 * @package Thallium\Controllers\SessionController
 * @subpackage Controllers
 * @license AGPL3
 * @copyright 2015-2016 Andreas Unterkircher <unki@netshadow.net>
 * @author Andreas Unterkircher <unki@netshadow.net>
 */
class SessionController extends DefaultController
{
    /** @var array $one_time_identifiers */
    protected $one_time_identifiers = array();

    /**
     * class constructor
     *
     * @param none
     * @return void
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function __construct()
    {
        if (!empty(session_id())) {
            return;
        }

        if (($http_only = ini_get('session.cookie_httponly')) === false ||
            !isset($http_only) ||
            empty($http_only) ||
            !$http_only
        ) {
            if (ini_set('session.cookie_httponly', 1) === false) {
                $this->raiseError(__METHOD__ .'(), failed to set session.cookie_httponly=1!', true);
                return;
            }
        }

        if (!session_start()) {
            static::raiseError(__METHOD__ .'(), session_start() returned false!', true);
            return;
        }

        // check if we have been fooled by a client sending a cookie with an empty or invalid sessionid.
        $sid = session_id();

        if (empty($sid) || !preg_match('/^[a-zA-Z0-9,\-]{22,40}$/', $sid)) {
            session_regenerate_id();
            session_start();
        }

        return;
    }

    /**
     * returns an one-time-identifier-key
     *
     * @param string $name
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function getOnetimeIdentifierId($name)
    {
        if (!isset($name) || empty($name) || !is_string($name)) {
            static::raiseError(__METHOD__ .'(), $name parameter is invalid!');
            return false;
        }

        if (array_key_exists($name, $this->one_time_identifiers) &&
            !empty($this->one_time_identifiers[$name])) {
            return $this->one_time_identifiers[$name];
        }

        global $thallium;

        if (($guid = $thallium->createGuid()) === false) {
            static::raiseError(get_class($thallium) .'::createGuid() returned false!');
            return false;
        }

        if (empty($guid) || !$thallium->isValidGuidSyntax($guid)) {
            static::raiseError(get_class($thallium) .'::createGuid() returned an invalid GUID');
            return false;
        }

        $this->one_time_identifiers[$name] = $guid;
        return $guid;
    }

    /**
     * returns the PHP internal session ID.
     *
     * @param none
     * @return string
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function getSessionId()
    {
        return session_id();
    }

    /**
     * checks if the provided $key leads to a registered session variable.
     *
     * @param string $key
     * @param string|null $prefix
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function hasVariable($key, $prefix = null)
    {
        if (!isset($key) || empty($key) || !is_string($key)) {
            static::raiseError(__METHOD__ .'(), $key parameter is invalid!');
            return false;
        }

        if (isset($prefix) && !empty($prefix) && !is_string($prefix)) {
            static::raiseError(__METHOD__ .'(), $prefix parameter is invalid!');
            return false;
        }

        $var_key = (!isset($prefix) || empty($prefix)) ? $key : $prefix .'_'. $key;

        if (!isset($_SESSION[$var_key])) {
            return false;
        }

        return true;
    }

    /**
     * retrieves an registered session variable identified by $key.
     *
     * @param string $key
     * @param string|null $prefix
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function getVariable($key, $prefix = null)
    {
        if (!isset($key) || empty($key) || !is_string($key)) {
            static::raiseError(__METHOD__ .'(), $key parameter is invalid!');
            return false;
        }

        if (isset($prefix) && !empty($prefix) && !is_string($prefix)) {
            static::raiseError(__METHOD__ .'(), $prefix parameter is invalid!');
            return false;
        }

        if (!$this->hasVariable($key, $prefix)) {
            static::raiseError(__CLASS__ .'::hasVariable() returned false!');
            return false;
        }

        $var_key = (!isset($prefix) || empty($prefix)) ? $key : $prefix .'_'. $key;

        return $_SESSION[$var_key];
    }

    /**
     * store a value in a session variable identified by $key
     *
     * @param string $key
     * @param string|int $value
     * @param string|null $prefix
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function setVariable($key, $value, $prefix = null)
    {
        if (!isset($key) || empty($key) || !is_string($key)) {
            static::raiseError(__METHOD__ .'(), $key parameters are invalid!');
            return false;
        }

        if (!isset($value) || (isset($value) && !is_string($value) && !is_numeric($value))) {
            static::raiseError(__METHOD__ .'(), $value parameters are invalid!');
            return false;
        }

        if (isset($prefix) && !empty($prefix) && !is_string($prefix)) {
            static::raiseError(__METHOD__ .'(), $prefix parameter is invalid!');
            return false;
        }

        $var_key = (!isset($prefix) || empty($prefix)) ? $key : $prefix .'_'. $key;

        $_SESSION[$var_key] = $value;
        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
