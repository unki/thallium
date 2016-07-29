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
 * ViewsController takes care of loading views and mapping the
 * clients page requests to a specific view.
 *
 * @package Thallium\Controllers\ViewsController
 * @subpackage Controllers
 * @license AGPL3
 * @copyright 2015-2016 Andreas Unterkircher <unki@netshadow.net>
 * @author Andreas Unterkircher <unki@netshadow.net>
 */
class ViewsController extends DefaultController
{
    /** @var array $page_map */
    protected static $page_map = array(
        '/^$/' => 'MainView',
        '/^main$/' => 'MainView',
        '/^about$/' => 'AboutView',
    );

    /** @var object $page_skeleton */
    protected $page_skeleton;

    /** @var array $loaded_views */
    protected $loaded_views = array();

    /**
     * class constructor
     *
     * @param none
     * @return void
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     *
     */
    public function __construct()
    {
        global $thallium;

        if (!$thallium->loadController('Templates', 'tmpl')) {
            static::raiseError(get_class($thallium) .'::loadController() returned false!', true);
            return;
        }

        try {
            $this->page_skeleton = new \Thallium\Views\SkeletonView;
        } catch (\Exception $e) {
            static::raiseError(__CLASS__ .', unable to load SkeletonView!', true, $e);
            return;
        }

        return;
    }

    /**
     * returns the name of a view that is responsible for handling
     * a specific page request.
     *
     * @param string $view
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    protected static function getViewName($view)
    {
        global $thallium;

        if (!isset($view) || empty($view) || !is_string($view)) {
            static::raiseError(__METHOD__ .'(), $view parameter is invalid!');
            return false;
        }

        foreach (array_keys(static::$page_map) as $entry) {
            if (($result = preg_match($entry, $view)) === false) {
                static::raiseError(__METHOD__ ."(), unable to match ${entry} in ${view}");
                return false;
            }

            if ($result == 0) {
                continue;
            }

            if (($prefix = $thallium->getNamespacePrefix()) === false) {
                static::raiseError(get_class($thallium) .'::getNamespacePrefix() returned false!');
                return false;
            }

            if (!isset($prefix) || empty($prefix) || !is_string($prefix)) {
                static::raiseError(get_class($thallium) .'::getNamespacePrefix() returned no valid data!');
                return false;
            }

            if (!class_exists('\\'. $prefix .'\\Views\\'.static::$page_map[$entry])) {
                static::raiseError(__METHOD__ ."(), view class ". static::$page_map[$entry] ." does not exist!");
                return false;
            }

            $view = '\\'. $prefix .'\\Views\\'.static::$page_map[$entry];
            return $view;
        }

        return false;
    }

    /**
     * loads the view specified as $view
     *
     * @param string $view
     * @return object|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function getView($view)
    {
        if (!isset($view) || empty($view) || !is_string($view)) {
            static::raiseError(__METHOD__ .'(), $view parameter is invalid!');
            return false;
        }

        if (($view_class = static::getViewName($view)) === false) {
            static::raiseError(__CLASS__ .'::getViewName() returned false!');
            return false;
        }

        if (!isset($view_class) || empty($view_class) || !is_string($view_class)) {
            static::raiseError(__CLASS__ .'::getViewName() returned invalid data!');
            return false;
        }

        if ($this->isLoadedView($view_class)) {
            return $this->getLoadedView($view_class);
        }

        try {
            $view_obj = new $view_class;
        } catch (\Exception $e) {
            static::raiseError(__METHOD__ ."(), failed to load '{$view}'!", true, $e);
            return false;
        }

        $this->loaded_views[$view_class] =& $view_obj;
        return $view_obj;
    }

    /**
     * returns the output of a view, either with or without the page
     * skeleton structure.
     *
     * @param string $view
     * @param bool $skeleton
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    public function load($view, $skeleton = true)
    {
        global $thallium, $tmpl;

        if (!isset($view) || empty($view) || !is_string($view)) {
            static::raiseError(__METHOD__ .'(), $view parameter is invalid!');
            return false;
        }

        if (!isset($skeleton) || !is_bool($skeleton)) {
            static::raiseError(__METHOD__ .'(), $skeleton parameter is invalid!');
            return false;
        }

        if (($page = $this->getView($view)) === false) {
            static::raiseError(__CLASS__ .'::getView() returned false!');
            return false;
        }

        if ($skeleton === false) {
            return $page->show();
        }

        if (($content = $page->show()) === false) {
            static::raiseError(get_class($page) .'::show() returned false!');
            return false;
        }

        // if $content=true, View has handled output already, we are done
        if ($content === true) {
            return true;
        }

        if (!empty($content)) {
            $tmpl->assign('page_content', $content);
        }

        return $this->page_skeleton->show();
    }

    /**
     * returns true if the specified view has already been loaded and is
     * present in the $loaded_views property.
     *
     * @param string $view
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    protected function isLoadedView($view)
    {
        if (!isset($view) || empty($view) || !is_string($view)) {
            static::raiseError(__METHOD__ .'(), $view parameter is invalid!');
            return false;
        }

        if (!array_key_exists($view, $this->loaded_views) ||
            !isset($this->loaded_views[$view]) ||
            empty($this->loaded_views[$view]) ||
            !is_object($this->loaded_views[$view])
        ) {
            return false;
        }

        return true;
    }

    /**
     * returns a view that has already been cached in property $loaded_views
     * a specific page request.
     *
     * @param string $view
     * @return object|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    protected function getLoadedView($view)
    {
        if (!isset($view) || empty($view) || !is_string($view)) {
            static::raiseError(__METHOD__ .'(), $view parameter is invalid!');
            return false;
        }

        if (!$this->isLoadedView($view)) {
            static::raiseError(__CLASS__ .'::isViewLoaded() returned false!');
            return false;
        }

        return $this->loaded_views[$view];
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
