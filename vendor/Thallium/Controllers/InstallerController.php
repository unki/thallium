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
 * InstallerController is mainly used to install the database
 * table schema into the configured database.
 *
 * @package Thallium\Controllers\InstallerController
 * @subpackage Controllers
 * @license AGPL3
 * @copyright 2015-2016 Andreas Unterkircher <unki@netshadow.net>
 * @author Andreas Unterkircher <unki@netshadow.net>
 */
class InstallerController extends DefaultController
{
    /**
     * @var int $schema_version_before holds the version number of the application
     * schema before the upgrade has been performed
     */
    protected $schema_version_before;

    /**
     * @var int $framework_schema_version_before holds the version number of the
     * framework schema before the upgrade has been performed
     */
    protected $framework_schema_version_before;

    /**
     * class constructor
     *
     * @param string $mode allow specifying the mode Thallium starts into
     * @return void
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function __construct()
    {
        global $db, $config;

        if (!isset($db) ||
            empty($db) ||
            !is_object($db) ||
            !is_a($db, 'Thallium\Controllers\DatabaseController')
        ) {
            static::raiseError(__METHOD__ .'(), it looks like DatabaseController is not available!', true);
            return;
        }

        if (!isset($config) ||
            empty($config) ||
            !is_object($config) ||
            !is_a($config, 'Thallium\Controllers\ConfigController')
        ) {
            static::raiseError(__METHOD__ .'(), it looks like ConfigController is not available!', true);
            return;
        }

        return;
    }

    /**
     * this is the public accessible method that triggers the installation process.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function setup()
    {
        global $db, $config;

        if ($db->checkTableExists("TABLEPREFIXmeta")) {
            if (($this->schema_version_before = $db->getApplicationDatabaseSchemaVersion()) === false) {
                static::raiseError(get_class($db) .'::getApplicationDatabaseSchemaVersion() returned false!');
                return false;
            }
            if (($this->framework_schema_version_before = $db->getFrameworkDatabaseSchemaVersion()) === false) {
                static::raiseError(get_class($db) .'::getFrameworkDatabaseSchemaVersion() returned false!');
                return false;
            }
        }

        if (!isset($this->schema_version_before)) {
            $this->schema_version_before = 0;
        }

        if (!isset($this->framework_schema_version_before)) {
            $this->framework_schema_version_before = 0;
        }

        if ($this->schema_version_before < $db->getApplicationSoftwareSchemaVersion() ||
            $this->framework_schema_version_before < $db->getFrameworkSoftwareSchemaVersion()
        ) {
            if (!$this->createDatabaseTables()) {
                static::raiseError(__CLASS__ .'::createDatabaseTables() returned false!');
                return false;
            }
        }

        if ($db->getApplicationDatabaseSchemaVersion() < $db->getApplicationSoftwareSchemaVersion() ||
            $db->getFrameworkDatabaseSchemaVersion() < $db->getFrameworkSoftwareSchemaVersion()
        ) {
            if (!$this->upgradeDatabaseSchema()) {
                static::raiseError(__CLASS__ .'::upgradeDatabaseSchema() returned false!');
                return false;
            }
        }

        if (!empty($this->schema_version_before)) {
            printf(
                'Application database schema version before upgrade: %s<br />',
                $this->schema_version_before
            );
        }

        printf(
            'Application software supported schema version: %s<br />',
            $db->getApplicationSoftwareSchemaVersion()
        );

        printf(
            'Application database schema version after upgrade: %s<br />',
            $db->getApplicationDatabaseSchemaVersion()
        );

        print '<br /><br />';

        if (!empty($this->framework_schema_version_before)) {
            printf(
                'Framework database schema version before upgrade: %s<br />',
                $this->framework_schema_version_before
            );
        }

        printf(
            'Framework software supported schema version: %s<br />',
            $db->getFrameworkSoftwareSchemaVersion()
        );

        printf(
            'Framework database schema version after upgrade: %s<br />',
            $db->getFrameworkDatabaseSchemaVersion()
        );

        if (($base_path = $config->getWebPath()) === true) {
            static::raiseError(get_class($config) .'"::getWebPath() returned false!');
            return false;
        }

        printf(
            '<br /><a href="%s">Return to application</a><br />',
            $base_path
        );

        return true;
    }

    /**
     * creates application- and framework-specific tables in the database
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final protected function createDatabaseTables()
    {
        if (!$this->createFrameworkDatabaseTables()) {
            static::raiseError(__CLASS__ .'::createFrameworkDatabaseTables() returned false!');
            return false;
        }

        if (!$this->createApplicationDatabaseTables()) {
            static::raiseError(__CLASS__ .'::createApplicationDatabaseTables() returned false!');
            return false;
        }

        return true;
    }

    /**
     * creates framework-specific tables in the database
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final protected function createFrameworkDatabaseTables()
    {
        global $db;

        if (!$db->checkTableExists("TABLEPREFIXaudit")) {
            $table_sql = "CREATE TABLE `TABLEPREFIXaudit` (
                `audit_idx` int(11) NOT NULL AUTO_INCREMENT,
                `audit_guid` varchar(255) DEFAULT NULL,
                `audit_type` varchar(255) DEFAULT NULL,
                `audit_scene` varchar(255) DEFAULT NULL,
                `audit_object_guid` varchar(255) DEFAULT NULL,
                `audit_message` text,
                `audit_time` timestamp(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
                PRIMARY KEY (`audit_idx`)
                )
                ENGINE=InnoDB DEFAULT CHARSET=utf8;";

            if ($db->query($table_sql) === false) {
                static::raiseError(__METHOD__ .'(), failed to create "audit" table!');
                return false;
            }
        }

        if (!$db->checkTableExists("TABLEPREFIXmessage_bus")) {
            $table_sql = "CREATE TABLE `TABLEPREFIXmessage_bus` (
                `msg_idx` int(11) NOT NULL AUTO_INCREMENT,
                `msg_guid` varchar(255) DEFAULT NULL,
                `msg_session_id` varchar(255) NOT NULL,
                `msg_submit_time` timestamp(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
                `msg_scope` varchar(255) DEFAULT NULL,
                `msg_command` varchar(255) NOT NULL,
                `msg_body` varchar(4096) NOT NULL,
                `msg_value` varchar(255) DEFAULT NULL,
                `msg_in_processing` varchar(1) DEFAULT NULL,
                PRIMARY KEY (`msg_idx`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8";

            if ($db->query($table_sql) === false) {
                static::raiseError(__METHOD__ .'(), failed to create "message_bus" table!');
                return false;
            }
        }

        if (!$db->checkTableExists("TABLEPREFIXjobs")) {
            $table_sql = "CREATE TABLE `TABLEPREFIXjobs` (
                `job_idx` int(11) NOT NULL AUTO_INCREMENT,
                `job_guid` varchar(255) DEFAULT NULL,
                `job_command` varchar(255) NOT NULL,
                `job_parameters` varchar(4096) DEFAULT NULL,
                `job_session_id` varchar(255) NOT NULL,
                `job_request_guid` varchar(255) DEFAULT NULL,
                `job_time` timestamp(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
                `job_in_processing` varchar(1) DEFAULT NULL,
                PRIMARY KEY (`job_idx`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8";

            if ($db->query($table_sql) === false) {
                static::raiseError(__METHOD__ .'(), failed to create "jobs" table!');
                return false;
            }
        }

        if (!$db->checkTableExists("TABLEPREFIXmeta")) {
            $table_sql = "CREATE TABLE `TABLEPREFIXmeta` (
                `meta_idx` int(11) NOT NULL auto_increment,
                `meta_key` varchar(255) default NULL,
                `meta_value` varchar(255) default NULL,
                PRIMARY KEY  (`meta_idx`),
                UNIQUE KEY `meta_key` (`meta_key`)
                )
                ENGINE=MyISAM DEFAULT CHARSET=utf8;";

            if ($db->query($table_sql) === false) {
                static::raiseError(__METHOD__ .'(), failed to create "meta" table!');
                return false;
            }

            if (!$db->setDatabaseSchemaVersion()) {
                static::raiseError(get_class($db) .'::setDatabaseFrameworkSchemaVersion() returned false!');
                return false;
            }

            if (!$db->setDatabaseSchemaVersion(null, 'framework')) {
                static::raiseError(get_class($db) .'::setDatabaseFrameworkSchemaVersion() returned false!');
                return false;
            }
        }

        if (!$db->getApplicationDatabaseSchemaVersion()) {
            if (!$db->setDatabaseSchemaVersion()) {
                static::raiseError(get_class($db) .'::setDatabaseSchemaVersion() returned false!');
                return false;
            }
        }

        if (!$db->getFrameworkDatabaseSchemaVersion()) {
            if (!$db->setDatabaseSchemaVersion(null, 'framework')) {
                static::raiseError(get_class($db) .'::setDatabaseSchemaVersion() returned false!');
                return false;
            }
        }

        return true;
    }

    /**
     * creates application-specific tables in the database.
     * normally this method has to be overriden with a new
     * method that actually knows about wapplication-
     * specific tables.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    protected function createApplicationDatabaseTables()
    {
        /* this method should be overloaded to install application specific tables. */
        return true;
    }

    /**
     * upgrades application- and framework-specific tables in the database
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final protected function upgradeDatabaseSchema()
    {
        if (!$this->upgradeApplicationDatabaseSchema()) {
            static::raiseError(__CLASS__ .'::upgradeApplicationDatabaseSchema() returned false!');
            return false;
        }

        if (!$this->upgradeFrameworkDatabaseSchema()) {
            static::raiseError(__CLASS__ .'::upgradeFrameworkDatabaseSchema() returned false!');
            return false;
        }

        return true;
    }

    /**
     * upgrades application-specific tables in the database
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    protected function upgradeApplicationDatabaseSchema()
    {
        global $db;

        if (($software_version = $db->getApplicationSoftwareSchemaVersion()) === false) {
            static::raiseError(get_class($db) .'::getSoftwareSchemaVersion() returned false!');
            return false;
        }

        if ($software_version < 1) {
            static::raiseError(__METHOD__ .'(), invalid framework schema version found!');
            return false;
        }

        if (($db_version = $db->getApplicationDatabaseSchemaVersion()) === false) {
            static::raiseError(get_class($db) .'::getApplicationDatabaseSchemaVersion() returned false!');
            return false;
        }

        if ($db_version >= $software_version) {
            return true;
        }

        for ($i = $db_version+1; $i <= $software_version; $i++) {
            $method_name = "upgradeApplicationDatabaseSchemaV{$i}";

            if (!method_exists($this, $method_name)) {
                static::raiseError(__METHOD__ .'(), no upgrade method found for version '. $i);
                return false;
            }

            if (!is_callable(array($this, $method_name))) {
                static::raiseError(sprintf(
                    '%s(), %s::%s() is not callable!',
                    __METHOD__,
                    __CLASS__,
                    $method_name
                ));
                return false;
            }

            $this->write("Invoking {$method_name}().<br />\n");

            if (!$this->$method_name()) {
                static::raiseError(__CLASS__ ."::{$method_name}() returned false!");
                return false;
            }
        }

        return true;
    }

    /**
     * upgrades framework-specific tables in the database
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final protected function upgradeFrameworkDatabaseSchema()
    {
        global $db;

        if (($software_version = $db->getFrameworkSoftwareSchemaVersion()) === false) {
            static::raiseError(get_class($db) .'::getFrameworkSoftwareSchemaVersion() returned false!');
            return false;
        }

        if ($software_version < 1) {
            static::raiseError(__METHOD__ .'(), invalid framework schema version found!');
            return false;
        }

        if (($db_version = $db->getFrameworkDatabaseSchemaVersion()) === false) {
            static::raiseError(get_class($db) .'::getFrameworkDatabaseSchemaVersion() returned false!');
            return false;
        }

        if ($db_version >= $software_version) {
            return true;
        }

        for ($i = $db_version+1; $i <= $software_version; $i++) {
            $method_name = "upgradeFrameworkDatabaseSchemaV{$i}";

            if (!method_exists($this, $method_name)) {
                static::raiseError(__METHOD__ .'(), no upgrade method found for version '. $i);
                return false;
            }

            if (!is_callable(array($this, $method_name))) {
                static::raiseError(sprintf(
                    '%s(), %s::%s() is not callable!',
                    __METHOD__,
                    __CLASS__,
                    $method_name
                ));
                return false;
            }

            $this->write("Invoking {$method_name}().<br />\n");

            if (!$this->$method_name()) {
                static::raiseError(__CLASS__ ."::{$method_name}() returned false!");
                return false;
            }
        }

        return true;
    }

    /**
     * upgrade to framework schema v2
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final protected function upgradeFrameworkDatabaseSchemaV2()
    {
        global $db;

        if ($db->checkColumnExists('TABLEPREFIXjobs', 'job_command')) {
            $db->setDatabaseSchemaVersion(2, 'framework');
            return true;
        }

        $result = $db->query(
            "ALTER TABLE
                TABLEPREFIXjobs
            ADD COLUMN
                `job_command` varchar(255) NOT NULL
            AFTER
                job_guid,
            ADD COLUMN
                `job_parameters` varchar(255) DEFAULT NULL
            AFTER
                job_command"
        );

        if ($result === false) {
            static::raiseError(__METHOD__ .'() failed!');
            return false;
        }

        $db->setDatabaseSchemaVersion(2, 'framework');
        return true;
    }

    /**
     * upgrade to framework schema v3
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final protected function upgradeFrameworkDatabaseSchemaV3()
    {
        global $db;

        $result = $db->query(
            "ALTER TABLE
                TABLEPREFIXmessage_bus
            MODIFY COLUMN
                `msg_body` varchar(4096) DEFAULT NULL"
        );

        if ($result === false) {
            static::raiseError(__METHOD__ .'() failed!');
            return false;
        }

        $result = $db->query(
            "ALTER TABLE
                TABLEPREFIXjobs
            MODIFY COLUMN
                `job_parameters` varchar(4096) DEFAULT NULL"
        );

        $db->setDatabaseSchemaVersion(3, 'framework');
        return true;
    }

    /**
     * upgrade to framework schema v4
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final protected function upgradeFrameworkDatabaseSchemaV4()
    {
        global $db;

        $result = $db->query(
            "ALTER TABLE
                TABLEPREFIXaudit
            ADD
                `audit_object_guid` varchar(255) DEFAULT NULL
            AFTER
                audit_scene"
        );

        if ($result === false) {
            static::raiseError(__METHOD__ .'() failed!');
            return false;
        }

        $db->setDatabaseSchemaVersion(4, 'framework');
        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
