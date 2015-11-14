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

class JobsModel extends DefaultModel
{
    public $table_name = 'jobs';
    public $column_name = 'job';
    public $fields = array(
            'queue_idx' => 'integer',
            );
    public $avail_items = array();
    public $items = array();

    public function deleteExpiredJobs($timeout)
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
                TABLEPREFIXjobs
            WHERE
                job_time < ?";

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
