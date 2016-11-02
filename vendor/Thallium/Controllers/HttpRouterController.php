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
 * HttpRouterController parses client requests and takes in user data
 * that is provided by $_GET, $_POST, etc.
 *
 * @package Thallium\Controllers\HttpRouterController
 * @subpackage Controllers
 * @license AGPL3
 * @copyright 2015-2016 Andreas Unterkircher <unki@netshadow.net>
 * @author Andreas Unterkircher <unki@netshadow.net>
 */
class HttpRouterController extends DefaultController
{
    /** @var \stdClass $query holds meta information about the HTTP query */
    protected $query;

    /** @var array $query_parts holds the exploded parts of the HTTP request query */
    protected $query_parts;

    /** @var array $httpRequestHeaders holds the HTTP request header information */
    protected $httpRequestHeaders;

    /** @var array $valid_request_methods supported list of HTTP request methods */
    protected static $valid_request_methods = array(
        'GET',
        'POST',
    );

    /** @var array $valid_request_methods supported list of actions to be trigged by a HTTP request */
    protected static $valid_request_actions = array(
        'overview',
        'login',
        'logout',
        'show',
        'list',
        'new',
        'edit',
        'rpc.html',
    );

    /** @var array $valid_rpc_actions supported list of remote procedure calls (RPC) */
    protected $valid_rpc_actions = array(
        'add',
        'update',
        'delete',
        'find-prev-next',
        'get-content',
        'submit-messages',
        'retrieve-messages',
        'process-messages',
        /*'toggle',
        'clone',
        'alter-position',
        'get-sub-menu',
        'set-host-profile',
        'get-host-state',
        'idle',*/
    );

    protected static $allowed_get_parameters = array(
        'items-per-page' => array(
            'filter' => FILTER_VALIDATE_INT | FILTER_SANITIZE_NUMBER_INT,
        ),
    );
    protected static $allowed_post_parameters = array(
        'type' => array(
            'filter' =>  FILTER_SANITIZE_STRING,
        ),
        'action' => array(
            'filter' => FILTER_SANITIZE_STRING,
        ),
        'id' => array(
            'filter' => FILTER_VALIDATE_INT | FILTER_SANITIZE_NUMBER_INT,
        ),
        'guid' => array(
            'filter' => FILTER_SANITIZE_STRING,
        ),
        'model' => array(
            'filter' => FILTER_SANITIZE_STRING,
        ),
        'key' => array(
            'filter' => FILTER_SANITIZE_STRING,
        ),
        'value' => array(
            'filter' => FILTER_SANITIZE_STRING,
        ),
        'messages' => array(
            'filter' => FILTER_UNSAFE_RAW,
        ),
        'view' => array(
            'filter' => FILTER_SANITIZE_STRING,
        ),
        'data' => array(
            'filter' => FILTER_SANITIZE_STRING,
        ),
        'direction' => array(
            'filter' => FILTER_SANITIZE_STRING,
        ),
        'search' => array(
            'filter' => FILTER_SANITIZE_STRING,
        ),
    );

    protected static $allowed_server_parameters = array(
        'REQUEST_URI' => array(
            'filter' => FILTER_SANITIZE_STRING,
        ),
        'REQUEST_METHOD' => array(
            'filter' => FILTER_SANITIZE_STRING,
        ),
    );

    /**
     * class constructor
     *
     * @param none
     * @return void
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function __construct()
    {
        global $thallium, $config;

        $this->query = new \stdClass();

        if (!$this->getRequestHeaders()) {
            static::raiseError(__CLASS__ .'::getRequestHeaders() returned false!', true);
            return;
        }

        $filtered_server = filter_input_array(INPUT_SERVER, static::$allowed_server_parameters);

        if ($filtered_server === false || (!is_null($filtered_server) && !is_array($filtered_server))) {
            static::raiseError(__METHOD__ .'(), failure on retrieving SERVER variables!', true);
            return false;
        }

        /**
         * in test mode, fake some HTTP request parameters.
         */
        if ($thallium->inTestMode()) {
            $filtered_server['REQUEST_URI'] = sprintf(
                '/thallium/documents/show/%d-%s?testparam=foobar',
                1,
                '0123456789012345678901234567890123456789012345678901234567890123'
            );
            $filtered_server['REQUEST_METHOD'] = 'GET';
        }

        if (!array_key_exists('REQUEST_URI', $filtered_server) ||
            !isset($filtered_server['REQUEST_URI']) ||
            empty($filtered_server['REQUEST_URI']) ||
            !is_string($filtered_server['REQUEST_URI'])
        ) {
            static::raiseError(__METHOD__ .'(), $_SERVER["REQUEST_URI"] is not set!', true);
            return;
        }

        if (!array_key_exists('REQUEST_METHOD', $filtered_server) ||
            !isset($filtered_server['REQUEST_METHOD']) ||
            empty($filtered_server['REQUEST_METHOD']) ||
            !is_string($filtered_server['REQUEST_METHOD'])
        ) {
            static::raiseError(__METHOD__ .'(), $_SERVER["REQUEST_METHOD"] is not set!', true);
            return;
        }

        if (!static::isValidRequestMethod($filtered_server['REQUEST_METHOD'])) {
            static::raiseError(__METHOD__ .'(), unsupported request method found!', true);
            return;
        }

        if (!$this->setQueryMethod($filtered_server['REQUEST_METHOD'])) {
            static::raiseError(__CLASS__ .'::setQueryMethod() returned false!', true);
            return;
        }

        if (!$this->setQueryUri($filtered_server['REQUEST_URI'])) {
            static::raiseError(__CLASS__ .'::setQueryUri() returned false!', true);
            return;
        }

        // check HTTP request URI
        $uri = $filtered_server['REQUEST_URI'];

        // just to check if someone may fools us.
        if (substr_count($uri, '/') > 10) {
            static::raiseError(__METHOD__ .'(), request looks strange - are you try to fooling us?', true);
            return;
        }

        if (($webpath = $config->getWebPath()) === false) {
            $this->raiseErrro(get_class($config) .'::getWebPath() returned false!', true);
            return;
        }

        // strip off our known base path (e.g. /thallium)
        if ($webpath !== '/') {
            $uri = str_replace($webpath, "", $uri);
        }

        // remove leading slashes if any
        $uri = ltrim($uri, '/');

        // explode string into an array
        $this->query_parts = explode('/', $uri);

        if (!is_array($this->query_parts) ||
            empty($this->query_parts) ||
            count($this->query_parts) < 1
        ) {
            static::raiseError(__METHOD__ .'(), unable to parse request URI - nothing to be found.', true);
            return;
        }

        // remove empty array elements
        $this->query_parts = array_filter($this->query_parts);

        if (!empty($this->query_parts)) {
            $last_element = array_slice($this->query_parts, -1, 1, true) ;

            if (!isset($last_element) ||
                empty($last_element) ||
                !is_array($last_element)
            ) {
                static::raiseError(__METHOD__ .'(), array_slice() failed!', true);
                return;
            }

            $last_element_key = key($last_element);

            if ($last_element_key >= 0 && strpos($this->query_parts[$last_element_key], '?') !== false) {
                if (($query_parts_params = explode('?', $this->query_parts[$last_element_key], 2)) === false) {
                    static::raiseError(__METHOD__ .'(), explode() returned false!', true);
                    return;
                }
                $this->query_parts[$last_element_key] = $query_parts_params[0];
                unset($query_parts_params[0]);
            }
        }

        /* for requests to the root page (config item base_web_path), select MainView */
        if (!isset($this->query_parts[0]) &&
            empty($uri) && (
                $filtered_server['REQUEST_URI'] == "/" ||
                rtrim($filtered_server['REQUEST_URI'], '/') === $webpath
            )
        ) {
            $view = "main";
        /* select View according parsed request URI */
        } elseif (isset($this->query_parts[0]) && !empty($this->query_parts[0])) {
            $view = $this->query_parts[0];
        }

        if (!isset($view) ||
            empty($view) ||
            !is_string($view)
        ) {
            static::raiseError(__METHOD__ .'(), check if base_web_path is correctly defined!', true);
            return;
        }

        if (!$this->setQueryView($view)) {
            static::raiseError(__CLASS__ .'::setQueryView() returned false!', true);
            return;
        }

        foreach (array_reverse($this->query_parts) as $part) {
            if (!isset($part) || empty($part) || !is_string($part)) {
                continue;
            }

            if (!static::isValidAction($part)) {
                continue;
            }

            if (!$this->setQueryMode($part)) {
                static::raiseError(__CLASS__ .'::setQueryMode() returned false!');
                return false;
            }

            break;
        }

        $this->query->params = array();

        $filtered_get = filter_input_array(INPUT_GET, static::$allowed_get_parameters);

        if ($filtered_get === false || (!is_null($filtered_get) && !is_array($filtered_get))) {
            static::raiseError(__METHOD__ .'(), failure on retrieving GET variables!', true);
            return false;
        }

        $filtered_post = filter_input_array(INPUT_POST, static::$allowed_post_parameters);

        if ($filtered_post === false || (!is_null($filtered_post) && !is_array($filtered_post))) {
            static::raiseError(__METHOD__ .'(), failure on retrieving POST variables!', true);
            return false;
        }

        /**
         * in test mode, fake some POST data for RPC testing.
         */
        if ($thallium->inTestMode()) {
            $filtered_post = array(
                'action' => 'get-content',
                'view' => 'InternalTest',
                'data' => array(
                    'content' => 'testcontent',
                ),
            );
        }

        if (!is_null($filtered_get)) {
            foreach ($filtered_get as $key => $value) {
                // while $filtered_get will contain also $key if that one was
                // actually not present in $_GET, it will end up with an unset
                // value. We can move on in that case.
                if (!isset($value)) {
                    continue;
                }

                if (is_array($value)) {
                    if (!array_walk($value, function (&$item_value) {
                        return htmlentities($item_value, ENT_QUOTES);
                    })) {
                        static::raiseError(__METHOD__ .'(), array_walk() returned false!');
                        return false;
                    }

                    if (!$this->addQueryParam($key, $value)) {
                        static::raiseError(__CLASS__ .'::addQueryParam() returned false!', true);
                        return false;
                    }
                    continue;
                }

                if (!$this->addQueryParam($key, htmlentities($value, ENT_QUOTES))) {
                    static::raiseError(__CLASS__ .'::addQueryParam() returned false!', true);
                    return false;
                }
            }
        }

        if (!is_null($filtered_post)) {
            foreach ($filtered_post as $key => $value) {
                // while $filtered_get will contain also $key if that one was
                // actually not present in $_POST, it will end up with an unset
                // value. We can move on in that case.
                if (!isset($value)) {
                    continue;
                }

                if (is_array($value)) {
                    if (!array_walk($value, function (&$item_value) {
                        return htmlentities($item_value, ENT_QUOTES);
                    })) {
                        static::raiseError(__METHOD__ .'(), array_walk() returned false!');
                        return false;
                    }

                    if (!$this->addQueryParam($key, $value, ENT_QUOTES)) {
                        static::raiseError(__CLASS__ .'::addQueryParam() returned false!', true);
                        return false;
                    }
                    continue;
                }

                if (!$this->addQueryParam($key, htmlentities($value, ENT_QUOTES))) {
                    static::raiseError(__CLASS__ .'::addQueryParam() returned false!', true);
                    return false;
                }
            }
        }

        foreach ($this->query_parts as $key => $value) {
            // if the query part is empty (may occur for URIs like 'xxx/?items-per-page=0', ignore.
            if (is_string($value) && empty($value)) {
                continue;
            }

            if (!$this->addQueryParam($key, $value)) {
                static::raiseError(__CLASS__ .'::addQueryParam() returned false!', true);
                return false;
            }
        }

        if (!isset($query_parts_params)) {
            return;
        }

        foreach ($query_parts_params as $param) {
            if (!isset($param) || empty($param) || !is_string($param)) {
                continue;
            }

            if (strpos($param, '=') === false) {
                continue;
            }

            if ((list($key, $value) = explode('=', $param)) === false) {
                static::raiseError(__METHOD__ .'(), explode() returned false!', true);
                return;
            }

            if (!$this->addQueryParam($key, $value)) {
                static::raiseError(__CLASS__ .'::addQueryParam() returned false!', true);
                return;
            }
        }

        return;
    }

    /**
     * select() method controlls how to react on a HTTP request
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function select()
    {
        //
        // RPC
        //
        if (/* common RPC calls */
            ($this->hasQueryMode() && ($this->getQueryMode() === 'rpc.html')) ||
            /* object update RPC calls */
            (
                $this->hasQueryMethod() && $this->getQueryMethod() === 'POST' &&
                $this->hasQueryView() && ($view = $this->getQueryView()) !== false &&
                $this->isValidUpdateObject($view)
            )
        ) {
            if (!$this->hasQueryParam('type') || !$this->hasQueryParam('action')) {
                static::raiseError(__METHOD__ .'(), incomplete RPC request!');
                return false;
            }

            if (($type = $this->getQueryParam('type')) === false) {
                static::raiseError(__CLASS__ .'::getQueryParam() returned false!');
                return false;
            }

            if (($action = $this->getQueryParam('action')) === false) {
                static::raiseError(__CLASS__ .'::getQueryParam() returned false!');
                return false;
            }

            if ($type !== "rpc" || !$this->isValidRpcAction($action)) {
                static::raiseError(__METHOD__ .'(), invalid RPC action!');
                return false;
            }

            if (!$this->setQueryCallType($type)) {
                static::raiseError(__CLASS__ .'::setQueryCallType() returned false!');
                return false;
            }

            if (!$this->setQueryAction($action)) {
                static::raiseError(__CLASS__ .'::setQueryAction() returned false!');
                return false;
            }

            return true;
        }

        // no more information in URI, then we are done
        if (count($this->query_parts) <= 1) {
            return true;
        }

        if (!$this->setQueryCallType('common')) {
            static::raiseError(__CLASS__ .'::setQueryCallType() returned false!');
            return false;
        }

        return true;
    }

    /**
     * return true if current request is a RPC call
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function isRpcCall()
    {
        if (!$this->hasQueryCallType()) {
            return false;
        }

        if (($call_type = $this->getQueryCallType()) === false) {
            static::raiseError(__CLASS__ .'::getQueryCallType() returned false!');
            return false;
        }

        if ($call_type !== 'rpc') {
            return false;
        }

        return true;
    }

    /**
     * return true if current request is a file-upload request
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function isUploadCall()
    {
        if ($this->hasQueryMethod() && $this->getQueryMethod() === 'POST' &&
            $this->hasQueryView() && ($view = $this->getQueryView()) !== 'false' &&
            $view === 'upload'
        ) {
            return true;
        }

        return false;
    }

    /**
     * return true if requested action is a supported action
     *
     * @param string $action
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    protected static function isValidAction($action)
    {
        if (!isset($action) ||
            empty($action) ||
            !is_string($action) ||
            !isset(static::$valid_request_actions) ||
            empty(static::$valid_request_actions) ||
            !is_array(static::$valid_request_actions) ||
            !in_array($action, static::$valid_request_actions)
        ) {
            return false;
        }

        return true;
    }

    /**
     * register an additional RPC action
     *
     * @param string $action
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function addValidRpcAction($action)
    {
        if (!isset($action) || empty($action) || !is_string($action)) {
            static::raiseError(__METHOD__ .'(), $action parameter is invalid!');
            return false;
        }

        if (in_array($action, $this->valid_rpc_actions)) {
            return true;
        }

        array_push($this->valid_rpc_actions, $action);
        return true;
    }

    /**
     * return true if requested RPC action is a supported action
     *
     * @param string $action
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function isValidRpcAction($action)
    {
        if (!isset($action) || empty($action) || !is_string($action)) {
            static::raiseError(__METHOD__ .'(), $action parameter is invalid!');
            return false;
        }

        if (!in_array($action, $this->valid_rpc_actions)) {
            return false;
        }

        return true;
    }

    /**
     * return a list of supported RPC actions as array.
     *
     * @param none
     * @return array|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function getValidRpcActions()
    {
        if (!isset($this->valid_rpc_actions)) {
            return false;
        }

        return $this->valid_rpc_actions;
    }

    /**
     * helper method that may be called from Views to return an
     * array containing HTTP request query details.
     *
     * @param none
     * @return array|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function parseQueryParams()
    {
        if (!$this->hasQueryParams()) {
            static::raiseError(__CLASS__ .'::hasQueryParams() returned false!');
            return false;
        }

        if (!$this->hasQueryParam(2)) {
            static::raiseError(__CLASS__ .'::hasQueryParam() returned false!');
            return false;
        }

        if (($param = $this->getQueryParam(2)) === false) {
            static::raiseError(__CLASS__ .'::getQueryParam() returned false!');
            return false;
        }

        $matches = array();

        if (!preg_match('/^([0-9]+)\-([a-z0-9]+)$/', $param, $matches) &&
            !preg_match('/^([a-zA-Z0-9_]+)_([0-9]+)_([a-z0-9]+)$/', $param, $matches)
        ) {
            return array(
                'id' => null,
                'guid' => null
            );
        }

        if (count($matches) === 3) {
            $id_pos = 1;
            $guid_pos = 2;
        } elseif (count($matches) === 4) {
            $id_pos = 2;
            $guid_pos = 3;
        }

        $id = $matches[$id_pos];
        $guid = $matches[$guid_pos];

        return array(
            'id' => $id,
            'guid' => $guid
        );
    }

    /**
     * outputs a Location: header to redirect a client to another place.
     *
     * @param string $page
     * @param string $mode
     * @param string $id
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function redirectTo($page, $mode, $id)
    {
        global $config;

        if (($url = $config->getWebPath()) === false) {
            static::raiseError(get_class($config) .'::getWebPath() returned false!');
            return false;
        }

        if (!isset($page) || empty($page) || !is_string($page)) {
            static::raiseError(__METHOD__ .'(), $page parameter is invalid!');
            return false;
        }

        if (!isset($mode) || empty($mode) || !is_string($mode)) {
            static::raiseError(__METHOD__ .'(), $mode parameter is invalid!');
            return false;
        }

        if (!isset($id) || empty($id) || !is_string($id)) {
            static::raiseError(__METHOD__ .'(), $id parameter is invalid!');
            return false;
        }

        Header("Location: ". $url);
        return true;
    }

    /**
     * return true if current request is a valid request method.
     *
     * @param string $method
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    protected static function isValidRequestMethod($method)
    {
        if (!isset($method) ||
            empty($method) ||
            !is_string($method) ||
            !isset(static::$valid_request_methods) ||
            empty(static::$valid_request_methods) ||
            !is_array(static::$valid_request_methods) ||
            !in_array($method, static::$valid_request_methods)
        ) {
            return false;
        }

        return true;
    }

    /**
     * return true if current request is a valid request method.
     *
     * @param string $update_object
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    protected function isValidUpdateObject($update_object)
    {
        global $thallium;

        if (!isset($update_object) || empty($update_object) || !is_string($update_object)) {
            static::raiseError(__METHOD__ .'(), $update_object parameter is invalid!');
            return false;
        }

        if (!$thallium->isValidModel($update_object)) {
            return false;
        }

        return true;
    }

    /**
     * return true if query params are available.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function hasQueryParams()
    {
        if (!isset($this->query->params) ||
            empty($this->query->params) ||
            !is_array($this->query->params)
        ) {
            return false;
        }

        return true;
    }

    /**
     * return all query params.
     *
     * @param none
     * @return array|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getQueryParams()
    {
        if (!$this->hasQueryParams()) {
            static::raiseError(__CLASS__ .'::hasQueryParams() returned false!');
            return false;
        }

        return $this->query->params;
    }

    /**
     * return true if the query param $name is known.
     *
     * @param sting|int $name
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function hasQueryParam($name)
    {
        if (!isset($name) || empty($name) || (!is_string($name) && !is_int($name))) {
            static::raiseError(__METHOD__ .'(), $name parameter is invalid!');
            return false;
        }

        if (!array_key_exists($name, $this->query->params)) {
            return false;
        }

        return true;
    }

    /**
     * return the query param $name.
     *
     * @param sting|int $name
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getQueryParam($name)
    {
        if (!isset($name) || empty($name) || (!is_string($name) && !is_int($name))) {
            static::raiseError(__METHOD__ .'(), $name parameter is invalid!');
            return false;
        }

        if (!$this->hasQueryParam($name)) {
            static::raiseError(__CLASS__ .'::hasQueryParam() returned false!');
            return false;
        }

        return $this->query->params[$name];
    }

    /**
     * store the provided query param $name and its value $value.
     *
     * @param sting|int $name
     * @param mixed $value
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function addQueryParam($name, $value)
    {
        if (!isset($name) ||
            (is_string($name) && empty($name)) ||
            (is_numeric($name) && (!is_int((int) $name)) || (int) $name < 0) ||
            (!is_string($name) && !is_numeric($name))
        ) {
            static::raiseError(__METHOD__ .'(), $name parameter is invalid!');
            return false;
        }

        if (!isset($value) ||
            (!is_int($value) && !is_numeric($value) && empty($value)) ||
            (!is_int($value) && !is_string($value) && !is_array($value))
        ) {
            static::raiseError(__METHOD__ .'(), $value parameter is invalid!');
            return false;
        }

        $this->query->params[$name] = $value;
        return true;
    }

    /**
     * returns true if the HTTP method used by the original HTTP
     * request is known.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function hasQueryMethod()
    {
        if (!isset($this->query->method) ||
            empty($this->query->method) ||
            !is_string($this->query->method)
        ) {
            return false;
        }

        return true;
    }

    /**
     * returns the method used by the original HTTP request.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getQueryMethod()
    {
        if (!$this->hasQueryMethod()) {
            static::raiseError(__CLASS__ .'::hasQueryMethod() returned false!');
            return false;
        }

        return $this->query->method;
    }

    /**
     * records the HTTP method used by the original HTTP request into
     * the internal $query object.
     *
     * @param string $method
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function setQueryMethod($method)
    {
        if (!isset($method) || empty($method) || !is_string($method)) {
            static::raiseError(__METHOD__ .'(), $method parameter is invalid!');
            return false;
        }

        $this->query->method = $method;
        return true;
    }

    /**
     * returns true if the URI of the original HTTP request is known.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function hasQueryUri()
    {
        if (!isset($this->query->uri) ||
            empty($this->query->uri) ||
            !is_string($this->query->uri)
        ) {
            return false;
        }

        return true;
    }

    /**
     * returns the URI of the original HTTP request.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getQueryUri()
    {
        if (!$this->hasQueryUri()) {
            static::raiseError(__CLASS__ .'::hasQueryUri() returned false!');
            return false;
        }

        return $this->query->uri;
    }

    /**
     * records the URI of the original HTTP request into
     * the internal $query object.
     *
     * @param string $uri
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function setQueryUri($uri)
    {
        if (!isset($uri) || empty($uri) || !is_string($uri)) {
            static::raiseError(__METHOD__ .'(), $uri parameter is invalid!');
            return false;
        }

        $this->query->uri = $uri;
        return true;
    }

    /**
     * returns true if the name of the view that has been requested is known.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function hasQueryView()
    {
        if (!isset($this->query->view) ||
            empty($this->query->view) ||
            !is_string($this->query->view)
        ) {
            return false;
        }

        return true;
    }

    /**
     * returns name of the view that has been requested.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getQueryView()
    {
        if (!$this->hasQueryView()) {
            static::raiseError(__CLASS__ .'::hasQueryView() returned false!');
            return false;
        }

        return $this->query->view;
    }

    /**
     * records the View name that has been requested.
     *
     * @param string $view
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function setQueryView($view)
    {
        if (!isset($view) || empty($view) || !is_string($view)) {
            static::raiseError(__METHOD__ .'(), $view parameter is invalid!');
            return false;
        }

        $this->query->view = $view;
        return true;
    }

    /**
     * returns true if the call type that has been requested is known.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function hasQueryCallType()
    {
        if (!isset($this->query->call_type) ||
            empty($this->query->call_type) ||
            !is_string($this->query->call_type)
        ) {
            return false;
        }

        return true;
    }

    /**
     * returns the call type that has been requested.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getQueryCallType()
    {
        if (!$this->hasQueryCallType()) {
            static::raiseError(__CLASS__ .'::hasQueryCallType() returned false!');
            return false;
        }

        return $this->query->call_type;
    }

    /**
     * records the call type that has been requested.
     *
     * @param string $call_type
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function setQueryCallType($call_type)
    {
        if (!isset($call_type) || empty($call_type) || !is_string($call_type)) {
            static::raiseError(__METHOD__ .'(), $call_type parameter is invalid!');
            return false;
        }

        $this->query->call_type = $call_type;
        return true;
    }

    /**
     * returns true if the query action that has been requested is known.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function hasQueryAction()
    {
        if (!isset($this->query->action) ||
            empty($this->query->action) ||
            !is_string($this->query->action)
        ) {
            return false;
        }

        return true;
    }

    /**
     * returns the query action that has been requested.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getQueryAction()
    {
        if (!$this->hasQueryAction()) {
            static::raiseError(__CLASS__ .'::hasQueryAction() returned false!');
            return false;
        }

        return $this->query->action;
    }

    /**
     * records the query action that has been requested.
     *
     * @param string $action
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function setQueryAction($action)
    {
        if (!isset($action) || empty($action) || !is_string($action)) {
            static::raiseError(__METHOD__ .'(), $action parameter is invalid!');
            return false;
        }

        $this->query->action = $action;
        return true;
    }

    /**
     * returns true if the query mode that has been requested is known.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function hasQueryMode()
    {
        if (!isset($this->query->mode) ||
            empty($this->query->mode) ||
            !is_string($this->query->mode)
        ) {
            return false;
        }

        return true;
    }

    /**
     * returns the query mode that has been requested.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getQueryMode()
    {
        if (!$this->hasQueryMode()) {
            static::raiseError(__CLASS__ .'::hasQueryMode() returned false!');
            return false;
        }

        return $this->query->mode;
    }

    /**
     * records the query mode that has been requested.
     *
     * @param string $mode
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function setQueryMode($mode)
    {
        if (!isset($mode) || empty($mode) || !is_string($mode)) {
            static::raiseError(__METHOD__ .'(), $mode parameter is invalid!');
            return false;
        }

        $this->query->mode = $mode;
        return true;
    }

    /**
     * returns true if the client is accepting JSON messages.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function isClientAcceptingJson()
    {
        if (($accept = $this->getHttpHeaders('Accept')) === false) {
            return false;
        }

        if ($accept !== strtolower('application/json')) {
            return false;
        }

        return true;
    }

    /**
     * stores all the HTTP request header information
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function getRequestHeaders()
    {
        if (!function_exists("apache_request_headers")) {
            static::raiseError(__CLASS__ .', PHP does not provide apache_request_headers() function!');
            return false;
        }

        if (($this->httpRequestHeaders = apache_request_headers()) === false) {
            static::raiseError(__CLASS__ .', apache_request_headers() returned false!');
            return false;
        }

        return true;
    }

    /**
     * returns all or only the HTTP request header specified by $header.
     *
     * @param string
     * @return string|array|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getHttpHeaders($header = null)
    {
        if (!is_null($header) && !is_string($header)) {
            static::raiseError(__METHOD__ .'(), $header parameter is invalid!');
            return false;
        }

        if (!isset($this->httpRequestHeaders) || empty($this->httpRequestHeaders)) {
            return false;
        }

        if (is_null($header)) {
            return $this->httpRequestHeaders;
        }

        if (in_array($header, array_keys($this->httpRequestHeaders))) {
            return $this->httpRequestHeaders[$header];
        }

        return false;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
