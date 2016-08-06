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
 * Represents a single audit log entry.
 *
 * @package Thallium\Models\AuditEntryModel
 * @subpackage Models
 * @license AGPL3
 * @copyright 2015-2016 Andreas Unterkircher <unki@netshadow.net>
 * @author Andreas Unterkircher <unki@netshadow.net>
 */
class AuditEntryModel extends DefaultModel
{
    /** @var string $model_table_name */
    protected static $model_table_name = 'audit';

    /** @var string $model_column_prefix */
    protected static $model_column_prefix = 'audit';

    /** @var array $model_fields */
    protected static $model_fields = array(
        'idx' => array(
            FIELD_TYPE => FIELD_INT,
        ),
        'guid' => array(
            FIELD_TYPE => FIELD_STRING,
        ),
        'type' => array(
            FIELD_TYPE => FIELD_STRING,
        ),
        'scene' => array(
            FIELD_TYPE => FIELD_STRING,
        ),
        'message' => array(
            FIELD_TYPE => FIELD_STRING,
        ),
        'time' => array(
            FIELD_TYPE => FIELD_TIMESTAMP,
        ),
        'object_guid' => array(
            FIELD_TYPE => FIELD_STRING,
        ),
    );

    /** @var int MAX_MSG_LENGTH */
    const MAX_MSG_LENGTH = 8192;

    /**
     * automatically set the time-field to the current time on saving.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function preSave()
    {
        if (($time = microtime(true)) === false) {
            static::raiseError(__METHOD__ .'microtime() returned false!');
            return false;
        }

        if (!$this->setFieldValue('time', $time)) {
            static::raiseError(__CLASS__ .'::setFieldValue() returned false!');
            return false;
        }

        return true;
    }

    /**
     * returns true, if the message field has a value set.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function hasMessage()
    {
        if (!$this->hasFieldValue('message')) {
            return false;
        }

        return true;
    }

    /**
     * returns the value of the message field.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getMessage()
    {
        if (!$this->hasMessage()) {
            static::raiseError(__CLASS__ .'::hasMessage() returned false!');
            return false;
        }

        if (($message = $this->getFieldValue('message')) === false) {
            static::raiseError(__CLASS__ .'::getFieldValue() returned false!');
            return false;
        }

        if (strlen($message) > static::MAX_MSG_LENGTH) {
            static::raiseError(__METHOD__ .'(), returned message is too long!');
            return false;
        }

        return $message;
    }

    /**
     * stores a value into the message field.
     *
     * @param string $message
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function setMessage($message)
    {
        if (!isset($message) || empty($message) || !is_string($message)) {
            static::raiseError(__METHOD__ .'(), $message parameter is invalid!');
            return false;
        }

        if (strlen($message) > static::MAX_MSG_LENGTH) {
            static::raiseError(__METHOD__ .'(), $message is too long!');
            return false;
        }

        if (!$this->setFieldValue('message', $message)) {
            static::raiseError(__CLASS__ .'::setFieldValue() returned false!');
            return false;
        }

        return true;
    }

    /**
     * returns true, if the type field has a value set.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function hasEntryType()
    {
        if (!$this->hasFieldValue('type')) {
            return false;
        }

        return true;
    }

    /**
     * returns the value of the type field.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getEntryType()
    {
        if (!$this->hasEntryType()) {
            static::raiseError(__CLASS__ .'::hasEntryType() returned false!');
            return false;
        }

        if (($type = $this->getFieldValue('type')) === false) {
            static::raiseError(__CLASS__ .'::getFieldValue() returned false!');
            return false;
        }

        return $type;
    }

    /**
     * stores a value into the type field.
     *
     * @param string $entry_type
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function setEntryType($entry_type)
    {
        if (!isset($entry_type) || empty($entry_type) || !is_string($entry_type)) {
            static::raiseError(__METHOD__ .'(), $entry_type parameter is invalid!');
            return false;
        }

        if (strlen($entry_type) > 255) {
            static::raiseError(__METHOD__ .'(), $entry_type is tooo long!');
            return false;
        }

        if (!$this->setFieldValue('type', $entry_type)) {
            static::raiseError(__CLASS__ .'::setFieldValue() returned false!');
            return false;
        }

        return true;
    }

    /**
     * returns true, if the scene field has a value set.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function hasScene()
    {
        if (!$this->hasFieldValue('scene')) {
            return false;
        }

        return true;
    }

    /**
     * returns the value of the scene field.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getScene()
    {
        if (!$this->hasScene()) {
            static::raiseError(__CLASS__ .'::hasScene() returned false!');
            return false;
        }

        if (($scene = $this->getFieldValue('scene')) === false) {
            static::raiseError(__CLASS__ .'::getFieldValue() returned false!');
            return false;
        }

        return $scene;
    }

    /**
     * stores a value into the scene field.
     *
     * @param string $scene
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function setScene($scene)
    {
        if (!isset($scene) || empty($scene) || !is_string($scene)) {
            static::raiseError(__METHOD__ .'(), $scene parameter is invalid!');
            return false;
        }

        if (strlen($scene) > 255) {
            return false;
        }

        if (!$this->setFieldValue('scene', $scene)) {
            static::raiseError(__CLASS__ .'::setFieldValue() returned false!');
            return false;
        }

        return true;
    }

    /**
     * returns true, if the object_guid field has a value set.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function hasEntryGuid()
    {
        if (!$this->hasFieldValue('object_guid')) {
            return false;
        }

        return true;
    }

    /**
     * returns the value of the object_guid field.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getEntryGuid()
    {
        if (!$this->hasEntryGuid()) {
            static::raiseError(__CLASS__ .'::hasEntryGuid() returned false!');
            return false;
        }

        if (($guid = $this->getFieldValue('object_guid')) === false) {
            static::raiseError(__CLASS__ .'::getFieldValue() returned false!');
            return false;
        }

        return $guid;
    }

    /**
     * stores a value into the object_guid field.
     *
     * @param string $message
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function setEntryGuid($guid)
    {
        if (!isset($guid) || empty($guid) || !is_string($guid)) {
            static::raiseError(__METHOD__ .'(), $guid parameter is invalid!');
            return false;
        }

        if (strlen($guid) > 255) {
            static::raiseError(__METHOD__ .'(), $guid is tooo long!');
            return false;
        }

        if (!$this->setFieldValue('object_guid', $guid)) {
            static::raiseError(__CLASS__ .'::setFieldValue() returned false!');
            return false;
        }

        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
