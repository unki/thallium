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

class AuditController extends DefaultController
{
    public function log($message, $entry_type, $scene, $guid = null)
    {
        try {
            $entry = new Models\AuditEntryModel;
        } catch (Exception $e) {
            $this->raiseError("Failed to load AuditEntryModel");
            return false;
        }

        if (!$entry->setMessage($message)) {
            $this->raiseError("AuditEntryModel::setMessage() returned false!");
            return false;
        }

        if (!empty($guid) && !$entry->setGuid($guid)) {
            $this->raiseError("AuditEntryModel::setGuid() returned false!");
            return false;
        }

        if (!$entry->setEntryType($entry_type)) {
            $this->raiseError("AuditEntryModel::setEntryType() returned false!");
            return false;
        }

        if (!$entry->setScene($scene)) {
            $this->raiseError("AuditEntryModel::setScene() returned false!");
            return false;
        }

        if (!$entry->save()) {
            $this->raiseError("AuditEntryModel::save() returned false!");
            return false;
        }

        return true;
    }

    public function retrieveAuditLog($guid)
    {
        global $thallium;

        if (empty($guid) || !$thallium->isValidGuidSyntax($guid)) {
            $this->raiseError(__METHOD__ .' requires a valid GUID as first parameter!');
            return false;
        }

        try {
            $log = new Models\AuditLogModel($guid);
        } catch (\Exception $e) {
            $this->raiseError("Failed to load AuditLogModel! ". $e->getMessage());
            return false;
        }

        if (!$entries = $log->getLog()) {
            $this->raiseError(get_class($log) .'::getLog() returned false!');
            return false;
        }

        if (!is_array($entries)) {
            $this->raiseError(__METHOD__ .' invalid audit log retrieved!');
            return false;
        }

        if (empty($entries)) {
            $entries = array('No audit log entries available!');
        }

        $txtlog = implode('\n', $entries);
        return $txtlog;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
