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
 * @author Darko Miletic
 * @package totara
 * @subpackage cron
 */

require(dirname(__FILE__).'/../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once(dirname(__FILE__).'/cronsettings_form.php');

$jsmodule = array(
    'name'     => 'cronsettings',
    'fullpath' => '/admin/cronsettings.js',
    'requires' => array('base', 'io', 'json')
);
$PAGE->requires->js_init_call('M.cronsettings.init', array('args' => '{"wwwroot": "' . $CFG->wwwroot . '"}'), false, $jsmodule);

require_login();

admin_externalpage_setup('cron_settings');

$context = context_system::instance();

require_capability('moodle/site:config', $context, $USER->id, true, "nopermissions");


/// Print the header stuff

echo $OUTPUT->header();

$cronsettings = new cronsettings_form();
$fromform = $cronsettings->get_data();

if (!empty($fromform)) {

    //Save settings
    $result = set_config('cron_max_time',
               isset($fromform->cron_max_time) ? $fromform->cron_max_time : 0);
    $result = set_config('cron_max_time_mail_notify',
               isset($fromform->cron_max_time_mail_notify) ? $fromform->cron_max_time_mail_notify : 0) && $result;
    $result = set_config('cron_max_time_kill',
               isset($fromform->cron_max_time_kill) ? $fromform->cron_max_time_kill : 0) && $result;

    //display confirmation
    if ($result) {
        echo $OUTPUT->notification(get_string('changessaved'), 'notifysuccess');
    } else {
        echo $OUTPUT->notification(get_string('errorwithsettings'));
    }

}

if (!$cronsettings->is_submitted()) {
    $data = array();
    $data['cron_max_time'] = isset($CFG->cron_max_time) ? $CFG->cron_max_time : 0;
    $data['cron_max_time_mail_notify'] = isset($CFG->cron_max_time_mail_notify) ? $CFG->cron_max_time_mail_notify : 0;
    $data['cron_max_time_kill'] = isset($CFG->cron_max_time_kill) ? $CFG->cron_max_time_kill : 0;
    $cronsettings->set_data($data);
}


/// Print the appropriate form
echo $OUTPUT->heading(get_string('cron_settings', 'admin'));

$cronsettings->display();

echo $OUTPUT->footer();
