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
global $CFG;
require_once($CFG->dirroot.'/totara/appraisal/lib.php');

abstract class appraisal_testcase extends advanced_testcase {
    /**
     * Prepares appraisal and assign cohort with two users assigned to it
     * Appraisal is neither validated nor activated.
     * @param array $def Optional definition of appraisal
     * @return appraisal
     */
    protected function prepare_appraisal_with_users(array $def = array(), array $users = array()) {
        if (empty($def)) {
            $def = array('name' => 'Appraisal', 'stages' => array(
                array('name' => 'Stage', 'timedue' => time() + 86400, 'pages' => array(
                    array('name' => 'Page', 'questions' => array(
                        array('name' => 'Text', 'type' => 'text', 'roles' => array(appraisal::ROLE_LEARNER => 7))
                    ))
                ))
            ));
        }
        $appraisal1 = appraisal::build($def);
        if (empty($users)) {
            for ($a = 0; $a < 2; $a++) {
                $user = $this->getDataGenerator()->create_user();
                // Set admin as a manager for users
                // By default definiton manager role is not involved.
                $assignment = new position_assignment(array('userid' => $user->id, 'type' => 1));
                $assignment->managerid = 2;
                assign_user_position($assignment, true);
                $users[] = $user;
            }
        }
        $cohort = $this->getDataGenerator()->create_cohort();
        foreach ($users as $user) {
            cohort_add_member($cohort->id, $user->id);
        }
        reset($users);
        // Add cohort to appraisal.
        $urlparams = array('includechildren' => false, 'listofvalues' => array($cohort->id));
        $assign = new totara_assign_appraisal('appraisal', $appraisal1);
        $grouptypeobj = $assign->load_grouptype('cohort');
        $grouptypeobj->handle_item_selector($urlparams);
        return array($appraisal1, $users);
    }

    /**
     * Give answer on question defined in default configuration (@see prepare_appraisal_with_users())
     * @param appraisal $appraisal
     * @param int $userid
     * @param int $id Question id If not set first question will be answered
     * @param string $submitaction optional submit action
     */
    protected function answer_question($appraisal, $roleassignment, $id = 0, $submitaction = '') {
        if (!$id) {
            $stages = appraisal_stage::fetch_appraisal($appraisal->id);
            $pages = appraisal_page::fetch_stage(current($stages)->id);
            $questions = appraisal_question::fetch_page(current($pages)->id);
            $id = current($questions)->id;
        }
        $question = new appraisal_question($id, $roleassignment);
        $field = $question->get_element()->get_prefix_form();

        $answer = new stdClass();
        $answer->$field = 'test';
        $answer->pageid = $question->appraisalstagepageid;
        $update = false;
        if ($submitaction != '') {
            $update = true;
            $answer->submitaction = $submitaction;
        }

        $appraisal->save_answers($answer, $roleassignment, $update);
    }

    /**
     * Map all stages, pages, and questions names to their id's
     * If two items of same type (stages, pages, or questions) has equal name, only last one will be mapped
     *
     * @param appraisal $appraisal
     * @return appraisal
     */
    protected function map($appraisal) {
        $map = array('stages' => array(), 'pages' => array(), 'questions' => array());
        $stages = appraisal_stage::fetch_appraisal($appraisal->id);
        foreach ($stages as $stage) {
            $map['stages'][$stage->name] = $stage->id;
            $pages = appraisal_page::fetch_stage($stage->id);
            foreach ($pages as $page) {
                $map['pages'][$page->name] = $page->id;
                $questions = appraisal_question::fetch_page($page->id);
                foreach ($questions as $question) {
                    $map['questions'][$question->name] = $question->id;
                }
            }
        }

        return $map;
    }
}
