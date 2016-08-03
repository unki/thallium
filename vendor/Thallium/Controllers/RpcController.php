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

/**
 * RpcController handles remote-procedure-call requests made by
 * clients. This should only be used seldomly and MessageBus
 * is a better joice.
 *
 * @package Thallium\Controllers\RpcController
 * @subpackage Controllers
 * @license AGPL3
 * @copyright 2015-2016 Andreas Unterkircher <unki@netshadow.net>
 * @author Andreas Unterkircher <unki@netshadow.net>
 */
class RpcController extends DefaultController
{
    /**
     * class constructur
     *
     * @param none
     * @return void
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function __construct()
    {
        global $router, $query;

        if (!isset($router) ||
            empty($router) ||
            !is_object($router) ||
            !is_a($router, 'Thallium\Controllers\HttpRouterController')
        ) {
            static::raiseError(__METHOD__ .'(), HttpRouterController not loaded!', true);
            return;
        }

        if (!isset($query) ||
            empty($query) ||
            !is_object($query) ||
            !is_a($query, 'stdClass')
        ) {
            static::raiseError(__METHOD__ .'(), $query not correctly set!', true);
            return;
        }

        return;
    }

    /**
     * this method is actually kicked from the MainController, if
     * that one seeѕ an inbound RPC request.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function perform()
    {
        global $router, $query;

        if (!isset($query->action) || empty($query->action) || !is_string($query->action)) {
            static::raiseError(__METHOD__ .'(), no action has been specified!');
            return false;
        }

        if (!$router->isValidRpcAction($query->action)) {
            static::raiseError(get_class($router) .'::isValidRpcAction() returned false!');
            return false;
        }

        if ($query->action == 'delete') {
            $rpc_method = 'rpcDelete';
        } elseif ($query->action == 'add' || $query->action == 'update') {
            $rpc_method = 'rpcUpdate';
        } elseif ($query->action == 'find-prev-next') {
            $rpc_method = 'rpcFindPrevNextObject';
        } elseif ($query->action == 'get-content') {
            $rpc_method = 'rpcGetContent';
        } elseif ($query->action == 'submit-messages') {
            $rpc_method == 'rpcSubmitToMessageBus';
        } elseif ($query->action == 'retrieve-messages') {
            $rpc_method == 'rpcRetrieveFromMessageBus';
        } elseif ($query->action == 'process-messages') {
            $rpc_method == 'rpcProcessMessages';
        } elseif ($query->action == 'idle') {
            $rpc_method == 'rpcIdle';
        } elseif (method_exists($this, 'performApplicationSpecifc')) {
            $rpc_method == 'performApplicationSpecifc';
        }

        if (!isset($rpc_method) || empty($rpc_method) || !is_string($rpc_method)) {
            static::raiseError(__METHOD__ .'(), no matching RPC action found!');
            return false;
        }

        if (!method_exists($this, $rpc_method)) {
            static::raiseError(sprintf(
                '%s(), RPC method "%s" does not exist!',
                __METHOD__,
                $rpc_method
            ));
            return false;
        }

        if (!is_callable(array($this, $rpc_method))) {
            static::raiseError(sprintf(
                '%s(), RPC method "%s" is not callable!',
                __METHOD__,
                $rpc_method
            ));
            return false;
        }

        if (!$this->$rpc_method()) {
            static::raiseError(sprintf(
                '%s::%s() has returned false!',
                __CLASS__,
                $rpc_method
            ));
            return false;
        }

        return true;
    }

    /**
     * idle method, actually does nothing.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function rpcIdle()
    {
        /* nothing to be done here */
        print "ok";
        return true;
    }

    /**
     * deletes an object
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function rpcDelete()
    {
        global $thallium;

        $input_fields = array('id', 'guid', 'model');

        foreach ($input_fields as $field) {
            if (!isset($_POST[$field])) {
                static::raiseError(__METHOD__ ."(), '{$field}' isn't set in POST request!");
                return false;
            }
            if (empty($_POST[$field])) {
                static::raiseError(__METHOD__ ."(), '{$field}' is empty!");
                return false;
            }
            if (!is_string($_POST[$field]) && !is_numeric($_POST[$field])) {
                static::raiseError(__METHOD__ ."(), '{$field}' is not from a valid type!");
                return false;
            }
        }

        $id = $_POST['id'];
        $guid = $_POST['guid'];
        $model = $_POST['model'];

        if (!$thallium->isValidId($id) && $id !== 'flush') {
            static::raiseError(__METHOD__ .'(), $id is invalid!');
            return false;
        }

        if (!$thallium->isValidGuidSyntax($guid) && $guid !== 'flush') {
            static::raiseError(__METHOD__ .'(), $guid is invalid!');
            return false;
        }

        if (($model_name = $thallium->getModelByNick($model)) === false) {
            static::raiseError(get_class($thallium) .'::getModelNameByNick() returned false!');
            return false;
        }

        /* special delete operation 'flush' */
        if ($id === 'flush' && $guid === 'flush') {
            if (($obj = $thallium->loadModel($model_name)) === false) {
                static::raiseError(get_class($thallium) .'::loadModel() returned false!');
                return false;
            }

            if (!method_exists($obj, 'flush')) {
                static::raiseError(__METHOD__ ."(), model {$model_name} does not provide a flush() method!");
                return false;
            }

            if (!$obj->permitsRpcActions('flush')) {
                static::raiseError(__METHOD__ ."(), model {$model_name} does not support flush-opertions!");
                return false;
            }

            if (!$obj->flush()) {
                static::raiseError(get_class($obj) .'::flush() returned false!');
                return false;
            }

            print "ok";
            return true;
        }

        if (($obj = $thallium->loadModel($model_name, $id, $guid)) === false) {
            static::raiseError(get_class($thallium) .'::loadModel() returned false!');
            return false;
        }

        if (!method_exists($obj, 'delete')) {
            static::raiseError(__METHOD__ ."(), model {$model_name} does not provide a delete() method!");
            return false;
        }

        if (!$obj->permitsRpcActions('delete')) {
            static::raiseError(get_class($obj) .' does not permit "delete" via a RPC call!');
            return false;
        }

        if (!$obj->delete()) {
            static::raiseError(get_class($obj) .'::delete() returned false!');
            return false;
        }

        print "ok";
        return true;
    }

    /**
     * retrieves content from a specific template.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function rpcGetContent()
    {
        global $views;

        $valid_content = array(
            'preview',
        );

        if (!array_key_exists('content', $_POST) ||
            !isset($_POST['content']) ||
            empty($_POST['content']) ||
            !is_string($_POST['content'])
        ) {
            static::raiseError(__METHOD__ .'(), no content requested!');
            return false;
        }

        if (!in_array($_POST['content'], $valid_content)) {
            static::raiseError(__METHOD__ .'(), no valid content requested!');
            return false;
        }

        switch ($_POST['content']) {
            case 'preview':
                if (($content = $views->load('PreviewView', false)) === false) {
                    static::raiseError(get_class($views) .'::load() returned false!');
                    return false;
                }
                break;
        }

        if (!isset($content) || empty($content) || !is_string($content)) {
            static::raiseError(__METHOD__ .'(), no content returned from View!');
            return false;
        }

        print $content;
        return true;
    }

    /**
     * returns the previous or next object
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
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

        if (!array_key_exists('model', $_POST) ||
            !isset($_POST['model']) ||
            empty($_POST['model']) ||
            !is_string($_POST['model'])
        ) {
            static::raiseError(__METHOD__ .'(), no model requested!');
            return false;
        }

        if (!in_array($_POST['model'], $valid_models)) {
            static::raiseError(__METHOD__ .'(), unknown model requested!');
            return false;
        }

        if (!array_key_exists('id', $_POST) ||
            !isset($_POST['id']) ||
            empty($_POST['id']) ||
            !is_string($_POST['id'])
        ) {
            static::raiseError(__METHOD__ .'(), no id provided!');
            return false;
        }

        $id = $_POST['id'];

        if (!$thallium->isValidId($id)) {
            static::raiseError(__METHOD__ .'(), invalid id provided!');
            return false;
        }

        if (!array_key_exists($_POST['direction']) ||
            !isset($_POST['direction']) ||
            empty($_POST['direction']) ||
            !is_string($_POST['direction'])
        ) {
            static::raiseError(__METHOD__ .'(), no direction provided!');
            return false;
        }

        if (!in_array($_POST['direction'], $valid_directions)) {
            static::raiseError(__METHOD__ .'(), invalid direction requested!');
            return false;
        }

        $direction = $_POST['direction'];

        if (($id = $thallium->parseId($id)) === false) {
            static::raiseError('Unable to parse \$id');
            return false;
        }

        switch ($id->model) {
            case 'queueitem':
                $model_name = '\Thallium\Models\QueueItemModel';
                break;
        }

        if (!isset($model_name) || empty($model_name) || !is_string($model_name)) {
            static::raiseError(__METHOD__ .'(), no model found!');
            return false;
        }

        try {
            $model = new $model_name(array(
                'idx' => $id->id,
                'guid' => $id->guid
            ));
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), failed to load model!', false, $e);
            return false;
        }

        if (($model_nick = $model->getModelNickName()) === false) {
            static::raiseError(get_class($model) .'::getModelNickName() returned false!');
            return false;
        }

        if (($neighbor = $model->$direction()) === false) {
            static::raiseError(sprintf(
                '%s:%s() returned false!',
                get_class($model),
                $direction
            ));
            return false;
        }

        print sprintf("%s-%s", $model_nick, $neighbor);
        return true;
    }

    /**
     * adds or update an object
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function rpcUpdate()
    {
        global $thallium;

        $input_fields = array(
            'key',
            'id',
            'value',
            'model'
        );

        foreach ($input_fields as $field) {
            if (!isset($_POST[$field])) {
                static::raiseError(__METHOD__ ."(), '{$field}' isn't set in POST request!");
                return false;
            }
            if (empty($_POST[$field]) && $field != 'value') {
                static::raiseError(__METHOD__ ."(), '{$field}' is empty!");
                return false;
            }
            if (!is_string($_POST[$field]) && !is_numeric($_POST[$field])) {
                static::raiseError(__METHOD__ ."(), '{$field}' is not from a valid type!");
                return false;
            }
        }

        $key = strtolower($_POST['key']);
        $id = $_POST['id'];
        $value = $_POST['value'];
        $model = $_POST['model'];

        if (!(preg_match("/^([a-z]+)_([a-z_]+)$/", $key, $parts))) {
            static::raiseError(__METHOD__ ."() , key looks invalid!");
            return false;
        }

        if ($id !== 'new' && !is_numeric($id)) {
            static::raiseError(__METHOD__ ."(), id is invalid!");
            return false;
        }

        if (!$thallium->isValidModel($model)) {
            static::raiseError(get_class($thallium) .'::isValidModel() returned false!');
            return false;
        }

        if ($id === 'new') {
            $id = null;
        }

        if (($model_name = $thallium->getModelByNick($model)) === false) {
            static::raiseError(get_class($thallium) .'::getModelNameByNick() returned false!');
            return false;
        }

        if (($obj = $thallium->loadModel($model_name, $id)) === false) {
            static::raiseError(get_class($thallium) .'::loadModel() returned false!');
            return false;
        }

        // check if model permits RPC updates
        if (!$obj->permitsRpcUpdates()) {
            static::raiseError(__METHOD__ ."(), model {$model} denys RPC updates!");
            return false;
        }

        if (!$obj->permitsRpcUpdateToField($key)) {
            static::raiseError(__METHOD__ ."(), model {$model} denys RPC updates to field {$key}!");
            return false;
        }

        // sanitize input value
        $value = htmlentities($value, ENT_QUOTES);
        $obj->$key = stripslashes($value);

        if (!$obj->save()) {
            static::raiseError(get_class($obj) ."::save() returned false!");
            return false;
        }

        print "ok";
        return true;
    }

    /**
     * submit one or messages into the message bus.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function rpcSubmitToMessageBus()
    {
        global $mbus;

        if (!array_key_exists('messages', $_POST) ||
            !isset($_POST['messages']) ||
            empty($_POST['messages']) ||
            !is_string($_POST['messages'])
        ) {
            static::raiseError(__METHOD__ .'(), no message submited!');
            return false;
        }

        if (!$mbus->submit($_POST['messages'])) {
            static::raiseError(get_class($mbus) .'::submit() returned false!');
            return false;
        }

        print "ok";
        return true;
    }

    /**
     * retrieve messages from message bus.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function rpcRetrieveFromMessageBus()
    {
        global $mbus;

        if (($messages = $mbus->poll()) === false) {
            static::raiseError(get_class($mbus) .'::poll() returned false!');
            return false;
        }

        if (!is_string($messages)) {
            static::raiseError(get_class($mbus) .'::poll() has not returned a string!');
            return false;
        }

        print $messages;
        return true;
    }

    /**
     * a client can trigger the server to process messages waiting in the
     * message bus.
     *
     * @param none
     * @return object
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function rpcProcessMessages()
    {
        global $thallium;

        if (!$thallium->processRequestMessages()) {
            static::raiseError(get_class($thallium) .'::processRequestMessages() returned false!');
            return false;
        }

        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
