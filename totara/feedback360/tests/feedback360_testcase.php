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
 * @subpackage feedback360
 */
require_once($CFG->dirroot.'/totara/feedback360/lib.php');

abstract class feedback360_testcase extends advanced_testcase {
    /**
     * Creates new feedback and assign one user on it.
     * @param array|int $users
     * @param int $quest Create question
     *
     * @return array(feedback360 $feedback360, array $users)
     */
    protected function prepare_feedback_with_users($users = array(), $quest = 1) {
        $feedback360 = new feedback360();
        $feedback360->name = 'Feedback';
        $feedback360->description = 'Description';
        $feedback360->save();

        // Add question.
        $quests = array();
        if ($quest) {
            for ($b = 1; $b <= $quest; $b++) {
                $question = new feedback360_question();
                $question->feedback360id = $feedback360->id;
                $question->attach_element('text');
                $question->name = 'Text'.$b;
                $question->save();
                $quests[$question->name] = $question;
            }
        }

        // Add user.
        $num = 1;
        if (!is_array($users)) {
            $num = intval($users);
            $users = array();
        }
        if (empty($users)) {
            for ($a = 0; $a < $num; $a++) {
                $user = $this->getDataGenerator()->create_user();
                $users[] = $user;
            }
        }
        $cohort = $this->getDataGenerator()->create_cohort();
        foreach ($users as $user) {
            cohort_add_member($cohort->id, $user->id);
        }
        reset($users);
        // Add cohort to appraisal.
        $listofvalues = array($cohort->id);
        $urlparams = array('module' => 'feedback360', 'grouptype' => 'cohort', 'itemid' => $feedback360->id, 'add' => true,
            'includechildren' => false, 'listofvalues' => $listofvalues);
        $assign = new totara_assign_feedback360('feedback360', $feedback360);
        $grouptypeobj = $assign->load_grouptype('cohort');
        $grouptypeobj->validate_item_selector(implode(',', $listofvalues));
        $grouptypeobj->handle_item_selector($urlparams);
        return array($feedback360, $users, $quests);
    }

    /**
     * Assign response user to user feedback360
     * @param feedback360 $feedbackid
     * @param int $assigneduserid user id assigned to feedback (not response)
     * @param int $respuser user that should be assigned
     * @return feedback360_responder object
     */
    public function assign_resp(feedback360 $feedback, $assigneduserid, $respuserid = 0) {
        global $DB;

        if ($feedback->status != feedback360::STATUS_ACTIVE) {
            $feedback->activate();
        }
        if (!$respuserid) {
            $respuser = $this->getDataGenerator()->create_user();
            $respuserid = $respuser->id;
        }
        $userassignment = $DB->get_record('feedback360_user_assignment', array('feedback360id' => $feedback->id,
            'userid' => $assigneduserid));
        feedback360_responder::update_system_assignments(array($respuserid), array(), $userassignment->id, time());
        $this->assertDebuggingCalled();
        return feedback360_responder::by_user($respuserid, $feedback->id, $assigneduserid);
    }
}
