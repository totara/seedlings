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
 * @author Ciaran Irvine <ciaran.irvine@totaralms.com>
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

// Load parameters and objects required for checking permissions.
$subjectid = optional_param('subjectid', $USER->id, PARAM_INT);
$role = optional_param('role', appraisal::ROLE_LEARNER, PARAM_INT);
if ($role == 0) {
    $role = appraisal::ROLE_LEARNER;
}
$latest = optional_param('latest', null, PARAM_INT);
if ($latest) {
    $appraisals = appraisal::get_user_appraisals($subjectid, $role);
    $appraisalid = reset($appraisals)->id;
} else {
    $appraisalid = required_param('appraisalid', PARAM_INT);
}
$appraisal = new appraisal($appraisalid);
$preview = optional_param('preview', null, PARAM_INT);
$action = optional_param('action', 'stages', PARAM_ACTION);

// Check that the subject/role are valid in the given appraisal.
$roleassignment = appraisal_role_assignment::get_role($appraisal->id, $subjectid, $USER->id, $role, $preview);
if (!$appraisal->can_access($roleassignment)) {
    throw new moodle_exception('error:cannotaccessappraisal');
}

// Set system context.
$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);

// If viewing pages then load the current page information and process submitted data.
if ($action == 'pages') {

    // Load the pages that can be viewed at this stage in this role.
    $page = null;
    $pageid = optional_param('pageid', null, PARAM_INT);
    if ($preview) {
        if (isset($pageid)) {
            $page = new appraisal_page($pageid);
        }
        $activestageid = required_param('stageid', PARAM_INT);
        $roleassignment->set_previewstageid($activestageid);
    } else {
        $userassignment = $roleassignment->get_user_assignment();
        $activestageid = $userassignment->activestageid;
    }
    $visiblepages = appraisal_page::get_applicable_pages($activestageid, $role);

    if (!empty($visiblepages)) {

        // Determine the page id.
        if (empty($pageid) || empty($visiblepages[$pageid])) {
            // Page not specified or is invalid for this user.
            if (!empty($roleassignment->activepageid)) {
                // Use activepageid.
                $pageid = $roleassignment->activepageid;
            } else {
                // Use first page on last page's stage.
                $laststageid = end($visiblepages)->appraisalstageid;
                foreach ($visiblepages as $visiblepage) {
                    if ($visiblepage->appraisalstageid == $laststageid) {
                        $pageid = $visiblepage->id;
                        break;
                    }
                }
            }
        }
        // At this point we are guaranteed to have a pageid that the role can view.

        $page = $visiblepages[$pageid];

        // If activepageid is not set then set it (temporarily) to first page on last stage.
        if (empty($roleassignment->activepageid)) {
            $laststageid = end($visiblepages)->appraisalstageid;
            foreach ($visiblepages as $visiblepage) {
                if ($visiblepage->appraisalstageid == $laststageid) {
                    $roleassignment->activepageid = $visiblepage->id;
                    break;
                }
            }
        }
        // At this point we are guaranteed that roleassignment->activepageid is set.

        // Load form.
        $otherassignments = $appraisal->get_all_assignments($subjectid, $preview);
        unset($otherassignments[$roleassignment->appraisalrole]);
        $islastpage = ($page == end($visiblepages));
        $form = new appraisal_answer_form(null, array('appraisal' => $appraisal, 'page' => $page,
            'roleassignment' => $roleassignment, 'otherassignments' => $otherassignments,
            'action' => $action, 'preview' => $preview, 'islastpage' => $islastpage), 'post', '', null, true, 'appraisalanswers');

        // We only deal with form data if it is not preview (can only be draft if it is also preview).
        if (!$preview) {

            $formissubmitted = $form->is_submitted();

            if (!$formissubmitted) {
                // They have just loaded this page, so load previous answers.
                $form->set_data($appraisal->get_answers($page->id, $roleassignment));

            } else {
                $formisvalid = $form->is_validated(); // Load the form data.
                $formiscancelled = $form->is_cancelled();
                $answers = $form->get_submitted_data(); // Get the data, even if invalid.

                // Only save the data if it is valid or if it is the active page and the user has clicked "Save progress".
                if (($answers->submitaction == 'saveprogress') && ($roleassignment->activepageid == $pageid)) {
                    /* User clicked "Save progress" on the active page, so save data (without completing stage, even if valid),
                     * notify user and stay on page. */
                    $appraisal->save_answers($answers, $roleassignment, false);
                    $returnurl = new moodle_url('/totara/appraisal/myappraisal.php', array('role' => $role,
                        'subjectid' => $subjectid, 'appraisalid' => $appraisalid, 'action' => $action, 'pageid' => $pageid));
                    totara_set_notification(get_string('progresssaved', 'totara_appraisal'), $returnurl,
                            array('class' => 'notifysuccess'));

                } else {
                    $savebuttonpushed = ($answers->submitaction == 'savechanges' ||
                                         $answers->submitaction == 'next' ||
                                         $answers->submitaction == 'completestage');

                    // We need to check against the stage that this page belongs to, not the activestage.
                    $pageislocked = $page->is_locked($roleassignment);

                    if (!$formisvalid) {
                        totara_set_notification(get_string('error:submitteddatainvalid', 'totara_appraisal'));
                    } else if ($savebuttonpushed && !$pageislocked && $formisvalid && !$formiscancelled) {
                        // Save valid data.
                        if ($appraisal->save_answers($answers, $roleassignment)) {
                            // Save was successful, so go to button destination (next page, appraisal overview or stay on page).
                            if ($answers->submitaction == 'next') {
                                // Load this page again (automatically goes to the current page).
                                redirect(new moodle_url('/totara/appraisal/myappraisal.php', array('role' => $role,
                                        'subjectid' => $subjectid, 'appraisalid' => $appraisalid, 'action' => 'pages')));

                            } else if ($answers->submitaction == 'completestage') {
                                // Notify and go to the stages page.
                                $returnurl = new moodle_url('/totara/appraisal/myappraisal.php', array('role' => $role,
                                    'subjectid' => $subjectid, 'appraisalid' => $appraisalid, 'action' => 'stages'));
                                totara_set_notification(get_string('stagecompleted', 'totara_appraisal'), $returnurl,
                                    array('class' => 'notifysuccess'));

                            } else if ($answers->submitaction == 'savechanges') {
                                // Notify and stay on page.
                                $returnurl = new moodle_url('/totara/appraisal/myappraisal.php', array('role' => $role,
                                    'subjectid' => $subjectid, 'appraisalid' => $appraisalid, 'action' => $action,
                                    'pageid' => $pageid));
                                totara_set_notification(get_string('changessaved', 'totara_appraisal'), $returnurl,
                                    array('class' => 'notifysuccess'));
                            }
                        }
                    }
                }
            }

            // Load the other answers.
            foreach ($otherassignments as $otherassignment) {
                $form->set_data($appraisal->get_answers($page->id, $otherassignment));
            }
        }
    }
} else if ($action == 'keepalive') {
    echo 'success';
    return;
}

// Include JS file.
// Setup custom javascript.
local_js(array(
    TOTARA_JS_DIALOG));
$jsmodule = array(
    'name' => 'totara_appraisal_myappraisal',
    'fullpath' => '/totara/appraisal/js/myappraisal.js',
    'requires' => array('json'));
$PAGE->requires->strings_for_js(array('printnow', 'printyourappraisal', 'snapshotgeneration', 'downloadnow',
    'snapshotdialogtitle'), 'totara_appraisal');
$PAGE->requires->js_init_call('M.totara_appraisal_myappraisal.init',  array('args' => json_encode(array(
    'appraisalid' => $appraisal->id, 'role' => $role, 'subjectid' => $subjectid,
    'keepalivetime' => ($CFG->sessiontimeout / 2)))), false, $jsmodule);

// Start page output.
$urlparams = array('role' => $role, 'subjectid' => $subjectid, 'appraisalid' => $appraisalid, 'action' => 'stages');
if ($preview) {
    $urlparams['preview'] = $preview;
}
$pageurl = new moodle_url('/totara/appraisal/myappraisal.php', $urlparams);
$PAGE->set_totara_menu_selected('appraisals');
if ($role == appraisal::ROLE_LEARNER) {
    $PAGE->navbar->add(get_string('myappraisals', 'totara_appraisal'), new moodle_url('/totara/appraisal/index.php'));
} else {
    $PAGE->navbar->add(get_string('myteamappraisals', 'totara_appraisal'),
            new moodle_url('/totara/appraisal/index.php', array('role' => $role)));
}
$PAGE->navbar->add($appraisal->name, $pageurl);
$PAGE->set_url($pageurl);
if ($preview) {
    $PAGE->set_pagelayout('popup');
} else {
    $PAGE->set_pagelayout('noblocks');
}
$heading = get_string('myappraisals', 'totara_appraisal');
$renderer = $PAGE->get_renderer('totara_appraisal');
$PAGE->set_title($heading);
$PAGE->set_heading(format_string($SITE->fullname));
echo $renderer->header();

// Output special headers.
if ($preview) {
    $urlparams = array('subjectid' => $subjectid, 'appraisalid' => $appraisalid, 'action' => $action, 'preview' => $preview);
    if (isset($activestageid)) {
        $urlparams['stageid'] = $activestageid;
    }
    if (isset($pageid)) {
        $urlparams['pageid'] = $pageid;
    }
    echo $renderer->display_preview_header($appraisal, $role, $urlparams);
} else if ($subjectid != $USER->id) {
    $subject = $DB->get_record('user', array('id' => $subjectid));
    echo $renderer->display_viewing_appraisal_header($subject);
}

// Output page content.
if ($action == 'stages') {
    $usercontext = context_user::instance($subjectid);
    if ($subjectid == $USER->id) {
        $showprint = has_capability('totara/appraisal:printownappraisals', $systemcontext);
    } else {
        $showprint = has_capability('totara/appraisal:printstaffappraisals', $usercontext);
    }

    $stages = appraisal_stage::get_stages($appraisalid);
    foreach ($stages as $stage) {
        $pages = appraisal_page::get_applicable_pages($stage->id, $role, 0, false);
        if (!empty($pages)) {
            $firstpage = reset($pages);
            $stage->firstpage = $firstpage->id;
        }
    }
    echo $renderer->display_stages($appraisal, $stages, $roleassignment, $showprint, $preview);
} else {
    if (isset($form)) {
        echo $renderer->display_pages($visiblepages, $page, $roleassignment, $preview, true);
        echo $renderer->container_start('verticaltabtree-content');
        $form->display();
        echo $renderer->container_end();
        echo $renderer->container_end(); // This is supposed to be here twice.
    } else {
        echo $renderer->display_pages($visiblepages, $page, $roleassignment, $preview);
    }
}

// End page output.
echo $renderer->footer();
