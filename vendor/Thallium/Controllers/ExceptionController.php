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
 * Despite its name, ExceptionController class is not based on
 * Thalliums DefaultController but rather extends PHPs own Exception class.
 *
 * @package Thallium\Controllers\ExceptionController
 * @subpackage Controllers
 * @license AGPL3
 * @copyright 2015-2016 Andreas Unterkircher <unki@netshadow.net>
 * @author Andreas Unterkircher <unki@netshadow.net>
 */
class ExceptionController extends \Exception
{
    /** @var bool $prints_json */
    protected $prints_json = false;

    /**
     * class constructor
     *
     * @param string $message
     * @param \Exception $captured_exception
     * @return void
     * @throws \Exception
     */
    public function __construct($message, $captured_exception = null)
    {
        parent::__construct($message, null, $captured_exception);
    }

    /**
     * this method returns a formated text containing the exception details.
     *
     * @param none
     * @return string
     * @throws none
     */
    public function getText()
    {
        $text = "";

        $text.= sprintf(
            "<br /><br />%s<br /><br />\n",
            str_replace("\n", "<br />\n", $this->getMessage())
        );
        $text.= sprintf(
            "Backtrace:<br />\n%s",
            str_replace("\n", "<br />\n", parent::getTraceAsString())
        );

        return $text;
    }

    /**
     * this method returns a JSON-formated text containing the exception details.
     *
     * @param none
     * @return string
     * @throws none
     */
    public function getJson()
    {
        $text = array();
        $trace = array();

        $text[] = $this->getMessage();
        $trace[] = parent::getTraceAsString();

        $json_data = array(
            'error' => 1,
            'text' => $text,
            'trace' => $trace,
        );

        if (($json = json_encode($json_data)) === false) {
            trigger_error("json_encode() failed!", E_USER_ERROR);
        }

        return $json;
    }

    /**
     * From php.net: The __toString() method allows a class to decide how it will
     * react when it is treated like a string. For example, what echo $obj; will
     * print. This method must return a string, as otherwise a fatal
     * E_RECOVERABLE_ERROR level error is emitted.
     *
     * @param none
     * @return string
     * @throws none
     */
    public function __toString()
    {
        global $thallium, $router;

        if (isset($router) &&
            !empty($router) &&
            is_object($router) &&
            is_a($router, 'Thallium\Controllers\HttpRouterController') &&
            isset($thallium) &&
            !empty($thallium) &&
            is_object($thallium) &&
            is_a($thallium, 'Thallium\Controllers\MainController') &&
            !$thallium->isRunningBackgroundJobs() &&
            $router->isClientAcceptingJson()
        ) {
            return $this->getJson();
        }

        return $this->getText();
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
