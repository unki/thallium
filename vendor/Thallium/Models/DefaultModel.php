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

namespace Thallium\Models;

use \PDO;

/**
 * DefaultModel is an abstract class that is used by all
 * the other Thallium Models.
 *
 * It declares some common methods, properties and constants.
 *
 * @package Thallium\Models\DefaultModel
 * @subpackage Models
 * @abstract
 * @license AGPL3
 * @copyright 2015-2016 Andreas Unterkircher <unki@netshadow.net>
 * @author Andreas Unterkircher <unki@netshadow.net>
 */
abstract class DefaultModel
{
    /** @var string $model_table_name */
    protected static $model_table_name;

    /** @var string $model_column_prefix */
    protected static $model_column_prefix;

    /** @var array $model_fields */
    protected static $model_fields = array();

    /** @var array $model_fields_index */
    protected static $model_fields_index = array();

    /** @var bool $model_has_items */
    protected static $model_has_items = false;

    /** @var string $model_items_model */
    protected static $model_items_model;

    /** @var array $model_links */
    protected static $model_links = array();

    /** @var bool $model_is_link_model */
    protected static $model_is_link_model = false;

    /** @var int $model_bulk_load_limit */
    protected static $model_bulk_load_limit = 10;

    /** @var string $model_friendly_name */
    protected static $model_friendly_name;

    /** @var bool $model_is_searchable */
    protected static $model_is_searchable = false;

    /** @var array $model_searchable_fields */
    protected static $model_searchable_fields = array();

    /** @var array $model_load_by */
    protected $model_load_by = array();

    /** @var array $model_sort_order */
    protected $model_sort_order = array();

    /** @var array $model_items */
    protected $model_items = array();

    /** @var array $model_items_lookup_index */
    protected $model_items_lookup_index = array();

    /** @var bool $model_permit_rpc_updates */
    protected $model_permit_rpc_updates = false;

    /** @var array $model_rpc_allowed_fields */
    protected $model_rpc_allowed_fields = array();

    /** @var array $model_rpc_allowed_actions */
    protected $model_rpc_allowed_actions = array();

    /** @var array $model_virtual_fields */
    protected $model_virtual_fields = array();

    /** @var array $model_init_values */
    protected $model_init_values = array();

    /** @var array $model_values */
    protected $model_values = array();

    /** @var int $id */
    protected $id;

    /** @var array $child_names */
    protected $child_names;

    /** @var bool $ignore_child_on_clone */
    protected $ignore_child_on_clone;

    /**
     * class constructor
     *
     * @param array|null $load_by
     * @param array|null $sort_order
     * @return void
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function __construct($load_by = array(), $sort_order = array())
    {
        if (!isset($load_by) || (
            !is_array($load_by) &&
            !is_null($load_by) &&
            !is_bool($load_by) && $load_by !== false)
        ) {
            static::raiseError(__METHOD__ .'(), $load_by parameter is invalid!', true);
            return;
        }

        if (!isset($sort_order) || (!is_array($sort_order) && !is_null($sort_order))) {
            static::raiseError(__METHOD__ .'(), $sort_order parameter is invalid!', true);
            return;
        }

        $this->model_load_by = $load_by;
        $this->model_sort_order = $sort_order;

        if (method_exists($this, '__init') && is_callable(array($this, '__init'))) {
            if (!$this->__init()) {
                static::raiseError(__METHOD__ .'(), __init() returned false!', true);
                return;
            }
        }

        if (!$this->validateModelSettings()) {
            static::raiseError(__CLASS__ .'::validateModelSettings() returned false!', true);
            return;
        }

        if (static::hasModelFields() && $this->isNewModel()) {
            if (!$this->initFields()) {
                static::raiseError(__CLASS__ .'::initFields() returned false!', true);
                return;
            }
            return;
        }

        if ($load_by === false) {
            return;
        }

        if (!$this->load()) {
            static::raiseError(__CLASS__ .'::load() returned false!', true);
            return;
        }

        return;
    }

    /**
     * validate model settings
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function validateModelSettings()
    {
        global $thallium;

        if (!isset(static::$model_table_name) ||
            empty(static::$model_table_name) ||
            !is_string(static::$model_table_name)
        ) {
            static::raiseError(__METHOD__ .'(), missing property "model_table_name"');
            return false;
        }

        if (!isset(static::$model_column_prefix) ||
            empty(static::$model_column_prefix) ||
            !is_string(static::$model_column_prefix)
        ) {
            static::raiseError(__METHOD__ .'(), missing property "model_column_prefix"');
            return false;
        }

        if (static::hasModelFields() && static::hasModelItems()) {
            static::raiseError(__METHOD__ .'(), model must no have fields and items at the same times!');
            return false;
        }

        if (!static::hasModelFields() && !static::hasModelItems()) {
            static::raiseError(__METHOD__ .'(), model is neither configured having fields nor items!');
            return false;
        }

        if (static::hasModelItems()) {
            if (!static::hasModelItemsModel()) {
                static::raiseError(__CLASS__ .'::hasModelItemsModel() returned false!');
                return false;
            }

            if (($items_model = static::getModelItemsModel()) === false) {
                static::raiseError(__CLASS__ .'::getModelItemsModel() returned false!');
                return false;
            }

            if (!$thallium->isRegisteredModel(null, $items_model)) {
                static::raiseError(get_class($thallium) .'::isRegisteredModel() returned false!');
                return false;
            }
        } elseif (static::hasModelFields()) {
            if (!isset(static::$model_fields) || !is_array(static::$model_fields)) {
                static::raiseError(__METHOD__ .'(), missing property "model_fields"');
                return false;
            }

            if (!empty(static::$model_fields)) {
                $known_field_types = array(
                    FIELD_TYPE,
                    FIELD_INT,
                    FIELD_STRING,
                    FIELD_BOOL,
                    FIELD_TIMESTAMP,
                    FIELD_YESNO,
                    FIELD_DATE,
                    FIELD_GUID,
                );

                foreach (static::$model_fields as $field => $params) {
                    if (!isset($field) ||
                        empty($field) ||
                        !is_string($field) ||
                        !preg_match('/^[a-zA-Z0-9_]+$/', $field)
                    ) {
                        static::raiseError(__METHOD__ .'(), invalid field entry (field name) found!');
                        return false;
                    }

                    if (!isset($params) || empty($params) || !is_array($params)) {
                        static::raiseError(__METHOD__ .'(), invalid field params found!');
                        return false;
                    }

                    if (!array_key_exists(FIELD_TYPE, $params) ||
                        !isset($params[FIELD_TYPE]) ||
                        empty($params[FIELD_TYPE]) ||
                        !is_string($params[FIELD_TYPE]) ||
                        !ctype_alnum($params[FIELD_TYPE])
                    ) {
                        static::raiseError(__METHOD__ .'(), invalid field type found!');
                        return false;
                    }

                    if (!in_array($params[FIELD_TYPE], $known_field_types)) {
                        static::raiseError(__METHOD__ .'(), unknown field type found!');
                        return false;
                    }

                    if (array_key_exists(FIELD_LENGTH, $params)) {
                        if (!is_int($params[FIELD_LENGTH])) {
                            static::raiseError(__METHOD__ ."(), FIELD_LENGTH of {$field} is not an integer!");
                            return false;
                        }
                        if ($params[FIELD_LENGTH] < 0 && $params[FIELD_LENGTH] < 16384) {
                            static::raiseError(__METHOD__ ."(), FIELD_LENGTH of {$field} is out of bound!");
                            return false;
                        }
                    }
                }
            }

            if (isset(static::$model_fields_index) &&
                !empty(static::$model_fields_index) &&
                is_array(static::$model_fields_index)
            ) {
                foreach (static::$model_fields_index as $field) {
                    if (!static::hasField($field)) {
                        static::raiseError(__CLASS__ .'::hasField() returned false!');
                        return false;
                    }
                }
            }
        }

        if (!isset($this->model_load_by) || (
            !is_array($this->model_load_by) &&
            !is_bool($this->model_load_by) &&
            !is_null($this->model_load_by)
        )) {
            static::raiseError(__METHOD__ .'(), missing property "model_load_by"');
            return false;
        }

        if (!empty($this->model_load_by)) {
            foreach ($this->model_load_by as $field => $value) {
                if (!isset($field) ||
                    empty($field) ||
                    !is_string($field)
                ) {
                    static::raiseError(__METHOD__ .'(), $model_load_by contains an invalid field!');
                    return false;
                }
                if ((isset($this) && $this->hasVirtualFields() && !$this->hasVirtualField($field)) &&
                    !static::hasField($field) &&
                    (static::hasModelItems() &&
                    ($items_model = static::getModelItemsModel()) !== false &&
                    ($full_model = $thallium->getFullModelName($items_model)) !== false &&
                    !$full_model::hasField($field))
                ) {
                    static::raiseError(__METHOD__ .'(), $model_load_by contains an unknown field!');
                    return false;
                }
                if (static::hasField($field) && !$this->validateField($field, $value)) {
                    static::raiseError(__METHOD__ .'(), $model_load_by contains an invalid value!');
                    return false;
                }
            }
        }

        if (!empty($this->model_sort_order)) {
            foreach ($this->model_sort_order as $field => $mode) {
                if (($items_model = static::getModelItemsModel()) === false) {
                    static::raiseError(__CLASS__ .'::getModelItemsModel() returned false!');
                    return false;
                }

                if (($full_model = $thallium->getFullModelName($items_model)) === false) {
                    static::raiseError(get_class($thallium) .'::getFullModelName() returned false!');
                    return false;
                }

                if (!isset($field) ||
                    empty($field) ||
                    !is_string($field) ||
                    !$full_model::hasModelFields() ||
                    !$full_model::hasField($field)
                ) {
                    static::raiseError(__METHOD__ ."(), \$model_sort_order contains an invalid field {$field}!");
                    return false;
                }

                if (!in_array(strtoupper($mode), array('ASC', 'DESC'))) {
                    static::raiseError(__METHOD__ .'(), $order is invalid!');
                    return false;
                }
            }
        }

        if (static::hasModelLinks()) {
            if (!is_array(static::$model_links)) {
                static::raiseError(__METHOD__ .'(), $model_links is not an array!');
                return false;
            }

            foreach (static::$model_links as $target => $field) {
                if (!isset($target) || empty($target) || !is_string($target)) {
                    static::raiseError(__METHOD__ .'(), $model_links link target is invalid!');
                    return false;
                }

                if (!isset($field) || empty($field) || !is_string($field)) {
                    static::raiseError(__METHOD__ .'(), $model_links link field is invalid!');
                    return false;
                }

                if (!static::hasField($field)) {
                    static::raiseError(__METHOD__ .'(), $model_links link field is unknown!');
                    return false;
                }

                if (($parts = explode('/', $target)) === false) {
                    static::raiseError(__METHOD__ .'(), failed to explode() $model_links target!');
                    return false;
                }

                if (count($parts) < 2) {
                    static::raiseError(__METHOD__ .'(), link information incorrectly declared!');
                    return false;
                }

                $target_model = $parts[0];
                $target_field = $parts[1];

                if (!isset($target_model) || empty($target_model) || !is_string($target_model)) {
                    static::raiseError(__METHOD__ .'(), $model_links member model value is invalid!');
                    return false;
                }

                if (!$thallium->isValidModel($target_model)) {
                    static::raiseError(
                        __METHOD__ .'(), $model_links member model value refers an unknown model!'
                    );
                    return false;
                }

                if (($target_full_model = $thallium->getFullModelName($target_model)) === false) {
                    static::raiseError(get_class($thallium) .'::getFullModelName() returned false!');
                    return false;
                }

                if (!isset($target_field) || empty($target_field) || !is_string($target_field)) {
                    static::raiseError(__METHOD__ .'(), $model_links member model field is invalid!');
                    return false;
                }

                if (!$target_full_model::hasModelItems() && !$target_full_model::hasField($target_field)) {
                    static::raiseError(sprintf('%s::hasField() returned false!', $target_full_model));
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * loads data from database into a model
     *
     * @param string $extend_query_where
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function load($extend_query_where = null)
    {
        if (isset($extend_query_where) &&
            !empty($extend_query_where) &&
            !is_string($extend_query_where)
        ) {
            static::raiseError(__METHOD__ .'(), $extend_query_where parameter is invalid!');
            return false;
        }

        global $thallium, $db;

        if (!static::hasModelFields() && !static::hasModelItems()) {
            return true;
        }

        if (static::hasModelFields() && empty($this->model_load_by)) {
            return true;
        }

        if (!isset($this->model_sort_order) ||
            !is_array($this->model_sort_order)
        ) {
            static::raiseError(__METHOD__ .'(), $model_sort_order is invalid!');
            return false;
        }

        if (!empty($this->model_sort_order)) {
            $order_by = array();
            foreach ($this->model_sort_order as $field => $mode) {
                if (($column = static::column($field)) === false) {
                    static::raiseError(__CLASS__ .'::column() returned false!');
                    return false;
                }
                array_push($order_by, "{$column} {$mode}");
            }
        }

        if (method_exists($this, 'preLoad') && is_callable(array($this, 'preLoad'))) {
            if (!$this->preLoad()) {
                static::raiseError(get_called_class() .'::preLoad() method returned false!');
                return false;
            }
        }

        $sql_query_columns = array();
        $sql_query_data = array();

        if (static::hasModelFields()) {
            if (($fields = $this->getFieldNames()) === false) {
                static::raiseError(__CLASS__ .'::getFieldNames() returned false!');
                return false;
            }
        } elseif (static::hasModelItems()) {
            $fields = array(
                FIELD_IDX,
                FIELD_GUID,
            );
        }

        if (!isset($fields) || empty($fields)) {
            return true;
        }

        foreach ($fields as $field) {
            if (($column = static::column($field)) === false) {
                static::raiseError(__CLASS__ .'::column() returned false!');
                return false;
            }

            if ($field == 'time') {
                $sql_query_columns[] = sprintf("UNIX_TIMESTAMP(%s) as %s", $column, $column);
                continue;
            }

            $sql_query_columns[$field] = $column;
        }

        foreach ($this->model_load_by as $field => $value) {
            if (($column = static::column($field)) === false) {
                static::raiseError(__CLASS__ .'::column() returned false!');
                return false;
            }

            $sql_query_data[$column] = $value;
        }

        $bind_params = array();

        if (($sql = $db->buildQuery(
            "SELECT",
            static::getTableName(),
            $sql_query_columns,
            $sql_query_data,
            $bind_params,
            $extend_query_where
        )) === false) {
            static::raiseError(get_class($db) .'::buildQuery() returned false!');
            return false;
        }

        if (isset($order_by) &&
            !empty($order_by) &&
            is_array($order_by)
        ) {
            $sql.= sprintf(' ORDER BY %s', implode(', ', $order_by));
        }

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
            static::raiseError(get_class($db) .'::prepare() returned invalid data!');
            return false;
        }

        foreach ($bind_params as $key => $value) {
            try {
                $sth->bindParam($key, $value);
            } catch (\PDOException $e) {
                static::raiseError(get_class($sth) .'::bindParam() failed!', false, $e);
                return false;
            } catch (\Exception $e) {
                static::raiseError(get_class($sth) .'::bindParam() failed!', false, $e);
                return false;
            }
        }

        if (!$db->execute($sth, $bind_params)) {
            $db->freeStatement($sth);
            static::raiseError(__METHOD__ .'(), unable to execute query!');
            return false;
        }

        $num_rows = $sth->rowCount();

        if (static::hasModelFields()) {
            if ($num_rows < 1) {
                $db->freeStatement($sth);
                static::raiseError(sprintf(
                    '%s(), no object found!',
                    __METHOD__
                ));
                return false;
            } elseif ($num_rows > 1) {
                $db->freeStatement($sth);
                static::raiseError(sprintf(
                    '%s(), more than one object found!',
                    __METHOD__
                ));
                return false;
            }
        }

        if ($num_rows === 0) {
            $db->freeStatement($sth);
            return true;
        }

        if (!static::hasModelFields() && !static::hasModelItems()) {
            static::raiseError(__METHOD__ .'(), unsupported model constelation found!');
            return false;
        }

        if (static::hasModelFields()) {
            if (($row = $sth->fetch(\PDO::FETCH_ASSOC)) === false) {
                $db->freeStatement($sth);
                static::raiseError(sprintf(
                    '%s(), unable to fetch SQL result for object id %s!',
                    __METHOD__,
                    $this->model_load_by[FIELD_IDX]
                ));
                return false;
            }

            $db->freeStatement($sth);

            foreach ($row as $key => $value) {
                if (($field = static::getFieldNameFromColumn($key)) === false) {
                    static::raiseError(__CLASS__ .'() returned false!');
                    return false;
                }

                if (!static::hasField($field)) {
                    static::raiseError(__METHOD__ ."(), received data for unknown field '{$field}'!");
                    return false;
                }

                if (!$this->validateField($field, $value)) {
                    static::raiseError(__CLASS__ ."::validateField() returned false for field {$field}!");
                    return false;
                }

                // type casting, as fixed point numbers are returned as string!
                if ($this->getFieldType($field) === FIELD_INT &&
                    is_string($value) &&
                    is_numeric($value)
                ) {
                    $value = intval($value);
                }

                if (!$this->setFieldValue($field, $value)) {
                    static::raiseError(__CLASS__ ."::setFieldValue() returned false for field {$field}!");
                    return false;
                }

                $this->model_init_values[$field] = $value;
            }
        } elseif (static::hasModelItems()) {
            while (($row = $sth->fetch(\PDO::FETCH_ASSOC)) !== false) {
                if (($items_model = static::getModelItemsModel()) === false) {
                    static::raiseError(__CLASS__ .'::getModelItemsModel() returned false!');
                    return false;
                }

                if (($child_model_name = $thallium->getFullModelName($items_model)) === false) {
                    $db->freeStatement($sth);
                    static::raiseError(get_class($thallium) .'::getFullModelName() returned false!');
                    return false;
                }

                foreach ($row as $key => $value) {
                    if (($field = $child_model_name::getFieldNameFromColumn($key)) === false) {
                        $db->freeStatement($sth);
                        static::raiseError(__CLASS__ .'() returned false!');
                        return false;
                    }

                    if (!$child_model_name::validateField($field, $value)) {
                        $db->freeStatement($sth);
                        static::raiseError(__CLASS__ ."::validateField() returned false for field {$field}!");
                        return false;
                    }
                }

                $item = array(
                    FIELD_MODEL => $child_model_name,
                    FIELD_IDX => $row[$child_model_name::column(FIELD_IDX)],
                    FIELD_GUID => $row[$child_model_name::column(FIELD_GUID)],
                    FIELD_INIT => false,
                );

                if (!$this->addItem($item)) {
                    $db->freeStatement($sth);
                    static::raiseError(__CLASS__ .'::addItem() returned false!');
                    return false;
                }
            }

            $db->freeStatement($sth);
        }

        if (method_exists($this, 'postLoad') && is_callable(array($this, 'postLoad'))) {
            if (!$this->postLoad()) {
                static::raiseError(get_called_class() ."::postLoad() method returned false!");
                return false;
            }
        }

        if (!isset($this->id) || empty($this->id) && static::hasField(FIELD_IDX)) {
            if (isset($this->model_values[FIELD_IDX]) && !empty($this->model_values[FIELD_IDX])) {
                $this->id = $this->model_values[FIELD_IDX];
            }
        }

        return true;
    }

    /**
     * finds data as defined in $query in the database
     * by searching fields that have been given in $fields.
     *
     * @param array $query
     * @param array $fields
     * @param bool $load
     * @return array|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public static function find($query, $fields, $load = false)
    {
        global $thallium, $db;

        if (!static::isSearchable()) {
            static::raiseError(__CLASS__ .'::isSearchable() returned false!');
            return false;
        }

        if (!isset($load) || !is_bool($load)) {
            static::raiseError(__METHOD__ .'(), $load parameter is invalid!');
            return false;
        }

        if (!isset($query) ||
            empty($query) ||
            !is_array($query) ||
            !array_key_exists('data', $query) ||
            !isset($query['data']) ||
            empty($query['data']) ||
            (!is_string($query['data']) && !is_numeric($query['data'])) ||
            !array_key_exists('type', $query) ||
            !isset($query['type']) ||
            empty($query['type']) ||
            !is_string($query['type'])
        ) {
            static::raiseError(__METHOD__ .'(), $query parameter is invalid!');
            return false;
        }

        if (!isset($fields) ||
            empty($fields) ||
            !is_array($fields)
        ) {
            static::raiseError(__METHOD__ .'(), $fields parameter is invalid!');
            return false;
        }

        $sql_query_columns = array(
            static::column(FIELD_IDX),
            static::column(FIELD_GUID),
        );

        $sql_query_columns = array();
        $sql_query_data = array();

        foreach ($fields as $field) {
            if (($column = static::column($field)) === false) {
                static::raiseError(__CLASS__ .'::column() returned false!');
                return false;
            }

            if ($field == 'time') {
                $sql_query_columns[] = sprintf("UNIX_TIMESTAMP(%s) as %s", $column, $column);
                continue;
            }

            $sql_query_columns[$field] = $column;
            $sql_query_data[$column] = $query['data'];
        }

        $bind_params = array();

        if (($sql = $db->buildQuery(
            "SELECT",
            static::getTableName(),
            $sql_query_columns,
            $sql_query_data,
            $bind_params,
            null,
            false,
            true
        )) === false) {
            static::raiseError(get_class($db) .'::buildQuery() returned false!');
            return false;
        }

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

        foreach ($bind_params as $key => $value) {
            try {
                $sth->bindParam($key, $value);
            } catch (\PDOException $e) {
                static::raiseError(get_class($sth) .'::bindParam() failed!', false, $e);
                return false;
            } catch (\Exception $e) {
                static::raiseError(get_class($sth) .'::bindParam() failed!', false, $e);
                return false;
            }
        }

        if (!$db->execute($sth, $bind_params)) {
            $db->freeStatement($sth);
            static::raiseError(__METHOD__ ."(), unable to execute query!");
            return false;
        }

        $num_rows = $sth->rowCount();

        if ($num_rows < 1) {
            $db->freeStatement($sth);
            return array();
        }

        if (($results = $sth->fetchAll(\PDO::FETCH_ASSOC)) === false) {
            static::raiseError(get_class($sth) .'::fetchAll() returned false!');
            return false;
        }

        if ($load === false) {
            return $results;
        }

        $items_model = get_called_class();

        try {
            $items = new $items_model(false);
        } catch (\Exception $e) {
            static::raiseError(
                sprintf('%s(), failed to load %s!', __METHOD__, $items_model),
                false,
                $e
            );
            return false;
        }

        if (($child_model = static::getModelItemsModel()) === false) {
            static::raiseError(__CLASS__ .'::getModelItemsModel() returned false!');
            return false;
        }

        if (($full_child_model = $thallium->getFullModelName($child_model)) === false) {
            static::raiseError(get_class($thallium) .'::getFullModelName() returned false!');
            return false;
        }

        $idx_field = static::column(FIELD_IDX);
        $guid_field = static::column(FIELD_GUID);

        foreach ($results as $result) {
            if (!array_key_exists($idx_field, $result) ||
                !array_key_exists($guid_field, $result)
            ) {
                static::raiseError(__METHOD__ .'(), incomplete result found!');
                return false;
            }

            if (!$items->addItem(array(
                FIELD_IDX => $result[$idx_field],
                FIELD_GUID => $result[$guid_field],
                FIELD_MODEL => $full_child_model,
            ))) {
                static::raiseError(get_class($items_model) .'::addItem() returned false!');
                return false;
            }
        }

        if (($item_keys = $items->getItemsKeys()) === false) {
            static::raiseError(get_class($items) .'::getItemsKeys() returned false!');
            return false;
        }

        if (!$items->bulkLoad($item_keys)) {
            static::raiseError(get_class($items) .'::bulkLoad() returned false!');
            return false;
        }

        return $items;
    }

    /**
     * if a model is configured to have items, it will consist of further models that
     * are representing these items. Too speed up the loading process, bulkLoad() may
     * be used automatically if number of items to load is > than $model_bulk_load_limit
     *
     * @param array $extend_query_where
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final protected function bulkLoad($keys)
    {
        global $thallium, $db;

        if (!static::hasModelItems()) {
            static::raiseError(__METHOD__ .'(), model is not configured to have items!');
            return false;
        }

        if (!isset($keys) || empty($keys) || !is_array($keys)) {
            static::raiseError(__METHOD__ .'(), $keys parameter is invalid!');
            return false;
        }

        $key_check_func = function ($key) {
            if (!isset($key) ||
                empty($key) ||
                (!is_numeric($key) && !is_int($key))
            ) {
                static::raiseError(__METHOD__ .'(), $keys parameter contains an invalid key!');
                return false;
            }
            return true;
        };

        if (!array_walk($keys, $key_check_func)) {
            static::raiseError(__METHOD__ .'(), $keys parameter failed validation!');
            return false;
        }

        if (($keys_str = implode(',', $keys)) === false) {
            static::raiseError(__METHOD__ .'(), something went wrong on implode()!');
            return false;
        }

        if (!isset($keys_str) || empty($keys_str) || !is_string($keys_str)) {
            static::raiseError(__METHOD__ .'(), implode() returned something unexcepted!');
            return false;
        }

        $result = $db->query(sprintf(
            "SELECT
                *
            FROM
                TABLEPREFIX%s
            WHERE
                %s_idx IN (%s)",
            static::$model_table_name,
            static::$model_column_prefix,
            $keys_str
        ));

        if (!isset($result) ||
            empty($result) ||
            !is_object($result) ||
            !is_a($result, 'PDOStatement')
        ) {
            static::raiseError(get_class($db) .'::query() returned false!');
            return false;
        }

        if ($result->rowCount() < 1) {
            return true;
        }

        if (($items_model = static::getModelItemsModel()) === false) {
            static::raiseError(__CLASS__ .'::getModelItemsModel() returned false!');
            return false;
        }

        if (($full_model = $thallium->getFullModelName($items_model)) === false) {
            static::raiseError(get_class($thallium) .'::getFullModelName() returned false!');
            return false;
        }

        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $item = array();

            foreach ($row as $key => $value) {
                if (($field = $full_model::getFieldNameFromColumn($key)) === false) {
                    static::raiseError(__CLASS__ .'() returned false!');
                    return false;
                }
                if (!$full_model::validateField($field, $value)) {
                    static::raiseError(__CLASS__ ."::validateField() returned false for field {$field}!");
                    return false;
                }
                $item[$field] = $value;
            }

            if (!array_key_exists(FIELD_IDX, $item)) {
                static::raiseError(__METHOD__ .'(), retrieved item misses idx field!');
                return false;
            }

            if (!$this->setItemData($item[FIELD_IDX], $item)) {
                static::raiseError(__CLASS__ .'::setItemData() returned false!');
                return false;
            }
        }

        return true;
    }

    /**
     * update model fields by the data provided in $data
     *
     * @param array|object $data
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function update($data)
    {
        if (!isset($data) ||
            empty($data) ||
            (!is_array($data) && !is_object($data))
        ) {
            static::raiseError(__METHOD__ .'(), $data parameter is invalid!');
            return false;
        }

        foreach ($data as $key => $value) {
            if (($field = static::getFieldNameFromColumn($key)) === false) {
                static::raiseError(__METHOD__ .'(), unknown field found!');
                return false;
            }

            if (static::hasField($field)) {
                // this will trigger the __set() method.
                $this->$key = $value;
                continue;
            }

            if ($this->hasVirtualFields() && !$this->hasVirtualField($field)) {
                static::raiseError(__METHOD__ .'(), model has no field like that!');
                return false;
            }

            if (($method_name = $this->getVirtualFieldSetMethod($field)) === false) {
                static::raiseError(__CLASS__ .'::getVirtualFieldSetMethod() returned false!');
                return false;
            }

            if (call_user_func(array($this, $method_name), $value) === false) {
                static::raiseError(__CLASS__ ."::{$method_name}() returned false!");
                return false;
            }
        }

        return true;
    }

    /**
     * update model fields by the data provided in $data.
     * contrary to update(), this method updates the field
     * values directly, without triggering the setXxx() method
     * of a field.
     *
     * !!! WARNING: even invalid values that the setXxx() methods would
     * !!! normally deny, can be set by this method!
     * !!! this is, why flood() can not be overriden and available only
     * !!! as protected!
     *
     * @param array|object $data
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final protected function flood($data)
    {
        if (!isset($data) ||
            empty($data) ||
            (!is_array($data) && !is_object($data))
        ) {
            static::raiseError(__METHOD__ .'(), $data parameter is invalid!');
            return false;
        }

        foreach ($data as $key => $value) {
            if (($field = static::getFieldNameFromColumn($key)) === false) {
                static::raiseError(__METHOD__ .'(), unknown field found!');
                return false;
            }

            if (static::hasField($field)) {
                // this will trigger the __set() method.
                $this->$key = $value;
                $this->model_init_values[$key] = $value;
                continue;
            }

            if ($this->hasVirtualFields() && !$this->hasVirtualField($field)) {
                static::raiseError(__METHOD__ .'(), model has no field like that!');
                return false;
            }

            if (($method_name = $this->getVirtualFieldSetMethod($field)) === false) {
                static::raiseError(__CLASS__ .'::getVirtualFieldSetMethod() returned false!');
                return false;
            }

            if (call_user_func(array($this, $method_name), $value) === false) {
                static::raiseError(__CLASS__ ."::{$method_name}() returned false!");
                return false;
            }

            $this->model_init_values[$key] = $value;
        }

        return true;
    }

    /**
     * deletes an model object from database.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function delete()
    {
        global $db;

        if (static::hasModelFields() && $this->isNew()) {
            return true;
        }

        if (static::hasModelItems() && !$this->hasItems()) {
            return true;
        }

        if (method_exists($this, 'preDelete') && is_callable(array($this, 'preDelete'))) {
            if (!$this->preDelete()) {
                static::raiseError(get_called_class() ."::preDelete() method returned false!");
                return false;
            }
        }

        if (static::hasModelLinks()) {
            if (!$this->deleteModelLinks()) {
                static::raiseError(__CLASS__ .'::deleteModelLinks() returned false!');
                return false;
            }
        }

        if (static::hasModelItems()) {
            if (!$this->deleteItems()) {
                static::raiseError(__CLASS__ .'::deleteItems() returned false!');
                return false;
            }
        } elseif (!static::hasModelItems()) {
            if (!isset($this->id)) {
                static::raiseError(__METHOD__ .'(), can not delete without knowing what to delete!');
                return false;
            }

            /* generic delete */
            $sth = $db->prepare(sprintf(
                "DELETE FROM
                    TABLEPREFIX%s
                WHERE
                    %s_idx LIKE ?",
                static::$model_table_name,
                static::$model_column_prefix
            ));

            if (!isset($sth) ||
                empty($sth) ||
                !is_object($sth) ||
                !is_a($sth, 'PDOStatement')
            ) {
                static::raiseError(__METHOD__ ."(), unable to prepare query");
                return false;
            }

            if (!$db->execute($sth, array($this->id))) {
                static::raiseError(__METHOD__ ."(), unable to execute query");
                return false;
            }

            $db->freeStatement($sth);
        }

        if (method_exists($this, 'postDelete') && is_callable(array($this, 'postDelete'))) {
            if (!$this->postDelete()) {
                static::raiseError(get_called_class() ."::postDelete() method returned false!");
                return false;
            }
        }

        return true;
    }

    /**
     * if a model is configured to have items, this method will delete all of those items.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function deleteItems()
    {
        if (!static::hasModelItems()) {
            static::raiseError(__METHOD__ .'(), model '. __CLASS__ .' is not declared to have items!');
            return false;
        }

        if (!$this->hasItems()) {
            return true;
        }

        if (($items = $this->getItemsKeys()) === false) {
            static::raiseError(__CLASS__ .'::getItems() returned false!');
            return false;
        }

        foreach ($items as $item_idx) {
            if (!$this->hasItem($item_idx)) {
                static::raiseError(__CLASS__ .'::hasItem() returned false!');
                return false;
            }

            if (($item = $this->getItem($item_idx)) === false) {
                static::raiseError(__CLASS__ .'::getItem() returned false!');
                return false;
            }

            if (!method_exists($item, 'delete') || !is_callable(array($item, 'delete'))) {
                static::raiseError(__METHOD__ .'(), model '. get_class($item) .' does not provide a delete() method!');
                return false;
            }

            if (!$item->delete()) {
                static::raiseError(get_class($item) .'::delete() returned false!');
                return false;
            }

            if (!$this->removeItem($item_idx)) {
                static::raiseError(__CLASS__ .'::removeItem() returned false!');
                return false;
            }
        }

        return true;
    }

    /**
     * this method is called after PHP has finshed a clone process.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function __clone()
    {
        global $thallium;

        $this->id = null;

        if (array_key_exists(FIELD_IDX, $this->model_values) ||
            isset($this->model_values[FIELD_IDX]) ||
            !empty($this->model_values[FIELD_IDX])
        ) {
            $this->model_values[FIELD_IDX] = null;
        }

        if (array_key_exists(FIELD_GUID, $this->model_values) ||
            isset($this->model_values[FIELD_GUID]) ||
            !empty($this->model_values[FIELD_GUID])
        ) {
            if (($old_guid = $this->getGuid()) === false) {
                static::raiseError(__CLASS__ .'::getGuid() returned false!', true);
                return;
            }

            if (($new_guid = $thallium->createGuid()) === false) {
                static::raiseError(get_class($thallium) .'::createGuid() returned false!', true);
                return;
            }

            if (!$this->setGuid($new_guid)) {
                static::raiseError(__CLASS__ .'::setGuid() returned false!', true);
                return;
            }
        }

        $pguid_field = 'derivation_guid';

        // record the parent objects GUID
        if (isset($old_guid) && static::hasField($pguid_field)) {
            if (!$this->setFieldValue($pguid_field, $old_guid)) {
                static::raiseError(__CLASS__ .'::setFieldValue() returned false!', true);
                return;
            }
        }

        if (!$this->save()) {
            static::raiseError(__CLASS__ .'::save() returned false!', true);
            return;
        }

        // if saving was successful, our new object should have an ID now
        if ($this->isNew()) {
            static::raiseError(__METHOD__ .'(), error on saving clone. no ID was returned from database!', true);
            return;
        }

        // now check for assigned childrens and duplicate those links too
        if (isset($this->child_names) && !isset($this->ignore_child_on_clone)) {
            // loop through all (known) childrens
            foreach (array_keys($this->child_names) as $child) {
                $prefix = $this->child_names[$child];

                // initate an empty child object
                if (($child_obj = $thallium->load_class($child)) === false) {
                    static::raiseError(__METHOD__ ."(), unable to locate class for {$child_obj}");
                    return false;
                }

                $sth = $db->prepare(sprintf(
                    "SELECT
                        *
                    FROM
                        TABLEPREFIXassign_%s_to_%s
                    WHERE
                        %s_%s_idx LIKE ?",
                    $child_obj->model_table_name,
                    static::$model_table_name,
                    $prefix,
                    static::$model_column_prefix
                ));

                if (!isset($sth) ||
                    empty(!$sth) ||
                    !is_object($sth) ||
                    !is_a($sth, 'PDOStatement')
                ) {
                    static::raiseError(__METHOD__ ."(), unable to prepare query");
                    return false;
                }

                if (!$db->execute($sth, array($srcobj->id))) {
                    static::raiseError(__METHOD__ ."(), unable to execute query");
                    return false;
                }

                while ($row = $sth->fetch(\PDO::FETCH_ASSOC)) {
                    $query = sprintf(
                        "INSERT INTO
                            TABLEPREFIXassign_%s_to_%s (",
                        $child_obj->model_table_name,
                        static::$model_table_name
                    );
                    $values = "";

                    foreach (array_keys($row) as $key) {
                        $query.= $key .",";
                        $values.= "?,";
                    }

                    $query = substr($query, 0, strlen($query)-1);
                    $values = substr($values, 0, strlen($values)-1);

                    $query = $query ."
                        ) VALUES (
                            $values
                            )
                        ";

                    $row[$this->child_names[$child] .'_idx'] = null;
                    $row[$this->child_names[$child] .'_'.static::$model_column_prefix.'_idx'] = $this->id;
                    if (isset($row[$this->child_names[$child] .'_guid'])) {
                        $row[$this->child_names[$child] .'_guid'] = $thallium->createGuid();
                    }

                    if (!isset($child_sth)) {
                        $child_sth = $db->prepare($query);
                    }

                    $db->execute($child_sth, array_values($row));
                }

                if (isset($child_sth)) {
                    $db->freeStatement($child_sth);
                }
                $db->freeStatement($sth);
            }
        }

        if (method_exists($this, 'postClone') && is_callable(array($this, 'postClone'))) {
            if (!$this->postClone()) {
                static::raiseError(__CLASS__ .'::postClone() method returned false!', true);
                return;
            }
        }
    }

    /**
     * initializes all fields
     *
     * @param array $override
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final protected function initFields($override = array())
    {
        if (!static::hasModelFields()) {
            static::raiseError(__METHOD__ .'(), this model has no fields!');
            return false;
        }

        if (isset($override) && !empty($override) && !is_array($override)) {
            static::raiseError(__METHOD__ .'(), $override parameter is invalid!');
            return false;
        }

        foreach (array_keys(static::$model_fields) as $field) {
            if (in_array($field, array_keys($override))) {
                $this->model_values[$field] = $override[$field];
                continue;
            }

            if (!static::hasDefaultValue($field)) {
                $this->model_values[$field] = null;
                continue;
            }

            if (($this->model_values[$field] = static::getDefaultValue($field)) === false) {
                static::raiseError(__CLASS__ .'::getDefaultValue() returned false!');
                return false;
            }
        }

        return true;
    }

    /**
     * this method overrides PHP own __set() method. it is called for all
     * undeclared properties - that means, that have not explicitly declared
     * before run time.
     * we capture these events here and try to locate a matching setXxxx()
     * method.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function __set($name, $value)
    {
        global $thallium;

        if (!static::hasModelFields() && !static::hasModelItems()) {
            if (!isset($thallium::$permit_undeclared_class_properties)) {
                static::raiseError(__METHOD__ ."(), trying to set an undeclared property {$name}!", true);
                return;
            }
            $this->$name = $value;
            return;
        }

        if ($this->hasVirtualFields() && $this->hasVirtualField($name)) {
            if (($name = static::getFieldNamefromColumn($name)) === false) {
                static::raiseError(__CLASS__ .'::getFieldNameFromColumn() returned false!', true);
                return;
            }

            if (($method_name = $this->getVirtualFieldSetMethod($name)) === false) {
                static::raiseError(__CLASS__ .'::getVirtualFieldSetMethod() returned false!', true);
                return;
            }

            if (call_user_func(array($this, $method_name), $value) === false) {
                static::raiseError(__CLASS__ ."::{$method_name}() returned false!", true);
                return;
            }

            return;
        }

        if (!static::hasModelFields()) {
            static::raiseError(__METHOD__ ."(), model_fields array not set for class ". get_class($this), true);
            return;
        }

        if (($field = static::getFieldNameFromColumn($name)) === false) {
            $this->raiseEerror(__CLASS__ .'::getFieldNameFromColumn() returned false!', true);
            return;
        }

        if (!$this->hasField($field) && $field !== 'id') {
            static::raiseError(__METHOD__ ."(), unknown key {$field}", true);
            return;
        }

        if (($field_type = static::getFieldType($field)) === false || empty($field_type)) {
            static::raiseError(__CLASS__ .'::getFieldType() returned false!', true);
            return;
        }

        if (($value_type = gettype($value)) === 'unknown type' || empty($value_type)) {
            static::raiseError(__METHOD__ .'(), value is of an unknown type!', true);
            return;
        }

        if (!static::validateField($field, $value)) {
            static::raiseError(__CLASS__ .'::validateField() returned false!', true);
            return;
        }

        // NULL values and empty strings can not be checked closer.
        if (is_null($value) || (is_string($value) && strlen($value) === 0)) {
            $this->model_values[$field] = $value;
            return;
        }

        /* if an empty string has been provided as value and the field type is
         * an integer value, cast the value to 0 instead.
         */
        if ($value_type == 'string' && $value === '' && $field_type == FIELD_INT) {
            $value_type = FIELD_INT;
            $value = 0;
        }

        /* values have been validated already by validateField(), but
           sometimes we have to cast values to their field types.
        */

        /* positiv integers */
        if ($field_type == FIELD_INT &&
            $value_type == 'string' &&
            ctype_digit($value) &&
            is_numeric($value)
        ) {
            $value = (int) $value;
            $value_type = $field_type;
        }
        /* negative integers */
        if ($field_type == FIELD_INT &&
            $value_type == 'string' &&
            preg_match("/^-?[1-9][0-9]*$/", $value) === 1
        ) {
            $value = (int) $value;
            $value_type = $field_type;
        /* distinguish GUIDs */
        } elseif ($field_type == FIELD_GUID &&
            $value_type == 'string'
        ) {
            if (!empty($value) &&
                $thallium->isValidGuidSyntax($value)
            ) {
                $value_type = FIELD_GUID;
            } elseif (empty($value)) {
                $value_type = FIELD_GUID;
            }
        /* distinguish YESNO */
        } elseif ($field_type == FIELD_YESNO &&
            $value_type == 'string' &&
            in_array($value, array('yes', 'no', 'Y', 'N'))
        ) {
            $value_type = 'yesno';
        /* distinguiѕh dates */
        } elseif ($field_type == FIELD_DATE &&
            $value_type == 'string' &&
            preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $value)
        ) {
            $value_type = 'date';
        /* distinguish timestamps */
        } elseif ($field_type == FIELD_TIMESTAMP &&
            $value_type == 'string'
        ) {
            $value_type = 'timestamp';
        } elseif ($field_type == FIELD_TIMESTAMP &&
            $value_type == 'double'
        ) {
            if (is_float($value)) {
                $value_type = 'timestamp';
            }
        }

        if ($value_type !== $field_type) {
            static::raiseError(
                __METHOD__
                ."(), field {$field}, value type ({$value_type}) does not match field type ({$field_type})!",
                true
            );
            return;
        }

        if (!static::hasFieldSetMethod($field)) {
            $this->model_values[$field] = $value;
            return;
        }

        if (($set_method = static::getFieldSetMethod($field)) === false) {
            static::raiseError(__CLASS__ .'::getFieldSetMethod() returned false!', true);
            return;
        }

        if (!is_callable(array($this, $set_method))) {
            static::raiseError(__CLASS__ ."::{$set_method}() is not callable!", true);
            return;
        }

        if (call_user_func(array($this, $set_method), $value) === false) {
            static::raiseError(__CLASS__ ."::{$set_method}() returned false!", true);
            return;
        }

        return;
    }

    /**
     * this method overrides PHP own __get() method. it is called for all
     * undeclared properties - that means, that have not explicitly declared
     * before run time.
     * we capture these events here and try to locate a matching getXxxx()
     * method.
     *
     * @param string $name
     * @return mixed
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function __get($name)
    {
        if (!static::hasModelFields() && !static::hasModelItems()) {
            return isset($this->$name) ? $this->$name : null;
        }

        if (($field = static::getFieldNamefromColumn($name)) === false) {
            static::raiseError(__CLASS__ .'::getFieldNameFromColumn() returned false!', true);
            return;
        }

        if (isset($this->model_values[$field])) {
            if (!static::hasFieldGetMethod($field)) {
                return $this->model_values[$field];
            }

            if (($get_method = static::getFieldGetMethod($field)) === false) {
                static::raiseError(__CLASS__ .'::getFieldGetMethod() returned false!', true);
                return;
            }

            if (!is_callable(array($this, $get_method))) {
                static::raiseError(__CLASS__ ."::{$get_method}() is not callable!", true);
                return;
            }

            if (($retval = call_user_func(array($this, $get_method), $value)) === false) {
                static::raiseError(__CLASS__ ."::{$get_method}() returned false!", true);
                return;
            }

            return $retval;
        }

        if (!$this->hasVirtualFields()) {
            return null;
        }

        if (!$this->hasVirtualField($field)) {
            return null;
        }

        if (($method_name = $this->getVirtualFieldGetMethod($field)) === false) {
            static::raiseError(__CLASS__ .'::getVirtualFieldGetMethod() returned false!', true);
            return false;
        }

        if (($value = call_user_func(array($this, $method_name), $value)) === false) {
            static::raiseError(__CLASS__ ."::{$method_name}() returned false!", true);
            return false;
        }

        return $value;
    }

    /**
     * saves model field values to database.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function save()
    {
        global $thallium, $db;

        if (!static::hasModelFields()) {
            static::raiseError(__METHOD__ ."(), model_fields array not set for class ". get_class($this));
        }

        if (method_exists($this, 'preSave') && is_callable(array($this, 'preSave'))) {
            if (!$this->preSave()) {
                static::raiseError(get_called_class() ."::preSave() method returned false!");
                return false;
            }
        }

        $time_field = static::column('time');

        if (!array_key_exists(FIELD_GUID, $this->model_values) ||
            !isset($this->model_values[FIELD_GUID]) ||
            empty($this->model_values[FIELD_GUID])
        ) {
            $this->model_values[FIELD_GUID] = $thallium->createGuid();
        }

        $sql = $this->isNew() ? 'INSERT INTO ' : 'UPDATE ';

        $sql.= sprintf("TABLEPREFIX%s SET ", static::$model_table_name);

        $arr_values = array();
        $arr_columns = array();

        foreach (array_keys(static::$model_fields) as $field) {
            if (($column = static::column($field)) === false) {
                static::raiseError(__METHOD__ .'(), invalid column found!');
                return false;
            }

            if (!array_key_exists($field, $this->model_values) ||
                !isset($this->model_values[$field])
            ) {
                continue;
            }

            $arr_columns[] = ($column == $time_field) ?
                sprintf("%s = FROM_UNIXTIME(?)", $column) : sprintf("%s = ?", $column);
            $arr_values[] = $this->model_values[$field];
        }

        $sql.= implode(', ', $arr_columns);

        if ($this->isNew()) {
            $this->model_values[FIELD_IDX] = null;
        } elseif (!$this->isNew()) {
            $sql.= sprintf(" WHERE %s LIKE ?", static::column(FIELD_IDX));
            $arr_values[] = $this->id;
        }

        if (($sth = $db->prepare($sql)) === false) {
            static::raiseError(__METHOD__ ."(), unable to prepare query");
            return false;
        }

        if (!isset($sth) ||
            empty($sth) ||
            !is_object($sth) ||
            !is_a($sth, 'PDOStatement')
        ) {
            static::raiseError(get_class($db) .'::prepare() returned no PDOStatement!');
            return false;
        }

        if (!$db->execute($sth, $arr_values)) {
            $db->freeStatement($sth);
            static::raiseError(__METHOD__ ."(), unable to execute query");
            return false;
        }

        if (!isset($this->id) || empty($this->id)) {
            if (($this->id = $db->getId()) === false) {
                $db->freeStatement($sth);
                static::raiseError(get_class($db) .'::getId() returned false!');
                return false;
            }
        }

        if (!array_key_exists(FIELD_IDX, $this->model_values) ||
            !isset($this->model_values[FIELD_IDX]) ||
            empty($this->model_values[FIELD_IDX]) ||
            is_null($this->model_values[FIELD_IDX])
        ) {
            $this->model_values[FIELD_IDX] = $this->id;
        }

        $db->freeStatement($sth);

        if (method_exists($this, 'postSave') && is_callable(array($this, 'postSave'))) {
            if (!$this->postSave()) {
                static::raiseError(get_called_class() ."::postSave() method returned false!");
                return false;
            }
        }

        // now we need to update the model_init_values array.
        $this->model_init_values = array();

        foreach (array_keys(static::$model_fields) as $field) {
            if (!array_key_exists($field, $this->model_values) ||
                !isset($this->model_values[$field])) {
                continue;
            }

            $this->model_init_values[$field] = $this->model_values[$field];
        }

        return true;
    }

    /*final public function toggleStatus($to)
    {
        global $db;

        if (!isset($this->id)) {
            return false;
        }
        if (!is_numeric($this->id)) {
            return false;
        }
        if (!isset(static::$model_table_name)) {
            return false;
        }
        if (!isset(static::$model_column_prefix)) {
            return false;
        }
        if (!in_array($to, array('off', 'on'))) {
            return false;
        }

        if ($to == "on") {
            $new_status = 'Y';
        } elseif ($to == "off") {
            $new_status = 'N';
        }

        $sth = $db->prepare(sprintf(
            "UPDATE
                TABLEPREFIX%s
            SET
                %s_active = ?
            WHERE
                %s_idx LIKE ?",
            static::$model_table_name,
            static::$model_column_prefix,
            static::$model_column_prefix
        ));

        if (!$sth) {
            static::raiseError(__METHOD__ ."(), unable to prepare query");
            return false;
        }

        if (!$db->execute($sth, array($new_status, $this->id))) {
            static::raiseError(__METHOD__ ."(), unable to execute query");
            return false;
        }

        $db->freeStatement($sth);
        return true;

    } // toggleStatus()*/

    /*final public function toggleChildStatus($to, $child_obj, $child_id)
    {
        global $db, $thallium;

        if (!isset($this->child_names)) {
            static::raiseError(__METHOD__ ."(), this object has no childs at all!");
            return false;
        }
        if (!isset($this->child_names[$child_obj])) {
            static::raiseError(__METHOD__ ."(), requested child is not known to this object!");
            return false;
        }

        $prefix = $this->child_names[$child_obj];

        if (($child_obj = $thallium->load_class($child_obj, $child_id)) === false) {
            static::raiseError(__METHOD__ ."(), unable to locate class for {$child_obj}");
            return false;
        }

        if (!isset($this->id)) {
            return false;
        }
        if (!is_numeric($this->id)) {
            return false;
        }
        if (!isset(static::$model_table_name)) {
            return false;
        }
        if (!isset(static::$model_column_prefix)) {
            return false;
        }
        if (!in_array($to, array('off', 'on'))) {
            return false;
        }

        if ($to == "on") {
            $new_status = 'Y';
        } elseif ($to == "off") {
            $new_status = 'N';
        }

        $sth = $db->prepare(sprintf(
            "UPDATE
                TABLEPREFIXassign_%s_to_%s
            SET
                %s_%s_active = ?
            WHERE
                %s_%s_idx LIKE ?
            AND
                %s_%s_idx LIKE ?",
            $child_obj->model_table_name,
            static::$model_table_name,
            $prefix,
            $child_obj->model_column_prefix,
            $prefix,
            static::$model_column_prefix,
            $prefix,
            $child_obj->model_column_prefix
        ));

        if (!$sth) {
            static::raiseError(__METHOD__ ."(), unable to prepare query");
            return false;
        }

        if (!$db->execute($sth, array(
            $new_status,
            $this->id,
            $child_id
        ))) {
            static::raiseError(__METHOD__ ."(), unable to execute query");
            return false;
        }

        $db->freeStatement($sth);
        return true;

    } // toggleChildStatus() */

    /**
     * returns the previous object in database relative to the current one.
     *
     * @param none
     * @return string
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function prev()
    {
        global $thallium, $db;

        if (!$this->hasIdx()) {
            static::raiseError(__CLASS__ .'::hasIdx() returned false!');
            return false;
        }

        if (($idx = $this->getIdx()) === false) {
            static::raiseError(__CLASS__ .'::getIdx() returned false!');
            return false;
        }

        $idx_field = static::column(FIELD_IDX);
        $guid_field = static::column(FIELD_GUID);

        $result = $db->fetchSingleRow(sprintf(
            "SELECT
                %s,
                %s
            FROM
                TABLEPREFIX%s
            WHERE
                %s = (
                    SELECT
                        MAX(%s)
                    FROM
                        TABLEPREFIX%s
                    WHERE
                        %s < %s
                )",
            $idx_field,
            $guid_field,
            static::$model_table_name,
            $idx_field,
            $idx_field,
            static::$model_table_name,
            $idx_field,
            $idx
        ));

        if (!isset($result)) {
            static::raiseError(__METHOD__ ."(), unable to locate previous record!");
            return false;
        }

        if (!isset($result->$idx_field) || !isset($result->$guid_field)) {
            return false;
        }

        if (!is_numeric($result->$idx_field) || !$thallium->isValidGuidSyntax($result->$guid_field)) {
            static::raiseError(
                __METHOD__ ."(), Invalid previous record found: ". htmlentities($result->$id, ENT_QUOTES)
            );
            return false;
        }

        return $result->$idx_field ."-". $result->$guid_field;
    }

    /**
     * returns the next object in database relative to the current one.
     *
     * @param none
     * @return string
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function next()
    {
        global $thallium, $db;

        if (!$this->hasIdx()) {
            static::raiseError(__CLASS__ .'::hasIdx() returned false!');
            return false;
        }

        if (($idx = $this->getIdx()) === false) {
            static::raiseError(__CLASS__ .'::getIdx() returned false!');
            return false;
        }

        $idx_field = static::column(FIELD_IDX);
        $guid_field = static::column(FIELD_GUID);

        $result = $db->fetchSingleRow(sprintf(
            "SELECT
                %s,
                %s
            FROM
                TABLEPREFIX%s
            WHERE
                %s = (
                    SELECT
                        MIN(%s)
                    FROM
                        TABLEPREFIX%s
                    WHERE
                        %s > %s
                )",
            $idx_field,
            $guid_field,
            static::$model_table_name,
            $idx_field,
            $idx_field,
            static::$model_table_name,
            $idx_field,
            $idx
        ));

        if (!isset($result)) {
            static::raiseError(__METHOD__ ."(), unable to locate next record!");
            return false;
        }

        if (!isset($result->$idx_field) || !isset($result->$guid_field)) {
            return false;
        }

        if (!is_numeric($result->$idx_field) || !$thallium->isValidGuidSyntax($result->$guid_field)) {
            static::raiseError(__METHOD__ ."(), invalid next record found: ". htmlentities($result->$id, ENT_QUOTES));
            return false;
        }

        return $result->$idx_field ."-". $result->$guid_field;
    }

    /**
     * verifies if the same object (idx, guid) is already in database.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final protected function isDuplicate()
    {
        global $db;

        // no need to check yet if $id isn't set
        if (empty($this->id)) {
            return false;
        }

        if ((!array_key_exists(FIELD_IDX, $this->model_values) ||
                !isset($this->model_values[FIELD_IDX]) ||
                empty($this->model_values[FIELD_IDX])) &&
            (!array_key_exists(FIELD_GUID, $this->model_values) ||
                !isset($this->model_values[FIELD_GUID]) ||
                empty($this->model_values[FIELD_GUID]))
        ) {
            static::raiseError(
                __METHOD__ ."(), can't check for duplicates if neither \$idx_field or \$guid_field is set!"
            );
            return false;
        }

        $idx_field = static::column(FIELD_IDX);
        $guid_field = static::column(FIELD_GUID);

        $arr_values = array();
        $where_sql = '';
        if (isset($this->model_values[FIELD_IDX]) && !empty($this->model_values[FIELD_IDX])) {
            $where_sql.= "
                {$idx_field} LIKE ?
            ";
            $arr_values[] = $this->model_values[FIELD_IDX];
        }
        if (isset($this->model_values[FIELD_GUID]) && !empty($this->model_values[FIELD_GUID])) {
            if (!empty($where_sql)) {
                $where_sql.= "
                    AND
                ";
            }
            $where_sql.= "
                {$guid_field} LIKE ?
            ";
            $arr_values[] = $this->model_values[FIELD_GUID];
        }

        if (!isset($where_sql) ||
            empty($where_sql) ||
            !is_string($where_sql)
        ) {
            return false;
        }

        $sql = sprintf(
            "SELECT
                %s
            FROM
                TABLEPREFIX%s
            WHERE
                %s <> %s
            AND
                %s",
            $idx_field,
            static::$model_table_name,
            $idx_field,
            $this->id,
            $where_sql
        );

        if (($sth = $db->prepare($sql)) === false) {
            static::raiseError(get_class($db) .'::prepare() returned false!');
            return false;
        }

        if (!$db->execute($sth, $arr_values)) {
            static::raiseError(get_class($db) .'::execute() returned false!');
            return false;
        }

        if ($sth->rowCount() <= 0) {
            $db->freeStatement($sth);
            return false;
        }

        $db->freeStatement($sth);
        return true;
    }

    /**
     * returns the database table column-name for a specific field.
     *
     * @param string $suffix
     * @return string
     * @throws \Thallium\Controllers\ExceptionController
     */
    final protected static function column($suffix)
    {
        if (!isset(static::$model_column_prefix) ||
            empty(static::$model_column_prefix) ||
            !is_string(static::$model_column_prefix)
        ) {
            return $suffix;
        }

        return sprintf('%s_%s', static::$model_column_prefix, $suffix);
    }

    /**
     * globally enable or disable RPC updates to this object.
     *
     * @param bool $state
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final protected function permitRpcUpdates($state)
    {
        if (!isset($state) || !is_bool($state)) {
            static::raiseError(__METHOD__ .'(), $state parameter is invalid!');
            return false;
        }

        $this->model_permit_rpc_updates = $state;
        return true;
    }

    /**
     * returns true if RPC updates are generally enabled for this object.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function permitsRpcUpdates()
    {
        if (!isset($this->model_permit_rpc_updates) ||
            empty($this->model_permit_rpc_updates) ||
            !is_bool($this->model_permit_rpc_updates) ||
            $this->model_permit_rpc_updates !== true
        ) {
            return false;
        }

        return true;
    }

    /**
     * allow the specified field to be updated by RPC updates.
     *
     * @param string $field
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final protected function addRpcEnabledField($field)
    {
        if (!is_array($this->model_rpc_allowed_fields)) {
            static::raiseError(__METHOD__ .'(), $model_rpc_allowed_fields is not an array!', true);
            return false;
        }

        if (!isset($field) ||
            empty($field) ||
            !is_string($field) ||
            (!static::hasField($field) &&
            (!isset($this) ||
            empty($this) ||
            !$this->hasVirtualFields() ||
            !$this->hasVirtualField($field)))
        ) {
            static::raiseError(__METHOD__ .'(), $field is invalid!', true);
            return false;
        }

        if (in_array($field, $this->model_rpc_allowed_fields)) {
            return true;
        }

        array_push($this->model_rpc_allowed_fields, $field);
        return true;
    }

    /**
     * allow the specified action to be performed by an RPC call.
     *
     * @param string $action
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final protected function addRpcAction($action)
    {
        if (!is_array($this->model_rpc_allowed_actions)) {
            static::raiseError(__METHOD__ .'(), $model_rpc_allowed_actions is not an array!', true);
            return false;
        }

        if (!isset($action) ||
            empty($action) ||
            !is_string($action)
        ) {
            static::raiseError(__METHOD__ .'(), $action parameter is invalid!', true);
            return false;
        }

        if (in_array($action, $this->model_rpc_allowed_actions)) {
            return true;
        }

        array_push($this->model_rpc_allowed_actions, $action);
        return true;
    }

    /**
     * returns true if RPC updates are permitted to the specified field.
     *
     * @param string $field
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function permitsRpcUpdateToField($field)
    {
        if (!is_array($this->model_rpc_allowed_fields)) {
            static::raiseError(__METHOD__ .'(), $model_rpc_allowed_fields is not an array!', true);
            return false;
        }

        if (!isset($field) ||
            empty($field) ||
            !is_string($field)
        ) {
            static::raiseError(__METHOD__ .'(), $field parameter is invalid!', true);
            return false;
        }

        if (($field_name = static::getFieldNameFromColumn($field)) === false) {
            static::raiseError(get_called_class() .'::getFieldNameFromColumn() returned false!');
            return false;
        }

        if (!static::hasField($field_name) &&
            (isset($this) &&
            !empty($this) &&
            $this->hasVirtualFields() &&
            !$this->hasVirtualField($field_name))
        ) {
            static::raiseError(__METHOD__ .'(), $field parameter refers an unknown field!', true);
            return false;
        }

        if (empty($this->model_rpc_allowed_fields)) {
            return false;
        }

        if (!in_array($field_name, $this->model_rpc_allowed_fields)) {
            return false;
        }

        return true;
    }

    /**
     * returns true if RPC action is allowed for this object.
     *
     * @param string $action
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function permitsRpcActions($action)
    {
        if (!is_array($this->model_rpc_allowed_actions)) {
            static::raiseError(__METHOD__ .'(), $model_rpc_allowed_actions is not an array!', true);
            return false;
        }

        if (!isset($action) ||
            empty($action) ||
            !is_string($action)
        ) {
            static::raiseError(__METHOD__ .'(), $action parameter is invalid!', true);
            return false;
        }

        if (empty($this->model_rpc_allowed_actions)) {
            return false;
        }

        if (!in_array($action, $this->model_rpc_allowed_actions)) {
            return false;
        }

        return true;
    }

    /**
     * returns true if the field FIELD_IDX has an value
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function hasIdx()
    {
        if (!static::hasModelFields()) {
            static::raiseError(__METHOD__ .'(), this model has no fields!');
            return false;
        }

        if (!static::hasField(FIELD_IDX)) {
            static::raiseError(__METHOD__ .'(), this model has no idx field!');
            return false;
        }

        if (!array_key_exists(FIELD_IDX, $this->model_values) ||
            !isset($this->model_values[FIELD_IDX]) ||
            empty($this->model_values[FIELD_IDX])
        ) {
            return false;
        }

        return true;
    }

    /**
     * legacy method. use getIdx() instead!
     *
     * @param none
     * @return int|bool
     * @throws \Thallium\Controllers\ExceptionController
     * @deprecated
     * @todo remove after 31.12.2016
     */
    final public function getId()
    {
        error_log(__METHOD__ .'(), legacy getId() has been called, '
            .'update your application to getIdx() to avoid this message.');
        return $this->getIdx();
    }

    /**
     * return the value of FIELD_IDX
     *
     * @param none
     * @return int|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function getIdx()
    {
        if (!$this->hasIdx()) {
            static::raiseError(__CLASS__ .'::getIdx() returned false!');
            return false;
        }

        if (($value = $this->getFieldValue(FIELD_IDX)) === false) {
            static::raiseError(__CLASS__ .'::getFieldValue() returned false!');
            return false;
        }

        return $value;
    }

    /**
     * returns true if the field FIELD_GUID has an value
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function hasGuid()
    {
        if (!static::hasModelFields()) {
            static::raiseError(__METHOD__ .'(), this model has no fields!');
            return false;
        }

        if (!static::hasField(FIELD_GUID)) {
            static::raiseError(__METHOD__ .'(), this model has no guid field!');
            return false;
        }

        if (!array_key_exists(FIELD_GUID, $this->model_values) ||
            !isset($this->model_values[FIELD_GUID]) ||
            empty($this->model_values[FIELD_GUID])
        ) {
            return false;
        }

        return true;
    }

    /**
     * return the value of FIELD_GUID
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function getGuid()
    {
        if (!$this->hasGuid()) {
            static::raiseError(__CLASS__ .'::hasGuid() returned false!');
            return false;
        }

        if (($value = $this->getFieldValue(FIELD_GUID)) === false) {
            static::raiseError(__CLASS__ .'::getFieldValue() returned false!');
            return false;
        }

        return $value;
    }

    /**
     * set FIELD_GUID to the value $guid
     *
     * @param string $guid
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function setGuid($guid)
    {
        global $thallium;

        if (!isset($guid) || empty($guid) || !is_string($guid)) {
            static::raiseError(__METHOD__ .'(), $guid parameter is invalid!');
            return false;
        }

        if (!$thallium->isValidGuidSyntax($guid)) {
            static::raiseError(get_class($thallium) .'::isValidGuidSyntax() returned false!');
            return false;
        }

        $this->model_values[FIELD_GUID] = $guid;
        return true;
    }

    /**
     * returns true if this model has fields configured.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public static function hasModelFields()
    {
        $called_class = get_called_class();

        if (!property_exists($called_class, 'model_fields')) {
            return false;
        }

        if (empty($called_class::$model_fields) ||
            !is_array($called_class::$model_fields)
        ) {
            return false;
        }

        return true;
    }

    /**
     * return an array of all fields
     *
     * @param bool $no_virtual
     * @return array
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function getModelFields($no_virtual = false)
    {
        if (!static::hasModelFields()) {
            static::raiseError(__METHOD__ .'(), this model has no fields defined!');
            return false;
        }

        $fields = array();

        foreach (static::$model_fields as $field => $params) {
            $value = null;

            if ($this->hasFieldValue($field)) {
                if (($value = $this->getFieldValue($field)) === false) {
                    static::raiseError(__CLASS__ .'::getFieldValue() returned false!');
                    return false;
                }
            }

            $field_ary = array(
                'name' => $field,
                'value' => $value,
                'params' => $params,
            );
            $fields[$field] = $field_ary;
        }

        if (!$this->hasVirtualFields() || (isset($no_virtual) && $no_virtual === true)) {
            return $fields;
        }

        if (($virtual_fields = $this->getVirtualFields()) === false) {
            static::raiseError(__CLASS__ .'::getVirtualFields() returned false!');
            return false;
        }

        foreach ($virtual_fields as $field) {
            $value = null;

            if ($this->hasVirtualFieldValue($field)) {
                if (($value = $this->getVirtualFieldValue($field)) === false) {
                    static::raiseError(__CLASS__ .'::getVirtualFieldValue() returned false!');
                    return false;
                }
            }

            $field_ary = array(
                'name' => $field,
                'value' => $value,
                'params' => array(
                    'privacy' => 'public',
                ),
            );
            $fields[$field] = $field_ary;
        }

        return $fields;
    }

    /**
     * return all the field names.
     *
     * @param none
     * @return array
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function getFieldNames()
    {
        if (!static::hasModelFields()) {
            static::raiseError(__METHOD__ .'(), this model has no fields defined!');
            return false;
        }

        return array_keys(static::$model_fields);
    }

    /**
     * returns true if the field $field_name is declared for this model.
     * it can not be used for virtual fields!
     *
     * @param string $field_name
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public static function hasField($field_name)
    {
        if (!isset($field_name) ||
            empty($field_name) ||
            !is_string($field_name)
        ) {
            static::raiseError(__METHOD__ .'(), $field_name parameter is invalid!');
            return false;
        }

        $called_class = get_called_class();
        if (!$called_class::hasModelFields()) {
            return false;
        }

        if (!array_key_exists($field_name, $called_class::$model_fields)) {
            return false;
        }

        return true;
    }

    /**
     * returns the column prefix.
     *
     * @param none
     * @return string
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function getFieldPrefix()
    {
        if (!isset(static::$model_column_prefix) ||
            empty(static::$model_column_prefix) ||
            !is_string(static::$model_column_prefix)
        ) {
            static::raiseError(__METHOD__ .'(), column name is not set!');
            return false;
        }

        return static::$model_column_prefix;
    }

    /**
     * returns true if the model is new and has not been saved to
     * database yet.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function isNew()
    {
        if (isset($this->id) && !empty($this->id)) {
            return false;
        }

        return true;
    }

    /**
     * raises an error by using the ExceptionController.
     *
     * @param string $string
     * @param bool $stop_execution
     * @param object|null $exception
     * @return void
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected static function raiseError($string, $stop_execution = false, $exception = null)
    {
        global $thallium;

        $thallium::raiseError(
            $string,
            $stop_execution,
            $exception
        );

        return;
    }

    /**
     * returns true if there are virtual fields configured.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function hasVirtualFields()
    {
        if (empty($this->model_virtual_fields)) {
            return true;
        }

        return true;
    }

    /**
     * returns true if the specified virtual field has a value.
     *
     * @param string $field
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function hasVirtualFieldValue($field)
    {
        if (!isset($field) && empty($field) && !is_string($field)) {
            static::raіseError(__METHOD__. '(), $field parameter is invalid!');
            return false;
        }

        if (!$this->hasVirtualField($field)) {
            static::raiseError(__CLASS__ .'::hasVirtualField() returned false!');
            return false;
        }

        if (($method_name = $this->getVirtualFieldGetMethod($field)) === false) {
            static::raiseError(__CLASS__ .'::getVirtualFieldGetMethod() returned false!');
            return false;
        }

        if (($value = call_user_func(array($this, $method_name))) === false) {
            static::raiseError(__CLASS__ .'::'. $method_name .'() returned false!');
            return false;
        }

        if (!isset($value) || empty($value)) {
            return false;
        }

        return true;
    }

    /**
     * returns the value of the specified virtual field.
     *
     * @param string $field
     * @return mixed
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function getVirtualFieldValue($field)
    {
        if (!isset($field) && empty($field) && !is_string($field)) {
            static::raіseError(__METHOD__. '(), $field parameter is invalid!');
            return false;
        }

        if (!$this->hasVirtualFieldValue($field)) {
            static::raiseError(__CLASS__ .'::hasVirtualFieldValue() returned false!');
            return false;
        }

        if (($method_name = $this->getVirtualFieldGetMethod($field)) === false) {
            static::raiseError(__CLASS__ .'::getVirtualFieldGetMethod() returned false!');
            return false;
        }

        if (($value = call_user_func(array($this, $method_name))) === false) {
            static::raiseError(__CLASS__ ."::{$method_name}() returned false!", true);
            return;
        }

        return $value;
    }

    /**
     * sets the value for the specified virtual field.
     *
     * @param string $field
     * @param mixed $value
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function setVirtualFieldValue($field, $value)
    {
        if (!isset($field) && empty($field) && !is_string($field)) {
            static::raіseError(__METHOD__. '(), $field parameter is invalid!');
            return false;
        }

        if (!$this->hasVirtualField($field)) {
            static::raiseError(__CLASS__ .'::hasVirtualField() returned false!');
            return false;
        }

        if (($method_name = $this->getVirtualFieldSetMethod($field)) === false) {
            static::raiseError(__CLASS__ .'::getVirtualFieldSetMethod() returned false!');
            return false;
        }

        if (($retval = call_user_func(array($this, $method_name), $value)) === false) {
            static::raiseError(__CLASS__ ."::{$method_name}() returned false!", true);
            return;
        }

        return $retval;
    }

    /**
     * returns the getXxx() method for the specified virtual field
     *
     * @param string $field
     * @return string
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function getVirtualFieldGetMethod($field)
    {
        if (!isset($field) || empty($field) || !is_string($field)) {
            static::raiseError(__METHOD__ .'(), $field parameter is invalid!');
            return false;
        }

        if (!$this->hasVirtualField($field)) {
            static::raiseError(__CLASS__ .'::hasVirtualField() returned false!');
            return false;
        }

        $method_name = sprintf('get%s', ucwords(strtolower($field)));

        if (!method_exists($this, $method_name) ||
            !is_callable(array($this, $method_name))
        ) {
            static::raiseError(__METHOD__ .'(), there is not callable get-method for that field!');
            return false;
        }

        return $method_name;
    }

    /**
     * returns the setXxx() method for the specified virtual field
     *
     * @param string $field
     * @return string
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function getVirtualFieldSetMethod($field)
    {
        if (!isset($field) || empty($field) || !is_string($field)) {
            static::raiseError(__METHOD__ .'(), $field parameter is invalid!');
            return false;
        }

        if (!$this->hasVirtualField($field)) {
            static::raiseError(__CLASS__ .'::hasVirtualField() returned false!');
            return false;
        }

        $method_name = sprintf('set%s', ucwords(strtolower($field)));

        if (!method_exists($this, $method_name) ||
            !is_callable(array($this, $method_name))
        ) {
            static::raiseError(__METHOD__ .'(), there is not callable set-method for that field!');
            return false;
        }

        return $method_name;
    }

    /**
     * return all virtual fields.
     *
     * @param none
     * @return array
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function getVirtualFields()
    {
        if (!$this->hasVirtualFields()) {
            static::raiseError(__CLASS__ .'::hasVirtualFields() returned false!');
            return false;
        }

        return $this->model_virtual_fields;
    }

    /**
     * returns true if the specified virtual field exists.
     *
     * @param string $vfield
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function hasVirtualField($vfield)
    {
        if (!isset($vfield) || empty($vfield) || !is_string($vfield)) {
            static::raiseError(__METHOD__ .'(), $vfield parameter is invalid!');
            return false;
        }

        if (!in_array($vfield, $this->model_virtual_fields)) {
            return false;
        }

        return true;
    }

    /**
     * registers a new virtual field to the model.
     *
     * @param string $vfield
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function addVirtualField($vfield)
    {
        if (!isset($vfield) || empty($vfield) || !is_string($vfield)) {
            static::raiseError(__METHOD__ .'(), $vfield parameter is invalid!');
            return false;
        }

        if ($this->hasVirtualField($vfield)) {
            return true;
        }

        array_push($this->model_virtual_fields, $vfield);
        return true;
    }

    /**
     * if a model has items, it returns all the items keys as array.
     *
     * @param none
     * @return array|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getItemsKeys()
    {
        if (!static::hasModelItems()) {
            static::raiseError(__METHOD__ .'(), model '. __CLASS__ .' is not declared to have items!');
            return false;
        }

        if (!$this->hasItems()) {
            static::raiseError(__CLASS__ .'::hasItems() returned false!');
            return false;
        }

        return array_keys($this->model_items);
    }

    /**
     * if a model has items, it returns all the items as array.
     *
     * @param int|null $offset
     * @param int|null $limit
     * @param array|null $filter
     * @return array|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getItems($offset = null, $limit = null, $filter = null)
    {
        if (!static::hasModelItems()) {
            static::raiseError(__METHOD__ .'(), model '. __CLASS__ .' is not declared to have items!');
            return false;
        }

        if (!$this->hasItems()) {
            static::raiseError(__CLASS__ .'::hasItems() returned false!');
            return false;
        }

        if (($keys = $this->getItemsKeys()) === false) {
            static::raiseError(__CLASS__ .'::getItemsKeys() returned false!');
            return false;
        }

        if (!isset($keys) || empty($keys) || !is_array($keys)) {
            static::raiseError(__CLASS__ .'::getItemsKeys() returned false!');
            return false;
        }

        if (isset($offset) &&
            !is_null($offset) &&
            !is_int($offset)
        ) {
            static::raiseError(__METHOD__ .'(), $offset parameter is invalid!');
            return false;
        } elseif (!isset($offset) || is_null($offset)) {
            $offset = 0;
        }

        if (isset($limit) &&
            !is_null($limit) &&
            !is_int($limit)
        ) {
            static::raiseError(__METHOD__ .'(), $limit parameter is invalid!');
            return false;
        }

        $keys = array_slice(
            $keys,
            $offset,
            $limit,
            true /* preserve_keys on */
        );

        if (!isset($keys) || empty($keys) || !is_array($keys)) {
            return array();
        }

        if (count($keys) > static::$model_bulk_load_limit) {
            if (!$this->bulkLoad($keys)) {
                static::raiseError(__CLASS__ .'::buldLoad() returned false!');
                return false;
            }
        }

        $result = array();

        foreach ($keys as $key) {
            if (($item = $this->getItem($key)) === false) {
                static::raiseError(__CLASS__ .'::getItem() returned false!');
                return false;
            }
            array_push($result, $item);
        }

        if (!isset($result) || empty($result) || !is_array($result)) {
            static::raiseError(__METHOD__ .'(), no items retrieved!');
            return false;
        }

        if (!isset($filter) || is_null($filter)) {
            return $result;
        }

        if (!is_array($filter)) {
            static::raiseError(__METHOD__ .'(), $filter parameter is invalid!');
            return false;
        }

        if (($items = $this->filterItems($result, $filter)) === false) {
            static::raiseError(__CLASS__ .'::filterItems() returned false!');
            return false;
        }

        return $items;
    }

    /**
     * filters a list of items by the provided filter.
     *
     * @param array $items
     * @param array $filter
     * @return array|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function filterItems($items, $filter)
    {
        if (!isset($items) || empty($items) || !is_array($items)) {
            static::raiseError(__METHOD__ .'(), $items parameter is invalid!');
            return false;
        }

        if (!isset($filter) || empty($filter) || !is_array($filter)) {
            static::raiseError(__METHOD__ .'(), $filter parameter is invalid!');
            return false;
        }

        if (!static::validateItemsFilter($filter)) {
            static::raiseError(__CLASS__ .'::validateItemsFilter() returned false!');
            return false;
        }

        $result = array();
        $hits = array();
        $hits_required = count($filter);

        foreach ($items as $key => $item) {
            if (!isset($item) || empty($item) || (!is_object($item) && !is_array($item))) {
                static::raiseError(__METHOD__ .'(), $items parameter contains an invalid іtem!');
                return false;
            }
            if (!array_key_exists($key, $hits) || !isset($hits[$key])) {
                $hits[$key] = 0;
            }
            foreach ($filter as $field => $pattern) {
                /* use the lookup-index */
                if (isset($this->model_items_lookup_index[$field]) &&
                    isset($this->model_items_lookup_index[$field][$key]) &&
                    $this->model_items_lookup_index[$field][$key] === $pattern
                ) {
                    $hits[$key]++;
                    continue;
                }

                if (!$item::hasField($field)) {
                    static::raiseError(__METHOD__ .'(), $filter parameter refers an unknown field!');
                    return false;
                }
                if (($value = $item->getFieldValue($field)) === false) {
                    static::raiseError(get_class($item) .'::getFieldValue() returned false!');
                    return false;
                }
                if ($value === $pattern) {
                    $hits[$key]++;
                }
            }
        }

        foreach ($hits as $key => $hits_present) {
            if ($hits_present !== $hits_required) {
                continue;
            }
            $result[$key] = $items[$key];
        }

        return $result;
    }

    /**
     * legacy method, has been replaaced by hasModelItems()
     *
     * @param string $field
     * @return string
     * @throws \Thallium\Controllers\ExceptionController
     * @deprecated
     * @todo remove after 31.12.2016
     */
    final public static function isHavingItems()
    {
        error_log(__METHOD__ .'(), legacy isHavingItems() has been called, '
            .'update your application to hasModelItems() to avoid this message.');

        return static::hasModelItems();
    }

    /**
     * returns true if the model is configured to have items.
     *
     * @param string $field
     * @return string
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public static function hasModelItems()
    {
        $called_class = get_called_class();

        if (!property_exists($called_class, 'model_has_items')) {
            return false;
        }

        if (empty($called_class::$model_has_items) ||
            !is_bool($called_class::$model_has_items) ||
            !$called_class::$model_has_items
        ) {
            return false;
        }

        return true;
    }

    /**
     * returns true if the model actually has items.
     * contrary to hasModelItems() which only checks if the
     * model is configured to have items at all.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function hasItems()
    {
        $called_class = get_called_class();
        if (!$called_class::hasModelItems()) {
            static::raiseError(__METHOD__ ."(), model {$called_class} is not declared to have items!", true);
            return false;
        }

        if (!isset($this->model_items) ||
            empty($this->model_items) ||
            !is_array($this->model_items)
        ) {
            return false;
        }

        return true;
    }

    /**
     * adds an item
     *
     * @param object $item
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function addItem($item)
    {
        if (!static::hasModelItems()) {
            static::raiseError(__METHOD__ .'(), model '. __CLASS__ .' is not declared to have items!');
            return false;
        }

        if (!isset($item) || empty($item)) {
            static::raiseError(__METHOD__ .'(), $item parameter is invalid!');
            return false;
        }

        if (!is_array($item) && !is_object($item)) {
            static::raiseError(__METHOD__ .'(), $item type is not supported!');
            return false;
        }

        if (is_array($item)) {
            if (!array_key_exists(FIELD_MODEL, $item)) {
                static::raiseError(__METHOD__ .'(), $item misses FIELD_MODEL key!');
                return false;
            }
            if (!array_key_exists(FIELD_IDX, $item)) {
                static::raiseError(__METHOD__ .'(), $item misses FIELD_IDX key!');
                return false;
            }
            if (!array_key_exists(FIELD_GUID, $item)) {
                static::raiseError(__METHOD__ .'(), $item misses FIELD_GUID key!');
                return false;
            }
            if (!array_key_exists(FIELD_IDX, $item) ||
                !isset($item[FIELD_IDX]) ||
                empty($item[FIELD_IDX]) ||
                !is_numeric($item[FIELD_IDX])
            ) {
                static::raiseError(__METHOD__ .'(), $item FIELD_IDX is invalid!');
                return false;
            }
            if (!array_key_exists(FIELD_GUID, $item) ||
                !isset($item[FIELD_GUID]) ||
                empty($item[FIELD_GUID]) ||
                !is_string($item[FIELD_GUID])
            ) {
                static::raiseError(__METHOD__ .'(), $item FIELD_GUID is invalid!');
                return false;
            }
            if (!array_key_exists(FIELD_MODEL, $item) ||
                !isset($item[FIELD_MODEL]) ||
                empty($item[FIELD_MODEL]) ||
                !is_string($item[FIELD_MODEL])
            ) {
                static::raiseError(__METHOD__ .'(), $item FIELD_MODEL is invalid!');
                return false;
            }
            $idx = $item[FIELD_IDX];
            $model = $item[FIELD_MODEL];
            unset($item[FIELD_MODEL]);
        } elseif (is_object($item)) {
            if (!method_exists($item, 'getIdx') || !is_callable(array(&$item, 'getIdx'))) {
                static::raiseError(__METHOD__ .'(), item model '. get_class($item) .' has no getIdx() method!');
                return false;
            }
            if (!method_exists($item, 'getGuid') || !is_callable(array(&$item, 'getGuid'))) {
                static::raiseError(__METHOD__ .'(), item model '. get_class($item) .' has no getGuid() method!');
                return false;
            }
            if (($idx = $item->getIdx()) === false) {
                static::raiseError(get_class($item) .'::getIdx() returned false!');
                return false;
            }
            if (($model = $item::getModelName()) === false) {
                static::raiseError(get_class($item) .'::getModelName() returned false!');
                return false;
            }
        }

        if (array_key_exists($idx, $this->model_items)) {
            static::raiseError(__METHOD__ ."(), item with key {$idx} does already exist!");
            return false;
        }

        if (!$this->setItemModel($idx, $model)) {
            static::raiseError(__CLASS__ .'::setItemModel() returned false!');
            return false;
        }

        if (!$this->setItemData($idx, $item, false)) {
            static::raiseError(__CLASS__ .'::setItemData() returned false!');
            return false;
        }

        return true;
    }

    /**
     * removes an item by clearing its data.
     *
     * @param int $idx,
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function removeItem($idx)
    {
        if (!isset($idx) || (!is_int($idx) && !is_numeric($idx))) {
            static::raiseError(__METHOD__ .'(), $idx parameter is invalid!');
            return false;
        }

        if (!static::hasModelItems()) {
            static::raiseError(__CLASS__ .'::hasModelItems() returned false!');
            return false;
        }

        if (!$this->hasItem($idx)) {
            static::raiseError(__CLASS__ .'::hasItem() returned false!');
            return false;
        }

        unset($this->model_items[$idx]);
        return true;
    }

    /**
     * returns the requested item
     *
     * @param int $idx
     * @param bool $reset
     * @param bool $allow_cached
     * @return mixed
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getItem($idx, $reset = true, $allow_cached = true)
    {
        global $cache;

        if (!isset($idx) || empty($idx) || (!is_string($idx) && !is_numeric($idx))) {
            static::raiseError(__METHOD__ .'(), $idx parameter is invalid!');
            return false;
        }

        if (!$this->hasItem($idx)) {
            static::raiseError(__CLASS__ .'::hasItem() returned false!');
            return false;
        }

        if (!$this->hasItemModel($idx)) {
            static::raiseError(__CLASS__ .'::hasItemModel() returned false!');
            return false;
        }

        if (($item_model = $this->getItemModel($idx)) === false) {
            static::raiseError(__CLASS__ .'::getItemModel() returned false!');
            return false;
        }

        $cache_key = sprintf("%s_%s", $item_model, $idx);

        if (isset($allow_cached) &&
            $allow_cached === true &&
            $cache->has($cache_key)
        ) {
            if (($item = $cache->get($cache_key)) === false) {
                static::raiseError(get_class($cache) .'::get() returned false!');
                return false;
            }
            if (isset($reset) && $reset === true) {
                if (!$item->resetFields()) {
                    static::raiseError(get_class($item) .'::resetFields() returned false!');
                    return false;
                }
            }
            return $item;
        }

        /* item fields data may be already available thru bulkloading. */
        if ($this->hasItemData($idx)) {
            if (($item_data = $this->getItemData($idx)) === false) {
                static::raiseError(__CLASS__ .'::getItemData() returned false!');
                return false;
            }

            if (!isset($item_data) ||
                empty($item_data) ||
                !is_array($item_data)
            ) {
                static::raiseError(__CLASS__ .'::getItemData() returned invalid data!');
                return flase;
            }

            try {
                $item = new $item_model;
            } catch (\Exception $e) {
                static::raiseError(__METHOD__ ."(), failed to load {$item_model}!", false, $e);
                return false;
            }

            if (!$item->flood($item_data)) {
                static::raiseError(get_class($item) .'::flood() returned false!');
                return false;
            }
        } elseif (!$this->hasItemData($idx)) {
            try {
                $item = new $item_model(array(
                    FIELD_IDX => $idx
                ));
            } catch (\Exception $e) {
                static::raiseError(__METHOD__ ."(), failed to load {$item_model}!", false, $e);
                return false;
            }
        }

        if (!isset($item) || empty($item)) {
            static::raiseError(__METHOD__ .'(), no valid item found!');
            return false;
        }

        if (!$cache->add($item, $cache_key)) {
            static::raiseError(get_class($cache) .'::add() returned false!');
            return false;
        }

        if (!$this->updateItemsLookupCache($item)) {
            static::raiseError(__CLASS__ .'::updateItemsLookupCache() returned false!');
            return false;
        }

        return $item;
    }

    /**
     * returns true if the provided item exists.
     *
     * @param int|string $idx
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function hasItem($idx)
    {
        global $thallium;

        if (!isset($idx) || (!is_string($idx) && !is_numeric($idx))) {
            static::raiseError(__METHOD__ .'(), $idx parameter is invalid!');
            return false;
        }

        if (is_numeric($idx)) {
            return array_key_exists($idx, $this->model_items);
        }

        if (!$thallium->isModelIdentifier($idx)) {
            return false;
        }

        if (!$this->hasItems()) {
            return false;
        }

        if (($items = $this->getItems()) === false) {
            static::raiseError(__CLASS__ .'::getItems() returned false!');
            return false;
        }

        foreach ($items as $item) {
            if (strval($item) === $idx) {
                return true;
            }
        }

        return false;
    }

    /**
     * returns true if the provided item has data (= field values)
     *
     * @param string|int $key
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function hasItemData($key)
    {
        if (!isset($key) ||
            empty($key) ||
            (!is_integer($key) && !is_numeric($key))
        ) {
            static::raiseError(__METHOD__ .'(), $key parameter is invalid!');
            return false;
        }

        if (!$this->hasItem($key)) {
            static::raiseError(__CLASS__ .'::hasItem() returned false!');
            return false;
        }

        if (!array_key_exists(FIELD_INT, $this->model_items[$key]) ||
            !isset($this->model_items[$key][FIELD_INIT]) ||
            !is_bool($this->model_items[$key][FIELD_INIT]) ||
            !$this->model_items[$key][FIELD_INIT]
        ) {
            return false;
        }

        return true;
    }

    /**
     * returns the model that is used for items.
     *
     * @param string|int $key
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function hasItemModel($key)
    {
        if (!isset($key) ||
            empty($key) ||
            (!is_integer($key) && !is_numeric($key))
        ) {
            static::raiseError(__METHOD__ .'(), $key parameter is invalid!');
            return false;
        }

        if (!$this->hasItem($key)) {
            static::raiseError(__CLASS__ .'::hasItem() returned false!');
            return false;
        }

        if (!isset($this->model_items[$key][FIELD_MODEL]) ||
            empty($this->model_items[$key][FIELD_MODEL]) ||
            !is_string($this->model_items[$key][FIELD_MODEL])
        ) {
            return false;
        }

        return true;
    }

    /**
     * returns the model if an specific item
     *
     * @param string|int $key
     * @return string
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function getItemModel($key)
    {
        if (!isset($key) ||
            empty($key) ||
            (!is_integer($key) && !is_numeric($key))
        ) {
            static::raiseError(__METHOD__ .'(), $key parameter is invalid!');
            return false;
        }

        if (!$this->hasItemModel($key)) {
            static::raiseError(__CLASS__ .'::hasItemModel() returned false!');
            return false;
        }

        if (!array_key_exists(FIELD_MODEL, $this->model_items[$key])) {
            static::raiseError(__METHOD__ .'(), item contains no model key!');
            return false;
        }

        return $this->model_items[$key][FIELD_MODEL];
    }

    /**
     * sets the model of a specific item
     *
     * @param string $field
     * @param string $model
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function setItemModel($key, $model)
    {
        if (!isset($key) ||
            empty($key) ||
            (!is_integer($key) && !is_numeric($key))
        ) {
            static::raiseError(__METHOD__ .'(), $key parameter is invalid!');
            return false;
        }

        if (!isset($model) || empty($model) || !is_string($model)) {
            static::raiseError(__METHOD__ .'(), $model parameter is invalid!');
            return false;
        }

        if (!$this->hasItem($key)) {
            $this->model_items[$key] = array();
        }

        $this->model_items[$key][FIELD_MODEL] = $model;
        return true;
    }

    /**
     * returns the field values of a specific item.
     *
     * @param string|int $key
     * @return mixed
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function getItemData($key)
    {
        if (!isset($key) ||
            empty($key) ||
            (!is_integer($key) && !is_numeric($key))
        ) {
            static::raiseError(__METHOD__ .'(), $key parameter is invalid!');
            return false;
        }

        if (!$this->hasItemData($key)) {
            static::raiseError(__CLASS__ .'::hasItemData() returned false!');
            return false;
        }

        return $this->model_items[$key][FIELD_DATA];
    }

    /**
     * sets data for a specific item.
     *
     * @param string $key
     * @param array $data
     * @param bool $init
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function setItemData($key, $data, $init = true)
    {
        if (!isset($key) ||
            empty($key) ||
            (!is_integer($key) && !is_numeric($key))
        ) {
            static::raiseError(__METHOD__ .'(), $key parameter is invalid!');
            return false;
        }

        if (!isset($data) ||
            empty($data) ||
            (!is_array($data) && !is_object($data))
        ) {
            static::raiseError(__METHOD__ .'(), $data parameter is invalid!');
            return false;
        }

        if (is_object($data)) {
            if (!method_exists($data, 'getModelFields')) {
                static::raiseError(__METHOD__ .'(), \$data provides no getModelFields() method!');
                return false;
            }
            if (($fields = $data->getModelFields()) === false) {
                static::raiseError(get_class($data) .'::getModelFields() returned false!');
                return false;
            }

            array_walk($fields, function (&$item, $idx) {
                if (!array_key_exists('name', $item) ||
                    !array_key_exists('value', $item)
                ) {
                    static::raiseError(__METHOD__ .'(), incomplete item received!');
                    return false;
                }
                $item = $item['value'];
            });
            $data = $fields;
        }

        if (!$this->hasItem($key)) {
            $this->model_items[$key] = array();
        }

        $this->model_items[$key][FIELD_DATA] = $data;
        $this->model_items[$key][FIELD_INIT] = $init;
        return true;
    }

    /**
     * returns the number of items.
     *
     * @param none
     * @return int|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getItemsCount()
    {
        if (!$this->hasItems()) {
            return false;
        }

        if (!isset($this->model_items)) {
            return false;
        }

        return count($this->model_items);
    }

    /**
     * returns true if this is a new model.
     * contrary to isNew() this one returns true,
     * if no loading-parameters have been provided
     * to the constructor by $model_load_by.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function isNewModel()
    {
        if (isset($this->model_load_by) &&
            !empty($this->model_load_by) &&
            is_array($this->model_load_by)
        ) {
            return false;
        }

        return true;
    }

    /**
     * returns the type of a specific field
     *
     * @param string $field_name
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public static function getFieldType($field_name)
    {
        if (!isset($field_name) || empty($field_name) || !is_string($field_name)) {
            static::raiseError(__METHOD__ .'(), $field_name parameter is invalid!');
            return false;
        }

        if (!static::hasField($field_name)) {
            static::raiseError(__METHOD__ ."(), model has no field {$field_name}!");
            return false;
        }

        return static::$model_fields[$field_name][FIELD_TYPE];
    }

    /**
     * returns the length of a specific field.
     *
     * @param string $field_name
     * @return int|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public static function getFieldLength($field_name)
    {
        if (!isset($field_name) || empty($field_name) || !is_string($field_name)) {
            static::raiseError(__METHOD__ .'(), $field_name parameter is invalid!');
            return false;
        }

        if (!static::hasField($field_name)) {
            static::raiseError(__METHOD__ ."(), model has no field {$field_name}!");
            return false;
        }

        if (!static::hasFieldLength($field_name) && static::getFieldType($field_name) === FIELD_STRING) {
            return 255;
        }

        return static::$model_fields[$field_name][FIELD_LENGTH];
    }

    /**
     * returns true if the field has a field-length declared.
     *
     * @param string $field_name
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public static function hasFieldLength($field_name)
    {
        if (!isset($field_name) || empty($field_name) || !is_string($field_name)) {
            static::raiseError(__METHOD__ .'(), $field_name parameter is invalid!');
            return false;
        }

        if (!static::hasField($field_name)) {
            static::raiseError(__METHOD__ ."(), model has no field {$field_name}!");
            return false;
        }

        if (!array_key_exists(FIELD_LENGTH, static::$model_fields[$field_name])) {
            return false;
        }

        return true;
    }

    /**
     * returns the table name that is used to store this model into.
     *
     * @param none
     * @return string
     * @throws \Thallium\Controllers\ExceptionController
     */
    public static function getTableName()
    {
        return sprintf("TABLEPREFIX%s", static::$model_table_name);
    }

    /**
     * returns the field name if a column-name is provided.
     *
     * @param string $column
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public static function getFieldNameFromColumn($column)
    {
        if (!isset($column) || empty($column) || !is_string($column)) {
            static::raiseError(__METHOD__ .'(), $column parameter is invalid!');
            return false;
        }

        if (strpos($column, static::$model_column_prefix .'_') === false) {
            return $column;
        }

        $field_name = str_replace(static::$model_column_prefix .'_', '', $column);

        if (!static::hasField($field_name) &&
            (isset($this) &&
            !empty($this) &&
            $this->hasVirtualFields() &&
            !$this->hasVirtualField($field_name))
        ) {
            static::raiseError(__CLASS__ .'::hasField() returned false!');
            return false;
        }

        return $field_name;
    }

    /**
     * validate if the provided $value is ok for the field $field.
     *
     * @param string $field
     * @param mixed $value
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public static function validateField($field, $value)
    {
        global $thallium;

        if (!isset($field) || empty($field) || !is_string($field)) {
            static::raiseError(__METHOD__ .'(), $field parameter is invalid!');
            return false;
        }

        if (!static::hasModelFields()) {
            static::raiseError(__CLASS__ .'::hasModelFields() returned false!');
            return false;
        }

        if (($type = static::getFieldType($field)) === false) {
            static::raiseError(__CLASS__ .'::getFieldType() returned false!');
            return false;
        }

        // empty values we can not check
        if (empty($value)) {
            return true;
        }

        switch ($type) {
            case FIELD_STRING:
                if (!is_string($value)) {
                    return false;
                }
                return true;
                break;
            case FIELD_INT:
                if (!is_numeric($value) || !is_int((int) $value)) {
                    return false;
                }
                return true;
                break;
            case FIELD_BOOL:
                if (!is_bool($value)) {
                    return false;
                }
                return true;
                break;
            case FIELD_YESNO:
                if (!in_array($value, array('yes', 'no', 'Y', 'N'))) {
                    return false;
                }
                return true;
                break;
            case FIELD_TIMESTAMP:
                if (is_float((float) $value)) {
                    if ((float) $value >= PHP_INT_MAX || (float) $value <= ~PHP_INT_MAX) {
                        return false;
                    }
                    return true;
                } elseif (is_int((int) $value)) {
                    if ((int) $value >= PHP_INT_MAX || (int) $value <= ~PHP_INT_MAX) {
                        return false;
                    }
                    return true;
                } elseif (is_string($value)) {
                    if (strtotime($value) === false) {
                        return false;
                    }
                    return true;
                }
                static::raiseError(__METHOD__ .'(), unsupported timestamp type found!');
                return false;
                break;
            case FIELD_DATE:
                if ($value !== "0000-00-00" &&
                    strtotime($value) === false
                ) {
                    return false;
                }
                return true;
                break;
            case FIELD_GUID:
                if (!$thallium->isValidGuidSyntax($value)) {
                    return false;
                }
                return true;
                break;
            default:
                static::raiseError(__METHOD__ ."(), unsupported type {$type} received!");
                return false;
                break;
        }

        return false;
    }

    /**
     * validates the provided items-filter.
     *
     * @param array $field
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected static function validateItemsFilter($filter)
    {
        if (!isset($filter) || empty($filter) || !is_array($filter)) {
            static::raiseError(__METHOD__ .'(), $filter parameter is invalid!');
            return false;
        }

        foreach ($filter as $field => $pattern) {
            if (!isset($field) || empty($field) || !is_string($field)) {
                static::raiseError(__METHOD__ .'(), $filter parameter contains an invalid $field name!');
                return false;
            }
            if (!isset($pattern) || empty($pattern) || (!is_string($pattern) && !is_int($pattern))) {
                static::raiseError(__METHOD__ .'(), $filter parameter contains an invalid $pattern!' . $pattern);
                return false;
            }
        }

        return true;
    }

    /**
     * if the model has items, flush the database table and clean out all items by this.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function flush()
    {
        if (!static::hasModelItems()) {
            static::raiseError(__CLASS__ .'::hasModelItems() returned false!');
            return false;
        }

        if (!$this->delete()) {
            static::raiseError(__CLASS__ .'::delete() returned false!');
            return false;
        }

        if (!$this->flushTable()) {
            static::raiseError(__CLASS__ .'::flushTable() returned false!');
            return false;
        }

        return true;
    }

    /**
     * flushes the database table.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function flushTable()
    {
        global $db;

        try {
            $db->query(sprintf(
                "TRUNCATE TABLE TABLEPREFIX%s",
                static::$model_table_name
            ));
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), SQL command TRUNCATE TABLE failed!', false, $e);
            return false;
        }

        return true;
    }

    /**
     * returns true if the field has a setXxxx() method.
     *
     * @param string $field
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function hasFieldSetMethod($field)
    {
        if (!isset($field) || empty($field) || !is_string($field)) {
            static::raiseError(__METHOD__ .'(), $field parameter is invalid!');
            return false;
        }

        if (!static::hasModelFields()) {
            static::raiseError(__METHOD__ .'(), this model has no fields!');
            return false;
        }

        if (!static::hasField($field)) {
            static::raiseError(__METHOD__ .'(), model does not provide the requested field!');
            return false;
        }

        if (!isset(static::$model_fields[$field]) ||
            empty(static::$model_fields[$field]) ||
            !is_array(static::$model_fields[$field])
        ) {
            static::raiseError(__METHOD__ .'(), $model_fields does not contain requested field!');
            return false;
        }

        if (!isset(static::$model_fields[$field][FIELD_SET]) ||
            empty(static::$model_fields[$field][FIELD_SET]) ||
            !is_string(static::$model_fields[$field][FIELD_SET]) ||
            !method_exists(get_called_class(), static::$model_fields[$field][FIELD_SET])
        ) {
            return false;
        }

        return true;
    }

    /**
     * returns the fields setXxx() method.
     *
     * @param string $field
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function getFieldSetMethod($field)
    {
        if (!isset($field) || empty($field) || !is_string($field)) {
            static::raiseError(__METHOD__ .'(), $field parameter is invalid!');
            return false;
        }

        if (!static::hasFieldSetMethod($field)) {
            static::raiseError(__CLASS__ .'::hasFieldSetMethod() returned false!');
            return false;
        }

        return static::$model_fields[$field][FIELD_SET];
    }

    /**
     * returns true if the specified field has a getXxx() method.
     *
     * @param string $field
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function hasFieldGetMethod($field)
    {
        if (!isset($field) || empty($field) || !is_string($field)) {
            static::raiseError(__METHOD__ .'(), $field parameter is invalid!');
            return false;
        }

        if (!static::hasModelFields()) {
            static::raiseError(__METHOD__ .'(), this model has no fields!');
            return false;
        }

        if (!static::hasField($field)) {
            static::raiseError(__METHOD__ .'(), model does not provide the requested field!');
            return false;
        }

        if (!isset(static::$model_fields[$field]) ||
            empty(static::$model_fields[$field]) ||
            !is_array(static::$model_fields[$field])
        ) {
            static::raiseError(__METHOD__ .'(), $model_fields does not contain requested field!');
            return false;
        }

        if (!isset(static::$model_fields[$field][FIELD_GET]) ||
            empty(static::$model_fields[$field][FIELD_GET]) ||
            !is_string(static::$model_fields[$field][FIELD_GET]) ||
            !method_exists(get_called_class(), static::$model_fields[$field][FIELD_GET])
        ) {
            return false;
        }

        return true;
    }

    /**
     * returns the getXxx() method for the specified field.
     *
     * @param string $field
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function getFieldGetMethod($field)
    {
        if (!isset($field) || empty($field) || !is_string($field)) {
            static::raiseError(__METHOD__ .'(), $field parameter is invalid!');
            return false;
        }

        if (!static::hasFieldGetMethod($field)) {
            static::raiseError(__CLASS__ .'::hasFieldGetMethod() returned false!');
            return false;
        }

        return static::$model_fields[$field][FIELD_GET];
    }

    /**
     * returns true, if the specified $field has a value.
     *
     * @param string $field
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function hasFieldValue($field)
    {
        if (!isset($field) || empty($field) || !is_string($field)) {
            static::raiseError(__METHOD__ .'(), $field parameter is invalid!');
            return false;
        }

        if (!static::hasModelFields() && isset($this) && !$this->hasVirtualFields()) {
            static::raiseError(__METHOD__ .'(), this model has no fields!');
            return false;
        }

        if (!static::hasField($field) && isset($this) && !$this->hasVirtualField($field)) {
            static::raiseError(__METHOD__ .'(), this model has not that field!');
            return false;
        }

        if (!static::hasField($field) && !isset($this)) {
            static::raiseError(__METHOD__ .'(), do not know how to locate that field!');
            return false;
        }

        if (static::hasField($field)) {
            if (!array_key_exists($field, $this->model_values) ||
                !isset($this->model_values[$field]) ||
                empty($this->model_values[$field])
            ) {
                return false;
            }
        } elseif (isset($this) && $this->hasVirtualField($field)) {
            if (!$this->hasVirtualFieldValue($field)) {
                return false;
            }
        }

        return true;
    }

    /**
     * sets the value of the specified field.
     *
     * @param string $field
     * @param mixed $value
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function setFieldValue($field, $value)
    {
        if (!isset($field) || empty($field) || !is_string($field)) {
            static::raiseError(__METHOD__ .'(), $field parameter is invalid!');
            return false;
        }

        if (!static::hasModelFields()) {
            static::raiseError(__METHOD__ .'(), this model has no fields!');
            return false;
        }

        if (!static::hasField($field) && !$this->hasVirtualField($field)) {
            static::raiseError(__METHOD__ .'(), this model has not that field!');
            return false;
        }

        if (!isset($value)) {
            if (static::hasField($field)) {
                $this->model_values[$field] = null;
            } elseif ($this->hasVirtualField($field)) {
                if (!$this->setVirtualFieldValue($field, null)) {
                    static::raiseError(__CLASS__ .'::setVirtualFieldValue() returned false!');
                    return false;
                }
            }
            return true;
        }

        // for non-virtual-fields, check their field length, if it has been declared.
        if (static::hasField($field) && $this->hasFieldLength($field)) {
            if ($this->getFieldType($field) === FIELD_STRING) {
                if (($field_length = $this->getFieldLength($field)) === false) {
                    static::raiseError(__CLASS__ .'::getFieldLength() returned false!');
                    return false;
                }
                $value_length = strlen($value);
                if ($value_length > $field_length) {
                    static::raiseError(
                        __METHOD__ ."(), values length ({$value_length}) exceeds fields length ({$field_length})!"
                    );
                    return false;
                }
            }
        }

        if (static::hasField($field)) {
            $this->model_values[$field] = $value;
            return true;
        }

        if (!$this->hasVirtualFields() || !$this->hasVirtualField($field)) {
            static::raiseError(__METHOD__ .'(), unknown how to set that field!');
            return false;
        }

        if (!$this->setVirtualFieldValue($field, $value)) {
            static::raiseError(__CLASS__ .'::setVirtualFieldValue() returned false!');
            return false;
        }

        return true;
    }

    /**
     * returns the value of the specified field.
     *
     * @param string $field
     * @return mixed
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function getFieldValue($field)
    {
        if (!isset($field) || empty($field) || !is_string($field)) {
            static::raiseError(__METHOD__ .'(), $field parameter is invalid!');
            return false;
        }

        if (!$this->hasFieldValue($field)) {
            static::raiseError(__CLASS__ .'::hasFieldValue() returned false!');
            return false;
        }

        if ($this->hasVirtualFields() && $this->hasVirtualField($field)) {
            if (($value = $this->getVirtualFieldValue($field)) === false) {
                static::raiseError(__CLASS__ .'::getVirtualFieldValue() returned false!');
                return false;
            }

            return $value;
        }

        return $this->model_values[$field];
    }

    /**
     * returns true if the field has a default value configured.
     *
     * @param string $field
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public static function hasDefaultValue($field)
    {
        if (!isset($field) || empty($field) || !is_string($field)) {
            static::raiseError(__METHOD__ .'(), $field parameter is invalid!');
            return false;
        }

        if (!static::hasModelFields()) {
            static::raiseError(__METHOD__ .'(), this model has no fields!');
            return false;
        }

        if (!static::hasField($field)) {
            static::raiseError(__METHOD__ .'(), this model has not that field!');
            return false;
        }

        if (!array_key_exists(FIELD_DEFAULT, static::$model_fields[$field])) {
            return false;
        }

        if (empty(static::$model_fields[$field][FIELD_DEFAULT])) {
            return false;
        }

        return true;
    }

    /**
     * returns the fields default value.
     *
     * @param string $field
     * @return mixed
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public static function getDefaultValue($field)
    {
        if (!isset($field) || empty($field) || !is_string($field)) {
            static::raiseError(__METHOD__ .'(), $field parameter is invalid!');
            return false;
        }

        if (!static::hasDefaultValue($field)) {
            static::raiseError(__CLASS__ .'::hasDefaultValue() returned false!');
            return false;
        }

        return static::$model_fields[$field][FIELD_DEFAULT];
    }

    /**
     * returns true, if the same object as specified by $load_by
     * is already present in database.
     *
     * @param array $load_by
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public static function exists($load_by = array())
    {
        global $db;

        if (!isset($load_by) || empty($load_by) || (!is_array($load_by) && !is_null($load_by))) {
            static::raiseError(__METHOD__ .'(), parameter $load_by has to be an array!', true);
            return;
        }

        if (($idx = static::column('idx')) === false) {
            static::raiseError(__CLASS__ .'::column() returned false!');
            return false;
        }

        $query_columns = array(
            $idx
        );

        $query_where = array();

        foreach ($load_by as $field => $value) {
            if (static::hasModelFields()) {
                if (!static::hasField($field)) {
                    static::raiseError(__CLASS__ .'::hasField() returned false!');
                    return false;
                }
            } else {
                if (!static::hasModelItems()) {
                    static::raiseError(__CLASS__ .'::hasModelItems() returned false!');
                    return false;
                }

                if (($items_model = static::getModelItemsModel(true)) === false) {
                    static::raiseError(__CLASS__ .'::getModelItemsModel() returned false!');
                    return false;
                }

                if (!$items_model::hasField($field)) {
                    static::raiseError(get_class($items_model) .'::hasField() returned false!');
                    return false;
                }
            }

            if (($column = static::column($field)) === false) {
                static::raiseError(__CLASS__ .'::column() returned false!');
                return false;
            }

            $query_where[$column] = $value;
        }

        $bind_params = array();

        if (($sql = $db->buildQuery(
            "SELECT",
            static::getTableName(),
            $query_columns,
            $query_where,
            $bind_params
        )) === false) {
            static::raiseError(get_class($db) .'::buildQuery() returned false!');
            return false;
        }

        try {
            $sth = $db->prepare($sql);
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), unable to prepare database query!');
            return false;
        }

        if (!$sth) {
            static::raiseError(get_class($db) ."::prepare() returned invalid data!");
            return false;
        }

        foreach ($bind_params as $key => $value) {
            $sth->bindParam($key, $value);
        }

        if (!$db->execute($sth, $bind_params)) {
            static::raiseError(__METHOD__ ."(), unable to execute query!");
            return false;
        }

        $num_rows = $sth->rowCount();
        $db->freeStatement($sth);

        if ($num_rows < 1) {
            return false;
        }

        if ($num_rows > 1) {
            static::raiseError(__METHOD__ .'(), more than one object found!');
            return false;
        }

        return true;
    }

    /**
     * returns true if the model is configured to have links.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public static function hasModelLinks()
    {
        if (!isset(static::$model_links) ||
            empty(static::$model_links)
        ) {
            return false;
        }

        return true;
    }

    /**
     * returns all the configured model links.
     *
     * @param none
     * @return array|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public static function getModelLinks()
    {
        if (!static::hasModelLinks()) {
            static::raiseError(__CLASS__ .'::hasModelLinks() returned false!');
            return false;
        }

        return static::$model_links;
    }

    /**
     * delete all the present links to other models.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function deleteModelLinks()
    {
        global $thallium;

        if (!static::hasModelLinks()) {
            return true;
        }

        if (($links = static::getModelLinks()) === false) {
            static::raiseError(__CLASS__ .'::getModelLinks() returned false!');
            return false;
        }

        if (empty($links)) {
            return true;
        }

        foreach ($links as $target => $my_field) {
            if (!static::hasField($my_field)) {
                static::raiseError(__CLASS__ .'::hasField() returned false!');
                return false;
            }

            // skip that link, if our field has no value right now.
            if (!$this->hasFieldValue($my_field)) {
                continue;
            }

            list($model, $field) = explode('/', $target);

            if (!isset($model) || empty($model) ||
                !isset($field) || empty($field)
            ) {
                static::raiseError(__METHOD__ .'(), encountered invalid model link!');
                return false;
            }

            if (($model_name = $thallium->getFullModelName($model)) === false) {
                static::raiseError(get_class($thallium) .'::getFullModelName() returned false!');
                return false;
            }

            if (($my_value = $this->getFieldValue($my_field)) === false) {
                static::raiseError(__CLASS__ .'::getFieldValue() returned false!');
                return false;
            }

            if (!$model_name::exists(array(
                $field => $my_value,
            ))) {
                return true;
            }

            try {
                $model = new $model_name(array(
                    $field => $my_value,
                ));
            } catch (\Exception $e) {
                static::raiseError(__METHOD__ ."(), failed to load {$model_name}!", false, $e);
                return false;
            }

            if ($model::isModelLinkModel() || $model::hasModelItems()) {
                if (!$model->delete()) {
                    static::raiseError(get_class($model) .'::delete() returned false!');
                    return false;
                }

                continue;
            }

            if (!$model::hasModelFields()) {
                static::raiseError(__METHOD__ .'(), got here of course model has no fields!');
                return false;
            }

            if (!$model->setFieldValue($field, null)) {
                static::raiseError(get_class($model) .'::setFieldValue() returned false!');
                return false;
            }

            if (!$model->save()) {
                static::raiseError(get_class($model) .'::save() returned false!');
                return false;
            }
        }

        return true;
    }

    /**
     * reset all the fields to their init values.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function resetFields()
    {
        if (!static::hasModelFields()) {
            static::raiseError(__CLASS__ .'::hasModelFields() returned false!');
            return false;
        }

        if (!isset($this->model_init_values) ||
            empty($this->model_init_values) ||
            !is_array($this->model_init_values)
        ) {
            static::raiseError(__METHOD__ .'(), no inital field values found!');
            return false;
        }

        if (!$this->update($this->model_init_values)) {
            static::raiseError(__CLASS__ .'::update() returned false!');
            return false;
        }

        return true;
    }

    /**
     * updates the items lookup cache with a new item.
     *
     * @param object $item
     * @return string
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function updateItemsLookupCache($item)
    {
        if (!isset($item) || empty($item) || !is_object($item)) {
            static::raiseError(__METHOD__ .'(), $item parameter is invalid!');
            return false;
        }

        if (!$item::hasModelFields()) {
            static::raiseError(get_class($item) .'::hasModelFields() returned false!');
            return false;
        }

        $index_fields = array(
            FIELD_IDX,
            FIELD_GUID,
        );

        if (isset(static::$model_fields_index) &&
            !empty(static::$model_fields_index) &&
            is_array(static::$model_fields_index)
        ) {
            foreach (static::$model_fields_index as $field) {
                if (!static::hasField($field)) {
                    static::raiseError(__CLASS__ .'::hasField() returned false!');
                    return false;
                }
                array_push($index_fields, $field);
            }
        }

        if (($fields = $item->getFieldNames()) === false) {
            static::raiseError(get_class($item) .'::getModelFields() returned false!');
            return false;
        }

        foreach ($fields as $field) {
            if (!in_array($field, $index_fields)) {
                continue;
            }

            if (!array_key_exists($field, $this->model_items_lookup_index) ||
                !isset($this->model_items_lookup_index[$field])
            ) {
                $this->model_items_lookup_index[$field] = array();
            }

            if (($idx = $item->getIdx()) === false) {
                static::raiseError(get_class($item) .'::getIdx() returned false!');
                return false;
            }

            if (!$item->hasFieldValue($field)) {
                continue;
            }

            if (($value = $item->getFieldValue($field)) === false) {
                static::raiseError(get_class($field) .'::getFieldValue() returned false!');
                return false;
            }

            $this->model_items_lookup_index[$field][$idx] = $value;
        }

        return true;
    }

    /**
     * returns the name of this model
     *
     * @param string $short
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public static function getModelName($short = false)
    {
        if (!isset($short) || $short === false) {
            return static::class;
        }

        $parts = explode('\\', static::class);

        return array_pop($parts);
    }

    /**
     * if model is called in a string-context, returns an unique identifier.
     *
     * @param none
     * @return string
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function __toString()
    {
        if (($model_name = static::getModelName(true)) === false) {
            trigger_error(__CLASS__ .'::getModelName() returned false!');
            return 'error';
        }

        if (!static::hasModelFields()) {
            return 'error';
        }

        if (!$this->hasIdx() || !$this->hasGuid()) {
            return 'error';
        }

        if (($idx = $this->getIdx()) === false) {
            trigger_error(__CLASS__ .'::getIdx() returend false!');
            return 'error';
        }

        if (($guid = $this->getGuid()) === false) {
            trigger_error(__CLASS__ .'::getGuid() returend false!');
            return 'error';
        }

        if (method_exists($this, 'hasName') && $this->hasName()) {
            if (($name = $this->getName()) === false) {
                trigger_error(__CLASS__ .'::getName() returned false!');
                return 'error';
            }
        }

        if (!isset($name)) {
            return sprintf('%s_%s_%s', $model_name, $idx, $guid);
        }

        return sprintf('%s_%s_%s_%s', $model_name, $name, $idx, $guid);
    }

    /**
     * return all the links this model has.
     *
     * @param bool $sorted
     * @param bool $unique
     * @param bool $recursive
     * @return array|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getModelLinkedList($sorted = false, $unique = false, $recursive = false)
    {
        if (!isset($sorted) || !is_bool($sorted)) {
            static::raiseError(__METHOD__ .'(), $sorted parameter is invalid!');
            return false;
        }

        if (!isset($unique) || !is_bool($unique)) {
            static::raiseError(__METHOD__ .'(), $unique parameter is invalid!');
            return false;
        }

        if (!isset($recursive) || !is_bool($recursive)) {
            static::raiseError(__METHOD__ .'(), $recursive parameter is invalid!');
            return false;
        }

        if (($model_links = static::getModelLinks()) === false) {
            static::raiseError(__CLASS__ .'::getModelLinks() returned false!');
            return false;
        }

        if (!is_array($model_links) || empty($model_links)) {
            return true;
        }

        $links = array();

        foreach ($model_links as $target => $field) {
            if (!$this->hasFieldValue($field)) {
                continue;
            }

            if (($field_value = $this->getFieldValue($field)) === false) {
                static::raiseError(__CLASS__ .'::getFieldValue() returned false!');
                return false;
            }

            if (($link_target = $this->getModelLinkTarget($target, $field_value)) === false) {
                static::raiseError(__CLASS__ .'::getModelLinkTarget() returned false!');
                return false;
            }

            if (!isset($link_target) || empty($link_target)) {
                continue;
            }

            if (!is_object($link_target) && !is_array($link_target)) {
                static::raiseError(__CLASS__ .'::getModelLinkTarget() returned unexpected data!');
                return false;
            }

            if ($recursive === false) {
                if (is_object($link_target)) {
                    $links[] = $link_target;
                } elseif (is_array($link_target)) {
                    $links = array_merge($links, $link_target);
                }
                continue;
            }

            if (is_object($link_target)) {
                $link_targets = array($link_target);
            } elseif (is_array($link_target)) {
                $link_targets = $link_target;
            }

            foreach ($link_targets as $link_target) {
                if ($this->getModelName(true) === $link_target->getModelName(true)) {
                    continue;
                }

                if (!$link_target::isModelLinkModel()) {
                    $links[] = $link_target;
                    continue;
                }

                if (($link_list = $link_target->getModelLinkedList(false, false, true)) === false) {
                    static::raiseError(get_class($link_target) .'::getModelLinkedList() returned false!');
                    return false;
                }

                foreach ($link_list as $link_item) {
                    if ($link_item->getModelName(true) === $this->getModelName(true)) {
                        continue;
                    }

                    if (is_object($link_item)) {
                        $links[] = $link_item;
                    } elseif (is_array($link_item)) {
                        $links = array_merge($links, $link_item);
                    }
                }
            }
        }

        if (isset($sorted) && $sorted === true) {
            sort($links);
        }

        if (isset($unique) && $unique === true) {
            $links = array_unique($links);
        }

        return $links;
    }

    /**
     * returns the link target items.
     *
     * @param string $link
     * @param string $value
     * @return array|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function getModelLinkTarget($link, $value)
    {
        global $thallium;

        if (!isset($link) || empty($link) || !is_string($link)) {
            static::raiseError(__METHOD__ .'(), $link parameter is invalid!');
            return false;
        }

        if (!isset($value) ||
            empty($value) ||
            (!is_string($value) && !is_numeric($value))
        ) {
            static::raiseError(__METHOD__ .'(), $value parameter is invalid!');
            return false;
        }

        if (($parts = explode('/', $link)) === false) {
            static::raiseError(__METHOD__ .'(), explode() returned false!');
            return false;
        }

        if (count($parts) < 2 ||
            !isset($parts[0]) || empty($parts[0]) || !is_string($parts[0]) ||
            !isset($parts[1]) || empty($parts[1]) || !is_string($parts[1])
        ) {
            static::raiseError(__METHOD__ .'(), link information incorrectly declared!');
            return false;
        }

        $model = $parts[0];
        $field = $parts[1];

        if (($full_model = $thallium->getFullModelName($model)) === false) {
            static::raiseError(get_class($thallium) .'::getFullModelName() returned false!');
            return false;
        }

        try {
            $obj = new $full_model(array(
                $field => $value,
            ));
        } catch (\Exception $e) {
            static::raiseError(sprintf('%s(), failed to load %s!', __METHOD__, $full_model), false, $e);
            return false;
        }

        if (!$obj->hasModelItems() && $obj->isNew()) {
            return;
        } elseif ($obj->hasModelItems() && !$obj->hasItems()) {
            return;
        }

        if (!$obj->hasModelItems()) {
            return $obj;
        }

        if (($items = $obj->getItems()) === false) {
            static::raiseError(get_class($obj) .'::getItems() returned false!');
            return false;
        }

        return $items;
    }

    /**
     * returns true if this model has a model defined for its items.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public static function hasModelItemsModel()
    {
        $called_class = get_called_class();

        if (!$called_class::hasModelItems()) {
            static::raiseError(sprintf('%s(), %s::hasModelItems() returned false!', __METHOD__, $called_class));
            return false;
        }

        if (!property_exists($called_class, 'model_items_model') ||
            empty($called_class::$model_items_model) ||
            !is_string($called_class::$model_items_model)
        ) {
            return false;
        }

        return true;
    }

    /**
     * returns the models items model (= type of item).
     *
     * @param bool $long
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public static function getModelItemsModel($long = false)
    {
        global $thallium;

        if (!isset($long) || !is_bool($long)) {
            static::raiseError(__METHOD__ .'(), $long parameter is invalid!');
            return false;
        }

        if (!static::hasModelItemsModel()) {
            static::raiseError(__CLASS__ .'::hasModelItemsModel() returned false!');
            return false;
        }

        $called_class = get_called_class();

        if ($long === false || !isset($thallium)) {
            return $called_class::$model_items_model;
        }

        if (($full_model = $thallium->getFullModelName($called_class::$model_items_model)) === false) {
            static::raiseError(get_class($thallium) .'::getFullModelName() returned false!');
            return false;
        }

        return $full_model;
    }

    /**
     * returns true if this model has a friendly name configured.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public static function hasModelFriendlyName()
    {
        $called_class = get_called_class();

        if (!property_exists($called_class, 'model_friendly_name') ||
            !isset($called_class::$model_friendly_name) ||
            empty($called_class::$model_friendly_name) ||
            !is_string($called_class::$model_friendly_name)
        ) {
            return false;
        }

        return true;
    }

    /**
     * returns the friendly name of this model, if it has been configured.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public static function getModelFriendlyName()
    {
        if (!static::hasModelFriendlyName()) {
            static::raiseError(__CLASS__ .'::hasModelFriendlyName() returned false!');
            return false;
        }

        $called_class = get_called_class();

        return $called_class::$model_friendly_name;
    }

    /**
     * returns true if the provided $value represents the logical
     * state 'enabled'.
     *
     * @param string $value
     * @return bool
     */
    protected static function isEnabled($value)
    {
        $means_enabled = array(
            true,
            1,
            'yes',
            'y',
            'true',
            'on',
            '1'
        );

        if (!in_array(strtolower($value), $means_enabled, true)) {
            return false;
        }

        return true;
    }

    /**
     * returns true if the provided $value represents the logical
     * state 'disabled'.
     *
     * @param string $value
     * @return bool
     */
    protected static function isDisabled($value)
    {
        $means_disabled = array(
            false,
            0,
            'no',
            'n',
            'false',
            'off',
            '0'
        );

        if (!in_array(strtolower($value), $means_disabled, true)) {
            return false;
        }

        return true;
    }

    /**
     * returns true if a model has been marked as searchable.
     *
     * @param none
     * @return bool
     * @throws none
     */
    public static function isSearchable()
    {
        if (!isset(static::$model_is_searchable) ||
            !is_bool(static::$model_is_searchable)
        ) {
            static::raiseError(__METHOD__ .'(), $model_is_searchable is not correctly declared!', true);
            return;
        }

        return static::$model_is_searchable;
    }

    /**
     * returns true if there are files declared that can be used
     * for searching.
     *
     * @param none
     * @return array|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public static function hasSearchableFields()
    {
        if (!isset(static::$model_searchable_fields) ||
            empty(static::$model_searchable_fields) ||
            !is_array(static::$model_searchable_fields)
        ) {
            return false;
        }

        return true;
    }

    /**
     * returns an array of fields that this model can search through
     *
     * @param none
     * @return array|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public static function getSearchableFields()
    {
        if (!static::hasSearchableFields()) {
            static::raiseError(__METHOD__ .'(), $model_searchable_fields is not correctly declared!');
            return false;
        }

        $std_fields = array(
            FIELD_IDX,
            FIELD_GUID,
        );

        return array_merge($std_fields, static::$model_searchable_fields);
    }

    /**
     * returns true if the model is a link-model.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public static function isModelLinkModel()
    {
        if (!isset(static::$model_is_link_model) || !is_bool(static::$model_is_link_model)) {
            static::raiseError(__METHOD__ .'(), model_is_link_model is incorrectly declared!');
            return false;
        }

        return static::$model_is_link_model;
    }

    /**
     * returns all fields.
     *
     * @param none
     * @return array|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getFieldValues()
    {
        if (!static::hasModelFields()) {
            static::raiseError(__CLASS__ .'::hasModelFields() returned false!');
            return false;
        }

        $values = array();

        if (($fields = $this->getModelFields()) === false) {
            static::raiseError(__CLASS__ .'::getModelFields() returned false!');
            return false;
        }

        foreach ($fields as $field) {
            $field_name = $field['name'];

            if (!$this->hasFieldValue($field_name)) {
                continue;
            }

            if (($value = $this->getFieldValue($field_name)) === false) {
                static::raiseError(__CLASS__ .'::getFieldValue() returned false!');
                return false;
            }

            $values[$field_name] = $value;
        }

        return $values;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
