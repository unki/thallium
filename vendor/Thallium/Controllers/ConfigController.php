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

class ConfigController extends DefaultController
{
    protected static $config_file_local = "config.ini";
    protected static $config_file_dist = "config.ini.dist";
    protected $config;

    public function __construct()
    {
        if (!file_exists(static::CONFIG_DIRECTORY)) {
            static::raiseError(sprintf(
                '%s(), configuration directory "%s" does not exist!',
                __METHOD__,
                static::CONFIG_DIRECTORY
            ), true);
            return;
        }

        if (!is_executable(static::CONFIG_DIRECTORY)) {
            static::raiseError(sprintf(
                '%s(), unable to enter config directory "%s"!',
                __METHOD__,
                static::CONFIG_DIRECTORY
            ), true);
            return;
        }

        if (!function_exists("parse_ini_file")) {
            static::raiseError(
                __METHOD__ .'(), PHP does not provide required parse_ini_file() function!',
                true
            );
            return;
        }

        $config_pure = array();

        foreach (array('dist', 'local') as $config) {
            if (($config_pure[$config] = $this->readConfig($config)) === false) {
                static::raiseError(sprintf(
                    '%s(), readConfig("%s") returned false!',
                    __METHOD__,
                    $config
                ), true);
                return;
            }
        }

        if (!isset($config_pure['dist']) ||
            empty($config_pure['dist']) ||
            !is_array($config_pure['dist'])
        ) {
            static::raiseError(__METHOD__ .'(), no valid config.ini.dist available!', true);
            return;
        }

        if (!isset($config_pure['local']) ||
            !is_array($config_pure['local'])
        ) {
            $config_pure['local'] = array();
        }

        if (!($this->config = array_replace_recursive($config_pure['dist'], $config_pure['local']))) {
            static::raiseError(sprintf(
                '%s(), failed to merge "%s" with "%s"!',
                __METHOD__,
                static::$config_file_local,
                static::$config_file_dist
            ), true);
            return;
        }

        return;
    }

    protected function readConfig($config_target)
    {
        $config_file = sprintf('config_file_%s', $config_target);

        $config_fqpn = sprintf(
            '%s/%s',
            static::CONFIG_DIRECTORY,
            static::$$config_file
        );

        // missing config.ini is ok
        if ($config_target == 'local' && !file_exists($config_fqpn)) {
            return true;
        }

        if (!file_exists($config_fqpn)) {
            static::raiseError(sprintf(
                '%s(), configuration file "%s" does not exist!',
                __METHOD__,
                $config_fqdn
            ));
            return false;
        }

        if (!is_readable($config_fqpn)) {
            static::raiseError(sprintf(
                '%s(), unable to read configuration file "%s"!',
                __METHOD__,
                $config_fqpn
            ));
            return false;
        }

        if (($config_ary = parse_ini_file($config_fqpn, true)) === false) {
            static::raiseError(sprintf(
                '%s(), parse_ini_file() failed on "%s"! Please check the files syntax!',
                __METHOD__,
                $config_fqpn
            ));
            return false;
        }

        if (empty($config_ary) || !is_array($config_ary)) {
            static::raiseError(sprintf(
                '%s(), invalid configuration retrieved from "%s"! Please check the files syntax!',
                __METHOD__,
                $config_fqpn
            ));
            return false;
        }

        if (!isset($config_ary['app']) || empty($config_ary['app']) || !array($config_ary['app'])) {
            static::raiseError(__METHOD__.'(), mandatory config section [app] is not configured!');
            return false;
        }

        // remove trailing slash from base_web_path if any, but not if base_web_path = /
        if (isset($config_ary['app']['base_web_path']) &&
            !empty($config_ary['app']['base_web_path']) &&
            $config_ary['app']['base_web_path'] != '/'
        ) {
            $config_ary['app']['base_web_path'] = rtrim($config_ary['app']['base_web_path'], '/');
        }

        return $config_ary;
    }

    public function getDatabaseConfiguration()
    {
        if (!isset($this->config['database']) ||
            empty($this->config['database']) ||
            !is_array($this->config['database'])
        ) {
            return false;
        }

        return $this->config['database'];
    }

    public function getDatabaseType()
    {
        if (($dbconfig = $this->getDatabaseConfiguration()) === false) {
            return false;
        }

        if (!isset($dbconfig['type']) ||
            empty($dbconfig['type']) ||
            !is_string($dbconfig['type'])
        ) {
            return false;
        }

        return $dbconfig['type'];
    }

    public function getWebPath()
    {
        if (!isset($this->config['app']['base_web_path']) ||
            empty($this->config['app']['base_web_path']) ||
            !is_string($this->config['app']['base_web_path'])
        ) {
            return false;
        }

        return $this->config['app']['base_web_path'];
    }

    public function getPageTitle()
    {
        if (!isset($this->config['app']['page_title']) ||
            empty($this->config['app']['page_title']) ||
            !is_string($this->config['app']['page_title'])
        ) {
            return false;
        }

        return $this->config['app']['page_title'];
    }

    protected function isEnabled($value)
    {
        if (!in_array($value, array('yes','y','true','on','1'))) {
            return false;
        }

        return true;
    }

    protected function isDisabled($value)
    {
        if (!in_array($value, array('no','n','false','off','0'))) {
            return false;
        }

        return true;
    }

    public function inMaintenanceMode()
    {
        if (!isset($this->config['app']['maintenance_mode']) ||
            empty($this->config['app']['maintenance_mode'])
        ) {
            return false;
        }

        if ($this->isDisabled($this->config['app']['maintenance_mode'])) {
            return false;
        }

        if (!$this->isEnabled($this->config['app']['maintenance_mode'])) {
            static::raiseError(
                __METHOD__ .'(), configuration option "maintenance_mode" in [app] section is invalid!'
            );
            return false;
        }

        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
