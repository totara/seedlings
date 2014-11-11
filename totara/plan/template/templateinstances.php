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
 * Page containing list of plan templates
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/totara/plan/lib.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

$id = required_param('id', PARAM_INT);

admin_externalpage_setup('managetemplates');

if (!$DB->get_record('dp_template', array('id' => $id))) {
    print_error('error:invalidtemplateid', 'totara_plan');
}

define('INSTANCES_PER_PAGE', 100);

$PAGE->navbar->add(get_string('templateinstances', 'totara_plan'));

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('templateinstances', 'totara_plan'));

$columns[] = 'name';
$headers[] = get_string('name', 'totara_plan');
$columns[] = 'user';
$headers[] = get_string('user');
$columns[] = 'startdate';
$headers[] = get_string('startdate', 'totara_plan');
$columns[] = 'enddate';
$headers[] = get_string('enddate', 'totara_plan');
$columns[] = 'status';
$headers[] = get_string('status', 'totara_plan');

$table = new flexible_table('Template_instances');
$table->define_columns($columns);
$table->define_headers($headers);
$table->define_baseurl(new moodle_url('/totara/plan/template/templateinstances.php', array('id' => $id)));
$table->set_attribute('class', 'generalbox dp-template-instances fullwidth');

$table->setup();

$sql = 'SELECT * FROM {dp_plan} WHERE templateid = ?';
$sort = ' ORDER BY id';
$params = array($id);

$perpage = INSTANCES_PER_PAGE;

$totalcount = $DB->count_records('dp_plan', array('templateid' => $id));

$table->initialbars($totalcount > $perpage);
$table->pagesize($perpage, $totalcount);

$instances = $DB->get_records_sql($sql.$sort, $params, $table->get_page_start(), $table->get_page_size());

foreach ($instances as $instance) {
    $tablerow = array();
    $tablerow[] = $OUTPUT->action_link(new moodle_url('/totara/plan/view.php', array('id' => $instance->id)), $instance->name);
    $user = $DB->get_record('user', array('id' => $instance->userid));
    $tablerow[] = $OUTPUT->action_link(new moodle_url('/user/view.php', array('id' => $user->id)), $user->firstname . ' ' . $user->lastname);
    $tablerow[] = date('j M Y', $instance->startdate);
    $tablerow[] = date('j M Y', $instance->enddate);

    switch($instance->status) {
    case DP_PLAN_STATUS_UNAPPROVED:
        $status = get_string('unapproved', 'totara_plan');
        break;
    case DP_PLAN_STATUS_APPROVED:
        $status = get_string('approved', 'totara_plan');
        break;

    case DP_PLAN_STATUS_COMPLETE:
        $status = get_string('complete', 'totara_plan');
        break;
    }
    $tablerow[] = $status;
    $table->add_data($tablerow);
}
$table->finish_html();

echo $OUTPUT->footer();
