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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage plan
 */

/**
 * General settings page for development plan templates
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');
require_once('template_forms.php');
require_once($CFG->dirroot . '/totara/plan/lib.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

$id = required_param('id', PARAM_INT);
$notice = optional_param('notice', 0, PARAM_INT); // notice flag

admin_externalpage_setup('managetemplates');

//Javascript include
local_js(array(
    TOTARA_JS_DIALOG
));

$returnurl = new moodle_url('/totara/plan/template/general.php', array('id' => $id));
$cancelurl = new moodle_url('/totara/plan/template/index.php');
if ($id) {
    if (!$template = $DB->get_record('dp_template', array('id' => $id))) {
        print_error('error:invalidtemplateid', 'totara_plan');
    }
}

$mform = new dp_template_general_settings_form(null, compact('id'));

// form results check
if ($mform->is_cancelled()) {
    redirect($cancelurl);
}
if ($fromform = $mform->get_data()) {

    if (empty($fromform->submitbutton)) {
        totara_set_notification(get_string('error:unknownbuttonclicked', 'totara_plan'), $returnurl);
    }
    if (update_general_settings($id, $fromform)) {
        totara_set_notification(get_string('update_general_settings', 'totara_plan'), $returnurl, array('class' => 'notifysuccess'));
    } else {
        totara_set_notification(get_string('error:update_general_settings', 'totara_plan'), $returnurl);
    }
}

$PAGE->navbar->add(format_string($template->fullname));

echo $OUTPUT->header();

if ($template) {
    echo $OUTPUT->heading(format_string($template->fullname));
} else {
    echo $OUTPUT->heading(get_string('newtemplate', 'totara_plan'));
}

$currenttab = 'general';
require('tabs.php');

$mform->display();

echo $OUTPUT->footer();


function update_general_settings($id, $fromform) {
    global $DB;

    $todb = new stdClass();
    $todb->id = $id;
    $todb->fullname = $fromform->templatename;
    $todb->enddate = $fromform->enddate;

    $transaction = $DB->start_delegated_transaction();

    $DB->update_record('dp_template', $todb);
    $transaction->allow_commit();
    return true;
}
