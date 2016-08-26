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
 * Represents a single message-bus message.
 *
 * @package Thallium\Models\MessageModel
 * @subpackage Models
 * @license AGPL3
 * @copyright 2015-2016 Andreas Unterkircher <unki@netshadow.net>
 * @author Andreas Unterkircher <unki@netshadow.net>
 */
class MessageModel extends DefaultModel
{
    /** @var string $model_table_name */
    protected static $model_table_name = 'message_bus';

    /** @var string $model_column_prefix */
    protected static $model_column_prefix = 'msg';

    /** @var array $model_fields */
    protected static $model_fields = array(
        'idx' => array(
            FIELD_TYPE => FIELD_INT,
        ),
        'guid' => array(
            FIELD_TYPE => FIELD_GUID,
        ),
        'scope' => array(
            FIELD_TYPE => FIELD_STRING,
        ),
        'submit_time' => array(
            FIELD_TYPE => FIELD_TIMESTAMP,
        ),
        'session_id' => array(
            FIELD_TYPE => FIELD_STRING,
        ),
        'command' => array(
            FIELD_TYPE => FIELD_STRING,
        ),
        'body' => array(
            FIELD_TYPE => FIELD_STRING,
            FIELD_LENGTH => 4096,
        ),
        'value' => array(
            FIELD_TYPE => FIELD_STRING,
        ),
        'in_processing' => array(
            FIELD_TYPE => FIELD_YESNO,
        ),
    );

    protected static $message_scopes = array(
        'inbound',
        'outbound'
    );

    /**
     * sets the messages command field.
     *
     * @param string $command
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function setCommand($command)
    {
        if (!isset($command) || empty($command) || !is_string($command)) {
            static::raiseError(__METHOD__ .'(), $command parameter is invalid!');
            return false;
        }

        if (!$this->setFieldValue('command', $command)) {
            static::raiseError(__CLASS__ .'::setFieldValue() returned false!');
            return false;
        }

        return true;
    }

    /**
     * sets the messages session_id field.
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
     * returns true if the session_id field is set.
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
     * returns the value of the session_id field.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getSessionId()
    {
        if (!$this->hasSessionId()) {
            static::raiseError(__CLASS__ .'::hasSessionId() returned false!');
            return false;
        }

        if (($session_id = $this->getFieldValue('session_id')) === false) {
            static::raiseError(__CLASS__ .'::getFieldValue() returned false!');
            return false;
        }

        return $session_id;
    }

    /**
     * returns true if there is a value in the session_id field.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function hasCommand()
    {
        if (!$this->hasFieldValue('command')) {
            return false;
        }

        return true;
    }

    /**
     * returns the value of the command field.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getCommand()
    {
        if (!$this->hasCommand()) {
            static::raiseError(__CLASS__ .'::hasCommand() returned false!');
            return false;
        }

        if (($command = $this->getFieldValue('command')) === false) {
            static::raiseError(__CLASS__ .'::getFieldValue() returned false!');
            return false;
        }

        return $command;
    }

    /**
     * sets the messages body field.
     *
     * @param string $body
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function setBody($body)
    {
        if (!isset($body) ||
            empty($body) ||
            (!is_string($body) && !is_array($body) && !is_object($body))
        ) {
            static::raiseError(__METHOD__ .'(), $body parameter is invalid!');
            return false;
        }

        if (is_string($body)) {
            if (!$this->setFieldValue('body', base64_encode(serialize($body)))) {
                static::raiseError(__CLASS__ .'::setFieldValue() returned false!');
                return false;
            }
            return true;
        } elseif (is_array($body)) {
            $filtered_body = array_filter($body, function ($var) {
                if (is_numeric($var) || is_string($var)) {
                    return true;
                }
                return false;
            });
            if (!$this->setFieldValue('body', base64_encode(serialize($filtered_body)))) {
                static::raiseError(__CLASS__ .'::setFieldValue() returned false!');
                return false;
            }
            return true;
        } elseif (!is_object($body)) {
            static::raiseError(__METHOD__ .'(), unknown $body type!');
            return false;
        }

        if (!is_a($body, 'stdClass')) {
            static::raiseError(__METHOD__ .'(), only stdClass objects are supported!');
            return false;
        }

        if (($vars = get_object_vars($body)) === null) {
            static::raiseError(__METHOD__ .'(), $body object has no properties assigned!');
            return false;
        }

        if (!isset($vars) || empty($vars) || !is_array($vars)) {
            static::raiseError(__METHOD__ .'(), get_object_vars() has not reveal any class properties!');
            return false;
        }

        $filtered_body = new \stdClass;
        foreach ($vars as $key => $value) {
            if ((!is_string($key) && !is_numeric($key)) ||
                (!is_string($value) && !is_numeric($value))
            ) {
                continue;
            }
            $filtered_body->$key = $value;
        }

        if (!$this->setFieldValue('body', base64_encode(serialize($filtered_body)))) {
            static::raiseError(__CLASS__ .'::setFieldValue() returned false!');
            return false;
        }

        return true;
    }

    /**
     * returns true if there is a value in the body field.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function hasBody()
    {
        if (!$this->hasFieldValue('body')) {
            return false;
        }

        return true;
    }

    /**
     * returns the decoded and unserialized value of the body field.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getBody()
    {
        if (!$this->hasFieldValue('body')) {
            static::raiseError(__CLASS__ .'::hasFieldValue() returned false!');
            return false;
        }

        if (($body_raw = $this->getBodyRaw()) === false) {
            static::raiseError(__CLASS__ .'::getBodyRaw() returned false!');
            return false;
        }

        if (($body = base64_decode($body_raw)) === false) {
            static::raiseError(__METHOD__ .'(), base64_decode() failed on msg_body!');
            return false;
        }

        if (($body = unserialize($body)) === false) {
            static::raiseError(__METHOD__ .'(), unserialize() msg_body failed!');
            return false;
        }

        return $body;
    }

    /**
     * returns the value of the body field, but undecoded, as it is stored
     * in database.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getBodyRaw()
    {
        if (!$this->hasFieldValue('body')) {
            static::raiseError(__CLASS__ .'::hasFieldValue() returned false!');
            return false;
        }

        if (($body_raw = $this->getFieldValue('body')) === false) {
            static::raiseError(__CLASS__ .'::getFieldValue() returned false!');
            return false;
        }

        return $body_raw;
    }

    /**
     * sets the messages scope field.
     *
     * @param string $scope
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function setScope($scope)
    {
        if (!isset($scope) || empty($scope) || !is_string($scope)) {
            static::raiseError(__METHOD__ .'(), $scope parameter is invalid!');
            return false;
        }

        if (!in_array($scope, static::$message_scopes)) {
            static::raiseError(__METHOD__ .'(), invalid scope provided!');
            return false;
        }

        if (!$this->setFieldValue('scope', $scope)) {
            static::raiseError(__CLASS__ .'::setFieldValue() returned false!');
            return false;
        }

        return true;
    }

    /**
     * returns true if the scope field is set.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function hasScope()
    {
        if (!$this->hasFieldValue('scope')) {
            return false;
        }

        return true;
    }

    /**
     * returns the value of the scope field.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getScope()
    {
        if (!$this->hasScope()) {
            static::raiseError(__CLASS__ .'::hasScope() returned false!');
            return false;
        }

        if (($scope = $this->getFieldValue('scope')) === false) {
            static::raiseError(__CLASS__ .'::getFieldValue() returned false!');
            return false;
        }

        if (!in_array($scope, static::$message_scopes)) {
            static::raiseError(__METHOD__ .'(), invalid scope returned!');
            return false;
        }

        return $scope;
    }

    /**
     * returns true if the message is designated to a client.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function isClientMessage()
    {
        if (!$this->hasScope()) {
            return false;
        }

        if (($scope = $this->getScope()) === false) {
            static::raiseError(__CLASS__ .'::getScope() returned false!');
            return false;
        }

        if ($scope !== 'inbound') {
            return false;
        }

        return true;
    }

    /**
     * returns true if the message is designated to the framework.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function isServerMessage()
    {
        if (!$this->hasScope()) {
            return false;
        }

        if (($scope = $this->getScope()) === false) {
            static::raiseError(__CLASS__ .'::getScope() returned false!');
            return false;
        }

        if ($scope !== 'outbound') {
            return false;
        }

        return true;
    }

    /**
     * sets the messages in_processing field.
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

        if ($value === true) {
            $value = 'Y';
        }

        if (!$this->setFieldValue('in_processing', $value)) {
            static::raiseError(__CLASS__ .'::setFieldValue() returned false!');
            return false;
        }

        return true;
    }

    /**
     * returns true if the in_processing field has a value set.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function hasProcessingFlag()
    {
        if (!$this->hasFieldValue('in_processing')) {
            return false;
        }

        return true;
    }
   
    /**
     * returns the value of the in_processing field.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getProcessingFlag()
    {
        if (!$this->hasProcessingFlag()) {
            static::raiseError(__CLASS__ .'::hasProcessingFlag() returned false!');
            return false;
        }

        if (($in_processing = $this->getFieldValue('in_processing')) === false) {
            static::raiseError(__CLASS__ .'::getFieldValue() returned false!');
            return false;
        }

        return $in_processing;
    }

    /**
     * returns true if the in_processing field is set to true
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function isProcessing()
    {
        if (!$this->hasProcessingFlag()) {
            return false;
        }

        if (($in_processing = $this->getFieldValue('in_processing')) === false) {
            static::raiseError(__CLASS__ .'::getFieldValue() returned false!');
            return false;
        }

        if (!static::isEnabled($in_processing)) {
            return false;
        }

        return true;
    }

    /**
     * sets the messages value field.
     *
     * @param string $value
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function setValue($value)
    {
        if (!isset($value) || empty($value) || !is_string($value)) {
            static::raiseError(__METHOD__ .'(), first parameter \$value has to be a string!');
            return false;
        }

        if (!$this->setFieldValue('value', $value)) {
            static::raiseError(__CLASS__ .'::setFieldValue() returned false!');
            return false;
        }

        return true;
    }

    /**
     * returns the value of the value field.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getValue()
    {
        if (!$this->hasValue()) {
            static::raiseError(__CLASS__ .'::hasValue() returned false!');
            return false;
        }

        if (($value = $this->getFieldValue('value')) === false) {
            static::raiseError(__CLASS__ .'::getFieldValue() returned false!');
            return false;
        }

        return $value;
    }

    /**
     * returns true if there is a value in the value field.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function hasValue()
    {
        if (!$this->hasFieldValue('value')) {
            return false;
        }

        return true;
    }

    /**
     * during saving, clear the in_processing field
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
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
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
