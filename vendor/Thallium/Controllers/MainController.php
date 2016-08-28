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
 * This is the main class of Thallium. MainController initials all
 * the other classes containing Controllers, Models and Views.
 *
 * @package Thallium\Controllers\MainController
 * @subpackage Controllers
 * @license AGPL3
 * @copyright 2015-2016 Andreas Unterkircher <unki@netshadow.net>
 * @author Andreas Unterkircher <unki@netshadow.net>
 */
class MainController extends DefaultController
{
    /** @var string FRAMEWORK_VERSION contains the application software level */
    const FRAMEWORK_VERSION = "1.1";

    /** @var int $verbosity_level declares the loglevel */
    protected $verbosity_level = LOG_WARNING;

    /** @var string $override_namespace_prefix override __NAMESPACE__ if necessary */
    protected $override_namespace_prefix;

    /** @var array $registeredModels contains the Thallium-internal models */
    protected $registeredModels = array(
        'auditentry' => 'AuditEntryModel',
        'auditlog' => 'AuditLogModel',
        'jobmodel' => 'JobModel',
        'jobsmodel' => 'JobsModel',
        'messagebusmodel' => 'MessageBusModel',
        'messagemodel' => 'MessageModel',
    );

    /** @var array $registeredHandlers contains the online-registered handlers */
    protected $registeredHandlers = array();

    /** @var bool $backgroundJobsRunning true if currently background jobs are running, false if not */
    protected $backgroundJobsRunning;

    /**
     * class constructor
     *
     * @param string $mode allow specifying the mode Thallium starts into
     * @return void
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function __construct($mode = null)
    {
        // we like errors!
        error_reporting(E_ALL);

        $GLOBALS['thallium'] =& $this;

        if (!$this->setExceptionHandler()) {
            static::raiseError(__CLASS__ .'::setExceptionHandler() returned false!', true);
            return;
        }


        if (!$this->loadController("Config", "config")) {
            static::raiseError(__CLASS__ .'::loadController() returned false!', true);
            return;
        }

        global $config;

        if ($config->inMaintenanceMode()) {
            print "This application is currently in maintenance mode. Please try again later!";
            exit(0);
        }

        if (!$this->loadController("Requirements", "requirements")) {
            static::raiseError(__CLASS__ .'::loadController() returned false!', true);
            return;
        }

        global $requirements;

        if (!$requirements->check()) {
            static::raiseError("Error - not all requirements are met. Please check!", true);
            return;
        }

        // no longer needed
        unset($requirements);

        if (!$this->loadController("Audit", "audit")) {
            static::raiseError(__CLASS__ .'::loadController() returned false!', true);
            return;
        }

        if (!$this->loadController("Database", "db")) {
            static::raiseError(__CLASS__ .'::loadController() returned false!', true);
            return;
        }

        if (!$this->isCmdline()) {
            if (!$this->loadController("HttpRouter", "router")) {
                static::raiseError(__CLASS__ .'::loadController() returned false!');
                return;
            }

            global $router;
        }

        if (isset($router) &&
            $router->hasQueryParams() &&
            $router->hasQueryParam('view') &&
            ($view = $router->getQueryParam('view')) === false &&
            $view === 'install'
        ) {
            $mode = "install";
        }

        if (((isset($mode) && $mode !== "install") ||
            !isset($mode)) &&
            $this->checkUpgrade()
        ) {
            return;
        }

        if (isset($mode) and $mode == "install") {
            if (!$this->loadController("Installer", "installer")) {
                static::raiseError(__CLASS__ .'::loadController() returned false!', true);
                return false;
            }

            global $installer;

            if (!$installer->setup()) {
                static::raiseError(get_class($installer) .'::setup() returned false!', true);
                return;
            }

            unset($installer);

            if (!static::inTestMode()) {
                exit(0);
            }
        }

        if (!$this->loadController("Cache", "cache")) {
            static::raiseError(__METHOD__ .'(), failed to load CacheController!', true);
            return;
        }

        if (!$this->loadController("Session", "session")) {
            static::raiseError(__METHOD__ .'(), failed to load SessionController!', true);
            return;
        }

        if (!$this->loadController("Jobs", "jobs")) {
            static::raiseError(__METHOD__ .'(), failed to load JobsController!', true);
            return;
        }

        if (!$this->loadController("MessageBus", "mbus")) {
            static::raiseError(__METHOD__ .'(), failed to load MessageBusController!', true);
            return;
        }

        if (!$this->loadController("Views", "views")) {
            static::raiseError(__CLASS__ .'::loadController() returned false!', true);
            return;
        }

        if (!$this->processRequestMessages()) {
            static::raiseError(__CLASS__ .'::processRequestMessages() returned false!', true);
            return;
        }

        try {
            $this->registerHandler('rpc', array($this, 'rpcHandler'));
            $this->registerHandler('view', array($this, 'viewHandler'));
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), failed to register handlers!', true, $e);
            return;
        }

        return;
    }

    /**
     * startup methods by which Thallium is actually starting to perform.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function startup()
    {
        /* if we are in test-mode, we can not take control about
         *  output buffer and also not start background tasks.
         */
        if (static::inTestMode()) {
            return true;
        }

        if (!ob_start()) {
            static::raiseError(__METHOD__ .'(), internal error, ob_start() returned false!');
            return false;
        }

        if (!$this->callHandlers()) {
            static::raiseError(__CLASS__ .'::callHandlers() returned false!');
            return false;
        }

        $size = ob_get_length();

        if ($size !== false) {
            header("Content-Length: {$size}");
            header('Connection: close');

            if (!ob_end_flush()) {
                error_log(__METHOD__ .'(), ob_end_flush() returned false!');
            }
            ob_flush();
            flush();
            session_write_close();
        }

        register_shutdown_function(array($this, 'flushOutputBufferToLog'));

        if (!ob_start()) {
            static::raiseError(__METHOD__ .'(), internal error, ob_start() returned false!', true);
            return false;
        }

        if (!$this->runBackgroundJobs()) {
            static::raiseError(__CLASS__ .'::runBackgroundJobs() returned false!');
            return false;
        }

        return true;
    }

    /**
     * this method triggers JobControllers runJobs() method to execute
     * scheduled or pending jobs.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function runBackgroundJobs()
    {
        global $jobs;

        /* if we are in test-mode, we should not start background tasks. */
        if (static::inTestMode()) {
            return true;
        }

        ignore_user_abort(true);
        set_time_limit(30);

        $this->backgroundJobsRunning = true;

        if (!$jobs->runJobs()) {
            static::raiseError(get_class($jobs) .'::runJobs() returned false!');
            return false;
        }

        return true;
    }

    /**
     * method to change the default verbosity level
     *
     * @param int $level LOG_INFO, LOG_WARNING, LOG_DEBUG
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function setVerbosity($level)
    {
        $valid_log_levels = array(
            0 => LOG_INFO,
            1 => LOG_WARNING,
            2 => LOG_DEBUG,
        );

        if (!isset($level) || !is_numeric($level)) {
            static::raiseError(__METHOD__ .'(), $level parameter is invalid!');
            return false;
        }

        if (!in_array($level, $valid_log_levels)) {
            static::raiseError(__METHOD__ .'(), unsupported verbosity level specified!');
            return false;
        }

        $this->verbosity_level = $level;
        return true;

    }

    /**
     * method loads the RpcController and calls perform() to handle RPC
     * requests.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    protected function rpcHandler()
    {
        if (!$this->loadController("Rpc", "rpc")) {
            static::raiseError(__CLASS__ .'::loadController() returned false!');
            return false;
        }

        global $rpc;

        if (!$rpc->perform()) {
            static::raiseError(get_class($rpc) .'::perform() returned false!');
            return false;
        }

        return true;
    }

    /**
     * method loads the UploadController and calls perform() to handle file
     * upload requests;
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    protected function uploadHandler()
    {
        if (!$this->loadController("Upload", "upload")) {
            static::raiseError(__CLASS__ .'::loadController() returned false!');
            return false;
        }

        global $upload;

        if (!$upload->perform()) {
            static::raiseError("UploadController::perform() returned false!");
            return false;
        }

        unset($upload);
        return true;
    }

    /**
     * method to validate an Thallium internal id.
     * This has to be an numeric value - either provided as int or string.
     *
     * @param int|string $id
     * @return bool
     * @throws none
     */
    public function isValidId($id)
    {
        if (!isset($id) || is_null($id)) {
            return false;
        }

        $type = gettype($id);

        if ($type === 'unknown type') {
            return false;
        }

        if ($type !== 'integer' && $type !== 'string') {
            return false;
        }

        if ($type === 'integer' && is_int($id)) {
            return true;
        }

        if (!intval($id)) {
            return false;
        }

        return true;
    }

    /**
     * method to check if the provided $model_name is an valid and registered
     * model.
     *
     * @param string $model_name
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function isValidModel($model_name)
    {
        if (!isset($model_name) ||
            empty($model_name) ||
            !is_string($model_name)
        ) {
            static::raiseError(__METHOD__ .'(), $model_name parameter is invalid!');
            return false;
        }

        $nick = null;
        $model = $model_name;

        if (!preg_match('/model$/i', $model_name)) {
            $nick = $model_name;
            $model = null;
        }

        if (!$this->isRegisteredModel($nick, $model)) {
            return false;
        }

        return true;
    }

    /**
     * method to validate an Thallium internal global unique identifier (GUID).
     *
     * @param string
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function isValidGuidSyntax($guid)
    {
        if (!isset($guid) || empty($guid) || !is_string($guid)) {
            return false;
        }

        if (strlen($guid) != 64) {
            return false;
        }

        return true;
    }

    /**
     * this method allow an string of modelname-id-guid to be parsed
     * into its individual parts.
     * startup methods by which Thallium is actually starting to perform.
     *
     * @param string $id
     * @return bool|stdClass
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function parseId($id)
    {
        if (!isset($id) || empty($id) || !is_string($id)) {
            static::raiseError(__METHOD__ .'(), $id parameter is invalid!');
            return false;
        }

        $parts = array();

        if (preg_match('/(\w+)-([0-9]+)-([a-z0-9]+)/', $id, $parts) === false) {
            return false;
        }

        if (!isset($parts) || empty($parts) || count($parts) != 4) {
            return false;
        }

        $id_obj = new \stdClass();
        $id_obj->original_id = $parts[0];
        $id_obj->model = $parts[1];
        $id_obj->id = $parts[2];
        $id_obj->guid = $parts[3];

        return $id_obj;
    }

    /**
     * method to generate a new global unique identifier (GUID)
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function createGuid()
    {
        if (!function_exists("openssl_random_pseudo_bytes")) {
            $guid = uniqid(rand(0, 32766), true);
            return $guid;
        }

        if (($guid = openssl_random_pseudo_bytes("32")) === false) {
            static::raiseError("openssl_random_pseudo_bytes() returned false!");
            return false;
        }

        $guid = bin2hex($guid);
        return $guid;
    }

    /**
     * method to load a specific model. if no further parameters have been
     * specified, a new model is initiated. Otherwise an existing model
     * identified by $id and/or $guid is getting loaded.
     *
     * @param string $model_name
     * @param int|null $id
     * @param string|null $guid
     * @return object|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function loadModel($model_name, $id = null, $guid = null)
    {
        if (!isset($model_name) || empty($model_name) || !is_string($model_name)) {
            static::raiseError(__METHOD__ .'(), $model_name parameter is invalid!');
            return false;
        }

        if (isset($id) && !empty($id) && !$this->isValidId($id)) {
            static::raiseError(__CLASS__ .'::isValidId() returned false!');
            return false;
        }

        if (isset($guid) && !empty($guid) && !$this->isValidGuidSyntax($guid)) {
            static::raiseError(__CLASS__ .'::isValidGuidSyntax() returned false!');
            return false;
        }

        if (($prefix = $this->getNamespacePrefix()) === false) {
            static::raiseError(__CLAS__ .'::getNamespacePrefix() return false!');
            return false;
        }

        if (!$this->isValidModel($model_name)) {
            static::raiseError(__CLASS__ .'::isValidModel() returned false!');
            return false;
        }

        if (!$this->isRegisteredModel(null, $model_name) &&
            ($this->isRegisteredModel($model_name, null) &&
            ($possible_name = $this->getModelByNick($model_name)) !== false)
        ) {
            static::raiseError(__METHOD__ .'(), no clue how to get the model!');
            return false;
        }

        if (isset($possible_name)) {
            $model_name = $possible_name;
        }

        $model = sprintf('\\%s\\Models\\%s', $prefix, $model_name);

        $load_by = array();
        if (isset($id) && !empty($id)) {
            $load_by['idx'] = $id;
        }
        if (isset($guid) && !empty($guid)) {
            $load_by['guid'] = $guid;
        }

        if (empty($load_by)) {
            static::raiseError(__METhOD__ .'(), no clue how to load that model!');
            return false;
        }

        try {
            $obj = new $model($load_by);
        } catch (\Exception $e) {
            static::raiseError(sprintf(
                "%s(), failed to load model %s",
                __METHOD__,
                $model
            ), false, $e);
            return false;
        }

        if (!isset($obj) || !is_object($obj)) {
            static::raiseError(__METHOD__ .'(), an unknown error occured!');
            return false;
        }

        return $obj;
    }

    /**
     * method to check if there is a pending upgrade that needs to be executed.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function checkUpgrade()
    {
        global $db, $config;

        if (($base_path = $config->getWebPath()) === false) {
            static::raiseError(get_class($config) .'::getWebPath() returned false!');
            return false;
        }

        if ($base_path == '/') {
            $base_path = '';
        }

        if (!$db->checkTableExists("TABLEPREFIXmeta")) {
            static::raiseError(
                "You are missing meta table in database! "
                ."You may run <a href=\"{$base_path}/install\">"
                ."Installer</a> to fix this.",
                true
            );
            return true;
        }

        try {
            $framework_db_schema_version = $db->getFrameworkDatabaseSchemaVersion();
            $framework_sw_schema_version = $db->getFrameworkSoftwareSchemaVersion();
            $application_db_schema_version = $db->getApplicationDatabaseSchemaVersion();
            $application_sw_schema_version = $db->getApplicationSoftwareSchemaVersion();
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), failed to read current schema state!');
            return false;
        }

        if ($application_db_schema_version < $application_sw_schema_version ||
            $framework_db_schema_version < $framework_sw_schema_version
        ) {
            static::raiseError(sprintf(
                "A database schema upgrade is pending.&nbsp;"
                ."You have to run <a href=\"%s/install\">Installer</a> "
                ."again to upgrade.",
                $base_path
            ), true);
            return true;
        }

        return false;
    }

    /**
     * method to load a specific controller. if no further parameters have been
     *
     * @param string $controller
     * @param string $global_name
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function loadController($controller, $global_name)
    {
        if (!isset($controller) || empty($controller) || !is_string($controller)) {
            static::raiseError(__METHOD__ .'(), $controller parameter is invalid!');
            return false;
        }

        if (isset($GLOBALS[$global_name]) &&
            !empty($GLOBALS[$global_name])
        ) {
            return true;
        }

        if (($prefix = $this->getNamespacePrefix()) === false) {
            static::raiseError(__METHOD__ .'(), failed to fetch namespace prefix!');
            return false;
        }

        $controller = sprintf('\\%s\\Controllers\\%sController', $prefix, $controller);

        if (!class_exists($controller, true)) {
            static::raiseError("{$controller} class is not available!");
            return false;
        }

        try {
            $$global_name = new $controller;
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), failed to load controller!', false, $e);
            return false;
        }

        $GLOBALS[$global_name] =& $$global_name;
        return true;
    }

    /**
     * returns the numeric user-id of the current process.
     *
     * @param none
     * @return int|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function getProcessUserId()
    {
        if (($uid = posix_getuid()) === false) {
            static::raiseError(__METHOD__ .'(), posix_getuid() returned false!');
            return false;
        }

        return $uid;
    }

    /**
     * returns the numeric group-id of the current process.
     *
     * @param none
     * @return int|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function getProcessGroupId()
    {
        if (($gid = posix_getgid()) === false) {
            static::raiseError(__METHOD__ .'(), posix_getgid() returned false!');
            return false;
        }

        return $gid;
    }

    /**
     * returns the user-name of the current process.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function getProcessUserName()
    {
        if (($uid = $this->getProcessUserId()) === false) {
            static::raiseError(__CLASS__ .'::getProcessUserId() returned false!');
            return false;
        }

        if (($user = posix_getpwuid($uid)) === false) {
            static::raiseError(__METHOD__ .'(), posix_getpwuid() returned false!');
            return false;
        }

        return $user['name'];
    }

    /**
     * returns the group-name of the current process.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function getProcessGroupName()
    {
        if (($uid = $this->getProcessGroupId()) === false) {
            static::raiseError(__CLASS__ .'::getProcessGroupId() returned false!');
            return false;
        }

        if (($group = posix_getgrgid($uid)) === false) {
            static::raiseError(__METHOD__ .'(), posix_getgrgid() returned false!');
            return false;
        }

        return $group['name'];
    }

    /**
     * method processes messages that have been submitted via the MessageBus
     * interface using MessageBusController.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function processRequestMessages()
    {
        global $mbus;

        if (($messages = $mbus->getRequestMessages()) === false || empty($messages)) {
            return true;
        }

        if (!is_array($messages)) {
            static::raiseError(get_class($mbus) .'::getRequestMessages() has not returned an array!');
            return false;
        }

        foreach ($messages as $message) {
            if (!$message->setProcessingFlag()) {
                static::raiseError(get_class($message) .'::setProcessingFlag() returned false!');
                return false;
            }

            if (!$message->save()) {
                static::raiseError(get_class($message) .'::save() returned false!');
                return false;
            }

            if (!$this->handleMessage($message)) {
                static::raiseError(__CLASS__ .'::handleMessage() returned false!');
                return false;
            }

            if (!$message->delete()) {
                static::raiseError(get_class($message) .'::delete() returned false!');
                return false;
            }
        }

        return true;
    }

    /**
     * handle a message.
     *
     * @param \Thallium\Models\MessageModel $message
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    protected function handleMessage(&$message)
    {
        global $jobs;

        if (!isset($message) || empty($message) || !is_object($message)) {
            static::raiseError(__METHOD__ .'(), $message pararmeter is invalid!');
            return false;
        }

        if (get_class($message) != 'Thallium\\Models\\MessageModel') {
            static::raiseError(__METHOD__ .'(), requires a MessageModel reference as parameter!');
            return false;
        }

        if (!$message->isClientMessage()) {
            static::raiseError(__METHOD__ .'(), can only handle client requests!');
            return false;
        }

        if (($command = $message->getCommand()) === false) {
            static::raiseError(get_class($message) .'::getCommand() returned false!');
            return false;
        }

        if (!is_string($command)) {
            static::raiseError(get_class($message) .'::getCommand() has not returned a string!');
            return false;
        }

        $parameters = null;

        if ($message->hasBody() && ($parameters = $message->getBody()) === false) {
            static::raiseError(get_class($message) .'::getBody() returned false!');
            return false;
        }

        if (($sessionid = $message->getSessionId()) === false) {
            static::raiseError(get_class($message) .'::getSessionId() returned false!');
            return false;
        }

        if (($msg_guid = $message->getGuid()) === false || !$this->isValidGuidSyntax($msg_guid)) {
            static::raiseError(get_class($message) .'::getGuid() has not returned a valid GUID!');
            return false;
        }

        if ($jobs->createJob($command, $parameters, $sessionid, $msg_guid) === false) {
            static::raiseError(get_class($jobs) .'::createJob() returned false!');
            return false;
        }

        return true;
    }

    /**
     * returns the current namespace prefix. if not overriden,
     * this will usually return __NAMESPACE__.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function getNamespacePrefix()
    {
        if (isset($this->override_namespace_prefix) &&
            !empty($this->override_namespace_prefix)
        ) {
            return $this->override_namespace_prefix;
        }

        $namespace = __NAMESPACE__;

        if (!strstr($namespace, '\\')) {
            return $namespace;
        }

        $namespace_parts = explode('\\', $namespace);

        if (!isset($namespace_parts) ||
            empty($namespace_parts) ||
            !is_array($namespace_parts) ||
            !isset($namespace_parts[0]) ||
            empty($namespace_parts[0]) ||
            !is_string($namespace_parts[0])
        ) {
            static::raiseError(__METHOD__ .'(), failed to extract prefix from __NAMESPACE__ constant!');
            return false;
        }

        return $namespace_parts[0];
    }

    /**
     * override the default namespace.
     *
     * @param string $prefix
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function setNamespacePrefix($prefix)
    {
        if (!isset($prefix) || empty($prefix) || !is_string($prefix)) {
            static::raiseError(__METHOD__ .'(), $prefix parameter is invalid!');
            return false;
        }

        $this->override_namespace_prefix = $prefix;
        return true;
    }

    /**
     * method returns a list of all registered models.
     *
     * @param none
     * @return array|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function getRegisteredModels()
    {
        if (!isset($this->registeredModels) ||
            empty($this->registeredModels) ||
            !is_array($this->registeredModels)
        ) {
            static::raiseError(__METHOD__ .'(), registeredModels property is invalid!');
            return false;
        }

        return $this->registeredModels;
    }

    /**
     * method to registere a model into Thallium.
     *
     * @param string $nick
     * @param string $model
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function registerModel($nick, $model)
    {
        if (!isset($this->registeredModels) ||
            empty($this->registeredModels) ||
            !is_array($this->registeredModels)
        ) {
            static::raiseError(__METHOD__ .'(), registeredModels property is invalid!', true);
            return false;
        }

        if (!isset($nick) || empty($nick) || !is_string($nick)) {
            static::raiseError(__METHOD__ .'(), $nick parameter is invalid!', true);
            return false;
        }

        if (!isset($model) || empty($model) || !is_string($model)) {
            static::raiseError(__METHOD__ .'(), $model parameter is invalid!', true);
            return false;
        }

        if ($this->isRegisteredModel($nick, $model)) {
            return true;
        }

        if (($prefix = $this->getNamespacePrefix()) === false) {
            static::raiseError(__METHOD__ .'(), failed to fetch namespace prefix!', true);
            return false;
        }

        $full_model_name = "\\{$prefix}\\Models\\{$model}";

        if (!class_exists($full_model_name, true)) {
            static::raiseError(__METHOD__ ."(), model {$model} class does not exist!", true);
            return false;
        }

        $this->registeredModels[$nick] = $model;
        return true;
    }

    /**
     * method to check if a model is registered in Thallium. the lookup can happen
     * by using the models nickname and/or the modelname.
     *
     * @param string $nick
     * @param string $model
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function isRegisteredModel($nick = null, $model = null)
    {
        if ((!isset($nick) || empty($nick) || !is_string($nick)) &&
            (!isset($model) || empty($model) || !is_string($model))
        ) {
            static::raiseError(__METHOD__ .'(), can not look for nothing!');
            return false;
        }

        if (($known_models = $this->getRegisteredModels()) === false) {
            static::raiseError(__METHOD__ .'(), getRegisteredModels() returned false!');
            return false;
        }

        $result = false;

        if (isset($nick) && !empty($nick)) {
            if (in_array($nick, array_keys($known_models))) {
                $result = true;
            }
        }

        // not looking for $model? then we are done.
        if (!isset($model) || empty($model)) {
            return $result;
        }

        // looking for nick was ok, but does it also match $model?
        if ($result) {
            if ($known_models[$nick] == $model) {
                return true;
            }

            return false;
        }

        if (!in_array($model, $known_models)) {
            return false;
        }

        return true;
    }

    /**
     * method to return the full modelname by using the models nickname.
     *
     * @param string $nick
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function getModelByNick($nick)
    {
        if (!isset($nick) || empty($nick) || !is_string($nick)) {
            static::raiseError(__METHOD__ .'(), $nick parameter is invalid!');
            return false;
        }

        if (($known_models = $this->getRegisteredModels()) === false) {
            static::raiseError(__METHOD__ .'(), getRegisteredModels() returned false!');
            return false;
        }

        if (!isset($known_models[$nick])) {
            return false;
        }

        return $known_models[$nick];
    }

    /**
     * method to check if the directory provided in as $dir parameter is hierarchical
     * below the $topmost directory.
     *
     * @param string $dir
     * @param string|null $topmost
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function isBelowDirectory($dir, $topmost = null)
    {
        if (!isset($dir) || empty($dir) || !is_string($dir)) {
            static::raiseError(__METHOD__ .'(), $dir parameter is invalid!');
            return false;
        }

        if (isset($topmost) && !empty($topmost) && !is_string($topmost)) {
            static::raiseError(__METHOD__ .'(), $topmost parameter is invalid!');
            return false;
        }

        if (!isset($topmost) || empty($topmost)) {
            $topmost = APP_BASE;
        }

        $dir = strtolower(realpath($dir));
        $dir_top = strtolower(realpath($topmost));

        $dir_top_reg = preg_quote($dir_top, '/');

        // check if $dir is within $dir_top
        if (!preg_match('/^'. $dir_top_reg .'/', $dir)) {
            return false;
        }

        if ($dir == $dir_top) {
            return false;
        }

        $cnt_dir = count(explode('/', $dir));
        $cnt_dir_top = count(explode('/', $dir_top));

        if ($cnt_dir <= $cnt_dir_top) {
            return false;
        }

        return true;
    }

    /**
     * method to register a handler to Thallium.
     *
     * @param string $handler_name
     * @param string|array $handler
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    protected function registerHandler($handler_name, $handler)
    {
        if (!isset($handler_name) || empty($handler_name) || !is_string($handler_name)) {
            static::raiseError(__METHOD__ .'(), $handler_name parameter is invalid!');
            return false;
        }

        if (!isset($handler) || empty($handler) || (!is_string($handler) && !is_array($handler))) {
            static::raiseError(__METHOD__ .'(), $handler parameter is invalid!');
            return false;
        }

        if (is_string($handler)) {
            $handler = array($this, $handler);
        } elseif (is_array($handler)) {
            if (count($handler) != 2 ||
                !isset($handler[0]) || empty($handler[0]) || !is_object($handler[0]) ||
                !isset($handler[1]) || empty($handler[1]) || !is_string($handler[1])
            ) {
                static::raiseError(__METHOD__ .'(), $handler parameter contains invalid data!');
                return false;
            }
        }

        if ($this->isRegisteredHandler($handler_name)) {
            static::raiseError(__METHOD__ ."(), a handler for {$handler_name} is already registered!");
            return false;
        }

        $this->registeredHandlers[$handler_name] = $handler;
    }

    /**
     * method to unregister a handler from Thallium.
     *
     * @param string $handler_name
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    protected function unregisterHandler($handler_name)
    {
        if (!isset($handler_name) || empty($handler_name) || !is_string($handler_name)) {
            static::raiseError(__METHOD__ .'(), $handler_name parameter is invalid!');
            return false;
        }

        if (!$this->isRegisteredHandler($handler_name)) {
            return true;
        }

        unset($this->registeredHandlers[$handler_name]);
        return true;
    }

    /**
     * method to check if a handler is register to Thallium.
     *
     * @param string $handler_name
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    protected function isRegisteredHandler($handler_name)
    {
        if (!isset($handler_name) || empty($handler_name) || !is_string($handler_name)) {
            static::raiseError(__METHOD__ .'(), $handler_name parameter is invalid!');
            return false;
        }

        if (!in_array($handler_name, array_keys($this->registeredHandlers))) {
            return false;
        }

        return true;
    }

    /**
     * method returns the handler identified by the provided $handler_name.
     *
     * @param string $handler_name
     * @return array|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */

    protected function getHandler($handler_name)
    {
        if (!isset($handler_name) || empty($handler_name) || !is_string($handler_name)) {
            static::raiseError(__METHOD__ .'(), $handler_name parameter is invalid!');
            return false;
        }

        if (!$this->isRegisteredHandler($handler_name)) {
            static::raiseError(__METHOD__ .'(), no such handler!');
            return false;
        }

        return $this->registeredHandlers[$handler_name];
    }

    /**
     * a generic view-handler
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    protected function viewHandler()
    {
        global $views, $router;

        if (!$router->hasQueryParams() ||
            !$router->hasQueryParam('view') ||
            ($view = $router->getQueryParam('view')) === false ||
            empty($view)
        ) {
            static::raiseError(__METHOD__ .'(), no view has been requested!');
            return false;
        }

        if (($page = $views->load($view)) === false) {
            static::raiseError(get_class($views) .'::load() returned false!');
            return false;
        }

        if ($page === true) {
            return true;
        }

        // display output and close the connection to the client.
        if (!empty($page)) {
            print $page;
        }

        return true;
    }

    /**
     * this methods gets called once by startup() method.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    protected function callHandlers()
    {
        global $router;

        if (!isset($router) ||
            empty($router) ||
            !is_object($router) ||
            !is_a($router, 'Thallium\Controllers\HttpRouterController')
        ) {
            static::raiseError(__METHOD__ .'(), HttpRouterController not loaded!');
            return false;
        }

        if ($router->isRpcCall()) {
            if (!$this->callHandler('rpc')) {
                static::raiseError(__CLASS__ .'::callHandler() returned false!');
                return false;
            }
            return true;
        } elseif ($router->isUploadCall()) {
            if (!$this->callHandler('upload')) {
                static::raiseError(__CLASS__ .'::callHandler() returned false!');
                return false;
            }
            return true;
        }

        if (!$this->callHandler('view')) {
            static::raiseError(__CLASS__ .'::callHandler() returned false!');
            return false;
        }

        return true;
    }

    /**
     * method that actually executes an registered handler
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    protected function callHandler($handler_name)
    {
        if (!isset($handler_name) || empty($handler_name) || !is_string($handler_name)) {
            static::raiseError(__METHOD__ .'(), $handler_name parameter is invalid!');
            return false;
        }

        if (($handler = $this->getHandler($handler_name)) === false) {
            static::raiseError(__CLASS__ .'::getHandler() returned false!');
            return false;
        }

        if (!isset($handler) || empty($handler) || !is_array($handler) ||
            !isset($handler[0]) || empty($handler[0]) || !is_object($handler[0]) ||
            !isset($handler[1]) || empty($handler[1]) || !is_string($handler[1])
        ) {
            static::raiseError(__CLASS__ .'::getHandler() returned invalid data!');
            return false;
        }

        if (!is_callable($handler, true)) {
            static::raiseError(__METHOD__ .'(), handler is not callable!');
            return false;
        }

        if (!call_user_func($handler)) {
            static::raiseError(get_class($handler[0]) ."::{$handler[1]}() returned false!");
            return false;
        }

        return true;
    }

    /**
     * method to flush the output buffer to the client.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function flushOutputBufferToLog()
    {
        if (($size = ob_get_length()) === false || empty($size)) {
            return true;
        }

        if (($buffer = ob_get_contents()) === false || empty($buffer)) {
            return true;
        }

        if (!ob_end_clean()) {
            error_log(__METHOD__ .'(), ob_end_clean() returned false!');
        }

        ob_flush();
        flush();

        error_log(__METHOD__ .'(), background jobs have issued output! output follows:');
        error_log(__METHOD__ .'(), '. $buffer);
        return true;
    }

    /**
     * method returns the full modelname if models nickname is provided.
     *
     * @param string $model
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function getFullModelName($model)
    {
        if (!$this->isRegisteredModel($model, $model)) {
            static::raiseError(__CLASS__ .'::isRegisteredModel() returned false!');
            return false;
        }

        if (($prefix = $this->getNamespacePrefix()) === false) {
            static::raiseError(__METHOD__ .'(), failed to fetch namespace prefix!');
            return false;
        }

        $full_model_name = "\\{$prefix}\\Models\\{$model}";
        return $full_model_name;
    }

    /**
     * returns true if a testsuite is running.
     * Hey, my car does the same for me - it doesn't matter...
     *
     * @param none
     * @return bool
     * @throws none
     */
    public static function inTestMode()
    {
        if (!defined('PHPUNIT_THALLIUM_TESTSUITE_ACTIVE')) {
            return false;
        }

        if ((int) constant('PHPUNIT_THALLIUM_TESTSUITE_ACTIVE') !== 1) {
            return false;
        }

        return true;
    }

    /**
     * install an generic exception handler that catches all not
     * previously catched exceptions. as soon as the exception
     * handler is called, PHP aborts.
     *
     * @param none
     * @return bool
     * @throws none
     */
    protected function setExceptionHandler()
    {
        if ($this->inTestMode()) {
            return true;
        }

        try {
            set_exception_handler(array(__CLASS__, 'exceptionHandler'));
        } catch (\Exception $e) {
            trigger_error("Failed to register execption handler. ". $e->getMessage(), E_USER_ERROR);
            return false;
        }

        return true;
    }

    /**
     * a generic exception handler that takes care of exceptions not
     * previously have been catched by a try-catch-block.
     *
     * Note: as soon as PHP has executed exceptionHandler(), it will
     * stop execution.
     */
    public static function exceptionHandler($e)
    {
        print $e;

        trigger_error("Execution stopped.", E_USER_ERROR);
        return;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
