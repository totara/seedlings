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
 * @author Aaron Wells <aaronw@catalyst.net.nz>
 * @package totara
 * @subpackage cohort
 */
/**
 * This page displays the embedded report for the "visible learning" items for a single cohort
 */
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');

$context = context_system::instance();
$canedit = has_capability('moodle/cohort:manage', $context);

$sid    = optional_param('sid', '0', PARAM_INT);
$id     = optional_param('id', false, PARAM_INT);
$format = optional_param('format', '', PARAM_TEXT);
$debug  = optional_param('debug', false, PARAM_BOOL);

$url = new moodle_url('/totara/cohort/visiblelearning.php', array('id' => $id, 'format' => $format));
admin_externalpage_setup('cohorts', '', null, $url, array('pagelayout' => 'report'));

if (empty($CFG->audiencevisibility)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('error:visiblelearningdisabled', 'totara_cohort'));
    echo $OUTPUT->footer();
    exit;
}

if (!$id) {
    echo $OUTPUT->header();
    $url = new moodle_url('/cohort/index.php');
    echo $OUTPUT->container(get_string('cohortvisiblelearningselect', 'totara_cohort', $url->out()));
    echo $OUTPUT->footer();
    exit;
}

$cohort = $DB->get_record('cohort', array('id' => $id), '*', MUST_EXIST);

$report = reportbuilder_get_embedded_report('cohort_associations_visible', array('cohortid' => $id), false, $sid);
$report->include_js();

// Handle a request for export.
if($format != '') {
    $report->export_data($format);
    die;
}

// Setup lightbox.
local_js(array(TOTARA_JS_DIALOG, TOTARA_JS_TREEVIEW));

$PAGE->requires->strings_for_js(array('none'), 'moodle');
$PAGE->requires->strings_for_js(array('assignvisiblelearning', 'assignenrolledlearning', 'deletelearningconfirm', 'savinglearning'), 'totara_cohort');
$jsmodule = array(
        'name' => 'totara_cohortlearning',
        'fullpath' => '/totara/cohort/dialog/learningitem.js',
        'requires' => array('json'));
$args = array('args' => '{"cohortid":' . $cohort->id . ',' .
            '"COHORT_ASSN_ITEMTYPE_CERTIF":' . COHORT_ASSN_ITEMTYPE_CERTIF . ',' .
            '"COHORT_ASSN_ITEMTYPE_PROGRAM":' . COHORT_ASSN_ITEMTYPE_PROGRAM . ',' .
            '"COHORT_ASSN_ITEMTYPE_COURSE":' . COHORT_ASSN_ITEMTYPE_COURSE . ',' .
            '"COHORT_ASSN_VALUE_VISIBLE":' . COHORT_ASSN_VALUE_VISIBLE . ',' .
            '"COHORT_ASSN_VALUE_ENROLLED":' . COHORT_ASSN_VALUE_ENROLLED . ',' .
            '"assign_value":' . COHORT_ASSN_VALUE_VISIBLE . ',' .
            '"assign_string":"' . $COHORT_ASSN_VALUES[COHORT_ASSN_VALUE_VISIBLE] .'",'.
            '"saveurl":"/totara/cohort/visiblelearning.php" }');
$PAGE->requires->js_init_call('M.totara_cohortlearning.init', $args, false, $jsmodule);

$strheading = get_string('visiblelearning', 'totara_cohort');
totara_cohort_navlinks($cohort->id, format_string($cohort->name), $strheading);
echo $OUTPUT->header();

if ($debug) {
    $report->debug($debug);
}

echo $OUTPUT->heading(format_string($cohort->name));
echo cohort_print_tabs('visiblelearning', $cohort->id, $cohort->cohorttype, $cohort);

if ($canedit) {
    echo html_writer::start_tag('div', array('class' => 'buttons'));

    if (has_capability('moodle/course:update', context_system::instance())) {
        // Add courses.
        echo html_writer::start_tag('div', array('class' => 'singlebutton'));
        echo html_writer::empty_tag('input', array('type' => 'submit', 'id' => 'add-course-learningitem-dialog',
            'value' => get_string('addcourses', 'totara_cohort')));
        echo html_writer::end_tag('div');
    }

    if (has_capability('totara/program:configuredetails', context_system::instance())) {
        // Add programs.
        echo html_writer::start_tag('div', array('class' => 'singlebutton'));
        echo html_writer::empty_tag('input', array('type' => 'submit', 'id' => 'add-program-learningitem-dialog',
            'value' => get_string('addprograms', 'totara_cohort')));
        echo html_writer::end_tag('div');

        // Add certifications.
        echo html_writer::start_tag('div', array('class' => 'singlebutton'));
        echo html_writer::empty_tag('input', array('type' => 'submit', 'id' => 'add-certification-learningitem-dialog',
            'value' => get_string('addcertifications', 'totara_cohort')));
        echo html_writer::end_tag('div');
    }

    echo html_writer::end_tag('div');
}

$report->display_search();
$report->display_sidebar_search();

// Print saved search buttons if appropriate.
echo $report->display_saved_search_options();

$report->display_table();

$output = $PAGE->get_renderer('totara_reportbuilder');
$output->export_select($report->_id, $sid);

echo $OUTPUT->footer();
