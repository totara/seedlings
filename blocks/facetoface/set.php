<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2009 Catalyst IT LTD
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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @author Francois Marier <francois@catalyst.net.nz>
 * @package blocks
 * @subpackage facetoface
 */

require_once(dirname(dirname(dirname(__FILE))) . '/config.php');
require_once($CFG->dirroot . '/calendar/lib.php');

$var        = required_param('var', PARAM_ALPHA);
$currenttab = required_param('tab', PARAM_ALPHA);
$day        = required_param('cal_d', PARAM_INT);
$month      = required_param('cal_m', PARAM_INT);
$year       = required_param('cal_y', PARAM_INT);

calendar_session_vars();

switch($var) {
case 'showgroups':
    $SESSION->cal_show_groups = !$SESSION->cal_show_groups;
    set_user_preference('calendar_savedflt', calendar_get_filters_status());
    break;
case 'showcourses':
    $SESSION->cal_show_course = !$SESSION->cal_show_course;
    set_user_preference('calendar_savedflt', calendar_get_filters_status());
    break;
case 'showglobal':
    $SESSION->cal_show_global = !$SESSION->cal_show_global;
    set_user_preference('calendar_savedflt', calendar_get_filters_status());
    break;
case 'showuser':
    $SESSION->cal_show_user = !$SESSION->cal_show_user;
    set_user_preference('calendar_savedflt', calendar_get_filters_status());
    break;
}

redirect(new moodle_url('/blocks/facetoface/calendar.php', array('tab' => $currenttab, 'cal_d' => $day, 'cal_m' => $month, 'cal_y' => $year)));
