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

class AuditLogModel extends DefaultModel
{
    protected $table_name = 'audit';
    protected $column_name = 'audit';
    protected $fields = array(
        'audit_log' => 'array',
    );

    public function __construct($guid = null)
    {
        global $db;

        // are we creating a new item?
        if (!isset($guid) || empty($guid)) {
            $this->raiseError('$guid parameter is missing!', true);
            return false;
        }

        $this->audit_log = array();

        // get $id from db
        $sql = "
            SELECT
                *
            FROM
                TABLEPREFIX{$this->table_name}
            WHERE
                audit_guid
            LIKE
                ?
        ";

        if (!($sth = $db->prepare($sql))) {
            $this->raiseError("DatabaseController::prepare() returned false!");
            return false;
        }

        if (!$db->execute($sth, array($guid))) {
            $this->raiseError("DatabaseController::execute() returned false!");
            return false;
        }

        while ($row = $sth->fetch()) {
            $this->audit_log[] = $row->audit_message;
        }

        $db->freeStatement($sth);
        return true;
    }

    public function getLog()
    {
        return $this->audit_log;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
