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
 * @author Aaron Barnes <aaronb@catalyst.net.nz>
 * @package totara
 * @subpackage plan
 */

/**
 * Plan linked courses specific course dialog generator
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content_courses.class.php');
require_once($CFG->dirroot.'/totara/cohort/lib.php');

class totara_dialog_linked_courses_content_courses extends totara_dialog_content_courses {

    /**
     * PHP file to use for search tab content
     *
     * @access  public
     * @var     string
     */
    public $search_code = '';

    /**
     * Overwrite parent's constructor to avoid categories being loaded
     *
     * @see     totara_dialog_content_courses::__construct()
     * @access  public
     */
    public function __construct() {}


    /**
     * Load courses to display
     *
     * @access  public
     * @var     integer planid  id of development plan for which linked courses should be loaded
     */
    public function load_courses($planid = null) {
        global $DB;

        $planid = (int) $planid;

        list($visibilitysql, $visibilityparams) = totara_visibility_where(null,
                                                                          'c.id',
                                                                          'c.visible',
                                                                          'c.audiencevisible',
                                                                          'c',
                                                                          'course');

        $sql = "
            SELECT
                dppca.id AS id,
                c.fullname AS fullname,
                c.sortorder AS sortorder
            FROM
                {dp_plan_course_assign} dppca
            INNER JOIN
                {course} c
             ON c.id = dppca.courseid
            INNER JOIN {context} ctx
             ON c.id = ctx.instanceid AND ctx.contextlevel =:contextlevel
            WHERE
                dppca.planid =:planid
             AND
               {$visibilitysql}
            ORDER BY
                c.fullname
        ";
        $params = array_merge(array('planid'=> $planid, 'contextlevel' => CONTEXT_COURSE), $visibilityparams);

        $this->courses = $DB->get_records_sql($sql, $params);

    }

    /**
     * Load courses to display based on a specific competency
     *
     * @access  public
     * @var     integer competency id of development plan for which linked courses should be loaded
     */
    public function load_courses_from_competency($competencyid = null) {
        global $DB;

        $competencyid = (int) $competencyid;

        list($visibilitysql, $visibilityparams) = totara_visibility_where(null,
            'c.id',
            'c.visible',
            'c.audiencevisible',
            'c',
            'course');

        $sql = "
            SELECT
                c.id As id,
                c.fullname AS fullname,
                c.sortorder AS sortorder
            FROM
                {course} c
            INNER JOIN {context} ctx
             ON c.id = ctx.instanceid AND ctx.contextlevel =:contextlevel
            INNER JOIN
                {comp_criteria} cc
             ON c.id = cc.iteminstance
            INNER JOIN
                {dp_plan_competency_assign} ca
             ON cc.competencyid = ca.competencyid
            WHERE
                cc.itemtype =:cctype
            AND ca.id =:comptid
            AND {$visibilitysql}
        ";
        $params = array(
            'contextlevel' => CONTEXT_COURSE,
            'cctype'  => COMPETENCY_EVIDENCE_TYPE_COURSE_COMPLETION,
            'comptid' => $competencyid);
        $params = array_merge($params, $visibilityparams);

        $this->courses = $DB->get_records_sql($sql, $params);

    }

}
