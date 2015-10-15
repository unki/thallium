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

class MessageBusController extends DefaultController
{
    const EXPIRE_TIMEOUT = 300;
    private $suppressOutboundMessaging = false;

    public function __construct()
    {
        global $session;

        if (!$session) {
            $this->raiseError(__METHOD__ ." requires SessionController to be initialized!", true);
            return false;
        }

        if (!$this->removeExpiredMessages()) {
            $this->raiseError('removeExpiredMessages() returned false!', true);
            return false;
        }

        return true;
    }

    public function submit($messages_raw)
    {
        global $session;

        if (!($sessionid = $session->getSessionId())) {
            $this->raiseError(get_class($session) .'::getSessionId() returned false!');
            return false;
        }

        if (empty($messages_raw)) {
            $this->raiseError(__METHOD__ .', first parameter can not be empty!');
            return false;
        }

        if (!is_string($messages_raw)) {
            $this->raiseError(__METHOD__ .', first parameter has to be a string!');
            return false;
        }

        if (!($json = json_decode($messages_raw))) {
            $this->raiseError('json_decode() returned false!');
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
            $this->raiseError(__METHOD__ .', submitted message object is incomplete!');
            return false;
        }

        if (strlen($json->json) != $json->size) {
            $this->raiseError(__METHOD__ .', verification failed - size differs!');
            return false;
        }

        if (sha1($json->json) != $json->hash) {
            $this->raiseError(__METHOD__ .', verification failed - hash differs!');
            return false;
        }

        if (!($messages = json_decode($json->json))) {
            $this->raiseError('json_decode() returned false!');
            return false;
        }

        foreach ($messages as $message) {
            if (!is_object($message)) {
                $this->raiseError(__METHOD__ .', \$message is not an object!');
                return false;
            }

            if (!isset($message->command) || empty($message->command)) {
                $this->raiseError(__METHOD__ .', \$message does not contain a command!');
                return false;
            }

            try {
                $mbmsg = new Models\MessageModel;
            } catch (\Exception $e) {
                $this->raiseError('Failed to load MessageModel!');
                return false;
            }

            if (!$mbmsg->setCommand($message->command)) {
                $this->raiseError(get_class($mbmsg) .'::setCommand() returned false!');
                return false;
            }

            if (!$mbmsg->setSessionId($sessionid)) {
                $this->raiseError(get_class($mbmsg) .'::setSessionId() returned false!');
                return false;
            }

            $mbmsg->setProcessingFlag(false);

            if (isset($message->message) && !empty($message->message)) {
                if (is_object($message->message) || is_array($message->message)) {
                    $msgbody = serialize($message->message);
                } else {
                    $msgbody = $message->message;
                }

                if (!$mbmsg->setMessage($msgbody)) {
                    $this->raiseError(get_class($mbmsg) .'::setMessage() returned false!');
                    return false;
                }
            }

            if (!$mbmsg->setScope('inbound')) {
                $this->raiseError(get_class($mbmsg) .'::setScope() returned false!');
                return false;
            }

            if (!$mbmsg->save()) {
                $this->raiseError(get_class($mbmsg) .'::save() returned false!');
                return false;
            }
        }

        return true;
    }

    public function poll()
    {
        global $session;

        $messages = array();

        try {
            $msgs = new Models\MessageBusModel;
        } catch (\Exception $e) {
            $this->raiseError('Failed to load MessageBusModel!');
            return false;
        }

        if (!($sessionid = $session->getSessionId())) {
            $this->raiseError(get_class($session) .'::getSessionId() returned false!');
            return false;
        }

        if (($messages = $msgs->getMessagesForSession($sessionid)) === false) {
            $this->raiseError(get_class($msgs) .'::getMessagesForSession() returned false!');
            return false;
        }

        $raw_messages = array();
        foreach ($messages as $message) {
            $raw_messages[] = array(
                'id' => $message->getId(),
                'guid' => $message->getGuid(),
                'command' => $message->getCommand(),
                'body' => $message->getBody(),
                'value' => $message->getValue()
            );

            if (!$message->delete()) {
                $this->raiseError(get_class($message) .'::delete() returned false!');
                return false;
            }
        }

        if (!($json = json_encode($raw_messages))) {
            $this->raiseError('json_encode() returned false!');
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

        if (!($reply = json_encode($reply_raw))) {
            $this->raiseError('json_encode() returned false!');
            return false;
        }

        return $reply;
    }

    public function getRequestMessages()
    {
        try {
            $msgs = new Models\MessageBusModel;
        } catch (\Exception $e) {
            $this->raiseError('Failed to load MessageBusModel!');
            return false;
        }

        if (($messages = $msgs->getServerRequests()) === false) {
            $this->raiseError(get_class($msgs) .'::getServerRequests() returned false!');
            return false;
        }

        if (!is_array($messages)) {
            $this->raiseError(get_class($msgs) .'::getServerRequests() has not returned an arary!');
            return false;
        }

        return $messages;
    }

    private function removeExpiredMessages()
    {
        try {
            $msgs = new Models\MessageBusModel;
        } catch (\Exception $e) {
            $this->raiseError('Failed to load MessageBusModel!');
            return false;
        }

        if (!$msgs->deleteExpiredMessages(self::EXPIRE_TIMEOUT)) {
            $this->raiseError(get_class($msgs) .'::deleteExpiredMessages() returned false!');
            return false;
        }

        return true;
    }

    public function sendMessageToClient($command, $body, $value, $sessionid = null)
    {
        global $jobs;

        if ($this->isSuppressOutboundMessaging()) {
            return true;
        }

        if (!isset($command) || empty($command) || !is_string($command)) {
            $this->raiseError(__METHOD__ .', parameter \$command is mandatory and has to be a string!');
            return false;
        }
        if (!isset($body) || empty($body) || !is_string($body)) {
            $this->raiseError(__METHOD__ .', parameter \$body is mandatory and has to be a string!');
            return false;
        }

        if (isset($value) && !empty($value) && !is_string($value)) {
            $this->raiseError(__METHOD__ .', parameter \$value has to be a string!');
            return false;
        }

        if (empty($sessionid) && !($sessionid = $this->getSessionIdFromJob())) {
            $this->raiseError(__METHOD__ .', no session id returnd by getSessionIdFromJob()!');
            return false;
        }

        if (!isset($sessionid) || empty($sessionid) || !is_string($sessionid)) {
            $this->raiseError(__METHOD__ .', the specified \$sessionid is invalid!');
            return false;
        }

        try {
            $msg = new Models\MessageModel;
        } catch (\Exception $e) {
            $this->raiseError(__METHOD__ .', failed to load MessageModel!');
            return false;
        }

        if (!$msg->setCommand($command)) {
            $this->raiseError(get_class($msg) .'::setCommand() returned false!');
            return false;
        }

        if (!$msg->setBody($body)) {
            $this->raiseError(get_class($msg) .'::setBody() returned false!');
            return false;
        }

        if (!$msg->setValue($value)) {
            $this->raiseError(get_class($msg) .'::setValue() returned false!');
            return false;
        }

        if (!$msg->setSessionId($sessionid)) {
            $this->raiseError(get_class($msg) .'::setSessionId() returned false!');
            return false;
        }

        if (!$msg->setScope('outbound')) {
            $this->raiseError(get_class($msg) .'::setScope() returned false!');
            return false;
        }

        if (!$msg->save()) {
            $this->raiseError(get_class($msg) .'::save() returned false!');
            return false;
        }

        return true;
    }

    private function getSessionIdFromJob($job_guid = null)
    {
        global $thallium, $jobs;

        if (empty($job_guid) && !($job_guid = $jobs->getCurrentJob())) {
            $this->raiseError(get_class($jobs) .'::getCurrentJob() returned false!');
            return false;
        }

        if (!$thallium->isValidGuidSyntax($job_guid)) {
            $this->raiseError(__METHOD__ .', \$job_guid is not a valid GUID!');
            return false;
        }

        try {
            $job = new Models\JobModel(null, $job_guid);
        } catch (\Exception $e) {
            $this->raiseError(__METHOD__ .', failed to load JobModel(null, {$job})!');
            return false;
        }

        if (!($sessionid = $job->getSessionId())) {
            $this->raiseError(get_class($message) .'::getSessionId() returned false!');
            return false;
        }

        return $sessionid;
    }

    public function isSuppressOutboundMessaging()
    {
        if (empty($this->suppressOutboundMessaging)) {
            return false;
        }

        return true;
    }

    public function suppressOutboundMessaging($state)
    {
        if (!is_bool($state)) {
            $this->raiseError(__METHOD__ .', parameter need to be boolean!');
            return false;
        }

        $this->suppressOutboundMessaging = $state;
        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
