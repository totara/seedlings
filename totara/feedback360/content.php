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
 * @subpackage totara_feedback360
 */
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/totara/feedback360/lib.php');
require_once($CFG->dirroot . '/totara/feedback360/feedback360_forms.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');

// Check if 360 Feedbacks are enabled.
feedback360::check_feature_enabled();

$questionid = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ACTION);

$question = new feedback360_question($questionid);
if ($question->id > 0) {
    $feedback360id = $question->feedback360id;
} else {
    $feedback360id = required_param('feedback360id', PARAM_INT);
}

admin_externalpage_setup('managefeedback360');
$systemcontext = context_system::instance();
require_capability('totara/feedback360:managefeedback360', $systemcontext);
require_capability('totara/feedback360:managepageelements', $systemcontext);

$output = $PAGE->get_renderer('totara_feedback360');

$feedback360 = new feedback360($feedback360id);
$isdraft = feedback360::is_draft($feedback360);

$mnewform = new feedback360_add_quest_form(null, array('feedback360id' => $feedback360->id));
$returnurl = new moodle_url('/totara/feedback360/content.php', array('feedback360id' => $feedback360->id));


switch ($action) {
    case 'pos':
        $pos = required_param('pos', PARAM_INT);
        feedback360_question::reorder($question->id, $pos);
        if (is_ajax_request($_SERVER)) {
            return;
        }
        totara_set_notification(get_string('feedback360updated', 'totara_feedback360'), $returnurl,
                array('class' => 'notifysuccess'));
    case 'posup':
        feedback360_question::reorder($question->id, $question->sortorder - 1);
        totara_set_notification(get_string('feedback360updated', 'totara_feedback360'), $returnurl,
                array('class' => 'notifysuccess'));
        break;
    case 'posdown':
        feedback360_question::reorder($question->id, $question->sortorder + 1);
        totara_set_notification(get_string('feedback360updated', 'totara_feedback360'), $returnurl,
                array('class' => 'notifysuccess'));
        break;
    case 'edit':
        if ($mnewform->is_submitted()) {
            $newelement = $mnewform->get_data();
            if (!$newelement) {
                totara_set_notification(get_string('error:choosedatatype', 'totara_question'), $returnurl,
                        array('class' => 'notifyproblem'));
            }
        }

        // Set element.
        if ($question->id < 1) {
            $question->attach_element(required_param('datatype', PARAM_ACTION));
        }

        $header = question_base_form::get_header($question->get_element());
        $preset = array('question' => $question, 'feedback360id' => $feedback360->id, 'readonly' => !$isdraft);

        // Element edit form.
        $meditform = new feedback360_quest_edit_form(null, $preset);
        $meditform->set_header($header);
        if ($meditform->is_cancelled()) {
            redirect($returnurl);
        }
        if ($isdraft && $fromform = $meditform->get_data()) {
            $question->set($fromform)->save();
            if (is_ajax_request($_SERVER)) {
                ajax_result();
                return;
            } else {
                totara_set_notification(get_string('contentupdated', 'totara_feedback360'), $returnurl,
                        array('class' => 'notifysuccess'));
            }
        } else {
            $meditform->set_data($question->get(true));
        }
        break;
    case 'delete':
        if ($question->id < 1) {
            totara_set_notification(get_string('error:elementnotfound', 'totara_question'), $returnurl,
                    array('class' => 'notifyproblem'));
            return;
        }
        $confirm = optional_param('confirm', 0, PARAM_INT);
        if ($confirm == 1) {
            feedback360_question::delete($question->id);
            if (is_ajax_request($_SERVER)) {
                echo 'success';
                return;
            }
            totara_set_notification(get_string('deletedquestion', 'totara_question'), $returnurl,
                    array('class' => 'notifysuccess'));
        }
        break;
    case 'clone':
        $question->duplicate($feedback360->id);
    default:
        $questions = feedback360_question::get_list($feedback360->id);
        if ($isdraft) {
            local_js();

            $jsmodule = array(
                'name' => 'totara_feedback360_content',
                'fullpath' => '/totara/feedback360/js/content.js',
                'requires' => array('json'));

            $PAGE->requires->js_init_call('M.totara_feedback360_content.init', array(), false, $jsmodule);
        }
}

echo $output->header();
echo $output->heading($feedback360->name);
echo $output->feedback360_additional_actions($feedback360->status, $feedback360->id);

echo $output->feedback360_management_tabs($feedback360->id, 'content');

switch ($action) {
    case 'delete':
        echo $output->confirm_question_delete($question);
        break;
    case 'edit':
        $meditform->display();
        break;
    default:
        echo html_writer::start_tag('div', array('class' => 'quest-container'));

        // Only let them add new questions if the feedback hasn't been activated yet.
        if ($isdraft) {
            $mnewform->display();
        } else {
            echo html_writer::empty_tag('br');
        }

        echo $output->list_questions($questions, $isdraft);
        echo html_writer::end_tag('div');
}
echo $output->footer();
