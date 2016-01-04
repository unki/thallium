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

use \PDO;

class DatabaseController extends DefaultController
{
    const SCHEMA_VERSION = 1;
    const FRAMEWORK_SCHEMA_VERSION = 3;

    protected $db;
    protected $db_cfg;
    protected $is_connected = false;

    public function __construct()
    {
        global $config;

        $this->is_connected = false;

        if (!($dbconfig = $config->getDatabaseConfiguration())) {
            $this->raiseError(
                "Database configuration is missing or incomplete"
                ." - please check configuration!",
                true
            );
            return false;
        }

        if (!isset(
            $dbconfig['type'],
            $dbconfig['host'],
            $dbconfig['db_name'],
            $dbconfig['db_user'],
            $dbconfig['db_pass']
        )) {
            $this->raiseErrror(
                "Incomplete database configuration - please check configuration!",
                true
            );
            return false;
        }

        $this->db_cfg = $dbconfig;

        if (!$this->connect()) {
            $this->raiseError(__CLASS__ ."::connect() returned false!");
            return false;
        }

        if (!$this->checkDatabaseSoftwareVersion()) {
            $this->raiseError(__CLASS__ ."::checkDatabaseSoftwareVersion() returned false!");
            return false;
        }

        return true;
    }

    protected function connect()
    {
        $options = array(
                'debug' => 2,
                'portability' => 'DB_PORTABILITY_ALL'
                );

        switch ($this->db_cfg['type']) {
            default:
            case 'mariadb':
            case 'mysql':
                $dsn = "mysql:dbname=". $this->db_cfg['db_name'] .";host=". $this->db_cfg['host'];
                $user = $this->db_cfg['db_user'];
                $pass = $this->db_cfg['db_pass'];
                break;
            case 'sqlite':
                $dsn = "sqlite:".$this->db_cfg['host'];
                $user = null;
                $pass = null;
                break;
        }

        try {
            $this->db = new \PDO($dsn, $user, $pass);
            $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            $this->raiseError("Error - unable to connect to database: ". $e->getMessage(), true);
            return false;
        }

        $this->SetConnectionStatus(true);
        return true;
    }

    protected function setConnectionStatus($status)
    {
        $this->is_connected = $status;
    }

    protected function getConnectionStatus()
    {
        return $this->is_connected;
    }

    public function query($query = "", $mode = \PDO::FETCH_OBJ)
    {
        if (!$this->getConnectionStatus()) {
            $this->connect();
        }

        if ($this->hasTablePrefix()) {
            $this->insertTablePrefix($query);
        }

        /* for manipulating queries use exec instead of query. can save
         * some resource because nothing has to be allocated for results.
         */
        if (preg_match('/^(update|insert|create|replace|truncate|delete)/i', $query)) {
            try {
                $result = $this->db->exec($query);
            } catch (\PDOException $e) {
                $this->raiseError(__METHOD__ .'(), query failed!', false, $e);
            }

            if (!isset($result) || $result === false) {
                return false;
            }
            return $result;
        }

        try {
            $result = $this->db->query($query, $mode);
        } catch (\PDOException $e) {
            $this->raiseError(__METHOD__ .'(), query failed!', false, $e);
        }

        if (!isset($result) || $result === false) {
            return false;
        }

        return $result;
    }

    public function prepare($query = "")
    {
        if (!$this->getConnectionStatus()) {
            $this->raiseError("Can't prepare query - we are not connected!");
        }

        if ($this->hasTablePrefix()) {
            $this->insertTablePrefix($query);
        }

        try {
            $result = $this->db->prepare($query);
        } catch (\PDOException $e) {
            $this->raiseError("Unable to prepare statement: ". $e->getMessage());
            return false;
        }

        return $result;

    } // db_prepare()

    public function execute($sth, $data = array())
    {
        if (!$this->getConnectionStatus()) {
            $this->raiseError("Can't prepare query - we are not connected!");
        }

        if (!is_object($sth)) {
            return false;
        }

        if (get_class($sth) != "PDOStatement") {
            return false;
        }

        /* if empty array is provided, we have to unset the $data.
           otherwise an empty array may clear all previously done
           (bindParam(), bindValue(), ...) bindings.
        */
        if (!isset($data) || empty($data) || !is_array($data)) {
            $data = null;
        }

        try {
            $result = $sth->execute($data);
        } catch (\PDOException $e) {
            $this->raiseError("Unable to execute statement: ". $e->getMessage());
            return false;
        }

        return $result;

    } // execute()

    public function freeStatement($sth)
    {
        if (!is_object($sth)) {
            return false;
        }

        if (get_class($sth) != "PDOStatement") {
            return false;
        }

        try {
            $sth->closeCursor();
        } catch (Exception $e) {
            $sth = null;
            return false;
        }

        return true;

    } // freeStatement()

    public function fetchSingleRow($query = "", $mode = \PDO::FETCH_OBJ)
    {
        if (!$this->getConnectionStatus()) {
            $this->raiseError("Can't fetch row - we are not connected!");
        }

        if (empty($query)) {
            return false;
        }

        if (($result = $this->query($query, $mode)) === false) {
            return false;
        }

        if ($result->rowCount() == 0) {
            return false;
        }

        try {
            $row = $result->fetch($mode);
        } catch (\PDOException $e) {
            $this->raiseError("Unable to query database: ". $e->getMessage());
            return false;
        }

        return $row;

    } // fetchSingleRow()

    public function hasTablePrefix()
    {
        if (isset($this->db_cfg['table_prefix']) &&
            !empty($this->db_cfg['table_prefix']) &&
            is_string($this->db_cfg['table_prefix'])
        ) {
            return true;
        }

        return false;
    }

    public function getTablePrefix()
    {
        if (!isset($this->db_cfg) || empty($this->db_cfg)) {
            return false;
        }

        if (!isset($this->db_cfg['table_prefix']) || empty($this->db_cfg['table_prefix'])) {
            return false;
        }

        return $this->db_cfg['table_prefix'];
    }

    public function insertTablePrefix(&$query)
    {
        $query = str_replace("TABLEPREFIX", $this->getTablePrefix(), $query);
    }

    public function getid()
    {
        if (!$this->getConnectionStatus()) {
            $this->raiseError("Can't fetch row - we are not connected!");
            return false;
        }

        try {
            $lastid = $this->db->lastInsertId();
        } catch (\PDOException $e) {
            $this->raiseError("unable to detect last inserted row ID!");
            return false;
        }

        /* Get the last primary key ID from execute query */
        return $lastid;

    }

    public function checkTableExists($table_name)
    {
        if (!$this->getConnectionStatus()) {
            $this->raiseError("Can't check table - we are not connected!");
            return false;
        }

        if (($tables = $this->getDatabaseTables()) === false) {
            $this->raiseError(__CLASS__ .'::getDatabaseTables() returned false!');
            return false;
        }

        if ($this->hasTablePrefix()) {
            $table_name = str_replace("TABLEPREFIX", $this->getTablePrefix(), $table_name);
        }

        if (!in_array($table_name, $tables)) {
            return false;
        }

        return true;
    }

    public function getDatabaseTables()
    {
        $tables = array();

        if (!($result = $this->query("SHOW TABLES"))) {
            $this->raiseError(__METHOD__ .'(), SHOW TABLES query failed!');
            return false;
        }

        if (!$result) {
            return $tables;
        }

        $tables_in = "Tables_in_{$this->db_cfg['db_name']}";

        while ($row = $result->fetch()) {
            array_push($tables, $row->$tables_in);
        }

        return $tables;
    }

    public function getApplicationDatabaseSchemaVersion()
    {
        if (!$this->getConnectionStatus()) {
            $this->raiseError("Can't check table - we are not connected!");
            return false;
        }

        if (!$this->checkTableExists("TABLEPREFIXmeta")) {
            return false;
        }

        $result = $this->fetchSingleRow(
            "SELECT
                meta_value
            FROM
                TABLEPREFIXmeta
            WHERE
                meta_key LIKE 'schema_version'"
        );

        if (isset($result->meta_value) && is_numeric($result->meta_value)) {
            return $result->meta_value;
        } elseif (isset($result->meta_value) && !is_numeric($result->meta_value)) {
            return false;
        }

        // in doubt we claim it's version 0
        return 0;
    }

    public function getFrameworkDatabaseSchemaVersion()
    {
        if (!$this->getConnectionStatus()) {
            $this->raiseError("Can't check table - we are not connected!");
            return false;
        }

        if (!$this->checkTableExists("TABLEPREFIXmeta")) {
            return false;
        }

        $result = $this->fetchSingleRow(
            "SELECT
                meta_value
            FROM
                TABLEPREFIXmeta
            WHERE
                meta_key LIKE 'framework_schema_version'"
        );

        if (isset($result->meta_value) && is_numeric($result->meta_value)) {
            return $result->meta_value;
        } elseif (isset($result->meta_value) && !is_numeric($result->meta_value)) {
            return false;
        }

        // in doubt we claim it's version 0
        return 0;
    }

    public function setDatabaseSchemaVersion($version = null, $mode = 'application')
    {
        if (!$this->checkTableExists("TABLEPREFIXmeta")) {
            $this->raiseError("Can not set schema version when 'meta' table does not exist!");
            return false;
        }

        if ($mode == 'application') {
            $key = 'schema_version';
        } elseif ($mode == 'framework') {
            $key = 'framework_schema_version';
        } else {
            $this->raiseError(__METHOD__ .'(), unsupported $mode parameter!');
            return false;
        }

        if (!isset($version) || empty($version)) {
            if ($mode == 'application') {
                $version = $this->getApplicationSoftwareSchemaVersion();
            } elseif ($mode == 'framework') {
                $version = $this->getFrameworkSoftwareSchemaVersion();
            }
        }

        $result = $this->query(
            "REPLACE INTO TABLEPREFIXmeta (
                meta_key,
                meta_value
            ) VALUES (
                '{$key}',
                '{$version}'
            )"
        );

        if (!$result) {
            $this->raiseError(__METHOD__ ."(), unable to set {$key} in meta table!");
            return false;
        }

        return true;
    }

    public function getApplicationSoftwareSchemaVersion()
    {
        return static::SCHEMA_VERSION;
    }

    public function getFrameworkSoftwareSchemaVersion()
    {
        return self::FRAMEWORK_SCHEMA_VERSION;
    }

    public function truncateDatabaseTables()
    {
        if (($tables = $this->getDatabaseTables()) === false) {
            $this->raiseError(__CLASS__ .'::getDatabaseTables() returned false!');
            return false;
        }

        foreach ($tables as $table) {
            if (($this->query("TRUNCATE TABLE ${table}")) === false) {
                $this->raiseError(__METHOD__ ."(), failed to truncate '{$table}' table!");
                return false;
            }
        }

        return true;
    }

    public function checkDatabaseSoftwareVersion()
    {
        if (!$version = $this->db->getAttribute(\PDO::ATTR_SERVER_VERSION)) {
            $this->raiseError("Failed to detect database software version!");
            return false;
        }

        if (!isset($version) || empty($version)) {
            $this->raiseError("Unable to fetch version information from database!");
            return false;
        }

        // extract the pure version without extra build specifics
        if (($version = preg_replace("/^(\d+)\.(\d+)\.(\d+).*$/", '${1}.${2}.${3}', $version)) === false) {
            $this->raiseError("Failed to parse version string (${version})!");
            return false;
        }

        if (strtolower($this->db_cfg['type']) == "mysql" && version_compare($version, "5.6.4", "<")) {
            $this->raiseError("MySQL server version 5.6.4 or later is required (found {$version})!");
            return false;
        }

        return true;
    }

    public function quote($text)
    {
        if (!method_exists($this->db, 'quote')) {
            $this->raiseError(__METHOD__ .'(), PDO driver does not provide quote method!');
            return false;
        }

        if (!is_string($text)) {
            $this->raiseError(__METHOD__ .'(), \$text is not a string!');
            return false;
        }

        if (($quoted = $this->db->quote($text)) === false) {
            $this->raiseError(__METHOD__ .'(), PDO driver does not support quote!');
            return false;
        }

        if (!empty($text) && empty($quoted)) {
            $this->raiseError(__METHOD__ .'(), something must have gone wrong!');
            return false;
        }

        return $text;
    }

    public function checkColumnExists($table_name, $column)
    {
        if (!$this->getConnectionStatus()) {
            $this->raiseError(__METHOD__ .'(), needs to be connected to database!');
            return false;
        }

        if (!isset($table_name) || empty($table_name) ||
            !isset($column) || empty($column)
        ) {
            $this->raiseError(__METHOD__ .'(), incomplete parameters!');
            return false;
        }

        if (!($result = $this->query("DESC ". $table_name, \PDO::FETCH_NUM))) {
            $this->raiseError(__METHOD__ .'(), failed to fetch table structure!');
            return false;
        }

        while ($row = $result->fetch()) {
            if (in_array($column, $row)) {
                return true;
            }
        }

        return false;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
