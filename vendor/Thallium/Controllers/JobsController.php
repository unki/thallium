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
 * JobsController handles a queue ob jobs that act on various
 * internal tasks that may be triggered by client or internally.
 *
 * @package Thallium\Controllers\JobController
 * @subpackage Controllers
 * @license AGPL3
 * @copyright 2015-2016 Andreas Unterkircher <unki@netshadow.net>
 * @author Andreas Unterkircher <unki@netshadow.net>
 */
class JobsController extends DefaultController
{
    /** @var int EXPIRE_TIMEOUT how long a job may wait for execution */
    const EXPIRE_TIMEOUT = 300;

    /** @var string $currentjobGuid */
    protected $currentJobGuid;

    /** @var array $registeredHandlers */
    protected $registeredHandlers = array();

    /** @var array $json_errors */
    protected $json_errors;

    /**
     * class constructor
     *
     * @param none
     * @return void
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function __construct()
    {
        if (!$this->removeExpiredJobs()) {
            static::raiseError(__CLASS__ .'::removeExpiredJobs() returned false!', true);
            return;
        }

        if (!$this->registerHandler('delete-request', array($this, 'handleDeleteRequest'))) {
            static::raiseError(__CLASS__ .'::registerHandler() returned false!', true);
            return;
        }

        if (!$this->registerHandler('save-request', array($this, 'handleSaveRequest'))) {
            static::raiseError(__CLASS__ .'::registerHandler() returned false!', true);
            return;
        }

        // Define the JSON errors.
        $constants = get_defined_constants(true);

        $this->json_errors = array();

        foreach ($constants["json"] as $name => $value) {
            if (!strncmp($name, "JSON_ERROR_", 11)) {
                $this->json_errors[$value] = $name;
            }
        }

        return;
    }

    /**
     * remove expired jobs from job queue
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function removeExpiredJobs()
    {
        try {
            $jobs = new \Thallium\Models\JobsModel;
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), failed to load JobsModel!', false, $e);
            return false;
        }

        if (!$jobs->deleteExpiredJobs(static::EXPIRE_TIMEOUT)) {
            static::raiseError(get_class($jobs) .'::deleteExpiredJobs() returned false!');
            return false;
        }

        return true;
    }

    /**
     * remove expired jobs from job queue
     *
     * @param string $command
     * @param array $parameters
     * @param string $sessionid
     * @param string $request_guid
     * @return object|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function createJob($command, $parameters = null, $sessionid = null, $request_guid = null)
    {
        global $thallium;

        if (!isset($command) || empty($command) || !is_string($command)) {
            static::raiseError(__METHOD__ .'(), $commmand parameter is invalid!');
            return false;
        }

        if (isset($parameters) && (
            empty($parameters) ||
            !is_array($parameters)
        )) {
            static::raiseError(__METHOD__ .'(), $parameters parameter is invalid!');
            return false;
        }

        if (isset($sessionid) && (empty($sessionid) || !is_string($sessionid))) {
            static::raiseError(__METHOD__ .'(), $sessionid parameter is invalid!');
            return false;
        }

        if (isset($request_guid) && (
            empty($request_guid) ||
            !is_string($request_guid) ||
            !$thallium->isValidGuidSyntax($request_guid)
        )) {
            static::raiseError(__METHOD__ .'(), $request_guid parameter is invalid!');
            return false;
        }

        try {
            $job = new \Thallium\Models\JobModel;
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), unable to load JobModel!', false, $e);
            return false;
        }

        if (isset($sessionid) && !$job->setSessionId($sessionid)) {
            static::raiseError(get_class($job) .'::setSessionId() returned false!');
            return false;
        }

        if (isset($request_guid) && !$job->setRequestGuid($request_guid)) {
            static::raiseError(get_class($job) .'::setRequestGuid() returned false!');
            return false;
        }

        if (!$job->setCommand($command)) {
            static::raiseError(get_class($job) .'::setCommand() returned false!');
            return false;
        }

        if (isset($parameters) && !$job->setParameters($parameters)) {
            static::raiseError(get_class($job) .'::setParameters() returned false!');
            return false;
        }

        if (!$job->save()) {
            static::raiseError(get_class($job) .'::save() returned false!');
            return false;
        }

        if (!$job->hasGuid()) {
            static::raiseError(get_class($job) .'::save() has not lead to a valid GUID!');
            return false;
        }

        return $job;
    }

    /**
     * delete a job from job queue
     *
     * @param string $job_guid
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function deleteJob($job_guid)
    {
        global $thallium;

        if (!isset($job_guid) ||
            empty($job_guid) ||
            !is_string($job_guid) ||
            !$thallium->isValidGuidSyntax($job_guid)
        ) {
            static::raiseError(__METHOD__ .'(), $job_guid parameter is invalid!');
            return false;
        }

        try {
            $job = new \Thallium\Models\JobModel(array(
                'guid' => $job_guid
            ));
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), failed to load JobModel!', false, $e);
            return false;
        }

        if (!$job->delete()) {
            static::raiseError(get_class($job) .'::delete() returned false!');
            return false;
        }

        if ($this->hasCurrentJob() &&
            ($cur_guid = $this->getCurrentJob()) !== false &&
            $cur_guid == $job_guid
        ) {
            if (!$this->clearCurrentJob()) {
                static::raiseError(__CLASS__ .'::clearCurrentJob() returned false!');
                return false;
            }
        }

        return true;
    }

    /**
     * set the current-job-flag
     *
     * @param string $job_guid
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function setCurrentJob($job_guid)
    {
        global $thallium;

        if (!isset($job_guid) ||
            empty($job_guid) ||
            !is_string($job_guid) ||
            !$thallium->isValidGuidSyntax($job_guid)) {
            static::raiseError(__METHOD__ .'(), $job_guid parameter is invalid!');
            return false;
        }

        $this->currentJobGuid = $job_guid;
        return true;
    }

    /**
     * get the current-job from the current-job-flag
     *
     * @param none
     * @return string
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getCurrentJob()
    {
        if (!$this->hasCurrentJob()) {
            static::raiseError(__CLASS__ .'::hasCurrentJob() returned false!');
            return false;
        }

        return $this->currentJobGuid;
    }

    /**
     * return true if there is a job in the current-job-flag.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function hasCurrentJob()
    {
        if (!isset($this->currentJobGuid) || empty($this->currentJobGuid)) {
            return false;
        }

        return true;
    }

    /**
     * clears the current-job-flag.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function clearCurrentJob()
    {
        $this->currentJobGuid = null;
        return true;
    }

    /**
     * run a specific job.
     *
     * @param string|object $run_job
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function runJob($run_job)
    {
        global $thallium, $mbus;

        if (!isset($run_job) ||
            empty($run_job) ||
            (!is_string($run_job) && !is_object($run_job))
        ) {
            static::raiseError(__METHOD__ .'(), $run_job parameter is invalid!');
            return false;
        }

        if (is_object($run_job) && !is_a($run_job, 'Thallium\Models\JobModel')) {
            static::raiseError(__METHOD__ .'(), $run_job is not a JobModel!');
            return false;
        }

        if (is_string($run_job) && !$thallium->isValidGuidSyntax($run_job)) {
            static::raiseError(__METHOD__ .'(), $run_job is not a valid GUID!');
            return false;
        }

        if (is_string($run_job)) {
            try {
                $job = new \Thallium\Models\JobModel(array(
                    'guid' => $run_job
                ));
            } catch (\Exception $e) {
                static::raiseError(__METHOD__ .'(), failed to load JobModel!', false, $e);
                return false;
            }
        }

        if (($command = $job->getCommand()) === false) {
            static::raiseError(get_class($job) .'::getCommand() returned false!');
            return false;
        }

        if (!isset($command) || empty($command) || !is_string($command)) {
            static::raiseError(get_class($job) .'::getCommand() returned invalid data!');
            return false;
        }

        if (!$this->isRegisteredHandler($command)) {
            static::raiseError(__METHOD__ ."(), there is no handler for {$command}!");
            return false;
        }

        if (($handler = $this->getHandler($command)) === false) {
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

        if (!$job->hasSessionId()) {
            if (($state = $mbus->suppressOutboundMessaging(true)) === null) {
                static::raiseError(get_class($mbus) .'::suppressOutboundMessaging() returned null!');
                return false;
            }
        }

        if (!call_user_func($handler, $job)) {
            static::raiseError(get_class($handler[0]) ."::{$handler[1]}() returned false!");
            return false;
        }

        if (!$job->hasSessionId()) {
            if ($mbus->suppressOutboundMessaging($state) === null) {
                static::raiseError(get_class($mbus) .'::suppressOutboundMessaging() returned null!');
                return false;
            }
        }

        return true;
    }

    /**
     * run job that are waiting in the job queue.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function runJobs()
    {
        try {
            $jobs = new \Thallium\Models\JobsModel;
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), failed to load JobsModel!', false, $e);
            return false;
        }

        if (($pending = $jobs->getPendingJobs()) === false) {
            static::raiseError(get_class($jobs) .'::getPendingJobs() returned false!');
            return false;
        }

        if (!isset($pending) || !is_array($pending)) {
            static::raiseError(get_class($jobs) .'::getPendingJobs() returned invalid data!');
            return false;
        }

        if (empty($pending)) {
            return true;
        }

        foreach ($pending as $job) {
            if ($job->isProcessing()) {
                return true;
            }

            if (!$job->setProcessingFlag()) {
                static::raiseError(get_class($job) .'::setProcessingFlag() returned false!');
                return false;
            }

            if (!$job->save()) {
                static::raiseError(get_class($job) .'::save() returned false!');
                return false;
            }

            if (($job_guid = $job->getGuid()) === false) {
                static::raiseError(get_class($job) .'::getGuid() returned false!');
                return false;
            }

            if (!$this->setCurrentJob($job_guid)) {
                static::raiseError(__CLASS__ .'::setCurrentJob() returned false!');
                return false;
            }

            if (!$this->runJob($job)) {
                static::raiseError(__CLASS__ .'::runJob() returned false!');
                return false;
            }

            if (!$job->delete()) {
                static::raiseError(get_class($job) .'::delete() returned false!');
                return false;
            }

            if (!$this->clearCurrentJob()) {
                static::raiseError(__CLASS__ .'::clearCurrentJob() returned false!');
                return false;
            }
        }

        return true;
    }

    /**
     * registers a handler that act on specific jobs.
     *
     * @param string $job_name
     * @param string|array $handler
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function registerHandler($job_name, $handler)
    {
        if (!isset($job_name) || empty($job_name) || !is_string($job_name)) {
            static::raiseError(__METHOD__ .'(), $job_name parameter is invalid!');
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

        if ($this->isRegisteredHandler($job_name)) {
            static::raiseError(__METHOD__ ."(), a handler for {$job_name} is already registered!");
            return false;
        }

        $this->registeredHandlers[$job_name] = $handler;
        return true;
    }

    /**
     * unregisteres a handler
     *
     * @param string $job_name
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function unregisterHandler($job_name)
    {
        if (!isset($job_name) || empty($job_name) || !is_string($job_name)) {
            static::raiseError(__METHOD__ .'(), $job_name parameter is invalid!');
            return false;
        }

        if (!$this->isRegisteredHandler($job_name)) {
            return true;
        }

        unset($this->registeredHandlers[$job_name]);
        return true;
    }

    /**
     * returns true if the provided handler name is already registered.
     *
     * @param string $job_name
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function isRegisteredHandler($job_name)
    {
        if (!isset($job_name) || empty($job_name) || !is_string($job_name)) {
            static::raiseError(__METHOD__ .'(), $job_name parameter is invalid!');
            return false;
        }

        if (!in_array($job_name, array_keys($this->registeredHandlers))) {
            return false;
        }

        return true;
    }

    /**
     * returns the handler name for a specific job.
     *
     * @param string $job_name
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getHandler($job_name)
    {
        if (!isset($job_name) || empty($job_name) || !is_string($job_name)) {
            static::raiseError(__METHOD__ .'(), $job_name parameter is invalid!');
            return false;
        }

        if (!$this->isRegisteredHandler($job_name)) {
            static::raiseError(__METHOD__ .'(), no such handler!');
            return false;
        }

        return $this->registeredHandlers[$job_name];
    }

    /**
     * a generic handler that handles delete-object requests
     *
     * @param array $job
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function handleDeleteRequest($job)
    {
        global $thallium, $mbus;

        if (!$mbus->sendMessageToClient('delete-reply', 'Preparing', '10%')) {
            static::raiseError(get_class($mbus) .'::sendMessageToClient() returned false!');
            return false;
        }

        if (!isset($job) ||
            empty($job) ||
            !is_object($job) ||
            !is_a($job, 'Thallium\Models\JobModel')
        ) {
            static::raiseError(__METHOD__ .'(), $job parameter is invalid!');
            return false;
        }

        if (!$job->hasParameters() || ($delete_request = $job->getParameters()) === false) {
            static::raiseError(get_class($job) .'::getParameters() returned false!');
            return false;
        }

        if (!is_object($delete_request)) {
            static::raiseError(get_class($job) .'::getParameters() returned invalid data!');
            return false;
        }

        if (!isset($delete_request->id) || empty($delete_request->id) ||
            !isset($delete_request->guid) || empty($delete_request->guid)
        ) {
            static::raiseError(__METHOD__ .'() delete-request is incomplete!');
            return false;
        }

        if ($delete_request->id !== 'all' && !$thallium->isValidId($delete_request->id)) {
            static::raiseError(__METHOD__ .'(), job id is invalid!');
            return false;
        }

        if ($delete_request->guid !== 'all' && !$thallium->isValidGuidSyntax($delete_request->guid)) {
            static::raiseError(__METHOD__ .'() job $guid is invalid!');
            return false;
        }

        if (!isset($delete_request->model) ||
            empty($delete_request->model) ||
            !is_string($delete_request->model)) {
            static::raiseError(__METHOD__ .'(), delete-request does not contain model information!');
            return false;
        }

        if (!$thallium->isRegisteredModel($delete_request->model)) {
            static::raiseError(__METHOD__ .'(), delete-request contains an unsupported model!');
            return false;
        }

        $model = $delete_request->model;
        $id = $delete_request->id;
        $guid = $delete_request->guid;

        if (!$mbus->sendMessageToClient('delete-reply', 'Deleting...', '20%')) {
            static::raiseError(get_class($mbus) .'::sendMessageToClient() returned false!');
            return false;
        }

        if (($obj = $thallium->loadModel($model, $id, $guid)) === false) {
            static::raiseError(get_class($thallium) .'::loadModel() returned false!');
            return false;
        }

        if (!$obj->permitsRpcActions('delete')) {
            static::raiseError(__METHOD__ ."(), requested model does not permit RPC 'delete' action!");
            return false;
        }

        if ($id === 'all' && $guid === 'all') {
            $rm_method =  method_exists($obj, 'flush') ? 'flush' : 'delete';

            if (!$obj->$rm_method()) {
                static::raiseError(get_class($obj) ."::${rm_method}() returned false!");
                return false;
            }

            if (!$mbus->sendMessageToClient('delete-reply', 'Done', '100%')) {
                static::raiseError(get_class($mbus) .'::sendMessageToClient() returned false!');
                return false;
            }

            return true;
        }

        if (!$obj->delete()) {
            static::raiseError(get_class($obj) .'::delete() returned false!');
            return false;
        }

        if (!$mbus->sendMessageToClient('delete-reply', 'Done', '100%')) {
            static::raiseError(get_class($mbus) .'::sendMessageToClient() returned false!');
            return false;
        }

        return true;
    }

    /**
     * a generic handler that saves data into an object.
     *
     * @param array $job
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function handleSaveRequest($job)
    {
        global $thallium, $mbus;

        if (!isset($job) ||
            empty($job) ||
            !is_object($job) ||
            !is_a($job, 'Thallium\Models\JobModel')
        ) {
            static::raiseError(__METHOD__ .'(), $job parameter is invalid!');
            return false;
        }

        if (!$job->hasParameters() || ($save_request = $job->getParameters()) === false) {
            static::raiseError(get_class($job) .'::getParameters() returned false!');
            return false;
        }

        if (!is_object($save_request)) {
            static::raiseError(get_class($job) .'::getParameters() returned invalid data!');
            return false;
        }

        if (!isset($save_request->id) || empty($save_request->id) ||
            !isset($save_request->guid) || empty($save_request->guid)
        ) {
            static::raiseError(__METHOD__ .'(), save-request is incomplete!');
            return false;
        }

        if ($save_request->id !== "new" && !$thallium->isValidId($save_request->id)) {
            static::raiseError(__METHOD__ .'(), job $id is invalid!');
            return false;
        }

        if ($save_request->guid !== "new" && !$thallium->isValidGuidSyntax($save_request->guid)) {
            static::raiseError(__METHOD__ .'(), job $guid is invalid!');
            return false;
        }

        if (!isset($save_request->model) ||
            empty($save_request->model) ||
            !is_string($save_request->model)) {
            static::raiseError(__METHOD__ .'(), save-request does not contain model information!');
            return false;
        }

        if (!$thallium->isRegisteredModel($save_request->model)) {
            static::raiseError(__METHOD__ .'(), save-request contains an unsupported model!');
            return false;
        }

        $model = $save_request->model;

        $id = ($save_request->id !== 'new') ? $save_request->id : null;
        $guid = ($save_request->guid !== 'new') ? $save_request->guid : null;

        unset($save_request->model);
        unset($save_request->id);
        unset($save_request->guid);

        if (!$mbus->sendMessageToClient('save-reply', 'Saving...', '20%')) {
            static::raiseError(get_class($mbus) .'::sendMessageToClient() returned false!');
            return false;
        }

        if (($obj = $thallium->loadModel($model, $id, $guid)) === false) {
            static::raiseError(get_class($thallium) .'::loadModel() returned false!');
            return false;
        }

        if (!$obj->permitsRpcActions('update')) {
            static::raiseError(__METHOD__ ."(), requested model does not permit RPC 'update' action!");
            return false;
        }

        if (!$obj->update($save_request)) {
            static::raiseError(get_class($obj) .'::update() returned false!');
            return false;
        }

        if (!$obj->save()) {
            static::raiseError(get_class($obj) .'::save() returned false!');
            return false;
        }

        if (!$mbus->sendMessageToClient('save-reply', 'Done', '100%')) {
            static::raiseError(get_class($mbus) .'::sendMessageToClient() returned false!');
            return false;
        }

        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
