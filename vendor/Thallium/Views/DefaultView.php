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

namespace Thallium\Views ;

abstract class DefaultView
{
    public $default_mode = "list";

    public function __construct()
    {
        global $thallium, $config;

        if (!isset($this->class_name)) {
            $thallium->raiseError("Class has not defined property 'class_name'. Something is wrong with it");
        }
    }

    public function show()
    {
        global $thallium, $query, $router, $tmpl;

        if (isset($query->params)) {
            $params = $query->params;
        }

        if ((!isset($params) || empty($params)) &&
            $this->default_mode == "list"
        ) {
            $mode = "list";
        } elseif (isset($params) && !empty($params)) {
            if (isset($params[0]) && $this->isKnownMode($params[0])) {
                $mode = $params[0];
            }
        } elseif ($this->default_mode == "show") {
            $mode = "show";
        }

        if (!isset($mode)) {
            $thallium->raiseError("\$mode not set - do not know how to proceed!");
            return false;
        }

        if ($mode == "list" && $tmpl->templateExists($this->class_name ."_list.tpl")) {
            return $this->showList();
        } elseif ($mode == "edit" && $tmpl->templateExists($this->class_name ."_edit.tpl")) {
            if (!$item = $router->parseQueryParams()) {
                $thallium->raiseError("HttpRouterController::parseQueryParams() returned false!");
                return false;
            }
            if (empty($item) ||
                !is_array($item) ||
                !isset($item['id']) ||
                empty($item['id']) ||
                !isset($item['hash']) ||
                empty($item['hash']) ||
                !$thallium->isValidId($item['id']) ||
                !$thallium->isValidGuidSyntax($item['hash'])
            ) {
                $thallium->raiseError("HttpRouterController::parseQueryParams() was unable to parse query parameters!");
                return false;
            }
            return $this->showEdit($item['id'], $item['hash']);

        } elseif ($mode == "show" && $tmpl->templateExists($this->class_name ."_show.tpl")) {
            if (!$item = $router->parseQueryParams()) {
                $thallium->raiseError("HttpRouterController::parseQueryParams() returned false!");
            }
            if (empty($item) ||
                !is_array($item) ||
                !isset($item['id']) ||
                empty($item['id']) ||
                !isset($item['hash']) ||
                empty($item['hash']) ||
                !$thallium->isValidId($item['id']) ||
                !$thallium->isValidGuidSyntax($item['hash'])
            ) {
                $thallium->raiseError("HttpRouterController::parseQueryParams() was unable to parse query parameters!");
                return false;
            }
            return $this->showItem($item['id'], $item['hash']);

        } elseif ($tmpl->templateExists($this->class_name .".tpl")) {
            return $tmpl->fetch($this->class_name .".tpl");
        }

        $thallium->raiseError("All methods utilized but still don't know what to show!");
        return false;
    }

    public function showList()
    {
        global $tmpl;
        $tmpl->registerPlugin("block", $this->class_name ."_list", array(&$this, $this->class_name ."List"));
        return $tmpl->fetch($this->class_name ."_list.tpl");
    }

    public function showEdit($id)
    {
        global $tmpl;
        $tmpl->assign('item', $id);
        return $tmpl->fetch($this->class_name ."_edit.tpl");
    }

    public function showItem($id, $hash)
    {
        global $tmpl;
        return $tmpl->fetch($this->class_name ."_show.tpl");
    }

    protected function isKnownMode($mode)
    {
        $valid_modes = array(
            'list',
            'edit',
            'show',
        );

        if (!in_array($mode, $valid_modes)) {
            return false;
        }

        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
