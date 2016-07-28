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

        // check HTTP request method
        if (!isset($_SERVER['REQUEST_URI']) ||
            empty($_SERVER['REQUEST_URI']) ||
            !is_string($_SERVER['REQUEST_URI'])
        ) {
            static::raiseError(__METHOD__ .'(), $_SERVER["REQUEST_URI"] is not set!', true);
            return;
        }

        if (!isset($_SERVER['REQUEST_METHOD']) ||
            empty($_SERVER['REQUEST_METHOD']) ||
            !is_string($_SERVER['REQUEST_METHOD'])
        ) {
            static::raiseError(__METHOD__ .'(), $_SERVER["REQUEST_METHOD"] is not set!', true);
            return;
        }

        if (!static::isValidRequestMethod($_SERVER['REQUEST_METHOD'])) {
            static::raiseError(__METHOD__ .'(), unsupported request method found!', true);
            return;
        }

        $this->query->method = $_SERVER['REQUEST_METHOD'];
        $this->query->uri = $_SERVER['REQUEST_URI'];

        // check HTTP request URI
        $uri = $_SERVER['REQUEST_URI'];

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
        if ($webpath != '/') {
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
        $last_element = count($this->query_parts)-1;

        if ($last_element >= 0 && strpos($this->query_parts[$last_element], '?') !== false) {
            if (($query_parts_params = explode('?', $this->query_parts[$last_element], 2)) === false) {
                static::raiseError(__METHOD__ .'(), explode() returned false!', true);
                return;
            }
            $this->query_parts[$last_element] = $query_parts_params[0];
            unset($query_parts_params[0]);
        }

        /* for requests to the root page (config item base_web_path), select MainView */
        if (!isset($this->query_parts[0]) &&
            empty($uri) && (
                $_SERVER['REQUEST_URI'] == "/" ||
                rtrim($_SERVER['REQUEST_URI'], '/') == $config->getWebPath()
            )
        ) {
            $this->query->view = "main";
        /* select View according parsed request URI */
        } elseif (isset($this->query_parts[0]) && !empty($this->query_parts[0])) {
            $this->query->view = $this->query_parts[0];
        } else {
            static::raiseError(__METHOD__ .'(), check if base_web_path is correctly defined!', true);
            return;
        }

        foreach (array_reverse($this->query_parts) as $part) {
            if (!isset($part) || empty($part) || !is_string($part)) {
                continue;
            }
            if (!static::isValidAction($part)) {
                continue;
            }
            $this->query->mode = $part;
            break;
        }

        $this->query->params = array();

        /* register further _GET parameters */
        if (isset($_GET) && is_array($_GET) && !empty($_GET)) {
            foreach ($_GET as $key => $value) {
                if (is_array($value)) {
                    array_walk($value, function (&$item_value) {
                        return htmlentities($item_value, ENT_QUOTES);
                    });
                    continue;
                }
                $this->query->params[$key] = htmlentities($value, ENT_QUOTES);
            }
        }

        /* register further _POST parameters */
        if (isset($_POST) && is_array($_POST) && !empty($_POST)) {
            foreach ($_POST as $key => $value) {
                if (is_array($value)) {
                    array_walk($value, function (&$item_value) {
                        return htmlentities($item_value, ENT_QUOTES);
                    });
                    continue;
                }
                $this->query->params[$key] = htmlentities($value, ENT_QUOTES);
            }
        }

        for ($i = 1; $i < count($this->query_parts); $i++) {
            array_push($this->query->params, $this->query_parts[$i]);
        }

        if (!isset($query_parts_params)) {
            return;
        }

        foreach ($query_parts_params as $param) {
            array_push($this->query->params, $param);
        }

        return;
    }

    /**
     * select() method controlls how to react on a HTTP request
     *
     * @param none
     * @return \stdClass|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function select()
    {
        //
        // RPC
        //
        if (/* common RPC calls */
            (isset($this->query->mode) && $this->query->mode == 'rpc.html') ||
            /* object update RPC calls */
            (
                isset($this->query->method) && $this->query->method == 'POST' &&
                $this->isValidUpdateObject($this->query->view)
            )
        ) {
            if (!isset($_POST['type']) || !isset($_POST['action'])) {
                static::raiseError("Incomplete RPC request!");
                return false;
            }
            if (!is_string($_POST['type']) || !is_string($_POST['action'])) {
                static::raiseError("Invalid RPC request!");
                return false;
            }
            if ($_POST['type'] != "rpc" && $this->isValidRpcAction($_POST['action'])) {
                static::raiseError("Invalid RPC action!");
                return false;
            }
            $this->query->call_type = "rpc";
            $this->query->action = $_POST['action'];
            return $this->query;
        }

        // no more information in URI, then we are done
        if (count($this->query_parts) <= 1) {
            return $this->query;
        }

        $this->query->call_type = "common";
        return $this->query;
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
        if (isset($this->query->call_type) && $this->query->call_type == "rpc") {
            return true;
        }

        return false;
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
        if (isset($this->query->method) &&
            $this->query->method == 'POST' &&
            isset($this->query->view) &&
            $this->query->view == 'upload'
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
        if (!isset($this->query->params) ||
            empty($this->query->params) ||
            !is_array($this->query->params) ||
            !isset($this->query->params[1])
        ) {
            return array(
                'id' => null,
                'guid' => null
            );
        }

        $safe_link = $this->query->params[1];
        $matches = array();

        if (!preg_match("/^([0-9]+)\-([a-z0-9]+)$/", $safe_link, $matches)) {
            return array(
                'id' => null,
                'guid' => null
            );
        }

        $id = $matches[1];
        $guid = $matches[2];

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

        if (!$thallium->isValidModel(null, $update_object)) {
            return false;
        }

        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
