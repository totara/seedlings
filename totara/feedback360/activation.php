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

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/totara/feedback360/lib.php');
require_once($CFG->dirroot . '/totara/feedback360/lib/assign/lib.php');
require_once($CFG->dirroot . '/totara/feedback360/feedback360_forms.php');

// Check if 360 Feedbacks are enabled.
feedback360::check_feature_enabled();

$id = required_param('id', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_INT);

admin_externalpage_setup('managefeedback360');
$systemcontext = context_system::instance();
require_capability('totara/feedback360:manageactivation', $systemcontext);

$feedback360 = new feedback360($id);

switch ($action) {
    case 'activate':
        $errors = $feedback360->validate();
        if (empty($errors) && $confirm) {
            require_sesskey();
            $feedback360->activate();
            totara_set_notification(get_string('feedback360activated', 'totara_feedback360', $feedback360->name),
                         new moodle_url('/totara/feedback360/manage.php'), array('class' => 'notifysuccess'));
        }
    break;
    case 'close':
        if ($confirm) {
            require_sesskey();
            $feedback360->close();
            totara_set_notification(get_string('feedback360closed', 'totara_feedback360', $feedback360->name),
                         new moodle_url('/totara/feedback360/manage.php'), array('class' => 'notifysuccess'));
        }
    break;
}

$output = $PAGE->get_renderer('totara_feedback360');
echo $output->header();
echo $output->heading($feedback360->name);

switch ($action) {
    case 'activate':
        echo $output->confirm_activation_feedback360($feedback360, $errors);
        break;
    case 'close':
        echo $output->confirm_close_feedback360($feedback360);
        break;
    default:
        echo get_string('unrecognizedaction', 'totara_feedback360');
}

echo $output->footer();
