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

namespace Thallium\Views;

/**
 * DefaultModel is an abstract class that is used by all
 * the other Thallium Views.
 *
 * It declares some common methods, properties and constants.
 *
 * @package Thallium\Views\DefaultView
 * @subpackage Views
 * @license AGPL3
 * @copyright 2015-2016 Andreas Unterkircher <unki@netshadow.net>
 * @author Andreas Unterkircher <unki@netshadow.net>
 */
abstract class DefaultView
{
    /** @var string $view_default_mode */
    protected static $view_default_mode = "list";

    /** @var string $view_class_name */
    protected static $view_class_name;

    /** @var array $view_default_modes */
    protected static $view_default_modes = array(
        '^list$',
        '^list-([0-9]+).html$',
        '^show$',
        '^edit$',
    );

    /** @var array $view_modes */
    protected $view_modes = array();

    /** @var array $view_items */
    protected $view_items = array();

    /** @var array $view_data */
    protected $view_data = array();

    /** @var object $view_current_item */
    protected $view_current_item;

    /**
     * class constructor
     *
     * @param none
     * @return void
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function __construct()
    {
        if (!static::validateView()) {
            static::raiseError(__CLASS__ .'::validateView() returned false!', true);
            return;
        }

        return;
    }

    /**
     * this overrides PHP own __set() method that is invoked for
     * on writing into an undeclared class property.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     * @throws \Thallium\Controllers\ExceptionController
     */
    final public function __set($name, $value)
    {
        global $thallium;

        if (!isset($thallium::$permit_undeclared_class_properties)) {
            static::raiseError(__METHOD__ ."(), trying to set an undeclared property {$name}!", true);
            return;
        }

        $this->$name = $value;
        return;
    }

    /**
     * validates the Views parameters
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected static function validateView()
    {
        if (!isset(static::$view_default_mode) ||
            empty(static::$view_default_mode) ||
            !is_string(static::$view_default_mode)
        ) {
            static::raiseError(__METHOD__ .'(), $view_default_mode is invalid!');
            return false;
        }

        if (!isset(static::$view_class_name) ||
            empty(static::$view_class_name) ||
            !is_string(static::$view_class_name)
        ) {
            static::raiseError(__METHOD__ .'(), $view_class_name is invalid!');
            return false;
        }

        return true;
    }

    /**
     * this is the main entry point into the view.
     * here the view decides what to do.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function show()
    {
        global $thallium, $query, $router, $tmpl;

        $items_per_page = null;

        if (isset($query->params)) {
            $params = $query->params;
        }

        if (isset($params) && !empty($params) && is_array($params)) {
            if (isset($query->params['items-per-page'])) {
                $items_per_page = $query->params['items-per-page'];
            }
            if (isset($params[0]) && !empty($params[0]) && $this->isValidMode($params[0])) {
                if ($query->params[0] == 'list.html') {
                    $mode = 'list';
                } elseif (preg_match('/^list-([0-9]+).html$/', $query->params[0], $parts) &&
                    isset($parts) &&
                    !empty($parts) &&
                    is_array($parts) &&
                    isset($parts[1]) &&
                    is_numeric($parts[1])
                ) {
                    $mode = 'list';
                    if (!$this->setSessionVar("current_page", $parts[1])) {
                        $this->raiseError(__CLASS__ .'::setSessionVar() returned false!');
                        return false;
                    }
                } else {
                    $mode = $params[0];
                }
            }
        }

        if (!isset($mode)) {
            $mode = static::$view_default_mode;
        }

        if ($mode == "list" && $tmpl->templateExists(static::$view_class_name ."_list.tpl")) {
            return $this->showList($mode, $items_per_page);
        } elseif ($mode == "edit" && $tmpl->templateExists(static::$view_class_name ."_edit.tpl")) {
            if (($item = $router->parseQueryParams()) === false) {
                static::raiseError("HttpRouterController::parseQueryParams() returned false!");
                return false;
            }
            if (empty($item) ||
                !is_array($item) ||
                !isset($item['id']) ||
                empty($item['id']) ||
                !isset($item['guid']) ||
                empty($item['guid']) ||
                !$thallium->isValidId($item['id']) ||
                !$thallium->isValidGuidSyntax($item['guid'])
            ) {
                static::raiseError("HttpRouterController::parseQueryParams() was unable to parse query parameters!");
                return false;
            }
            return $this->showEdit($item['id'], $item['guid']);
        } elseif ($mode == "show" && $tmpl->templateExists(static::$view_class_name ."_show.tpl")) {
            if (($item = $router->parseQueryParams()) === false) {
                static::raiseError("HttpRouterController::parseQueryParams() returned false!");
            }
            if (empty($item) ||
                !is_array($item) ||
                !isset($item['id']) ||
                empty($item['id']) ||
                !isset($item['guid']) ||
                empty($item['guid']) ||
                !$thallium->isValidId($item['id']) ||
                !$thallium->isValidGuidSyntax($item['guid'])
            ) {
                static::raiseError("HttpRouterController::parseQueryParams() was unable to parse query parameters!");
                return false;
            }
            return $this->showItem($item['id'], $item['guid']);
        } elseif ($tmpl->templateExists(static::$view_class_name .".tpl")) {
            return $tmpl->fetch(static::$view_class_name .".tpl");
        }

        static::raiseError(__METHOD__ .'(), all methods utilized but still do not know what to show!');
        return false;
    }

    /**
     * a helper method to display a listing.
     *
     * @param int|null $pageno
     * @param int|null $items_limit
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function showList($pageno = null, $items_limit = null)
    {
        global $tmpl;

        if (!isset($pageno) || empty($pageno) || !is_numeric($pageno)) {
            if (!$this->hasSessionVar("current_page") ||
                ($current_page = $this->getSessionVar("current_page")) === false) {
                $current_page = 1;
            }
        } else {
            $current_page = $pageno;
        }

        if (!isset($items_limit) || is_null($items_limit) || !is_numeric($items_limit)) {
            if (!$this->hasSessionVar("current_items_limit") ||
                ($current_items_limit = $this->getSessionVar("current_items_limit")) === false) {
                $current_items_limit = -1;
            }
        } else {
            $current_items_limit = $items_limit;
        }

        if (method_exists($this, static::$view_class_name ."List") &&
            is_callable(array(&$this, static::$view_class_name ."List"))
        ) {
            $tmpl->registerPlugin(
                'block',
                static::$view_class_name ."_list",
                array(&$this, static::$view_class_name ."List")
            );
        } else {
            $tmpl->registerPlugin(
                'block',
                static::$view_class_name ."_list",
                array(&$this, 'dataList')
            );
        }

        $template_name = static::$view_class_name ."_list.tpl";

        if (!$tmpl->templateExists($template_name)) {
            static::raiseError(__METHOD__ ."(), template '{$template_name}' does not exist!");
            return false;
        }

        if (!$this->hasViewData()) {
            return $tmpl->fetch($template_name);
        }

        try {
            $pager = new \Thallium\Controllers\PagingController(array(
                'delta' => 2,
            ));
        } catch (\Exception $e) {
            $this->raiseError(__METHOD__ .'(), failed to load PagingController!', false, $e);
            return false;
        }

        if (($view_data = $this->getViewData()) === false) {
            static::raiseError(__CLASS__ .'::getViewData() returned false!');
            return false;
        }

        if (!$view_data->hasItems()) {
            return $tmpl->fetch($template_name);
        }

        if (!$pager->setPagingData($view_data)) {
            $this->raiseError(get_class($pager) .'::setPagingData() returned false!');
            return false;
        }

        if (!$pager->setCurrentPage($current_page)) {
            $this->raiseError(get_class($pager) .'::setCurrentPage() returned false!');
            return false;
        }

        if (!$pager->setItemsLimit($current_items_limit)) {
            $this->raiseError(get_class($pager) .'::setItemsLimit() returned false!');
            return false;
        }

        if (($items = $pager->getPageData()) === false) {
            $this->raiseError(get_class($pager) .'::getPageData() returned false!');
            return false;
        }

        if (!isset($items) || !is_array($items)) {
            $this->raiseError(get_class($pager) .'::getPageData() returned invalid data!');
            return false;
        }

        $this->view_items = $items;

        if (!$this->setSessionVar("current_page", $current_page)) {
            $this->raiseError(__CLASS__ .'::setSessionVar() returned false!');
            return false;
        }

        if (!$this->setSessionVar("current_items_limit", $current_items_limit)) {
            $this->raiseError(__CLASS__ .'::setSessionVar() returned false!');
            return false;
        }

        $tmpl->assign('pager', $pager);

        return $tmpl->fetch($template_name);
    }

    /**
     * a helper method to display a editable view for a model.
     *
     * @param int $id
     * @param string $guid
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function showEdit($id, $guid)
    {
        global $thallium, $tmpl;

        if (!isset($id) ||
            empty($id) ||
            !is_numeric($id) ||
            !$thallium->isValidId($id)
        ) {
            static::raiseError(__METHOD__ .'(), $id parameter is invalid!');
            return false;
        }

        if (!isset($guid) ||
            empty($guid) ||
            !is_string($guid) ||
            !$thallium->isValidGuidSyntax($guid)
        ) {
            static::raiseError(__METHOD__ .'(), $guid parameter is invalid!');
            return false;
        }

        $tmpl->assign('item', $id);

        $template_name = static::$view_class_name ."_edit.tpl";

        if (!$tmpl->templateExists($template_name)) {
            static::raiseError(sprintf(
                '%s(), template "%s" does not exist!',
                __METHOD__,
                $template_name
            ));
            return false;
        }

        return $tmpl->fetch($template_name);
    }

    /**
     * a helper method to display a model.
     *
     * @param int $id
     * @param string $guid
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function showItem($id, $guid)
    {
        global $thallium, $tmpl;

        if (!isset($id) ||
            empty($id) ||
            !is_numeric($id) ||
            $thallium->isValidId($id)
        ) {
            static::raiseError(__METHOD__ .'(), $id parameter is invalid!');
            return false;
        }

        if (!isset($guid) ||
            empty($guid) ||
            !is_string($guid) ||
            $thallium->isValidGuidSyntax($guid)
        ) {
            static::raiseError(__METHOD__ .'(), $guid parameter is invalid!');
            return false;
        }

        $template_name = static::$view_class_name ."_show.tpl";

        if (!$tmpl->templateExists($template_name)) {
            static::raiseError(sprintf(
                '%s(), template "%s" does not exist!',
                __METHOD__,
                $template_name
            ));
            return false;
        }

        return $tmpl->fetch($template_name);
    }

    /**
     * triggers an exception.
     *
     * @param string $string
     * @param bool $stop_execution
     * @param callable $execption
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
     * adds a mode that this view is going to support.
     *
     * @param string $mode
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function addMode($mode)
    {
        if (!isset($mode) || empty($mode) || !is_string($mode)) {
            static::raiseError(__METHOD__ .'(), $mode parameter is invalid!');
            return false;
        }

        if (in_array($mode, static::$view_default_modes)) {
            return true;
        }

        if (isset($this->view_modes) &&
            !empty($this->view_modes) &&
            is_array($this->view_modes) &&
            in_array($mode, $this->view_modes)
        ) {
            return true;
        }

        array_push($this->view_modes, $mode);
        return true;
    }

    /**
     * returns true if $mode is a valid mode for the current View.
     *
     * @param string $mode
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function isValidMode($mode)
    {
        if (!isset($mode) || empty($mode) || !is_string($mode)) {
            static::raiseError(__METHOD__ .'(), $mode parameter is invalid!');
            return false;
        }

        if (($modes = $this->getModes()) === false) {
            static::raiseError(__CLASS__ .'::getModes() returned false!');
            return false;
        }

        foreach ($modes as $pattern) {
            if (preg_match("/{$pattern}/", $mode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * returns all the available modes for the current View.
     *
     * @param none
     * @return array
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function getModes()
    {
        if (!isset($this->view_modes) || empty($this->view_modes) || !is_array($this->view_modes)) {
            return static::$view_default_modes;
        }

        return array_merge(static::$view_default_modes, $this->view_modes);
    }

    /**
     * returns true if the session-variable $name is set.
     *
     * @param string $name
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function hasSessionVar($name)
    {
        global $session;

        if (!isset($name) || empty($name) || !is_string($name)) {
            static::raiseError(__METHOD__ .'(), $name parameter is invalid!');
            return false;
        }

        if (!$session->hasVariable($name, static::$view_class_name)) {
            return false;
        }

        return true;
    }

    /**
     * returns the value of the session-variable $name.
     *
     * @param string $name
     * @return mixed|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function getSessionVar($name)
    {
        global $session;

        if (!isset($name) || empty($name) || !is_string($name)) {
            static::raiseError(__METHOD__ .'(), $name parameter is invalid!');
            return false;
        }

        if (!$this->hasSessionVar($name)) {
            static::raiseError(__CLASS__ .'::hasSessionVar() returned false!');
            return false;
        }

        if (($value = $session->getVariable($name, static::$view_class_name)) === false) {
            static::raiseError(get_class($session) .'::getVariable() returned false!');
            return false;
        }

        return $value;
    }

    /**
     * sets the value $value on the session-variable $name.
     *
     * @param string $name
     * @param mixed $value
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function setSessionVar($name, $value)
    {
        global $session;

        if (!isset($name) || empty($name) || !is_string($name)) {
            static::raiseError(__METHOD__ .'(), $name parameter is invalid!');
            return false;
        }

        if (!isset($value) ||
            (!is_string($value) && !is_numeric($value) && !is_array($value) && !is_object($value))
        ) {
            static::raiseError(__METHOD__ .'(), $value parameter is invalid!');
            return false;
        }

        if (!$session->setVariable(
            $name,
            $value,
            static::$view_class_name
        )) {
            static::raiseError(get_class($session) .'::setVariable() returned false!');
            return false;
        }

        return true;
    }

    /**
     * sets the view data this View operates on.
     *
     * @param object $data
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function setViewData(&$data)
    {
        if (!isset($data) || empty($data) || !is_object($data)) {
            static::raiseError(__METHOD__ .'(), $data parameter is invalid!');
            return false;
        }

        if (!method_exists($data, 'hasModelItems') ||
            !is_callable(array(&$data, 'hasModelItems')) ||
            !$data->hasModelItems() ||
            !method_exists($data, 'hasItems') ||
            !is_callable(array(&$data, 'hasItems'))
        ) {
            static::raiseError(__METHOD__ .'(), $data parameter is not a valid data model!');
            return false;
        }

        $this->view_data = $data;
        return true;
    }

    /**
     * returns true if the View has data set.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function hasViewData()
    {
        if (!isset($this->view_data) || empty($this->view_data) || !is_object($this->view_data)) {
            return false;
        }

        return true;
    }

    /**
     * returns the View data.
     *
     * @param none
     * @return array|object
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function getViewData()
    {
        if (!$this->hasViewData()) {
            static::raiseError(__CLASS__ .'::getViewData() returned false!');
            return false;
        }

        return $this->view_data;
    }

    /**
     * a smarty block plugin that floods a list of items.
     *
     * @param array $params
     * @param string $content
     * @param object $smarty
     * @param bool $repeat
     * @return string
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function dataList($params, $content, &$smarty, &$repeat)
    {
        if (array_key_exists('name', $params) &&
            isset($params['name']) &&
            !empty($params['name']) &&
            is_string($params['name'])
        ) {
            $list_name = $params['name'];
        } else {
            $list_name = 'item_list';
        }

        if (array_key_exists('assign', $params) &&
            isset($params['assign']) &&
            !empty($params['assign']) &&
            is_string($params['assign'])
        ) {
            $assign_to = $params['assign'];
        } else {
            $assign_to = 'item';
        }

        if (($index = $this->getListIndex($list_name, $smarty)) === false) {
            static::raiseError(__CLASS__ .'::getListIndex() returned false!');
            $repeat = false;
            return false;
        }

        if (!$this->hasViewData()) {
            $repeat = false;
            return $content;
        }

        if ($index >= count($this->view_items)) {
            $repeat = false;
            return $content;
        }

        if (($items_keys = array_keys($this->view_items)) === false) {
            static::raiseError(__METHOD__ .'(), internal function went wrong!');
            return false;
        }

        if (!array_key_exists($index, $items_keys) ||
            !isset($items_keys[$index]) ||
            !is_numeric($items_keys[$index])
        ) {
            static::raiseError(__METHOD__ .'(), internal function went wrong!');
            $repeat = false;
            return false;
        }

        $item_idx = $items_keys[$index];

        if (!isset($item_idx) || !is_numeric($item_idx)) {
            $repeat = false;
            return $content;
        }

        if (!array_key_exists($item_idx, $this->view_items)) {
            static::raiseError(__METHOD__ .'(), internal fucntion went wrong!');
            $repeat = false;
            return false;
        }

        $item = $this->view_items[$item_idx];

        if (!isset($item) || empty($item) || !is_object($item)) {
            $repeat = false;
            return $content;
        }

        if (!$this->setCurrentItem($item)) {
            static::raiseError(__CLASS__ .'::setCurrentItem() returned false!');
            $repeat = false;
            return false;
        }

        $smarty->assign($assign_to, $item);

        if ($item->hasIdx() && $item->hasGuid()) {
            $smarty->assign("item_safe_link", "{$item->getIdx()}-{$item->getGuid()}");
        }

        if (!$this->setListIndex($list_name, $index+=1, $smarty)) {
            static::raiseError(__CLASS__ .'::setListIndex() returned false!');
            $repeat = false;
            return false;
        }

        $repeat = true;
        return $content;
    }

    /**
     * returns the current index pointer of a list identified by $list_name.
     *
     * @param string $list_name
     * @param object $smarty
     * @return int|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function getListIndex($list_name, &$smarty)
    {
        if (!isset($list_name) || empty($list_name) || !is_string($list_name)) {
            static::raiseError(__METHOD__ .'(), $list_name parameter is invalid!');
            return false;
        }

        if (!isset($smarty) || empty($smarty) || !is_object($smarty)) {
            static::raiseError(__METHOD__ .'(), $smarty parameter is invalid!');
            return false;
        }

        $smarty_tmpl_var = sprintf('smarty.IB.%s.index', $list_name);

        try {
            $index = $smarty->getTemplateVars($smarty_tmpl_var);
        } catch (\Exception $e) {
            $index = false;
        }

        if (!isset($index) || empty($index)) {
            $index = 0;
        }

        return $index;
    }

    /**
     * sets the current index pointer of a list identified by $list_name.
     *
     * @param string $list_name
     * @param int $index
     * @param object $smarty
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function setListIndex($list_name, $index, &$smarty)
    {
        if (!isset($list_name) || empty($list_name) || !is_string($list_name)) {
            static::raiseError(__METHOD__ .'(), $list_name parameter is invalid!');
            return false;
        }

        if (!isset($index) || empty($index) || !is_numeric($index)) {
            static::raiseError(__METHOD__ .'(), $index parameter is invalid!');
            return false;
        }

        if (!isset($smarty) || empty($smarty) || !is_object($smarty)) {
            static::raiseError(__METHOD__ .'(), $smarty parameter is invalid!');
            return false;
        }

        $smarty_tmpl_var = sprintf('smarty.IB.%s.index', $list_name);

        try {
            $smarty->assign($smarty_tmpl_var, $index);
        } catch (\Exception $e) {
            static::raiseError(get_class($smarty) .'::assign() failed!', false, $e);
            return false;
        }

        return true;
    }

    /**
     * returns true if the View knows about the current item that is
     * currently handled.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function hasCurrentItem()
    {
        if (!isset($this->view_current_item) ||
            empty($this->view_current_item)
        ) {
            return false;
        }

        return true;
    }

    /**
     * returns the current item if the View knows about.
     *
     * @param none
     * @return object|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function getCurrentItem()
    {
        if (!$this->hasCurrentItem()) {
            static::raiseError(__CLASS__ .'::hasCurrentItem() returned false!');
            return false;
        }

        return $this->view_current_item;
    }

    /**
     * sets the current item.
     *
     * @param object $item
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    protected function setCurrentItem(&$item)
    {
        if (!isset($item) || empty($item) || !is_object($item)) {
            static::raiseError(__METHOD__ .'(), $item parameter is invalid!');
            return false;
        }

        $this->view_current_item =& $item;
        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
