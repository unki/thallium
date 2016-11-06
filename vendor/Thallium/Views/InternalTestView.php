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
 * This View represents the page skeleton - the skeletal structure
 * of the who web application.
 *
 * @package Thallium\Views\InternalTestView
 * @subpackage Views
 * @license AGPL3
 * @copyright 2015-2016 Andreas Unterkircher <unki@netshadow.net>
 * @author Andreas Unterkircher <unki@netshadow.net>
 */
class InternalTestView extends DefaultView
{
    /** @var string $view_class_name */
    protected static $view_class_name = 'internaltestview';

    /** @var string $bar */
    protected $bar;

    /**
     * overwrite parents __construct() method as we do not have a lot to do here.
     *
     * @param none
     * @return void
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function __construct()
    {
        if (!\Thallium\Controllers\MainController::inTestMode()) {
            static::raiseError(__METHOD__ .'(), this view is only valid in test mode!', true);
            return;
        }

        if (!$this->addMode('show')) {
            static::raiseError(__CLASS__ .'::addMode() returned false!', true);
            return;
        }

        if (!$this->addContent('testcontent')) {
            static::raiseError(__CLASS__ .'::addContent() returned false!', true);
            return;
        }

        parent::__construct();
        return;
    }

    public function getTestContent()
    {
        return __CLASS__;
    }

    /**
     * overwrite parents show() method as we do not have a lot to do here.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function show()
    {
        global $tmpl;

        if (!$tmpl->templateExists('skeleton.tpl')) {
            static::raiseError(__METHOD__ .'(), skeleton.tpl does not exist!');
            return false;
        }

        $tmpl->assign('page_content', 'foobar');
        return $tmpl->fetch('skeleton.tpl');
    }

    /**
     * overwrite parents showList() method as we do not have a lot to do here.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function showList($pageno = null, $items_limit = null)
    {
        global $tmpl;

        if (!$tmpl->templateExists('skeleton.tpl')) {
            static::raiseError(__METHOD__ .'(), skeleton.tpl does not exist!');
            return false;
        }

        $tmpl->assign('page_content', 'foobar');
        return $tmpl->fetch('skeleton.tpl');
    }

    /**
     * overwrite parents showEdit() method as we do not have a lot to do here.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController
     */
    public function showEdit($id, $guid)
    {
        global $tmpl;

        if (!$tmpl->templateExists('skeleton.tpl')) {
            static::raiseError(__METHOD__ .'(), skeleton.tpl does not exist!');
            return false;
        }

        $tmpl->assign('page_content', 'foobar');
        return $tmpl->fetch('skeleton.tpl');
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
