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

use \Thallium\Models;

class JobsController extends DefaultController
{
    const EXPIRE_TIMEOUT = 300;
    protected $currentJobGuid;

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
            $jobs = new Models\JobsModel;
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

    public function createJob($sessionid = null, $request_guid = null)
    {
        global $thallium;

        if (isset($sessionid) && (empty($sessionid) || !is_string($sessionid))) {
            $this->raiseError(__METHOD__ .', parameter \$sessionid has to be a string!');
            return false;
        }

        if (isset($request_guid) && (
                empty($request_guid) ||
                !$thallium->isValidGuidSyntax($request_guid)
            )
        ) {
            $this->raiseError(__METHOD__ .', parameter \$request_guid is invalid!');
            return false;
        }

        try {
            $job = new Models\JobModel;
        } catch (\Exception $e) {
            $this->raiseError(__METHOD__ .', unable to load JobModel!');
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

        return $job->job_guid;
    }

    public function deleteJob($job_guid)
    {
        global $thallium;

        if (!isset($job_guid) || empty($job_guid) || !$thallium->isValidGuidSyntax($job_guid)) {
            $this->raiseError(__METHOD__ .', first parameter has to be a valid GUID!');
            return false;
        }

        try {
            $job = new Models\JobModel(null, $job_guid);
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

    public function setJobInProcessing($guid = null)
    {
        if (!isset($guid) || empty($guid) && $this->hasCurrentJob()) {
            $guid = $this->getCurrentJob();
        }

        try {
            $job = new Models\JobModel(null, $guid);
        } catch (\Exception $e) {
            $this->raiseError(__METHOD__ .", failed to load JobModel(null, {$guid})!");
            return false;
        }

        if (!$job->setProcessingFlag()) {
            $this->raiseError(get_class($job) .'::setProcessingFlag() returned false!');
            return false;
        }

        if (!$job->save()) {
            $this->raiseError(get_class($job) .'::save() returned false!');
            return false;
        }

        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
