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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package totara
 * @subpackage cohort
 */
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/totara/cohort/lib.php');
require_once($CFG->dirroot . '/totara/cohort/cohort_forms.php');

admin_externalpage_setup('cohortglobalsettings');

$returnurl = $CFG->wwwroot . "/totara/cohort/globalsettings.php";

// form definition
$mform = new cohort_global_settings_form();

// form results check
if ($mform->is_cancelled()) {
    redirect($returnurl);
}
if ($fromform = $mform->get_data()) {

    if (empty($fromform->submitbutton)) {
        totara_set_notification(get_string('error:unknownbuttonclicked', 'totara_cohort'), $returnurl);
    }

    update_global_settings($fromform);

    totara_set_notification(get_string('globalsettingsupdated', 'totara_cohort'), $returnurl, array('class' => 'notifysuccess'));
}

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('cohortglobalsettings', 'totara_cohort'));

// display the form
$mform->display();

echo $OUTPUT->footer();

/**
 * Update global report builder settings
 *
 * @param object $fromform Moodle form object containing global setting changes to apply
 *
 * @return True if settings could be successfully updated
 */
function update_global_settings($fromform) {
    global $COHORT_ALERT;

    $alertoptions = array();
    foreach ($COHORT_ALERT as $code => $option) {
        $checkboxname = 'alert' . $code;
        if (isset($fromform->$checkboxname) && $fromform->$checkboxname == 1) {
            $alertoptions[] = $code;
        }
    }
    set_config('alertoptions', implode(',', $alertoptions), 'cohort');
    return true;
}
