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
 * @author Ben Lobo <ben.lobo@kineo.com>
 * @package totara
 * @subpackage plan
 */

require_once($CFG->dirroot.'/totara/program/lib.php');
require_once($CFG->dirroot.'/totara/program/program.class.php');

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

class dp_program_component extends dp_base_component {

    public static $permissions = array(
        'updateprogram' => true,
        'setpriority' => false,
        'setduedate' => false,
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

        if ($programsettings = $DB->get_record('dp_program_settings', array('templateid' => $this->plan->templateid))) {
            $settings[$this->component.'_duedatemode'] = $programsettings->duedatemode;
            $settings[$this->component.'_prioritymode'] = $programsettings->prioritymode;
            $settings[$this->component.'_priorityscale'] = $programsettings->priorityscale;
        }
    }

    /**
     * Get a single assigned item
     *
     * @access  public
     * @return  object|false
     */
    public function get_assigned_item($itemid) {
        global $DB;
        $sql = "
            SELECT
                a.id,
                a.planid,
                a.programid,
                a.id AS itemid,
                p.fullname,
                a.approved
            FROM
                {dp_plan_program_assign} a
            INNER JOIN
                {prog} p
             ON p.id = a.programid
            WHERE
                a.planid = ?
            AND a.id = ?";
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
        global $DB, $CFG;
        require_once($CFG->dirroot . '/totara/cohort/lib.php');

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

        $completion_field = 'pc.status AS programcompletion,';
        // save same value again with a new alias so the column
        // can be sorted
        $completion_field .= 'pc.status AS progress,';
        $completion_joins = "LEFT JOIN
            {prog_completion} pc
            ON ( pc.programid = a.programid
            AND pc.userid = :planuserid
            AND pc.coursesetid = 0)";
        $params['planuserid'] = $this->plan->userid;

        list($visibilitysql, $visibilityparams) = totara_visibility_where($this->plan->userid,
                                                                          'p.id',
                                                                          'p.visible',
                                                                          'p.audiencevisible',
                                                                          'p',
                                                                          'program');
        $params = array_merge($params, $visibilityparams);
        $where .= " AND {$visibilitysql} ";

        return $DB->get_records_sql(
            "
            SELECT
                a.*,
                $completion_field
                p.fullname,
                p.fullname AS name,
                p.icon,
                CASE
                    WHEN linkedevidence.count IS NULL THEN 0
                    ELSE linkedevidence.count
                END AS linkedevidence
            FROM
                {dp_plan_program_assign} a
                $completion_joins
            LEFT JOIN
                (SELECT itemid,
                    COUNT(id) AS count
                    FROM {dp_plan_evidence_relation}
                    WHERE component = 'program'
                    GROUP BY itemid) linkedevidence
                ON linkedevidence.itemid = a.id
            INNER JOIN
                {prog} p
             ON p.id = a.programid
            INNER JOIN {context} ctx ON p.id = ctx.instanceid AND ctx.contextlevel = " . CONTEXT_PROGRAM . "
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

        $where = "a.planid = :planid";
        $params = array('planid' => $this->plan->id);
        if ($approved !== null) {
            list($approvedsql, $approvedparams) = $DB->get_in_or_equal($approved, SQL_PARAMS_NAMED, 'approved');
            $where .= " AND a.approved {$approvedsql}";
            $params = array_merge($params, $approvedparams);
        }

        if ($keywords) {
            list($searchsql, $searchparams) = totara_search_get_keyword_where_clause($keywords, array('p.fullname'),
                SQL_PARAMS_NAMED);
            $params = array_merge($params, $searchparams);
            $where .= ' AND '.$searchsql;
        }

        $completion_joins = "LEFT JOIN
            {prog_completion} pc
            ON ( pc.programid = a.programid
            AND pc.userid = :planuserid
            AND pc.coursesetid = 0)";
        $params['planuserid'] = $this->plan->userid;

        $sql = "FROM
                {dp_plan_program_assign} a
                $completion_joins
            LEFT JOIN
                (SELECT itemid,
                    COUNT(id) AS count
                    FROM {dp_plan_evidence_relation}
                    WHERE component = 'program'
                    GROUP BY itemid) linkedevidence
                ON linkedevidence.itemid = a.id
            INNER JOIN
                {prog} p
             ON p.id = a.programid
            WHERE $where";

        $search_info->id = 'a.id';
        $search_info->fullname = 'p.fullname';
        $search_info->sql = $sql;
        $search_info->order = 'ORDER BY p.fullname';
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

        $delete = optional_param('d', 0, PARAM_INT); // program assignment id to delete
        $confirm = optional_param('confirm', 0, PARAM_INT); // confirm delete

        $currenturl = $this->get_url();

        if ($delete && $confirm) {
            if (!confirm_sesskey()) {
                totara_set_notification(get_string('confirmsesskeybad', 'error'), $currenturl);
            }

            // Load item
            if (!$deleteitem = $this->get_assigned_item($delete)) {
                print_error('error:couldnotfindassigneditem', 'totara_plan');
            }

            // Remove linked evidence
            $params = array('planid' => $this->plan->id, 'component' => $this->component, 'itemid' => $delete);
            $DB->delete_records('dp_plan_evidence_relation', $params);

            // Unassign item
            if ($this->unassign_item($deleteitem)) {
                add_to_log(SITEID, 'plan', 'removed program', "component.php?id={$this->plan->id}&amp;c=program", "{$deleteitem->fullname} (ID:{$deleteitem->id})");
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
        $canapproveprograms = ($this->get_setting('updateprogram') == DP_PERMISSION_APPROVE);
        $duedates = optional_param_array('duedate_program', array(), PARAM_TEXT);
        $priorities = optional_param_array('priorities_program', array(), PARAM_TEXT);
        $approved_programs = optional_param_array('approve_program', array(), PARAM_INT);
        $reasonfordecision = optional_param_array('reasonfordecision_program', array(), PARAM_TEXT);
        $currenturl = qualified_me();
        $stored_records = array();

        if (!empty($duedates) && $cansetduedates) {
            $datepickerlongyearplaceholder = get_string('datepickerlongyearplaceholder', 'totara_core');
            foreach ($duedates as $id => $duedate) {
                // allow empty due dates
                if ($duedate == '' || $duedate == $datepickerlongyearplaceholder) {
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
                    if (preg_match($datepattern, $duedate, $matches) == 0) {
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

        if (!empty($approved_programs) && $canapproveprograms) {
            // Update approvals
            foreach ($approved_programs as $id => $approved) {
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

        if (!empty($stored_records)) {
            $oldrecords = $DB->get_records_list('dp_plan_program_assign', 'id', array_keys($stored_records));

            $updates = '';
            $approvals = array();
            $transaction = $DB->start_delegated_transaction();

            foreach ($stored_records as $itemid => $record) {
                // Update the record
                $DB->update_record('dp_plan_program_assign', $record);
                // update the due date for the program completion record
                if (isset($record->duedate)) {
                    if ($prog_plan = $DB->get_record('dp_plan_program_assign', array('id' => $record->id))) {
                        $program = new program($prog_plan->programid);
                        $completionsettings = array(
                                'timedue' => $record->duedate
                                );
                        $program->update_program_complete($this->plan->userid, $completionsettings);
                    }
                }
            }
            $transaction->allow_commit();

            // Process update alerts
            foreach ($stored_records as $itemid => $record) {
                // Record the updates for later use
                $program = $DB->get_record('prog', array('id' => $oldrecords[$itemid]->programid));
                $programheader = html_writer::tag('p', html_writer::tag('strong', format_string($program->fullname).':')) . html_writer::empty_tag('br');
                $programprinted = false;
                if (!empty($record->priority) && $oldrecords[$itemid]->priority != $record->priority) {
                    $oldpriority = $DB->get_field('dp_priority_scale_value', 'name', array('id' => $oldrecords[$itemid]->priority));
                    $newpriority = $DB->get_field('dp_priority_scale_value', 'name', array('id' => $record->priority));
                    $updates .= $programheader;
                    $programprinted = true;
                    $updates .= get_string('priority', 'totara_plan').' - '.
                        get_string('changedfromxtoy', 'totara_plan', (object)array('before' => $oldpriority, 'after' => $newpriority)).html_writer::empty_tag('br');
                }
                if (!empty($record->duedate) && $oldrecords[$itemid]->duedate != $record->duedate) {
                    $updates .= $programprinted ? '' : $programheader;
                    $programprinted = true;
                    $updates .= get_string('duedate', 'totara_plan').' - '.
                        get_string('changedfromxtoy', 'totara_plan', (object)array('before' => empty($oldrecords[$itemid]->duedate) ? '' :
                                    userdate($oldrecords[$itemid]->duedate, get_string('strfdateshortmonth', 'langconfig'), $CFG->timezone, false),
                                    'after' => userdate($record->duedate, get_string('strfdateshortmonth', 'langconfig'), $CFG->timezone, false))).html_writer::empty_tag('br');
                }
                if (!empty($record->approved) && $oldrecords[$itemid]->approved != $record->approved) {
                    $approval = new stdClass();
                    $text = $programheader;
                    $text .= get_string('approval', 'totara_plan').' - '.
                        get_string('changedfromxtoy', 'totara_plan', (object)array('before' => dp_get_approval_status_from_code($oldrecords[$itemid]->approved),
                                    'after' => dp_get_approval_status_from_code($record->approved))).html_writer::empty_tag('br');
                    $approval->text = $text;
                    $approval->itemname = $program->fullname;
                    $approval->before = $oldrecords[$itemid]->approved;
                    $approval->after = $record->approved;
                    $approval->reasonfordecision = $record->reasonfordecision;
                    $approvals[] = $approval;

                }
                $updates .= $programprinted ? html_writer::end_tag('p') : '';
            }  // foreach

            if ($this->plan->status != DP_PLAN_STATUS_UNAPPROVED && count($approvals)>0) {
                foreach ($approvals as $approval) {
                    $this->send_component_approval_alert($approval);

                    $action = ($approval->after == DP_APPROVAL_APPROVED) ? 'approved' : 'declined';
                    add_to_log(SITEID, 'plan', "{$action} program", "component.php?id={$this->plan->id}&amp;c=program", $approval->itemname);
                }
            }

            // Send update alert
            if ($this->plan->status != DP_PLAN_STATUS_UNAPPROVED && strlen($updates)) {
                $this->send_component_update_alert($updates);
            }

            $currenturl = new moodle_url($currenturl);
            $currenturl->remove_params('badduedates');
            if (!empty($badduedates)) {
                $currenturl->params(array('badduedates' => implode(',', $badduedates)));
            }
            $currenturl = $currenturl->out();

            if ($this->plan->reviewing_pending) {
                return true;
            }
            else {
                $issuesnotification = '';
                if (!empty($badduedates)) {
                    $issuesnotification .= $this->get_setting('duedatemode') == DP_DUEDATES_REQUIRED ?
                        html_writer::empty_tag('br').get_string('noteduedateswrongformatorrequired', 'totara_plan') : html_writer::empty_tag('br').get_string('noteduedateswrongformat', 'totara_plan');
                }

                // Do not create notification or redirect if ajax request
                if (!$ajax) {
                    totara_set_notification(get_string('programsupdated', 'totara_plan').$issuesnotification, $currenturl, array('class' => 'notifysuccess'));
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
     * Returns true if any programs use the scale given
     *
     * @param integer $scaleid
     * return boolean
     */
    public static function is_priority_scale_used($scaleid) {
        global $DB;
        $sql = "
            SELECT pa.id
            FROM {dp_plan_program_assign} pa
            LEFT JOIN
                {dp_priority_scale_value} psv
            ON pa.priority = psv.id
            WHERE psv.priorityscaleid = ?";
        $params = array($scaleid);
        return $DB->record_exists_sql($sql, $params);
    }

    /**
     * Code to run before after header is displayed
     *
     * @access  public
     * @return  void
     */
    public function post_header_hook() {
        global $CFG, $OUTPUT;
        $delete = optional_param('d', 0, PARAM_INT); // program assignment id to delete
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
     * Assign a new program item to this plan
     *
     * @access  public
     * @param   $itemid     integer
     * @param   boolean $checkpermissions If false user permission checks are skipped (optional)
     * @param   boolean $manual Was this assignment created manually by a user? (optional)
     * @return  object  Inserted record
     */
    public function assign_new_item($itemid, $checkpermissions = true, $manual = true) {
        global $DB;

        // Get approval value for new item if required
        if ($checkpermissions) {
            if (!$permission = $this->can_update_items()) {
                print_error('error:cannotupdateprograms', 'totara_plan');
            }
        } else {
            $permission = DP_PERMISSION_ALLOW;
        }

        $item = new stdClass();
        $item->planid = $this->plan->id;
        $item->programid = $itemid;
        $item->priority = null;
        $item->duedate = null;
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
        $item->fullname = $DB->get_field('prog', 'fullname', array('id' => $itemid));

        $result = $DB->insert_record('dp_plan_program_assign', $item);
        add_to_log(SITEID, 'plan', 'added program', "component.php?id={$this->plan->id}&amp;c=program", "Program ID: {$itemid}");
        $item->id = $result;

        // create a completion record for this program for this plan's user to
        // record when the program was started and when it is due
        $program = new program($item->programid);
        $completionsettings = array(
            'status'        => STATUS_PROGRAM_INCOMPLETE,
            'timestarted'   => time(),
            'timedue'       => $item->duedate !== null ? $item->duedate : 0
        );

        $program->update_program_complete($this->plan->userid, $completionsettings);

        return $result ? $item : $result;
    }

    /**
     * First calls the parent method to unassign the program from the learning
     * plan then, if successful, deletes the completion
     *
     * @access  public
     * @return  boolean
     */
    public function unassign_item($item) {
        $userid = $this->plan->userid;

        // first unassign the program from the plan
        if ($result = parent::unassign_item($item)) {

            // create a new program instance
            $program = new program($item->programid);

            // check that the program is not also part of the user's required learning
            if ($program->assigned_to_users_required_learning($userid)) {
                return $result;
            }

            // check that the program is not assigned to any other learning plans
            if ($program->assigned_to_users_non_required_learning($userid)) {
                return $result;
            }

            // check that the program is not complete (don't delete the history record if the program has already been completed)
            if (!$program->is_program_complete($userid)) {
                $result = $program->delete_completion_record($userid);
            }
            return $result;
        }
        return $result;
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

            // Get course picker
            $jsmodule = array(
                'name' => 'totara_plan_component',
                'fullpath' => '/totara/plan/component.js',
                'requires' => array('json'));
            $PAGE->requires->js_init_call('M.totara_plan_component.init', array('args' => '{"plan_id":'.$this->plan->id.', "page":"'.$paginated.'", "component_name":"'.$component_name.'", "sesskey":"'.$sesskey.'"}'), false, $jsmodule);

            $PAGE->requires->string_for_js('save', 'totara_core');
            $PAGE->requires->string_for_js('cancel', 'moodle');
            $PAGE->requires->string_for_js('continue', 'moodle');
            $PAGE->requires->string_for_js('addprograms', 'totara_plan');

            $jsmodule = array(
                'name' => 'totara_plan_program_find',
                'fullpath' => '/totara/plan/components/program/find.js',
                'requires' => array('json'));
            $PAGE->requires->js_init_call('M.totara_plan_program_find.init', array('args' => '{"plan_id":'.$this->plan->id.'}'), false, $jsmodule);
        }
    }

    /**
     * Check if item is "complete" or "finished"
     *
     * @access  public
     * @param   object  $item
     * @return  boolean
     */
    protected function is_item_complete($item) {
        return in_array($item->programcompletion, array(STATUS_PROGRAM_COMPLETE));
    }

    /*********************************************************************************************
     *
     * Display methods
     *
     ********************************************************************************************/

    /**
     * Display progress for an item in a list
     *
     * @access protected
     * @param object $item the item to check
     * @return string the item status
     */
    protected function display_list_item_progress($item) {
        $program = new program($item->programid);
        return $this->is_item_approved($item->approved) ? $program->display_progress($this->plan->userid) : get_string('unapproved', 'totara_plan');
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
     * Display an items available actions
     *
     * @access protected
     * @param object $item the item being checked
     * @return string $markup the display markup
     */
    protected function display_list_item_actions($item) {
        global $OUTPUT;

        $markup = '';

        // Actions
        if ($this->can_delete_item($item)) {
            $currenturl = $this->get_url();
            $strdelete = get_string('delete', 'totara_plan');
            $delete = $OUTPUT->action_icon(new moodle_url($currenturl, array('d' => $item->id)), new pix_icon('/t/delete', $strdelete));
            $markup .= $delete;
        }
        return $markup;
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
        $viewingasmanager = $this->plan->role == 'manager';

        $extraparams = '';
        if ($viewingasmanager) {
            $extraparams = $this->plan->userid;
        }

        $prog = new program($item->programid);
        $accessible = $prog->is_accessible();

        $itemicon = ($item && !empty($item->icon)) ? $item->icon : 'default';
        $img = html_writer::empty_tag('img', array('src' => totara_get_icon($item->programid, TOTARA_ICON_TYPE_PROGRAM),
            'class' => 'course_icon', 'alt' => ''));
        if ($approved && $accessible) {
            $link = $OUTPUT->action_link(
                    new moodle_url('/totara/plan/components/' . $this->component . '/view.php',array('id' => $this->plan->id, 'itemid' => $item->id, 'userid' => $extraparams)),
                    format_string($item->fullname)
            );
            return $img . $link;
        } elseif (!$approved && $accessible) {
            return $img . format_string($item->fullname);
        } elseif (!$accessible) {
            return $img . html_writer::tag('span', format_string($item->fullname), array('class' => 'inaccessible'));
        }

    }

    /**
     * Display details for a single program
     *
     * @param integer $progassid ID of the program assignment (not the program id)
     * @return string HTML string to display the course information
     */
    function display_program_detail($progassid) {
        global $DB, $OUTPUT;

        $sql = "SELECT pa.*, prog.*, pc.status AS programcompletion
                FROM {dp_plan_program_assign} pa
                LEFT JOIN {prog} prog ON prog.id = pa.programid
                LEFT JOIN {prog_completion} pc ON ( prog.id = pc.programid AND pc.userid = ? AND pc.coursesetid = 0)
                WHERE pa.id = ?";
        $params = array($this->plan->userid, $progassid);
        $item = $DB->get_record_sql($sql, $params);

        if (!$item) {
            return get_string('programnotfound', 'totara_plan');
        }

        $out = '';
        $itemicon = (!empty($item->icon)) ? $item->icon : 'default';
        $icon = html_writer::empty_tag('img', array('src' => totara_get_icon($item->id, TOTARA_ICON_TYPE_PROGRAM),
            'class' => 'course_icon', 'alt' => ''));
        $out .= $OUTPUT->heading($icon . format_string($item->fullname), 3);

        $program = new program($item->id);

        $out .= $program->display($this->plan->userid);

        return $out;
    }

    /*
     * Return data about program progress within this plan
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
        // Get programs assigned to this plan
        if ($programs = $this->get_assigned_items()) {
            foreach ($programs as $p) {
                if ($p->approved != DP_APPROVAL_APPROVED) {
                    continue;
                }
                // Determine program completion
                $prog = new program($p->programid);
                if (!$prog) {
                    continue;
                }

                if ($prog->is_program_complete($this->plan->userid)) {
                    $completionsum ++;
                    $completedcount++;
                }

                if ($prog->is_program_inprogress($this->plan->userid)) {
                    $inprogresscount ++;
                }
            }
        }

        $progress_str = "{$completedcount}/" . count($programs) . " " .
            get_string('programscomplete', 'totara_program') . ", {$inprogresscount} " .
            get_string('inprogress', 'totara_plan') . "\n";

        $progress = new stdClass();
        $progress->complete = $completionsum;
        $progress->total = count($programs);
        $progress->text = $progress_str;

        return $progress;
    }


    /**
     * Gets all plans containing specified program
     *
     * @param int $programid
     * @param int $userid
     * @return array $plans ids of plans with specified program
     */
    public static function get_plans_containing_item($programid, $userid) {
        global $DB;

        $sql = "SELECT DISTINCT
                planid
            FROM
                {dp_plan_program_assign} pa
            JOIN
                {dp_plan} p
              ON
                pa.planid = p.id
            WHERE
                pa.programid = ?
            AND
                p.userid = ?";
        $params = array($programid, $userid);

        return $DB->get_fieldset_sql($sql, $params);
    }

    /**
     * Reactivates item when re-activating a plan
     *
     * @return bool $success
     */
    public function reactivate_items() {
        // TODO
        return true;
    }
}
