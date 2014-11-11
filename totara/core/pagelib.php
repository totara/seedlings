<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010 onwards Totara Learning Solutions LTD
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage totara_core
 */

/**
 * Page with AJAX support
 */
class totara_page extends moodle_page {
    /**
     * Return totara requirements manager
     * @return page_requirements_manager tracks the JavaScript, CSS files, etc. required by this page.
     */
    protected function magic_get_requires() {
        if (is_null($this->_requires)) {
            $this->_requires = new totara_page_requirements_manager();
        }
        return parent::magic_get_requires();
    }
}

/**
 * Requirements manager with AJAX support
 */
class totara_page_requirements_manager extends page_requirements_manager {
    /**
     * Set head sent state if that was AJAX call
     */
    public function __construct() {
        parent::__construct();
        if (is_ajax_request($_SERVER)) {
            $this->headdone = true;
        }
    }
    /**
     * Make all domready events as regular events if that was AJAX call
     *
     * @param bool $ondomready
     * @return string
     */
    protected function get_javascript_code($ondomready) {
        if (is_ajax_request($_SERVER)) {
            $this->jscalls['normal'] = array_merge($this->jscalls['normal'], $this->jscalls['ondomready']);
            $this->jscalls['ondomready'] = array();
            $ondomready = false;
        }
        return parent::get_javascript_code($ondomready);
    }

    /**
     * Add short static javascript code fragment to page footer.
     * This is ajax supported version
     *
     * @param string $jscode
     * @param bool $ondomready wait for dom ready (helps with some IE problems when modifying DOM)
     * @param array $module JS module specification array
     */
    public function js_init_code($jscode, $ondomready = false, array $module = null) {
        if (is_ajax_request($_SERVER)) {
            $ondomready = false;
        }
        return parent::js_init_code($jscode, $ondomready, $module);
    }
}
