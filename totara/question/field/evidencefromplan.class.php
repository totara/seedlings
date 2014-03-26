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

class question_evidencefromplan extends review {

    protected $component = 'evidence';

    public static function get_info() {
        return array('group' => question_manager::GROUP_REVIEW,
                     'type' => get_string('questiontypeevidencefromplan', 'totara_question'));
    }

    public function __construct($storage, $subjectid = 0, $answerid = 0) {
        $this->buttonlabel = get_string('chooseevidencefromplanreview', 'totara_question');

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
                      FROM {dp_plan_evidence_relation} link
                      JOIN {dp_plan} pl
                        ON link.planid = pl.id
                      JOIN {dp_plan_evidence} item
                        ON link.evidenceid = item.id
                     WHERE pl.userid = ?
                       AND pl.status >= ?';
        return $DB->count_records_sql($itemsql, array($this->subjectid, DP_PLAN_STATUS_APPROVED));
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

            $sql = 'SELECT reviewdata.*, pe.name AS fullname
                      FROM {'.$this->prefix.'_review_data} reviewdata
                      LEFT JOIN {dp_plan_evidence_relation} per
                        ON reviewdata.itemid = per.evidenceid
                      LEFT JOIN {dp_plan_evidence} pe
                        ON per.evidenceid = pe.id
                     WHERE reviewdata.'.$this->prefix.'questfieldid = ?
                       AND reviewdata.'.$this->storage->answerfield.' '.$answerssql.'
                     ORDER BY reviewdata.itemid';

            $items = $DB->get_records_sql($sql, array_merge(array($this->id), $answerids));
            foreach ($items as $item) {
                if (!isset($item->fullname)) {
                    $item->fullname = html_writer::tag('em',
                            get_string('reviewevidencefromplanassignmissing', 'totara_question'));
                    $item->ismissing = true;
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

        $sql = "SELECT item.evidenceid
                  FROM {dp_plan_evidence_relation} item
                  JOIN {dp_plan} pl
                    ON item.planid = pl.id
                 WHERE item.evidenceid " . $itemssql . "
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

        $sql = 'SELECT DISTINCT pe.id, pe.name as fullname
                  FROM {' . $this->prefix . '_review_data} review_data
                  JOIN {dp_plan_evidence} pe
                    ON review_data.itemid = pe.id
                  JOIN {dp_plan_evidence_relation} per
                    ON pe.id = per.evidenceid
                 WHERE review_data.' . $this->prefix . 'questfieldid = ?
                   AND per.planid = ?';
        return $DB->get_records_sql($sql, array($this->id, $planid));
    }

}
