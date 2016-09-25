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
 * DefaultController is an abstract class that is used by all
 * the other Thallium Controllers.
 *
 * It declares some common methods, properties and constants.
 *
 * @package Thallium\Controllers\DefaultController
 * @subpackage Controllers
 * @abstract
 * @license AGPL3
 * @copyright 2015-2016 Andreas Unterkircher <unki@netshadow.net>
 * @author Andreas Unterkircher <unki@netshadow.net>
 */
abstract class DefaultController
{
    /**
     * @var string CONFIG_DIRECTORY declares the path to the config directory.
     * APP_BASE is from static.php
     */
    const CONFIG_DIRECTORY = APP_BASE ."/config";

    /**
     * @var string CACHE_DIRECTORY declares the path to the cache directory.
     * APP_BASE is from static.php
     */
    const CACHE_DIRECTORY = APP_BASE ."/cache";

    /** @var string LOG_LEVEL declares the default log-level */
    const LOG_LEVEL = LOG_WARNING;

    /**
     * class constructor
     *
     * @param none
     * @return void
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function __construct()
    {
        return;
    }

    /**
     * method __set() is called on anything writting to a
     * undeclared class property.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function __set($name, $value)
    {
        global $thallium;

        if (!isset($thallium::$permit_undeclared_class_properties)) {
            static::raiseError(__METHOD__ ."(), trying to set an undeclared property {$name}!", true);
            return;
        }

        $this->$name = $value;
        return;
    }

    /**
     * this method provides a generic interface to send a
     * message to the client via the MessageBusController.
     *
     * @param string $command
     * @param string $body
     * @param string $data
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function sendMessage($command, $body, $value = null)
    {
        global $thallium, $mbus;

        if (($prefix = $thallium->getNamespacePrefix()) === false) {
            static::raiseError(__METHOD__ .'(), failed to fetch namespace prefix!');
            return false;
        }

        if (!isset($mbus) ||
            empty($mbus) ||
            !is_object($mbus) ||
            !is_a($mbus, sprintf('%s\Controllers\MessageBusController', $prefix))
        ) {
            static::raiseError(__METHOD__ .'(), MessageBusController is not initialized!');
            return false;
        }

        if (!isset($command) || empty($command) || !is_string($command)) {
            static::raiseError(__METHOD__ .'(), parameter $command is invalid!');
            return false;
        }

        if (!isset($body) || empty($body) || !is_string($body)) {
            static::raiseError(__METHOD__ .'(), parameter $body is invalid!');
            return false;
        }

        if (isset($value) && !empty($value) && !is_string($value)) {
            static::raiseError(__METHOD__ .'(), parameter $value is invalid!');
            return false;
        }

        if (!$mbus->sendMessageToClient($command, $body, $value)) {
            static::raiseError(get_class($mbus) .'::sendMessageToClient() returned false!');
            return false;
        }

        return true;
    }

    /**
     * methods raises an exception by throwing a ExceptionController exception.
     *
     * @param string $text
     * @param bool $stop_execution
     * @param callable|null $catched_exception
     * @return void
     * @throws \Thallium\Controllers\ExceptionController
     */
    public static function raiseError($text, $stop_execution = false, $catched_exception = null)
    {
        if (!isset($text) || empty($text) || !is_string($text)) {
            $text = "Unspecified error.";
        }

        if (!isset($stop_execution) || !is_bool($stop_execution)) {
            $stop_execution = false;
        }

        if (isset($catched_exception) &&
            !is_null($catched_exception) &&
            !is_object($catched_exception)
        ) {
            $catched_exception = null;
        }

        // If in test-mode, just throw the exception and return.
        // It is then phpunits job to pick up the exception.
        if (MainController::inTestMode()) {
            throw new ExceptionController($text, $catched_exception);
            return;
        }

        try {
            throw new ExceptionController($text, $catched_exception);
        } catch (\Thallium\Controllers\ExceptionController $e) {
            if ($e->printsJson()) {
                print $e;
                exit(1);
            }
            // if Previous exceptions have been captured, first display that ones.
            $prev = $e;
            while ($prev = $prev->getPrevious()) {
                print $prev;
            }
            // now finally print the actually captured exception.
            print $e;
        }

        if ($stop_execution === true) {
            trigger_error("Execution stopped.", E_USER_ERROR);
        }

        return;
    }

    /**
     * this method provides a generic output method.
     *
     * depending on configuration, output will be stdout, webservers error_log
     * (by using error_log() function) or a specific log file.
     *
     * @param string $logtext
     * @param int $loglevel
     * @param bool|null $override_output
     * @param bool|null $no_newline
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function write($logtext, $loglevel = LOG_INFO, $override_output = null, $no_newline = null)
    {
        $logtype = 'display';

        if (isset($this->config->logging) && !empty($this->config->logging)) {
            $logtype = $this->config->logging;
        }

        if (isset($override_output) || !empty($override_output)) {
            $logtype = $override_output;
        }

        if ($loglevel > $this->getVerbosity()) {
            return true;
        }

        switch ($logtype) {
            default:
            case 'display':
                print $logtext;
                if (!static::isCmdline()) {
                    print "<br />";
                } elseif (!isset($no_newline)) {
                    print "\n";
                }
                break;
            case 'errorlog':
                error_log($logtext);
                break;
            case 'logfile':
                error_log($logtext, 3, $this->config->log_file);
                break;
        }

        return true;

    }

    /**
     * this method detects if execution is currently in a
     * command-line environment. if so, it will return true.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public static function isCmdline()
    {
        if (php_sapi_name() !== 'cli') {
            return false;
        }

        return true;

    }

    /**
     * this method returns the current log verbosity.
     *
     * @param none
     * @return int
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getVerbosity()
    {
        return static::LOG_LEVEL;
    }

    /**
     * this method returns true if the provided object $obj is
     * based on the model $model.
     *
     * @todo nothing seems to used this method right now. maybe removable?
     *
     * @param object $obj
     * @param string $model
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function requireModel($obj, $model)
    {
        global $thallium;

        if (!isset($obj) || empty($obj) || !is_object($obj)) {
            static::raiseError(__METHOD__ .'(), parameter $obj is invalid!');
            return false;
        }

        if (!isset($model) || empty($model) || !is_string($model)) {
            static::raiseError(__METHOD__ .'(), parameter $model is invalid!');
            return false;
        }

        if (($prefix = $thallium->getNamespacePrefix()) === false) {
            static::raiseError(get_class($thallium) .'::getNamespacePrefix() returned false!');
            return false;
        }

        $model_full = $prefix .'\\Models\\'. $model;

        if (get_class($obj) != $model_full) {
            return false;
        }

        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
