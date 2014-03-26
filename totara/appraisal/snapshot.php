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
require_once($CFG->dirroot . '/totara/appraisal/lib.php');
require_once($CFG->dirroot . '/totara/appraisal/appraisal_forms.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');

// Check if Appraisals are enabled.
appraisal::check_feature_enabled();

require_login();

// Set system context.
$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);

// Load parameters and objects required for checking permissions.
$subjectid = optional_param('subjectid', $USER->id, PARAM_INT);
$role = optional_param('role', appraisal::ROLE_LEARNER, PARAM_INT);
if ($role == 0) {
    $role = appraisal::ROLE_LEARNER;
}
$roles = appraisal::get_roles();


$appraisalid = required_param('appraisalid', PARAM_INT);
$spaces = optional_param('spaces', 0, PARAM_INT);

// We expect array of stages.
$stageschecked = (isset($_REQUEST['stages']) && is_array($_REQUEST['stages'])) ? $_REQUEST['stages'] : array();
$printstages = array_keys(array_filter($stageschecked));
$action = optional_param('action', '', PARAM_ACTION);

if ($action == 'stages') {
    if ($subjectid == $USER->id) {
        require_capability('totara/appraisal:printownappraisals', $systemcontext);
    } else {
        $usercontext = context_user::instance($subjectid);
        require_capability('totara/appraisal:printstaffappraisals', $usercontext);
    }
}

if (!is_array($printstages)) {
    throw new appraisal_exception('error:stagesmustbearray');
}

$appraisal = new appraisal($appraisalid);

if ($action == 'stages') {
    // Show dialog box with stages select.
    $stageslist = appraisal_stage::get_stages($appraisal->id, array($role));
    $stagesform = new appraisal_print_stages_form(null, array('appraisalid' => $appraisalid, 'stages' => $stageslist,
        'subjectid' => $subjectid, 'role' => $role), 'post', '', array('id' => 'printform', 'class' => 'print-stages-form'));
    $stagesform->display();
    exit();
}

// Check that the subject/role are valid in the given appraisal.
$roleassignment = appraisal_role_assignment::get_role($appraisal->id, $subjectid, $USER->id, $role);
$userassignment = $roleassignment->get_user_assignment();
if (!$appraisal->can_access($roleassignment)) {
    throw new appraisal_exception('error:cannotaccessappraisal');
}
$assignments = $appraisal->get_all_assignments($subjectid);
$otherassignments = $assignments;
unset($otherassignments[$roleassignment->appraisalrole]);

$PAGE->set_url(new moodle_url('/totara/appraisal/snapshot.php', array('role' => $role,
    'subjectid' => $subjectid, 'appraisalid' => $appraisalid, 'action' => $action)));

$PAGE->set_pagelayout('popup');
$CFG->themeorder = array('default');

$renderer = $PAGE->get_renderer('totara_appraisal');
$PAGE->requires->js_init_code('window.print()', true);
$heading = get_string('myappraisals', 'totara_appraisal');
$PAGE->set_title($heading);
$PAGE->set_heading($heading);
$nouserpic = false;

if ($action == 'snapshot') {
    $ob_was = 0;
    $nouserpic = true;
    $ob_level = ob_get_level();
    // IIS always return true for ob_get_level().
    while ($ob_level != $ob_was && $ob_level > 0) {
        $ob_was = $ob_level;
        ob_flush();
        $ob_level = ob_get_level();
    }
    require_once($CFG->libdir . '/dompdf/lib.php');
    set_time_limit('300');
    ob_start();
}

echo $renderer->header();

// Print appraisal header.
echo $renderer->heading($appraisal->name, 1);
$appdesc = new stdClass();
$appdesc->description = $appraisal->description;
$appdesc->descriptionformat = FORMAT_HTML;
$appdesc = file_prepare_standard_editor($appdesc, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
        'totara_appraisal', 'appraisal', $appraisal->id);
echo $appdesc->description_editor['text'];
$subject = $DB->get_record('user', array('id' => $subjectid));
echo $renderer->display_viewing_appraisal_header($subject, 'youareprintingxsappraisal', $role);
$stageslist = appraisal_stage::get_stages($appraisal->id, array($role));

foreach ($stageslist as $stageid => $stagedata) {
    if (empty($printstages) || in_array($stageid, $printstages)) {
        // Print stage.
        $stage = new appraisal_stage($stageid);
        echo $renderer->display_stage($appraisal, $stage, $userassignment, $roleassignment, '', false);

        $pages = appraisal_page::get_applicable_pages($stageid, $role, 0, false);
        foreach ($pages as $page) {
            // Print page.
            echo $renderer->heading($page->name);

            // Print form.
            $form = new appraisal_answer_form(null, array('appraisal' => $appraisal, 'page' => $page,
            'userassignment' => $userassignment, 'roleassignment' => $roleassignment,
            'otherassignments' => $otherassignments, 'spaces' => $spaces, 'nouserpic' => $nouserpic,
            'action' => 'print', 'preview' => false, 'islastpage' => false, 'readonly' => true));

            foreach ($assignments as $assignment) {
                $form->set_data($appraisal->get_answers($page->id, $assignment));
            }
            if ($action == 'snapshot') {
                $form->display();
            } else {
                $form->display();
            }
            $form->reset_form_sent();
        }
    }
}

if ($action == 'snapshot') {
    $html = ob_get_contents();
    ob_end_clean();
    $filename = 'appraisal_'.$appraisal->id.'_'.date("Y-m-d_His").'_'.$roles[$role].'.pdf';
    $pdf = new totara_dompdf();
    $pdf->load_html($html);
    $pdf->render();

    file_put_contents($CFG->tempdir.'/'.$filename, $pdf->output());

    // Save into db.
    $downloadurl = $appraisal->save_snapshot($CFG->tempdir.'/'.$filename, $roleassignment->id);

    // Message for dialog.
    $strsource = new stdClass();
    $strsource->link = $renderer->action_link(new moodle_url('/totara/appraisal/index.php'),
            get_string('allappraisals', 'totara_appraisal'));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'downloadurl', 'id' => 'downloadurl',
            'value' => $downloadurl));
    echo html_writer::tag('div', get_string('snapshotdone', 'totara_appraisal', $strsource),
            array('class'=>'notifysuccess dialog-nobind'));
} else {
    echo $renderer->footer();
}
