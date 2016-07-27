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

use \PDO;

/**
 * RequirementsController is used to check if all the software
 * requirements as well as required directories to be available
 * with the right permissions.
 *
 * @package Thallium\Controllers\ConfigController
 * @subpackage Controllers
 * @license AGPL3
 * @copyright 2015-2016 Andreas Unterkircher <unki@netshadow.net>
 * @author Andreas Unterkircher <unki@netshadow.net>
 */
class RequirementsController extends DefaultController
{
    /**
     * class constructor
     *
     * @param none
     * @return void
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function __construct()
    {
        if (!constant('APP_BASE')) {
            static::raiseError(__METHOD__ .'(), APP_BASE is not defined!', true);
            return;
        }

        return;
    }

    /**
     * This method gets called from the outside and triggers the controller
     * to perform all checks.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function check()
    {
        $missing = false;

        if (!$this->checkPhp()) {
            $missing = true;
        }

        if (!$this->checkDatabaseSupport()) {
            $missing = true;
        }

        if (!$this->checkExternalLibraries()) {
            $missing = true;
        }

        if (!$this->checkDirectoryPermissions()) {
            $missing = true;
        }

        if ($missing) {
            return false;
        }

        return true;
    }

    /**
     * perform checks for required PHP internal functions and extensions.
     * to perform all checks.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    protected function checkPhp()
    {
        global $config;

        $missing = false;

        if (!(function_exists("microtime"))) {
            static::raiseError(__METHOD__ .'(), microtime() function does not exist!');
            $missing = true;
        }

        if ($missing) {
            return false;
        }

        return true;
    }

    /**
     * perform checks if the configured database type ([database] section)
     * is supported by PHP.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    protected function checkDatabaseSupport()
    {
        global $config;

        $missing = false;

        if (($dbtype = $config->getDatabaseType()) === false) {
            static::raiseError(
                __METHOD__ .'(), incomplete configuration found, can not check requirements!'
            );
            return false;
        }

        switch ($dbtype) {
            case 'mariadb':
            case 'mysql':
                $db_class_name = "mysqli";
                $db_pdo_name = "mysql";
                break;
            case 'sqlite3':
                $db_class_name = "Sqlite3";
                $db_pdo_name = "sqlite";
                break;
            default:
                $db_class_name = null;
                $db_pdo_name = null;
                break;
        }

        if (!$db_class_name || !$db_pdo_name) {
            $this->write("Error - unsupported database configuration, can not check requirements!", LOG_ERR);
            $missing = true;
        }

        if (!class_exists($db_class_name)) {
            $this->write("PHP {$dbtype} extension is missing!", LOG_ERR);
            $missing = true;
        }

        // check for PDO database support support
        if ((array_search($db_pdo_name, PDO::getAvailableDrivers())) === false) {
            $this->write("PDO {$db_pdo_name} support not available", LOG_ERR);
            $missing = true;
        }

        if ($missing) {
            return false;
        }

        return true;
    }

    /**
     * perform checks if external libraries are available.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    protected function checkExternalLibraries()
    {
        global $config;

        $missing = false;

        ini_set('track_errors', 1);

        @include_once 'smarty3/Smarty.class.php';
        if (isset($php_errormsg) && preg_match('/Failed opening.*for inclusion/i', $php_errormsg)) {
            $this->write("Smarty3 template engine is missing!", LOG_ERR);
            $missing = true;
            unset($php_errormsg);
        }

        ini_restore('track_errors');

        if ($missing) {
            return false;
        }

        return true;
    }

    /**
     * perform checks directory permission checks.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    protected function checkDirectoryPermissions()
    {
        global $thallium;
        $missing = false;

        if (!$uid = $thallium->getProcessUserId()) {
            static::raiseError(get_class($thallium) .'::getProcessUserId() returned false!');
            return false;
        }

        if (!$gid = $thallium->getProcessGroupId()) {
            static::raiseError(get_class($thallium) .'::getProcessGroupId() returned false!');
            return false;
        }

        $directories = array(
            self::CONFIG_DIRECTORY => 'r',
            self::CACHE_DIRECTORY => 'w',
        );

        foreach ($directories as $dir => $perm) {
            if (!file_exists($dir) && !mkdir($dir, 0700)) {
                $this->write("failed to create {$dir} directory!", LOG_ERR);
                $missing = true;
                continue;
            }

            if (file_exists($dir) && !is_readable($dir)) {
                $this->write("{$dir} is not readable for {$uid}:{$gid}!", LOG_ERR);
                $missing = true;
                continue;
            }

            if (file_exists($dir) && $perm == 'w' && !is_writeable($dir)) {
                $this->write("{$dir} is not writeable for {$uid}:{$gid}!", LOG_ERR);
                $missing = true;
                continue;
            }
        }

        if ($missing) {
            return false;
        }

        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
