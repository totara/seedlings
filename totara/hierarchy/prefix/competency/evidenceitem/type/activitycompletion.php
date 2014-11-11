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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @package totara
 * @subpackage totara_hierarchy
 */

/**
 * Activity completion competency evidence type
 */
class competency_evidence_type_activitycompletion extends competency_evidence_type {

    /**
     * Evidence item type
     * @var string
     */
    public $itemtype = COMPETENCY_EVIDENCE_TYPE_ACTIVITY_COMPLETION;

    /**
     * Module instance
     * @var object
     */
    private $_module;

    /**
     * Add this evidence to a competency
     *
     * @param   $competency Competency object
     * @return  void
     */
    public function add($competency) {
        global $DB;

        // Set item details
        $cmrec = $DB->get_record_sql("
            SELECT
                cm.*,
                md.name as modname
            FROM
                {course_modules} cm,
                {modules} md
            WHERE
                cm.id = ?
            AND md.id = cm.module
        ", array($this->iteminstance));

       $this->iteminstance = $cmrec->instance;
       $this->itemmodule = $cmrec->modname;

       return parent::add($competency);
    }

    /**
     * Return module instance
     *
     * @return object
     */
    private function _get_module() {
        global $DB;

        // If already loaded
        if ($this->_module) {
            return $this->_module;
        }

        // Get module
        $module = $DB->get_record($this->itemmodule, array('id' => $this->iteminstance));

        if (!$module) {
            print_error('loadmoduleinstance', 'totara_hierarchy', array($a => $this->itemmodule, $b => $this->iteminstance));
        }

        // Save module instanace
        $this->_module = $module;
        return $this->_module;
    }

    /**
     * Return evidence name and link
     *
     * @return  string
     */
    public function get_name() {
        global $CFG;

        $module = $this->_get_module();

        return '<a href="'.$CFG->wwwroot.'/mod/'.$this->itemmodule.'/view.php?id='.$this->iteminstance.'">'.format_string($module->name).'</a>';
    }

    /**
     * Return evidence item type and link
     *
     * @return  string
     */
    public function get_type() {
        global $CFG;

        $name = $this->get_type_name();

        $module = $this->_get_module();

        return '<a href="'.$CFG->wwwroot.'/report/progress/index.php?course='.$module->course.'">'.format_string($name).'</a>';
    }

    /**
     * Find user's who have completed this evidence type
     * @access  public
     * @return  void
     */
    public function cron() {

        global $CFG, $DB;

        // Only select activity completions that have changed
        // since an evidence item evidence was last changed
        //
        // A note on the sub-query, it returns:
        //   scaleid | proficient
        // where proficient is the ID of the lowest scale
        // value in that scale that has the proficient flag
        // set to 1
        //
        // The sub-sub-query is needed to allow us to return
        // the ID, when the actual item is determined by
        // the sortorder
        $sql = "
            SELECT DISTINCT
                ccr.id AS id,
                cc.id AS itemid,
                cc.competencyid,
                cmc.userid,
                ccr.timecreated,
                cmc.completionstate,
                proficient.proficient,
                cs.defaultid
            FROM
                {comp_criteria} cc
            INNER JOIN
                {comp} co
             ON cc.competencyid = co.id
            INNER JOIN
                {course_modules_completion} cmc
             ON cc.iteminstance = cmc.coursemoduleid
            INNER JOIN
                {comp_scale_assignments} csa
            ON co.frameworkid = csa.frameworkid
            INNER JOIN
                {comp_scale} cs
             ON csa.scaleid = cs.id
            INNER JOIN
            (
                SELECT csv.scaleid, csv.id AS proficient
                FROM {comp_scale_values} csv
                INNER JOIN
                (
                    SELECT scaleid, MAX(sortorder) AS maxsort
                    FROM {comp_scale_values}
                    WHERE proficient = 1
                    GROUP BY scaleid
                ) grouped
                ON csv.scaleid = grouped.scaleid AND csv.sortorder = grouped.maxsort
            ) proficient
            ON cs.id = proficient.scaleid
            LEFT JOIN
                {comp_criteria_record} ccr
             ON ccr.itemid = cc.id
            AND ccr.userid = cmc.userid
            WHERE
                cc.itemtype = 'activitycompletion'
            AND cmc.id IS NOT NULL
            AND proficient.proficient IS NOT NULL
            AND
            (
                (
                    ccr.proficiencymeasured <> proficient.proficient
                AND ccr.timemodified < cmc.timemodified
                )
             OR ccr.proficiencymeasured IS NULL
            )
        ";

        // Loop through evidence itmes, and mark as complete
        if ($rs = $DB->get_recordset_sql($sql)) {
            foreach ($rs as $record) {

                if (debugging()) {
                    mtrace('.', '');
                }

                require_once($CFG->dirroot . '/totara/hierarchy/prefix/competency/evidenceitem/type/record.php');
                $evidence = new comp_criteria_record((array)$record, false);

                if (in_array($record['completionstate'], array(COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS))) {
                    $evidence->proficiencymeasured = $record['proficient'];
                }
                elseif ($record['defaultid']) {
                    $evidence->proficiencymeasured = $record['defaultid'];
                }
                else {
                    continue;
                }

                $evidence->save();
            }

            if (debugging() && isset($evidence)) {
                mtrace('');
            }
            $rs->close();
        }
    }
}
