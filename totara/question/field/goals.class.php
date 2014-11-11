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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage totara_question
 */
global $CFG;
require_once('reviewrating.class.php');
require_once($CFG->dirroot . '/totara/hierarchy/prefix/goal/lib.php');

class question_goals extends reviewrating {

    const SELECT_COMPANY_ADDALL = 1;
    const SELECT_COMPANY_USERCANCHOOSE = 2;
    const SELECT_PERSONAL_ADDALL = 4;
    const SELECT_PERSONAL_USERCANCHOOSE = 8;

    public static function get_info() {
        return array('group' => question_manager::GROUP_REVIEW,
                     'type' => get_string('questiontypegoals', 'totara_question'));
    }

    public function __construct($storage, $subjectid = 0, $answerid = 0) {
        $this->buttonlabel = get_string('choosegoalsreview', 'totara_question');

        parent::__construct($storage, $subjectid, $answerid);
    }

    /**
     * Customfield specific settings elements
     *
     * @param MoodleQuickForm $form
     * @param bool $readonly
     * @param object $moduleinfo
     */
    protected function add_field_specific_settings_elements(MoodleQuickForm $form, $readonly, $moduleinfo) {
        $selection = array();

        // Company.
        $selection[] = $form->createElement('static', 'selectcompanyheader', null,
                html_writer::tag('b', get_string('goalscompany', 'totara_question')));
        $selection[] = $form->createElement('radio', 'selectcompany', null,
                get_string('selectcompanyusercanchoose', 'totara_question'), self::SELECT_COMPANY_USERCANCHOOSE);
        $selection[] = $form->createElement('radio', 'selectcompany', null,
                get_string('selectcompanyaddall', 'totara_question'), self::SELECT_COMPANY_ADDALL);
        $selection[] = $form->createElement('radio', 'selectcompany', null,
                get_string('selectcompanydonotreview', 'totara_question'), 0);

        // Personal.
        $selection[] = $form->createElement('static', 'selectpersonalheader', null,
                html_writer::tag('b', get_string('goalspersonal', 'totara_question')));
        $selection[] = $form->createElement('radio', 'selectpersonal', null,
                get_string('selectpersonalusercanchoose', 'totara_question'), self::SELECT_PERSONAL_USERCANCHOOSE);
        $selection[] = $form->createElement('radio', 'selectpersonal', null,
                get_string('selectpersonaladdall', 'totara_question'), self::SELECT_PERSONAL_ADDALL);
        $selection[] = $form->createElement('radio', 'selectpersonal', null,
                get_string('selectpersonaldonotreview', 'totara_question'), 0);

        $form->addGroup($selection, 'selection', get_string('goalselection', 'totara_question'),
                array('<br/>'), true);
        $form->addRule('selection', null, 'required');

        parent::add_field_specific_settings_elements($form, $readonly, $moduleinfo);
    }

    /**
     * Set saved configuration to form object
     *
     * @param stdClass $toform
     * @return stdClass $toform
     */
    public function define_get(stdClass $toform) {
        parent::define_get($toform);

        if (empty($this->param5)) {
            $this->param5 = self::SELECT_COMPANY_USERCANCHOOSE | self::SELECT_PERSONAL_USERCANCHOOSE;
        }

        $toform->selection['selectcompany'] =
                $this->param5 & (self::SELECT_COMPANY_USERCANCHOOSE | self::SELECT_COMPANY_ADDALL);
        $toform->selection['selectpersonal'] =
                $this->param5 & (self::SELECT_PERSONAL_USERCANCHOOSE | self::SELECT_PERSONAL_ADDALL);

        return $toform;
    }

    /**
     * Set values from configuration form
     *
     * @param stdClass $fromform
     * @return stdClass $fromform
     */
    public function define_set(stdClass $fromform) {
        $fromform = parent::define_set($fromform);

        $this->param5 = (int)$fromform->selection['selectcompany'] |
                        (int)$fromform->selection['selectpersonal'];

        return $fromform;
    }

    /**
     * Validate custom element configuration
     *
     * @param stdClass $data
     * @param array $files
     */
    public function define_validate($data, $files) {
        $err = parent::define_validate($data, $files);

        $selection = (int)$data->selection['selectcompany'] | (int)$data->selection['selectpersonal'];

        if (!$selection) {
            $err['selection'] = get_string('error:allowselectgoals', 'totara_question');
        }

        return $err;
    }

    /**
     * Add form elements that represent current field
     *
     * @see question_base::add_field_specific_edit_elements()
     * @param MoodleQuickForm $form Form to alter
     */
    public function add_field_specific_edit_elements(MoodleQuickForm $form) {
        // Process auto-add of goal items.
        if ($this->can_answer_items()) {
            if ($this->param5 & (self::SELECT_COMPANY_ADDALL | self::SELECT_PERSONAL_ADDALL)) {
                $currentgoals = $this->get_review_items();
                if ($this->param5 & self::SELECT_COMPANY_ADDALL) {
                    $availablecompanygoals = goal::get_goal_items(array('userid' => $this->subjectid), goal::SCOPE_COMPANY);
                    foreach ($availablecompanygoals as $goalrecord) {
                        $needle = new stdClass();
                        $needle->itemid = $goalrecord->id;
                        $needle->scope = goal::SCOPE_COMPANY;
                        $needle->uniquekey = $goalrecord->id . '_' . goal::SCOPE_COMPANY;
                        if (!array_search($needle, $currentgoals)) {
                            // Add a new review data record.
                            $this->prepare_stub($needle);
                        }
                    }
                }
                if ($this->param5 & self::SELECT_PERSONAL_ADDALL) {
                    $availablepersonalgoals = goal::get_goal_items(array('userid' => $this->subjectid), goal::SCOPE_PERSONAL);
                    foreach ($availablepersonalgoals as $goalpersonal) {
                        $needle = new stdClass();
                        $needle->itemid = $goalpersonal->id;
                        $needle->scope = goal::SCOPE_PERSONAL;
                        $needle->uniquekey = $goalpersonal->id . '_' . goal::SCOPE_PERSONAL;
                        if (!array_search($needle, $currentgoals)) {
                            // Add a new review data record.
                            $this->prepare_stub($needle);
                        }
                    }
                }
            }
        }

        $this->add_common_review_edit_elements($form);
    }

    /**
     * Determine if there are any review items that belong to the subject.
     *
     * @return bool
     */
    public function has_review_items() {
        global $DB;

        if ($this->param5 & self::SELECT_COMPANY_USERCANCHOOSE) {
            $companygoals = $DB->get_record('goal_user_assignment', array('userid' => $this->subjectid), '*', IGNORE_MULTIPLE);
        } else {
            $companygoals = false;
        }
        if ($this->param5 & self::SELECT_PERSONAL_USERCANCHOOSE) {
            $personalgoals = goal::get_goal_items(array('userid' => $this->subjectid), goal::SCOPE_PERSONAL);
        } else {
            $personalgoals = array();
        }

        return (!empty($personalgoals) || $companygoals);
    }

    /**
     * Add a rating selector to the form.
     *
     * The select element you define must include classes "rating_selector rating_item_<item-identifier>"
     * so that the ratings of all of the same items on the same page will automatically be updated to keep
     * them in sync. See goals for an example.
     *
     * @param MoodleQuickForm $form
     * @param object $item
     */
    protected function add_rating_selector(MoodleQuickForm $form, $item) {
        global $DB;

        $goalitem = goal::get_goal_item(array('id' => $item->itemid), $item->scope);

        if (empty($goalitem)) {
            return;
        }

        $goal = new goal();
        if (!$permissions = $goal->get_permissions(null, $this->subjectid)) {
            // Error setting up page permissions.
            print_error('error:viewusergoals', 'totara_hierarchy');
        }

        extract($permissions);

        $scalevalueid = $goalitem->scalevalueid;

        if (($scalevalueid == 0) || ($item->scope == goal::SCOPE_PERSONAL && $goalitem->scaleid == 0)) {
            // There is no scale.
            $form->addElement('static', '', get_string('goalstatus', 'totara_question'),
                    html_writer::tag('em', get_string('goalhasnoscale', 'totara_question')));
        } else {
            // Check the appropriate permissions.
            if ($item->scope == goal::SCOPE_PERSONAL) {
                // Check the personal permissions.
                $caneditstatus = $can_edit_personal;

            } else {
                // Check the company permissions.
                $caneditstatus = $can_edit_company;
            }

            // Get scalevalue (includes scaleid).
            $scalevalue = $DB->get_record('goal_scale_values', array('id' => $scalevalueid));

            if (!$this->viewonly && $caneditstatus) {
                $scalevalues = $DB->get_records('goal_scale_values', array('scaleid' => $scalevalue->scaleid));
                $options = array();
                foreach ($scalevalues as $scalevalue) {
                    $options[$scalevalue->id] = format_string($scalevalue->name);
                }
                $name = $this->get_prefix_form() . '_scalevalueid_' . $item->itemid . '_' . $item->scope;
                $form->addElement('select', $name, get_string('goalstatus', 'totara_question'), $options,
                        array('class' => 'rating_selector rating_item_goal_' . $item->itemid . '_' . $item->scope));
                $form->setDefault($name, $scalevalueid);
            } else {
                $form->addElement('static', '', get_string('goalstatus', 'totara_question'), format_string($scalevalue->name));
            }
        }
    }

    /**
     * Get a list of all reviewdata records for this question and subject.
     *
     * @return array of reviewdata records, one per subquestion (scale value) per answerer (role)
     */
    public function get_items() {
        global $DB;

        $module = $this->prefix;
        $relatedanswerids = $module::get_related_answerids($this->answerid);

        if (!empty($relatedanswerids)) {
            list($answerssql, $answerids) = $DB->get_in_or_equal($relatedanswerids);

            /* Note that this join is done in this order so that fullname will be null if the goal_record
             * or goal_personal records have been deleted. */
            $sql = 'SELECT reviewdata.*, item.fullname
                      FROM {'.$this->prefix.'_review_data} reviewdata
                      LEFT JOIN (SELECT gr.id, ? AS scope, g.fullname
                                   FROM {goal_record} gr
                                   JOIN {goal} g
                                     ON gr.goalid = g.id
                                  WHERE gr.userid = ?
                                    AND gr.deleted = 0
                                  UNION
                                 SELECT pg.id, ? AS scope, pg.name AS fullname
                                   FROM {goal_personal} pg
                                  WHERE pg.userid = ?
                                    AND pg.deleted = 0) item
                        ON reviewdata.itemid = item.id
                       AND reviewdata.scope = '.$DB->sql_cast_char2int('item.scope').'
                     WHERE reviewdata.'.$this->prefix.'questfieldid = ?
                       AND reviewdata.'.$this->storage->answerfield.' '.$answerssql;

            $params = array_merge(array(goal::SCOPE_COMPANY, $this->subjectid,
                                        goal::SCOPE_PERSONAL, $this->subjectid, $this->id), $answerids);

            $items = $DB->get_records_sql($sql, $params);
            foreach ($items as $item) {
                if (!isset($item->fullname)) {
                    $item->fullname = html_writer::tag('em',
                            get_string('reviewgoalsassignmissing', 'totara_question'));
                    $item->ismissing = true;
                }
            }
            return $items;
        } else {
            return array();
        }
    }

    /** Get a list of all items that are linked to this question.
     *
     * @return array
     */
    public function get_review_items() {
        global $DB;

        $module = $this->prefix;
        $relatedanswerids = $module::get_related_answerids($this->answerid);

        if ($relatedanswerids) {
            list($answerssql, $answerids) = $DB->get_in_or_equal($relatedanswerids);

            $sql = 'SELECT DISTINCT ' . $DB->sql_concat('reviewdata.itemid', "'_'", 'reviewdata.scope') . ' AS uniquekey,
                           reviewdata.itemid, reviewdata.scope
                      FROM {'.$this->prefix.'_review_data} reviewdata
                     WHERE reviewdata.'.$this->prefix.'questfieldid = ?
                       AND reviewdata.'.$this->storage->answerfield.' '.$answerssql;

            return $DB->get_records_sql($sql, array_merge(array($this->id), $answerids));
        } else {
            return array();
        }
    }

    /**
     * Check that ids are assigned to user.
     *
     * @param array $idlist
     * @param int $userid the user which these ids should belong to
     * @param int $scope
     * @return array $ids filtered
     */
    public function check_target_ids(array $idlist, $userid, $scope = 0) {
        global $DB;

        list($itemssql, $params) = $DB->get_in_or_equal($idlist);
        $params[] = $userid;

        if ($scope == goal::SCOPE_PERSONAL) {
            $sql = "SELECT item.id
                      FROM {goal_personal} item
                     WHERE item.id " . $itemssql . "
                       AND item.userid = ?
                       AND deleted = 0";
        } else if ($scope == goal::SCOPE_COMPANY) {
            $sql = "SELECT item.id
                      FROM {goal_record} item
                     WHERE item.id " . $itemssql . "
                       AND item.userid = ?
                       AND deleted = 0";
        } else {
            throw new question_exception('Unknown type of goal');
        }

        $new_items = $DB->get_records_sql($sql, $params);

        return array_keys($new_items);
    }

    /**
     * Get items that have already been added to the review question, so that they can be excluded from the selection dialog.
     *
     * @param int $planid unused in goals
     * @return array
     */
    public function get_already_selected($planid) {
        global $DB;

        $sql_company = 'SELECT DISTINCT gr.id, g.fullname
                  FROM {' . $this->prefix . '_review_data} reviewdata
                  JOIN {goal_record} gr
                    ON reviewdata.itemid = gr.id
                   AND gr.deleted = 0
                  JOIN {goal} g
                    ON gr.goalid = g.id
                 WHERE reviewdata.' . $this->prefix . 'questfieldid = ?
                   AND reviewdata.scope = ?';

        $sql_personal = 'SELECT DISTINCT ' . $DB->sql_concat("'personal_'", 'pg.id') . ' AS id, pg.name AS fullname
                  FROM {' . $this->prefix . '_review_data} reviewdata
                  JOIN {goal_personal} pg
                    ON reviewdata.itemid = pg.id
                   AND pg.deleted = 0
                 WHERE reviewdata.' . $this->prefix . 'questfieldid = ?
                 AND reviewdata.scope = ?';

        $selected_company = $DB->get_records_sql($sql_company, array($this->id, goal::SCOPE_COMPANY));
        $selected_personal = $DB->get_records_sql($sql_personal, array($this->id, goal::SCOPE_PERSONAL));

        return $selected_company + $selected_personal;
    }

    /**
     * Check if the user can add and remove company review items.
     *
     * @return bool
     */
    public function can_select_company() {
        return $this->param5 & self::SELECT_COMPANY_USERCANCHOOSE;
    }

    /**
     * Check if the user can add and remove personal review items.
     *
     * @return bool
     */
    public function can_select_personal() {
        return $this->param5 & self::SELECT_PERSONAL_USERCANCHOOSE;
    }

    /**
     * Check if the user can add and remove review items.
     *
     * @return bool
     */
    public function can_select_items() {
        if ($this->can_select_company() || $this->can_select_personal()) {
            return parent::can_select_items();
        } else {
            return false;
        }
    }

    /**
     * Check if the item can be deleted.
     *
     * @param array $itemgroup the review item group to test
     * @return bool
     */
    public function can_delete_item($itemgroup) {
        $anyitemset = reset($itemgroup);
        $anyitem = reset($anyitemset);
        $scope = $anyitem->scope;
        if ($scope == goal::SCOPE_PERSONAL) {
            if ($this->param5 & self::SELECT_PERSONAL_USERCANCHOOSE || (isset($anyitem->ismissing) && $anyitem->ismissing)) {
                return parent::can_delete_item($itemgroup);
            }
        } else if ($scope == goal::SCOPE_COMPANY) {
            if ($this->param5 & self::SELECT_COMPANY_USERCANCHOOSE || (isset($anyitem->ismissing) && $anyitem->ismissing)) {
                return parent::can_delete_item($itemgroup);
            }
        }
        return false;
    }

    /**
     * Custom set value for question instance
     *
     * @param stdClass $data
     * @param $source
     */
    public function edit_set(stdClass $data, $source) {
        parent::edit_set($data, $source);

        $goal = new goal();
        if (!$permissions = $goal->get_permissions(null, $this->subjectid)) {
            // Error setting up page permissions.
            print_error('error:viewusergoals', 'totara_hierarchy');
        }

        extract($permissions);

        if ($source == 'form') {
            // Save the scalevalueids to the db.
            $goals = $this->get_review_items();
            foreach ($goals as $goal) {
                $name = $this->get_prefix_form() . '_scalevalueid_' . $goal->itemid . '_' . $goal->scope;
                if (isset($data->$name)) {
                    $scalevalueid = $data->$name;
                    $todb = new stdClass();
                    $todb->id = $goal->itemid;
                    $todb->scalevalueid = $scalevalueid;
                    if ($goal->scope == goal::SCOPE_COMPANY && $can_edit_company) {
                        goal::update_goal_item($todb, goal::SCOPE_COMPANY);
                    } else if ($goal->scope == goal::SCOPE_PERSONAL && $can_edit_personal) {
                        goal::update_goal_item($todb, goal::SCOPE_PERSONAL);
                    }
                }
            }
        }
    }

}
