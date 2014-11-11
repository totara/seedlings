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
 * @package totara_feedback360
 */

/**
 * View answer on feedback360
 */
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');
require_once($CFG->dirroot . '/totara/feedback360/lib.php');
require_once($CFG->dirroot . '/totara/feedback360/feedback360_forms.php');

// Check if 360 Feedbacks are enabled.
feedback360::check_feature_enabled();

$preview = optional_param('preview', 0, PARAM_INT);
$viewanswer = optional_param('myfeedback', 0, PARAM_INT);
$returnurl = new moodle_url('/totara/feedback360/index.php');

$token = optional_param('token', '', PARAM_ALPHANUM);
$isexternaluser = ($token != '');

if (!$isexternaluser) {
    require_login();
    if (isguestuser()) {
        $SESSION->wantsurl = qualified_me();
        redirect(get_login_url());
    }
}

// Get response assignment object, and check who is viewing the page.
$viewasown = false;
if ($isexternaluser) {
    // Get the user's email address from the token.
    $email = $DB->get_field('feedback360_email_assignment', 'email', array('token' => $token));
    $respassignment = feedback360_responder::by_email($email, $token);
    $returnurl = new moodle_url('/totara/feedback360/feedback.php', array('token' => $token));
    if ($respassignment) {
        $respassignment->tokenaccess = true;
    }

} else if ($preview) {
    $feedback360id = required_param('feedback360id', PARAM_INT);

    $systemcontext = context_system::instance();
    $canmanage = has_capability('totara/feedback360:managefeedback360', $systemcontext);
    $assigned = feedback360::has_user_assignment($USER->id, $feedback360id);
    $manager = feedback360::check_managing_assigned($feedback360id, $USER->id);

    if ($assigned) {
        require_capability('totara/feedback360:manageownfeedback360', $systemcontext);
        $viewasown = true;
    }

    if (!empty($manager)) {
        $usercontext = context_user::instance($manager[0]); // Doesn't matter which user, just check one.
        require_capability('totara/feedback360:managestafffeedback', $usercontext);
    }

    if (!$canmanage && !$assigned && empty($manager)) {
        print_error('error:previewpermissions', 'totara_feedback360');
    }

    $respassignment = feedback360_responder::by_preview($feedback360id);
} else if ($viewanswer) {
    $responseid = required_param('responseid', PARAM_INT);
    $respassignment = new feedback360_responder($responseid);

    if ($respassignment->subjectid != $USER->id) {
        // If you arent the owner of the feedback request.
        if (totara_is_manager($respassignment->subjectid)) {
            // Or their manager.
            $capability_context = context_user::instance($respassignment->subjectid);
            require_capability('totara/feedback360:viewstaffreceivedfeedback360', $capability_context);
        } else if (!is_siteadmin()) {
            // Or a site admin, then you shouldnt see this page.
            throw new feedback360_exception('error:accessdenied');
        }
    } else {
        $systemcontext = context_system::instance();
        require_capability('totara/feedback360:viewownreceivedfeedback360', $systemcontext);
        // You are the owner of the feedback request.
        $viewasown = true;
    }

    // You are viewing something that hasn't been viewed, mark it as viewed.
    if (!$respassignment->viewed) {
        $respassignment->viewed = true;
        $respassignment->save();
    }
} else {
    $feedback360id = required_param('feedback360id', PARAM_INT);
    $subjectid = required_param('userid', PARAM_INT);
    $viewas = optional_param('viewas', $USER->id, PARAM_INT);

    // If you aren't the owner of the response.
    if ($viewas != $USER->id) {
        if (totara_is_manager($viewas)) {
            // You are a manager viewing your team members responses to someone else, you need to view staff feedback capability.
            $usercontext = context_user::instance($viewas);
            require_capability('totara/feedback360:viewstaffrequestedfeedback360', $usercontext);
        } else {
            // Otherwise you shouldn't be viewing this page.
            print_error('error:accessdenied');
        }
    } else {
        $viewasown = true;
    }
    $respassignment = feedback360_responder::by_user($viewas, $feedback360id, $subjectid);
}

if (!$respassignment) {
    totara_set_notification(get_string('feedback360notfound', 'totara_feedback360'),
            new moodle_url('/totara/feedback360/index.php'), array('class' => 'notifyproblem'));
}

// Set up the page.
$pageurl = new moodle_url('/totara/feedback360/index.php');
$PAGE->set_context(null);
$PAGE->set_url($pageurl);

if ($preview || $isexternaluser) {
    $PAGE->set_pagelayout('popup');
} else {
    $PAGE->set_pagelayout('noblocks');
}

if ($isexternaluser) {
    $heading = get_string('feedback360', 'totara_feedback360');

    $PAGE->set_title($heading);
    $PAGE->set_heading($heading);
    $PAGE->set_totara_menu_selected('appraisals');
    $PAGE->navbar->add($heading);
    $PAGE->navbar->add(get_string('givefeedback', 'totara_feedback360'));
} else if ($viewasown) {
    $heading = get_string('myfeedback', 'totara_feedback360');

    $PAGE->set_title($heading);
    $PAGE->set_heading($heading);
    $PAGE->set_totara_menu_selected('appraisals');
    $PAGE->navbar->add(get_string('feedback360', 'totara_feedback360'), new moodle_url('/totara/feedback360/index.php'));
    $PAGE->navbar->add(get_string('givefeedback', 'totara_feedback360'));
} else {
    $owner = $DB->get_record('user', array('id' => $respassignment->userid));
    $userxfeedback = get_string('userxfeedback360', 'totara_feedback360', fullname($owner));

    $PAGE->set_title($userxfeedback);
    $PAGE->set_heading($userxfeedback);
    $PAGE->set_totara_menu_selected('myteam');
    $PAGE->navbar->add(get_string('myteam', 'totara_core'), new moodle_url('/my/teammembers.php'));
    $PAGE->navbar->add($userxfeedback);
    $PAGE->navbar->add(get_string('viewresponse', 'totara_feedback360'));
}

$feedback360 = new feedback360($respassignment->feedback360id);

$backurl = null;
if ($viewanswer) {
    $backurl = new moodle_url('/totara/feedback360/request/view.php',
            array('userassignment' => $respassignment->feedback360userassignmentid));
} else if (!empty($viewas)) {
    $backurl = new moodle_url('/totara/feedback360/index.php',
            array('userid' => $viewas));
}
$form = new feedback360_answer_form(null, array('feedback360' => $feedback360, 'resp' => $respassignment, 'preview' => $preview,
        'backurl' => $backurl));

// Process form submission.
if ($form->is_submitted() && !$respassignment->is_completed()) {
    if ($form->is_cancelled()) {
        redirect($returnurl);
    }

    $formisvalid = $form->is_validated(); // Load the form data.
    $answers = $form->get_submitted_data();
    if ($answers->action == 'saveprogress' || ($answers->action == 'submit' && $formisvalid)) {
        // Save.
        $feedback360->save_answers($answers, $respassignment);
        $message = get_string('progresssaved', 'totara_feedback360');
        if ($answers->action == 'submit') {
            // Mark as answered.
            $respassignment->complete();
            $message = get_string('feedbacksubmitted', 'totara_feedback360');
        }
        totara_set_notification($message, $returnurl, array('class' => 'notifysuccess'));
    }
    if ($answers->action == 'submit' && !$formisvalid) {
        totara_set_notification(get_string('error:submitform', 'totara_feedback360'), null, array('class' => 'notifyproblem'));
    }
} else if (!$preview) {
    $form->set_data($feedback360->get_answers($respassignment));
}

// JS support.
local_js();
$jsmodule = array(
    'name' => 'totara_feedback360_feedback',
    'fullpath' => '/totara/feedback360/js/feedback.js',
    'requires' => array('json'));
$PAGE->requires->js_init_call('M.totara_feedback360_feedback.init', array($form->_form->getAttribute('id')),
        false, $jsmodule);

$renderer = $PAGE->get_renderer('totara_feedback360');

echo $renderer->header();

if ($preview) {
    $feedbackname = $DB->get_field_select('feedback360', 'name', 'id = :fbid', array('fbid' => $respassignment->feedback360id));
    echo $renderer->display_preview_feedback_header($respassignment, $feedbackname);
} else {
    $subjectuser = $DB->get_record('user', array('id' => $respassignment->subjectid));
    echo $renderer->display_feedback_header($respassignment, $subjectuser);
}
$form->display();

echo $renderer->footer();
