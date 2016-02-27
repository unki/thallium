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

class MessageBusModel extends DefaultModel
{
    protected $table_name = 'message_bus';
    protected $column_name = 'msg';
    protected $fields = array(
            'msg_idx' => 'integer',
            );
    protected $avail_items = array();
    protected $items = array();

    public function getMessagesForSession($session_id)
    {
        global $db;

        $messages = array();

        if (empty($session_id)) {
            $this->raiseError(__METHOD__ .', \$session_id can not be empty!');
            return false;
        }

        $idx_field = $this->column_name ."_idx";

        $sql =
            "SELECT
                msg_idx,
                msg_guid
            FROM
                TABLEPREFIX{$this->table_name}
            WHERE
                msg_scope
            LIKE
                'outbound'
            AND
                msg_session_id
            LIKE
                ?
            ORDER BY
                msg_submit_time ASC";

        if (!($sth = $db->prepare($sql))) {
            $this->raiseError(__METHOD__ .', failed to prepare query!');
            return false;
        };

        if (!$db->execute($sth, array($session_id))) {
            $db->freeStatement($sth);
            $this->raiseError(__METHOD__ .', failed to execute query!');
            return false;
        }

        while ($row = $sth->fetch()) {
            if (!isset($row->msg_idx) || empty($row->msg_idx) ||
                !isset($row->msg_guid) || empty($row->msg_guid)
            ) {
                $db->freeStatement($sth);
                $this->raiseError(__METHOD__ .', message returned from query is incomplete!');
            }

            try {
                $message = new MessageModel($row->msg_idx, $row->msg_guid);
            } catch (\Exception $e) {
                $db->freeStatement($sth);
                $this->raiseError('Failed to load MessageModel!');
                return false;
            }

            array_push($messages, $message);
        }

        $db->freeStatement($sth);
        return $messages;
    }

    public function getServerRequests()
    {
        global $db;

        $messages = array();

        $idx_field = $this->column_name ."_idx";

        $sql =
            "SELECT
                msg_idx,
                msg_guid
            FROM
                TABLEPREFIX{$this->table_name}
            WHERE
                msg_scope
            LIKE
                'inbound'
            AND
                msg_in_processing <> 'Y'";

        if (!($result = $db->query($sql))) {
            $this->raiseError(__METHOD__ .', failed to query database!');
            return false;
        };

        while ($row = $result->fetch()) {
            if (!isset($row->msg_idx) || empty($row->msg_idx) ||
                !isset($row->msg_guid) || empty($row->msg_guid)
            ) {
                $db->freeStatement($sth);
                $this->raiseError(__METHOD__ .', message returned from query is incomplete!');
            }

            try {
                $message = new MessageModel($row->msg_idx, $row->msg_guid);
            } catch (\Exception $e) {
                $this->raiseError('Failed to load MessageModel!');
                return false;
            }

            array_push($messages, $message);
        }

        return $messages;
    }

    public function deleteExpiredMessages($timeout)
    {
        global $db;

        if (!isset($timeout) || empty($timeout) || !is_numeric($timeout)) {
            $this->raiseError(__METHOD__ .', parameter needs to be an integer!');
            return false;
        }

        $now = microtime(true);
        $oldest = $now-$timeout;

        $sql =
            "DELETE FROM
                TABLEPREFIXmessage_bus
            WHERE
                UNIX_TIMESTAMP(msg_submit_time) < ?";

        if (!($sth = $db->prepare($sql))) {
            $this->raiseError(__METHOD__ .', failed to prepare query!');
            return false;
        }

        if (!($db->execute($sth, array($oldest)))) {
            $this->raiseError(__METHOD__ .', failed to execute query!');
            return false;
        }

        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
