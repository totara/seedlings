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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @author Aaron Barnes <aaronb@catalyst.net.nz>
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage plan
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

class dp_course_component extends dp_base_component {

    public static $permissions = array(
        'updatecourse' => true,
        //'commenton' => false,
        'setpriority' => false,
        'setduedate' => false,
        'setcompletionstatus' => false,
        'deletemandatory' => false,
    );


    /**
     * Initialize settings for the component
     *
     * @access  public
     * @param   array   $settings
     * @return  void
     */
    public function initialize_settings(&$settings) {
        global $DB;

        if ($coursesettings = $DB->get_record('dp_course_settings', array('templateid' => $this->plan->templateid))) {
            $settings[$this->component.'_duedatemode'] = $coursesettings->duedatemode;
            $settings[$this->component.'_prioritymode'] = $coursesettings->prioritymode;
            $settings[$this->component.'_priorityscale'] = $coursesettings->priorityscale;
        }
    }


    /**
     * Get a single assignment
     *
     * @access  public
     * @param integer $assignmentid ID of the course assignment
     * @return  object|false
     */
    public function get_assignment($assignmentid) {
        global $DB;
        $sql = "
            SELECT
                a.*,
                c.fullname
            FROM
                {dp_plan_course_assign} a
            INNER JOIN
                {course} c
             ON c.id = a.courseid
            WHERE
                a.planid = ?
            AND a.id = ?
            ";
        $params = array($this->plan->id, $assignmentid);

        return $DB->get_record_sql($sql, $params);
    }

    /**
     * Get a single assigned item
     *
     * @access  public
     * @param integer $itemid ID of a course that is assigned to this plan
     * @return  object|false
     */
    public function get_assigned_item($itemid) {
        global $DB;

        $sql = "SELECT
                a.id,
                a.planid,
                a.courseid,
                a.id AS itemid,
                c.fullname,
                a.approved
            FROM
                {dp_plan_course_assign} a
            INNER JOIN
                {course} c
             ON c.id = a.courseid
            WHERE
                a.planid = ?
            AND c.id = ?";
        $params = array($this->plan->id, $itemid);

        return $DB->get_record_sql($sql, $params);
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

        // Generate where clause (using named parameters because of how query is built)
        $where = "a.planid = :planid";
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

        if ($this->plan->is_complete()) {
            // Use the 'snapshot' status value
            $completion_field = 'a.completionstatus AS coursecompletion,';
            // save same value again with a new alias so the column
            // can be sorted
            $completion_field .= 'a.completionstatus AS progress,';
            $completion_joins = '';
        } else {
            // Use the 'live' status value
            $completion_field = 'cc.status AS coursecompletion,';
            // save same value again with a new alias so the column
            // can be sorted
            $completion_field .= 'cc.status AS progress,';
            $completion_joins = "LEFT JOIN
                {course_completions} cc
                ON ( cc.course = a.courseid
                AND cc.userid = :planuserid )";
            $params['planuserid'] = $this->plan->userid;
        }

        list($visibilitysql, $visibilityparams) = totara_visibility_where($this->plan->userid,
                                                                          'c.id',
                                                                          'c.visible',
                                                                          'c.audiencevisible',
                                                                          'c',
                                                                          'course');
        $params = array_merge($params, $visibilityparams);
        $where .= " AND {$visibilitysql} ";

        return $DB->get_records_sql(
            "
            SELECT
                a.*,
                {$completion_field}
                c.fullname,
                c.fullname AS name,
                c.icon,
                c.enablecompletion,
                CASE
                    WHEN linkedevidence.count IS NULL THEN 0
                    ELSE linkedevidence.count
                END AS linkedevidence
            FROM
                {dp_plan_course_assign} a
                {$completion_joins}
            INNER JOIN
                {course} c
             ON c.id = a.courseid
            INNER JOIN
                {context} ctx
             ON c.id = ctx.instanceid AND ctx.contextlevel = " . CONTEXT_COURSE . "
            LEFT JOIN
                (SELECT itemid,
                    COUNT(id) AS count
                    FROM {dp_plan_evidence_relation}
                    WHERE component = 'course'
                    GROUP BY itemid) linkedevidence
                ON linkedevidence.itemid = a.id
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
    public function get_search_info(stdClass $search_info, array $keywords, $parentid = 0, $approved = null) {
        global $DB;

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

        $completion_joins = '';
        if (!$this->plan->is_complete()) {
            $completion_joins = "LEFT JOIN
                {course_completions} cc
                ON ( cc.course = a.courseid
                AND cc.userid = :planuserid )";
            $params['planuserid'] = $this->plan->userid;
        }

        $sql = "FROM
                {dp_plan_course_assign} a
                {$completion_joins}
            INNER JOIN
                {course} c
             ON c.id = a.courseid
            LEFT JOIN
                (SELECT itemid,
                    COUNT(id) AS count
                    FROM {dp_plan_evidence_relation}
                    WHERE component = 'course'
                    GROUP BY itemid) linkedevidence
                ON linkedevidence.itemid = a.id
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

        $delete = optional_param('d', 0, PARAM_INT); // course assignment id to delete
        $confirm = optional_param('confirm', 0, PARAM_INT); // confirm delete

        $currenturl = $this->get_url();

        if ($delete && $confirm) {
            if (!confirm_sesskey()) {
                totara_set_notification(get_string('confirmsesskeybad', 'error'), $currenturl);
            }

            // Load item
            if (!$deleteitem = $this->get_assignment($delete)) {
                print_error('error:couldnotfindassigneditem', 'totara_plan');
            }

            // Check mandatory permissions
            if (!$this->can_delete_item($deleteitem)) {
                print_error('error:nopermissiondeletemandatorycourse', 'totara_plan');
            }

            // Unassign item
            if ($this->unassign_item($deleteitem)) {
                add_to_log(SITEID, 'plan', 'removed course', "component.php?id={$this->plan->id}&amp;c=course", "{$deleteitem->fullname} (ID:{$deleteitem->id})");
                dp_plan_check_plan_complete(array($this->plan->id));

                // Remove linked evidence
                $params = array('planid' => $this->plan->id, 'component' => $this->component, 'itemid' => $delete);
                $DB->delete_records('dp_plan_evidence_relation', $params);

                totara_set_notification(get_string('canremoveitem', 'totara_plan'), $currenturl, array('class' => 'notifysuccess'));

            } else {
                print_error('error:couldnotunassignitem', 'totara_plan');
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
        // Put any actions that need to be perfomed when
        // a plan is created in here
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
        if ($this->can_update_items()) {
            // Setup lightbox
            local_js(array(
                TOTARA_JS_DIALOG,
                TOTARA_JS_TREEVIEW
            ));

            $component_name = required_param('c', PARAM_ALPHA);
            $paginated = optional_param('page', 0, PARAM_INT);
            $sesskey = sesskey();

            $jsmodule = array(
                'name' => 'totara_plan_component',
                'fullpath' => '/totara/plan/component.js',
                'requires' => array('json'));
            $PAGE->requires->js_init_call('M.totara_plan_component.init', array('args' => '{"plan_id":'.$this->plan->id.', "page":"'.$paginated.'", "component_name":"'.$component_name.'", "sesskey":"'.$sesskey.'"}'), false, $jsmodule);

            $PAGE->requires->string_for_js('save', 'totara_core');
            $PAGE->requires->string_for_js('cancel', 'moodle');
            $PAGE->requires->string_for_js('addcourses', 'totara_plan');

            $jsmodule = array(
                'name' => 'totara_plan_course_find',
                'fullpath' => '/totara/plan/components/course/find.js',
                'requires' => array('json'));
            $PAGE->requires->js_init_call('M.totara_plan_course_find.init', array('args' => '{"plan_id":'.$this->plan->id.'}'), false, $jsmodule);
        }
    }


    /**
     * Code to run after page header is display
     *
     * @access  public
     * @return  void
     */
    public function post_header_hook() {
        global $OUTPUT, $CFG;
        $delete = optional_param('d', 0, PARAM_INT); // course assignment id to delete
        $currenturl = $this->get_url();
        $continueurl = new moodle_url($currenturl->out(), array('d' => $delete, 'confirm' => '1', 'sesskey' => sesskey()));
        if ($delete) {
            require_once($CFG->dirroot . '/totara/plan/components/evidence/evidence.class.php');
            $evidence = new dp_evidence_relation($this->plan->id, $this->component, $delete);
            echo $evidence->display_delete_warning();

            echo $OUTPUT->confirm(get_string('confirmitemdelete', 'totara_plan'), $continueurl, $currenturl);
            echo $OUTPUT->footer();
            die();
        }
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
                print_error('error:cannotupdatecourses', 'totara_plan');
            }
        } else {
            $permission = DP_PERMISSION_ALLOW;
        }

        $item = new stdClass();
        $item->planid = $this->plan->id;
        $item->courseid = $itemid;
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
        $item->fullname = $DB->get_field('course', 'fullname', array('id' => $itemid));

        $result = $DB->insert_record('dp_plan_course_assign', $item);
        add_to_log(SITEID, 'plan', 'added course', "component.php?id={$this->plan->id}&amp;c=course", "Course ID: {$itemid}");
        $item->id = $result;

        return $item;
    }


    /**
     * Displays a list of linked courses
     *
     * @param   array   $list           The list of linked courses
     * @param   array   $mandatory_list The list of mandatory courses (optional)
     *
     * @return  false|string  $out  the table to display
     */
    function display_linked_courses($list, $mandatory_list = null) {
        global $DB;

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
        if ($this->plan->is_complete()) {
            // Use the 'snapshot' status value
            $completion_field = 'ca.completionstatus AS coursecompletion,';
            // save same value again with a new alias so the column
            // can be sorted
            $completion_field .= 'ca.completionstatus AS progress ';
            $completion_joins = '';
        } else {
            // Use the 'live' status value
            $completion_field = 'cc.status AS coursecompletion,';
            // save same value again with a new alias so the column
            // can be sorted
            $completion_field .= 'ca.completionstatus AS progress ';
            $completion_joins = "LEFT JOIN
                {course_completions} cc
                ON ( cc.course = ca.courseid
                AND cc.userid = :userid )";
            $params['userid'] = $this->plan->userid;
        }

        list($visibilitysql, $visibilityparams) = totara_visibility_where($this->plan->userid,
                                                                          'c.id',
                                                                          'c.visible',
                                                                          'c.audiencevisible',
                                                                          'c',
                                                                          'course');

        $select = "SELECT ca.*, c.fullname, c.icon, c.visible, c.audiencevisible, psv.name AS priorityname, $completion_field";

        // get courses assigned to this plan
        // and related details
        $from = "
            FROM
                {dp_plan_course_assign} ca
            LEFT JOIN
                {course} c
             ON c.id = ca.courseid
            LEFT JOIN
                {context} ctx
             ON c.id = ctx.instanceid AND ctx.contextlevel = " . CONTEXT_COURSE . "
            LEFT JOIN
                {dp_priority_scale_value} psv
            ON  (ca.priority = psv.id
            AND psv.priorityscaleid = :pscaleid )
            $completion_joins
        ";
        $params['pscaleid'] = $priorityscaleid;
        list($insql, $inparams) = $DB->get_in_or_equal($list, SQL_PARAMS_NAMED);
        $where = " WHERE ca.id $insql
            AND ca.approved = :approved ";
        $params = array_merge($params, $inparams, $visibilityparams);
        $params['approved'] = DP_APPROVAL_APPROVED;
        $where .= " AND {$visibilitysql} ";
        $sort = " ORDER BY c.fullname";

        $tableheaders = array(
            get_string('coursename', 'totara_plan'),
        );
        $tablecolumns = array(
            'fullname',
        );

        if ($showpriorities) {
            $tableheaders[] = get_string('priority', 'totara_plan');
            $tablecolumns[] = 'priority';
        }

        if ($showduedates) {
            $tableheaders[] = get_string('duedate', 'totara_plan');
            $tablecolumns[] = 'duedate';
        }

        $tableheaders[] = get_string('progress', 'totara_plan');
        $tablecolumns[] = 'progress';

        if (!$this->plan->is_complete() && $this->can_update_items()) {
            $tableheaders[] = get_string('remove', 'totara_plan', get_string('courses'));
            $tablecolumns[] = 'remove';
        }
        //start output buffering to bypass echo statements in $table->add_data()
        ob_start();
        $table = new flexible_table('linkedcourselist');
        $table->define_columns($tablecolumns);
        $table->define_headers($tableheaders);
        $table->define_baseurl($this->get_url());
        $table->set_attribute('class', 'logtable generalbox dp-plan-component-items');
        $table->setup();

        $sql = $select.$from.$where.$sort;
        if ($records = $DB->get_recordset_sql($sql, $params)) {
            $numberrows = $DB->count_records_sql('SELECT COUNT(*) FROM (' . $sql . ') t', $params);
            $rownumber = 0;
            // get the scale values used for competencies in this plan
            $priorityvalues = $DB->get_records('dp_priority_scale_value', array('priorityscaleid' => $priorityscaleid), 'sortorder', 'id,name,sortorder');

            foreach ($records as $ca) {
                $row = array();
                $row[] = $this->display_item_name($ca);

                if ($showpriorities) {
                    $row[] = $this->display_priority_as_text($ca->priority, $ca->priorityname, $priorityvalues);
                }

                if ($showduedates) {
                    $row[] = $this->display_duedate_as_text($ca->duedate);
                }

                $row[] = $this->display_status_as_progress_bar($ca);

                if (!$this->plan->is_complete() && $this->can_update_items()) {
                    //if the course is mandatory disable the delete checkbox
                    if (!empty($mandatory_list) && in_array($ca->id, $mandatory_list)) {
                        $row[] = html_writer::checkbox('delete_linked_course_assign['.$ca->id.']', '1', false,
                            get_string('mandatory', 'totara_plan'), array('disabled' => 'true'));
                    }
                    else{
                        $row[] = html_writer::checkbox('delete_linked_course_assign['.$ca->id.']', '1', false);
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
            $table->finish_html();
            $out = ob_get_contents();
            ob_end_clean();

            return $out;
        }

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

        if ($approved) {
            $class = '';
            $action_link = $OUTPUT->action_link(new moodle_url('/course/view.php', array('id' => $item->courseid)), get_string('launchcourse', 'totara_plan'), null, array('class' => 'link-as-button'));
            $launch = $OUTPUT->container(html_writer::tag('small', $action_link), "plan-launch-course-button");
        } else {
            $class = 'dimmed';
            $launch = '';
        }
        $item->icon = (empty($item->icon)) ? 'default' : $item->icon;
        $img = html_writer::empty_tag('img', array('src' => totara_get_icon($item->courseid, TOTARA_ICON_TYPE_COURSE),
            'class' => 'course_icon', 'alt' => ''));
        $url = new moodle_url('/totara/plan/components/' . $this->component . '/view.php', array('id' => $this->plan->id, 'itemid' => $item->id));
        $link = $OUTPUT->action_link($url, format_string($item->fullname), null, array('class' => $class));

        return $img . $link . $launch;
    }


    /**
     * Display details for a single course
     *
     * @param integer $caid ID of the course assignment (not the course id)
     * @return string HTML string to display the course information
     */
    function display_course_detail($caid) {
        global $DB, $OUTPUT;

        $priorityscaleid = ($this->get_setting('priorityscale')) ? $this->get_setting('priorityscale') : -1;
        $priorityenabled = $this->get_setting('prioritymode') != DP_PRIORITY_NONE;
        $duedateenabled = $this->get_setting('duedatemode') != DP_DUEDATES_NONE;

        $params = array();
        if ($this->plan->is_complete()) {
            $completion_field = 'ca.completionstatus AS coursecompletion';

            $completion_joins = '';
        } else {
            $completion_field = 'cc.status AS coursecompletion';

            $completion_joins = "LEFT JOIN {course_completions} cc
                    ON (cc.course = ca.courseid
                    AND cc.userid = :userid)";
            $params['userid'] = $this->plan->userid;
        }

        $sql = "SELECT ca.*, course.*, psv.name AS priorityname, {$completion_field}
            FROM {dp_plan_course_assign} ca
                LEFT JOIN {dp_priority_scale_value} psv
                    ON (ca.priority = psv.id
                    AND psv.priorityscaleid = :pscaleid)
                LEFT JOIN {course} course
                    ON course.id = ca.courseid
                {$completion_joins}
            WHERE ca.id = :completeid";
        $params['pscaleid'] = $priorityscaleid;
        $params['completeid'] = $caid;
        $item = $DB->get_record_sql($sql, $params);

        if (!$item) {
            return get_string('coursenotfound', 'totara_plan');
        }

        $out = '';

        // get the priority values used for competencies in this plan
        $priorityvalues = $DB->get_records('dp_priority_scale_value', array('priorityscaleid' => $priorityscaleid), 'sortorder', 'id,name,sortorder');

        if ($this->is_item_approved($item->approved)) {
            $action_link = $OUTPUT->action_link(new moodle_url('/course/view.php', array('id' => $item->courseid)), get_string('launchcourse', 'totara_plan'));
            $out = $OUTPUT->container($action_link, "plan-launch-course-button");
        }

        $icon = html_writer::empty_tag('img', array('src' => totara_get_icon($item->courseid, TOTARA_ICON_TYPE_COURSE),
            'class' => 'course_icon', 'alt' => ''));
        $out .= $OUTPUT->heading($icon . format_string($item->fullname), 3);
        $cell = array();

        if ($priorityenabled && !empty($item->priority)) {
            $cell[] = new html_table_cell(get_string('priority', 'totara_plan') . ': ' . $this->display_priority_as_text($item->priority, $item->priorityname, $priorityvalues));
        }
        if ($duedateenabled && !empty($item->duedate)) {
            $cell[] = new html_table_cell(get_string('duedate', 'totara_plan') . ': ' . $this->display_duedate_as_text($item->duedate) . html_writer::empty_tag('br') . $this->display_duedate_highlight_info($item->duedate));
        }
        if ($progressbar = $this->display_status_as_progress_bar($item)) {
            unset($completionstatus);
            $cell[] = new html_table_cell(get_string('progress', 'totara_plan'));
            $cell[] = new html_table_cell($progressbar);
        }
        $row = new html_table_row($cell);
        $table = new html_table();
        $table->data = array($row);
        $table->attributes = array('class' => 'planiteminfobox');
        $out .= html_writer::table($table);

        $item->summary = file_rewrite_pluginfile_urls($item->summary, 'pluginfile.php',
            context_course::instance($item->id)->id, 'course', 'summary', NULL);
        $out .= html_writer::tag('p', format_text($item->summary, FORMAT_HTML));

        return $out;
    }


    /**
     * Displays an items status as a progress bar
     *
     * @param object $item the item to check
     * @return string $out display markup
     */
    function display_status_as_progress_bar($item) {
        return totara_display_course_progress_icon($this->plan->userid, $item->courseid, $item->coursecompletion);
    }


    /**
     * Check if an item is complete
     *
     * @access  protected
     * @param   object  $item
     * @return  boolean
     */
    protected function is_item_complete($item) {
        return in_array($item->coursecompletion, array(COMPLETION_STATUS_COMPLETE, COMPLETION_STATUS_COMPLETEVIARPL));
    }


    /**
     * Process component's settings update
     *
     * @access  public
     * @param   bool    $ajax   Is an AJAX request (optional)
     * @return  void
     */
    public function process_settings_update($ajax = false) {
        // @todo validation notices, including preventing empty due dates
        // if duedatemode is required
        // @todo consider handling differently - currently all updates must
        // work or nothing is changed - is that the best way?
        global $CFG, $DB;

        if (!confirm_sesskey()) {
            return 0;
        }
        $cansetduedates = ($this->get_setting('setduedate') == DP_PERMISSION_ALLOW);
        $cansetpriorities = ($this->get_setting('setpriority') == DP_PERMISSION_ALLOW);
        $canapprovecourses = ($this->get_setting('updatecourse') == DP_PERMISSION_APPROVE);
        $duedates = optional_param_array('duedate_course', array(), PARAM_TEXT);
        $priorities = optional_param_array('priorities_course', array(), PARAM_TEXT);
        $approved_courses = optional_param_array('approve_course', array(), PARAM_INT);
        $reasonfordecision = optional_param_array('reasonfordecision_course', array(), PARAM_TEXT);
        $currenturl = qualified_me();
        $stored_records = array();

        if (!empty($duedates) && $cansetduedates) {
            $badduedates = array();  // Record naughty duedates
            foreach ($duedates as $id => $duedate) {
                // allow empty due dates
                if ($duedate == '' || $duedate == get_string('datepickerlongyearplaceholder', 'totara_core')) {
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

        if (!empty($priorities)) {
            foreach ($priorities as $pid => $priority) {
                $priority = (int) $priority;
                if (array_key_exists($pid, $stored_records)) {
                    // add to the existing update object
                    $stored_records[$pid]->priority = $priority;
                } else {
                    // create a new update object
                    $todb = new stdClass();
                    $todb->id = $pid;
                    $todb->priority = $priority;
                    $stored_records[$pid] = $todb;
                }
            }
        }
        if (!empty($approved_courses) && $canapprovecourses) {
            // Update approvals
            foreach ($approved_courses as $id => $approved) {
                if (!$approved) {
                    continue;
                }
                $approved = (int) $approved;
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
            $oldrecords = $DB->get_records_list('dp_plan_course_assign', 'id', array_keys($stored_records));

            $updates = '';
            $approvals = array();
                $transaction = $DB->start_delegated_transaction();

                foreach ($stored_records as $itemid => $record) {
                    // Update the record
                    $DB->update_record('dp_plan_course_assign', $record);
                }
                $transaction->allow_commit();

                // Process update alerts
                foreach ($stored_records as $itemid => $record) {
                    // Record the updates for later use
                    $course = $DB->get_record('course', array('id' => $oldrecords[$itemid]->courseid));
                    $courseheader = html_writer::tag('p', html_writer::tag('strong', format_string($course->fullname).':')) . html_writer::empty_tag('br');
                    $courseprinted = false;
                    if (!empty($record->priority) && $oldrecords[$itemid]->priority != $record->priority) {
                        $oldpriority = $DB->get_field('dp_priority_scale_value', 'name', array('id' => $oldrecords[$itemid]->priority));
                        $newpriority = $DB->get_field('dp_priority_scale_value', 'name', array('id' => $record->priority));
                        $updates .= $courseheader;
                        $courseprinted = true;
                        $updates .= get_string('priority', 'totara_plan').' - '.
                            get_string('changedfromxtoy', 'totara_plan', (object)array('before' => $oldpriority, 'after' => $newpriority)).html_writer::empty_tag('br');
                    }
                    if (!empty($record->duedate) && $oldrecords[$itemid]->duedate != $record->duedate) {
                        $updates .= $courseprinted ? '' : $courseheader;
                        $courseprinted = true;
                        $updates .= get_string('duedate', 'totara_plan').' - '.
                            get_string('changedfromxtoy', 'totara_plan', (object)array('before' => empty($oldrecords[$itemid]->duedate) ? '' :
                                userdate($oldrecords[$itemid]->duedate, get_string('strfdateshortmonth', 'langconfig'), $CFG->timezone, false),
                            'after' => userdate($record->duedate, get_string('strfdateshortmonth', 'langconfig'), $CFG->timezone, false))).html_writer::empty_tag('br');
                    }
                    if (!empty($record->approved) && $oldrecords[$itemid]->approved != $record->approved) {
                        $approval = new stdClass();
                        $text = $courseheader;
                        $text .= get_string('approval', 'totara_plan').' - '.
                            get_string('changedfromxtoy', 'totara_plan', (object)array('before' => dp_get_approval_status_from_code($oldrecords[$itemid]->approved),
                            'after' => dp_get_approval_status_from_code($record->approved))).html_writer::empty_tag('br');
                        $approval->text = $text;
                        $approval->itemname = $course->fullname;
                        $approval->before = $oldrecords[$itemid]->approved;
                        $approval->after = $record->approved;
                        $approval->reasonfordecision = $record->reasonfordecision;
                        $approvals[] = $approval;

                    }
                    $updates .= $courseprinted ? html_writer::end_tag('p') : '';
                }  // foreach

                if ($this->plan->status != DP_PLAN_STATUS_UNAPPROVED && count($approvals)>0) {
                    foreach ($approvals as $approval) {
                        $this->send_component_approval_alert($approval);

                        $action = ($approval->after == DP_APPROVAL_APPROVED) ? 'approved' : 'declined';
                        add_to_log(SITEID, 'plan', "{$action} course", "component.php?id={$this->plan->id}&amp;c=course", $approval->itemname);
                    }
                }

                // Send update alert
                if ($this->plan->status != DP_PLAN_STATUS_UNAPPROVED && strlen($updates)) {
                    $this->send_component_update_alert($updates);
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
                        totara_set_notification(get_string('coursesupdated', 'totara_plan').$issuesnotification, $currenturl, array('class' => 'notifysuccess'));
                    }
                } else {
                    // Do not create notification or redirect if ajax request
                    if (!$ajax) {
                        totara_set_notification(get_string('coursesnotupdated', 'totara_plan'), $currenturl);
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
     * Returns true if any courses use the scale given
     *
     * @param integer $scaleid
     * return boolean
     */
    public static function is_priority_scale_used($scaleid) {
        global $DB;

        $sql = "
            SELECT ca.id
            FROM {dp_plan_course_assign} ca
            LEFT JOIN
                {dp_priority_scale_value} psv
            ON ca.priority = psv.id
            WHERE psv.priorityscaleid = ?";
        return $DB->record_exists_sql($sql, array($scaleid));
    }


    /**
     * Get headers for a list
     *
     * @return array $headers
     */
    function get_list_headers() {
        $headers = parent::get_list_headers();

        foreach ($headers->headers as $i => $h) {
            if ($h == get_string('status', 'totara_plan')) {
                // Replace 'Status' header with 'Progress'
                $headers->headers[$i] = get_string('progress', 'totara_plan');
                break;
            }
        }

        return $headers;
    }


    /**
     * Display progress for an item in a list
     *
     * @access protected
     * @param object $item the item to check
     * @return string the item status
     */
    protected function display_list_item_progress($item) {
        return $this->is_item_approved($item->approved) ? $this->display_status_as_progress_bar($item) : '';
    }


    /**
     * Display an items available actions
     *
     * @access protected
     * @param object $item the item being checked
     * @return string $markup the display markup
     */
    protected function display_list_item_actions($item) {
        global $OUTPUT, $CFG;

        $markup = '';

        // Get permissions
        $cansetcompletion = !$this->plan->is_complete() && $this->get_setting('setcompletionstatus') >= DP_PERMISSION_ALLOW;

        // Check course has completion enabled
        $course = new stdClass();
        $course->id = $item->courseid;
        $course->enablecompletion = $item->enablecompletion;
        $cinfo = new completion_info($course);

        // Only allow setting an RPL if completion is enabled for the site and course
        $cansetcompletion = $cansetcompletion && $cinfo->is_enabled();

        $approved = $this->is_item_approved($item->approved);

        // Actions
        if ($this->can_delete_item($item)) {
            $strdelete = get_string('delete', 'totara_plan');
            $currenturl = $this->get_url();
            $currenturl->params(array('d' => $item->id, 'title' => $strdelete));
            $delete = $OUTPUT->action_icon($currenturl, new pix_icon('/t/delete', $strdelete));
            $markup .= $delete;
        }

        if ($cansetcompletion && $approved && $CFG->enablecourserpl) {
            $strrpl = get_string('addrpl', 'totara_plan');
            $proficient = $OUTPUT->action_icon(new moodle_url('/totara/plan/components/course/rpl.php', array('id' => $this->plan->id, 'courseid' => $item->courseid)),
                new pix_icon('/t/ranges', $strrpl));
            $markup .= $proficient;
        }

        return $markup;
    }

    /*
     * Return data about course progress within this plan
     *
     * @return mixed Object containing stats, or false if no progress stats available
     *
     * Object should contain the following properties:
     *    $progress->complete => Integer count of number of items completed
     *    $progress->total => Integer count of total number of items in this plan
     *    $progress->text => String description of completion (for use in tooltip)
     */
    public function progress_stats() {

        $completedcount = 0;
        $completionsum = 0;
        $inprogresscount = 0;
        // Get courses assigned to this plan
        if ($courses = $this->get_assigned_items()) {
            foreach ($courses as $c) {
                if ($c->approved != DP_APPROVAL_APPROVED) {
                    continue;
                }
                // Determine course completion
                if (empty($c->coursecompletion)) {
                    continue;
                }
                switch ($c->coursecompletion) {
                    case COMPLETION_STATUS_COMPLETE:
                    case COMPLETION_STATUS_COMPLETEVIARPL:
                        $completionsum += 1;
                        $completedcount++;
                        break;
                    case COMPLETION_STATUS_INPROGRESS:
                        $inprogresscount++;
                        break;
                    default:
                }
            }
        }

        $progress_str = "{$completedcount}/" . count($courses) . " " .
            get_string('coursescomplete', 'totara_plan') . ", {$inprogresscount} " .
            get_string('inprogress', 'totara_plan') . "\n";

        $progress = new stdClass();
        $progress->complete = $completionsum;
        $progress->total = count($courses);
        $progress->text = $progress_str;

        return $progress;
    }


    /**
     * Reactivates course when re-activating a plan
     *
     * @return bool
     */
    public function reactivate_items() {
        global $DB;

        $sql = "UPDATE {dp_plan_course_assign} SET completionstatus = null WHERE planid = ?";

        return $DB->execute($sql, array($this->plan->id));
    }


    /**
     * Gets all plans containing specified course
     *
     * @param int $courseid
     * @param int $userid
     * @return array $plans ids of plans with specified course
     */
    public static function get_plans_containing_item($courseid, $userid) {
        global $DB;

        $sql = "SELECT DISTINCT
                planid
            FROM
                {dp_plan_course_assign} ca
            JOIN
                {dp_plan} p
              ON
                ca.planid = p.id
            WHERE
                ca.courseid = ?
            AND
                p.userid = ?";

        return $DB->get_fieldset_sql($sql, array($courseid, $userid));
    }

    /*
     * Display the competency picker
     *
     * @access  public
     * @param   int $competencyid the id of the competency for which selected & available courses should be displayed
     * @return  string markup for javascript course picker
     */
    public function display_competency_picker($courseid) {
        global $OUTPUT;

        if (!$permission = $this->can_update_items()) {
            return '';
        }

        $btntext = get_string('addlinkedcompetencies', 'totara_plan');

        $html = $OUTPUT->container_start('buttons');
        $html .= $OUTPUT->container_start('singlebutton dp-plan-assign-button');
        $html .= $OUTPUT->container_start();
        $html .= html_writer::script('var competency_id = ' . $courseid . ';' . 'var plan_id = ' . $this->plan->id);
        $html .= $OUTPUT->single_submit($btntext, array('id' => "show-competency-dialog"));

        $html .= $OUTPUT->container_end();
        $html .= $OUTPUT->container_end();
        $html .= $OUTPUT->container_end();

        return $html;
    }


    /**
     * Check to see if the course can be deleted
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
}
