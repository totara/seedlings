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
 * Page for adding a program
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('lib.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');
require_once($CFG->dirroot . '/totara/cohort/lib.php');
require_once('edit_form.php');
require_once($CFG->dirroot . '/totara/certification/lib.php');
require_once($CFG->dirroot . '/totara/customfield/fieldlib.php');

require_login();

$categoryid = optional_param('category', 0, PARAM_INT); // course category - can be changed in edit form
$iscertif = optional_param('iscertif', 0, PARAM_INT); // program=0|certification=1 - passed from certification/add.php

// Check if programs or certifications are enabled.
if ($iscertif) {
    check_certification_enabled();
} else {
    check_program_enabled();
}

$systemcontext = context_system::instance();
$actualurl = new moodle_url('/totara/program/add.php', array('category' => $categoryid, 'iscertif' => $iscertif));

// Integrate into the admin tree only if the user can create programs at the top level,
// otherwise the admin block does not appear to this user, and you get an error.
if ($iscertif) {
    if (has_capability('totara/certification:createcertification', $systemcontext)) {
        admin_externalpage_setup('managecertifications', '', null, $actualurl);
    } else {
        $PAGE->set_context($systemcontext);
        $PAGE->set_url($actualurl);
        $PAGE->set_title(get_string('createnewcertification', 'totara_certification'));
        $PAGE->set_heading(get_string('createnewcertification', 'totara_certification'));
    }
} else {
    if (has_capability('totara/program:createprogram', $systemcontext)) {
        admin_externalpage_setup('programmgmt', '', null, $actualurl);
    } else {
        $PAGE->set_context($systemcontext);
        $PAGE->set_url($actualurl);
        $PAGE->set_title(get_string('createnewprogram', 'totara_program'));
        $PAGE->set_heading(get_string('createnewprogram', 'totara_program'));
    }
}


//Javascript include
local_js(array(
    TOTARA_JS_DIALOG,
    TOTARA_JS_UI,
    TOTARA_JS_ICON_PREVIEW,
    TOTARA_JS_TREEVIEW
));

$PAGE->requires->string_for_js('chooseicon', 'totara_program');
$iconjsmodule = array(
        'name' => 'totara_iconpicker',
        'fullpath' => '/totara/core/js/icon.picker.js',
        'requires' => array('json'));

$iconargs = array('args' => '{"selected_icon":"default", "type":"program"}');

$PAGE->requires->js_init_call('M.totara_iconpicker.init', $iconargs, false, $iconjsmodule);

// Visible audiences.
if (!empty($CFG->audiencevisibility)) {
    $PAGE->requires->strings_for_js(array('programcohortsvisible'), 'totara_cohort');
    $jsmodule = array(
                    'name' => 'totara_visiblecohort',
                    'fullpath' => '/totara/cohort/dialog/visiblecohort.js',
                    'requires' => array('json'));
    $args = array('args'=>'{"visibleselected":"", "type":"program"}');
    $PAGE->requires->js_init_call('M.totara_visiblecohort.init', $args, true, $jsmodule);
}

if ($categoryid) { // creating new program in this category
    if (!$category = $DB->get_record('course_categories', array('id' => $categoryid))) {
        print_error('Category ID was incorrect');
    }
    if (!$iscertif) {
        require_capability('totara/program:createprogram', context_coursecat::instance($category->id));
    } else {
        require_capability('totara/certification:createcertification', context_coursecat::instance($category->id));
    }

} else {
    print_error('Program category must be specified');
}
///
/// Data and actions
///

$item = new stdClass();
$item->id = 0;
$item->endnote = '';
$item->endnoteformat = FORMAT_HTML;
$item->summary = '';
$item->summaryformat = FORMAT_HTML;

$currenturl = qualified_me();
$progindexurl = "{$CFG->wwwroot}/totara/program/index.php";

$item = file_prepare_standard_editor($item, 'summary', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
                                          'totara_program', 'summary', 0);

$item = file_prepare_standard_editor($item, 'endnote', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
                                          'totara_program', 'endnote', 0);

$overviewfilesoptions = prog_program_overviewfiles_options($item);
if ($overviewfilesoptions) {
    file_prepare_standard_filemanager($item, 'overviewfiles', $overviewfilesoptions, $systemcontext, 'totara_program', 'overviewfiles', 0);
}
$form = new program_edit_form($currenturl, array('action' => 'add', 'category' => $category, 'editoroptions' => $TEXTAREA_OPTIONS));

$form = new program_edit_form($currenturl, array('action' => 'add', 'category' => $category,
                'editoroptions' => $TEXTAREA_OPTIONS, 'iscertif' =>  $iscertif));

if ($form->is_cancelled()) {
    redirect($progindexurl);
}

// Set type.
$instancetype = COHORT_ASSN_ITEMTYPE_PROGRAM;
if ($iscertif) {
    $instancetype = COHORT_ASSN_ITEMTYPE_CERTIF;
}

// Handle form submit
if ($data = $form->get_data()) {

    if (isset($data->savechanges)) {

        $program_todb = new stdClass;

        $program_todb->availablefrom = ($data->availablefrom) ? $data->availablefrom : 0;
        $program_todb->availableuntil = ($data->availableuntil) ? $data->availableuntil : 0;
        $available = prog_check_availability($program_todb->availablefrom, $program_todb->availableuntil);

        //Calcuate sortorder
        $sortorder = $DB->get_field('prog', 'MAX(sortorder) + 1', array());

        $now = time();
        $program_todb->timecreated = $now;
        $program_todb->timemodified = $now;
        $program_todb->usermodified = $USER->id;
        $program_todb->category = $data->category;
        $program_todb->shortname = $data->shortname;
        $program_todb->fullname = $data->fullname;
        $program_todb->idnumber = $data->idnumber;
        $program_todb->sortorder = !empty($sortorder) ? $sortorder : 0;
        $program_todb->icon = $data->icon;
        $program_todb->exceptionssent = 0;
        $program_todb->available = $available;
        if (isset($data->visible)) {
            $program_todb->visible = $data->visible;
        }
        if (isset($data->audiencevisible)) {
            $program_todb->audiencevisible = $data->audiencevisible;
        }
        // Text editor fields will be updated later.
        $program_todb->summary = '';
        $program_todb->endnote ='';
        $newid = 0;

        $transaction = $DB->start_delegated_transaction();
        // Set up the program
        $newid = $DB->insert_record('prog', $program_todb);
        $program = new program($newid);
        $transaction->allow_commit();

        $data->id = $newid;
        customfield_save_data($data, 'program', 'prog');

        // Create message manager to add default messages.
        $messagemanager = new prog_messages_manager($newid, true);

        $editoroptions = $TEXTAREA_OPTIONS;
        $editoroptions['context'] = context_program::instance($newid);

        $data = file_postupdate_standard_editor($data, 'summary', $editoroptions, $editoroptions['context'], 'totara_program', 'summary', 0);
        $data = file_postupdate_standard_editor($data, 'endnote', $editoroptions, $editoroptions['context'], 'totara_program', 'endnote', 0);
        if ($overviewfilesoptions = prog_program_overviewfiles_options($newid)) {
            // Save the course overviewfiles
            $data = file_postupdate_standard_filemanager($data, 'overviewfiles', $overviewfilesoptions, $editoroptions['context'], 'totara_program', 'overviewfiles', 0);
        }
        $DB->set_field('prog', 'summary', $data->summary, array('id' => $newid));
        $DB->set_field('prog', 'endnote', $data->endnote, array('id' => $newid));

        // Visible audiences.
        if (!empty($CFG->audiencevisibility)) {
            $visiblecohorts = totara_cohort_get_visible_learning($newid, $instancetype);
            $visiblecohorts = !empty($visiblecohorts) ? $visiblecohorts : array();
            $newvisible = !empty($data->cohortsvisible) ? explode(',', $data->cohortsvisible) : array();
            if ($todelete = array_diff(array_keys($visiblecohorts), $newvisible)) {
                // Delete removed cohorts.
                foreach ($todelete as $cohortid) {
                    totara_cohort_delete_association($cohortid, $visiblecohorts[$cohortid]->associd,
                                                     $instancetype, COHORT_ASSN_VALUE_VISIBLE);
                }
            }

            if ($newvisible = array_diff($newvisible, array_keys($visiblecohorts))) {
                // Add new cohort associations.
                foreach ($newvisible as $cohortid) {
                    totara_cohort_add_association($cohortid, $newid, $instancetype, COHORT_ASSN_VALUE_VISIBLE);
                }
            }
        }

        add_to_log(SITEID, 'program', 'created', "edit.php?id={$newid}", $program->fullname);

        // take them straight to edit page if they have permissions,
        // otherwise view the program
        $programcontext = context_program::instance($newid);
        if (has_capability('totara/program:configuredetails', $programcontext)) {
            $viewurl = "{$CFG->wwwroot}/totara/program/edit.php?id={$newid}&amp;action=edit";
        } else {
            $viewurl = "{$CFG->wwwroot}/totara/program/view.php?id={$newid}";
        }

        // Certification
        $newcertid = 0;
        if ($data->iscertif) {
            $certification_todb = new stdClass;
            $certification_todb->learningcomptype = CERTIFTYPE_PROGRAM;
            $certification_todb->activeperiod = '1 year';
            $certification_todb->windowperiod = '1 month';
            $certification_todb->recertifydatetype = CERTIFRECERT_EXPIRY;
            $certification_todb->timemodified = time();

            // TODO move to prog transaction?
            $transaction = $DB->start_delegated_transaction();

            // Set up the certification
            $newcertid = $DB->insert_record('certif', $certification_todb);
            $DB->set_field('prog', 'certifid', $newcertid , array('id' => $newid));

            $transaction->allow_commit();

            add_to_log(SITEID, 'certification', 'created', "edit.php?id={$newid}", '');

            if (has_capability('totara/certification:configuredetails', $programcontext)) {
                $viewurl = "{$CFG->wwwroot}/totara/program/edit.php?id={$newid}";
            } else {
                $viewurl = "{$CFG->wwwroot}/totara/program/view.php?id={$newid}";
            }

            $successmsg = get_string('certifprogramcreatesuccess', 'totara_certification');
        } else {
            $successmsg = get_string('programcreatesuccess', 'totara_program');
        }

        // Call prog_fix_program_sortorder to ensure new program is displayed properly and the counts are updated.
        // Needs to be called at the very end!
        prog_fix_program_sortorder($data->category);

        $event = \totara_program\event\program_created::create(
            array(
                'objectid' => $newid,
                'context' => context_program::instance($newid),
                'userid' => $USER->id,
                'other' => array(
                    'certifid' => $newcertid,
                ),
            )
        );
        $event->trigger();

      totara_set_notification($successmsg, $viewurl, array('class' => 'notifysuccess'));
    }
}

///
/// Display
///
if (!$iscertif) {
    $heading = get_string('createnewprogram', 'totara_program');
    $pagetitle = format_string(get_string('program', 'totara_program').': '.$heading);
} else {
    $heading = get_string('createnewcertifprog', 'totara_certification');
    $pagetitle = format_string(get_string('certifprog', 'totara_certification').': '.$heading);
}

prog_add_base_navlinks();
$PAGE->navbar->add($heading);

echo $OUTPUT->header();

echo $OUTPUT->container_start('program add', 'program-add');

$context = context_coursecat::instance($category->id);
$exceptions = 0;
echo $OUTPUT->heading($heading);

require('tabs.php');

$form->display();

echo $OUTPUT->container_end();

echo $OUTPUT->footer();
