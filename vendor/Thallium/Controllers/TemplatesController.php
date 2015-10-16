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

use \Smarty;

class TemplatesController extends DefaultController
{
    private $smarty;

    public $config_template_dir;
    public $config_compile_dir;
    public $config_config_dir;
    public $config_cache_dir;
    public $supported_modes = array (
            'list',
            'show',
            'edit',
            'delete',
            'add',
            'upload',
            'truncate',
            );
    public $default_mode = "list";

    public function __construct()
    {
        global $config, $views;

        try {
            $this->smarty = new Smarty;
        } catch (\Exception $e) {
            $this->raiseError('Failed to load Smarty!', true);
            return false;
        }

        // disable template caching during development
        $this->smarty->setCaching(Smarty::CACHING_OFF);
        $this->smarty->force_compile = true;
        $this->smarty->caching = false;

        $this->config_template_dir = APP_BASE .'/views/templates';
        $this->config_compile_dir  = self::CACHE_DIRECTORY .'/templates_c';
        $this->config_config_dir   = self::CACHE_DIRECTORY .'/smarty_config';
        $this->config_cache_dir    = self::CACHE_DIRECTORY .'/smarty_cache';

        if (!file_exists($this->config_compile_dir) && !is_writeable(self::CACHE_DIRECTORY)) {
            $this->raiseError(
                "Cache directory ". CACHE_DIRECTORY ." is not writeable"
                ."for user (". $this->getuid() .").<br />\n"
                ."Please check that permissions are set correctly to this directory.<br />\n"
            );
        }

        if (!file_exists($this->config_compile_dir) && !mkdir($this->config_compile_dir, 0700)) {
            $this->raiseError("Failed to create directory ". $this->config_compile_dir);
            return false;
        }

        if (!is_writeable($this->config_compile_dir)) {
            $this->raiseError(
                "Error - Smarty compile directory ". $this->config_compile_dir ." is not writeable
                for the current user (". $this->getuid() .").<br />\n
                Please check that permissions are set correctly to this directory.<br />\n"
            );
            return false;
        }

        $this->smarty->setTemplateDir($this->config_template_dir);
        $this->smarty->setCompileDir($this->config_compile_dir);
        $this->smarty->setConfigDir($this->config_config_dir);
        $this->smarty->setCacheDir($this->config_cache_dir);

        if (!($base_web_path = $config->getWebPath())) {
            $this->raiseError("Web path is missing!");
            return false;
        }

        if ($base_web_path == '/') {
            $base_web_path = '';
        }

        $this->assign('icon_chains', $base_web_path .'/resources/icons/flag_blue.gif');
        $this->assign('icon_chains_assign_pipe', $base_web_path .'/resources/icons/flag_blue_with_purple_arrow.gif');
        $this->assign('icon_options', $base_web_path .'/resources/icons/options.gif');
        $this->assign('icon_pipes', $base_web_path .'/resources/icons/flag_pink.gif');
        $this->assign('icon_ports', $base_web_path .'/resources/icons/flag_orange.gif');
        $this->assign('icon_protocols', $base_web_path .'/resources/icons/flag_red.gif');
        $this->assign('icon_servicelevels', $base_web_path .'/resources/icons/flag_yellow.gif');
        $this->assign('icon_filters', $base_web_path .'/resources/icons/flag_green.gif');
        $this->assign('icon_targets', $base_web_path .'/resources/icons/flag_purple.gif');
        $this->assign('icon_clone', $base_web_path .'/resources/icons/clone.png');
        $this->assign('icon_delete', $base_web_path .'/resources/icons/delete.png');
        $this->assign('icon_active', $base_web_path .'/resources/icons/active.gif');
        $this->assign('icon_inactive', $base_web_path .'/resources/icons/inactive.gif');
        $this->assign('icon_arrow_left', $base_web_path .'/resources/icons/arrow_left.gif');
        $this->assign('icon_arrow_right', $base_web_path .'/resources/icons/arrow_right.gif');
        $this->assign('icon_chains_arrow_up', $base_web_path .'/resources/icons/ms_chains_arrow_up_14.gif');
        $this->assign('icon_chains_arrow_down', $base_web_path .'/resources/icons/ms_chains_arrow_down_14.gif');
        $this->assign('icon_pipes_arrow_up', $base_web_path .'/resources/icons/ms_pipes_arrow_up_14.gif');
        $this->assign('icon_pipes_arrow_down', $base_web_path .'/resources/icons/ms_pipes_arrow_down_14.gif');
        $this->assign('icon_users', $base_web_path .'/resources/icons/ms_users_14.gif');
        $this->assign('icon_about', $base_web_path .'/resources/icons/home.gif');
        $this->assign('icon_home', $base_web_path .'/resources/icons/home.gif');
        $this->assign('icon_new', $base_web_path .'/resources/icons/page_white.gif');
        $this->assign('icon_monitor', $base_web_path .'/resources/icons/chart_pie.gif');
        $this->assign('icon_shaper_start', $base_web_path .'/resources/icons/enable.gif');
        $this->assign('icon_shaper_stop', $base_web_path .'/resources/icons/disable.gif');
        $this->assign('icon_bandwidth', $base_web_path .'/resources/icons/bandwidth.gif');
        $this->assign('icon_update', $base_web_path .'/resources/icons/update.gif');
        $this->assign('icon_interfaces', $base_web_path .'/resources/icons/network_card.gif');
        $this->assign('icon_hosts', $base_web_path .'/resources/icons/host.png');
        $this->assign('icon_treeend', $base_web_path .'/resources/icons/tree_end.gif');
        $this->assign('icon_rules_show', $base_web_path .'/resources/icons/show.gif');
        $this->assign('icon_rules_load', $base_web_path .'/resources/icons/enable.gif');
        $this->assign('icon_rules_unload', $base_web_path .'/resources/icons/disable.gif');
        $this->assign('icon_rules_export', $base_web_path .'/resources/icons/disk.gif');
        $this->assign('icon_rules_restore', $base_web_path .'/resources/icons/restore.gif');
        $this->assign('icon_rules_reset', $base_web_path .'/resources/icons/reset.gif');
        $this->assign('icon_rules_update', $base_web_path .'/resources/icons/update.gif');
        $this->assign('icon_pdf', $base_web_path .'/resources/icons/page_white_acrobat.gif');
        $this->assign('icon_menu_down', $base_web_path .'/resources/icons/bullet_arrow_down.png');
        $this->assign('icon_menu_right', $base_web_path .'/resources/icons/bullet_arrow_right.png');
        $this->assign('icon_busy', $base_web_path .'/resources/icons/busy.png');
        $this->assign('icon_ready', $base_web_path .'/resources/icons/ready.png');
        $this->assign('icon_process', $base_web_path .'/resources/icons/task.png');
        $this->assign('web_path', $base_web_path);

        $this->registerPlugin('function', 'get_page_url', array(&$this, 'getPageUrl'), false);
        return true;
    }

    public function getuid()
    {
        if ($uid = posix_getuid()) {
            if ($user = posix_getpwuid($uid)) {
                return $user['name'];
            }
        }

        return 'n/a';

    }

    public function fetch(
        $template = null,
        $cache_id = null,
        $compile_id = null,
        $parent = null,
        $display = false,
        $merge_tpl_vars = true,
        $no_output_filter = false
    ) {
        if (!file_exists($this->config_template_dir ."/". $template)) {
            $this->raiseError("Unable to locate ". $template ." in directory ". $this->config_template_dir);
            return false;
        }

        // Now call parent method
        try {
            $result =  $this->smarty->fetch(
                $template,
                $cache_id,
                $compile_id,
                $parent,
                $display,
                $merge_tpl_vars,
                $no_output_filter
            );
        } catch (\SmartyException $e) {
            $this->raiseError("Smarty throwed an exception! ". $e->getMessage());
            return false;
        } catch (\Exception $e) {
            $this->raiseError('An exception occured: '. $e->getMessage());
            return false;
        }

        return $result;
    }

    public function getMenuState($params, &$smarty)
    {
        global $query;

        if (!array_key_exists('page', $params)) {
            $this->raiseError("getMenuState: missing 'page' parameter", E_USER_WARNING);
            $repeat = false;
            return false;
        }

        if ($params['page'] == $query->view) {
            return "active";
        }

        return null;
    }

    public function getHumanReadableFilesize($params, &$smarty)
    {
        global $query;

        if (!array_key_exists('size', $params)) {
            $this->raiseError("getMenuState: missing 'size' parameter", E_USER_WARNING);
            $repeat = false;
            return false;
        }

        if ($params['size'] < 1048576) {
            return round($params['size']/1024, 2) ."KB";
        }

        return round($params['size']/1048576, 2) ."MB";
    }

    public function assign($key, $value)
    {
        if (!$this->smarty->assign($key, $value)) {
            $this->raiseError(get_class($this->smarty) .'::assign() returned false!');
            return false;
        }

        return true;
    }

    public function registerPlugin($type, $name, $callback, $cacheable = true)
    {
        if (!$this->smarty->registerPlugin($type, $name, $callback, $cacheable)) {
            $this->raiseError(get_class($this->smarty) .'::registerPlugin() returned false!');
            return false;
        }

        return true;
    }

    /**
     * return requested page
     *
     * @param string
     */
    public function getPageUrl($params, &$smarty)
    {
        global $db, $config;

        if (!array_key_exists('page', $params)) {
            $this->raiseError("getUrl: missing 'page' parameter", E_USER_WARNING);
            $repeat = false;
            return false;
        }

        $sth = $db->prepare(
            "SELECT
                page_uri
            FROM
                TABLEPREFIXpages
            WHERE
                page_name LIKE ?"
        );

        $db->execute($sth, array(
            $params['page']
        ));

        if ($sth->rowCount() <= 0) {
            $db->freeStatement($sth);
            return false;
        }

        if (($row = $sth->fetch()) === false) {
            $db->freeStatement($sth);
            return false;
        }

        if (!isset($row->page_uri)) {
            $db->freeStatement($sth);
            return false;
        }

        if (isset($params['id']) && !empty($params['id'])) {
            $row->page_uri = str_replace("[id]", (int) $params['id'], $row->page_uri);
        }

        $db->freeStatement($sth);
        $url = $config->getWebPath() .'/'. $row->page_uri;
        return $url;
    }

    public function templateExists($tmpl)
    {
        if (!$this->smarty->templateExists($tmpl)) {
            return false;
        }

        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
