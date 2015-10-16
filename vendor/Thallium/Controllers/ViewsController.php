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

use \Thallium\Views;

class ViewsController extends DefaultController
{
    private $page_map = array(
        '/^$/' => 'MainView',
        '/^main$/' => 'MainView',
        '/^about$/' => 'AboutView',
    );
    private $page_skeleton;

    public function __construct()
    {
        try {
            $tmpl = new TemplatesController;
        } catch (\Exception $e) {
            $this->raiseError(__CLASS__ .', unable to load TemplatesController!', true, $e);
            return false;
        }
        $GLOBALS['tmpl'] =& $tmpl;

        try {
            $this->page_skeleton = new Views\SkeletonView;
        } catch (\Exception $e) {
            $this->raiseError(__CLASS__ .', unable to load SkeletonView!', true, $e);
            return false;
        }

        return true;
    }

    public function getViewName($view)
    {
        global $thallium;

        foreach (array_keys($this->page_map) as $entry) {
            if (($result = preg_match($entry, $view)) === false) {
                $this->raiseError(__METHOD__ ."(), unable to match ${entry} in ${view}");
                return false;
            }

            if ($result == 0) {
                continue;
            }

            if (!($prefix = $thallium->getNamespacePrefix())) {
                $this->raiseError(get_class($thallium) .'::getNamespacePrefix() returned false!');
                return false;
            }

            if (!class_exists('\\'. $prefix .'\\Views\\'.$this->page_map[$entry])) {
                $this->raiseError(__METHOD__ ."(), view class ". $this->page_map[$entry] ." does not exist!");
                return false;
            }

            return $this->page_map[$entry];
        }
    }

    public function load($view, $skeleton = true)
    {
        global $thallium, $tmpl;

        if (!($prefix = $thallium->getNamespacePrefix())) {
            $this->raiseError(get_class($thallium) .'::getNamespacePrefix() returned false!');
            return false;
        }

        $view = '\\'. $prefix .'\\Views\\'.$view;

        try {
            $page = new $view;
        } catch (Exception $e) {
            $this->raiseError("Failed to load view {$view}!");
            return false;
        }

        if (!$skeleton) {
            return $page->show();
        }

        if (!($content = $page->show())) {
            return false;
        }

        // if $content=true, View has handled output already, we are done
        if ($content === true) {
            return true;
        }

        $tmpl->assign('page_content', $content);
        return $this->page_skeleton->show();
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
