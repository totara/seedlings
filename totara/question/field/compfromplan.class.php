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
require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/evidence/lib.php');
require_once($CFG->dirroot.'/totara/plan/development_plan.class.php');

class question_compfromplan extends reviewrating {

    protected $component = 'competency';

    public static function get_info() {
        return array('group' => question_manager::GROUP_REVIEW,
                     'type' => get_string('questiontypecompfromplan', 'totara_question'));
    }

    public function __construct($storage, $subjectid = 0, $answerid = 0) {
        $this->buttonlabel = get_string('choosecompfromplanreview', 'totara_question');

        parent::__construct($storage, $subjectid, $answerid);
    }

    /**
     * Determine if there are any review items that belong to the subject.
     *
     * @return bool
     */
    public function has_review_items() {
        global $DB;

        $itemsql = 'SELECT COUNT(item.id)
                      FROM {dp_plan_competency_assign} item
                      JOIN {dp_plan} pl
                        ON item.planid = pl.id
                     WHERE pl.userid = ?
                       AND pl.status >= ?
                       AND item.approved >= ?';
        return $DB->count_records_sql($itemsql, array($this->subjectid, DP_PLAN_STATUS_APPROVED, DP_APPROVAL_APPROVED));
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

        // Get the scale value id (field "proficiency" in comp_record).
        $compassign = $DB->get_record('dp_plan_competency_assign', array('id' => $item->itemid));
        if (empty($compassign)) {
            return;
        }

        $comprecord = $DB->get_record('comp_record',
                array('userid' => $this->subjectid, 'competencyid' => $compassign->competencyid));

        if (isset($comprecord) && isset($comprecord->proficiency) && $comprecord->proficiency > 0) {
            $scalevalueid = $comprecord->proficiency;
            $scalevalue = $DB->get_record('comp_scale_values', array('id' => $scalevalueid));
            $scaleid = $scalevalue->scaleid;
            $scalevaluename = format_string($scalevalue->name);
        } else {
            $comp = $DB->get_record('comp', array('id' => $compassign->competencyid));
            $compscaleassign = $DB->get_record('comp_scale_assignments', array('frameworkid' => $comp->frameworkid));
            $scalevalueid = 0;
            $scaleid = $compscaleassign->scaleid;
            $scalevaluename = get_string('notset', 'totara_hierarchy');
        }

        if (!$this->viewonly && $this->can_update_competency($item->itemid) === true) {
            $scalevalues = $DB->get_records('comp_scale_values', array('scaleid' => $scaleid), 'sortorder');
            $options = array();
            $options[0] = get_string('notset', 'totara_hierarchy');
            foreach ($scalevalues as $value) {
                $options[$value->id] = format_string($value->name);
            }
            $name = $this->get_prefix_form() . '_scalevalueid_' . $item->itemid;
            $form->addElement('select', $name, get_string('competencystatus', 'totara_question'), $options,
                    array('class' => 'rating_selector rating_item_compfromplan_' . $item->itemid));
            $form->setDefault($name, $scalevalueid);
        } else {
            $form->addElement('static', '', get_string('competencystatus', 'totara_question'), $scalevaluename);
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

            $sql = 'SELECT reviewdata.*, comp.fullname, pl.name AS planname
                     FROM {'.$this->prefix.'_review_data} reviewdata
                     LEFT JOIN {dp_plan_competency_assign} pca
                       ON reviewdata.itemid = pca.id
                     LEFT JOIN {comp} comp
                       ON comp.id = pca.competencyid
                     LEFT JOIN {dp_plan} pl
                       ON pl.id = pca.planid
                    WHERE reviewdata.'.$this->prefix.'questfieldid = ?
                      AND reviewdata.'.$this->storage->answerfield.' '.$answerssql.'
                    ORDER BY reviewdata.itemid';

            $items = $DB->get_records_sql($sql, array_merge(array($this->id), $answerids));
            foreach ($items as $item) {
                if (!isset($item->fullname) || !isset($item->planname)) {
                    $item->fullname = html_writer::tag('em',
                            get_string('reviewcompfromplanassignmissing', 'totara_question'));
                }
            }
            return $items;
        } else {
            return array();
        }
    }

    /**
     * Check that ids are assigned to user.
     *
     * @param array $ids
     * @param int $userid the user which these ids should belong to
     * @return array $ids filtered
     */
    public function check_target_ids(array $idlist, $userid) {
        global $DB;

        list($itemssql, $params) = $DB->get_in_or_equal($idlist);
        $params[] = $userid;

        $sql = "SELECT item.id
                  FROM {dp_plan_competency_assign} item
                  JOIN {dp_plan} pl
                    ON item.planid = pl.id
                 WHERE item.id " . $itemssql . "
                   AND pl.userid = ?";

        $new_items = $DB->get_records_sql($sql, $params);

        return array_keys($new_items);
    }

    /**
     * Get items that have already been added to the review question, so that they can be excluded from the selection dialog.
     *
     * @param int $planid
     * @return array
     */
    public function get_already_selected($planid) {
        global $DB;

        $sql = 'SELECT DISTINCT pca.id, c.fullname
                  FROM {' . $this->prefix . '_review_data} review_data
                  JOIN {dp_plan_competency_assign} pca
                    ON review_data.itemid = pca.id
                  JOIN {comp} c
                    ON c.id = pca.competencyid
                 WHERE review_data.' . $this->prefix . 'questfieldid = ?
                   AND pca.planid = ?';
        return $DB->get_records_sql($sql, array($this->id, $planid));
    }

    /**
     * Determine if the current user can edit the competency of the subject.
     *
     * @param int $compassignid
     * @return bool|array
     */
    public function can_update_competency($compassignid) {
        global $DB;

        $compassign = $DB->get_record('dp_plan_competency_assign', array('id' => $compassignid));
        if (empty($compassign)) {
            return false;
        }
        $plan = new development_plan($compassign->planid);
        $componentname = 'competency';
        $component = $plan->get_component($componentname);

        return hierarchy_can_add_competency_evidence($plan, $component, $this->subjectid, $compassign->competencyid);
    }

    /**
     * Update the scale value for the competency of the subject.
     *
     * @param int $compassignid
     * @param int $scalevalueid new scale value id (stored in "proficiency" field)
     */
    public function update_competency($compassignid, $scalevalueid) {
        global $DB, $USER;

        $compassign = $DB->get_record('dp_plan_competency_assign', array('id' => $compassignid));
        $plan = new development_plan($compassign->planid);
        $componentname = 'competency';
        $component = $plan->get_component($componentname);

        // Log it.
        add_to_log(SITEID, 'plan', 'competency proficiency updated',
                "update-competency-setting.php?competencyid={$compassign->competencyid}&prof={$scalevalueid}&planid={$plan->id}",
                'ajax');

        // Update the competency evidence.
        $details = new stdClass();
        $posrec = $DB->get_record('pos_assignment', array('userid' => $this->subjectid, 'type' => POSITION_TYPE_PRIMARY),
                'id, positionid, organisationid');
        if ($posrec) {
            $details->positionid = $posrec->positionid;
            $details->organisationid = $posrec->organisationid;
            unset($posrec);
        }
        $details->assessorname = fullname($USER);
        $details->assessorid = $USER->id;

        hierarchy_add_competency_evidence($compassign->competencyid, $this->subjectid, $scalevalueid, $component, $details);
    }

    /**
     * Custom set value for question instance
     *
     * @param stdClass $data
     * @param $source
     */
    public function edit_set(stdClass $data, $source) {
        parent::edit_set($data, $source);

        if ($source == 'form') {
            // Save the scalevalueids to the db.
            $competencies = $this->get_review_items();
            foreach ($competencies as $competency) {
                $name = $this->get_prefix_form() . '_scalevalueid_' . $competency->itemid;
                if ($this->can_update_competency($competency->itemid) === true && isset($data->$name)) {
                    $scalevalueid = $data->$name;
                    $this->update_competency($competency->itemid, $scalevalueid);
                }
            }
        }
    }

}
