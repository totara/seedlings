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
require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->dirroot.'/totara/program/lib.php');
require_once($CFG->dirroot.'/totara/program/program_assignments.class.php');

require_login();

$completiontime = required_param('completiontime', PARAM_TEXT);
$completionevent = required_param('completionevent', PARAM_INT);
$completioninstance = required_param('completioninstance', PARAM_INT);

if ($completiontime == COMPLETION_TIME_NOT_SET && $completionevent == COMPLETION_EVENT_NONE && $completioninstance == 0) {
    echo get_string('setcompletion', 'totara_program');
} else {
    $string = prog_assignment_category::build_completion_string($completiontime, $completionevent, $completioninstance);
    if (trim($string) == '') {
        echo 'error';
    } else {
        echo $string;
    }
}
