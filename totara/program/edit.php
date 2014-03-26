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
 * @author Ben Lobo <ben.lobo@kineo.com>
 * @package totara
 * @subpackage program
 */

/**
 * Program view/edit page
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('lib.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');
require_once('edit_form.php');
require_once($CFG->dirroot . '/totara/certification/lib.php');
require_once($CFG->dirroot . '/totara/customfield/fieldlib.php');

$id = required_param('id', PARAM_INT); // program id
$action = optional_param('action', 'view', PARAM_TEXT);
$category = optional_param('category', '', PARAM_INT);
$nojs = optional_param('nojs', 0, PARAM_INT);

require_login();

$systemcontext = context_system::instance();
$program = new program($id);
$iscertif = $program->certifid ? true : false;
$programcontext = $program->get_context();
$PAGE->set_context($programcontext);

customfield_load_data($program, 'program', 'prog');

// Check if programs or certifications are enabled.
if ($iscertif) {
    check_certification_enabled();
} else {
    check_program_enabled();
}

// Redirect to delete page if deleting.
if ($action == 'delete') {
    redirect(new moodle_url('/totara/program/delete.php', array('id' => $id, 'category' => $category)));
}

if (!has_capability('totara/program:configuredetails', $programcontext)) {
    print_error('error:nopermissions', 'totara_program');
}

$PAGE->set_url(new moodle_url('/totara/program/edit.php', array('id' => $id, 'action' => $action)));
$PAGE->set_title(format_string($program->fullname));
$PAGE->set_heading(format_string($program->fullname));

if ($action == 'edit') {
    // Javascript include.
    local_js(array(
        TOTARA_JS_DIALOG,
        TOTARA_JS_UI,
        TOTARA_JS_DATEPICKER,
        TOTARA_JS_ICON_PREVIEW,
        TOTARA_JS_PLACEHOLDER,
        TOTARA_JS_TREEVIEW
    ));

    $PAGE->requires->string_for_js('youhaveunsavedchanges', 'totara_program');
    $args = array('args'=>'{"id":'.$id.'}');
    $jsmodule = array(
            'name' => 'totara_programedit',
            'fullpath' => '/totara/program/program_edit.js',
            'requires' => array('json'));
    $PAGE->requires->js_init_call('M.totara_programedit.init',$args, false, $jsmodule);

    // attach a date picker to the available until/from fields
    build_datepicker_js(
        'input[name="availablefromselector"], input[name="availableuntilselector"]'
    );

    // Visible audiences.
    if (!empty($CFG->audiencevisibility)) {
        if(empty($program->id)) {
            $visibleselected = '';
        } else {
            $visibleselected = totara_cohort_get_visible_learning($program->id, COHORT_ASSN_ITEMTYPE_PROGRAM);
            $visibleselected = !empty($visibleselected) ? implode(',', array_keys($visibleselected)) : '';
        }
        $PAGE->requires->strings_for_js(array('programcohortsvisible'), 'totara_cohort');
        $jsmodule = array(
                        'name' => 'totara_visiblecohort',
                        'fullpath' => '/totara/cohort/dialog/visiblecohort.js',
                        'requires' => array('json'));
        $args = array('args'=>'{"visibleselected":"' . $visibleselected . '", "type":"program"}');
        $PAGE->requires->js_init_call('M.totara_visiblecohort.init', $args, true, $jsmodule);
        unset($visibleselected);
    }

    // Icon picker.
    $PAGE->requires->string_for_js('chooseicon', 'totara_program');
    $iconjsmodule = array(
            'name' => 'totara_iconpicker',
            'fullpath' => '/totara/core/js/icon.picker.js',
            'requires' => array('json'));

    $iconargs = array('args' => '{"selected_icon":"' . $program->icon . '", "type":"program"}');

    $PAGE->requires->js_init_call('M.totara_iconpicker.init', $iconargs, false, $iconjsmodule);
}

if (!$progcategory = $DB->get_record('course_categories', array('id' => $program->category))) {
    print_error('error:determineprogcat', 'totara_program');
}

$currenturl = qualified_me();
$currenturl_noquerystring = strip_querystring($currenturl);
$viewurl = $currenturl_noquerystring."?id={$id}&action=view";
$editurl = $currenturl_noquerystring."?id={$id}&action=edit";

$editcontenturl = "{$CFG->wwwroot}/totara/program/edit_content.php?id={$program->id}";
$editassignmentsurl = "{$CFG->wwwroot}/totara/program/edit_assignments.php?id={$program->id}";
$editmessagesurl = "{$CFG->wwwroot}/totara/program/edit_messages.php?id={$program->id}";
$editcertificationsurl = "{$CFG->wwwroot}/totara/certification/edit_certification.php?id={$program->id}";

//set up textareas
$program->endnoteformat = FORMAT_HTML;
$program->summaryformat = FORMAT_HTML;

$editoroptions = $TEXTAREA_OPTIONS;
$editoroptions['context'] = context_program::instance($program->id);
$program = file_prepare_standard_editor($program, 'summary', $editoroptions, $editoroptions['context'],
                                          'totara_program', 'summary', 0);

$program = file_prepare_standard_editor($program, 'endnote', $editoroptions, $editoroptions['context'],
    'totara_program', 'endnote', 0);

$overviewfilesoptions = prog_program_overviewfiles_options($program);
if ($overviewfilesoptions) {
    file_prepare_standard_filemanager($program, 'overviewfiles', $overviewfilesoptions, $programcontext, 'totara_program', 'overviewfiles', 0);
}
$detailsform = new program_edit_form($currenturl,
                array('program' => $program, 'action' => $action, 'category' => $progcategory,
                        'editoroptions' => $TEXTAREA_OPTIONS, 'nojs' => $nojs, 'iscertif' =>  $iscertif),
                        'post', '', array('name'=>'form_prog_details'));

if ($detailsform->is_cancelled()) {
    totara_set_notification(get_string('programupdatecancelled', 'totara_program'), $viewurl, array('class' => 'notifysuccess'));
}



// Handle form submits
if ($data = $detailsform->get_data()) {
    if (isset($data->edit)) {
        redirect($editurl);
    } else if (isset($data->savechanges)) {

        // Preprocess to convert string dates e.g. '23/11/2012' to a unix timestamp
        $data->availablefrom = ($data->availablefromselector) ? totara_date_parse_from_format(get_string('datepickerlongyearparseformat', 'totara_core'),$data->availablefromselector) : 0;
        $data->availableuntil = ($data->availableuntilselector) ? totara_date_parse_from_format(get_string('datepickerlongyearparseformat', 'totara_core'),$data->availableuntilselector) : 0;

        $data->timemodified = time();
        $data->usermodified = $USER->id;

        // Program has moved categories
        if ($data->category != $program->category) {
            prog_move_programs(array($program->id), $data->category);
        }

        // Save program data
        $DB->update_record('prog', $data);

        $data->id = $program->id;
        customfield_save_data($data, 'program', 'prog');

        if (isset($data->savechanges)) {
            $nexturl = $viewurl;
        }

        $programcontext = context_program::instance($program->id);
        file_postupdate_standard_editor($data, 'summary', $TEXTAREA_OPTIONS, $programcontext, 'totara_program', 'summary', 0);
        $DB->set_field('prog', 'summary', $data->summary, array('id' => $data->id));

        if ($overviewfilesoptions = prog_program_overviewfiles_options($data->id)) {
            file_postupdate_standard_filemanager($data, 'overviewfiles', $overviewfilesoptions, $programcontext, 'totara_program', 'overviewfiles', 0);
        }

        file_postupdate_standard_editor($data, 'endnote', $TEXTAREA_OPTIONS, $programcontext, 'totara_program', 'endnote', 0);
        $DB->set_field('prog', 'endnote', $data->endnote, array('id' => $data->id));

        // Visible audiences.
        if (!empty($CFG->audiencevisibility) && has_capability('totara/coursecatalog:manageaudiencevisibility', $systemcontext)) {
            $visiblecohorts = totara_cohort_get_visible_learning($program->id, COHORT_ASSN_ITEMTYPE_PROGRAM);
            $visiblecohorts = !empty($visiblecohorts) ? $visiblecohorts : array();
            $newvisible = !empty($data->cohortsvisible) ? explode(',', $data->cohortsvisible) : array();
            if ($todelete = array_diff(array_keys($visiblecohorts), $newvisible)) {
                // Delete removed cohorts.
                foreach ($todelete as $cohortid) {
                    totara_cohort_delete_association($cohortid, $visiblecohorts[$cohortid]->associd,
                                                    COHORT_ASSN_ITEMTYPE_PROGRAM, COHORT_ASSN_VALUE_VISIBLE);
                }
            }

            if ($newvisible = array_diff($newvisible, array_keys($visiblecohorts))) {
                // Add new cohort associations.
                foreach ($newvisible as $cohortid) {
                    totara_cohort_add_association($cohortid, $program->id, COHORT_ASSN_ITEMTYPE_PROGRAM, COHORT_ASSN_VALUE_VISIBLE);
                }
            }
        }

        totara_set_notification(get_string('programdetailssaved', 'totara_program'), $nexturl, array('class' => 'notifysuccess'));
    }

    // Reload program to reflect any changes
    $program = new program($id);
}

// log this request
add_to_log(SITEID, 'program', 'view', "edit.php?id={$program->id}&amp;iscertif={$iscertif}", $program->fullname);

///
/// Display
///

$programpagelinks = '';
$pageid = 'program-overview';

if ($action == 'edit') {
    $currenttab = 'details';
    $pageid = 'program-overview-details';
} else {
    $currenttab = 'overview';
}

echo $OUTPUT->header();

echo $OUTPUT->container_start('program overview', $pageid);

echo $OUTPUT->heading(format_string($program->fullname));

$renderer = $PAGE->get_renderer('totara_program');
// Display the current status
echo $program->display_current_status();
$exceptions = $program->get_exception_count();
require('tabs.php');

// Program details
$program->availablefromselector = $program->availablefrom > 0 ? userdate($program->availablefrom, get_string('datepickerlongyearphpuserdate', 'totara_core'), $CFG->timezone, false) : '';
$program->availableuntilselector = $program->availableuntil > 0 ? userdate($program->availableuntil, get_string('datepickerlongyearphpuserdate', 'totara_core'), $CFG->timezone, false) : '';

$detailsform->set_data($program);
$detailsform->display();

// display content, assignments and messages if in view mode
if ($action == 'view') {

    // display the content form
    $contentform = new program_content_nonedit_form($editcontenturl, array('program' => $program), 'get');
    $contentform->set_data($program);
    $contentform->display();

    // display the assignments form
    $assignmentform = new program_assignments_nonedit_form($editassignmentsurl, array('program' => $program), 'get');
    $assignmentform->set_data($program);
    $assignmentform->display();

    // display the messages form
    $messagesform = new program_messages_nonedit_form($editmessagesurl, array('program' => $program), 'get');
    $messagesform->set_data($program);
    $messagesform->display();

    if ($iscertif) {
        // display the certifications form
        $certificationsform = new program_certifications_nonedit_form($editcertificationsurl, array('program' => $program), 'get');
        $certificationsform->set_data($program);
        $certificationsform->display();
    }

    // display the delete button form
    if (has_capability('totara/program:deleteprogram', $program->get_context())) {
        $deleteform = new program_delete_form($currenturl, array('program' => $program));
        $deleteform->set_data($program);
        $deleteform->display();
    }

}

if ($action == 'edit') {
    echo $renderer->get_cancel_button(array('id' => $program->id));
}

echo $OUTPUT->container_end();
echo $OUTPUT->footer();
