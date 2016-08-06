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

namespace Thallium\Models ;

/**
 * Represents a single job.
 *
 * @package Thallium\Models\JobModel
 * @subpackage Models
 * @license AGPL3
 * @copyright 2015-2016 Andreas Unterkircher <unki@netshadow.net>
 * @author Andreas Unterkircher <unki@netshadow.net>
 */
class JobModel extends DefaultModel
{
    /** @var string $model_table_name */
    protected static $model_table_name = 'jobs';

    /** @var string $model_column_prefix */
    protected static $model_column_prefix = 'job';

    /** @var array $model_fields */
    protected static $model_fields = array(
        'idx' => array(
            FIELD_TYPE => FIELD_INT,
        ),
        'guid' => array(
            FIELD_TYPE => FIELD_GUID,
        ),
        'command' => array(
            FIELD_TYPE => FIELD_STRING,
        ),
        'command' => array(
            FIELD_TYPE => FIELD_STRING,
        ),
        'parameters' => array(
            FIELD_TYPE => FIELD_STRING,
        ),
        'session_id' => array(
            FIELD_TYPE => FIELD_STRING,
        ),
        'request_guid' => array(
            FIELD_TYPE => FIELD_GUID,
        ),
        'time' => array(
            FIELD_TYPE => FIELD_TIMESTAMP,
        ),
        'in_processing' => array(
            FIELD_TYPE => FIELD_YESNO,
        ),
    );

    /**
     * sets the value of the session_id field
     *
     * @param string $sessionid
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function setSessionId($sessionid)
    {
        if (!isset($sessionid) || empty($sessionid) || !is_string($sessionid)) {
            static::raiseError(__METHOD__ .'(), $sessionid parameter is invalid!');
            return false;
        }

        if (!$this->setFieldValue('session_id', $sessionid)) {
            static::raiseError(__CLASS__ .'::setFieldValue() returned false!');
            return false;
        }

        return true;
    }

    /**
     * returns the value of the session_id field
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getSessionId()
    {
        if (!$this->hasFieldValue('session_id')) {
            static::raiseError(__METHOD__ .'(), \$job_session_id has not been set yet!');
            return false;
        }

        if (($session_id = $this->getFieldValue('session_id')) === false) {
            static::raiseError(__CLASS__ .'::getFieldValue() returned false!');
            return false;
        }

        return $session_id;
    }

    /**
     * returns true if the session_id field has a value.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function hasSessionId()
    {
        if (!$this->hasFieldValue('session_id')) {
            return false;
        }

        return true;
    }

    /**
     * sets the value of the in_processing field
     *
     * @param bool $value
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function setProcessingFlag($value = true)
    {
        if (!isset($value) || !is_bool($value)) {
            static::raiseError(__METHOD__ .'(), $value parameter is invalid!');
            return false;
        }

        $value = 'N';

        if ($value === 'true') {
            $value = 'Y';
        }

        if (!$this->setFieldValue('in_processing', $value)) {
            static::raiseError(__CLASS__ .'::setFieldValue() returned false!');
            return false;
        }

        return true;
    }

    /**
     * returns the value of the in_processing field
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getProcessingFlag()
    {
        if (!$this->hasFieldValue('in_processing')) {
            return 'N';
        }

        if (($flag = $this->getFieldValue('in_processing')) === false) {
            static::raiseError(__CLASS__ .'::getFieldValue() returned false!');
            return false;
        }

        return $flag;
    }

    /**
     * returns true if the in_processing flag is true.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function isProcessing()
    {
        if (($flag = $this->getProcessingFlag()) === false) {
            static::raiseError(__CLASS__ .'::getProcessingFlag() returned false!');
            return false;
        }

        if (!$this->isEnabled($flag)) {
            return false;
        }

        return true;
    }

    protected function preSave()
    {
        if ($this->hasFieldValue('in_processing')) {
            return true;
        }

        if (!$this->setFieldValue('in_processing', 'N')) {
            static::raiseError(__CLASS__ .'::setFieldValue() returned false!');
            return false;
        }

        return true;
    }

    /**
     * sets the value of the request_guid field
     *
     * @param string $guid
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function setRequestGuid($guid)
    {
        global $thallium;

        if (!isset($guid) ||
            empty($guid) ||
            !is_string($guid) ||
            !$thallium->isValidGuidSyntax($guid)
        ) {
            static::raiseError(__METHOD__ .'(), $guid parameter is invalid!');
            return false;
        }

        if (!$this->setFieldValue('request_guid', $guid)) {
            static::raiseError(__CLASS__ .'::setFieldValue() returned false!');
            return false;
        }

        return true;
    }

    /**
     * returns the value of the request_guid field
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getRequestGuid()
    {
        if (!$this->hasFieldValue('request_guid')) {
            static::raiseError(__CLASS__ .'::hasFieldValue() returned false!');
            return false;
        }

        if (($request_guid = $this->getFieldValue('request_guid')) === false) {
            static::raiseError(__CLASS__ .'::getFieldValue() returned false!');
            return false;
        }

        return $request_guid;
    }

    /**
     * returns the value of the command field
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getCommand()
    {
        if (!$this->hasFieldValue('command')) {
            static::raiseError(__CLASS__ .'::hasFieldValue() returned false!');
            return false;
        }

        if (($command = $this->getFieldValue('command')) === false) {
            static::raiseError(__CLASS__ .'::getFieldValue() returned false!');
            return false;
        }

        return $command;
    }

    /**
     * sets the value of the command field
     *
     * @param string $command
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function setCommand($command)
    {
        if (!isset($command) || empty($command) || !is_string($command)) {
            static::raiseError(__METHOD__ .'(), $command parameter needs to be set!');
            return false;
        }

        if (!$this->setFieldValue('command', $command)) {
            static::raiseError(__CLASS__ .'::setFieldValue() returned false!');
            return false;
        }

        return true;
    }

    /**
     * returns the value of the parameters field
     *
     * @param none
     * @return array|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getParameters()
    {
        if (!$this->hasParameters()) {
            static::raiseError(__CLASS__ .'::hasParameters() returned false!');
            return false;
        }

        if (($parameters = $this->getFieldValue('parameters')) === false) {
            static::raiseError(__CLASS__ .'::getFieldValue() returned false!');
            return false;
        }

        if (($params = base64_decode($parameters)) === false) {
            static::raiseError(__METHOD__ .'(), base64_decode() failed on job_parameters!');
            return false;
        }

        if (($params = unserialize($params)) === false) {
            static::raiseError(__METHOD__ .'(), unserialize() job_parameters failed!');
            return false;
        }

        return $params;
    }

    /**
     * sets the value of the parameters field
     *
     * @param array $parameters
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function setParameters($parameters)
    {
        if (!isset($parameters) ||
            empty($parameters) ||
            (!is_string($parameters) || !is_array($parameters) || !is_object($parameters))
        ) {
            static::raiseError(__METHOD__ .'(), $parameters parameter is invalid!');
            return false;
        }

        if (is_object($parameters) && !is_a($parameters, 'stdClass')) {
            static::raiseError(__METHOD__ .'(), only stdClass objects are supported!');
            return false;
        }

        if (!$this->setFieldValue('parameters', base64_encode(serialize($parameters)))) {
            static::raiseError(__CLASS__ .'::setFieldValue() returned false!');
            return false;
        }

        return true;
    }

    /**
     * returns true if the parameters field has a value.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function hasParameters()
    {
        if (!$this->hasFieldValue('parameters')) {
            return false;
        }

        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
