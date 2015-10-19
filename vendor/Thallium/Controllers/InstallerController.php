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

class InstallerController extends DefaultController
{
    protected $schema_version_before;

    public function setup()
    {
        global $db, $config;

        if ($db->checkTableExists("TABLEPREFIXmeta")) {
            if (($this->schema_version_before = $db->getDatabaseSchemaVersion()) === false) {
                $this->raiseError("DatabaseController::getDatabaseSchemaVersion() returned false!");
                return false;
            }
        }

        if (!isset($this->schema_version_before)) {
            $this->schema_version_before = 0;
        }

        if ($this->schema_version_before < $db->getSoftwareSchemaVersion()) {
            if (!$this->createDatabaseTables()) {
                $this->raiseError("InstallerController::createDatabaseTables() returned false!");
                return false;
            }
        }

        if ($db->getDatabaseSchemaVersion() < $db->getSoftwareSchemaVersion()) {
            if (!$this->upgradeDatabaseSchema()) {
                $this->raiseError("InstallerController::upgradeDatabaseSchema() returned false!");
                return false;
            }
        }

        if (!empty($this->schema_version_before)) {
            print "Database schema version before upgrade: {$this->schema_version_before}<br />\n";
        }
        print "Software supported schema version: {$db->getSoftwareSchemaVersion()}<br />\n";
        print "Database schema version after upgrade: {$db->getDatabaseSchemaVersion()}<br />\n";

        if (!($base_path = $config->getWebPath())) {
            $this->raiseError("ConfigController::getWebPath() returned false!");
            return false;
        }

        print "<a href='{$base_path}'>Return to application</a><br />\n";

        return true;
    }

    protected function createDatabaseTables()
    {
        global $db;

        if (!$db->checkTableExists("TABLEPREFIXaudit")) {
            $table_sql = "CREATE TABLE `TABLEPREFIXaudit` (
                `audit_idx` int(11) NOT NULL AUTO_INCREMENT,
                `audit_guid` varchar(255) DEFAULT NULL,
                `audit_type` varchar(255) DEFAULT NULL,
                `audit_scene` varchar(255) DEFAULT NULL,
                `audit_message` text,
                `audit_time` timestamp(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
                PRIMARY KEY (`audit_idx`)
                    )
                    ENGINE=InnoDB DEFAULT CHARSET=utf8;";

            if ($db->query($table_sql) === false) {
                $this->raiseError("Failed to create 'audit' table");
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
                `msg_body` varchar(255) NOT NULL,
                `msg_value` varchar(255) DEFAULT NULL,
                `msg_in_processing` varchar(1) DEFAULT NULL,
                PRIMARY KEY (`msg_idx`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8";

            if ($db->query($table_sql) === false) {
                $this->raiseError("Failed to create 'message_bus' table");
                return false;
            }
        }

        if (!$db->checkTableExists("TABLEPREFIXjobs")) {
            $table_sql = "CREATE TABLE `TABLEPREFIXjobs` (
                `job_idx` int(11) NOT NULL AUTO_INCREMENT,
                `job_guid` varchar(255) DEFAULT NULL,
                `job_session_id` varchar(255) NOT NULL,
                `job_request_guid` varchar(255) DEFAULT NULL,
                `job_time` timestamp(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
                `job_in_processing` varchar(1) DEFAULT NULL,
                PRIMARY KEY (`job_idx`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8";

            if ($db->query($table_sql) === false) {
                $this->raiseError("Failed to create 'jobs' table");
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
                $this->raiseError("Failed to create 'meta' table");
                return false;
            }

            if (!$db->setDatabaseSchemaVersion()) {
                $this->raiseError("Failed to set schema verison!");
                return false;
            }
        }
        if (!$db->getDatabaseSchemaVersion()) {
            if (!$db->setDatabaseSchemaVersion()) {
                $this->raiseError("DatabaseController:setDatabaseSchemaVersion() returned false!");
                return false;
            }
        }

        return true;
    }

    protected function upgradeDatabaseSchema()
    {
        global $db;

        if (!$software_version = $db->getSoftwareSchemaVersion()) {
            $this->raiseError(get_class($db) .'::getSoftwareSchemaVersion() returned false!');
            return false;
        }

        if (($db_version = $db->getDatabaseSchemaVersion()) === false) {
            $this->raiseError(get_class($db) .'::getDatabaseSchemaVersion() returned false!');
            return false;
        }

        if ($db_version == $software_version) {
            return true;
        }

        for ($i = $db_version+1; $i <= $software_version; $i++) {
            $method_name = "upgradeDatabaseSchemaV{$i}";

            if (!method_exists($this, $method_name)) {
                continue;
            }

            if (!$this->$method_name()) {
                $this->raiseError(__CLASS__ ."::{$method_name} returned false!");
                return false;
            }
        }

        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
