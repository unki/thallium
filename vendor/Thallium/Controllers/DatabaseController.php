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
 * DatabaseController handles all the database specific tasks like
 * storing and retriving something from the database.
 *
 * @package Thallium\Controllers\DatabaseController
 * @subpackage Controllers
 * @license AGPL3
 * @copyright 2015-2016 Andreas Unterkircher <unki@netshadow.net>
 * @author Andreas Unterkircher <unki@netshadow.net>
 */
class DatabaseController extends DefaultController
{
    /** @var int SCHEMA_VERSION */
    const SCHEMA_VERSION = 1;

    /** @var int FRAMEWORK_SCHEMA_VERSION */
    const FRAMEWORK_SCHEMA_VERSION = 4;

    /** @var \PDO $db */
    protected $db;

    /** @var array $db_cfg */
    protected $db_cfg;

    /** @var bool $is_connected */
    protected $is_connected = false;

    /** @var bool $is_open_transaction */
    protected $is_open_transaction = false;

    /** @var bool $supported_fetch_methods */
    protected static $supported_fetch_methods = array(
        \PDO::FETCH_LAZY,
        \PDO::FETCH_ASSOC,
        \PDO::FETCH_NAMED,
        \PDO::FETCH_NUM,
        \PDO::FETCH_BOTH,
        \PDO::FETCH_OBJ,
        \PDO::FETCH_COLUMN,
        \PDO::FETCH_CLASS,
        \PDO::FETCH_INTO,
    );

    /**
     * class constructor
     *
     * @param none
     * @return void
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function __construct()
    {
        global $config;

        if (!isset($config) ||
            empty($config) ||
            !is_object($config) ||
            !is_a($config, 'Thallium\Controllers\ConfigController')
        ) {
            static::raiseError(__METHOD__ .'(), looks like ConfigController has not be loaded!', true);
            return;
        }

        $this->is_connected = false;

        if (($dbconfig = $config->getDatabaseConfiguration()) === false) {
            static::raiseError(
                "Database configuration is missing or incomplete"
                ." - please check configuration!",
                true
            );
            return;
        }

        $this->db_cfg = $dbconfig;

        if (!$this->verifyDatabaseConfiguration()) {
            static::raiseError(__CLASS__ .'::verifyDatabaseConfiguration() returned false!', true);
            return;
        }

        if (!$this->connect()) {
            static::raiseError(__CLASS__ .'::connect() returned false!', true);
            return;
        }

        if (!$this->checkDatabaseSoftwareVersion()) {
            static::raiseError(__CLASS__ .'::checkDatabaseSoftwareVersion() returned false!', true);
            return;
        }

        return;
    }

    /**
     * verify the loaded database configuration settings.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    protected function verifyDatabaseConfiguration()
    {
        if (!isset($this->db_cfg) || empty($this->db_cfg) || !is_array($this->db_cfg)) {
            static::raiseError(__METHOD__ .'(), database configuration has not been loaded!');
            return false;
        }

        if (!isset($this->db_cfg['type']) ||
            empty($this->db_cfg['type']) ||
            !is_string($this->db_cfg['type'])
        ) {
            static::raiseError(__METHOD__ .'(), "type" parameter in [database] section is invalid!');
            return false;
        }

        if (!isset($this->db_cfg['host']) ||
            empty($this->db_cfg['host']) ||
            !is_string($this->db_cfg['host'])
        ) {
            static::raiseError(__METHOD__ .'(), "host" parameter in [database] section is invalid!');
            return false;
        }

        if (!isset($this->db_cfg['port']) ||
            empty($this->db_cfg['port']) ||
            !is_numeric($this->db_cfg['port'])
        ) {
            static::raiseError(__METHOD__ .'(), "port" parameter in [database] section is invalid!');
            return false;
        }

        if (isset($this->db_cfg['socket']) &&
            !empty($this->db_cfg['socket']) && (
            !is_string($this->db_cfg['socket']) ||
            !file_exists($this->db_cfg['socket']) ||
            !is_readable($this->db_cfg['socket']) ||
            !is_writeable($this->db_cfg['socket']))
        ) {
            static::raiseError(__METHOD__ .'(), "socket" parameter in [database] section is invalid!');
            return false;
        }

        if (!isset($this->db_cfg['db_name']) ||
            empty($this->db_cfg['db_name']) ||
            !is_string($this->db_cfg['db_name'])
        ) {
            static::raiseError(__METHOD__ .'(), "db_name" parameter in [database] section is invalid!');
            return false;
        }

        if (!isset($this->db_cfg['db_user']) ||
            empty($this->db_cfg['db_user']) ||
            !is_string($this->db_cfg['db_user'])
        ) {
            static::raiseError(__METHOD__ .'(), "db_user" parameter in [database] section is invalid!');
            return false;
        }

        if (!isset($this->db_cfg['db_pass']) ||
            empty($this->db_cfg['db_pass']) ||
            !is_string($this->db_cfg['db_pass'])
        ) {
            static::raiseError(__METHOD__ .'(), "db_pass" parameter in [database] section is invalid!');
            return false;
        }

        return true;
    }

    /**
     * opens a connection to the database.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final protected function connect()
    {
        switch ($this->db_cfg['type']) {
            default:
            case 'mariadb':
            case 'mysql':
                if (isset($this->db_cfg['socket'])) {
                    $dsn_str = "mysql:unix_socket=%s;dbname=%s";
                    $dsn_data = array(
                        $this->db_cfg['socket'],
                        $this->db_cfg['db_name'],
                    );
                } else {
                    $dsn_str = "mysql:dbname=%s;host=%s;port=%s";
                    $dsn_data = array(
                        $this->db_cfg['db_name'],
                        $this->db_cfg['host'],
                        $this->db_cfg['port']
                    );
                }
                $dsn = vsprintf($dsn_str, $dsn_data);
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
        } catch (\PDOException $e) {
            static::raiseError(__METHOD__ .'(), unable to connect to database!', true, $e);
            return false;
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), an unspecific error occurred!', false, $e);
            return false;
        }

        if (!$this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION)) {
            static::raiseError(get_class($this->db) .'::setAttribute() returned false!');
            return false;
        }

        if (!$this->db->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_OBJ)) {
            static::raiseError(get_class($this->db) .'::setAttribute() returned false!');
            return false;
        }

        if (!$this->setConnectionStatus(true)) {
            static::raiseError(__CLASS__ .'::setConnectionStatus() returned false!');
            return false;
        }

        return true;
    }

    /**
     * sets or clears the internal connected-flag.
     *
     * @param bool $status
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final protected function setConnectionStatus($status)
    {
        if (!isset($status) || !is_bool($status)) {
            static::raiseError(__METHOD__ .'(), $status parameter is invalid!');
            return false;
        }

        $this->is_connected = $status;
        return true;
    }

    /**
     * returns the state of the internal connected-flag.
     *
     * @param bool $status
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final protected function isConnected()
    {
        if (!isset($this->is_connected) || !is_bool($this->is_connected)) {
            return false;
        }

        return $this->is_connected;
    }

    /**
     * executes a SQL query.
     *
     * @param string $query
     * @param int $mode
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function query($query, $mode = \PDO::FETCH_OBJ)
    {
        if (!isset($query) || empty($query) || !is_string($query)) {
            static::raiseError(__METHOD__ .'(), $query parameter is invalid!');
            return false;
        }

        if (!isset($mode) || empty($mode) || !is_int($mode)) {
            static::raiseError(__METHOD__ .'(), $mode parameter is invalid!');
            return false;
        }

        if (!in_array($mode, static::$supported_fetch_methods)) {
            static::raiseError(__METHOD__ .'(), $mode contains an unsupported fetch method!');
            return false;
        }

        if (!$this->isConnected()) {
            if (!$this->connect()) {
                static::raiseError(__CLASS__ .'::connect() returned false!');
                return false;
            }
        }

        if ($this->hasTablePrefix() && !$this->insertTablePrefix($query)) {
            static::raiseError(__CLASS__ .'::insertTablePrefix() returned false!');
            return false;
        }

        /* for manipulating queries use exec instead of query. this can save some resource
         * because nothing has to be allocated for results.
         */
        if (preg_match('/^[[:blank:]]*(update|insert|create|replace|truncate|delete|alter)[[:blank:]]/i', $query)) {
            try {
                $result = $this->db->exec($query);
            } catch (\PDOException $e) {
                static::raiseError(__METHOD__ .'(), query failed!', false, $e);
                return false;
            } catch (\Exception $e) {
                static::raiseError(__METHOD__ .'(), an unspecific error occurred!', false, $e);
                return false;
            }

            /* PDO::exec() sometimes returns false even if operation was successful.
             * http://php.net/manual/de/pdo.exec.php#118156
             * so overrule fow now.
             */
            return true;

            if (!isset($result) || $result === false) {
                return false;
            }

            return $result;
        }

        try {
            $result = $this->db->query($query, $mode);
        } catch (\PDOException $e) {
            static::raiseError(__METHOD__ .'(), query failed!', false, $e);
            return false;
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), an unspecific error occurred!', false, $e);
            return false;
        }

        if (!isset($result) || $result === false) {
            return false;
        }

        return $result;
    }

    /**
     * prepares an SQL statement.
     *
     * @param string $query
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function prepare($query)
    {
        if (!isset($query) || empty($query) || !is_string($query)) {
            static::raiseError(__METHOD__ .'(), $query parameter is invalid!');
            return false;
        }

        if (!$this->isConnected()) {
            static::raiseError(__CLASS__ .'::isConnected() returned false!');
            return false;
        }

        if ($this->hasTablePrefix() && !$this->insertTablePrefix($query)) {
            static::raiseError(__CLASS__ .'::insertTablePrefix() returned false!');
            return false;
        }

        try {
            $result = $this->db->prepare($query);
        } catch (\PDOException $e) {
            static::raiseError(__METHOD__ .'(), unable to prepare statement!', false, $e);
            return false;
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), an unspecific error occurred!', false, $e);
            return false;
        }

        return $result;

    }

    /**
     * executes an previously prepared SQL statement.
     *
     * @param \PDOStatement $sth
     * @param array $data
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function execute($sth, $data = array())
    {
        if (!$this->isConnected()) {
            static::raiseError(__CLASS__ .'::isConnected() returned false!');
            return false;
        }

        if (!isset($sth) || empty($sth) || !is_object($sth)) {
            static::raiseError(__METHOD__ .'(), $sth parameter is invalid!');
            return false;
        }

        if (!is_a($sth, 'PDOStatement')) {
            static::raiseError(__METHOD__ .'(), $sth parameter does not contain a PDOStatement!');
            return false;
        }

        if (isset($data) && !empty($data) && is_array($data)) {
            foreach ($data as $key => $value) {
                $sth->bindParam(":{$key}", $value);
            }
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
            static::raiseError(__METHOD__ .'(), unable to execute statement!', false, $e);
            return false;
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), an unspecific error occurred!', false, $e);
            return false;
        }

        return $result;
    }

    /**
     * frees the resources of a SQL result.
     *
     * @param \PDOStatement $sth
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function freeStatement($sth)
    {
        if (!isset($sth) || empty($sth) || !is_object($sth)) {
            static::raiseError(__METHOD__ .'(), $sth parameter is invalid!');
            return false;
        }

        if (!is_a($sth, 'PDOStatement')) {
            static::raiseError(__METHOD__ .'(), $sth parameter does not contain a PDOStatement!');
            return false;
        }

        try {
            $sth->closeCursor();
        } catch (\Exception $e) {
            unset($sth);
            return false;
        }

        return true;

    }

    /**
     * frees the resources of a SQL result.
     *
     * @param string $query
     * @param int $mode
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function fetchSingleRow($query = "", $mode = \PDO::FETCH_OBJ)
    {
        if (!isset($query) || empty($query) || !is_string($query)) {
            static::raiseError(__METHOD__ .'(), $query parameter is invalid!');
            return false;
        }

        if (!isset($mode) || empty($mode) || !is_int($mode)) {
            static::raiseError(__METHOD__ .'(), $mode parameter is invalid!');
            return false;
        }

        if (!in_array($mode, static::$supported_fetch_methods)) {
            static::raiseError(__METHOD__ .'(), $mode contains an unsupported fetch method!');
            return false;
        }

        if (!$this->isConnected()) {
            static::raiseError(__CLASS__ .'::isConnected() returned false!');
            return false;
        }

        if (($result = $this->query($query, $mode)) === false) {
            static::raiseError(__CLASS__ .'::query() returned false!');
            return false;
        }

        if ($result->rowCount() == 0) {
            return false;
        }

        try {
            $row = $result->fetch($mode);
        } catch (\PDOException $e) {
            static::raiseError(__METHOD__ .'(), unable to fetch from database!', false, $e);
            return false;
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), an unspecific error occurred!', false, $e);
            return false;
        }

        return $row;

    }

    /**
     * returns true if an table-prefix has been specified in the configuration.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function hasTablePrefix()
    {
        if (isset($this->db_cfg['table_prefix']) &&
            !empty($this->db_cfg['table_prefix']) &&
            is_string($this->db_cfg['table_prefix'])
        ) {
            return true;
        }

        return false;
    }

    /**
     * returns the table-prefix that has been specified in the configuration.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function getTablePrefix()
    {
        if (!$this->hasTablePrefix()) {
            static::raiseError(__CLASS__ .'::hasTablePrefix() returned false!');
            return false;
        }

        return $this->db_cfg['table_prefix'];
    }

    /**
     * changes the provided query string and replaces the occurances of the
     * keyword TABLEPREFIX by the actual table-prefix.
     *
     * @param string $query
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function insertTablePrefix(&$query)
    {
        if (!isset($query) || empty($query) || !is_string($query)) {
            static::raiseError(__METHOD__ .'(), $query parameter is invalid!');
            return false;
        }

        if (!$this->hasTablePrefix()) {
            return true;
        }

        if (($prefix = $this->getTablePrefix()) === false) {
            static::raiseError(__CLASS__ .'::getTablePrefix() returend false!');
            return false;
        }

        $query = str_replace("TABLEPREFIX", $prefix, $query);
        return true;
    }

    /**
     * returns the primary key of the latest performed SQL query.
     *
     * @param none
     * @return int|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function getId()
    {
        if (!$this->isConnected()) {
            static::raiseError(__CLASS__ .'::isConnected() returned false!');
            return false;
        }

        try {
            $lastid = $this->db->lastInsertId();
        } catch (\PDOException $e) {
            static::raiseError(__METHOD__ .'(), unable to detect last inserted row ID!', false, $e);
            return false;
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), an unspecific error occurred!', false, $e);
            return false;
        }

        /* Get the last primary key ID from execute query */
        return $lastid;
    }

    /**
     * returns true if table $table_name exists in database.
     *
     * @param string $table_name
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function checkTableExists($table_name)
    {
        if (!isset($table_name) || empty($table_name) || !is_string($table_name)) {
            static::raiseError(__METHOD__ .'(), $table_name parameter is invalid!');
            return false;
        }

        if (!$this->isConnected()) {
            static::raiseError(__CLASS__ .'::isConnected() returned false!');
            return false;
        }

        if ($this->hasTablePrefix() && !$this->insertTablePrefix($table_name)) {
            static::raiseError(__CLASS__ .'::insertTablePrefix() returned false!');
            return false;
        }

        if (($tables = $this->getDatabaseTables()) === false) {
            static::raiseError(__CLASS__ .'::getDatabaseTables() returned false!');
            return false;
        }

        if (!in_array($table_name, $tables)) {
            return false;
        }

        return true;
    }

    /**
     * retrieves all existing tables from database.
     *
     * @param none
     * @return array|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function getDatabaseTables()
    {
        $tables = array();

        if (($result = $this->query("SHOW TABLES")) == false) {
            static::raiseError(__CLASS__ .'::query() returned false!');
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

    /**
     * retrieves the application schema version from the meta table.
     *
     * @param none
     * @return int|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function getApplicationDatabaseSchemaVersion()
    {
        if (!$this->isConnected()) {
            static::raiseError(__CLASS__ .'::isConnected() returned false!');
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

    /**
     * retrieves the framework schema version from the meta table.
     *
     * @param none
     * @return int|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function getFrameworkDatabaseSchemaVersion()
    {
        if (!$this->isConnected()) {
            static::raiseError(__CLASS__ .'::isConnected() returned false!');
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

    /**
     * sets the schema version for the application or framework schema in the
     * meta table.
     *
     * @param int $version
     * @param string $mode
     * @return int|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function setDatabaseSchemaVersion($version = 0, $mode = 'application')
    {
        $valid_schemas = array(
            'application',
            'framework',
        );

        if (!is_int($version) && !is_numeric($version) && !is_null($version)) {
            static::raiseError(__METHOD__ .'(), $version parameter is invalid!');
            return false;
        }

        if (!isset($mode) || empty($mode) || !is_string($mode)) {
            static::raiseError(__METHOD__ .'(), $mode parameter is invalid!');
            return false;
        }

        if (!in_array($mode, $valid_schemas)) {
            static::raiseError(__METHOD__ .'(), $mode parameter contains an invalid schema!');
            return false;
        }

        if (!$this->checkTableExists("TABLEPREFIXmeta")) {
            static::raiseError(__METHOD__ .'(), can not set schema version as "meta" table does not exist!');
            return false;
        }

        if ($mode === 'application') {
            $key = 'schema_version';
        } elseif ($mode === 'framework') {
            $key = 'framework_schema_version';
        }

        if (!isset($version) || empty($version)) {
            if ($mode == 'application') {
                $get_method = 'getApplicationSoftwareSchemaVersion';
            } elseif ($mode == 'framework') {
                $get_method = 'getFrameworkSoftwareSchemaVersion';
            }

            if (($version = $this->$get_method()) === false) {
                static::raiseError(sprintf(
                    '%s:%s returned false!',
                    __CLASS__,
                    $get_method
                ));
                return false;
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
            static::raiseError(__METHOD__ ."(), unable to set {$key} in meta table!");
            return false;
        }

        return true;
    }

    /**
     * returns the application schema version number
     *
     * @param none
     * @return int
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function getApplicationSoftwareSchemaVersion()
    {
        return static::SCHEMA_VERSION;
    }

    /**
     * returns the framework schema version number
     *
     * @param none
     * @return int
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function getFrameworkSoftwareSchemaVersion()
    {
        return static::FRAMEWORK_SCHEMA_VERSION;
    }

    /**
     * truncates all tables in the database and wipes out any data in them.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function truncateDatabaseTables()
    {
        if (($tables = $this->getDatabaseTables()) === false) {
            static::raiseError(__CLASS__ .'::getDatabaseTables() returned false!');
            return false;
        }

        foreach ($tables as $table) {
            if (($this->query("TRUNCATE TABLE ${table}")) === false) {
                static::raiseError(__METHOD__ ."(), failed to truncate '{$table}' table!");
                return false;
            }
        }

        return true;
    }

    /**
     * checks what kind of database software is in use and if it is supported.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function checkDatabaseSoftwareVersion()
    {
        if (!$version = $this->db->getAttribute(\PDO::ATTR_SERVER_VERSION)) {
            static::raiseError(__METHOD__ .'(), failed to detect database software version!');
            return false;
        }

        if (!isset($version) || empty($version)) {
            static::raiseError(__METHOD__ .'(), unable to fetch version information from database!');
            return false;
        }

        // extract the pure version without extra build specifics
        if (($version = preg_replace("/^(\d+)\.(\d+)\.(\d+).*$/", '${1}.${2}.${3}', $version)) === false) {
            static::raiseError(__METHOD__ ."(), failed to parse version string (${version})!");
            return false;
        }

        if (strtolower($this->db_cfg['type']) == "mysql" && version_compare($version, "5.6.4", "<")) {
            static::raiseError(__METHOD__ ."(), MySQL server version 5.6.4 or later is required (found {$version})!");
            return false;
        }

        return true;
    }

    /**
     * uses the PDO internal quote() method to ecape the provided string
     *
     * @param string $text
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function quote($text)
    {
        if (!method_exists($this->db, 'quote')) {
            static::raiseError(__METHOD__ .'(), PDO driver does not provide quote method!');
            return false;
        }

        if (!isset($text) || empty($text) || !is_string($text)) {
            static::raiseError(__METHOD__ .'(), $text parameter is invalid!');
            return false;
        }

        if (($quoted = $this->db->quote($text)) === false) {
            static::raiseError(get_class($this->db) .'::quote() returned false!');
            return false;
        }

        if (!empty($text) && empty($quoted)) {
            static::raiseError(__METHOD__ .'(), something must have gone wrong!');
            return false;
        }

        return $text;
    }

    /**
     * returns true if the specificed $column exists in table $table_name
     *
     * @param string $table_name
     * @param string $column
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function checkColumnExists($table_name, $column)
    {
        if (!$this->isConnected()) {
            static::raiseError(__CLASS__ .'::isConnected() returned false!');
            return false;
        }

        if (!isset($table_name) || empty($table_name) || !is_string($table_name)) {
            static::raiseError(__METHOD__ .'(), $table_name parameter is invalid!');
            return false;
        }

        if (!isset($column) || empty($column) || !is_string($column)) {
            static::raiseError(__METHOD__ .'(), $column parameter is invalid!');
            return false;
        }

        if (($result = $this->query("DESC ". $table_name, \PDO::FETCH_NUM)) === false) {
            static::raiseError(__METHOD__ .'(), failed to fetch table structure!');
            return false;
        }

        while ($row = $result->fetch()) {
            if (in_array($column, $row)) {
                return true;
            }
        }

        return false;
    }

    /**
     * constructs an SQL query by the provided parameters
     *
     * @param string $type
     * @param string $table_name
     * @param string|array $query_columns
     * @param array $query_data
     * @param array $bind_params
     * @param string $extend_where_query
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function buildQuery(
        $type,
        $table_name,
        $query_columns = "*",
        $query_data = array(),
        &$bind_params = array(),
        $extend_where_query = null
    ) {
        if (!isset($type) || empty($type) || !is_string($type)) {
            static::raiseError(__METHOD__ .'(), $type parameter is invalid!');
            return false;
        }

        if (!isset($table_name) || empty($table_name) || !is_string($table_name)) {
            static::raiseError(__METHOD__ .'(), $table_name parameter is invalid!');
            return false;
        }

        if (!isset($query_columns) || (!is_array($query_columns) && !is_string($query_columns))) {
            static::raiseError(__METHOD__ .'(), $query_columns parameter is invalid!');
            return false;
        }

        if (!isset($query_data) || !is_array($query_data)) {
            static::raiseError(__METHOD__ .'(), $query_data parameter is invalid!');
            return false;
        }

        if (!isset($bind_params) || !is_array($bind_params)) {
            static::raiseError(__METHOD__ .'(), $bind_params parameter is invalid!');
            return false;
        }

        if (isset($extend_where_query) &&
            !empty($extend_where_query) &&
            !is_string($extend_where_query)
        ) {
            static::raiseError(__METHOD__ .'(), $extend_where_query is invalid!');
            return false;
        }

        if (!is_string($query_columns) && !is_array($query_columns)) {
            static::raiseError(__METHOD__ .'(), $query_columns parameter has an unsupported type!');
            return false;
        } elseif (is_string($query_columns)) {
            $query_columns_str = $query_columns;
        } elseif (is_array($query_columns)) {
            $query_columns_str = "*";

            if (count($query_columns) >= 1) {
                $columns = array();
                foreach ($query_columns as $key => $value) {
                    $columns[] = $value;
                }
                if (($query_columns_str = implode(', ', $columns)) === false) {
                    static::raiseError(__METHOD__ .'(), implode() returned false!');
                    return false;
                }
            }
        }

        if (is_string($query_data)) {
            if (empty($query_data)) {
                return sprintf(
                    "%s %s FROM %s %s",
                    $type,
                    $query_columns_str,
                    $table_name,
                    !empty($extend_where_query) ? "WHERE {$extend_where_query}" : null
                );
            }

            return sprintf(
                "%s %s FROM %s WHERE %s %s",
                $type,
                $query_columns_str,
                $table_name,
                $query_data,
                $extend_where_query
            );
        } elseif (is_array($query_data) && count($query_data) < 1) {
            return sprintf(
                "%s %s FROM %s %s",
                $type,
                $query_columns_str,
                $table_name,
                !empty($extend_where_query) ? "WHERE {$extend_where_query}" : null
            );
            return $sql;
        }

        $query_wheres_str = '';
        $wheres = array();

        foreach ($query_data as $key => $value) {
            $value_key = sprintf("v_%s", $key);
            $wheres[] = sprintf("%s LIKE :%s", $key, $value_key);
            $bind_params[$value_key] = $value;
        }
        if (($query_wheres_str = implode(' AND ', $wheres)) === false) {
            static::raiseError(__METHOD__ .'(), implode() returned false!');
            return false;
        }

        $sql = sprintf(
            "%s %s FROM %s WHERE %s %s",
            $type,
            $query_columns_str,
            $table_name,
            $query_wheres_str,
            $extend_where_query
        );

        return $sql;
    }

    /**
     * returns a list of all columns within a table.
     *
     * @param string $table_name
     * @return array|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function getColumns($table_name)
    {
        if (!$this->isConnected()) {
            static::raiseError(__CLASS__ .'::isConnected() returned false!');
            return false;
        }

        if (($result = $this->query("DESC ". $table_name, \PDO::FETCH_NUM)) === false) {
            static::raiseError(__METHOD__ .'(), failed to fetch table structure!');
            return false;
        }

        if (($columns = $result->fetchAll()) === false) {
            static::raiseError(get_class($result) .'::fetchAll() returned false!');
            return false;
        }

        return $columns;
    }

    /**
     * starts a database transaction
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function newTransaction()
    {
        if (!$this->isConnected()) {
            static::raiseError(__CLASS__ .'::isConnected() returned false!');
            return false;
        }

        if (isset($this->is_open_transaction) and $this->is_open_transaction === true) {
            static::raiseError(__METHOD__ .'(), there is already an ongoing transaction!');
            return false;
        }

        try {
            $this->db->beginTransaction();
        } catch (\PDOException $e) {
            static::raiseError(get_class($this->db) .'::beginTransaction() failed!', false, $e);
            return false;
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), an unspecific error occurred!', false, $e);
            return false;
        }

        $this->is_open_transaction = true;
        return true;
    }

    /**
     * closes a database transaction
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function closeTransaction()
    {
        if (!isset($this->is_open_transaction) or $this->is_open_transaction !== true) {
            return true;
        }

        try {
            $this->db->commit();
        } catch (\PDOException $e) {
            static::raiseError(get_class($this->db) .'::commit() failed!', false, $e);
            return false;
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), an unspecific error occurred!', false, $e);
            return false;
        }

        $this->is_open_transaction = false;
        return true;
    }

    /**
     * returns the database name.
     *
     * @param none
     * @return string
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function getDatabaseName()
    {
        return $this->db_cfg['db_name'];
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
