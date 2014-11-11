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
 * @author Aaron Barnes <aaronb@catalyst.net.nz>
 * @package totara
 * @subpackage plan
 */

require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/lib.php');
require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/evidence/lib.php');


if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

class dp_competency_component extends dp_base_component {

    public static $permissions = array(
        'updatecompetency' => true,
        //'commenton' => false,
        'setpriority' => false,
        'setduedate' => false,
        'setproficiency' => false,
        'deletemandatory' => false
    );


    /**
     * Constructor, set default name
     *
     * @access  public
     * @param   object  $plan
     * @return  void
     */
    public function __construct($plan) {
        parent::__construct($plan);
        $this->defaultname = get_string('competencies', 'totara_plan');
    }


    /**
     * Initialize settings for the component
     *
     * @access  public
     * @param   array   $settings
     * @return  void
     */
    public function initialize_settings(&$settings) {
        global $DB;

        if ($competencysettings = $DB->get_record('dp_competency_settings', array('templateid' => $this->plan->templateid))) {
            $settings[$this->component.'_duedatemode'] = $competencysettings->duedatemode;
            $settings[$this->component.'_prioritymode'] = $competencysettings->prioritymode;
            $settings[$this->component.'_priorityscale'] = $competencysettings->priorityscale;
            $settings[$this->component.'_autoassignorg'] = $competencysettings->autoassignorg;
            $settings[$this->component.'_autoassignpos'] = $competencysettings->autoassignpos;
            $settings[$this->component.'_includecompleted'] = $competencysettings->includecompleted;
            $settings[$this->component.'_autoassigncourses'] = $competencysettings->autoassigncourses;
            $settings[$this->component.'_autoadddefaultevidence'] = $competencysettings->autoadddefaultevidence;
        }
    }


    /**
     * Get list of items assigned to plan
     *
     * Optionally, filtered by status
     *
     * @access  public
     * @param   mixed   $approved   (optional)
     * @param   string  $orderby    (optional)
     * @param   int     $limitfrom  (optional)
     * @param   int     $limitnum   (optional)
     * @return  array
     */
    public function get_assigned_items($approved = null, $orderby='', $limitfrom='', $limitnum='') {
        global $DB;

        // Generate where clause
        $where = "c.visible = 1 AND a.planid = :planid";
        $params = array('planid' => $this->plan->id);
        if ($approved !== null) {
            list($approvedsql, $approvedparams) = $DB->get_in_or_equal($approved, SQL_PARAMS_NAMED, 'approved');
            $where .= " AND a.approved {$approvedsql}";
            $params = array_merge($params, $approvedparams);
        }

        // Generate order by clause
        if ($orderby) {
            $orderby = "ORDER BY $orderby";
        }

        // Generate status code
        if ($this->plan->is_complete()) {
            // Use the 'snapshot' status value
            $status = "LEFT JOIN {comp_scale_values} csv ON a.scalevalueid = csv.id ";
        } else {
            // Use the 'live' status value
            $status = "
                LEFT JOIN
                    {comp_record} cr
                 ON a.competencyid = cr.competencyid
                AND cr.userid = :planuserid
                LEFT JOIN
                    {comp_scale_values} csv
                 ON cr.proficiency = csv.id";
            $params['planuserid'] = $this->plan->userid;
        }

        return  $DB->get_records_sql(
            "
            SELECT
                a.*,
                csv.sortorder AS progress,
                c.fullname,
                c.fullname AS name,
                CASE WHEN linkedcourses.count IS NULL
                    THEN 0 ELSE linkedcourses.count
                END AS linkedcourses,
                CASE
                    WHEN linkedevidence.count IS NULL THEN 0
                    ELSE linkedevidence.count
                END AS linkedevidence,
                csv.id AS profscalevalueid,
                csv.name AS status,
                csv.sortorder AS profsort
            FROM
                {dp_plan_competency_assign} a
            INNER JOIN
                {comp} c
                ON c.id = a.competencyid
            LEFT JOIN
                (SELECT itemid1 AS assignid,
                    COUNT(id) AS count
                    FROM {dp_plan_component_relation}
                    WHERE component1 = 'competency'
                    AND component2 = 'course'
                    GROUP BY itemid1) linkedcourses
                ON linkedcourses.assignid = a.id
            LEFT JOIN
                (SELECT itemid,
                    COUNT(id) AS count
                    FROM {dp_plan_evidence_relation}
                    WHERE component = 'competency'
                    GROUP BY itemid) linkedevidence
                ON linkedevidence.itemid = a.id
            $status
            WHERE
                $where
                $orderby
            ",
            $params,
            $limitfrom,
            $limitnum
        );

    }

    /**
     * Search information for search dialog box
     *
     * @param stdClass $search_info
     * @param array $keywords
     * @param int $parentid
     * @param array $approved
     */
    public function get_search_info(stdClass $search_info, $keywords, $parentid = 0, $approved = null) {
        global $DB;

        // Generate where clause.
        $where = "c.visible = 1 AND a.planid = :planid";
        $params = array('planid' => $this->plan->id);
        if ($approved !== null) {
            list($approvedsql, $approvedparams) = $DB->get_in_or_equal($approved, SQL_PARAMS_NAMED, 'approved');
            $where .= " AND a.approved {$approvedsql}";
            $params = array_merge($params, $approvedparams);
        }

        if ($keywords) {
            list($searchsql, $searchparams) = totara_search_get_keyword_where_clause($keywords, array('c.fullname'),
                SQL_PARAMS_NAMED);
            $params = array_merge($params, $searchparams);
            $where .= ' AND '.$searchsql;
        }

        // Generate status code.
        if ($this->plan->is_complete()) {
            // Use the 'snapshot' status value.
            $status = "LEFT JOIN {comp_scale_values} csv ON a.scalevalueid = csv.id ";
        } else {
            // Use the 'live' status value.
            $status = "
                LEFT JOIN
                    {comp_record} cr
                 ON a.competencyid = cr.competencyid
                AND cr.userid = :planuserid
                LEFT JOIN
                    {comp_scale_values} csv
                 ON cr.proficiency = csv.id";
            $params['planuserid'] = $this->plan->userid;
        }

        $sql = "FROM
                {dp_plan_competency_assign} a
            INNER JOIN
                {comp} c
                ON c.id = a.competencyid
            LEFT JOIN
                (SELECT itemid1 AS assignid,
                    COUNT(id) AS count
                    FROM {dp_plan_component_relation}
                    WHERE component1 = 'competency'
                    AND component2 = 'course'
                    GROUP BY itemid1) linkedcourses
                ON linkedcourses.assignid = a.id
            LEFT JOIN
                (SELECT itemid,
                    COUNT(id) AS count
                    FROM {dp_plan_evidence_relation}
                    WHERE component = 'competency'
                    GROUP BY itemid) linkedevidence
                ON linkedevidence.itemid = a.id
            $status
            WHERE
                $where";
        $search_info->id = 'a.id';
        $search_info->fullname = 'c.fullname';
        $search_info->sql = $sql;
        $search_info->order = 'ORDER BY c.fullname';
        $search_info->params = $params;
    }

    /**
     * Process an action
     *
     * General component actions can come in here
     *
     * @access  public
     * @return  void
     */
    public function process_action_hook() {
        global $DB;
        $delete = optional_param('d', 0, PARAM_INT); // competency assignment id to delete
        $confirm = optional_param('confirm', 0, PARAM_INT); // confirm delete

        $currenturl = $this->get_url();

        if ($delete && $confirm) {
            if (!confirm_sesskey()) {
                totara_set_notification(get_string('confirmsesskeybad', 'error'), $currenturl);
            }
            if ($this->remove_competency_assignment($delete)) {
                add_to_log(SITEID, 'plan', 'removed competency', "component.php?id={$this->plan->id}&c=competency", "Competency (ID:{$delete})");

                $dropcourselist = optional_param_array('dropcourse', array(), PARAM_INT);
                if ($dropcourselist) {
                    if (!is_array($dropcourselist)) {
                        $dropcourselist = array($dropcourselist);
                    }
                    $coursecomponent = $this->plan->get_component('course');
                    foreach ($dropcourselist as $courseid) {
                        add_to_log(SITEID, 'plan', 'removed course', "component.php?id={$this->plan->id}&c=course", "Course (ID:{$courseid}) via Competency {$delete}");
                        $coursecomponent->unassign_item($coursecomponent->get_assignment($courseid));
                    }
                }

                // Remove linked evidence
                $params = array('planid' => $this->plan->id, 'component' => $this->component, 'itemid' => $delete);
                $DB->delete_records('dp_plan_evidence_relation', $params);

                totara_set_notification(get_string('canremoveitem', 'totara_plan'), $currenturl, array('class' => 'notifysuccess'));
            } else {
                totara_set_notification(get_string('cannotremoveitem', 'totara_plan'), $currenturl);
            }
        }
    }


    /**
     * Process when plan is created
     *
     * Any actions that need to be processed on a component
     * when a plan is created.
     *
     * @access public
     * @return void
     */
    public function plan_create_hook() {
        if ($this->get_setting('autoassignorg')) {
            // From organisation
            if (!$this->assign_from_org()) {
                error(get_string('unabletoassigncompsfromorg', 'totara_plan'));
            }
        }
        if ($this->get_setting('autoassignpos')) {
            // From position
            if (!$this->assign_from_pos()) {
                error(get_string('unabletoassigncompsfrompos', 'totara_plan'));
            }
        }
    }


    /**
     * Code to load the JS for the picker
     *
     * @access  public
     * @return  void
     */
    public function setup_picker() {
        global $PAGE;
        // If we are showing dialog
        if ($this->can_update_items() || hierarchy_can_add_competency_evidence($this->plan, $this, $this->plan->viewas, null)) {
            // Setup lightbox
            local_js(array(
                TOTARA_JS_DIALOG,
                TOTARA_JS_TREEVIEW
            ));

            $component_name = required_param('c', PARAM_ALPHA);
            $paginated = optional_param('page', 0, PARAM_INT);
            $sesskey = sesskey();

            // Get course picker
            $jsmodule = array(
                'name' => 'totara_plan_component',
                'fullpath' => '/totara/plan/component.js',
                'requires' => array('json'));
            $PAGE->requires->js_init_call('M.totara_plan_component.init', array('args' => '{"plan_id":'.$this->plan->id.', "page":"'.$paginated.'", "component_name":"'.$component_name.'", "sesskey":"'.$sesskey.'"}'), false, $jsmodule);

            $PAGE->requires->string_for_js('save', 'totara_core');
            $PAGE->requires->string_for_js('cancel', 'moodle');
            $PAGE->requires->string_for_js('continue', 'moodle');
            $PAGE->requires->string_for_js('addcompetencys', 'totara_plan');

            $jsmodule = array(
                'name' => 'totara_plan_competency_find',
                'fullpath' => '/totara/plan/components/competency/find.js',
                'requires' => array('json'));
            $PAGE->requires->js_init_call('M.totara_plan_competency_find.init', array('args' => '{"plan_id":'.$this->plan->id.'}'), false, $jsmodule);
        }
    }


    /**
     * Code to run after page header is display
     *
     * @access  public
     * @return  void
     */
    public function post_header_hook() {
        global $CFG, $USER, $DB, $OUTPUT;

        $delete = optional_param('d', 0, PARAM_INT); // course assignment id to delete
        $currenturl = $this->get_url();

        if ($delete) {
            require_once($CFG->dirroot . '/totara/plan/components/evidence/evidence.class.php');
            $evidence = new dp_evidence_relation($this->plan->id, $this->component, $delete);
            echo $evidence->display_delete_warning();

            // Print a list of linked courses
            $sql = "
                SELECT courseasn.id, course.fullname
                FROM
                    {course} course
                    INNER JOIN {dp_plan_course_assign} courseasn
                        ON course.id = courseasn.courseid
                    INNER JOIN {dp_plan_component_relation} rel
                        ON rel.itemid2 = courseasn.id
                WHERE
                    rel.component1 = 'competency'
                    AND rel.itemid1 = ?
                    AND rel.component2 = 'course'
                    AND NOT EXISTS (
                        SELECT 1
                        FROM {dp_plan_component_relation} rel2
                        WHERE
                            rel2.component1 = 'competency'
                            AND rel2.itemid1 <> ?
                            AND rel2.component2 = 'course'
                            AND rel2.itemid2 = courseasn.id
                    )
            ";
            $courses = $DB->get_records_sql($sql, array($delete, $delete));
            if ($courses) {
                echo $OUTPUT->box_start('generalbox', 'notice');
                $compname = $DB->get_field_sql("SELECT comp.fullname FROM {comp} comp INNER JOIN {dp_plan_competency_assign} compasn ON comp.id = compasn.competencyid WHERE compasn.id = ?", array($delete));
                echo $OUTPUT->heading(get_string('deletelinkedcoursesheader', 'totara_plan', s(format_string($compname))));

                if ($USER->id == $this->plan->userid) {
                    echo html_writer::tag('p', get_string('deletelinkedcoursesinstructionslearner', 'totara_plan'));
                } else {
                    if ($planowner = $DB->get_record('user', array('id' => $this->plan->userid), 'firstname, lastname')) {
                        $planowner_name = fullname($planowner);
                    }

                    echo html_writer::tag('p', get_string('deletelinkedcoursesinstructionsmanager', 'totara_plan', $planowner_name));
                }

                $form = html_writer::start_tag('form', array('method' => 'get', 'action' => "{$CFG->wwwroot}/totara/plan/component.php"));
                $form .= html_writer::empty_tag("input", array('type' => "hidden", 'name' => "d", 'value' => "$delete"));
                $form .= html_writer::empty_tag("input", array('type' => "hidden", 'name' => "c", 'value' => "competency"));
                $form .= html_writer::empty_tag("input", array('type' => "hidden", 'name' => "confirm", 'value' => "1"));
                $form .= html_writer::empty_tag("input", array('type' => "hidden", 'name' => "sesskey", 'value' => sesskey()));
                $form .= html_writer::empty_tag("input", array('type' => "hidden", 'name' => "id", 'value' => $this->plan->id));
                foreach ($courses as $rec) {
                    $form .= html_writer::tag('p', html_writer::checkbox('dropcourse[]',$rec->id, true, $rec->fullname));
                }
                $form .= $OUTPUT->container(html_writer::empty_tag("input", array('type' => "submit", 'value' => s(get_string('deletelinkedcoursessubmit', 'totara_plan')))), 'buttons');
                $form .= html_writer::end_tag('form');
                echo $form;
                echo $OUTPUT->box_end();
                echo $OUTPUT->footer();
                die();
            } else {
                $continueurl = new moodle_url($currenturl->out(), array('d' => $delete, 'confirm' => '1', 'sesskey' => sesskey()));
                echo $OUTPUT->confirm(get_string('confirmitemdelete', 'totara_plan'), $continueurl, $currenturl);
                echo $OUTPUT->footer();
                die();

            }
        }
    }


    /**
     * Get course evidence items associated with required competencies
     *
     * Looks up the evidence items assigned to each competency and
     * finds any with a type of 'coursecompletion', if found, returns
     * an array of the course information.
     *
     * This is used to determine the default 'linked courses' that
     * should be added to the plan when this competency is added.
     *
     * @param array $competencies Array of competency IDs
     *
     * @return array Array of objects, keyed on the competency ids provided. Each element contains an object containing course id and name
     */
    function get_course_evidence_items($competencies) {
        global $CFG, $DB;
        // for access to evidence item type constants
        require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/lib.php');

        // invalid input
        if (!is_array($competencies)) {
            return false;
        }

        // no competencies, return empty array
        if (count($competencies) == 0) {
            return array();
        }

        list($insql, $inparams) = $DB->get_in_or_equal($competencies);
        $sql = "
            SELECT
                cc.id,
                cc.competencyid,
                cc.iteminstance AS courseid,
                c.fullname,
                cc.linktype
            FROM
                {comp_criteria} cc
            LEFT JOIN
                {course} c
             ON cc.iteminstance = c.id
            WHERE
                cc.itemtype = ?
            AND cc.competencyid $insql
        ";
        $params = array(COMPETENCY_EVIDENCE_TYPE_COURSE_COMPLETION);
        $params = array_merge($params, $inparams);
        $rs = $DB->get_recordset_sql($sql, $params);
        $out = totara_group_records($rs, 'competencyid');
        $rs->close();

        return $out;
    }


    /**
     * Displays the list of approvals pending
     *
     * @param  object  $pendingitems  the list of pending items
     * @return false|string  the table to display
     */
    function display_approval_list($pendingitems) {
        global $DB, $OUTPUT;

        $competencies = array();
        foreach ($pendingitems as $item) {
            $competencies[] = $item->competencyid;
        }
        $evidence = $this->get_course_evidence_items($competencies);

        $table = new html_table();
        $table->attributes['class'] = 'fullwidth generaltable';
        foreach ($pendingitems as $item) {
            $row = array();
            // @todo write abstracted display_item_name() and use here
            $name = format_string($item->fullname);

            // if there is competency evidence, display it below the
            // competency with checkboxes and a description
            if (array_key_exists($item->competencyid, $evidence)) {
                // @todo lang string
                $name .= html_writer::empty_tag('br');
                $name .= html_writer::tag('strong', get_string('includerelatedcourses', 'totara_plan'));
                foreach ($evidence[$item->competencyid] as $course) {
                    $message = '';
                    if ($this->plan->get_component('course')->is_item_assigned($course->courseid)) {
                        $message = ' (' . get_string('alreadyassignedtoplan', 'totara_plan') . ')';
                    }
                    $name .= html_writer::empty_tag('br');
                    // @todo add code to disable unless
                    // pulldown set to approve
                    $name .= html_writer::checkbox("linkedcourses[{$item->id}][{$course->courseid}]", "1", true) . format_string($course->fullname) . $message;
                }
                $OUTPUT->container($name, "related-courses");
            }

            $row[] = $name;
            $row[] = $this->display_approval_options($item, $item->approved);
            $table->data[] = $row;
        }
        return html_writer::table($table, true);
    }


    /**
     * Displays a list of linked competencies
     *
     * @param  array  $list  the list of linked competencies
     * @param  array  $mandatory_list the list of mandatory linked competencies (optional)
     *
     * @return false|string  $out  the table to display
     */
    function display_linked_competencies($list, $mandatory_list = null) {
        global $DB, $OUTPUT;

        if (!is_array($list) || count($list) == 0) {
            return false;
        }

        $showduedates = ($this->get_setting('duedatemode') == DP_DUEDATES_OPTIONAL ||
            $this->get_setting('duedatemode') == DP_DUEDATES_REQUIRED);
        $showpriorities =
            ($this->get_setting('prioritymode') == DP_PRIORITY_OPTIONAL ||
            $this->get_setting('prioritymode') == DP_PRIORITY_REQUIRED);
        $priorityscaleid = ($this->get_setting('priorityscale')) ? $this->get_setting('priorityscale') : -1;

        $params = array();
        $select = 'SELECT ca.*, c.fullname, csv.name AS  status, csv.sortorder AS profsort,
                   psv.name AS priorityname ';

        // get competencies assigned to this plan
        $from = "FROM {dp_plan_competency_assign} ca
            LEFT JOIN {comp} c
                   ON c.id = ca.competencyid ";
        if ($this->plan->status == DP_PLAN_STATUS_COMPLETE) {
            // Use the 'snapshot' status value
            $from .= "LEFT JOIN {comp_scale_values} csv ON ca.scalevalueid = csv.id ";
        } else {
            // Use the 'live' status value
            $from .= "LEFT JOIN {comp_record} cr
                             ON ca.competencyid = cr.competencyid AND cr.userid = ?
                      LEFT JOIN {comp_scale_values} csv
                             ON cr.proficiency = csv.id ";
            $params[] = $this->plan->userid;
        }
        $from .= "LEFT JOIN {dp_priority_scale_value} psv
                         ON (ca.priority = psv.id
                        AND psv.priorityscaleid = ? ) ";
        $params[] = $priorityscaleid;
        list($insql, $inparams) = $DB->get_in_or_equal($list);
        $where = "WHERE ca.id $insql
                    AND ca.approved = ? ";
        $params = array_merge($params, $inparams);
        $params[] = DP_APPROVAL_APPROVED;
        $sort = "ORDER BY c.fullname";

        $tableheaders = array(
            get_string('name', 'totara_plan'),
            get_string('status', 'totara_plan'),
        );
        $tablecolumns = array(
            'fullname',
            'proficiency',
        );

        if ($showpriorities) {
            $tableheaders[] = get_string('priority', 'totara_plan');
            $tablecolumns[] = 'priority';
        }

        if ($showduedates) {
            $tableheaders[] = get_string('duedate', 'totara_plan');
            $tablecolumns[] = 'duedate';
        }

        if (!$this->plan->is_complete() && $this->can_update_items()) {
            $tableheaders[] = get_string('remove', 'totara_plan', get_string('competencies', 'totara_plan'));
            $tablecolumns[] = 'remove';
        }

        $table = new flexible_table('linkedcompetencylist');
        $table->define_baseurl(qualified_me());
        $table->define_columns($tablecolumns);
        $table->define_headers($tableheaders);

        $table->set_attribute('class', 'logtable generalbox dp-plan-component-items');
        $table->setup();

        // get the scale values used for competencies in this plan
        $priorityvalues = $DB->get_records('dp_priority_scale_value', array('priorityscaleid' => $priorityscaleid), 'sortorder', 'id,name,sortorder');
        $numberrows = $DB->count_records_sql('SELECT COUNT(*) FROM (' . $select . $from . $where . ') t', $params);
        $rownumber = 0;
        if ($records = $DB->get_recordset_sql($select . $from . $where . $sort, $params)) {

            foreach ($records as $ca) {
                $proficient = $this->is_item_complete($ca);

                $row = array();
                $row[] = $this->display_item_name($ca);

                $row[] = $this->display_status($ca);

                if ($showpriorities) {
                    $row[] = $this->display_priority_as_text($ca->priority, $ca->priorityname, $priorityvalues);
                }

                if ($showduedates) {
                    $row[] = $this->display_duedate_as_text($ca->duedate);
                }

                if (!$this->plan->is_complete() && $this->can_update_items()) {
                    if (!empty($mandatory_list) && in_array($ca->id, $mandatory_list)) {
                        // If this course has a mandatory link to the compentency disable checkbox
                        $row[] = html_writer::checkbox('delete_linked_comp_assign['.$ca->id.']', '1', false,
                            get_string('mandatory', 'totara_plan'), array('disabled' => 'true'));
                    } else {
                        $row[] = html_writer::checkbox('delete_linked_comp_assign['.$ca->id.']', '1', false);
                    }
                }

                if (++$rownumber >= $numberrows) {
                    $table->add_data($row, 'last');
                } else {
                    $table->add_data($row);
                }
            }
            $records->close();

            // return instead of outputing table contents
            ob_start();
            $table->finish_html();
            $out = ob_get_contents();
            ob_end_clean();

            return $out;
        }

    }


    /**
     * Check if an item is complete
     *
     * @access  protected
     * @param   object  $item
     * @return  boolean
     */
    protected function is_item_complete($item) {

        // Get proficiencies
        if (!$proficiencies = competency::get_proficiencies($this->plan->userid)) {
            $proficiencies = array();
        }

        // If no record
        if (!array_key_exists($item->competencyid, $proficiencies)) {
            return false;
        }

        // Something wrong with get_proficiencies()
        if (!isset($proficiencies[$item->competencyid]->isproficient)) {
            return false;
        }

        return $proficiencies[$item->competencyid]->isproficient;
    }


    /**
     * Get item's proficiency value
     *
     * @access  private
     * @param   object  $item
     * @return  string
     */
    private function get_item_proficiency($item) {

        // Get proficiencies
        $proficiencies = competency::get_proficiencies($this->plan->userid);

        // If no record
        if (!array_key_exists($item->id, $proficiencies)) {
            return false;
        }

        // Something wrong with get_proficiencies()
        if (!isset($proficiencies[$item->id]->isproficient)) {
            return false;
        }

        return $proficiencies[$item->id]->proficiency;
    }


    /**
     * Display item's name
     *
     * @access  public
     * @param   object  $item
     * @return  string
     */
    public function display_item_name($item) {
        global $CFG, $OUTPUT;
        $approved = $this->is_item_approved($item->approved);

        $class = ($approved) ? '' : 'dimmed';
        $icon = $this->determine_item_icon($item);
        $img = $OUTPUT->pix_icon("/msgicons/" . $icon, '', 'totara_core', array('class' => 'competency-state-icon'));

        $link = $OUTPUT->action_link(
                new moodle_url('/totara/plan/components/' . $this->component . '/view.php',array('id' => $this->plan->id, 'itemid' => $item->id)),
                format_string($item->fullname),null, array('class' => $class)
        );

        return $img . $link;
    }


    /**
     * Display the items related icon
     *
     * @param object $item the item being checked
     * @return string
     */
    function determine_item_icon($item) {
        // @todo in future the item state will determine the icon
        return "competency-regular";
    }


    /**
     * Display details for a single competency
     *
     * @param integer $caid ID of the competency assignment (not the competency id)
     *
     * @return string HTML string to display the competency information
     */
    function display_competency_detail($caid) {
        global $DB, $OUTPUT;

        $priorityscaleid = ($this->get_setting('priorityscale')) ? $this->get_setting('priorityscale') : -1;

        $priorityenabled = $this->get_setting('prioritymode') != DP_PRIORITY_NONE;
        $duedateenabled = $this->get_setting('duedatemode') != DP_DUEDATES_NONE;

        // get competency assignment and competency details
        $sql = "SELECT ca.*, comp.*, psv.name AS priorityname
            FROM {dp_plan_competency_assign} ca
                LEFT JOIN {dp_priority_scale_value} psv
                    ON (ca.priority = psv.id
                    AND psv.priorityscaleid = ?)
                LEFT JOIN {comp} comp ON comp.id = ca.competencyid
                WHERE ca.id = ?";
        $item = $DB->get_record_sql($sql, array($priorityscaleid, $caid));

        if (!$item) {
            return get_string('error:competencynotfound', 'totara_plan');
        }

        $out = '';

        // get the priority values used for competencies in this plan
        $priorityvalues = $DB->get_records('dp_priority_scale_value', array('priorityscaleid' => $priorityscaleid), 'sortorder', 'id,name,sortorder');

        $icon = $this->determine_item_icon($item);
        $icon = $OUTPUT->pix_icon("/msgicons/" . $icon, format_string($item->fullname), 'totara_core', array('class' => "competency_state_icon"));
        $out .= $OUTPUT->heading($icon . format_string($item->fullname), 3);
        $t = new html_table();
        $t->class = "planiteminfobox";
        $cells = array();

        if ($priorityenabled && !empty($item->priority)) {
            $cells[] = new html_table_cell(get_string('priority', 'totara_plan') . ': ' . $this->display_priority_as_text($item->priority, $item->priorityname, $priorityvalues));
        }
        if ($duedateenabled && !empty($item->duedate)) {
            $cells[] = new html_table_cell(get_string('duedate', 'totara_plan') . ': ' . $this->display_duedate_as_text($item->duedate) . html_writer::empty_tag('br') . $this->display_duedate_highlight_info($item->duedate));
        }
        if ($status = $this->get_status($item->competencyid)) {
            $cells[] = new html_table_cell(get_string('status', 'totara_plan'). ': ' . format_string($status));
        }
        $rows = new html_table_row($cells);
        $t->data = array($rows);
        $out .= html_writer::table($t);
        $item->description = file_rewrite_pluginfile_urls($item->description, 'pluginfile.php',
            context_system::instance()->id, 'totara_hierarchy', 'comp', $item->id);
        $out .= html_writer::tag('p', format_text($item->description, FORMAT_HTML));

        return $out;
    }


    /**
     * Displays an items status
     *
     * @access public
     * @param object $item the item being checked
     * @return string
     */
    public function display_status($item) {
        // @todo: add colors and stuff?
        return format_string($item->status);
    }


    /**
     * Process component's settings update
     *
     * @access  public
     * @param   bool    $ajax   Is an AJAX request (optional)
     * @return  void
     */
    public function process_settings_update($ajax = false) {
        global $CFG, $USER, $DB;

        if (!confirm_sesskey()) {
            return 0;
        }
        // @todo validation notices, including preventing empty due dates
        // if duedatemode is required
        $cansetduedates = ($this->get_setting('setduedate') == DP_PERMISSION_ALLOW);
        $cansetpriorities = ($this->get_setting('setpriority') == DP_PERMISSION_ALLOW);
        $canapprovecomps = ($this->get_setting('updatecompetency') == DP_PERMISSION_APPROVE);
        $duedates = optional_param_array('duedate_competency', array(), PARAM_TEXT);
        $priorities = optional_param_array('priorities_competency', array(), PARAM_INT);
        $approved_comps = optional_param_array('approve_competency', array(), PARAM_INT);
        $reasonfordecision = optional_param_array('reasonfordecision_competency', array(), PARAM_TEXT);
        $evidences = optional_param_array('compprof_competency', array(), PARAM_INT);

        // The parameter 'linkedcourses' is coming through as a 2D array, which is unsupported by optional_param_array.
        // We are manually retrieving the variable and looping through cleaning all of the params.
        $linkedcourses = array();
        if (isset($_POST['linkedcourses'])) {
            // We know this uses _POST not _GET.
            foreach ($_POST['linkedcourses'] as $competency => $courses) {
                // Clean and add each competencyid.
                $compid = clean_param($competency, PARAM_INT);
                $linkedcourses[$compid] = array();

                // Loop through and clean/add each courseid.
                foreach ($courses as $courseid => $v) {
                    $cid = clean_param($courseid, PARAM_INT);
                    // Clean_param will return 0 if there is non-integer data in the POST.
                    if ($cid > 0) {
                        $linkedcourses[$compid][$cid] = $cid;
                    }
                }
            }
        }

        $currenturl = qualified_me();

        $oldrecords = $DB->get_records_list('dp_plan_competency_assign', 'planid', array($this->plan->id), null, 'id, planid, competencyid, approved');
        $status = true;
        $stored_records = array();
        if (!empty($evidences)) {
            // Update evidence
            foreach ($evidences as $id => $evidence) {
                if (!isset($oldrecords[$id])) {
                    continue;
                }
                $competencyid = $oldrecords[$id]->competencyid;

                if (hierarchy_can_add_competency_evidence($this->plan, $this, $this->plan->userid, $competencyid)) {
                    // Update the competency evidence
                    $details = new stdClass();

                    // Get user's current primary position and organisation (if any)
                    $posrec = $DB->get_record('pos_assignment', array('userid' => $this->plan->userid, 'type' => POSITION_TYPE_PRIMARY), 'id, positionid, organisationid');
                    if ($posrec) {
                        $details->positionid = $posrec->positionid;
                        $details->organisationid = $posrec->organisationid;
                        unset($posrec);
                    }

                    $details->assessorname = fullname($USER);
                    $details->assessorid = $USER->id;
                    $result = hierarchy_add_competency_evidence($competencyid, $this->plan->userid, $evidence, $this, $details);
                    if ($result) {
                        dp_plan_item_updated($this->plan->userid, 'competency', $competencyid);
                    }
                }
            }
        }

        if (!empty($duedates) && $cansetduedates) {
            // Update duedates
            foreach ($duedates as $id => $duedate) {
                // allow empty due dates
                if ($duedate == '' || $duedate == 'dd/mm/yy') {
                    // set all empty due dates to the plan due date
                    // if they are required
                    if ($this->get_setting('duedatemode') == DP_DUEDATES_REQUIRED) {
                        $duedateout = $this->plan->enddate;
                        $badduedates[] = $id;
                    } else {
                        $duedateout = null;
                    }
                } else {
                   $datepattern = get_string('datepickerlongyearregexphp', 'totara_core');
                    if (preg_match($datepattern, $duedate) == 0) {
                        // skip badly formatted date strings
                        $badduedates[] = $id;
                        continue;
                    }
                    $duedateout = totara_date_parse_from_format(get_string('datepickerlongyearparseformat', 'totara_core'), $duedate);
                }

                $todb = new stdClass();
                $todb->id = $id;
                $todb->duedate = $duedateout;
                $stored_records[$id] = $todb;
            }
        }

        if (!empty($priorities) && $cansetpriorities) {
            foreach ($priorities as $id => $priority) {
                $priority = (int) $priority;
                if (array_key_exists($id, $stored_records)) {
                    // add to the existing update object
                    $stored_records[$id]->priority = $priority;
                } else {
                    // create a new update object
                    $todb = new stdClass();
                    $todb->id = $id;
                    $todb->priority = $priority;
                    $stored_records[$id] = $todb;
                }
            }
        }

        if (!empty($approved_comps) && $canapprovecomps) {
            // Update approvals
            foreach ($approved_comps as $id => $approved) {
                if (!$approved) {
                    continue;
                }
                $reason = isset($reasonfordecision[$id]) ? $reasonfordecision[$id] : '' ;
                if (array_key_exists($id, $stored_records)) {
                    // add to the existing update object
                    $stored_records[$id]->approved = $approved;
                    $todb->reasonfordecision = $reason;
                } else {
                    // create a new update object
                    $todb = new stdClass();
                    $todb->id = $id;
                    $todb->approved = $approved;
                    $todb->reasonfordecision = $reason;
                    $stored_records[$id] = $todb;
                }
            }
        }

        $status = true;
        if (!empty($stored_records)) {
            $updates = '';
            $approvals = array();
            $transaction = $DB->start_delegated_transaction();

            foreach ($stored_records as $itemid => $record) {
                // Update the record
                $DB->update_record('dp_plan_competency_assign', $record);
                // if the record was updated check for linked courses
                if (isset($record->approved) && $record->approved == DP_APPROVAL_APPROVED) {
                    if (isset($linkedcourses[$record->id]) && is_array($linkedcourses[$record->id])) {
                        //   add the linked courses
                        foreach ($linkedcourses[$record->id] as $course => $unused) {
                            // add course if it's not already in this plan
                            // @todo what if course is assigned but not approved?
                            if (!$this->plan->get_component('course')->is_item_assigned($course)) {
                                $this->plan->get_component('course')->assign_new_item($course);
                            }
                            // now we need to grab the assignment ID
                            $assignmentid = $DB->get_field('dp_plan_course_assign', 'id', array('planid' => $this->plan->id, 'courseid' => $course), IGNORE_MISSING);
                            if (!$assignmentid) {
                                // something went wrong trying to assign the course
                                // don't attempt to create a relation
                                $status = false;
                                continue;
                            }
                            // create relation
                            $this->plan->add_component_relation('competency', $record->id, 'course', $assignmentid);
                        }
                    }
                }
            }

            if ($status) {
                $transaction->allow_commit();
                // Process update alerts
                foreach ($stored_records as $itemid => $record) {
                    $competency = $DB->get_record('comp', array('id' => $oldrecords[$itemid]->competencyid));
                    $compheader = html_writer::tag('p', html_writer::tag('strong', format_string($competency->fullname)). ': ') . html_writer::empty_tag('br');
                    $compprinted = false;
                    if (!empty($record->priority) && $oldrecords[$itemid]->priority != $record->priority) {
                        $oldpriority = $DB->get_field('dp_priority_scale_value', 'name', array('id' => $oldrecords[$itemid]->priority));
                        $newpriority = $DB->get_field('dp_priority_scale_value', 'name', array('id' => $record->priority));
                        $updates .= $compheader;
                        $compprinted = true;
                        $updates .= get_string('priority', 'totara_plan').' - '.get_string('changedfromxtoy',
                            'totara_plan', (object)array('before' => $oldpriority, 'after' => $newpriority)).html_writer::empty_tag('br');
                    }
                    if (!empty($record->duedate) && $oldrecords[$itemid]->duedate != $record->duedate) {
                        $updates .= $compprinted ? '' : $compheader;
                        $compprinted = true;
                        $dateformat = get_string('strftimedateshortmonth', 'langconfig');
                        $updates .= get_string('duedate', 'totara_plan').' - '.
                            get_string('changedfromxtoy', 'totara_plan', (object)array('before' => empty($oldrecords[$itemid]->duedate) ? '' :
                                    userdate($oldrecords[$itemid]->duedate, $dateformat, $CFG->timezone, false),
                                'after' => userdate($record->duedate, $dateformat, $CFG->timezone, false))).html_writer::empty_tag('br');
                    }
                    if (!empty($record->approved) && $oldrecords[$itemid]->approved != $record->approved) {
                        $approval = new stdClass();
                        $text = $compheader;
                        $text .= get_string('approval', 'totara_plan').' - '.
                            get_string('changedfromxtoy', 'totara_plan', (object)array('before' => dp_get_approval_status_from_code($oldrecords[$itemid]->approved),
                            'after' => dp_get_approval_status_from_code($record->approved))).html_writer::empty_tag('br');
                        $approval->text = $text;
                        $approval->itemname = format_string($competency->fullname);
                        $approval->before = $oldrecords[$itemid]->approved;
                        $approval->after = $record->approved;
                        $approval->reasonfordecision = $record->reasonfordecision;
                        $approvals[] = $approval;

                        // Check if we are auto marking competencies with default evidence values
                        if ($this->get_setting('autoadddefaultevidence')) {
                            if ($record->approved == DP_APPROVAL_APPROVED && $this->plan->status == DP_PLAN_STATUS_APPROVED) {
                                plan_mark_competency_default($oldrecords[$record->id]->competencyid, $this->plan->userid, $this);
                            }
                        }
                    }
                    // TODO: proficiencies ??
                    $updates .= $compprinted ? html_writer::end_tag('p') : '';
                }  // foreach

                // Send update alert
                if ($this->plan->status != DP_PLAN_STATUS_UNAPPROVED && strlen($updates)) {
                    $this->send_component_update_alert($updates);
                }

                if ($this->plan->status != DP_PLAN_STATUS_UNAPPROVED && count($approvals)>0) {
                    foreach ($approvals as $approval) {
                        $this->send_component_approval_alert($approval);

                        $action = ($approval->after == DP_APPROVAL_APPROVED) ? 'approved' : 'declined';
                        add_to_log(SITEID, 'plan', "{$action} competency", "component.php?id={$this->plan->id}&amp;c=competency", $approval->itemname);
                    }
                }
            }

            $currenturl = new moodle_url($currenturl);
            $currenturl->remove_params('badduedates');
            if (!empty($badduedates)) {
                $currenturl->params(array('badduedates' => implode(',', $badduedates)));
            }
            $currenturl = $currenturl->out();


            if ($this->plan->reviewing_pending) {
                return $status;
            }
            else {
                if ($status) {
                    $issuesnotification = '';
                    if (!empty($badduedates)) {
                        $issuesnotification .= $this->get_setting('duedatemode') == DP_DUEDATES_REQUIRED ?
                            html_writer::empty_tag('br').get_string('noteduedateswrongformatorrequired', 'totara_plan') : html_writer::empty_tag('br').get_string('noteduedateswrongformat', 'totara_plan');
                    }

                    // Do not create notification or redirect if ajax request
                    if (!$ajax) {
                        totara_set_notification(get_string('competenciesupdated', 'totara_plan').$issuesnotification, $currenturl, array('class' => 'notifysuccess'));
                    }
                } else {
                    // Do not create notification or redirect if ajax request
                    if (!$ajax) {
                        totara_set_notification(get_string('error:competenciesupdated', 'totara_plan'), $currenturl);
                    }
                }
            }
        }

        if ($this->plan->reviewing_pending) {
            return null;
        }

        // Do not redirect if ajax request
        if (!$ajax) {
            redirect($currenturl);
        }
    }


    /**
     * Returns true if any competencies use the scale given
     *
     * @param integer $scaleid
     * return boolean
     */
    public static function is_priority_scale_used($scaleid) {
        global $DB;

        $sql = "
            SELECT ca.id
            FROM {dp_plan_competency_assign} ca
            LEFT JOIN
                {dp_priority_scale_value} psv
            ON ca.priority = psv.id
            WHERE psv.priorityscaleid = ?";
        return $DB->record_exists_sql($sql, array($scaleid));
    }


    /**
     * Removes an assigned competency
     *
     * @param  int  $caid  the competency item
     * @return boolean
     */
    function remove_competency_assignment($caid) {
        global $DB;

        // Load item
        $item = $DB->get_record('dp_plan_competency_assign', array('id' => $caid));

        if (!$item) {
            return false;
        }

        if (!$this->can_delete_item($item)) {
            print_error('error:nopermissiondeletemandatorycomp', 'totara_plan');
        }

        $item->itemid = $item->id;
        return $this->unassign_item($item);
    }


    /**
     * Assign a new item to this component of the plan
     *
     * @access  public
     * @param   integer $itemid
     * @param   boolean $checkpermissions If false user permission checks are skipped (optional)
     * @param   boolean $manual Was this assignment created manually by a user? (optional)
     * @return  object  Inserted record
     */
    public function assign_new_item($itemid, $checkpermissions = true, $manual = true) {
        global $DB;
        // Get approval value for new item if required
        if ($checkpermissions) {
            if (!$permission = $this->can_update_items()) {
                print_error('error:cannotupdatecompetencies', 'totara_plan');
            }
        } else {
            $permission = DP_PERMISSION_ALLOW;
        }

        $item = new stdClass();
        $item->planid = $this->plan->id;
        $item->competencyid = $itemid;
        $item->priority = null;
        $item->duedate = null;
        $item->completionstatus = null;
        $item->grade = null;
        $item->manual = (int) $manual;

        // Check required values for priority/due data
        if ($this->get_setting('prioritymode') == DP_PRIORITY_REQUIRED) {
            $item->priority = $this->get_default_priority();
        }

        if ($this->get_setting('duedatemode') == DP_DUEDATES_REQUIRED) {
            $item->duedate = $this->plan->enddate;
        }

        // Set approved status
        if ($permission >= DP_PERMISSION_ALLOW) {
            $item->approved = DP_APPROVAL_APPROVED;
        }
        else { # $permission == DP_PERMISSION_REQUEST
            $item->approved = DP_APPROVAL_UNAPPROVED;
        }

        // Load fullname of item
        $item->fullname = $DB->get_field('comp', 'fullname', array('id' => $itemid));

        add_to_log(SITEID, 'plan', 'added competency', "component.php?id={$this->plan->id}&amp;c=competency", "Competency ID: {$itemid}");

        if ($result = $DB->insert_record('dp_plan_competency_assign', $item)) {
            $item->id = $result;

            // Check if we are auto marking competencies with default evidence values
            if ($this->get_setting('autoadddefaultevidence')) {
                if ($result && $item->approved == DP_APPROVAL_APPROVED && $this->plan->status == DP_PLAN_STATUS_APPROVED) {
                    plan_mark_competency_default($item->competencyid, $this->plan->userid, $this);
                }
            }
        }

        return $result ? $item : $result;
    }


    /**
     * Assigns competencies to a plan
     *
     * @access  public
     * @param   array   $competencies       The competencies to be assigned
     * @param   bool    $checkpermissions   If false user permission checks are skipped (optional)
     * @param   array   $relation           Optional relation type (component, id)
     * @return bool
     */
    function auto_assign_competencies($competencies, $checkpermissions = true, $relation = false) {
        global $DB;

            $transaction = $DB->start_delegated_transaction();

            // Get all currently-assigned competencies
            $assigned = $DB->get_records('dp_plan_competency_assign', array('planid' => $this->plan->id), '', 'competencyid');
            $assigned = array_keys($assigned);
            foreach ($competencies as $c) {
                // Don't assign duplicate competencies
                if (!in_array($c->id, $assigned)) {
                    // Assign competency item (false = assigned automatically)
                    if (!$assignment = $this->assign_new_item($c->id, $checkpermissions, false)) {
                        return false;
                    }
                }
                // Add relation
                if ($relation) {
                    $mandatory = $c->linktype == PLAN_LINKTYPE_MANDATORY ? 'competency' : '';
                    $this->plan->add_component_relation($relation['component'], $relation['id'], 'competency', $assignment->id, $mandatory);
                }
            }
            $transaction->allow_commit();
        return true;
    }


    /**
     * Assigns linked courses to competencies
     *
     * @param array $competencies the competencies to be assigned
     * @param  boolean $checkpermissions If false user permission checks are skipped (optional)
     * @return void
     */
    function assign_linked_courses($competencies, $checkpermissions=true) {
        global $DB;
        // get array of competency ids
        foreach ($competencies as $competency) {
            $cids[] = $competency->id;
        }
        $comp_assignments = $this->get_item_assignments();

        $evidence = $this->get_course_evidence_items($cids);
        if ($evidence) {
            foreach ($evidence as $compid => $linkedcourses) {
                foreach ($linkedcourses as $linkedcourse) {
                    $courseid = $linkedcourse->courseid;
                    if (!$this->plan->get_component('course')->is_item_assigned($courseid)) {
                        // assign the course if not already assigned
                        $this->plan->get_component('course')->assign_new_item($courseid, $checkpermissions);
                    }

                    $assignmentid = $DB->get_field('dp_plan_course_assign', 'id', array('planid' => $this->plan->id, 'courseid' => $courseid));
                    if (!$assignmentid) {
                        // something went wrong trying to assign the course
                        // don't attempt to create a relation
                        continue;
                    }

                    // also lookup the competency assignment id
                    $comp_assign_id = array_search($compid, $comp_assignments);
                    // competency doesn't seem to be assigned
                    if (!$comp_assign_id) {
                        continue;
                    }

                    // Create relation
                    $mandatory = $linkedcourse->linktype == PLAN_LINKTYPE_MANDATORY ? 'course' : '';
                    $this->plan->add_component_relation('competency', $comp_assign_id, 'course', $assignmentid, $mandatory);
                }
            }
        }
    }


    /**
     * Automatically assigns competencies and linked courses based on the users position
     *
     * @return boolean
     */
    function assign_from_pos() {
        global $CFG;
        $includecourses = $this->get_setting('autoassigncourses');
        $includecompleted = $this->get_setting('includecompleted');

        require_once($CFG->dirroot.'/totara/hierarchy/prefix/position/lib.php');

        // Get primary position
        $position_assignment = new position_assignment(
            array(
                'userid'    => $this->plan->userid,
                'type'      => POSITION_TYPE_PRIMARY
            )
        );

        if (empty($position_assignment->positionid)) {
            // No position assigned to the primary position, so just go away
            return true;
        }

        $position = new position();
        if ($includecompleted) {
            $competencies = $position->get_assigned_competencies($position_assignment->positionid);
        } else {
            $completed_competency_ids = competency::get_user_completed_competencies($this->plan->userid);
            $competencies = $position->get_assigned_competencies($position_assignment->positionid, 0, $completed_competency_ids);
        }

        if ($competencies) {
            $relation = array('component' => 'position', 'id' => $position_assignment->positionid);
            if ($this->auto_assign_competencies($competencies, false, $relation)) {
                // Assign courses
                if ($includecourses) {
                    $this->assign_linked_courses($competencies, false);
                }
            } else {
                return false;
            }
        }

        return true;
    }


    /**
     * Automatically assigns competencies and linked courses based on the users organisation
     *
     * @return boolean
     */
    function assign_from_org() {
        global $CFG;
        $includecourses = $this->get_setting('autoassigncourses');
        $includecompleted = $this->get_setting('includecompleted');

        require_once($CFG->dirroot.'/totara/hierarchy/prefix/position/lib.php');
        // Get primary position
        $position_assignment = new position_assignment(
            array(
                'userid'    => $this->plan->userid,
                'type'      => POSITION_TYPE_PRIMARY
            )
        );
        if (empty($position_assignment->organisationid)) {
            // No organisation assigned to the primary position, so just go away
            return true;
        }

        require_once($CFG->dirroot.'/totara/hierarchy/prefix/organisation/lib.php');
        $org = new organisation();
        if ($includecompleted) {
            $competencies = $org->get_assigned_competencies($position_assignment->organisationid);
        } else {
            $completed_competency_ids = competency::get_user_completed_competencies($this->plan->userid);
            $competencies = $org->get_assigned_competencies($position_assignment->organisationid, 0, $completed_competency_ids);
        }

        if ($competencies) {
            $relation = array('component' => 'organisation', 'id' => $position_assignment->organisationid);
            if ($this->auto_assign_competencies($competencies, false, $relation)) {
                // assign courses
                if ($includecourses) {
                    $this->assign_linked_courses($competencies, false);
                }
            } else {
                return false;
            }
        }

        return true;
    }


    /**
     * Display an items progress status
     *
     * @access protected
     * @param object $item the item being checked
     * @return string the items status
     */
    protected function display_list_item_progress($item) {
        if ($this->can_update_competency_evidence($item)) {
            return $this->get_competency_menu($item);
        } else {
            return $this->is_item_approved($item->approved) ? $this->display_status($item) : '';
        }
    }

    /**
     * Gets the ajax-enabled dropdown menu to set the competency's proficiency
     * TODO: This uses a lot of the same code as in rb_source_dp_competency::
     * rb_display_proficiency_and_approval_menu. It would be good to abstract
     * that out and make them both call the same function.
     * @param $item
     */
    public function get_competency_menu($item) {
        global $DB;

        // Get the info we need about the framework
        $sql = "SELECT
                    cs.defaultid as defaultid, cs.id as scaleid
                FROM {comp} c
                JOIN {comp_scale_assignments} csa
                    ON c.frameworkid = csa.frameworkid
                JOIN {comp_scale} cs
                    ON csa.scaleid = cs.id
                WHERE c.id = ?";
        $scaledetails = $DB->get_record_sql($sql, array($item->competencyid), MUST_EXIST);

        $compscale = $DB->get_records_menu('comp_scale_values', array('scaleid' => $scaledetails->scaleid), 'sortorder');

        $formatscale = array();
        foreach ($compscale as $key => $scale) {
            $formatscale[$key] = format_string($scale);
        }

        $attributes = array(); //in this case no attributes are set
        $output = html_writer::select($formatscale,
                                    "compprof_{$this->component}[{$item->id}]",
                                    $item->profscalevalueid,
                                    array(($item->profscalevalueid ? '' : 0) => ($item->profscalevalueid ? '' : get_string('notset', 'totara_hierarchy'))),
                                    $attributes);

        return $output;
    }

    /**
     * Display an items available actions
     *
     * @access protected
     * @param object $item the item being checked
     * @return string $markup the display html
     */
    protected function display_list_item_actions($item) {

        $markup = '';
        $markup .= $this->display_comp_delete_icon($item);
        $markup .= $this->display_comp_add_evidence_icon($item);

        return $markup;
    }

    /**
     * Display the icon to delete a competency.
     *
     * @param object $item
     * @return string
     */
    protected function display_comp_delete_icon($item) {
        global $OUTPUT;
        if ($this->can_delete_item($item)) {
            $currenturl = $this->get_url();
            $strdelete = get_string('delete', 'totara_plan');
            return $OUTPUT->action_icon(new moodle_url($currenturl, array('d' => $item->id, 'title' => $strdelete)), new pix_icon('/t/delete', $strdelete));
        }
        return '';
    }

    /**
     * Display the icon to add competency evidence
     *
     * @param object $item The competency (must include "approved" field)
     * @param string $returnurl The URL to tell the add evidence page to return to
     * @return $string
     */
    public function display_comp_add_evidence_icon($item, $returnurl=false) {
        global $OUTPUT;
        if ($this->can_update_competency_evidence($item)) {
            $straddevidence = get_string('setstatusicon', 'totara_plan');
            return $OUTPUT->action_icon(new moodle_url('/totara/plan/components/competency/add_evidence.php',
                array('userid' => $this->plan->userid, 'id' => $this->plan->id, 'competencyid' => $item->competencyid, 'returnurl' => $returnurl)),
                new pix_icon('/t/ranges', $straddevidence));
        }
        return '';
    }

    /**
     * Can you add competency evidence to this competency?
     * @param $item (must contain "approved" field)
     */
    public function can_update_competency_evidence($item) {
        if (!empty($item->approved)) {
            // Get permissions
            $cansetproficiency = !$this->plan->is_complete() && $this->get_setting('setproficiency') >= DP_PERMISSION_ALLOW;
            $approved = $this->is_item_approved($item->approved);
            return $cansetproficiency && $approved;
        }
        return false;
    }


    /**
     * Check to see if the competency can be deleted
     *
     * @access  public
     * @param   object  $item
     * @return  bool
     */
    public function can_delete_item($item) {

        // Check whether this course is a mandatory relation
        if ($this->is_mandatory_relation($item->id)) {
            if ($this->get_setting('deletemandatory') <= DP_PERMISSION_DENY) {
                return false;
            }
        }

        return parent::can_delete_item($item);
    }


    /*
     * Display the course picker
     *
     * @access  public
     * @param   int $competencyid the id of the competency for which selected & available courses should be displayed
     * @return  string markup for javascript course picker
     */
    public function display_course_picker($competencyid) {
        global $OUTPUT;

        if (!$permission = $this->can_update_items()) {
            return '';
        }

        $btntext_addfromplan = get_string('addlinkedcourses', 'totara_plan');
        $btntext_addfromcomp = get_string('addlinkedcoursescompetency', 'totara_plan');

        $html = $OUTPUT->container_start('buttons');
        $html .= $OUTPUT->container_start('singlebutton dp-plan-assign-button');
        $html .= $OUTPUT->container_start();
        $html .= html_writer::script('var competency_id = ' . $competencyid . ';' . 'var plan_id = ' . $this->plan->id . ';');
        $html .= $OUTPUT->single_submit($btntext_addfromplan, array('id' => "show-course-dialog"));
        $html .= $OUTPUT->single_submit($btntext_addfromcomp, array('id' => "show-course-dialog-competency"));

        $html .= $OUTPUT->container_end();
        $html .= $OUTPUT->container_end();
        $html .= $OUTPUT->container_end();

        return $html;
    }


    /*
     * Get the status of a specified competency
     *
     * @access  public
     * @param   int $compid the id of the competency for which the status should be retrieved
     * @return  string the status
     */
    public function get_status($compid) {
        global $DB;

        $sql = "SELECT csv.name
            FROM {dp_plan_competency_assign} ca ";

        if ($this->plan->is_complete()) {
            // Use the 'snapshot' status value
            $sql .= "LEFT JOIN {comp_scale_values} csv ON ca.scalevalueid = csv.id ";
        } else {
            // Use the 'live' status value
            $sql .= "
                LEFT JOIN
                    {comp_record} cr
                 ON ca.competencyid = cr.competencyid
                AND cr.userid = ?
                LEFT JOIN
                    {comp_scale_values} csv
                 ON cr.proficiency = csv.id ";
        }

        $sql .= "WHERE ca.competencyid = ? AND ca.planid = ?";

        return $DB->get_field_sql($sql, array($this->plan->userid, $compid, $this->plan->id));
    }


    /*
     * Return data about competency progress within this plan
     *
     * @return mixed Object containing stats, or false if no progress stats available
     *
     * Object should contain the following properties:
     *    $progress->complete => Integer count of number of items completed
     *    $progress->total => Integer count of total number of items in this plan
     *    $progress->text => String description of completion (for use in tooltip)
     */
    public function progress_stats() {
        global $DB;

        // array of all comp scale value ids that represent a status of proficient
        $proficient_scale_values = $DB->get_records('comp_scale_values', array('proficient' => 1));
        $proficient_ids = ($proficient_scale_values) ? array_keys($proficient_scale_values) : array();

        $completedcount = 0;
        // Get competencies assigned to this plan
        if ($competencies = $this->get_assigned_items()) {
            foreach ($competencies as $c) {
                if ($c->approved != DP_APPROVAL_APPROVED) {
                    continue;
                }

                // Determine proficiency
                $scalevalueid = $c->profscalevalueid;
                if (empty($scalevalueid)) {
                    continue;
                }

                if (in_array($scalevalueid, $proficient_ids)) {
                    $completedcount++;
                }
            }
        }
        $a = new stdClass();
        $a->count = $completedcount;
        $a->total = count($competencies);
        $progress_str =  get_string('xofy', 'totara_core', $a) . " " .
            get_string('competenciesachieved', 'totara_plan') . "\n";

        $progress = new stdClass();
        $progress->complete = $completedcount;
        $progress->total = count($competencies);
        $progress->text = $progress_str;

        return $progress;
    }


    /**
     * Reactivates competency when re-activating a plan
     *
     * @return bool
     */
    public function reactivate_items() {
        global $DB;

        $sql = "UPDATE {dp_plan_competency_assign} SET scalevalueid = 0 WHERE planid = ?";
        $DB->execute($sql, array($this->plan->id));
        return true;
    }


    /**
     * Gets all plans containing specified competency
     *
     * @param int $competencyid
     * @param int $userid
     * @return array $plans ids of plans with specified competency
     */
    public static function get_plans_containing_item($competencyid, $userid) {
        global $DB;

        $sql = "SELECT DISTINCT
                planid
            FROM
                {dp_plan_competency_assign} ca
            JOIN
                {dp_plan} p
              ON
                ca.planid = p.id
            WHERE
                ca.competencyid = ?
            AND
                p.userid = ?";

        return $DB->get_fieldset_sql($sql, array($competencyid, $userid));
    }
}
