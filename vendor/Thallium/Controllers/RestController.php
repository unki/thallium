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

class RestController extends DefaultController
{
    protected $valid_request_methods = array(
        'GET',
        'POST'
    );

    protected static $paging_limit = 500;

    protected $request;
    protected $patterns = array();
    protected $handlers = array();

    public function __construct()
    {
        global $router;

        if (!isset($router) ||
            empty($router) ||
            !is_object($router) ||
            !is_a($router, 'Thallium\Controllers\HttpRouterController')
        ) {
            static::raiseError(__METHOD__ .'(), HttpRouterController is not loaded', true);
            return;
        }

        if (!$this->verifyRequest()) {
            static::raiseError(__CLASS__ .'::verifyRequest() returned false!', true);
            return;
        }

        $this->registerHandler('/.*/', array($this, 'readHandler'));
        return;
    }

    protected function verifyRequest()
    {
        global $router;

        if (!$this->checkRequestMethod()) {
            static::raiseError(__CLASS__ .', checkRequestMethod() returned false!');
            return false;
        }

        if (!$this->checkRequestUri()) {
            static::raiseError(__CLASS__ .', checkRequestUri() returned false!');
            return false;
        }

        if (!$this->checkRequestParams()) {
            static::raiseError(__CLASS__ .', checkRequestParams() returned false!');
            return false;
        }

        return true;
    }

    protected function checkRequestMethod()
    {
        global $router;

        if (!$router->hasQueryMethod()) {
            static::raiseError(get_class($router) .'::hasQueryMethod() returned false!');
            return false;
        }

        if (($method = $router->getQueryMethod()) === false) {
            static::raiseError(get_class($router) .'::getQueryMethod() returned false!');
            return false;
        }

        if (!isset($method) || empty($method) || !is_string($method)) {
            static::raiseError(__METHOD__ .'(), incomplete query method information found!');
            return false;
        }

        if (!$this->isValidRequestType($method)) {
            static::raiseError(__METHOD__ ."(), query method {$method} is not allowed!");
            return false;
        }

        $this->request['method'] = $method;
        return true;
    }

    protected function checkRequestUri()
    {
        global $router, $config;

        if (!$router->hasQueryUri()) {
            static::raiseError(get_class($router) .'::hasQueryUri() returned false!');
            return false;
        }

        if (($uri = $router->getQueryUri()) === false) {
            static::raiseError(get_class($router) .'::getQueryUri() returned false!');
            return false;
        }

        if (!isset($uri) || empty($uri) || !is_string($uri)) {
            static::raiseError(__METHOD__ .'(), no valid URI found!');
            return false;
        }

        // just to check if someone may fools us.
        if (substr_count($uri, '/') > 10) {
            static::raiseError(__METHOD__ .'(), REST request looks strange!');
            return false;
        }

        if (($webpath = $config->getWebPath()) === false) {
            $this->raiseErrro(get_class($config) .'::getWebPath() returned false!', true);
            return;
        }

        $uri = str_replace($webpath, '', $uri);

        // remove leading slashes if any
        $uri = ltrim($uri, '/');

        $uri = preg_replace('/^rest(\/.+)$/', '$1', $uri);
        $this->request['uri'] = trim($uri);

        return true;
    }

    protected function checkRequestParams()
    {
        global $router;

        if (!$router->hasQueryParams()) {
            static::raiseError(get_class($router) .'::hasQueryParams() returned false!');
            return false;
        }

        if (($params = $router->getQueryParams()) === false) {
            static::raiseError(get_class($router) .'::getQueryParams() returned false!');
            return false;
        }

        if (!isset($params) || empty($params) || !is_array($params)) {
            static::raiseError(__METHOD__ .'(), no valid REST request!');
            return false;
        }

        if ($params[0] !== 'rest') {
            static::raiseError(__METHOD__ .'(), first parameter is not "rest"!');
            return false;
        }

        unset($params[0]);

        if (!$this->addRequestParams($params)) {
            static::raiseError(__CLASS__ .'::addRequestParams() returned false!');
            return false;
        }

        return true;
    }

    public function handle()
    {
        if ($this->request['method'] === 'POST') {
            if (!$this->handlePostRequest()) {
                static::raiseError(__CLASS__ .'::handlePostRequest() returned false!');
                return false;
            }
        } elseif ($this->request['method'] === 'GET') {
            if (!$this->handleGetRequest()) {
                static::raiseError(__CLASS__ .'::handleGetRequest() returned false!');
                return false;
            }
        }

        if (($handler = $this->findHandler()) === false) {
            static::raiseError(__CLASS__ .'::findRequestHandler() returned false!');
            return false;
        }

        if (!isset($handler) ||
            empty($handler) ||
            !is_array($handler) ||
            !is_callable($handler)
        ) {
            static::raiseError(__METHOD__ .'(), no request handler found!');
            return false;
        }

        if (($retval = call_user_func($handler, $this->request)) === false) {
            static::raiseError(sprintf(
                '%s::%s() returned false!',
                get_class($handler[0]),
                $handler[1]
            ));
            return false;
        }

        if (is_bool($retval)) {
            return true;
        }

        if (($json_string = $this->packJson($retval)) === false) {
            static::raiseError(__CLASS__ .'::packJson() returned false!');
            return false;
        }

        print $json_string;
        return true;
    }

    protected function registerHandler($pattern, $handler)
    {
        if (!isset($pattern) ||
            empty($pattern) ||
            !is_string($pattern) ||
            !preg_match('/^\/.*\/$/', $pattern)
        ) {
            static::raiseError(__METHOD__ .'(), $pattern parameter is invalid!', true);
            return false;
        }

        if (!isset($handler) ||
            empty($handler) ||
            !is_array($handler) ||
            !is_callable($handler)
        ) {
            static::raiseError(__METHOD__ .'(), $handler parameter is invalid!', true);
            return false;
        }

        if (array_key_exists($pattern, $this->handlers)) {
            static::raiseError(__METHOD__ .'(), a handler with the same pattern has already been registered!', true);
            return false;
        }

        array_unshift($this->patterns, $pattern);
        array_unshift($this->handlers, $handler);
        return true;
    }

    protected function getPatterns()
    {
        if (!isset($this->patterns) || !is_array($this->patterns)) {
            static::raiseError(__METHOD__ .'(), $patterns is invalid!');
            return false;
        }

        return $this->patterns;
    }

    protected function getHandlers()
    {
        if (!isset($this->handlers) || !is_array($this->handlers)) {
            static::raiseError(__METHOD__ .'(), $handlers is invalid!');
            return false;
        }

        return $this->handlers;
    }

    protected function findHandler()
    {
        if (($patterns = $this->getPatterns()) === false) {
            static::raiseError(__CLASS__ .'::getPatterns() returned false!');
            return false;
        }

        foreach ($patterns as $pattern_id => $pattern_str) {
            if (!preg_match($pattern_str, $this->request['uri'])) {
                continue;
            }

            $matching_pattern = $pattern_id;
            break;
        }

        if (!isset($matching_pattern) || !is_numeric($matching_pattern)) {
            static::raiseError(__METHOD__ .'(), no matching pattern found!');
            return false;
        }

        if (($handler = $this->getHandler($matching_pattern)) === false) {
            static::raiseError(__CLASS__ .'::getHandlerForPattern() returned false!');
            return false;
        }

        return $handler;
    }

    protected function getHandler($pattern)
    {
        if (!isset($pattern) || (!is_numeric($pattern) && !is_string($pattern))) {
            static::raiseError(__METHOD__ .'(), $pattern parameter is invalid!');
            return false;
        }

        if (!array_key_exists($pattern, $this->handlers) ||
            !isset($this->handlers[$pattern]) ||
            empty($this->handlers[$pattern])
        ) {
            return false;
        }

        return $this->handlers[$pattern];
    }

    protected function isValidRequestType($type)
    {
        if (!isset($type) || empty($type) || !is_string($type)) {
            static::raiseError(__METHOD__ .'(), $type parameter is invalid!');
            return false;
        }

        if (!in_array(strtoupper($type), $this->valid_request_methods)) {
            return false;
        }

        return true;
    }

    protected function readHandler($load_by = null)
    {
        global $thallium;

        if (isset($load_by) && (
            empty($load_by) ||
            !is_array($load_by)
        )) {
            static::raiseError(__METHOD__ .'(), $load_by parameter is invalid!');
            return false;
        }

        if (!$this->hasRequestData()) {
            static::raiseError(__CLASS__ .'::hasRequestData() returned false!');
            return false;
        }

        if (($request = $this->getRequestData()) === false) {
            static::raiseError(__CLASS__ .'::getRequestData() returned false!');
            return false;
        }

        if (!isset($request) ||
            empty($request) ||
            !is_array($request) ||
            count($request) < 1 ||
            !array_key_exists(1, $request)
        ) {
            static::raiseError(__METHOD__ .'(), invalid request data received!');
            return false;
        }

        $request_object = $request[1];

        if (!$this->checkRequestObject($request_object)) {
            static::raiseError(__CLASS__ .'::checkRequestObject() returned false!');
            return false;
        }

        if (array_key_exists(2, $request)) {
            $request_selector = $request[2];

            if (!$this->checkRequestSelector($request_selector)) {
                static::raiseError(__CLASS__ .', checkRequestSelector() returned false!');
                return false;
            }
        }

        if (array_key_exists(3, $request)) {
            $request_field  = $request[3];

            if (!$this->checkRequestField($request_field)) {
                static::raiseError(__CLASS__ .', checkRequestField() returned false!');
                return false;
            }
        }

        if (($model_name = $thallium->getModelByNick($request_object)) === false) {
            static::raiseError(get_class($thallium) .'::getModelByNick() returned false!');
            return false;
        }

        if (($full_model = $thallium->getFullModelName($model_name)) === false) {
            static::raiseError(get_class($thallium) .'::getFullModelName() returned false!');
            return false;
        }

        if ($full_model::hasModelItems()) {
            if (array_key_exists('filter', $this->request['params'])) {
                $params = $this->request['params'];
                if (!isset($params['filter']) ||
                    empty($params['filter']) ||
                    !is_string($params['filter'])
                ) {
                    static::raiseError(__METHOD__ .'(), invalid filter provided!');
                    return false;
                }

                $filter = array(
                    'type' => 'text',
                    'data' => $params['filter']
                );

                if (($items = $full_model::find($filter, array('idx', 'guid', 'name'), true)) === false) {
                    static::raiseError($full_model .'::filter() returned false!');
                    return false;
                }

                if ($items->getItemsCount() > static::$paging_limit) {
                    return array('error' => 1, 'text' => 'too large result, please request paging or filter');
                }

                if (($items = $items->getItems()) === false) {
                    static::raiseError(get_class($model) .'::getItems() returned false!');
                    return false;
                }

                $result = array();

                foreach ($items as $item) {
                    if (!$item->hasIdx() || !$item->hasName()) {
                        continue;
                    }

                    if (($item_idx = $item->getIdx()) === false) {
                        static::raiseError(get_class($item) .'::getIdx() returned false!');
                        return false;
                    }

                    if (($item_name = $item->getName()) === false) {
                        static::raiseError(get_class($item) .'::getName() returned false!');
                        return false;
                    }

                    $result[] = array(
                        'idx' => $item_idx,
                        'name' => $item_name,
                    );
                }

                return $result;
            }

            try {
                $model = new $full_model;
            } catch (\Exception $e) {
                static::raiseError(__METHOD__ ."(), failed to load {$model_class}!");
                return false;
            }

            if ($model->getItemsCount() > static::$paging_limit) {
                return array('error' => 1, 'text' => 'too large result, please request paging or filter');
            }

            if (($items = $model->getItems()) === false) {
                static::raiseError(get_class($model) .'::getItems() returned false!');
                return false;
            }

            $result = array();

            foreach ($items as $item) {
                if (!$item->hasIdx() || !$item->hasName()) {
                    continue;
                }

                if (($item_idx = $item->getIdx()) === false) {
                    static::raiseError(get_class($item) .'::getIdx() returned false!');
                    return false;
                }

                if (($item_name = $item->getName()) === false) {
                    static::raiseError(get_class($item) .'::getName() returned false!');
                    return false;
                }

                $result[] = array(
                    'idx' => $item_idx,
                    'name' => $item_name,
                );
            }

            return $result;
        }

        if (!$full_model::hasModelFields()) {
            static::raiseError(__METHOD__ .'(), model has no fields nor items!');
            return false;
        }

        if (!isset($load_by)) {
            $load_by = array();
        }

        if (is_numeric($request_selector) and $request_selector >= 1) {
            $load_by = array(
                FIELD_IDX => $request_selector,
            );
        } elseif (is_string($request_selector) and $thallium->isValidGuidSyntax($request_selector)) {
            $load_by = array(
                FIELD_GUID => $request_selector,
            );
        }

        try {
            $model = new $full_model($load_by);
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ ."(), failed to load {$model_class}!");
            return false;
        }

        if (isset($request_field)) {
            if (!$full_model::hasField($request_field) &&
                (!$model->hasVirtualFields() ||
                !$model->hasVirtualField($request_field))
            ) {
                static::raiseError(__METHOD__ .'(), requested model has no field like requested.');
                return false;
            }

            if (($value = $model->getFieldValue($request_field)) === false) {
                static::raiseError(get_class($model) .'::getFieldValue() returned false!');
                return false;
            }

            return array(
                'name' => $request_field,
                'value' => $value,
            );
        }

        if (($values = $model->getFieldValues()) === false) {
            static::raiseError(get_class($model) .'::getFieldValues() returned false!');
            return false;
        }

        return $values;
    }

    protected function packJson($data)
    {
        if (!isset($data) || (!is_object($data) && !is_array($data))) {
            static::raiseError(__METHOD__ .'(), $data parameter is invalid!');
            return false;
        }

        $filtered_data = array();

        try {
            $json_str = json_encode($data);
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), json_encode() returned false!', false, $e);
            return false;
        }

        if (!isset($json_str) ||
            empty($json_str) ||
            !is_string($json_str)
        ) {
            static::raiseError(__METHOD__ .'(), json_encode() returned something invalid!');
            return false;
        }

        try {
            $json_ary = json_encode(array(
                'type' => 'reply',
                'length' => strlen($json_str),
                'data' => $json_str
            ));
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), json_encode() returned false!', false, $e);
            return false;
        }

        return $json_ary;
    }

    protected function handlePostRequest()
    {
        if (!$this->verifyPostRequest()) {
            static::raiseError(__CLASS__ .'::verifyPostRequest() returned false!');
            return false;
        }

        if (($data = $this->getPostData()) === false) {
            static::raiseError(__CLASS__ .'::getPostData() returned false!');
            return false;
        }

        try {
            $json = json_decode($data);
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), json_decode() returned false!', false, $e);
            return false;
        }

        if (!$this->addRequestData($json)) {
            static::raiseError(__CLASS__ .'::addRequestData() returned false!');
            return false;
        }

        return true;
    }

    protected function handleGetRequest()
    {
        if (($params = $this->getRequestParams()) === false) {
            static::raiseError(__CLASS__ .'::getRequestParams() returned false!');
            return false;
        }

        if (!$this->addRequestData($params)) {
            static::raiseError(__CLASS__ .'::addRequestData() returned false!');
            return false;
        }

        return true;
    }

    protected function verifyPostRequest()
    {
        global $router;

        // normally we get some JSON here. so just verify we are not hit by something else
        if (($content_type = $router->getHttpHeaders('Content-Type')) === false) {
            static::raiseError(__METHOD__ .'(), Content-Type header is not set!');
            return false;
        }

        if (empty($content_type) || $content_type !== 'application/json') {
            static::raiseError(__METHOD__ .'(), unsupported Content-Type header found!');
            return false;
        }

        if (isset($_POST) && !empty($_POST)) {
            static::raiseError(__METHOD__ .'(), for REST API requests, _POST should not be set!');
            return false;
        }

        return true;
    }

    protected function getPostData()
    {
        if (($data = file_get_contents('php://input')) === false) {
            static::raiseError(__METHOD__ .'(), failed to read from php://input!');
            return false;
        }

        if (!isset($data)) {
            static::raiseError(__METHOD__ .'(), no valid data found!');
            return false;
        }

        return $data;
    }

    protected function invokeRequestHandler($request)
    {
        try {
            $req = new \MasterShaper\Controllers\RequestController;
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), failed to load RequestController!');
            return false;
        }

        if (!isset($request->what) || empty($request->what)) {
            static::raiseError(__METHOD__ .'(), request contains no "what"!');
            return false;
        }

        if (!isset($request->param1) || empty($request->param1)) {
            static::raiseError(__METHOD__ .'(), request contains no "param1"!');
            return false;
        }

        if (!isset($request->uuid) || empty($request->uuid)) {
            static::raiseError(__METHOD__ .'(), request contains no uuid!');
            return false;
        }

        if (($reply = $req->request($request)) === false) {
            static::raiseError(get_class($req) .'::request() returned false!');
            return false;
        }

        return $reply;
    }

    protected function checkRequestObject($object)
    {
        if (!isset($object) || empty($object) || !is_string($object)) {
            static::raiseError(__METHOD__ .'(), $object parameter is invalid!');
            return false;
        }

        return true;
    }

    protected function checkRequestSelector($selector)
    {
        if (!isset($selector) ||
            empty($selector) ||
            (!is_string($selector) && !is_numeric($selector))
        ) {
            static::raiseError(__METHOD__ .'(), $requestor parameter is invalid!');
            return false;
        }

        return true;
    }

    protected function checkRequestField($field)
    {
        if (!isset($field) || empty($field) || !is_string($field)) {
            return false;
        }

        return true;
    }

    protected function checkRequestSubProperty()
    {
        if (!isset($this->request_params[4]) ||
            empty($this->request_params[4])
        ) {
            return true;
        }

        if (!is_string($this->request_params[4])) {
            static::raiseError(__METHOD__ .'(), no valid subproperty requested by REST query!');
            return false;
        }

        $this->request_subproperty= $this->request_params[4];
        return true;
    }

    protected function addRequestData($data)
    {
        if (!isset($data) ||
            (!is_array($data) && !is_object($data)) ||
            (is_object($data) && !is_a($data, '\stdClass'))
        ) {
            static::raiseError(__METHOD__ .'(), $data parameter is invalid!');
            return false;
        }

        $this->request['data'] = $data;
        return true;
    }

    protected function hasRequestData()
    {
        if (!array_key_exists('data', $this->request) ||
            !isset($this->request['data']) ||
            empty($this->request['data']) ||
            (!is_array($this->request['data']) && !is_object($this->request['data']))
        ) {
            return false;
        }

        return true;
    }

    protected function getRequestData()
    {
        if (!$this->hasRequestData()) {
            static::raiseError(__CLASS__ .'::hasRequestData() returned false!');
            return false;
        }

        return $this->request['data'];
    }

    protected function addRequestParams($params)
    {
        if (!isset($params) || empty($params) || !is_array($params)) {
            static::raiseError(__METHOD__ .'(), $params parameter is invalid!');
            return false;
        }

        $this->request['params'] = $params;
        return true;
    }

    protected function hasRequestParams()
    {
        if (!array_key_exists('params', $this->request) ||
            !isset($this->request['params']) ||
            empty($this->request['params']) ||
            !is_array($this->request['params'])
        ) {
            return false;
        }

        return true;
    }

    protected function getRequestParams()
    {
        if (!$this->hasRequestParams()) {
            static::raiseError(__CLASS__ .'::hasRequestParams() returned false!');
            return false;
        }

        return $this->request['params'];
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
