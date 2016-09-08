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

class SearchController extends DefaultController
{
    protected $result = array();

    public function search($objectofdesire)
    {
        if (!$this->validateInput($objectofdesire)) {
            static::raiseError(__CLASS__ .'::validateInput() returned false!');
            return false;
        }

        if (!$this->query($objectofdesire)) {
            static::raiseError(__CLASS__ .'::query() returned false!');
            return false;
        }

        return true;
    }

    protected function validateInput($input)
    {
        if (!isset($input) || !is_string($input)) {
            return false;
        }

        return true;
    }

    protected function query($query)
    {
        $query_ary = array(
            'data' => $query,
            'type' => 'string'
        );

        if (($time = $this->isDateString($query)) !== false) {
            $query_ary = array(
                'data' => $time,
                'type' => 'timestamp'
            );
        }

        if (preg_match('/^(devices|ippool|sites):(.+)$/', $query, $matches)) {
            $query_ary['filter'] = $matches[1];
            $query_ary['data'] = $matches[2];
        }

        if (!$this->queryModels($query_ary)) {
            static::raiseError(__CLASS__ .'::queryModels() returned false!');
            return false;
        }

        return true;
    }

    protected function queryModels($query)
    {
        global $thallium;

        if (!$this->isValidSearchQuery($query)) {
            static::raiseError(__CLASS__ .'::isValidSearchQuery() returned false!');
            return false;
        }

        if (!$thallium->hasRegisteredModels()) {
            static::raiseError(get_class($thallium) .'::hasRegisteredModels() returned false!');
            return false;
        }

        if (($known_models = $thallium->getRegisteredModels()) === false) {
            static::raiseError(get_class($thallium) .'::getRegisteredModels() returned false!');
            return false;
        }

        $selected_models = array();

        foreach ($known_models as $nick => $name) {
            if (($full_name = $thallium->getFullModelName($name)) === false) {
                static::raiseError(get_class($thallium) .'::getFullModelName() returned false!');
                return false;
            }

            if (!$full_name::hasModelItems()) {
                continue;
            }

            if (!$full_name::isSearchable()) {
                continue;
            }

            $selected_models[$nick] = $full_name;
        }

        $selected_fields = array();

        foreach ($selected_models as $nick => $full_name) {
            if (!$full_name::hasSearchableFields()) {
                continue;
            }

            if (($fields = $full_name::getSearchableFields()) === false) {
                static::raiseError(sprintf(
                    '%s::getSearchableFields() returned false!',
                    $full_name
                ));
                return false;
            }

            $selected_fields[$full_name] = $fields;
        }

        $results = array();

        foreach ($selected_fields as $model => $fields) {
            if (($result = $model::find($query, $fields)) === false) {
                static::raiseError(sprintf(
                    '%s::find() returned false!',
                    $model
                ));
                return false;
            }

            $results[$model] = $result;
        }

        $this->result = $results;
        return true;
    }

    public function hasResults()
    {
        if (!isset($this->result) || empty($this->result) || !is_array($this->result)) {
            return false;
        }

        return true;
    }

    public function getResults()
    {
        if (!$this->hasResults()) {
            static::raiseError(__CLASS__ .'::hasResults() returned false!');
            return false;
        }

        return $this->result;
    }

    protected function isDateString($string)
    {
        if (($time = strtotime($string)) === false) {
            return false;
        }

        if (!is_integer($time)) {
            return false;
        }

        return $time;
    }

    protected function isValidSearchQuery($query)
    {
        if (!isset($query) ||
            empty($query) ||
            !is_array($query)
        ) {
            return false;
        }

        if (!isset($query['data']) ||
            empty($query['data']) ||
            !isset($query['type']) ||
            empty($query['type'])
        ) {
            return false;
        }

        if ($query['type'] != 'string' &&
            $query['type'] != 'timestamp'
        ) {
            return false;
        }

        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
