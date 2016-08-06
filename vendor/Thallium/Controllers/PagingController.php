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
 * PagingController acts similar as the PEAR class Pager.
 * On providing data to the controller, it also the split
 * this data into pages so that long lists can be paged.
 *
 * @package Thallium\Controllers\PagingController
 * @subpackage Controllers
 * @license AGPL3
 * @copyright 2015-2016 Andreas Unterkircher <unki@netshadow.net>
 * @author Andreas Unterkircher <unki@netshadow.net>
 */
class PagingController extends DefaultController
{
    /** @var array $pagingData */
    protected $pagingData = array();

    /** @var array $pagingParameters */
    protected $pagingParameters = array();

    /** @var int $currentPage */
    protected $currentPage;

    /** @var int $currentItemsLimit */
    protected $currentItemsLimit;

    /** @var array $itemsPerPageLimits */
    protected static $itemsPerPageLimits = array(
        10, 25, 50, 100, 0
    );

    /**
     * class constructor
     *
     * @param array $params
     * @return void
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function __construct($params)
    {
        if (!isset($params) || empty($params) || !is_array($params)) {
            static::raiseError(__CLASS__ .'::__construct(), $params parameter is invalid!', true);
            return;
        }

        if (!$this->setPagingParameters($params)) {
            static::raiseError(__CLASS__ .'::setPagingParameters() returned false!', true);
            return;
        }

        return;
    }

    /**
     * sets data that will be paged
     *
     * @param object $data
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function setPagingData(&$data)
    {
        if (!isset($data) || empty($data) || !is_object($data)) {
            static::raiseError(__METHOD__ .'(), $data parameter is invalid!');
            return false;
        }

        if ($this->hasPagingData()) {
            static::raiseError(__METHOD__ .'(), paging data already set!');
            return false;
        }

        if (!method_exists($data, 'hasItems') ||
            !is_callable(array(&$data, 'hasItems'))
        ) {
            static::raiseError(__METHOD__ .'(), $data does not provide the required methods!');
            return false;
        }

        $this->pagingData = $data;
        return true;
    }

    /**
     * this method returns the paged data starting from $offset and will
     * contain max. $limit items.
     *
     * @param int $offset
     * @param int|null $limit
     * @return arary
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final protected function getPagingData($offset, $limit)
    {
        if (!isset($offset) || !is_numeric($offset)) {
            static::raiseError(__METHOD__ .'(), $offset parameter is invalid!');
            return false;
        }

        if (!is_null($limit) && !is_numeric($limit)) {
            static::raiseError(__METHOD__ .'(), $limit parameter is invalid!');
            return false;
        }

        if (!$this->hasPagingData()) {
            static::raiseError(__CLASS__ .'::hasPagingData() returned false!');
            return false;
        }

        if (!$this->pagingData->hasItems()) {
            return array();
        }

        if (($data = $this->pagingData->getItems($offset, $limit)) === false) {
            static::raiseError(get_class($this->pagingData) .'::getItems() returned false!');
            return false;
        }

        return $data;
    }

    /**
     * returns how many items in total are available in the paged data
     *
     * @param none
     * @return int
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final protected function getPagingDataCount()
    {
        if (!$this->hasPagingData()) {
            static::raiseError(__CLASS__ .'::hasPagingData() returned false!');
            return false;
        }

        if (!$this->pagingData->hasItems()) {
            return 0;
        }

        if (($count = $this->pagingData->getItemsCount()) === false) {
            static::raiseError(get_class($this->pagingData) .'::getItemsCount() returned false!');
            return false;
        }

        return $count;
    }

    /**
     * startup methods by which Thallium is actually starting to perform.
     *
     * @param array $params
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final protected function setPagingParameters($params)
    {
        if (!isset($params) || empty($params) || !is_array($params)) {
            static::raiseError(__METHOD__ .'(), $params is invalid!');
            return false;
        }

        if (isset($this->pagingParameters) && !empty($this->pagingParameters)) {
            static::raiseError(__METHOD__ .'(), paging parameters already set!');
            return false;
        }

        foreach ($params as $key => $value) {
            if (!$this->setParameter($key, $value)) {
                static::raiseError(__CLASS__ .'::setParameter() returned false!');
                return false;
            }
        }

        return true;
    }

    /**
     * internal set parameter for paging.
     *
     * @param string $key
     * @param string $value
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final protected function setParameter($key, $value)
    {
        if (!isset($key) || empty($key) || !is_string($key)) {
            static::raiseError(__METHOD__ .'(), $key parameter is invalid!');
            return false;
        }

        if (!isset($value) || empty($value) || (!is_string($value) && !is_numeric($value))) {
            static::raiseError(__METHOD__ .'(), $value parameter is invalid!');
            return false;
        }

        $this->pagingParameters[$key] = $value;
        return true;
    }

    /**
     * returns the requested internal paging parameter.
     *
     * @param none
     * @return string|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final protected function getParameter($key)
    {
        if (!isset($key) || empty($key) || !is_string($key)) {
            static::raiseError(__METHOD__ .'(), $key parameter is invalid!');
            return false;
        }

        if (!isset($this->pagingParameters[$key])) {
            return false;
        }

        return $this->pagingParameters[$key];
    }

    /**
     * returns the number of pages based on the total number of items
     * and the items-per-page limit.
     *
     * @param none
     * @return int|false
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function getNumberOfPages()
    {
        if (!$this->hasPagingData()) {
            static::raiseError(__METHOD__ .'(), paging data has not been set yet!');
            return false;
        }

        if (($items_per_page = $this->getCurrentItemsLimit()) === false) {
            static::raiseError(__CLASS__ .'::getCurrentItemsLimit() returned false!');
            return false;
        }

        if (!isset($items_per_page) ||
            is_null($items_per_page) ||
            !is_numeric($items_per_page) ||
            (int) $items_per_page < 0
        ) {
            static::raiseError(__METHOD__ .'(), $items_per_page not correctly defined!');
            return false;
        }

        if (($totalItems = $this->getPagingDataCount()) === false) {
            static::raiseError(__CLASS__ .'::getPagingDataCount() returned false!');
            return false;
        }

        if (!isset($totalItems) || !is_int($totalItems)) {
            static::raiseError(__CLASS__ .'::getPagingDataCount() returned invalid data!');
            return false;
        }

        if ($totalItems < 1) {
            return 1;
        }

        $totalPages = 1;

        if ($items_per_page > 0) {
            $totalPages = ceil($totalItems/$items_per_page);
        }

        if (!isset($totalPages) ||
            empty($totalPages) ||
            !is_numeric($totalPages) ||
            $totalPages < 1
        ) {
            static::raiseError(__METHOD__ .'(), failure on calculating total pages!');
            return false;
        }

        return $totalPages;
    }

    /**
     * returns the current page number.
     *
     * @param none
     * @return int|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function getCurrentPage()
    {
        if (!isset($this->currentPage) ||
            empty($this->currentPage)
        ) {
            return false;
        }

        if ($this->currentPage > $this->getNumberOfPages()) {
            $this->currentPage = 1;
        }

        return $this->currentPage;
    }

    /**
     * returns true if the provided page number matches the current page number
     *
     * @param int $pageno
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function isCurrentPage($pageno)
    {
        if (!isset($pageno) || !is_numeric($pageno)) {
            static::raiseError(__METHOD__ .'(), $pageno parameter is invalid!');
            return false;
        }

        if (($curpage = $this->getCurrentPage()) === false) {
            return false;
        }

        if ($pageno != $curpage) {
            return false;
        }

        return true;
    }

    /**
     * startup methods by which Thallium is actually starting to perform.
     *
     * @param int $pageno
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function setCurrentPage($pageno)
    {
        if (!isset($pageno) || empty($pageno) || !is_numeric($pageno) || $pageno < 1) {
            static::raiseError(__METHOD__ .'(), $pageno parameter is invalid!');
            return false;
        }

        if (!$this->hasPagingData()) {
            $this->currentPage = $pageno;
            return true;
        }

        if (($total = $this->getNumberOfPages()) === false) {
            static::raiseError(__CLASS__ .'::getNumberOfPages() returned false!');
            return false;
        }

        if ($pageno < 1 || $pageno > $total) {
            $pageno = 1;
        }

        $this->currentPage = $pageno;
        return true;
    }

    /**
     * returns the whole data that has been submited as paging data.
     *
     * @param none
     * @return array
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function getPageData()
    {
        if (($page = $this->getCurrentPage()) === false) {
            $page = 1;
        }

        if (!$this->hasPagingData()) {
            static::raiseError(__METHOD__ .'(), paging data has not been set yet!');
            return false;
        }

        if (($total = $this->getNumberOfPages()) === false) {
            static::raiseError(__CLASS__ .'::getNumberOfPages() returned false!');
            return false;
        }

        if (($items_per_page = $this->getCurrentItemsLimit()) === false) {
            static::raiseError(__CLASS__ .'::getCurrentItemsLimit() returned false!');
            return false;
        }

        if ($page > $total) {
            $page = 1;
        }

        if (($totalItems = $this->getPagingDataCount()) === false) {
            static::raiseError(__CLASS__ .'::getPagingDataCount() returned false!');
            return false;
        }

        if (!isset($totalItems) || !is_int($totalItems)) {
            static::raiseError(__CLASS__ .'::getPagingDataCount() returned invalid data!');
            return false;
        }

        if ($totalItems <= $items_per_page) {
            $page = 1;
        }

        if (gettype($items_per_page) === 'string' &&
            is_numeric($items_per_page)
        ) {
            $items_per_page = intval($items_per_page);
        }

        $start = ($page-1)*$items_per_page;

        /* so that DefaultModel::getItems() actually returns all items at once */
        if ($items_per_page === 0) {
            $items_per_page = null;
        }

        if (($data = $this->getPagingData($start, $items_per_page)) === false) {
            static::raiseError(__CLASS__ .':getPagingData() returned false!');
            return false;
        }

        if (!isset($data) || !is_array($data)) {
            static::raiseError(__METHOD__ .'(), slicing paging data failed!');
            return false;
        }

        return $data;
    }

    /**
     * returns true if there is paging data set.
     *
     * @param none
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function hasPagingData()
    {
        if (!isset($this->pagingData) ||
            empty($this->pagingData) ||
            !is_object($this->pagingData)
        ) {
            return false;
        }

        return true;
    }

    /**
     * returns the number of the next, following page.
     *
     * @param none
     * @return int|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function getNextPageNumber()
    {
        if (($page = $this->getCurrentPage()) === false) {
            static::raiseError(__CLASS__ .'::getCurrentPage() returned false!');
            return false;
        }

        if (($total = $this->getNumberOfPages()) === false) {
            static::raiseError(__CLASS__ .'::getNumberOfPages() returned false!');
            return false;
        }

        if (!isset($page) || empty($page) || !is_numeric($page) ||
            !isset($total) || empty($total) || !is_numeric($total) ||
            $total < 0
        ) {
            static::raiseError(__METHOD__ .'(), incomplete informations!');
            return false;
        }

        if ($page >= $total) {
            return false;
        }

        return $page+1;
    }

    /**
     * returns the number of the previous page
     *
     * @param none
     * @return int|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function getPreviousPageNumber()
    {
        if (($page = $this->getCurrentPage()) === false) {
            static::raiseError(__CLASS__ .'::getCurrentPage() returned false!');
            return false;
        }

        if (!isset($page) || empty($page) || !is_numeric($page)) {
            static::raiseError(__METHOD__ .'(), incomplete informations!');
            return false;
        }

        if ($page <= 1) {
            return false;
        }

        return $page-1;
    }

    /**
     * returns the number of the first page. usually 1.
     *
     * @param none
     * @return int
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function getFirstPageNumber()
    {
        return 1;
    }

    /**
     * returns the number of the last page
     *
     * @param none
     * @return int|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function getLastPageNumber()
    {
        if (($pages = $this->getNumberOfPages()) === false) {
            return false;
        }

        return $pages;
    }

    /**
     * returns the number of pages.
     *
     * @param none
     * @return int|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     * @todo is this duplicate to getNumberofPages()?
     */
    final public function getPageNumbers()
    {
        if (($total = $this->getNumberOfPages()) === false) {
            static::raiseError(__CLASS__ .'::getNumberOfPages() returned false!');
            return false;
        }

        if (!isset($total) ||
            empty($total) ||
            !is_numeric($total) ||
            $total < 0
        ) {
            static::raiseError(__CLASS__ .'::getNumberOfPages() returned invalid data!');
            return false;
        }

        $pages = array();
        for ($i = 1; $i <= $total; $i++) {
            $pages[] = $i;
        }

        return $pages;
    }

    /**
     * returns the delta page number.
     *
     * @param none
     * @return int|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function getDeltaPageNumbers()
    {
        if (($pages = $this->getPageNumbers()) === false) {
            static::raiseError(__CLASS__ .'::getPageNumbers() returned false!');
            return false;
        }

        if (($delta = $this->getParameter('delta')) === false) {
            static::raiseError(__METHOD__ .'(), $delta has not been set!');
            return false;
        }

        if (!($page = $this->getCurrentPage())) {
            $page = 1;
        }

        if (!isset($pages) || empty($pages) || !is_array($pages) ||
            !isset($delta) || empty($delta) || !is_numeric($delta) || $delta < 1 ||
            !isset($page) || empty($page) || !is_numeric($page) || $page < 1
        ) {
            static::raiseError(__METHOD__ .'(), incomplete informations!');
            return false;
        }

        if ($delta >= count($pages)) {
            return $pages;
        }

        if ($delta == 1) {
            return $page;
        }

        $start = $page-$delta;
        $end = $page+$delta;

        if ($page <= $delta) {
            $start = 1;
            $end = ($page+$delta) >= count($pages) ? count($pages) : ($page+$delta) ;
        } elseif (($page+$delta) >= count($pages)) {
            $start = $page-$delta;
            $end = count($pages);
        }

        /*
        print_r(array('pages' => count($pages), 'page' => $page, 'delta' => $delta, 'start' => $start, 'end' => $end));
        */
        $deltaPages = array();
        for ($i = $start; $i <= $end; $i++) {
            $deltaPages[] = $i;
        }

        return $deltaPages;
    }

    /**
     * returns the currently set items-per-page limit.
     *
     * @param none
     * @return int|bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function getCurrentItemsLimit()
    {
        if (!isset($this->currentItemsLimit)) {
            return static::$itemsPerPageLimits[0];
        }

        return $this->currentItemsLimit;
    }

    /**
     * returns an array of possible items-per-page limits.
     *
     * @param none
     * @return array
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function getItemsLimits()
    {
        return static::$itemsPerPageLimits;
    }

    /**
     * set the items-per-page limit.
     *
     * @param int $limit
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function setItemsLimit($limit)
    {
        if (!isset($limit) || !is_numeric($limit)) {
            static::raiseError(__METHOD__ .'(), $limit parameter is invalid!');
            return false;
        }

        if ($limit < 0) {
            if (($limit = $this->getFirstItemsLimit()) === false) {
                static::raiseError(__CLASS__ .'::getFirstItemsLimit() returned false!');
                return false;
            }
            $this->currentItemsLimit = $limit;
            return true;
        }

        if (!$this->isValidItemsLimit($limit)) {
            static::raiseError(__METHOD__ .'(), $limit parameter is not within allowed-limits list!');
            return false;
        }

        $this->currentItemsLimit = $limit;
        return true;
    }

    final public function isValidItemsLimit($limit)
    {
        if (!isset($limit) || !is_numeric($limit)) {
            static::raiseError(__METHOD__ .'(), $limit parameter is invalid!');
            return false;
        }

        if (($limits = $this->getItemsLimits()) === false) {
            static::raiseError(__CLASS__ .'::getCurrentItemsLimits() returned false!');
            return false;
        }

        if (!in_array($limit, $limits)) {
            return false;
        }

        return true;
    }

    final public function getFirstItemsLimit()
    {
        if (($limits = $this->getItemsLimits()) === false) {
            static::raiseError(__CLASS__ .'::getCurrentItemsLimits() returned false!');
            return false;
        }

        if (!isset($limits) || empty($limits) || !is_array($limits)) {
            return 0;
        }

        if (($first = array_shift($limits)) === null) {
            return 0;
        }

        return $first;
    }

    /**
     * returns true if the provided $limit matches the one that is currently
     * selected in the controller
     *
     * @param int $limit
     * @return bool
     * @throws \Thallium\Controllers\ExceptionController if an error occurs.
     */
    final public function isCurrentItemsLimit($limit)
    {
        if (!isset($limit) || !is_numeric($limit)) {
            static::raiseError(__METHOD__ .'(), $limit parameter is invalid!');
            return false;
        }

        if (($cur_limit = $this->getCurrentItemsLimit()) === false) {
            static::raiseError(__CLASS__ .'::getCurrentItemsLimit() returned false!');
            return false;
        }

        if ($limit !== $cur_limit) {
            return false;
        }

        return true;
    }
}

// vim: set filetype=php expandtab softtabstop=4 tabstop=4 shiftwidth=4:
