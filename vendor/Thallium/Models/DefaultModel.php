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

namespace Thallium\Models;

use \PDO;

abstract class DefaultModel
{
    protected $model_load_by = array();
    protected $model_sort_order = array();
    protected static $model_table_name;
    protected static $model_column_prefix;
    protected static $model_fields = array();
    protected static $model_has_items = false;
    protected $model_items = array();
    protected static $model_items_model;
    protected $model_permit_rpc_updates = false;
    protected $model_rpc_allowed_fields = array();
    protected $model_rpc_allowed_actions = array();
    protected $model_virtual_fields = array();
    protected $model_init_values = array();

    protected $child_names;
    protected $ignore_child_on_clone;

    public function __construct($load_by = array(), $sort_order = array())
    {
        if (!isset($load_by) || (!is_array($load_by) && !is_null($load_by))) {
            $this->raiseError(__METHOD__ .'(), parameter $load_by has to be an array!', true);
            return;
        }

        if (!isset($sort_order) || (!is_array($sort_order) && !is_null($sort_order))) {
            $this->raiseError(__METHOD__ .'(), parameter $sort_order has to be an array!', true);
            return;
        }

        $this->model_load_by = $load_by;
        $this->model_sort_order = $sort_order;

        if (!$this->validateModelSettings()) {
            return;
        }

        if (method_exists($this, '__init') && is_callable(array($this, '__init'))) {
            if (!$this->__init()) {
                $this->raiseError(__METHOD__ .'(), __init() returned false!', true);
                return;
            }
        }

        if ($this->isNewModel()) {
            $this->initFields();
            return;
        }

        $this->model_init_values = array();

        if (!$this->load()) {
            $this->raiseError(__CLASS__ ."::load() returned false!", true);
            return false;
        }

        return true;

    } // __construct()

    protected function validateModelSettings()
    {
        global $thallium;

        if (!isset(static::$model_table_name) ||
            empty(static::$model_table_name) ||
            !is_string(static::$model_table_name)
        ) {
            $this->raiseError(__METHOD__ .'(), missing property "model_table_name"', true);
            return false;
        }

        if (!isset(static::$model_column_prefix) ||
            empty(static::$model_column_prefix) ||
            !is_string(static::$model_column_prefix)
        ) {
            $this->raiseError(__METHOD__ .'(), missing property "model_column_prefix"', true);
            return false;
        }

        if (static::hasFields() && static::isHavingItems()) {
            $this->raiseError(__METHOD__ .'(), model must no have fields and items at the same times!', true);
            return false;
        }

        if (static::isHavingItems()) {
            if (!isset(static::$model_items_model) ||
                empty(static::$model_items_model) ||
                !is_string(static::$model_items_model) ||
                !$thallium->isRegisteredModel(null, static::$model_items_model)
            ) {
                $this->raiseError(__METHOD__ .'(), $model_items_model is invalid!', true);
                return false;
            }
        }

        if (!isset(static::$model_fields) || !is_array(static::$model_fields)) {
            $this->raiseError(__METHOD__ .'(), missing property "model_fields"', true);
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
                    $this->raiseError(__METHOD__ .'(), invalid field entry (field name) found!', true);
                    return false;
                }
                if (!isset($params) || empty($params) || !is_array($params)) {
                    $this->raiseError(__METHOD__ .'(), invalid field params found!', true);
                    return false;
                }
                if (!isset($params[FIELD_TYPE]) ||
                    empty($params[FIELD_TYPE]) ||
                    !is_string($params[FIELD_TYPE]) ||
                    !ctype_alnum($params[FIELD_TYPE])
                ) {
                    $this->raiseError(__METHOD__ .'(), invalid field type found!', true);
                    return false;
                }
                if (!in_array($params[FIELD_TYPE], $known_field_types)) {
                    $this->raiseError(__METHOD__ .'(), unknown field type found!', true);
                    return false;
                }
            }
        }

        if (!isset($this->model_load_by) || !is_array($this->model_load_by)) {
            $this->raiseError(__METHOD__ .'(), missing property "model_load_by"', true);
            return false;
        }

        if (!empty($this->model_load_by)) {
            foreach ($this->model_load_by as $field => $value) {
                if (!isset($field) ||
                    empty($field) ||
                    !is_string($field) ||
                    !static::hasField($field)
                ) {
                    $this->raiseError(__METHOD__ .'(), $model_load_by contains an invalid field!', true);
                    return false;
                }
                if (!$this->validateField($field, $value)) {
                    $this->raiseError(__METHOD__ .'(), $model_load_by contains an invalid value!', true);
                    return false;
                }
            }
        }

        if (!empty($this->model_sort_order)) {
            foreach ($this->model_sort_order as $field => $mode) {
                if (($full_model = $thallium->getFullModelName(static::$model_items_model)) === false) {
                    $this->raiseError(get_class($thallium) .'::getFullModelName() returned false!', true);
                    return false;
                }
                if (!isset($field) ||
                    empty($field) ||
                    !is_string($field) ||
                    !$full_model::hasFields() ||
                    !$full_model::hasField($field)
                ) {
                    $this->raiseError(__METHOD__ ."(), \$model_sort_order contains an invalid field {$field}!", true);
                    return false;
                }
                if (!in_array(strtoupper($mode), array('ASC', 'DESC'))) {
                    $this->raiseError(__METHOD__ .'(), \$order is invalid!');
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * load
     *
     */
    protected function load($extend_query_where = null)
    {
        global $db;

        if (!static::hasFields() && !static::isHavingItems()) {
            return true;
        }

        if (static::hasFields() && empty($this->model_load_by)) {
            return true;
        }

        if (!isset($this->model_sort_order) ||
            !is_array($this->model_sort_order)
        ) {
            $this->raiseError(__METHOD__ .'(), $model_sort_order is invalid!');
            return false;
        }

        if (!empty($this->model_sort_order)) {
            $order_by = array();
            foreach ($this->model_sort_order as $field => $mode) {
                if (($column = $this->column($field)) === false) {
                    $this->raiseError(__CLASS__ .'::column() returned false!');
                    return false;
                }
                array_push($order_by, "{$column} {$mode}");
            }
        }

        if (isset($extend_query_where) &&
            !empty($extend_query_where) &&
            !is_string($extend_query_where)
        ) {
            $this->raiseError(__METHOD__ .'(), $extend_query_where parameter is invalid!');
            return false;
        }

        if (method_exists($this, 'preLoad') && is_callable($this, 'preLoad')) {
            if (!$this->preLoad()) {
                $this->raiseError(get_called_class() ."::preLoad() method returned false!");
                return false;
            }
        }

        $sql_query_columns = array();
        $sql_query_data = array();

        if (static::hasFields()) {
            if (($fields = $this->getFieldNames()) === false) {
                $this->raiseError(__CLASS__ .'::getFieldNames() returned false!');
                return false;
            }
        } elseif (static::isHavingItems()) {
            $fields = array(
                'idx',
                'guid',
            );
        }

        if (!isset($fields) || empty($fields)) {
            return true;
        }

        foreach ($fields as $field) {
            if (($column = $this->column($field)) === false) {
                $this->raiseError(__CLASS__ .'::column() returned false!');
                return false;
            }
            if ($field == 'time') {
                $sql_query_columns[] = sprintf("UNIX_TIMESTAMP(%s) as %s", $column, $column);
                continue;
            }
            $sql_query_columns[$field] = $column;
        }

        foreach ($this->model_load_by as $field => $value) {
            if (($column = $this->column($field)) === false) {
                $this->raiseError(__CLASS__ .'::column() returned false!');
                return false;
            }
            $sql_query_data[$column] = $value;
        }

        $bind_params = array();

        if (($sql = $db->buildQuery(
            "SELECT",
            self::getTableName(),
            $sql_query_columns,
            $sql_query_data,
            $bind_params,
            $extend_query_where
        )) === false) {
            $this->raiseError(get_class($db) .'::buildQuery() returned false!');
            return false;
        }

        if (isset($order_by) &&
            !empty($order_by) &&
            is_array($order_by)
        ) {
            $sql.= ' ORDER BY '. implode(', ', $oder_by);
        }

        try {
            $sth = $db->prepare($sql);
        } catch (\Exception $e) {
            $this->raiseError(__METHOD__ .'(), unable to prepare database query!');
            return false;
        }

        if (!$sth) {
            $this->raiseError(get_class($db) ."::prepare() returned invalid data!");
            return false;
        }

        foreach ($bind_params as $key => $value) {
            $sth->bindParam($key, $value);
        }

        if (!$db->execute($sth, $bind_params)) {
            $this->raiseError(__METHOD__ ."(), unable to execute query!");
            return false;
        }

        $num_rows = $sth->rowCount();

        if (static::hasFields()) {
            if ($num_rows < 1) {
                $db->freeStatement($sth);
                $this->raiseError(__METHOD__ ."(), no object with id {$this->id} found!");
                return false;
            } elseif ($num_rows > 1) {
                $db->freeStatement($sth);
                $this->raiseError(__METHOD__ ."(), more than one object with id {$this->id} found!");
                return false;
            }
        }

        if ($num_rows == 0) {
            $db->freeStatement($sth);
            return true;
        }

        if (static::hasFields()) {
            if (($row = $sth->fetch(\PDO::FETCH_ASSOC)) === false) {
                $db->freeStatement($sth);
                $this->raiseError(__METHOD__ ."(), unable to fetch SQL result for object id ". $this->id);
                return true;
            }

            $db->freeStatement($sth);

            foreach ($row as $key => $value) {
                if (($field = $this->getFieldNameFromColumn($key)) === false) {
                    $this->raiseError(__CLASS__ .'() returned false!');
                    return false;
                }
                if (!static::hasField($field)) {
                    $this->raiseError(__METHOD__ ."(), received data for unknown field '{$field}'!");
                    return false;
                }
                if (!$this->validateField($field, $value)) {
                    $this->raiseError(__CLASS__ ."::validateField() returned false for field {$field}!");
                    return false;
                }
                $this->model_init_values[$key] = $value;
                $this->$key = $value;
            }
        } elseif (static::isHavingItems()) {
            while (($row = $sth->fetch(\PDO::FETCH_ASSOC)) !== false) {
                foreach ($row as $key => $value) {
                    if (($field = $this->getFieldNameFromColumn($key)) === false) {
                        $this->raiseError(__CLASS__ .'() returned false!');
                        return false;
                    }
                    if (!in_array($field, array('idx', 'guid'))) {
                        $this->raiseError(__METHOD__ ."(), received data for unknown field '{$field}'!");
                        return false;
                    }
                    if (!$this->validateField($field, $value)) {
                        $this->raiseError(__CLASS__ ."::validateField() returned false for field {$field}!");
                        return false;
                    }
                }
                try {
                    $item = new static::$model_items_model(array(
                        'idx' => $row['idx'],
                        'guid' => $row['guid'],
                    ));
                } catch (\Exception $e) {
                    $this->raiseError(__METHOD__ .'(), failed to load '. static::$model_items_model .'!');
                    return false;
                }
                if (!$this->addItem($item)) {
                    $this->raiseError(__CLASS__ .'::addItem() returned false!');
                    return true;
                }
            }
        }

        if (method_exists($this, 'postLoad') && is_callable($this, 'postLoad')) {
            if (!$this->postLoad()) {
                $this->raiseError(get_called_class() ."::postLoad() method returned false!");
                return false;
            }
        }

        return true;

    } // load();

    /**
     * update object variables via array
     *
     * @param mixed $data
     * @return bool
     */
    final public function update($data)
    {
        if (!is_array($data)) {
            return false;
        }

        foreach ($data as $key => $value) {
            $this->$key = $value;
        }

        return true;

    } // update()

    /**
     * delete
     */
    public function delete()
    {
        global $db;

        if (!isset($this->id)) {
            $this->raiseError(__METHOD__ .'(), object id is not set!');
            return false;
        }
        if (!is_numeric($this->id)) {
            $this->raiseError(__METHOD__ .'(), object id is invalid!');
            return false;
        }
        if (!isset(static::$model_table_name)) {
            $this->raiseError(__METHOD__ .'(), table name is not set!');
            return false;
        }
        if (!isset(static::$model_column_prefix)) {
            $this->raiseError(__METHOD__ .'(), column name is not set!');
            return false;
        }

        if (static::isHavingItems() && $this->hasItems()) {
            if (!$this->deleteItems()) {
                $this->raiseError(__CLASS__ .'::deleteItems() returned false!');
                return false;
            }
        }

        if (!static::hasFields()) {
            return true;
        }

        if (method_exists($this, 'preDelete')) {
            if (!$this->preDelete()) {
                $this->raiseError(get_called_class() ."::preDelete() method returned false!");
                return false;
            }
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

        if (!$sth) {
            $this->raiseError(__METHOD__ ."(), unable to prepare query");
            return false;
        }

        if (!$db->execute($sth, array($this->id))) {
            $this->raiseError(__METHOD__ ."(), unable to execute query");
            return false;
        }

        $db->freeStatement($sth);

        if (method_exists($this, 'postDelete')) {
            if (!$this->postDelete()) {
                $this->raiseError(get_called_class() ."::postDelete() method returned false!");
                return false;
            }
        }

        return true;

    } // delete()

    public function deleteItems()
    {
        if (!static::isHavingItems()) {
            $this->raiseError(__METHOD__ .'(), model '. __CLASS__ .' is not declared to have items!');
            return false;
        }

        if (!$this->hasItems()) {
            return true;
        }

        if (($items = $this->getItems()) === false) {
            $this->raiseError(__CLASS__ .'::getItems() returned false!');
            return false;
        }

        foreach ($items as $item) {
            if (!method_exists($item, 'delete') || !is_callable($item, 'delete')) {
                $this->raiseError(__METHOD__ .'(), model '. get_class($item) .' does not provide a delete() method!');
                return false;
            }
            if (!$item->delete()) {
                $this->raiseError(get_class($item) .'::delete() returned false!');
                return false;
            }
            $this->raiseError(get_class($item) .'::delete() returned false!');
            return false;
        }

        return true;
    }

    /**
     * clone
     */
    final public function createClone(&$srcobj)
    {
        global $thallium, $db;

        if (!isset($srcobj->id)) {
            return false;
        }
        if (!is_numeric($srcobj->id)) {
            return false;
        }
        if (!isset($srcobj->model_fields)) {
            return false;
        }

        foreach (array_keys($srcobj->model_fields) as $field) {
            // check for a matching key in clone's model_fields array
            if (!in_array($field, array_keys(static::$model_fields))) {
                continue;
            }

            $this->$field = $srcobj->$field;
        }

        if (method_exists($this, 'preClone')) {
            if (!$this->preClone($srcobj)) {
                $this->raiseError(get_called_class() ."::preClone() method returned false!");
                return false;
            }
        }

        $idx_field = static::$model_column_prefix.'_idx';
        $guid_field = static::$model_column_prefix.'_guid';
        $pguid = static::$model_column_prefix.'_derivation_guid';

        $this->id = null;
        if (isset($this->$idx_field)) {
            $this->$idx_field = null;
        }
        if (isset($this->$guid_field)) {
            $this->$guid_field = $thallium->createGuid();
        }

        // record the parent objects GUID
        if (isset($srcobj->$guid_field) &&
            !empty($srcobj->$guid_field) &&
            static::hasField($pguid)
        ) {
            $this->$pguid = $srcobj->$guid_field;
        }

        $this->save();

        // if saving was successful, our new object should have an ID now
        if (!isset($this->id) || empty($this->id)) {
            $this->raiseError(__METHOD__ ."(), error on saving clone. no ID was returned from database!");
            return false;
        }

        $this->$idx_field = $this->id;

        // now check for assigned childrens and duplicate those links too
        if (isset($this->child_names) && !isset($this->ignore_child_on_clone)) {
            // loop through all (known) childrens
            foreach (array_keys($this->child_names) as $child) {
                $prefix = $this->child_names[$child];

                // initate an empty child object
                if (($child_obj = $thallium->load_class($child)) === false) {
                    $this->raiseError(__METHOD__ ."(), unable to locate class for {$child_obj}");
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

                if (!$sth) {
                    $this->raiseError(__METHOD__ ."(), unable to prepare query");
                    return false;
                }

                if (!$db->execute($sth, array($srcobj->id))) {
                    $this->raiseError(__METHOD__ ."(), unable to execute query");
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

                    $row[$this->child_names[$child] .'_idx'] = 'NULL';
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

        if (method_exists($this, 'postClone')) {
            if (!$this->postClone($srcobj)) {
                $this->raiseError(get_called_class() ."::postClone() method returned false!");
                return false;
            }
        }

        return true;

    } // createClone()

    /**
     * init fields
     */
    final protected function initFields($override = null)
    {
        if (!static::hasFields()) {
            return true;
        }

        foreach (array_keys(static::$model_fields) as $field) {
            // check for a matching key in clone's model_fields array
            if (isset($override) &&
                !empty($override) &&
                is_array($override) &&
                in_array($field, array_keys($override))
            ) {
                $this->$field = $override[$field];
                continue;
            }

            $this->$field = null;
        }

        return true;

    } // initFields()

    /* override PHP's __set() function */
    final public function __set($name, $value)
    {
        if ($this->hasVirtualFields() && $this->hasVirtualField($name)) {

            if (($name = $this->getFieldNamefromColumn($name)) === false) {
                $this->raiseError(__CLASS__ .'::getFieldNameFromColumn() returned false!');
                return false;
            }

            $method_name = 'set'.ucwords(strtolower($name));

            if (!method_exists($this, $method_name)) {
                $this->raiseError(__METHOD__ .'(), virtual field exists but there is no set-method for it!', true);
                return false;
            }

            if (!$this->$method_name($value)) {
                $this->raiseError(__CLASS__ ."::{$method_name} returned false!", true);
                return false;
            }
            return true;
        }

        if (!static::hasFields()) {
            $this->raiseError(__METHOD__ ."(), model_fields array not set for class ". get_class($this), true);
        }

        if (($field = $this->getFieldNameFromColumn($name)) === false) {
            $this->raiseEerror(__CLASS__ .'::getFieldNameFromColumn() returned false!');
            return false;
        }

        if (!array_key_exists($field, static::$model_fields) && $field != 'id') {
            $this->raiseError(__METHOD__ ."(), unknown key in ". __CLASS__ ."::__set(): {$field}", true);
        }

        $this->$name = $value;

    } // __set()

    /* override PHP's __get() function */
    final public function __get($name)
    {
        if (isset($this->$name)) {
            return $this->$name;
        }

        if (!$this->hasVirtualFields()) {
            return null;
        }

        if (!$this->hasVirtualField($name)) {
            return null;
        }

        if (($name = $this->getFieldNamefromColumn($name)) === false) {
            $this->raiseError(__CLASS__ .'::getFieldNameFromColumn() returned false!');
            return false;
        }

        $method_name = 'get'.ucwords(strtolower($name));

        if (!method_exists($this, $method_name)) {
            return null;
        }

        if (($value = $this->$method_name()) === false) {
            return null;
        }

        return $value;
    }

    public function save()
    {
        global $thallium, $db;

        if (!static::hasFields()) {
            $this->raiseError(__METHOD__ ."(), model_fields array not set for class ". get_class($this));
        }

        if (method_exists($this, 'preSave')) {
            if (!$this->preSave()) {
                $this->raiseError(get_called_class() ."::preSave() method returned false!");
                return false;
            }
        }

        $guid_field = $this->column('guid');
        $idx_field = $this->column('idx');
        $time_field = $this->column('time');

        if (!isset($this->$guid_field) || empty($this->$guid_field)) {
            $this->$guid_field = $thallium->createGuid();
        }

        /* new object */
        if (!isset($this->id) || empty($this->id)) {
            $sql = 'INSERT INTO ';
        /* existing object */
        } else {
            $sql = 'UPDATE ';
        }

        $sql.= sprintf("TABLEPREFIX%s SET ", static::$model_table_name);

        $arr_values = array();

        foreach (array_keys(static::$model_fields) as $key) {
            if (!isset($this->$key)) {
                continue;
            }

            if ($key == $time_field) {
                $sql.= $key ." = FROM_UNIXTIME(?), ";
            } else {
                $sql.= $key ." = ?, ";
            }
            $arr_values[] = $this->$key;
        }
        $sql = substr($sql, 0, strlen($sql)-2) .' ';

        if (!isset($this->id)) {
            $this->$idx_field = 'NULL';
        } else {
            $sql.= sprintf(
                "WHERE %s_idx LIKE ?",
                static::$model_column_prefix
            );
            $arr_values[] = $this->id;
        }

        if (($sth = $db->prepare($sql)) === false) {
            $this->raiseError(__METHOD__ ."(), unable to prepare query");
            return false;
        }

        if (!$db->execute($sth, $arr_values)) {
            $this->raiseError(__METHOD__ ."(), unable to execute query");
            return false;
        }

        if (!isset($this->id) || empty($this->id)) {
            $this->id = $db->getid();
        }

        if (!isset($this->$idx_field) || empty($this->$idx_field) || $this->$idx_field == 'NULL') {
            $this->$idx_field = $this->id;
        }

        $db->freeStatement($sth);

        if (method_exists($this, 'postSave')) {
            if (!$this->postSave()) {
                $this->raiseError(get_called_class() ."::postSave() method returned false!");
                return false;
            }
        }

        // now we need to update the model_init_values array.

        $this->model_init_values = array();

        foreach (array_keys(static::$model_fields) as $field) {
            if (!isset($this->$field)) {
                continue;
            }

            $this->model_init_values[$field] = $this->$field;
        }

        return true;

    } // save()

    final public function toggleStatus($to)
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
            $this->raiseError(__METHOD__ ."(), unable to prepare query");
            return false;
        }

        if (!$db->execute($sth, array($new_status, $this->id))) {
            $this->raiseError(__METHOD__ ."(), unable to execute query");
            return false;
        }

        $db->freeStatement($sth);
        return true;

    } // toggleStatus()

    final public function toggleChildStatus($to, $child_obj, $child_id)
    {
        global $db, $thallium;

        if (!isset($this->child_names)) {
            $this->raiseError(__METHOD__ ."(), this object has no childs at all!");
            return false;
        }
        if (!isset($this->child_names[$child_obj])) {
            $this->raiseError(__METHOD__ ."(), requested child is not known to this object!");
            return false;
        }

        $prefix = $this->child_names[$child_obj];

        if (($child_obj = $thallium->load_class($child_obj, $child_id)) === false) {
            $this->raiseError(__METHOD__ ."(), unable to locate class for {$child_obj}");
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
            $this->raiseError(__METHOD__ ."(), unable to prepare query");
            return false;
        }

        if (!$db->execute($sth, array(
            $new_status,
            $this->id,
            $child_id
        ))) {
            $this->raiseError(__METHOD__ ."(), unable to execute query");
            return false;
        }

        $db->freeStatement($sth);
        return true;

    } // toggleChildStatus()

    final public function prev()
    {
        global $thallium, $db;

        $idx_field = static::$model_column_prefix ."_idx";
        $guid_field = static::$model_column_prefix ."_guid";

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
            $id,
            $guid_field,
            static::$model_table_name,
            $id,
            $id,
            static::$model_table_name,
            $id,
            $this->id
        ));

        if (!isset($result)) {
            $this->raiseError(__METHOD__ ."(), unable to locate previous record!");
            return false;
        }

        if (!isset($result->$idx_field) || !isset($result->$guid_field)) {
            $this->raiseError(__METHOD__ ."(), no previous record available!");
            return false;
        }

        if (!is_numeric($result->$idx_field) || !$thallium->isValidGuidSyntax($result->$guid_field)) {
            $this->raiseError(
                __METHOD__ ."(), Invalid previous record found: ". htmlentities($result->$id, ENT_QUOTES)
            );
            return false;
        }

        return $result->$id ."-". $result->$guid_field;
    }

    final public function next()
    {
        global $thallium, $db;

        $idx_field = static::$model_column_prefix ."_idx";
        $guid_field = static::$model_column_prefix ."_guid";

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
            $id,
            $guid_field,
            static::$model_table_name,
            $id,
            $id,
            static::$model_table_name,
            $id,
            $this->id
        ));

        if (!isset($result)) {
            $this->raiseError(__METHOD__ ."(), unable to locate next record!");
            return false;
        }

        if (!isset($result->$idx_field) || !isset($result->$guid_field)) {
            $this->raiseError(__METHOD__ ."(), no next record available!");
            return false;
        }

        if (!is_numeric($result->$idx_field) || !$thallium->isValidGuidSyntax($result->$guid_field)) {
            $this->raiseError(__METHOD__ ."(), invalid next record found: ". htmlentities($result->$id, ENT_QUOTES));
            return false;
        }

        return $result->$id ."-". $result->$guid_field;
    }

    final protected function isDuplicate()
    {
        global $db;

        // no need to check yet if $id isn't set
        if (empty($this->id)) {
            return false;
        }

        $idx_field = static::$model_column_prefix.'_idx';
        $guid_field = static::$model_column_prefix.'_guid';

        if ((!isset($this->$idx_field) || empty($this->$idx_field)) &&
            (!isset($this->$guid_field) || empty($this->$guid_field))
        ) {
            $this->raiseError(
                __METHOD__ ."(), can't check for duplicates if neither \$idx_field or \$guid_field is set!"
            );
            return false;
        }

        $arr_values = array();
        $where_sql = '';
        if (isset($this->$idx_field) && !empty($this->$idx_field)) {
            $where_sql.= "
                {$idx_field} LIKE ?
            ";
            $arr_values[] = $this->$idx_field;
        }
        if (isset($this->$guid_field) && !empty($this->$guid_field)) {
            if (!empty($where_sql)) {
                $where_sql.= "
                    AND
                ";
            }
            $where_sql.= "
                {$guid_field} LIKE ?
            ";
            $arr_values[] = $this->$guid_field;
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

        $sth = $db->prepare($sql);

        if (!$sth) {
            $this->raiseError(__METHOD__ ."(), unable to prepare query");
            return false;
        }

        if (!$db->execute($sth, $arr_values)) {
            $this->raiseError(__METHOD__ ."(), unable to execute query");
            return false;
        }

        if ($sth->rowCount() <= 0) {
            $db->freeStatement($sth);
            return false;
        }

        $db->freeStatement($sth);
        return true;
    }

    final protected function column($suffix)
    {
        if (!isset(static::$model_column_prefix) || empty(static::$model_column_prefix)) {
            return $suffix;
        }

        return static::$model_column_prefix .'_'. $suffix;
    }

    final protected function permitRpcUpdates($state)
    {
        if (!is_bool($state)) {
            $this->raiseError(__METHOD__ .'(), parameter must be a boolean value', true);
            return false;
        }

        $this->model_permit_rpc_updates = $state;
        return true;
    }

    final public function permitsRpcUpdates()
    {
        if (!isset($this->model_permit_rpc_updates) ||
            !$this->model_permit_rpc_updates
        ) {
            return false;
        }

        return true;
    }

    final protected function addRpcEnabledField($field)
    {
        if (!is_array($this->model_rpc_allowed_fields)) {
            $this->raiseError(__METHOD__ .'(), $model_rpc_allowed_fields is not an array!', true);
            return false;
        }

        if (!isset($field) ||
            empty($field) ||
            !is_string($field) ||
            static::hasField($field)
        ) {
            $this->raiseError(__METHOD__ .'(), $field is invalid!', true);
            return false;
        }

        if (in_array($field, $this->model_rpc_allowed_fields)) {
            return true;
        }

        array_push($this->model_rpc_allowed_fields, $field);
        return true;
    }

    final protected function addRpcAction($action)
    {
        if (!is_array($this->model_rpc_allowed_actions)) {
            $this->raiseError(__METHOD__ .'(), $model_rpc_allowed_actions is not an array!', true);
            return false;
        }

        if (!isset($action) ||
            empty($action) ||
            !is_string($action)
        ) {
            $this->raiseError(__METHOD__ .'(), $action parameter is invalid!', true);
            return false;
        }

        if (in_array($action, $this->model_rpc_allowed_actions)) {
            return true;
        }

        array_push($this->model_rpc_allowed_actions, $action);
        return true;
    }

    final public function permitsRpcUpdateToField($field)
    {
        if (!is_array($this->model_rpc_allowed_fields)) {
            $this->raiseError(__METHOD__ .'(), $model_rpc_allowed_fields is not an array!', true);
            return false;
        }

        if (!isset($field) ||
            empty($field) ||
            !is_string($field) ||
            !static::hasField($field)
        ) {
            $this->raiseError(__METHOD__ .'(), $field parameter is invalid!', true);
            return false;
        }

        if (empty($this->model_rpc_allowed_fields)) {
            return false;
        }

        if (!in_array($field, $this->model_rpc_allowed_fields)) {
            return false;
        }

        return true;
    }

    final public function permitsRpcActions($action)
    {
        if (!is_array($this->model_rpc_allowed_actions)) {
            $this->raiseError(__METHOD__ .'(), $model_rpc_allowed_actions is not an array!', true);
            return false;
        }

        if (!isset($action) ||
            empty($action) ||
            !is_string($action)
        ) {
            $this->raiseError(__METHOD__ .'(), $action parameter is invalid!', true);
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

    final public function getId()
    {
        if (!static::hasField('idx')) {
            $this->raiseError(__METHOD__ .'(), model has no idx field!');
            return false;
        }

        $idx_field = static::$model_column_prefix .'_idx';

        if (!isset($this->$idx_field)) {
            return false;
        }

        return $this->$idx_field;
    }

    final public function getGuid()
    {
        if (!static::hasField('guid')) {
            $this->raiseError(__METHOD__ .'(), model has no guid field!');
            return false;
        }

        $guid_field = static::$model_column_prefix .'_guid';

        if (!isset($this->$guid_field)) {
            return false;
        }

        return $this->$guid_field;
    }

    final public function setGuid($guid)
    {
        global $thallium;

        if (!isset($guid) || empty($guid) || !is_string($guid)) {
            $this->raiseError(__METHOD__ .'(), $guid parameter is invalid!');
            return false;
        }

        if (!$thallium->isValidGuidSyntax($guid)) {
            $this->raiseError(get_class($thallium) .'::isValidGuidSyntax() returned false!');
            return false;
        }

        $guid_field = static::$model_column_prefix .'_guid';

        $this->$guid_field = $guid;
        return true;
    }

    final public static function hasFields()
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

    final public function getFields()
    {
        if (!static::hasFields()) {
            $this->raiseError(__METHOD__ .'(), this model has no fields defined!');
            return false;
        }

        $fields = array();

        foreach (static::$model_fields as $field => $sec) {
            $field_ary = array(
                'name' => $field,
                'value' => $this->$field,
                'privacy' => $sec,
            );
            $fields[$field] = $field_ary;
        }

        if (!$this->hasVirtualFields()) {
            return $fields;
        }

        foreach ($this->model_virtual_fields as $field) {
            $field_ary = array(
                'name' => $field,
                'value' => $this->$field,
                'privacy' => 'public'
            );
            $fields[$field] = $field_ary;
        }

        return $fields;
    }

    final public function getFieldNames()
    {
        if (!static::hasFields()) {
            $this->raiseError(__METHOD__ .'(), this model has no fields defined!');
            return false;
        }

        return array_keys(static::$model_fields);
    }

    final public static function hasField($field_name)
    {
        if (!isset($field_name) ||
            empty($field_name) ||
            !is_string($field_name)
        ) {
            static::raiseError(__METHOD__ .'(), do not know what to look for!');
            return false;
        }

        $called_class = get_called_class();
        if (!$called_class::hasFields()) {
            static::raiseError(__METHOD__ .'(), this model has no fields defined!');
            return false;
        }

        if (!in_array($field_name, array_keys($called_class::$model_fields))) {
            return false;
        }

        return true;
    }

    final public function getFieldPrefix()
    {
        if (!isset(static::$model_column_prefix) ||
            empty(static::$model_column_prefix) ||
            !is_string(static::$model_column_prefix)
        ) {
            $this->raiseError(__METHOD__ .'(), column name is not set!');
            return false;
        }

        return static::$model_column_prefix;
    }

    final public function isNew()
    {
        if (isset($this->id) && !empty($this->id)) {
            return false;
        }

        return true;
    }

    public function raiseError($string, $stop_execution = false, $exception = null)
    {
        global $thallium;

        $thallium->raiseError(
            $string,
            $stop_execution,
            $exception
        );

        return true;
    }

    final public function hasVirtualFields()
    {
        if (empty($this->model_virtual_fields)) {
            return true;
        }

        return true;
    }

    final public function hasVirtualField($vfield)
    {
        if (!isset($vfield) || empty($vfield) || !is_string($vfield)) {
            $this->raiseError(__METHOD__ .'(), $vfield parameter is invalid!');
            return false;
        }

        if (!in_array($vfield, $this->model_virtual_fields)) {
            return false;
        }

        return true;
    }

    final public function addVirtualField($vfield)
    {
        if (!isset($vfield) || empty($vfield) || !is_string($vfield)) {
            $this->raiseError(__METHOD__ .'(), $vfield parameter is invalid!');
            return false;
        }

        if ($this->hasVirtualField($vfield)) {
            return true;
        }

        array_push($this->model_virtual_fields, $vfield);
        return true;
    }

    final public function setField($field, $value)
    {
        if (!isset($field) || empty($field) || !is_string($field)) {
            $this->raiseError(__METHOD__ .'(), $field parameter is invalid!');
            return false;
        }

        if (!static::hasField($field)) {
            $this->raiseError(__METHOD__ .'(), invalid field specified!');
            return false;
        }

        $this->$field = $value;
        return true;
    }

    public function getItemsKeys()
    {
        if (!static::isHavingItems()) {
            $this->raiseError(__METHOD__ .'(), model '. __CLASS__ .' is not declared to have items!');
            return false;
        }

        if (!isset($this->model_items)) {
            $this->raiseError(__METHOD__ .'(), no items available!');
            return false;
        }

        return array_keys($this->model_items);
    }

    public function getItems()
    {
        if (!isset($this->model_items)) {
            $this->raiseError(__METHOD__ .'(), no items available!');
            return false;
        }

        return $this->model_items;
    }

    final public static function isHavingItems()
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

    public function hasItems()
    {
        $called_class = get_called_class();
        if (!$called_class::isHavingItems()) {
            $this->raiseError(__METHOD__ ."(), model {$called_class} is not declared to have items!", true);
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

    public function addItem($item)
    {
        if (!static::isHavingItems()) {
            $this->raiseError(__METHOD__ .'(), model '. __CLASS__ .' is not declared to have items!');
            return false;
        }

        if (!method_exists($item, 'getId')) {
            $this->raiseError(__METHOD__ .'(), item model '. get_class($item) .' has no getId() method!');
            return false;
        }

        if (($idx = $item->getId()) === false) {
            $this->raiseError(get_class($item) .'::getId() returned false!');
            return false;
        }

        if (array_key_exists($idx, $this->model_items)) {
            $this->raiseError(__METHOD__ ."(), item with key {$idx} does already exist!");
            return false;
        }

        $this->model_items[$idx] = $item;
        return true;
    }

    public function getItem($idx)
    {
        if (!isset($idx) || empty($idx) || (!is_string($idx) && !is_numeric($idx))) {
            $this->raiseError(__METHOD__ .'(), $idx parameter is invalid!');
            return false;
        }

        if (!$this->hasItem($idx)) {
            $this->raiseError(__CLASS__ .'::hasItem() returned false!');
            return false;
        }

        return $this->model_items[$idx];
    }

    public function hasItem($idx)
    {
        if (!isset($idx) || empty($idx) || (!is_string($idx) && !is_numeric($idx))) {
            $this->raiseError(__METHOD__ .'(), $idx parameter is invalid!');
            return false;
        }

        if (!in_array($idx, array_keys($this->model_items))) {
            return false;
        }

        return true;
    }

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

    public function isNewModel()
    {
        if (isset($this->model_load_by) &&
            is_array($this->model_load_by) &&
            !empty($this->model_load_by)
        ) {
            return false;
        }

        return true;
    }

    public function getFieldType($field_name)
    {
        if (!isset($field_name) || empty($field_name) || !is_string($field_name)) {
            $this->raiseError(__METHOD__ .'(), $field_name parameter is invalid!');
            return false;
        }

        if (!static::hasField($field_name)) {
            $this->raiseError(__METHOD__ ."(), model has no field {$field_name}!");
            return false;
        }

        return static::$model_fields[$field_name]['type'];
    }

    public function getTableName()
    {
        return sprintf("TABLEPREFIX%s", static::$model_table_name);
    }

    public function getFieldNameFromColumn($column)
    {
        if (!isset($column) || empty($column) || !is_string($column)) {
            $this->raiseError(__METHOD__ .'(), $column parameter is invalid!');
            return false;
        }

        if (strpos($column, static::$model_column_prefix .'_') === false) {
            return $column;
        }

        $field_name = str_replace(static::$model_column_prefix .'_', '', $column);

        if (!static::hasField($field_name)) {
            $this->raiseError(__CLASS__ .'::hasField() returned false!');
            return false;
        }

        return $field_name;
    }

    public function validateField($field, $value)
    {
        global $thallium;

        if (!isset($field) || empty($field) || !is_string($field)) {
            $this->raiseError(__METHOD__ .'(), $field parameter is invalid!');
            return false;
        }

        if (!static::hasFields()) {
            $this->raiseError(__CLASS__ .'::hasField() returned false!');
            return false;
        }

        if (($type = $this->getFieldType($field)) === false) {
            $this->raiseError(__CLASS__ .'::getFieldType() returned false!');
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
                break;
            case FIELD_INT:
                if (!is_numeric($value) || !is_int((int) $value)) {
                    return false;
                }
                break;
            case FIELD_BOOL:
                if (!is_bool($value)) {
                    return false;
                }
                break;
            case FIELD_YESNO:
                if (!in_array($value, array('yes', 'no', 'Y', 'N'))) {
                    return false;
                }
                break;
            case FIELD_TIMESTAMP:
                if (is_float((float) $value)) {
                    if ((float) $value >= PHP_INT_MAX || (float) $value <= ~PHP_INT_MAX) {
                        return false;
                    }
                } elseif (is_int((int) $value)) {
                    if ((int) $value >= PHP_INT_MAX || (int) $value <= ~PHP_INT_MAX) {
                        return false;
                    }
                } elseif (is_string($value)) {
                    if (strtotime($value) === false) {
                        return false;
                    }
                } else {
                    $this->raiseError(__METHOD__ .'(), unsupported timestamp type found!');
                    return false;
                }
                break;
            case FIELD_DATE:
                if (strtotime($value) === false) {
                    return false;
                }
                break;
            case FIELD_GUID:
                if (!$thallium->isValidGuidSyntax($value)) {
                    return false;
                }
                break;
            default:
                $this->raiseError(__METHOD__ ."(), unsupported type {$type} received!");
                return false;
                break;
        }

        return true;
    }

    public function flush()
    {
        if (!static::isHavingItems()) {
            return $this->flushTable();
        }

        if (!$this->delete()) {
            $this->raiseError(__CLASS__ .'::delete() returned false!');
            return false;
        }

        if (!$this->flushTable()) {
            $this->raiseError(__CLASS__ .'::flushTable() returned false!');
            return false;
        }

        return true;
    }

    public function flushTable()
    {
        global $db;

        try {
            $db->query(sprintf(
                "TRUNCATE TABLE TABLEPREFIX%s",
                static::$model_table_name
            ));
        } catch (\Exception $e) {
            $this->raiseError(__METHOD__ .'(), SQL command TRUNCATE TABLE failed!');
            return false;
        }

        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
