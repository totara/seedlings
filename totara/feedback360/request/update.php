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
 * @author David Curry <david.curry@totaralms.com>
 * @package totara
 * @subpackage totara_feedback360
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');

$users = required_param('users', PARAM_SEQUENCE);
$userformid = required_param('userform', PARAM_INT);

$PAGE->set_context(context_system::instance());

$renderer = $PAGE->get_renderer('totara_feedback360');
$out = '';

$out .= html_writer::start_tag('div', array('id' => 'system_assignments', 'class' => 'replacement_box'));

foreach (explode(',', trim($users, ',')) as $userid) {
    $user = $DB->get_record('user', array('id' => $userid));
    $resp_params = array('userid' => $userid, 'feedback360userassignmentid' => $userformid);
    $resp = $DB->get_record('feedback360_resp_assignment', $resp_params);

    $out .= $renderer->system_user_record($user, $userformid, $resp);
}

$out .= html_writer::end_tag('div');

echo "DONE{$out}";
exit();
