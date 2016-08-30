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
 * MessageBusController manages receives and sends messages to
 * clients.
 *
 * @package Thallium\Controllers\MessageBusController
 * @subpackage Controllers
 * @license AGPL3
 * @copyright 2015-2016 Andreas Unterkircher <unki@netshadow.net>
 * @author Andreas Unterkircher <unki@netshadow.net>
 */
class MessageBusController extends DefaultController
{
    /** @var int EXPIRE_TIMEOUT how long a message is considered valid before it is expired */
    const EXPIRE_TIMEOUT = 300;

    /** @var bool $suppressOutboundMessaging allows to temporary suppress all outbound messages */
    protected $suppressOutboundMessaging = false;

    /** @var array $json_errors */
    protected $json_errors = array();

    /**
     * class constructor
     *
     * @param none
     * @return void
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function __construct()
    {
        global $session;

        if (!isset($session) ||
            empty($session) ||
            !is_object($session) ||
            !is_a($session, 'Thallium\Controllers\SessionController')
        ) {
            static::raiseError(__METHOD__ ." requires SessionController to be initialized!", true);
            return;
        }

        if (!$this->removeExpiredMessages()) {
            static::raiseError(__CLASS__ .'::removeExpiredMessages() returned false!', true);
            return;
        }

        // Define the JSON errors.
        $constants = get_defined_constants(true);

        foreach ($constants["json"] as $name => $value) {
            if (!strncmp($name, "JSON_ERROR_", 11)) {
                $this->json_errors[$value] = $name;
            }
        }

        return;
    }

    /**
     * submit one or more messages to the message bus.
     * inbound messages in a JSON-formated string.
     *
     * @param string $messages_raw
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function submit($messages_raw)
    {
        global $session;

        if (($sessionid = $session->getSessionId()) == false) {
            static::raiseError(get_class($session) .'::getSessionId() returned false!');
            return false;
        }

        if (!isset($messages_raw) || empty($messages_raw) || !is_string($messages_raw)) {
            static::raiseError(__METHOD__ .'(), $messages_raw parameter is invalid!');
            return false;
        }

        // In HttpRouterController htmlentites() was used to sanitize POST data ('messages').
        // Before we can pass this data to json_decode(), we have to undo the changes
        // htmlentites() has made.
        try {
            $messages_raw = html_entity_decode($messages_raw);
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'8), html_entity_decode() failed!', false, $e);
            return false;
        }

        if (!isset($messages_raw) || empty($messages_raw) || !is_string($messages_raw)) {
            static::raiseError(__METHOD__ .'(), $messages_raw parameter is invalid!');
            return false;
        }

        if (($json = json_decode($messages_raw, false, 2)) === null) {
            static::raiseError(__METHOD__ .'(), json_decode() returned false! '. $this->json_errors[json_last_error()]);
            return false;
        }

        if (empty($json)) {
            return true;
        }

        if (!isset($json->count) || empty($json->count) ||
            !isset($json->size) || empty($json->size) ||
            !isset($json->hash) || empty($json->hash) ||
            !isset($json->json) || empty($json->json)
        ) {
            static::raiseError(__METHOD__ .'(), submitted message object is incomplete!');
            return false;
        }

        if (strlen($json->json) != $json->size) {
            static::raiseError(__METHOD__ .'(), verification failed - size differs!');
            return false;
        }

        if (sha1($json->json) != $json->hash) {
            static::raiseError(__METHOD__ .'(), verification failed - hash differs!');
            return false;
        }

        if (($messages = json_decode($json->json, false, 10)) === null) {
            static::raiseError(__METHOD__ .'(), json_decode() returned false! '. $this->json_errors[json_last_error()]);
            return false;
        }

        foreach ($messages as $message) {
            if (!is_object($message)) {
                static::raiseError(__METHOD__ .'(), $message is not an object!');
                return false;
            }

            if (!isset($message->command) || empty($message->command)) {
                static::raiseError(__METHOD__ .'(), $message does not contain a command!');
                return false;
            }

            try {
                $mbmsg = new \Thallium\Models\MessageModel;
            } catch (\Exception $e) {
                static::raiseError(__METHOD__ .'(), failed to load MessageModel!', false, $e);
                return false;
            }

            if (!$mbmsg->setCommand($message->command)) {
                static::raiseError(get_class($mbmsg) .'::setCommand() returned false!');
                return false;
            }

            if (!$mbmsg->setSessionId($sessionid)) {
                static::raiseError(get_class($mbmsg) .'::setSessionId() returned false!');
                return false;
            }

            if (!$mbmsg->setProcessingFlag(false)) {
                static::raiseError(get_class($mbmsg) .'::setProcessingFlag() returned false!');
                return false;
            }

            if (isset($message->message) && !empty($message->message)) {
                if (!$mbmsg->setBody($message->message)) {
                    static::raiseError(get_class($mbmsg) .'::setBody() returned false!');
                    return false;
                }
            }

            if (!$mbmsg->setScope('inbound')) {
                static::raiseError(get_class($mbmsg) .'::setScope() returned false!');
                return false;
            }

            if (!$mbmsg->save()) {
                static::raiseError(get_class($mbmsg) .'::save() returned false!');
                return false;
            }
        }

        return true;
    }

    /**
     * this method is called when a client polls the message bus for pending
     * messages. they are returned as JSON-encoded strings.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function poll()
    {
        global $session;

        $messages = array();

        try {
            $msgs = new \Thallium\Models\MessageBusModel;
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), failed to load MessageBusModel!', false, $e);
            return false;
        }

        if (($sessionid = $session->getSessionId()) === false) {
            static::raiseError(get_class($session) .'::getSessionId() returned false!');
            return false;
        }

        if (($messages = $msgs->getMessagesForSession($sessionid)) === false) {
            static::raiseError(get_class($msgs) .'::getMessagesForSession() returned false!');
            return false;
        }

        $raw_messages = array();

        foreach ($messages as $message) {
            if (!$message->hasIdx() || ($msg_idx = $message->getIdx()) === false) {
                static::raiseError(__CLASS__ .'::getIdx() returned false!');
                return false;
            }

            if (!$message->hasGuid() || ($msg_guid = $message->getGuid()) === false) {
                static::raiseError(__CLASS__ .'::getGuid() returned false!');
                return false;
            }

            if (!$message->hasCommand() || ($msg_cmd = $message->getCommand()) === false) {
                static::raiseError(__CLASS__ .'::getCommand() returned false!');
                return false;
            }

            if (!$message->hasBody() || ($msg_body = $message->getBody()) === false) {
                static::raiseError(__CLASS__ .'::getBody() returned false!');
                return false;
            }

            if (!$message->hasValue() || ($msg_value = $message->getValue()) === false) {
                static::raiseError(__CLASS__ .'::getValue() returned false!');
                return false;
            }

            $raw_messages[] = array(
                'id' => $msg_idx,
                'guid' => $msg_guid,
                'command' => $msg_cmd,
                'body' => $msg_body,
                'value' => $msg_value,
            );

            if (!$message->delete()) {
                static::raiseError(get_class($message) .'::delete() returned false!');
                return false;
            }
        }

        if (($json = json_encode($raw_messages)) === false) {
            static::raiseError(__METHOD__ .'(), json_encode() returned false!');
            return false;
        }

        $len = count($raw_messages);
        $size = strlen($json);
        $hash = sha1($json);

        $reply_raw = array(
            'count' => $len,
            'size' => $size,
            'hash' => $hash,
            'json' => $json
        );

        if (($reply = json_encode($reply_raw)) === false) {
            static::raiseError(__METHOD__ .'(), json_encode() returned false!');
            return false;
        }

        return $reply;
    }

    /**
     * retrieve messages from message bus with type request.
     *
     * @param none
     * @return array|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function getRequestMessages()
    {
        try {
            $msgs = new \Thallium\Models\MessageBusModel;
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), Failed to load MessageBusModel!', false, $e);
            return false;
        }

        if (($messages = $msgs->getServerRequests()) === false) {
            static::raiseError(get_class($msgs) .'::getServerRequests() returned false!');
            return false;
        }

        if (!is_array($messages)) {
            static::raiseError(get_class($msgs) .'::getServerRequests() has not returned an arary!');
            return false;
        }

        return $messages;
    }

    /**
     * purges all expired messages from the message bus.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    protected function removeExpiredMessages()
    {
        try {
            $msgs = new \Thallium\Models\MessageBusModel;
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), failed to load MessageBusModel!', false, $e);
            return false;
        }

        if (!$msgs->deleteExpiredMessages(static::EXPIRE_TIMEOUT)) {
            static::raiseError(get_class($msgs) .'::deleteExpiredMessages() returned false!');
            return false;
        }

        return true;
    }

    /**
     * submit a message to the message bus that has to be sent to a client.
     *
     * @param string $command
     * @param string $body
     * @param string $value
     * @param string|null $sessionid
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function sendMessageToClient($command, $body, $value, $sessionid = null)
    {
        if ($this->isSuppressOutboundMessaging()) {
            return true;
        }

        if (!isset($command) || empty($command) || !is_string($command)) {
            static::raiseError(__METHOD__ .'(), $command parameter is invalid!');
            return false;
        }

        if (!isset($body) || empty($body) || !is_string($body)) {
            static::raiseError(__METHOD__ .'(), $body parameter is invalid!');
            return false;
        }

        if (isset($value) && !empty($value) && !is_string($value)) {
            static::raiseError(__METHOD__ .'(), $value parameter is invalid!');
            return false;
        }

        if (empty($sessionid) && ($sessionid = $this->getSessionIdFromJob()) === false) {
            static::raiseError(__CLASS__ .'::getSessionIdFromJob() returned false!');
            return false;
        }

        if (!isset($sessionid) || empty($sessionid) || !is_string($sessionid)) {
            static::raiseError(__METHOD__ .'(), the specified $sessionid is invalid!');
            return false;
        }

        try {
            $msg = new \Thallium\Models\MessageModel;
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), failed to load MessageModel!', false, $e);
            return false;
        }

        if (!$msg->setCommand($command)) {
            static::raiseError(get_class($msg) .'::setCommand() returned false!');
            return false;
        }

        if (!$msg->setBody($body)) {
            static::raiseError(get_class($msg) .'::setBody() returned false!');
            return false;
        }

        if (!$msg->setValue($value)) {
            static::raiseError(get_class($msg) .'::setValue() returned false!');
            return false;
        }

        if (!$msg->setSessionId($sessionid)) {
            static::raiseError(get_class($msg) .'::setSessionId() returned false!');
            return false;
        }

        if (!$msg->setScope('outbound')) {
            static::raiseError(get_class($msg) .'::setScope() returned false!');
            return false;
        }

        if (!$msg->save()) {
            static::raiseError(get_class($msg) .'::save() returned false!');
            return false;
        }

        return true;
    }

    /**
     * return the session-id that is bound to a specific job.
     *
     * @param string|null $job_guid
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    protected function getSessionIdFromJob($job_guid = null)
    {
        global $thallium, $jobs;

        if (!isset($job_guid) || empty($job_guid)) {
            if (($job_guid = $jobs->getCurrentJob()) === false) {
                static::raiseError(get_class($jobs) .'::getCurrentJob() returned false!');
                return false;
            }

            if (!isset($job_guid) || empty($job_guid)) {
                static::raiseError(__METHOD__ .'(), no job found to work on!');
                return false;
            }
        }

        if (!$thallium->isValidGuidSyntax($job_guid)) {
            static::raiseError(get_class($thallium) .'::isValidGuidSyntax() returned false!');
            return false;
        }

        try {
            $job = new \Thallium\Models\JobModel(array(
                FIELD_GUID => $job_guid
            ));
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), failed to load JobModel!', false, $e);
            return false;
        }

        if (($sessionid = $job->getSessionId()) === false) {
            static::raiseError(get_class($job) .'::getSessionId() returned false!');
            return false;
        }

        return $sessionid;
    }

    /**
     * returns true if outbound messaging is temporary disabled.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function isSuppressOutboundMessaging()
    {
        if (!isset($this->suppressOutboundMessaging) ||
            empty($this->suppressOutboundMessaging) ||
            !is_bool($this->suppressOutboundMessaging) ||
            $this->suppressOutboundMessaging === false
        ) {
            return false;
        }

        return true;
    }

    /**
     * enable or disable outbound messaging
     *
     * @param bool $state
     * @return bool the state before
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function suppressOutboundMessaging($state)
    {
        if (!isset($state) || !is_bool($state)) {
            static::raiseError(__METHOD__ .'(), $state parameter is invalid!');
            return false;
        }

        $state_before = $this->suppressOutboundMessaging;
        $this->suppressOutboundMessaging = $state;

        return $state_before;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
