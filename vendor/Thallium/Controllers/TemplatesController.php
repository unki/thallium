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

use \Smarty;

/**
 * TemplatesController manage the interaction with the Smarty
 * template engine. Usually is accessed by ViewsController or
 * Views themself.
 *
 * @package Thallium\Controllers\TemplatesController
 * @subpackage Controllers
 * @license AGPL3
 * @copyright 2015-2016 Andreas Unterkircher <unki@netshadow.net>
 * @author Andreas Unterkircher <unki@netshadow.net>
 */
class TemplatesController extends DefaultController
{
    /** @var object $smarty */
    protected $smarty;

    /** @var string $config_template_dir */
    protected $config_template_dir;

    /** @var string $config_compile_dir */
    protected $config_compile_dir;

    /** @var string $config_config_dir */
    protected $config_config_dir;

    /** @var string $config_cache_dir */
    protected $config_cache_dir;

    /**
     * class constructor
     *
     * @param none
     * @return void
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function __construct()
    {
        global $config, $thallium;

        try {
            $this->smarty = new \Smarty;
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), failed to load Smarty!', true, $e);
            return;
        }

        if (($prefix = $thallium->getNamespacePrefix()) === false) {
            static::raiseError(get_class($thallium) .'::getNameSpacePrefix() returned false!', true);
            return;
        }

        // disable template caching during development
        $this->smarty->setCaching(Smarty::CACHING_OFF);
        $this->smarty->force_compile = true;
        $this->smarty->caching = false;

        $this->config_template_dir = APP_BASE .'/vendor/'. $prefix .'/Views/templates';
        $this->config_compile_dir  = static::CACHE_DIRECTORY .'/templates_c';
        $this->config_config_dir   = static::CACHE_DIRECTORY .'/smarty_config';
        $this->config_cache_dir    = static::CACHE_DIRECTORY .'/smarty_cache';

        if (($uid = $this->getuid()) === false) {
            static::raiseError(__CLASS__ .'::getUid() returned false!', true);
            return;
        }

        if (!file_exists($this->config_compile_dir) && !is_writeable(static::CACHE_DIRECTORY)) {
            static::raiseError(sprintf(
                '%s(), cache directory "%s" is not writeable'
                .'for user (%s).<br />\n'
                .'Please check that permissions are set correctly to this directory.<br />\n',
                __METHOD__,
                static::CACHE_DIRECTORY,
                $uid
            ), true);
            return;
        }

        if (!file_exists($this->config_compile_dir) && !mkdir($this->config_compile_dir, 0700)) {
            static::raiseError(__METHOD__ .'(), failed to create directory '. $this->config_compile_dir, true);
            return;
        }

        if (!is_writeable($this->config_compile_dir)) {
            static::raiseError(sprintf(
                '%s(), error - Smarty compile directory "%s" is not '
                .'writeable for the current user (%s).<br />'
                ."Please check that permissions are set correctly to this directory.<br />",
                __METHOD__,
                $this->config_compile_dir,
                $this->getuid()
            ), true);
            return;
        }

        try {
            $this->smarty->setTemplateDir($this->config_template_dir);
            $this->smarty->setCompileDir($this->config_compile_dir);
            $this->smarty->setConfigDir($this->config_config_dir);
            $this->smarty->setCacheDir($this->config_cache_dir);
        } catch (\SmartyException $e) {
            static::raiseError(__METHOD__ .'(), configuring Smarty failed!', true, $e);
            return;
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), unknown error on configuring Smarty!', true, $e);
            return;
        }

        if (($app_web_path = $config->getWebPath()) === false) {
            static::raiseError(get_class($config) .'::getWebPath() returned false!', true);
            return;
        }

        if ($app_web_path == '/') {
            $app_web_path = '';
        }

        if (($page_title = $config->getPageTitle()) === false) {
            $page_title = 'Thallium v'. MainController::FRAMEWORK_VERSION;
        }

        $this->smarty->assign('config', $config);
        $this->smarty->assign('app_web_path', $app_web_path);
        $this->smarty->assign('page_title', $page_title);

        if (!$this->registerPlugin("function", "get_url", array(&$this, "getUrl"), false)) {
            static::raiseError(__CLASS__ .'::registerPlugin() returned false!', true);
            return;
        }

        if (!$this->registerPlugin(
            "function",
            "get_humanreadable_filesize",
            array(&$this, "getHumanReadableFilesize"),
            false
        )) {
            static::raiseError(__CLASS__ .'::registerPlugin() returned false!', true);
            return;
        }

        if (!$this->registerPlugin("function", "raise_error", array(&$this, "smartyRaiseError"), false)) {
            static::raiseError(__CLASS__ .'::registerPlugin() returned false!', true);
            return;
        }

        return;
    }

    /**
     * returns the user name of the current process owner.
     *
     * @param none
     * @return string|false
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function getuid()
    {
        if (($uid = posix_getuid()) === null) {
            static::raiseError(__METHOD__ .'(), posix_getuid() returned null!');
            return false;
        }

        if (($user = posix_getpwuid($uid)) === null) {
            static::raiseError(__METHOD__ .'(), posix_getpwuid() returned null!');
            return false;
        }

        if (!array_key_exists('name', $user) ||
            !isset($user['name']) ||
            empty($user['name']) ||
            !is_string($user['name'])
        ) {
            return false;
        }

        return $user['name'];
    }

    /**
     * this method mimes fetch() method of Smarty, but tries to verify
     * that the template file actually exists before it triggers Smarty.
     *
     * @param string $template
     * @param string $cache_id
     * @param string $compile_id
     * @param string $parent
     * @param bool $display
     * @param bool $merge_tpl_vars
     * @param bool $no_output_filter
     * @return string|false
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function fetch(
        $template = null,
        $cache_id = null,
        $compile_id = null,
        $parent = null,
        $display = false,
        $merge_tpl_vars = true,
        $no_output_filter = false
    ) {

        if (!isset($template) || empty($template) || !is_string($template)) {
            static::raiseError(__METHOD__ .'(), $template parameter is invalid!');
            return false;
        }

        if (!file_exists($this->config_template_dir ."/". $template)) {
            static::raiseError(sprintf(
                '%s(), unable to locate %s in directory %s',
                __METHOD__,
                $template,
                $this->config_template_dir
            ));
            return false;
        }

        try {
            $result = $this->smarty->fetch(
                $template,
                $cache_id,
                $compile_id,
                $parent,
                $display,
                $merge_tpl_vars,
                $no_output_filter
            );
        } catch (\SmartyException $e) {
            static::raiseError(__METHOD__ .'(), Smarty has thrown an exception!', false, $e);
            return false;
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), an exception occured!', false, $e);
            return false;
        }

        return $result;
    }

    /**
     * returns the menu state
     *
     * @param array $params
     * @param object $smarty
     * @return string|null
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     * @SuppressWarnings
     */
    public function getMenuState($params, &$smarty)
    {
        global $router;

        if (!array_key_exists('page', $params)) {
            static::raiseError(__METHOD__ .'(), missing "page" parameter!');
            return false;
        }

        if (!$router->hasQueryParams() ||
            !$router->hasQueryParam('view') ||
            ($view = $router->getQueryParam('view')) === false
        ) {
            return null;
        }

        if ($params['page'] !== $view) {
            return null;
        }

        return "active";
    }

    /**
     * returns the provided byte value in human-readable notations as
     * KB, MB, GB, etc.
     *
     * @param array $params
     * @param object $smarty
     * @return string|false
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     * @SuppressWarnings
     */
    public function getHumanReadableFilesize($params, &$smarty)
    {
        if (!array_key_exists('size', $params)) {
            static::raiseError(__METHOD__ .'(), missing "size" parameter!');
            return false;
        }

        if ($params['size'] < 1048576) {
            $result = sprintf("%sKB", round($params['size']/1024, 2));
            return $result;
        }

        $result = sprintf("%sMB", round($params['size']/1048576, 2));
        return $result;
    }

    /**
     * this method mimes assign() Smarty method but verifies $key and $value parameters first.
     *
     * @param string $key
     * @param bool|int|string|array|object $value
     * @return string|false
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function assign($key, $value)
    {
        if (!isset($key) || empty($key) || !is_string($key)) {
            static::raiseError(__METHOD__ .'(), $key parameter is invalid!');
            return false;
        }

        if (!isset($value) || (
            !is_bool($value) &&
            !is_string($value) &&
            !is_numeric($value) &&
            !is_int($value) &&
            !is_array($value) &&
            !is_object($value)
        )) {
            static::raiseError(__METHOD__ .'(), $value parameter is invalid!');
            return false;
        }

        try {
            $this->smarty->assign($key, $value);
        } catch (\SmartyException $e) {
            static::raiseError(__METHOD__ .'(), Smarty has thrown an exception!', false, $e);
            return false;
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), an exception occured!', false, $e);
            return false;
        }

        return true;
    }

    /**
     * return true if the provided plugin name is registered in Smarty.
     *
     * @param string $type
     * @param string $name
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function hasPlugin($type, $name)
    {
        if (!isset($type) || empty($type) || !is_string($type)) {
            static::raiseError(__METHOD__ .'(), $type parameter is invalid!');
            return false;
        }

        if (!isset($name) || empty($name) || !is_string($name)) {
            static::raiseError(__METHOD__ .'(), $name parameter is invalid!');
            return false;
        }

        if (!array_key_exists($type, $this->smarty->smarty->registered_plugins)) {
            return false;
        }

        if (!array_key_exists($name, $this->smarty->smarty->registered_plugins[$type])) {
            return false;
        }

        return true;
    }

    /**
     * this method mimes registerPlugin() Smarty method and checks if the callback
     * function is actually callable.
     *
     * @param string $type
     * @param string $name
     * @param callable $callback
     * @param bool $cacheable
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function registerPlugin($type, $name, $callback, $cacheable = true)
    {
        if (!isset($type) || empty($type) || !is_string($type)) {
            static::raiseError(__METHOD__ .'(), $type parameter is invalid!');
            return false;
        }

        if (!isset($name) || empty($name) || !is_string($name)) {
            static::raiseError(__METHOD__ .'(), $name parameter is invalid!');
            return false;
        }

        if (!isset($callback) ||
            empty($callback) ||
            !is_callable($callback)
        ) {
            static::raiseError(__METHOD__ .'(), $callback parameter is invalid!');
            return false;
        }

        if (!isset($cacheable) || !is_bool($cacheable)) {
            static::raiseError(__METHOD__ .'(), $cacheable parameter is invalid!');
            return false;
        }

        if ($this->hasPlugin($type, $name)) {
            return true;
        }

        try {
            $this->smarty->registerPlugin($type, $name, $callback, $cacheable);
        } catch (\SmartyException $e) {
            static::raiseError(__METHOD__ .'(), Smarty has thrown an exception!', false, $e);
            return false;
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ .'(), an exception occured!', false, $e);
            return false;
        }

        return true;
    }

    /**
     * returns true if the specified template exists by usings Smartys own
     * templateExists() method.
     *
     * @param string $tmpl
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function templateExists($tmpl)
    {
        if (!isset($tmpl) || empty($tmpl) || !is_string($tmpl)) {
            static::raiseError(__METHOD__ .'(), $tmpl parameter is invalid!');
            return false;
        }

        if (!$this->smarty->templateExists($tmpl)) {
            return false;
        }

        return true;
    }

    /**
     * general getUrl() method :)
     *
     * @param array $params
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public static function getUrl($params)
    {
        global $config, $views;

        if (!isset($params) ||
            empty($params) ||
            !is_array($params)
        ) {
            static::raiseError(__METHOD__ .'(), $params parameter is invalid!');
            return false;
        }

        if (!array_key_exists('page', $params) ||
            empty($params['page']) ||
            !is_string($params['page'])
        ) {
            static::raiseError(__METHOD__ .'(), missing "page" parameter!');
            return false;
        }

        if (array_key_exists('mode', $params)) {
            if (($view = $views->getView($params['page'])) === false) {
                static::raiseError(get_class($views) .'::getView() returned false!');
                return false;
            }

            if (!isset($view) || empty($view) || !is_object($view)) {
                static::raiseError(get_class($views) .'::getView() returned invalid data!');
                return false;
            }

            if (!$view->isValidMode($params['mode'])) {
                static::raiseError(get_class($view) .'::isValidMode() returned false!');
                return false;
            }
        }

        if (($url = $config->getWebPath()) === false) {
            static::raiseError(get_class($config) .'::getWebPath() returned false!');
            return false;
        }

        if ($url == '/') {
            $url = "";
        }

        $url.= '/'. $params['page'] .'/';

        if (array_key_exists('mode', $params) && !empty($params['mode'])) {
            $url.= $params['mode'] .'/';
        }

        if (array_key_exists('id', $params) && !empty($params['id'])) {
            $url.= $params['id'] .'/';
        }

        if (array_key_exists('file', $params) && !empty($params['file'])) {
            $url.= $params['file'] .'/';
        }

        if (!array_key_exists('number', $params) &&
            !array_key_exists('items_per_page', $params)) {
            return $url;
        }

        if (array_key_exists('number', $params)) {
            $url.= "list-{$params['number']}.html";
        }

        if (array_key_exists('items_per_page', $params)) {
            $url.= "?items-per-page=". $params['items_per_page'];
        }

        return $url;
    }

    /**
     * raises an exception and can be called from within a template.
     *
     * @param array $params
     * @return void
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public static function smartyRaiseError($params)
    {
        $message = array_key_exists('message', $params) ? $params['message'] : 'unknown error';
        $stop = array_key_exists('stop', $params) ? $params['stop'] : false;

        if (!isset($message) || empty($message) || !is_string($message)) {
            static::raiseError(__METHOD__ .'(), $message parameter is invalid!');
            return false;
        }

        if (!isset($stop) || !is_bool($stop)) {
            static::raiseError(__METHOD__ .'(), $stop parameter is invalid!');
            return false;
        }

        static::raiseError($message, $stop);
        return;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
