<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Cohort related management functions, this file needs to be included manually.
 *
 * @package    core_cohort
 * @copyright  2010 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../config.php');
require_once($CFG->libdir.'/adminlib.php');
require($CFG->dirroot.'/course/lib.php');
require($CFG->dirroot.'/cohort/lib.php');
require($CFG->dirroot.'/cohort/edit_form.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');

$usetags = (!empty($CFG->usetags));
if ($usetags) {
    require_once($CFG->dirroot.'/tag/lib.php');
}

$id        = optional_param('id', 0, PARAM_INT);
$contextid = optional_param('contextid', 0, PARAM_INT);
$delete    = optional_param('delete', 0, PARAM_BOOL);
$confirm   = optional_param('confirm', 0, PARAM_BOOL);

$url = new moodle_url('/cohort/edit.php', array('id' => $id, 'contextid' => $contextid,
    'delete' => $delete, 'confirm' => $confirm));
admin_externalpage_setup('cohorts', '', null, $url, array('pagelayout'=>'report'));

require_login();

$category = null;
if ($id) {
    $cohort = $DB->get_record('cohort', array('id'=>$id), '*', MUST_EXIST);
    if ($usetags) {
        $cohort->otags = array_keys(tag_get_tags_array('cohort', $cohort->id, 'official'));
    }
    $context = context::instance_by_id($cohort->contextid, MUST_EXIST);
} else {
    $context = context::instance_by_id($contextid, MUST_EXIST);
    if ($context->contextlevel != CONTEXT_COURSECAT and $context->contextlevel != CONTEXT_SYSTEM) {
        print_error('invalidcontext');
    }
    $cohort = new stdClass();
    $cohort->id          = 0;
    $cohort->contextid   = $context->id;
    $cohort->name        = '';
    $cohort->description = '';
    $cohort->cohorttype  = cohort::TYPE_STATIC;
}

require_capability('moodle/cohort:manage', $context);

$returnurl = new moodle_url('/cohort/index.php', array('contextid'=>$context->id));

if (!empty($cohort->component)) {
    // We can not manually edit cohorts that were created by external systems, sorry.
    redirect($returnurl);
}

$PAGE->set_context($context);
$PAGE->set_url('/cohort/edit.php', array('contextid'=>$context->id, 'id'=>$cohort->id));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');

if ($context->contextlevel == CONTEXT_COURSECAT) {
    $category = $DB->get_record('course_categories', array('id'=>$context->instanceid), '*', MUST_EXIST);
    navigation_node::override_active_url(new moodle_url('/cohort/index.php', array('contextid'=>$cohort->contextid)));

} else {
    navigation_node::override_active_url(new moodle_url('/cohort/index.php', array()));
}

if ($delete and $cohort->id) {
    $PAGE->url->param('delete', 1);
    if ($confirm and confirm_sesskey()) {
        cohort_delete_cohort($cohort);
        redirect($returnurl);
    }
    $strheading = get_string('delcohort', 'cohort');
    $PAGE->navbar->add($strheading);
    $PAGE->set_title($strheading);
    $PAGE->set_heading($COURSE->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->heading($strheading);
    $yesurl = new moodle_url('/cohort/edit.php', array('id'=>$cohort->id, 'delete'=>1, 'confirm'=>1,'sesskey'=>sesskey()));
    $message = get_string('delconfirm', 'cohort', format_string($cohort->name));
    echo $OUTPUT->confirm($message, $yesurl, $returnurl);
    echo $OUTPUT->footer();
    die;
}

$editoroptions = array('maxfiles'=>0, 'context'=>$context);
if ($cohort->id) {
    // Edit existing.
    $cohort = file_prepare_standard_editor($cohort, 'description', $editoroptions, $context);
    $strheading = get_string('editcohort', 'cohort');
} else {
    $cohort = file_prepare_standard_editor($cohort, 'description', $editoroptions, $context);
    $strheading = get_string('addcohort', 'cohort');
}

$PAGE->set_title($strheading);
$PAGE->set_heading($COURSE->fullname);
$PAGE->navbar->add($strheading);

$cohort->descriptionformat = FORMAT_HTML;
$cohort = file_prepare_standard_editor($cohort, 'description', $editoroptions, $context, 'cohort', 'cohort', $cohort->id);
$editform = new cohort_edit_form(null, array('editoroptions'=>$editoroptions, 'data'=>$cohort));

if ($editform->is_cancelled()) {
    redirect($returnurl);

} else if ($data = $editform->get_data()) {

    if ($data->id) {
        cohort_update_cohort($data);
        if ($usetags) {
            if (isset($data->otags)) {
                tag_set('cohort', $cohort->id, tag_get_name($data->otags));
            } else {
                tag_set('cohort', $cohort->id, array());
            }
        }
        add_to_log(SITEID, 'cohort', 'edit', '/cohort/view.php?id='.$cohort->id, $data->idnumber);
        //update textarea
        $data = file_postupdate_standard_editor($data, 'description', $editoroptions, $context, 'cohort', 'cohort', $data->id);
        $DB->set_field('cohort', 'description', $data->description, array('id' => $data->id));
        // Updated
        $url = new moodle_url('/cohort/view.php', array('id' => $data->id));
        totara_set_notification(get_string('successfullyupdated','totara_cohort'), $url, array('class' => 'notifysuccess'));
    } else {
        $cohortid = cohort_add_cohort($data);
        if ($usetags) {
            if (isset($data->otags)) {
                tag_set('cohort', $cohortid, tag_get_name($data->otags));
            } else {
                tag_set('cohort', $cohortid, array());
            }
        }
        add_to_log(SITEID, 'cohort', 'create', '/cohort/view.php?id='.$cohortid, $data->idnumber);
        //update textarea
        $data = file_postupdate_standard_editor($data, 'description', $editoroptions, $context, 'cohort', 'cohort', $cohortid);
        $DB->set_field('cohort', 'description', $data->description, array('id' => $cohortid));
        if ($data->cohorttype == cohort::TYPE_STATIC) {
            $url = new moodle_url('/cohort/assign.php', array('id' => $cohortid));
        } else {
            $url = new moodle_url('/totara/cohort/rules.php', array('id' => $cohortid));
        }
        redirect($url);
    }

    // Use new context id, it could have been changed.
    redirect(new moodle_url('/cohort/index.php', array('contextid'=>$data->contextid)));
}

echo $OUTPUT->header();
if ($cohort->id != false) {
    echo $OUTPUT->heading($strheading);
    echo cohort_print_tabs('edit', $cohort->id, $cohort->cohorttype, $cohort);
}
else {
    echo $OUTPUT->heading($strheading);
}
echo $editform->display();

echo $OUTPUT->footer();
