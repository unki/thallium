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
 * This model can consist one or more JobModels and represents
 * the internal work queue.
 *
 * @package Thallium\Models\JobsModel
 * @subpackage Controllers
 * @license AGPL3
 * @copyright 2015-2016 Andreas Unterkircher <unki@netshadow.net>
 * @author Andreas Unterkircher <unki@netshadow.net>
 */
class JobsModel extends DefaultModel
{
    /** @var string $model_table_name */
    protected static $model_table_name = 'jobs';

    /** @var string $model_column_prefix */
    protected static $model_column_prefix = 'job';

    /** @var bool $model_has_items */
    protected static $model_has_items = true;

    /** @var string $model_items_model */
    protected static $model_items_model = 'JobModel';

    /**
     * removes expired jobs from queue
     *
     * @param int $timeout
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function deleteExpiredJobs($timeout)
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
                TABLEPREFIXjobs
            WHERE
                UNIX_TIMESTAMP(job_time) < ?";

        try {
            $sth = $db->prepare($sql);
        } catch (\PDOException $e) {
            static::raiseError(get_class($db) .'::prepare() failed!', false, $e);
            return false;
        } catch (\Exception $e) {
            static::raiseError(get_class($db) .'::prepare() failed!', false, $e);
            return false;
        }

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
        }

        return true;
    }

    /**
     * returns an array of pending jobs.
     *
     * @param none
     * @return array|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getPendingJobs()
    {
        global $db;

        $sql = sprintf(
            "SELECT
                job_idx
            FROM
                TABLEPREFIX%s
            WHERE
                job_in_processing <> 'Y'",
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
        }

        if (!isset($sth) ||
            empty($sth) ||
            !is_object($sth) ||
            !is_a($sth, 'PDOStatement')
        ) {
            static::raiseError(get_class($db) ."::prepare() returned invalid data!");
            return false;
        }

        try {
            $db->execute($sth);
        } catch (\PDOException $e) {
            static::raiseError(get_class($db) .'::execute() failed!', false, $e);
            return false;
        } catch (\Exception $e) {
            static::raiseError(get_class($db) .'::execute() failed!', false, $e);
            return false;
        }

        $jobs = array();

        if ($sth->rowCount() < 1) {
            return $jobs;
        }

        while ($row = $sth->fetch()) {
            try {
                $job = new \Thallium\Models\JobModel(array(
                    'idx' => $row->job_idx
                ));
            } catch (\Exception $e) {
                static::raiseError(__METHOD__ .'(), failed to load JobModel!', false, $e);
                return false;
            }
            array_push($jobs, $job);
        }

        return $jobs;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
