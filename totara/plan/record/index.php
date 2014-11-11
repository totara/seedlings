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
 * @author Nathan Lewis <nathan.lewis@totaralms.com>
 * @package totara
 * @subpackage plan
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/totara/plan/lib.php');

require_login();

$userid = optional_param('userid', $USER->id, PARAM_INT);
$rolstatus = optional_param('status', 'all', PARAM_ALPHA);

$params = array('userid' => $userid, 'status' => $rolstatus);

if ($visible = dp_get_rol_tabs_visible($userid)) {
    $showtab = $visible[0];
    if ($showtab !== 'evidence') {
        redirect(new moodle_url("/totara/plan/record/{$showtab}.php", $params));
    } else {
        redirect(new moodle_url('/totara/plan/record/evidence/index.php', $params));
    }
}

// No tabs are visible (shouldn't happen), redirect to my learning page.
redirect(new moodle_url('/my/'));
