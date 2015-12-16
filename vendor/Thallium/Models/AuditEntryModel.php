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

class AuditEntryModel extends DefaultModel
{
    public $table_name = 'audit';
    public $column_name = 'audit';
    public $fields = array(
            'audit_idx' => 'integer',
            'audit_guid' => 'string',
            'audit_type' => 'string',
            'audit_scene' => 'string',
            'audit_message' => 'string',
            'audit_time' => 'timestamp',
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
                audit_idx
            FROM
                TABLEPREFIX{$this->table_name}
            WHERE
        ";

        $arr_query = array();
        if (isset($id)) {
            $sql.= "
                audit_idx LIKE ?
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
                audit_guid LIKE ?
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
            $this->raiseError("Unable to find audit entry with guid value {$guid}");
            return false;
        }

        if (!isset($row->audit_idx) || empty($row->audit_idx)) {
            $this->raiseError("Unable to find audit entry with guid value {$guid}");
            return false;
        }

        $db->freeStatement($sth);

        parent::__construct($row->audit_idx);

        return true;
    }

    public function preSave()
    {
        if (!($time = microtime(true))) {
            $this->raiseError("microtime() returned false!");
            return false;
        }

        $this->audit_time = $time;

        return true;
    }

    public function setEntryGuid($guid)
    {
        global $thallium;

        if (empty($guid)) {
            return true;
        }

        if (!$thallium->isValidGuidSyntax($guid)) {
            $this->raiseError(get_class($thallium) .'::isValidGuidSyntax() returned false!');
            return false;
        }

        $this->audit_guid = $guid;
        return true;
    }

    public function setMessage($message)
    {
        if (empty($message)) {
            $this->raiseError(__METHOD__ .", \$message can not be empty!");
            return false;
        }
        if (!is_string($message)) {
            $this->raiseError(__METHOD__ .", \$message must be a string!");
            return false;
        }

        if (strlen($message) > 8192) {
            $this->raiseError(__METHOD__ .", \$message is to long!");
            return false;
        }

        $this->audit_message = $message;
        return true;
    }

    public function setEntryType($entry_type)
    {
        if (empty($entry_type)) {
            $this->raiseError(__METHOD__ .", \$entry_type can not be empty!");
            return false;
        }
        if (!is_string($entry_type)) {
            $this->raiseError(__METHOD__ .", \$entry_type must be a string!");
            return false;
        }

        if (strlen($entry_type) > 255) {
            $this->raiseError(__METHOD__ .", \$entry_type is to long!");
            return false;
        }

        $this->audit_type = $entry_type;
        return true;
    }

    public function setScene($scene)
    {
        if (empty($scene)) {
            $this->raiseError(__METHOD__ .", \$scene can not be empty!");
            return false;
        }
        if (!is_string($scene)) {
            $this->raiseError(__METHOD__ .", \$scene must be a string!");
            return false;
        }

        if (strlen($scene) > 255) {
            $this->raiseError(__METHOD__ .", \$scene is to long!");
            return false;
        }

        $this->audit_scene = $scene;
        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
