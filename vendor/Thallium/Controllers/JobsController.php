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

class JobsController extends DefaultController
{
    const EXPIRE_TIMEOUT = 300;
    protected $currentJobGuid;
    protected $registeredHandlers = array();

    public function __construct()
    {
        if (!$this->removeExpiredJobs()) {
            $this->raiseError('removeExpiredJobs() returned false!', true);
            return false;
        }

        return true;
    }

    protected function removeExpiredJobs()
    {
        try {
            $jobs = new \Thallium\Models\JobsModel;
        } catch (\Exception $e) {
            $this->raiseError('Failed to load JobsModel!');
            return false;
        }

        if (!$jobs->deleteExpiredJobs(self::EXPIRE_TIMEOUT)) {
            $this->raiseError(get_class($jobs) .'::deleteExpiredJobs() returned false!');
            return false;
        }

        return true;
    }

    public function createJob($command, $parameters = null, $sessionid = null, $request_guid = null)
    {
        global $thallium;

        if (!isset($command) || empty($command) || !is_string($command)) {
            $this->raiseError(__METHOD__ .'(), parameter $commmand is required!');
            return false;
        }

        if (isset($sessionid) && (empty($sessionid) || !is_string($sessionid))) {
            $this->raiseError(__METHOD__ .'(), parameter $sessionid has to be a string!');
            return false;
        }

        if (isset($request_guid) &&
           (empty($request_guid) || !$thallium->isValidGuidSyntax($request_guid))
        ) {
            $this->raiseError(__METHOD__ .'(), parameter $request_guid is invalid!');
            return false;
        }

        try {
            $job = new \Thallium\Models\JobModel;
        } catch (\Exception $e) {
            $this->raiseError(__METHOD__ .'(), unable to load JobModel!');
            return false;
        }

        if (isset($sessionid) && !$job->setSessionId($sessionid)) {
            $this->raiseError(get_class($job) .'::setSessionId() returned false!');
            return false;
        }

        if (isset($request_guid) && !$job->setRequestGuid($request_guid)) {
            $this->raiseError(get_class($job) .'::setRequestGuid() returned false!');
            return false;
        }

        if (!$job->setCommand($command)) {
            $this->raiseError(get_class($job) .'::setCommand() returned false!');
            return false;
        }

        if (isset($parameters) && !empty($parameters)) {
            if (!$job->setParameters($parameters)) {
                $this->raiseError(get_class($job) .'::setParameters() returned false!');
                return false;
            }
        }

        if (!$job->save()) {
            $this->raiseError(get_class($job) .'::save() returned false!');
            return false;
        }

        if (!isset($job->job_guid) ||
            empty($job->job_guid) ||
            !$thallium->isValidGuidSyntax($job->job_guid)
        ) {
            $this->raiseError(get_class($job) .'::save() has not lead to a valid GUID!');
            return false;
        }

        return $job;
    }

    public function deleteJob($job_guid)
    {
        global $thallium;

        if (!isset($job_guid) || empty($job_guid) || !$thallium->isValidGuidSyntax($job_guid)) {
            $this->raiseError(__METHOD__ .', first parameter has to be a valid GUID!');
            return false;
        }

        try {
            $job = new \Thallium\Models\JobModel(null, $job_guid);
        } catch (\Exception $e) {
            $this->raiseError(__METHOD__ .", failed to load JobModel(null, {$job_guid})");
            return false;
        }

        if (!$job->delete()) {
            $this->raiseError(get_class($job) .'::delete() returned false!');
            return false;
        }

        if ($this->hasCurrentJob() && ($cur_guid = $this->getCurrentJob())) {
            if ($cur_guid == $job_guid) {
                $this->clearCurrentJob();
            }
        }

        return true;
    }

    public function setCurrentJob($job_guid)
    {
        global $thallium;

        if (!isset($job_guid) || empty($job_guid) || !$thallium->isValidGuidSyntax($job_guid)) {
            $this->raiseError(__METHOD__ .', first parameter has to be a valid GUID!');
            return false;
        }

        $this->currentJobGuid = $job_guid;
        return true;
    }

    public function getCurrentJob()
    {
        if (!$this->hasCurrentJob()) {
            return false;
        }

        return $this->currentJobGuid;
    }

    public function hasCurrentJob()
    {
        if (!isset($this->currentJobGuid) || empty($this->currentJobGuid)) {
            return false;
        }

        return true;
    }

    public function clearCurrentJob()
    {
        unset($this->currentJobGuid);
        return true;
    }

    public function runJob($job)
    {
        global $thallium, $mbus;

        if (is_string($job) && $thallium->isValidGuidSyntax($job)) {
            try {
                $job = new \Thallium\Models\JobModel(null, $job);
            } catch (\Exception $e) {
                $this->raiseError(__METHOD__ .'(), failed to load JobModel!');
                return false;
            }
        }

        if (!is_object($job)) {
            $this->raiseError(__METHOD__ .'(), no valid JobModel provided!');
            return false;
        }

        if (($command = $job->getCommand()) === false) {
            $this->raiseError(get_class($job) .'::getCommand() returned false!');
            return false;
        }

        if (!isset($command) || empty($command) || !is_string($command)) {
            $this->raiseError(get_class($job) .'::getCommand() returned invalid data!');
            return false;
        }

        if (!$this->isRegisteredHandler($command)) {
            $this->raiseError(__METHOD__ ."(), there is no handler for {$command}!");
            return false;
        }

        if (($handler = $this->getHandler($command)) === false) {
            $this->raiseError(__CLASS__ .'::getHandler() returned false!');
            return false;
        }

        if (!isset($handler) || empty($handler) || !is_array($handler) ||
            !isset($handler[0]) || empty($handler[0]) || !is_object($handler[0]) ||
            !isset($handler[1]) || empty($handler[1]) || !is_string($handler[1])
        ) {
            $this->raiseError(__CLASS__ .'::getHandler() returned invalid data!');
            return false;
        }

        if (!is_callable($handler, true)) {
            $this->raiseError(__METHOD__ .'(), handler is not callable!');
            return false;
        }

        if (!$job->hasSessionId()) {
            $state = $mbus->suppressOutboundMessaging(true);
        }

        if (!call_user_func($handler, $job)) {
            $this->raiseError(get_class($handler[0]) ."::{$handler[1]}() returned false!");
            return false;
        }

        if (!$job->hasSessionId()) {
            $mbus->suppressOutboundMessaging($state);
        }

        return true;
    }

    public function runJobs()
    {
        try {
            $jobs = new \Thallium\Models\JobsModel;
        } catch (\Exception $e) {
            $this->raiseError(__METHOD__ .'(), failed to load JobsModel!');
            return false;
        }

        if (($pending = $jobs->getPendingJobs()) === false) {
            $this->raiseError(get_class($jobs) .'::getPendingJobs() returned false!');
            return false;
        }

        if (!isset($pending) || !is_array($pending)) {
            $this->raiseError(get_class($jobs) .'::getPendingJobs() returned invalid data!');
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
                $this->raiseError(get_class($job) .'::setProcessingFlag() returned false!');
                return false;
            }

            if (!$job->save()) {
                $this->raiseError(get_class($job) .'::save() returned false!');
                return false;
            }

            if (!$this->setCurrentJob($job->getGuid())) {
                $this->raiseError(__CLASS__ .'::setCurrentJob() returned false!');
                return false;
            }

            if (!$this->runJob($job)) {
                $this->raiseError(__CLASS__ .'::runJob() returned false!');
                return false;
            }

            if (!$job->delete()) {
                $this->raiseError(get_class($job) .'::delete() returned false!');
                return false;
            }

            if (!$this->clearCurrentJob()) {
                $this->raiseError(__CLASS__ .'::clearCurrentJob() returned false!');
                return false;
            }
        }

        return true;
    }

    public function registerHandler($job_name, $handler)
    {
        if (!isset($job_name) || empty($job_name) || !is_string($job_name)) {
            $this->raiseError(__METHOD__ .'(), $job_name parameter is invalid!');
            return false;
        }

        if (!isset($handler) || empty($handler) || (!is_string($handler) && !is_array($handler))) {
            $this->raiseError(__METHOD__ .'(), $handler parameter is invalid!');
            return false;
        }

        if (is_string($handler)) {
            $handler = array($this, $handler);
        } else {
            if (count($handler) != 2 ||
                !isset($handler[0]) || empty($handler[0]) || !is_object($handler[0]) ||
                !isset($handler[1]) || empty($handler[1]) || !is_string($handler[1])
            ) {
                $this->raiseError(__METHOD__ .'(), $handler parameter contains invalid data!');
                return false;
            }
        }

        if ($this->isRegisteredHandler($job_name)) {
            $this->raiseError(__METHOD__ ."(), a handler for {$job_name} is already registered!");
            return false;
        }

        $this->registeredHandlers[$job_name] = $handler;
    }

    public function unregisterHandler($job_name)
    {
        if (!isset($job_name) || empty($job_name) || !is_string($job_name)) {
            $this->raiseError(__METHOD__ .'(), $job_name parameter is invalid!');
            return false;
        }

        if (!$this->isRegisteredHandler($job_name)) {
            return true;
        }

        unset($this->registeredHandlers[$job_name]);
        return true;
    }

    public function isRegisteredHandler($job_name)
    {
        if (!isset($job_name) || empty($job_name) || !is_string($job_name)) {
            $this->raiseError(__METHOD__ .'(), $job_name parameter is invalid!');
            return false;
        }

        if (!in_array($job_name, array_keys($this->registeredHandlers))) {
            return false;
        }

        return true;
    }

    public function getHandler($job_name)
    {
        if (!isset($job_name) || empty($job_name) || !is_string($job_name)) {
            $this->raiseError(__METHOD__ .'(), $job_name parameter is invalid!');
            return false;
        }

        if (!$this->isRegisteredHandler($job_name)) {
            $this->raiseError(__METHOD__ .'(), no such handler!');
            return false;
        }

        return $this->registeredHandlers[$job_name];
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
