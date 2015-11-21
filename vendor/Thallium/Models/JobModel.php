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

namespace Thallium\Models ;

class JobModel extends DefaultModel
{
    public $table_name = 'jobs';
    public $column_name = 'job';
    public $fields = array(
        'job_idx' => 'integer',
        'job_guid' => 'integer',
        'job_command' => 'string',
        'job_parameters' => 'string',
        'job_session_id' => 'string',
        'job_request_guid' => 'string',
        'job_time' => 'timestamp',
        'job_in_processing' => 'string',
    );

    public function __construct($id = null, $guid = null)
    {
        global $db;

        // are we creating a new item?
        if (!isset($id) && !isset($guid)) {
            parent::__construct(null);
            return true;
        }

        // get $id from db
        $sql = "
            SELECT
                job_idx
            FROM
                TABLEPREFIX{$this->table_name}
            WHERE
        ";

        $arr_query = array();
        if (isset($id)) {
            $sql.= "
                job_idx LIKE ?
            ";
            $arr_query[] = $id;
        }
        if (isset($id) && isset($guid)) {
            $sql.= "
                AND
            ";
        }
        if (isset($guid)) {
            $sql.= "
                job_guid LIKE ?
            ";
            $arr_query[] = $guid;
        };

        if (!($sth = $db->prepare($sql))) {
            $this->raiseError("DatabaseController::prepare() returned false!");
            return false;
        }

        if (!$db->execute($sth, $arr_query)) {
            $this->raiseError("DatabaseController::execute() returned false!");
            return false;
        }

        if (!($row = $sth->fetch())) {
            $this->raiseError("Unable to find job with guid value {$guid}");
            return false;
        }

        if (!isset($row->job_idx) || empty($row->job_idx)) {
            $this->raiseError("Unable to find job with guid value {$guid}");
            return false;
        }

        $db->freeStatement($sth);

        parent::__construct($row->job_idx);
        return true;
    }

    public function setSessionId($sessionid)
    {
        if (empty($sessionid)) {
            $this->raiseError(__METHOD__ .', an empty session id is not allowed!');
            return false;
        }

        if (!is_string($sessionid)) {
            $this->raiseError(__METHOD__ .', parameter has to be a string!');
            return false;
        }

        $this->job_session_id = $sessionid;
        return true;
    }

    public function getSessionId()
    {
        if (!isset($this->job_session_id)) {
            $this->raiseError(__METHOD__ .', \$job_session_id has not been set yet!');
            return false;
        }

        return $this->job_session_id;
    }

    public function setProcessingFlag($value = true)
    {
        if (!$value) {
            $this->job_in_processing = 'N';
            return true;
        }

        $this->job_in_processing = 'Y';
        return true;
    }

    public function getProcessingFlag()
    {
        if (!isset($this->job_in_processing)) {
            return 'N';
        }

        return $this->job_in_processing;
    }

    public function isProcessing()
    {
        if (!isset($this->getProcessingFlag)) {
            return false;
        }

        if ($this->job_in_processing != 'Y') {
            return false;
        }

        return true;
    }

    protected function preSave()
    {
        if (!isset($this->job_in_processing) || empty($this->job_in_processing)) {
            $this->job_in_processing = 'N';
        }

        return true;
    }

    public function setRequestGuid($guid)
    {
        global $thallium;

        if (empty($guid) || !$thallium->isValidGuidSyntax($guid)) {
            $this->raiseError(__METHOD__ .', first parameter needs to be a valid GUID!');
            return false;
        }

        $this->job_request_guid = $guid;
        return true;
    }

    public function getRequestGuid()
    {
        if (!isset($this->job_request_guid)) {
            $this->raiseError(__METHOD__ .', \$job_request_guid has not been set yet!');
            return false;
        }

        return $this->job_request_guid;
    }

    public function getCommand()
    {
        if (!isset($this->job_command)) {
            return false;
        }

        return $this->job_command;
    }

    public function setCommand($command)
    {
        if (!isset($command) || empty($command) || !is_string($command)) {
            $this->raiseError(__METHOD__ .'(), $command parameter needs to be set!');
            return false;
        }

        $this->job_command = $command;
        return true;
    }

    public function getParameters()
    {
        if (!isset($this->job_parameters)) {
            return false;
        }

        return $this->job_parameters;
    }

    public function setParameters($parameters)
    {
        if (!isset($parameters) || empty($parameters) || !is_string($parameters)) {
            $this->raiseError(__METHOD__ .'(), $parameters parameter needs to be set!');
            return false;
        }

        $this->job_parameters = $parameters;
        return true;
    }

    public function hasParameters()
    {
        if (!isset($this->job_parameters) || empty($this->job_parameters)) {
            return false;
        }

        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
