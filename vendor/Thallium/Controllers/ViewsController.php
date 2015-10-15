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
        $this->page_skeleton = new Views\SkeletonView;
    }

    public function getViewName($view)
    {
        foreach (array_keys($this->page_map) as $entry) {
            if (($result = preg_match($entry, $view)) === false) {
                print "Error - unable to match ${entry} in ${view}";
                exit(1);
            }

            if ($result == 0) {
                continue;
            }

            if (!class_exists('\\Thallium\\Views\\'.$this->page_map[$entry])) {
                print "Error - view class ". $this->page_map[$entry] ." does not exist!";
                exit(1);
            }

            return $this->page_map[$entry];

        }
    }

    public function load($view, $skeleton = true)
    {
        $view = '\\Thallium\\Views\\'.$view;

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

        $this->page_skeleton->assign('page_content', $content);

        return $this->page_skeleton->show();
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
