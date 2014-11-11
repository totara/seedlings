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
 * Program view page
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once 'HTML/QuickForm/Renderer/QuickHtml.php';
require_once($CFG->libdir.'/adminlib.php');
require_once('lib.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');
require_once('edit_content_form.php');

$id = required_param('id', PARAM_INT); // program id
$submitdata = false;

require_login();

$systemcontext = context_system::instance();

// Constructors called in turn are: program, prog_content, prog_courseset, (multi_course_set).
// So all existing data loaded.
$program = new program($id);
$iscertif = $program->certifid ? true : false;
$programcontext = $program->get_context();

// Check if programs or certifications are enabled.
if ($iscertif) {
    check_certification_enabled();
} else {
    check_program_enabled();
}

if (!has_capability('totara/program:configurecontent', $programcontext)) {
    print_error('error:nopermissions', 'totara_program');
}

$PAGE->set_url(new moodle_url('/totara/program/edit_content.php', array('id' => $id)));
$PAGE->set_context($programcontext);
$PAGE->set_title(format_string($program->fullname));
$PAGE->set_heading(format_string($program->fullname));

// Javascript include.
local_js(array(
    TOTARA_JS_DIALOG,
    TOTARA_JS_TREEVIEW
));

$programcontent = $program->get_content();

$currenturl = qualified_me();
$currenturl_noquerystring = strip_querystring($currenturl);
// not used: $viewurl = $currenturl_noquerystring."?id={$id}";

$overviewurl = $CFG->wwwroot."/totara/program/edit.php?id={$id}&action=view";

// if the form has been submitted we need to make sure that the program object
// contains all the submitted data before the form is created and validated as
// the form is defined based on the status of the program object. Nothing is
// saved to the database at this point and the submitted data is only used to
// populate the $program obect.
// This process MUST only READ data from the database and MUST NOT WRITE
// anything as nothing has been checked or validated yet.

if ($rawdata = data_submitted()) {
    require_sesskey();

    // process the submitted elements to create the courseset etc info that is passed on to program_content_edit_form()
    if (!$programcontent->setup_content($rawdata)) {
        print_error('error:unabletosetupprogcontent', 'totara_program');
    }

    global $CERTIFPATHSUF;
    if ($si = get_certification_path_field($rawdata, 'addcontent', 'Add')) {
        $addsuffix = $CERTIFPATHSUF[$si];
    } else {
        $addsuffix = '';
    }
    if ($si = get_certification_path_field($rawdata, 'update', 1)) {
        $updatesuffix = $CERTIFPATHSUF[$si];
    } else {
        $updatesuffix = '';
    }

    if (isset($rawdata->{'addcontent'.$addsuffix})) {
        if (!$programcontent->add_set($rawdata->{'contenttype'.$addsuffix})) {
            echo $OUTPUT->notification(get_string('error:unabletoaddset', 'totara_program'));
        }
    } else if (isset($rawdata->{'update'.$updatesuffix})) {
        $programcontent->update_content();
        echo $OUTPUT->notification(get_string('contentupdatednotsaved', 'totara_program'));
    } else if ($setnumber = $programcontent->check_set_action('delete', $rawdata)) {
        if (!$programcontent->delete_set($setnumber)) {
            echo $OUTPUT->notification(get_string('error:deleteset', 'totara_program'));
        } else {
            $submitdata = true;
        }
    } else if ($setnumber = $programcontent->check_set_action('update', $rawdata)) {
        $programcontent->update_set($setnumber);
    } else if ($setnumber = $programcontent->check_set_action('moveup', $rawdata)) {
        $programcontent->move_set_up($setnumber);
    } else if ($setnumber = $programcontent->check_set_action('movedown', $rawdata)) {
        $programcontent->move_set_down($setnumber);
    } else if ($setnumber = $programcontent->check_set_action('addcourse', $rawdata)) {
        if (!$programcontent->add_course($setnumber, $rawdata)) {
            echo $OUTPUT->notification(get_string('error:setunabletoaddcourse', 'totara_program'));
        } else {
            $submitdata = true;
        }
    } else if ($setnumber = $programcontent->check_set_action('addcompetency', $rawdata)) {
        if (!$programcontent->add_competency($setnumber, $rawdata)) {
            echo $OUTPUT->notification(get_string('error:setunableaddcompetency', 'totara_program'));
        } else {
            $submitdata = true;
        }
    } else if ($action = $programcontent->check_set_action('deletecourse', $rawdata)) {
        if (!$programcontent->delete_course($action->setnumber, $action->courseid, $rawdata)) {
            echo $OUTPUT->notification(get_string('error:setunabletodeletecourse', 'totara_program', $action->setnumber));
        } else {
            $submitdata = true;
        }
    }

}


// create form and populate
$contenteditform = new program_content_edit_form($currenturl, array('program'=>$program), 'post', '', array('name'=>'form_prog_content'));

// this removes the 'mform' class which is set be default on the form and which
// causes problems with the styling
// TODO SCANMSG This may cause issues when styling
//$contenteditform->_form->updateAttributes(array('class' => ''));

if ($contenteditform->is_cancelled()) {
    totara_set_notification(get_string('programupdatecancelled', 'totara_program'), $overviewurl, array('class' => 'notifysuccess'));
}

// if the form has not been submitted, fill in the saved values and defaults
if (!$rawdata) {
    $contenteditform->set_data($programcontent->formdataobject);
}

// This is where we validate and check the submitted data before saving it
if ($data = $contenteditform->get_data()) {
    if (isset($data->savechanges) || $submitdata) {

        // first set up the program content with the validated and checked submitted data
        if (!$programcontent->setup_content($data)) {
            print_error('error:setupprogcontent', 'totara_program');
        }

        // if certif and user want to have the recert path be a copy of the cert path
        // replicate the cert courseset(s) by recreated objects
        if (isset($data->sameascert_rc) && $data->sameascert_rc == 1) {
            $programcontent->copy_coursesets_to_recert($data);
        }

        // Save program content
        if (!$programcontent->save_content()) {
            totara_set_notification(get_string('programupdatefail', 'totara_program'), $currenturl);
        } else {
            // log this request
            add_to_log(SITEID, 'program', 'update content', "edit_content.php?id={$program->id}", $program->fullname);

            $prog_update = new stdClass();
            $prog_update->id = $id;
            $prog_update->timemodified = time();
            $prog_update->usermodified = $USER->id;
            $DB->update_record('prog', $prog_update);

            if (isset($data->savechanges) || $submitdata) {
                totara_set_notification(get_string('programcontentsaved', 'totara_program'),
                    'edit_content.php?id='.$id, array('class' => 'notifysuccess'));
            }
        }
    }
}

// log this request
add_to_log(SITEID, 'program', 'view content', "edit_content.php?id={$program->id}", $program->fullname);

// Display.
$heading = format_string($program->fullname);

if ($iscertif) {
    $heading .= ' ('.get_string('certification', 'totara_certification').')';
}

//Javascript includes
$PAGE->requires->strings_for_js(array('addcourseset', 'addcourses', 'cancel', 'ok', 'addcompetency',
                'addcourse', 'addcourses', 'editcontent', 'saveallchanges', 'confirmcontentchanges',
                'youhaveunsavedchanges', 'youhaveunsavedchanges', 'or', 'and', 'affectedusercount',
                'tosavecontent', 'error:courses_nocourses', 'error:recur_nocourse', 'error:nocompetency'),
            'totara_program');
$selected_addrecurringcourse = json_encode(dialog_display_currently_selected(get_string('selected', 'totara_hierarchy'), 'addrecurringcourse'));
$selected_addcompetency = json_encode(dialog_display_currently_selected(get_string('selected', 'totara_hierarchy'), 'addcompetency'));
$args = array('args'=> '{"id":'.$program->id.','.
                        '"display_selected_addcompetency":'.$selected_addcompetency.','.
                        '"display_selected_addrecurringcourse":'.$selected_addrecurringcourse.','.
                        '"COMPLETIONTYPE_ANY":"'.COMPLETIONTYPE_ANY.'",'.
                        '"CONTENTTYPE_MULTICOURSE":"'.CONTENTTYPE_MULTICOURSE.'",'.
                        '"CONTENTTYPE_COMPETENCY":"'.CONTENTTYPE_COMPETENCY.'",'.
                        '"CONTENTTYPE_RECURRING":"'.CONTENTTYPE_RECURRING.'"}');
$jsmodule = array(
     'name' => 'totara_programcontent',
     'fullpath' => '/totara/program/content/program_content.js',
     'requires' => array('json','event-delegate')
     );
$PAGE->requires->js_init_call('M.totara_programcontent.init', $args, false, $jsmodule);

echo $OUTPUT->header();

echo $OUTPUT->container_start('program content', 'edit-program-content');

echo $OUTPUT->heading($heading);
$renderer = $PAGE->get_renderer('totara_program');
// Display the current status
echo $program->display_current_status();

$exceptions = $program->get_exception_count();
$currenttab = 'content';
require('tabs.php');

// display the curent status and a link to the exceptions report if there are any exceptions

// Display the form
$contenteditform->display();

echo $OUTPUT->container_end();

echo $renderer->get_cancel_button(array('id' => $program->id));

echo $OUTPUT->footer();
