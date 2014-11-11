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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

/**
 * Set a SESSION var to store the visibility of a report builder column
 *
 * Called via AJAX from totara/reportbuilder/showhide.php
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

require_login();
require_sesskey();

$shortname = required_param('shortname', PARAM_TEXT);
$column = required_param('column', PARAM_TEXT);
$value = required_param('value', PARAM_TEXT);

// get current settings
$cols = isset($SESSION->rb_showhide_columns) ?
    $SESSION->rb_showhide_columns : array();

// update value
if (!isset($cols[$shortname])) {
    $cols[$shortname] = array();
}
$cols[$shortname][$column] = $value;

// store back to session
$SESSION->rb_showhide_columns = $cols;


