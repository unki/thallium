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
    private $schema_version_before;

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

    private function createDatabaseTables()
    {
        global $db;

        if (!$db->checkTableExists("TABLEPREFIXarchive")) {
            $table_sql = "CREATE TABLE `TABLEPREFIXarchive` (
                `document_idx` int(11) NOT NULL AUTO_INCREMENT,
                `document_guid` varchar(255) DEFAULT NULL,
                `document_title` varchar(255) DEFAULT NULL,
                `document_description` TEXT DEFAULT NULL,
                `document_file_name` varchar(255) DEFAULT NULL,
                `document_file_hash` varchar(255) DEFAULT NULL,
                `document_file_size` int(11) DEFAULT NULL,
                `document_signing_icon_position` int(11) DEFAULT NULL,
                `document_time` timestamp(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
                `document_version` varchar(255) DEFAULT NULL,
                `document_derivation` int(11) DEFAULT NULL,
                `document_derivation_guid` varchar(255) DEFAULT NULL,
                `document_signed_copy` varchar(1) DEFAULT NULL
                PRIMARY KEY (`document_idx`)
                    )
                    ENGINE=InnoDB DEFAULT CHARSET=utf8;";

            if ($db->query($table_sql) === false) {
                $this->raiseError("Failed to create 'archive' table");
                return false;
            }
        }

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

        if (!$db->checkTableExists("TABLEPREFIXqueue")) {
            $table_sql = "CREATE TABLE `TABLEPREFIXqueue` (
                `queue_idx` int(11) NOT NULL AUTO_INCREMENT,
                `queue_guid` varchar(255) DEFAULT NULL,
                `queue_file_name` varchar(255) DEFAULT NULL,
                `queue_file_hash` varchar(255) DEFAULT NULL,
                `queue_file_size` int(11) DEFAULT NULL,
                `queue_signing_icon_position` int(11) DEFAULT NULL,
                `queue_state` varchar(255) DEFAULT NULL,
                `queue_time` timestamp(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
                PRIMARY KEY (`queue_idx`)
                    )
                    ENGINE=InnoDB DEFAULT CHARSET=utf8;";

            if ($db->query($table_sql) === false) {
                $this->raiseError("Failed to create 'queue' table");
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

        if (!$db->checkTableExists("TABLEPREFIXkeywords")) {
            $table_sql = "CREATE TABLE `TABLEPREFIXkeywords` (
                `keyword_idx` int(11) NOT NULL auto_increment,
                `keyword_name` varchar(255) default NULL,
                `keyword_guid` varchar(255) default NULL,
                PRIMARY KEY  (`keyword_idx`)
                )
                ENGINE=MyISAM DEFAULT CHARSET=utf8;";

            if ($db->query($table_sql) === false) {
                $this->raiseError("Failed to create 'keywords' table");
                return false;
            }
        }

        if (!$db->checkTableExists("TABLEPREFIXassign_keywords_to_document")) {
            $table_sql = "CREATE TABLE `TABLEPREFIXassign_keywords_to_document` (
                `akd_idx` int(11) NOT NULL auto_increment,
                `akd_guid` varchar(255) default NULL,
                `akd_archive_idx` int(11) NOT NULL,
                `akd_keyword_idx` int(11) NOT NULL,
                PRIMARY KEY  (`akd_idx`),
                UNIQUE KEY `document_keywords` (`akd_archive_idx`,`akd_keyword_idx`),
                KEY `documents` (`akd_archive_idx`)
                )
                ENGINE=MyISAM DEFAULT CHARSET=utf8;";

            if ($db->query($table_sql) === false) {
                $this->raiseError("Failed to create 'assign_keywords_to_document' table");
                return false;
            }
        }

        if (!$db->checkTableExists("TABLEPREFIXdocument_indices")) {
            $table_sql = "CREATE TABLE `TABLEPREFIXdocument_indices` (
                `di_idx` int(11) NOT NULL auto_increment,
                `di_guid` varchar(255) default NULL,
                `di_document_idx` int(11) NOT NULL,
                `di_document_guid` varchar(255) default NULL,
                `di_text` TEXT default NULL,
                PRIMARY KEY  (`di_idx`),
                KEY `document_indices` (`di_document_idx`,`di_document_guid`),
                FULLTEXT KEY `text` (`di_text`)
                )
                ENGINE=MyISAM DEFAULT CHARSET=utf8;";

            if ($db->query($table_sql) === false) {
                $this->raiseError("Failed to create 'document_indices' table");
                return false;
            }
        }

        if (!$db->checkTableExists("TABLEPREFIXdocument_properties")) {
            $table_sql = "CREATE TABLE `TABLEPREFIXdocument_properties` (
                `dp_idx` int(11) NOT NULL auto_increment,
                `dp_guid` varchar(255) default NULL,
                `dp_document_idx` int(11) NOT NULL,
                `dp_document_guid` varchar(255) default NULL,
                `dp_property` varchar(255) default NULL,
                `dp_value` varchar(255) default NULL,
                PRIMARY KEY  (`dp_idx`),
                KEY `document_properties` (`dp_document_idx`,`dp_document_guid`)
                )
                ENGINE=MyISAM DEFAULT CHARSET=utf8;";

            if ($db->query($table_sql) === false) {
                $this->raiseError("Failed to create 'document_properties' table");
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
                ) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8";

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
                ) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8";

            if ($db->query($table_sql) === false) {
                $this->raiseError("Failed to create 'jobs' table");
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

    private function upgradeDatabaseSchema()
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

        /* final action in this function
        // disabled for now as of 20150923
        if (!$db->setDatabaseSchemaVersion()) {
            $this->raiseError("DatabaseController:setDatabaseSchemaVersion() returned false!");
            return false;
        }*/

        return true;
    }

    private function upgradeDatabaseSchemaV3()
    {
        global $db;

        $result = $db->query(
            "ALTER TABLE
                TABLEPREFIXassign_keywords_to_document
            ADD
                unique index `document_keywords` (akd_archive_idx, akd_keyword_idx),
            ADD index `documents` (akd_archive_idx)"
        );

        if ($result === false) {
            $this->raiseError(__METHOD__ ." failed!");
            return false;
        }

        $db->setDatabaseSchemaVersion(3);
        return true;
    }

    private function upgradeDatabaseSchemaV4()
    {
        global $db;

        if ($db->checkColumnExists(
            'TABLEPREFIXarchive',
            'document_signed_copy'
        )) {
            $db->setDatabaseSchemaVersion(4);
            return true;
        }

        $result = $db->query(
            "ALTER TABLE
                TABLEPREFIXarchive
            ADD
                `document_signed_copy` varchar(1) DEFAULT NULL
            AFTER
                document_derivation_guid"
        );

        if ($result === false) {
            $this->raiseError(__METHOD__ ." failed!");
            return false;
        }

        $db->setDatabaseSchemaVersion(4);
        return true;
    }

    private function upgradeDatabaseSchemaV5()
    {
        global $db;

        if ($db->checkColumnExists(
            'TABLEPREFIXarchive',
            'document_description'
        )) {
            $db->setDatabaseSchemaVersion(5);
            return true;
        }

        $result = $db->query(
            "ALTER TABLE
                TABLEPREFIXarchive
            ADD
                `document_description` TEXT DEFAULT NULL
            AFTER
                document_derivation_guid"
        );

        if ($result === false) {
            $this->raiseError(__METHOD__ ." failed!");
            return false;
        }

        $db->setDatabaseSchemaVersion(5);
        return true;
    }

    private function upgradeDatabaseSchemaV6()
    {
        global $db;


        if ($db->checkColumnExists(
            'TABLEPREFIXarchive',
            'document_title'
        )) {
            $db->setDatabaseSchemaVersion(6);
            return true;
        }

        $result = $db->query(
            "ALTER TABLE
                TABLEPREFIXarchive
            ADD
                `document_title` varchar(255) DEFAULT NULL
            AFTER
                document_guid"
        );

        if ($result === false) {
            $this->raiseError(__METHOD__ ." failed!");
            return false;
        }

        $db->setDatabaseSchemaVersion(6);
        return true;
    }

    private function upgradeDatabaseSchemaV7()
    {
        global $db;

        $result = $db->query("
            UPDATE
                TABLEPREFIXarchive
            SET
                document_title=document_file_name
            WHERE
                document_title=''
            OR
                document_title IS NULL
        ");

        if ($result === false) {
            $this->raiseError(__METHOD__ ." failed!");
            return false;
        }

        $db->setDatabaseSchemaVersion(7);
        return true;
    }

    private function upgradeDatabaseSchemaV8()
    {
        global $thallium, $db;

        if ($db->checkColumnExists(
            'TABLEPREFIXassign_keywords_to_document',
            'akd_guid'
        )) {
            $db->setDatabaseSchemaVersion(8);
            return true;
        }

        $result = $db->query(
            "ALTER TABLE
                TABLEPREFIXassign_keywords_to_document
            ADD
                `akd_guid` varchar(255) default NULL
            AFTER
                akd_idx"
        );

        if ($result === false) {
            $this->raiseError(__METHOD__ ." failed!");
            return false;
        }

        $result = $db->query(
            "SELECT
                akd_idx
            FROM
                TABLEPREFIXassign_keywords_to_document"
        );

        if ($result === false) {
            $this->raiseError(__METHOD__ .' failed!');
            return false;
        }

        while ($row = $result->fetch()) {
            if (!$guid = $thallium->createGuid()) {
                $this->raiseError(get_class($thallium) .'::createGuid() returned no valid GUID!');
                return false;
            }

            $res = $db->query(
                "UPDATE
                    TABLEPREFIXassign_keywords_to_document
                SET
                    akd_guid='{$guid}'
                WHERE
                    akd_idx LIKE '{$row->akd_idx}'"
            );
            if ($res === false) {
                $this->raiseError(__METHOD__ .', update failed!');
                return false;
            }
        }

        $db->setDatabaseSchemaVersion(8);
        return true;
    }

    private function upgradeDatabaseSchemaV10()
    {
        global $db;

        if ($db->checkColumnExists(
            'TABLEPREFIXmessage_bus',
            'msg_session_id'
        )) {
            $db->setDatabaseSchemaVersion(10);
            return true;
        }

        $result = $db->query(
            "ALTER TABLE
                TABLEPREFIXmessage_bus
            CHANGE COLUMN
                msg_session msg_session_id varchar(255) NOT NULL"
        );

        if ($result === false) {
            $this->raiseError(__METHOD__ ." failed!");
            return false;
        }

        $db->setDatabaseSchemaVersion(10);
        return true;
    }

    private function upgradeDatabaseSchemaV11()
    {
        global $db;

        if ($db->checkColumnExists('TABLEPREFIXmessage_bus', 'msg_guid') &&
            $db->checkColumnExists('TABLEPREFIXmessage_bus', 'msg_body')
        ) {
            $db->setDatabaseSchemaVersion(11);
            return true;
        }

        $result = $db->query(
            "ALTER TABLE
                TABLEPREFIXmessage_bus
            ADD COLUMN
                `msg_guid` varchar(255) default NULL
            AFTER
                msg_idx,
            ADD COLUMN
                `msg_body` varchar(255) NOT NULL
            AFTER
                msg_command"
        );

        if ($result === false) {
            $this->raiseError(__METHOD__ ." failed!");
            return false;
        }

        $db->setDatabaseSchemaVersion(11);
        return true;
    }

    private function upgradeDatabaseSchemaV12()
    {
        global $db;

        if ($db->checkColumnExists(
            'TABLEPREFIXmessage_bus',
            'msg_scope'
        )) {
            $db->setDatabaseSchemaVersion(12);
            return true;
        }

        $result = $db->query(
            "ALTER TABLE
                TABLEPREFIXmessage_bus
            ADD COLUMN
                `msg_scope` varchar(255) default NULL
            AFTER
                msg_session_id"
        );

        if ($result === false) {
            $this->raiseError(__METHOD__ ." failed!");
            return false;
        }

        $db->setDatabaseSchemaVersion(12);
        return true;
    }

    private function upgradeDatabaseSchemaV13()
    {
        global $db;

        if ($db->checkColumnExists('TABLEPREFIXmessage_bus', 'msg_submit_time') &&
            $db->checkColumnExists('TABLEPREFIXmessage_bus', 'msg_in_processing')
        ) {
            $db->setDatabaseSchemaVersion(13);
            return true;
        }

        $result = $db->query(
            "ALTER TABLE
                TABLEPREFIXmessage_bus
            ADD COLUMN
                `msg_submit_time` timestamp(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)
            AFTER
                msg_session_id,
            ADD COLUMN
                `msg_in_processing` varchar(1) DEFAULT NULL
            AFTER
                msg_body"
        );

        if ($result === false) {
            $this->raiseError(__METHOD__ ." failed!");
            return false;
        }

        $db->setDatabaseSchemaVersion(13);
        return true;
    }

    private function upgradeDatabaseSchemaV14()
    {
        global $db;

        if ($db->checkColumnExists('TABLEPREFIXmessage_bus', 'msg_value')) {
            $db->setDatabaseSchemaVersion(14);
            return true;
        }

        $result = $db->query(
            "ALTER TABLE
                TABLEPREFIXmessage_bus
            ADD COLUMN
                `msg_value` varchar(255) default NULL
            AFTER
                msg_body"
        );

        if ($result === false) {
            $this->raiseError(__METHOD__ ." failed!");
            return false;
        }

        $db->setDatabaseSchemaVersion(14);
        return true;
    }

    private function upgradeDatabaseSchemaV15()
    {
        global $db;

        $db->setDatabaseSchemaVersion(15);
        return true;
    }

    private function upgradeDatabaseSchemaV16()
    {
        global $db;

        if ($db->checkColumnExists('TABLEPREFIXjobs', 'job_request_guid')) {
            $db->setDatabaseSchemaVersion(16);
            return true;
        }

        $result = $db->query(
            "ALTER TABLE
                TABLEPREFIXjobs
            ADD COLUMN
                `job_request_guid` varchar(255) DEFAULT NULL
            AFTER
                msg_body"
        );

        if ($result === false) {
            $this->raiseError(__METHOD__ ." failed!");
            return false;
        }

        $db->setDatabaseSchemaVersion(16);
        return true;
    }

    private function upgradeDatabaseSchemaV17()
    {
        global $db;

        $db->setDatabaseSchemaVersion(17);
        return true;
    }

    private function upgradeDatabaseSchemaV18()
    {
        global $db;

        if ($db->checkColumnExists('TABLEPREFIXdocument_indices', 'di_guid')) {
            $db->setDatabaseSchemaVersion(18);
            return true;
        }

        $result = $db->query(
            "ALTER TABLE
                TABLEPREFIXdocument_indices
            ADD COLUMN
                `di_guid` varchar(255) default NULL
            AFTER
                di_idx"
        );

        if ($result === false) {
            $this->raiseError(__METHOD__ ." failed!");
            return false;
        }

        $db->setDatabaseSchemaVersion(18);
        return true;
    }

    private function upgradeDatabaseSchemaV19()
    {
        global $db;

        $db->setDatabaseSchemaVersion(19);
        return true;
    }

    private function upgradeDatabaseSchemaV20()
    {
        global $db;

        $result = $db->query(
            "ALTER TABLE
                TABLEPREFIXdocument_indices
            DROP KEY
                document_indices,
            ADD KEY
                `document_indices` (`di_document_idx`,`di_document_guid`)"
        );

        if ($result === false) {
            $this->raiseError(__METHOD__ ." failed!");
            return false;
        }

        $db->setDatabaseSchemaVersion(20);
        return true;
    }

    private function upgradeDatabaseSchemaV21()
    {
        global $db;

        $result = $db->query(
            "ALTER TABLE
                TABLEPREFIXdocument_properties
            DROP KEY
                document_properties,
            ADD KEY
                `document_properties` (`dp_document_idx`,`dp_document_guid`)"
        );

        if ($result === false) {
            $this->raiseError(__METHOD__ ." failed!");
            return false;
        }

        $db->setDatabaseSchemaVersion(21);
        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
