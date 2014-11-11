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
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage totara_appraisal
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/totara/appraisal/lib.php');
require_once($CFG->dirroot . '/totara/appraisal/appraisal_forms.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');

// Check if Appraisals are enabled.
appraisal::check_feature_enabled();

admin_externalpage_setup('manageappraisals');
$systemcontext = context_system::instance();
require_capability('totara/appraisal:managepageelements', $systemcontext);

$action = optional_param('action', '', PARAM_ACTION);
$appraisalid = optional_param('appraisalid', 0, PARAM_INT);
$id = optional_param('id', 0, PARAM_INT); // Stage id.
$stage = null;
if ($appraisalid < 1) {
    if ($id < 1) {
        throw new appraisal_exception('Stage not found', 23);
    }
    $stage = new appraisal_stage($id);
    $appraisalid = $stage->appraisalid;
}
$appraisal = new appraisal($appraisalid);
if (!$stage) {
    $stage = new appraisal_stage($id);
}
$isdraft = appraisal::is_draft($appraisal);

$returnurl = new moodle_url('/totara/appraisal/stage.php', array('appraisalid' => $appraisalid));

switch ($action) {
    case 'edit':
        $stage = new appraisal_stage($id);
        $defaults = $stage->get();
        $defaults->appraisalid = $appraisalid;
        $defaults->descriptionformat = FORMAT_HTML;
        $defaults = file_prepare_standard_editor($defaults, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
            'totara_appraisal', 'appraisal_stage', $id);
        $mform = new appraisal_stage_edit_form(null, array('action'=>$action, 'stage' => $defaults, 'readonly' => !$isdraft));
        if ($mform->is_cancelled()) {
            redirect($returnurl);
        }
        if ($isdraft && $fromform = $mform->get_data()) {
            if (!confirm_sesskey()) {
                print_error('confirmsesskeybad', 'error');
            }
            if (empty($fromform->submitbutton)) {
                totara_set_notification(get_string('error:unknownbuttonclicked', 'totara_appraisal'), $returnurl);
            }
            if (!empty($fromform->timedue)) {
                // Set date to end-of-day.
                $fromform->timedue += ((int)$fromform->timedue > 0 ? (DAYSECS - 1) : 0);
                if ($fromform->timedue < time()) {
                    totara_set_notification(get_string('error:completebyinvalid', 'totara_appraisal'));
                    break;
                }
            }
            $stage->set($fromform);
            if ($stage->id < 1) {
                $stage->save();
            }
            $fromform = file_postupdate_standard_editor($fromform, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
                'totara_appraisal', 'appraisal_stage', $stage->id);

            $stage->description = $fromform->description;
            $stage->save();

            // Create any pages that have been specified.
            if (isset($fromform->stageinitialpagetitles)) {
                $newpagenames = explode("\n", clean_param($fromform->stageinitialpagetitles, PARAM_TEXT));
                foreach ($newpagenames as $newpagename) {
                    if (trim($newpagename)) {
                        $newpage = new appraisal_page(0);
                        $todb = new stdClass();
                        $todb->appraisalstageid = $stage->id;
                        $todb->name = $newpagename;
                        $newpage->set($todb)->save();
                    }
                }
            }

            add_to_log(SITEID, 'appraisal', 'update stage', 'stage.php?appraisalid='.$appraisalid.'&action=stageedit&id='
                    .$id, 'General Settings: Appraisal ID=' . $appraisalid);
            totara_set_notification(get_string('stageupdated', 'totara_appraisal'), $returnurl,
                    array('class' => 'notifysuccess'));
        }
        break;
    case 'delete':
        if ($stage->id < 1) {
            totara_set_notification(get_string('error:stagenotfound', 'totara_appraisal'), $returnurl,
                    array('class' => 'notifyproblem'));
        }
        if ($isdraft) {
            $confirm = optional_param('confirm', 0, PARAM_INT);
            if ($confirm == 1) {
                if (!confirm_sesskey()) {
                    print_error('confirmsesskeybad', 'error');
                }
                $stage->delete();
                totara_set_notification(get_string('deletedstage', 'totara_appraisal'), $returnurl,
                        array('class' => 'notifysuccess'));
            }
        } else {
            totara_set_notification(get_string('error:appraisalmustdraft', 'totara_appraisal'), $returnurl,
                    array('class' => 'notifyproblem'));
        }
        break;
    default:
        $stages = appraisal_stage::get_list($appraisalid);
        $pages = array();
        $stage = null;

        if ($stages && count($stages)) {
            if (!$id) {
                $id = current($stages)->id;
            }
            $stage = new appraisal_stage($id);

            if (isset($stage)) {
                $pages = appraisal_page::fetch_stage($stage->id);
                if ($stage->appraisalid != $appraisalid) {
                    throw new appraisal_exception('Stage must be within current appraisal');
                }
            }
        }
        break;
}

$output = $PAGE->get_renderer('totara_appraisal');
$title = $PAGE->title . ': ' . $appraisal->name;
$PAGE->set_title($title);
$PAGE->set_heading($appraisal->name);
$PAGE->navbar->add($appraisal->name);

local_js();

$PAGE->requires->string_for_js('cancel', 'moodle');
$PAGE->requires->string_for_js('ok', 'moodle');
$PAGE->requires->string_for_js('yes', 'moodle');
$PAGE->requires->string_for_js('no', 'moodle');
$PAGE->requires->string_for_js('savechanges', 'moodle');
$PAGE->requires->string_for_js('confirmdeleteitem', 'totara_appraisal');
$PAGE->requires->string_for_js('confirmdeleteitemwithredisplay', 'totara_appraisal');
$PAGE->requires->string_for_js('error:cannotdelete', 'totara_appraisal');
$PAGE->requires->string_for_js('addpage', 'totara_appraisal');
$jsmodule = array(
    'name' => 'totara_appraisal_stage',
    'fullpath' => '/totara/appraisal/js/stage.js',
    'requires' => array('json'));

$args = array('args' => '{"sesskey":"'.sesskey().'"}');
$PAGE->requires->js_init_call('M.totara_appraisal_stage.init', $args, false, $jsmodule);

// Include tinymce in the page if required so it is available inside
// question dialog.
$editor = editors_get_preferred_editor(FORMAT_HTML);
if (($editor instanceof tinymce_texteditor)) {
    $filename = $CFG->debugdeveloper ? 'tiny_mce_src.js' : 'tiny_mce.js';
    $PAGE->requires->js(new moodle_url($CFG->httpswwwroot.'/lib/editor/tinymce/tiny_mce/'.$editor->version.'/' . $filename));
}

echo $output->header();
echo $output->heading($appraisal->name);
echo $output->appraisal_additional_actions($appraisal->status, $appraisal->id);

echo $output->appraisal_management_tabs($appraisal->id, 'content');

switch ($action) {
    case 'delete':
        echo $output->heading(get_string('deletestage', 'totara_appraisal', $stage->name),3);
        echo $output->confirm_delete_stage($stage->id);
        break;
    case 'edit':
        echo $mform->display();
        break;
    default:
        echo $output->heading(get_string('stages', 'totara_appraisal'), 3);
        echo $output->create_stage_button($appraisal);
        echo $output->appraisal_stages_table($stages, 0, $id);
        echo $output->stage_page_container($stage, $pages);
        break;
}

echo $output->footer();
