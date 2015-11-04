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

class RpcController extends DefaultController
{
    public function perform()
    {
        global $router, $query;

        if (!isset($query->action)) {
            $this->raiseError("No action specified!");
        }

        if (!$router->isValidRpcAction($query->action)) {
            $this->raiseError("Invalid RPC action: ". htmlentities($query->action, ENT_QUOTES));
            return false;
        }

        switch ($query->action) {
            case 'delete':
                $this->rpcDelete();
                break;
            case 'add':
            case 'update':
                $this->rpcUpdate();
                break;
            case 'find-prev-next':
                $this->rpcFindPrevNextObject();
                break;
            /*case 'toggle':
                $this->rpc_toggle_object_status();
                break;
            case 'clone':
                $this->rpc_clone_object();
                break;
            case 'alter-position':
                $this->rpc_alter_position();
                break;
            case 'get-sub-menu':
                $this->rpc_get_sub_menu();
                break;*/
            case 'get-content':
                $this->rpcGetContent();
                break;
            case 'submit-messages':
                $this->rpcSubmitToMessageBus();
                break;
            case 'retrieve-messages':
                $this->rpcRetrieveFromMessageBus();
                break;
            case 'idle':
                // just do nothing, for debugging
                print "ok";
                break;
            default:
                if (!method_exists($this, 'performApplicationSpecifc')) {
                    $this->raiseError("Unknown RPC action\n");
                    return false;
                }
                if (!$this->performApplicationSpecifc()) {
                    $this->raiseError(__CLASS__ .'::performApplicationSpecifc() returned false!');
                    return false;
                }
                break;
        }

        return true;
    }

    protected function rpcDelete()
    {
        global $thallium;

        $input_fields = array('id', 'guid', 'model');

        foreach ($input_fields as $field) {
            if (!isset($_POST[$field])) {
                $this->raiseError(__METHOD__ ."'{$field}' isn't set in POST request!");
                return false;
            }
            if (empty($_POST[$field])) {
                $this->raiseError(__METHOD__ ."'{$field}' is empty!");
                return false;
            }
            if (!is_string($_POST[$field]) && !is_numeric($_POST[$field])) {
                $this->raiseError(__METHOD__ ."'{$field}' is not from a valid type!");
                return false;
            }
        }

        $id = $_POST['id'];
        $guid = $_POST['guid'];
        $model = $_POST['model'];

        if (!$thallium->isValidId($id) && $id != 'flush') {
            $this->raiseError(__METHOD__ .', \$id is invalid!');
            return false;
        }

        if (!$thallium->isValidGuidSyntax($guid) && $guid != 'flush') {
            $this->raiseError(__METHOD__ .', \$guid is invalid!');
            return false;
        }

        if (($model_name = $thallium->getModelByNick($model)) === false) {
            $this->raiseError(get_class($thallium) .'::getModelNameByNick() returned false!');
            return false;
        }

        /* special delete operation 'flush' */
        if ($id == 'flush' && $guid == 'flush') {
            if (($obj = $thallium->loadModel($model_name)) === false) {
                $this->raiseError(get_class($thallium) .'::loadModel() returned false!');
                return false;
            }

            if (!method_exists($obj, 'flush')) {
                $this->raiseError(__METHOD__ ."(), model {$model_name} does not provide a flush() method!");
                return false;
            }
            if (!$obj->permitsRpcActions('flush')) {
                $this->raiseError(__METHOD__ ."(), model {$model_name} does not support flush-opertions!");
                return false;
            }
            if (!$obj->flush()) {
                $this->raiseError(get_class($obj) .'::flush() returned false!');
                return false;
            }
            print "ok";
            return true;
        }

        if (($obj = $thallium->loadModel($model_name, $id, $guid)) === false) {
            $this->raiseError(get_class($thallium) .'::loadModel() returned false!');
            return false;
        }

        if (!method_exists($obj, 'delete')) {
            $this->raiseError(__METHOD__ ."(), model {$model_name} does not provide a delete() method!");
            return false;
        }

        if (!$obj->permitsRpcActions('delete')) {
            $this->raiseError(get_class($obj) .' does not permit "delete" via a RPC call!');
            return false;
        }

        if (!$obj->delete()) {
            $this->raiseError(get_class($obj) .'::delete() returned false!');
            return false;
        }

        print "ok";
        return true;
    }

    protected function rpcGetContent()
    {
        global $views;

        $valid_content = array(
                'preview',
        );

        if (!isset($_POST['content'])) {
            $this->raiseError('No content requested!');
            return false;
        }

        if (!in_array($_POST['content'], $valid_content)) {
            $this->raiseError('unknown content requested: '. htmlentities($_POST['content'], ENT_QUOTES));
            return false;
        }

        switch ($_POST['content']) {
            case 'preview':
                $content = $views->load('PreviewView', false);
                break;
        }

        if (isset($content) && !empty($content)) {
            print $content;
            return true;
        }

        $this->raiseError("No content found!");
        return false;
    }

    protected function rpcFindPrevNextObject()
    {
        global $thallium, $views;

        $valid_models = array(
            'queueitem',
        );

        $valid_directions = array(
            'next',
            'prev',
        );

        if (!isset($_POST['model'])) {
            $this->raiseError('No model requested!');
            return false;
        }

        if (!in_array($_POST['model'], $valid_models)) {
            $this->raiseError('unknown model requested: '. htmlentities($_POST['model'], ENT_QUOTES));
            return false;
        }

        if (!isset($_POST['id'])) {
            $this->raiseError('id is not set!');
            return false;
        }

        $id = $_POST['id'];

        if (!$thallium->isValidId($id)) {
            $this->raiseError('\$id is invalid');
            return false;
        }

        if (!isset($_POST['direction'])) {
            $this->raiseError('direction is not set!');
            return false;
        }

        if (!in_array($_POST['direction'], $valid_directions)) {
            $this->raiseError('invalid direction requested: '. htmlentities($_POST['direction'], ENT_QUOTES));
            return false;
        }

        if (($id = $thallium->parseId($id)) === false) {
            $this->raiseError('Unable to parse \$id');
            return false;
        }

        switch ($id->model) {
            case 'queueitem':
                $model = new \Thallium\Models\QueueItemModel($id->id, $id->guid);
                break;
        }

        if (!isset($model) || empty($model)) {
            $this->raiseError("Model not found: ". htmlentities($id->modek, ENT_QUOTES));
            return false;
        }

        switch ($_POST['direction']) {
            case 'prev':
                $prev = $model->prev();
                if ($prev) {
                    print "queueitem-". $prev;
                }
                break;
            case 'next':
                $next = $model->next();
                if ($next) {
                    print "queueitem-". $next;
                }
                break;
        }

        return true;
    }

    protected function rpcUpdate()
    {
        global $thallium;

        $input_fields = array('key', 'id', 'value', 'model');

        foreach ($input_fields as $field) {
            if (!isset($_POST[$field])) {
                $this->raiseError(__METHOD__ ."(), '{$field}' isn't set in POST request!");
                return false;
            }
            if (empty($_POST[$field])) {
                $this->raiseError(__METHOD__ ."(), '{$field}' is empty!");
                return false;
            }
            if (!is_string($_POST[$field]) && !is_numeric($_POST[$field])) {
                $this->raiseError(__METHOD__ ."(), '{$field}' is not from a valid type!");
                return false;
            }
        }

        $key = strtolower($_POST['key']);
        $id = $_POST['id'];
        $value = $_POST['value'];
        $model = $_POST['model'];

        if (!(preg_match("/^([a-z]+)_([a-z_]+)$/", $key, $parts))) {
            $this->raiseError(__METHOD__ ."() , key looks invalid!");
            return false;
        }

        if ($id != 'new' && !is_numeric($id)) {
            $this->raiseError(__METHOD__ ."(), id is invalid!");
            return false;
        }

        if (!$thallium->isValidModel($model)) {
            $this->raiseError(__METHOD__ ."(), scope contains an invalid model ({$model})!");
            return false;
        }

        if ($id == 'new') {
            $id = null;
        }

        if (($model_name = $thallium->getModelByNick($model)) === false) {
            $this->raiseError(get_class($thallium) .'::getModelNameByNick() returned false!');
            return false;
        }

        if (!($obj = $thallium->loadModel($model_name, $id))) {
            $this->raiseError(__METHOD__ ."(), failed to load {$model}!");
            return false;
        }

        // check if model permits RPC updates
        if (!$obj->permitsRpcUpdates()) {
            $this->raiseError(__METHOD__ ."(), model {$model} denys RPC updates!");
            return false;
        }

        if (!$obj->permitsRpcUpdateToField($key)) {
            $this->raiseError(__METHOD__ ."(), model {$model} denys RPC updates to field {$key}!");
            return false;
        }

        // sanitize input value
        $value = htmlentities($value, ENT_QUOTES);
        $obj->$key = stripslashes($value);

        if (!$obj->save()) {
            $this->raiseError(get_class($obj) ."::save() returned false!");
            return false;
        }

        print "ok";
        return true;
    }

    protected function rpcSubmitToMessageBus()
    {
        global $mbus;

        if (!isset($_POST['messages']) ||
            empty($_POST['messages']) ||
            !is_string($_POST['messages'])
        ) {
            $this->raiseError(__METHOD__ .'(), no message submited!');
            return false;
        }

        if (!$mbus->submit($_POST['messages'])) {
            $this->raiseError(get_class($mbus) .'::submit() returned false!');
            return false;
        }

        print "ok";
        return true;
    }

    protected function rpcRetrieveFromMessageBus()
    {
        global $mbus;

        if (!($messages = $mbus->poll())) {
            $this->raiseError(get_class($mbus) .'::poll() returned false!');
            return false;
        }

        if (!is_string($messages)) {
            $this->raiseError(get_class($mbus) .'::poll() has not returned a string!');
            return false;
        }

        print $messages;
        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
