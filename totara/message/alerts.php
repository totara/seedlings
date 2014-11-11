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
 * @author Piers Harding <piers@catalyst.net.nz>
 * @package totara
 * @subpackage message
 */

/**
 * Displays collaborative features for the current user
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/totara/reportbuilder/lib.php');

// Initialise jquery requirements.
require_once($CFG->dirroot.'/totara/core/js/lib/setup.php');

require_login();

global $USER;

$sid = optional_param('sid', '0', PARAM_INT);
$format = optional_param('format', '',PARAM_TEXT); //export format

// Default to current user.
$userid = $USER->id;

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/totara/message/alerts.php');
$PAGE->set_pagelayout('noblocks');

$strheading = get_string('alerts', 'totara_message');

$shortname = 'alerts';
$data = array(
    'userid' => $userid,
);
if (!$report = reportbuilder_get_embedded_report($shortname, $data, false, $sid)) {
    print_error('error:couldnotgenerateembeddedreport', 'totara_reportbuilder');
}

$report->defaultsortcolumn = 'message_values_sent';
$report->defaultsortorder = 3;

$logurl = $PAGE->url->out_as_local_url();
if ($format!='') {
    $report->export_data($format);
    die;
}

\totara_reportbuilder\event\report_viewed::create_from_report($report)->trigger();

$report->include_js();
$PAGE->requires->js_init_call('M.totara_message.init');

///
/// Display the page
///
$PAGE->navbar->add(get_string('mylearning', 'totara_core'), new moodle_url('/my/'));
$PAGE->navbar->add($strheading);

$PAGE->set_title($strheading);
$PAGE->set_button($report->edit_button());
$PAGE->set_heading(format_string($SITE->fullname));

$output = $PAGE->get_renderer('totara_reportbuilder');

echo $output->header();
echo $output->heading($strheading);
echo html_writer::tag('p', html_writer::link("{$CFG->wwwroot}/my/", "<< " . get_string('mylearning', 'totara_core')));

$countfiltered = $report->get_filtered_count();
$countall = $report->get_full_count();

// Display heading including filtering stats.
if ($countfiltered == $countall) {
    echo $output->heading(get_string('recordsall', 'totara_message', $countall), 3);
} else {
    $a = new stdClass();
    $a->countfiltered = $countfiltered;
    $a->countall = $countall;
    echo $output->heading(get_string('recordsshown', 'totara_message', $a), 3);
}

if (empty($report->description)) {
    $report->description = get_string('alert_description', 'totara_message');
}

echo $output->print_description($report->description, $report->_id);

$report->display_search();
$report->display_sidebar_search();

// Print saved search buttons if appropriate.
echo $report->display_saved_search_options();

$PAGE->requires->string_for_js('reviewitems', 'block_totara_alerts');
$PAGE->requires->js_init_call('M.totara_message.dismiss_input_toggle');

echo $output->showhide_button($report->_id, $report->shortname);

echo html_writer::start_tag('form', array('id' => 'totara_messages', 'name' => 'totara_messages',
        'action' => new moodle_url('/totara/message/action.php'),  'method' => 'post'));
$report->display_table();
if ($countfiltered > 0) {
    echo totara_message_action_button('dismiss');
    echo totara_message_action_button('accept');
    echo totara_message_action_button('reject');

    $out = $output->box_start('generalbox', 'totara_message_actions');
    $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'returnto', 'value' => $FULLME));
    $out .= get_string('withselected', 'totara_message');
    $out .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'dismiss', 'id' => 'totara-dismiss',
            'disabled' => 'true', 'value' => get_string('dismiss', 'totara_message')));
    $out .= html_writer::tag('noscript', get_string('noscript', 'totara_message'));
    $out .= $output->box_end();
    print $out;
    print totara_message_checkbox_all_none();
}
print html_writer::end_tag('form');

// Export button.
$output->export_select($report->_id, $sid);

echo $output->footer();
