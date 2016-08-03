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
 * MessageBusModel represents the internal message queue.
 * Messages are the represented by MessageModels.
 *
 * @package Thallium\Models\MessageBusModel
 * @subpackage Models
 * @license AGPL3
 * @copyright 2015-2016 Andreas Unterkircher <unki@netshadow.net>
 * @author Andreas Unterkircher <unki@netshadow.net>
 */
class MessageBusModel extends DefaultModel
{
    /** @var string $model_table_name */
    protected static $model_table_name = 'message_bus';

    /** @var string $model_column_prefix */
    protected static $model_column_prefix = 'msg';

    /** @var bool $model_has_items */
    protected static $model_has_items = true;

    /** @var string $model_items_model */
    protected static $model_items_model = 'MessageModel';

    /**
     * get messages associated with a specific session id
     *
     * @param string $session_id
     * @return array|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getMessagesForSession($session_id)
    {
        global $db;

        if (!isset($session_id) || empty($session_id) || !is_string($session_id)) {
            static::raiseError(__METHOD__ .'(), $session_id parameter is invalid!');
            return false;
        }

        $messages = array();

        $sql = sprintf(
            "SELECT
                msg_idx,
                msg_guid
            FROM
                TABLEPREFIX%s
            WHERE
                msg_scope
            LIKE
                'outbound'
            AND
                msg_session_id
            LIKE
                ?
            ORDER BY
                msg_submit_time ASC",
            static::$model_table_name
        );

        try {
            $sth = $db->prepare($sql);
        } catch (\PDOException $e) {
            static::raiseError(get_class($db) .'::prepare() failed!', false, $e);
            return false;
        } catch (\Exception $e) {
            static::raiseError(get_class($db) .'::prepare() failed!', false, $e);
            return false;
        };

        try {
            $db->execute($sth, array($session_id));
        } catch (\PDOException $e) {
            static::raiseError(get_class($db) .'::execute() failed!', false, $e);
            return false;
        } catch (\Exception $e) {
            static::raiseError(get_class($db) .'::execute() failed!', false, $e);
            return false;
        };
       
        while ($row = $sth->fetch()) {
            if (!isset($row->msg_idx) || empty($row->msg_idx) ||
                !isset($row->msg_guid) || empty($row->msg_guid)
            ) {
                $db->freeStatement($sth);
                static::raiseError(__METHOD__ .'(), message returned from query is incomplete!');
                return false;
            }

            try {
                $message = new \Thallium\Models\MessageModel(array(
                    'idx' => $row->msg_idx,
                    'guid' => $row->msg_guid
                ));
            } catch (\Exception $e) {
                $db->freeStatement($sth);
                static::raiseError(__METHOD__ .'(), failed to load MessageModel!', false, $e);
                return false;
            }

            array_push($messages, $message);
        }

        $db->freeStatement($sth);
        return $messages;
    }

    /**
     * get messages that are designated to the framework.
     *
     * @param none
     * @return array|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getServerRequests()
    {
        global $db;

        $messages = array();

        $sql = sprintf(
            "SELECT
                msg_idx,
                msg_guid
            FROM
                TABLEPREFIX%s
            WHERE
                msg_scope
            LIKE
                'inbound'
            AND
                msg_in_processing <> 'Y'",
            static::$model_table_name
        );

        try {
            $sth = $db->prepare($sql);
        } catch (\PDOException $e) {
            static::raiseError(get_class($db) .'::prepare() failed!', false, $e);
            return false;
        } catch (\Exception $e) {
            static::raiseError(get_class($db) .'::prepare() failed!', false, $e);
            return false;
        };

        try {
            $db->execute($sth);
        } catch (\PDOException $e) {
            static::raiseError(get_class($db) .'::execute() failed!', false, $e);
            return false;
        } catch (\Exception $e) {
            static::raiseError(get_class($db) .'::execute() failed!', false, $e);
            return false;
        };
 
        while ($row = $sth->fetch()) {
            if (!isset($row->msg_idx) || empty($row->msg_idx) ||
                !isset($row->msg_guid) || empty($row->msg_guid)
            ) {
                $db->freeStatement($sth);
                static::raiseError(__METHOD__ .'(), message returned from query is incomplete!');
                return false;
            }

            try {
                $message = new \Thallium\Models\MessageModel(array(
                    'idx' => $row->msg_idx,
                    'guid' => $row->msg_guid
                ));
            } catch (\Exception $e) {
                static::raiseError(__METHOD__ .'(), failed to load MessageModel!', false, $e);
                return false;
            }

            array_push($messages, $message);
        }

        return $messages;
    }

    /**
     * remove expired messages from message bus queue.
     *
     * @param int $timeout
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function deleteExpiredMessages($timeout)
    {
        global $db;

        if (!isset($timeout) || empty($timeout) || !is_numeric($timeout)) {
            static::raiseError(__METHOD__ .', parameter needs to be an integer!');
            return false;
        }

        $now = microtime(true);
        $oldest = $now-$timeout;

        $sql =
            "DELETE FROM
                TABLEPREFIXmessage_bus
            WHERE
                UNIX_TIMESTAMP(msg_submit_time) < ?";

        try {
            $sth = $db->prepare($sql);
        } catch (\PDOException $e) {
            static::raiseError(get_class($db) .'::prepare() failed!', false, $e);
            return false;
        } catch (\Exception $e) {
            static::raiseError(get_class($db) .'::prepare() failed!', false, $e);
            return false;
        };

        if (!isset($sth) ||
            empty($sth) ||
            !is_object($sth) ||
            !is_a($sth, 'PDOStatement')
        ) {
            static::raiseError(get_class($db) ."::prepare() returned invalid data!");
            return false;
        }

        try {
            $db->execute($sth, array($oldest));
        } catch (\PDOException $e) {
            static::raiseError(get_class($db) .'::execute() failed!', false, $e);
            return false;
        } catch (\Exception $e) {
            static::raiseError(get_class($db) .'::execute() failed!', false, $e);
            return false;
        };

        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
