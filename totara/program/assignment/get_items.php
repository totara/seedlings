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
 * @package totara
 * @subpackage program
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot.'/totara/program/lib.php');

$PAGE->set_context(context_system::instance());
require_login();

$catid = required_param('catid', PARAM_INT); // Id of the category, as specified in the class definition
$progid = required_param('progid', PARAM_INT); // Id of the program record

// Check capabilities
require_capability('totara/program:configureassignments', program_get_context($progid));

// Categories
$categories = array(
    new organisations_category(),
    new positions_category(),
    new cohorts_category(),
    new managers_category(),
    new individuals_category(),
);

// Find the matching category
foreach ($categories as $category) {
    if ($category->id == $catid) {
        $category->build_table($progid);

        // Get the html and javascript
        $html = $category->display(true);
        $html .= html_writer::script($category->get_js($progid));
        $data = array(
            'html'  => $html
        );
        echo json_encode($data);
        die();
    }
}
