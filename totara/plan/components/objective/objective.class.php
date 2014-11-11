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
 * @author Aaron Wells <aaronw@catalyst.net.nz>
 * @package totara
 * @subpackage plan
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->dirroot.'/totara/plan/lib.php');

class dp_objective_component extends dp_base_component {

    public static $permissions = array(
        'updateobjective' => true,
        //'commenton' => false,
        'setpriority' => false,
        'setduedate' => false,
        'setproficiency' => false
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
        $this->defaultname = get_string('objectives', 'totara_plan');
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
        if ($objectivesettings = $DB->get_record('dp_objective_settings', array('templateid' => $this->plan->templateid))) {
            $settings[$this->component.'_duedatemode'] = $objectivesettings->duedatemode;
            $settings[$this->component.'_prioritymode'] = $objectivesettings->prioritymode;
            $settings[$this->component.'_priorityscale'] = $objectivesettings->priorityscale;
            $settings[$this->component.'_objectivescale'] = $objectivesettings->objectivescale;
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
        $where = "a.planid = :planid";
        $params = array('planid' => $this->plan->id);
        if ($approved !== null) {
            list($approvedsql, $approvedparams) = $DB->get_in_or_equal($approved, SQL_PARAMS_NAMED, 'approved');
            $where .= " AND a.approved $approvedsql";
            $params = array_merge($params, $approvedparams);
        }

        // Generate order by clause
        if ($orderby) {
            $orderby = "ORDER BY $orderby";
        }

        // Generate status code
        $status = "LEFT JOIN {dp_objective_scale_value} osv ON a.scalevalueid = osv.id ";
        $sql = "SELECT
                a.*,
                a.scalevalueid AS progress,
                a.fullname AS name,
                CASE WHEN linkedcourses.count IS NULL
                    THEN 0 ELSE linkedcourses.count
                END AS linkedcourses,
                CASE
                    WHEN linkedevidence.count IS NULL THEN 0
                    ELSE linkedevidence.count
                END AS linkedevidence,
                osv.achieved
            FROM
                {dp_plan_objective} a
            LEFT JOIN
                (SELECT itemid2 AS assignid,
                    count(id) AS count
                    FROM {dp_plan_component_relation}
                    WHERE component2 = :comp1 AND
                        component1 = :comp2
                    GROUP BY itemid2) linkedcourses
                ON linkedcourses.assignid = a.id
            LEFT JOIN
                (SELECT itemid,
                    COUNT(id) AS count
                    FROM {dp_plan_evidence_relation}
                    WHERE component = 'objective'
                    GROUP BY itemid) linkedevidence
                ON linkedevidence.itemid = a.id
            $status
            WHERE
                $where
                $orderby
            ";
        $params['comp1'] = 'objective';
        $params['comp2'] = 'course';
        return $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);

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
            $where .= " AND a.approved $approvedsql";
            $params = array_merge($params, $approvedparams);
        }

        if ($keywords) {
            list($searchsql, $searchparams) = totara_search_get_keyword_where_clause($keywords, array('a.fullname'),
                SQL_PARAMS_NAMED);
            $params = array_merge($params, $searchparams);
            $where .= ' AND '.$searchsql;
        }

        // Generate status code.
        $status = "LEFT JOIN {dp_objective_scale_value} osv ON a.scalevalueid = osv.id ";
        $sql = "FROM
                {dp_plan_objective} a
            LEFT JOIN
                (SELECT itemid2 AS assignid,
                    count(id) AS count
                    FROM {dp_plan_component_relation}
                    WHERE component2 = :comp1 AND
                        component1 = :comp2
                    GROUP BY itemid2) linkedcourses
                ON linkedcourses.assignid = a.id
            LEFT JOIN
                (SELECT itemid,
                    COUNT(id) AS count
                    FROM {dp_plan_evidence_relation}
                    WHERE component = 'objective'
                    GROUP BY itemid) linkedevidence
                ON linkedevidence.itemid = a.id
            $status
            WHERE
                $where";
        $params['comp1'] = 'objective';
        $params['comp2'] = 'course';

        $search_info->id = 'a.id';
        $search_info->fullname = 'a.fullname';
        $search_info->sql = $sql;
        $search_info->order = 'ORDER BY a.fullname';
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
        // Put any relevant actions that should be performed
        // on this component in here
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

        }
    }


    /**
     * Generates a flexibletable of details for all the specified linked objectives
     * of a component
     *
     * @global object $CFG
     * @param array $list of objective ids
     * @return string
     */
    function display_linked_objectives($list) {
        global $DB;

        if (!is_array($list) || count($list) == 0) {
            return false;
        }

        $showduedates = ($this->get_setting('duedatemode') == DP_DUEDATES_OPTIONAL ||
            $this->get_setting('duedatemode') == DP_DUEDATES_REQUIRED);
        $showpriorities = ($this->get_setting('prioritymode') == DP_PRIORITY_OPTIONAL ||
            $this->get_setting('prioritymode') == DP_PRIORITY_REQUIRED);
        $priorityscaleid = ($this->get_setting('priorityscale')) ? $this->get_setting('priorityscale') : -1;

        $objectivename = get_string('objective', 'totara_plan');

        // Get data
        $select = 'SELECT po.*, po.fullname AS objname,
            osv.name AS proficiency, psv.name AS priorityname ';
        $from = "FROM {dp_plan_objective} po
            LEFT JOIN {dp_objective_scale_value} osv ON po.scalevalueid = osv.id
            LEFT JOIN {dp_priority_scale_value} psv
                ON po.priority = psv.id AND psv.priorityscaleid = ? ";
        $params = array($priorityscaleid);
        list($insql, $inparams) = $DB->get_in_or_equal($list);
        $where = "WHERE po.id $insql ";
        $params = array_merge($params, $inparams);
        $sort = "ORDER BY po.fullname";
        $records = $DB->get_recordset_sql($select . $from . $where . $sort, $params);
        $numberrows = $DB->count_records_sql('SELECT COUNT(*) FROM (' . $select . $from . $where . ') t', $params);
        $rownumber = 0;

        // get the scale values used for competencies in this plan
        $priorityvalues = $DB->get_records('dp_priority_scale_value', array('priorityscaleid' => $priorityscaleid), 'sortorder', 'id,name,sortorder');

        // Set up table
        $tableheaders = array(
            get_string('name'),
            get_string('status', 'totara_plan'),
        );
        $tablecolumns = array(
            'fullname',
            'proficiency',
        );

        if ($showpriorities) {
            $tableheaders[] = get_string('priority', 'totara_plan');
            $tablecolumns[] = 'priorityname';
        }

        if ($showduedates) {
            $tableheaders[] = get_string('duedate', 'totara_plan');
            $tablecolumns[] = 'duedate';
        }

        $table = new flexible_table('linkedobjectivelist');
        $table->define_baseurl(qualified_me());
        $table->define_columns($tablecolumns);
        $table->define_headers($tableheaders);

        $table->set_attribute('class', 'logtable generalbox dp-plan-component-items');
        $table->setup();

        foreach ($records as $o) {
            $row[] = $this->display_item_name($o);
            $row[] = $o->proficiency;
            if ($showpriorities) {
                $row[] = $this->display_priority_as_text($o->priority, $o->priorityname, $priorityvalues);
            }
            if ($showduedates) {
                $row[] = $this->display_duedate_as_text($o->duedate);
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


    /**
     * Display item's name
     *
     * @access  public
     * @param   object  $item
     * @return  string
     */
    function display_item_name($item) {
        global $CFG, $OUTPUT;
        $approved = $this->is_item_approved($item->approved);

        $class = ($approved) ? '' : 'dimmed';
        $icon = $this->determine_item_icon($item);
        $img = $OUTPUT->pix_icon("/msgicons/" . $icon, format_string($item->fullname), 'totara_core', array('class' => 'objective-state-icon'));
        $link = $OUTPUT->action_link(
                new moodle_url('/totara/plan/components/' . $this->component . '/view.php', array('id' => $this->plan->id, 'itemid' => $item->id)),
                format_string($item->fullname), null, array('class' => $class)
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
        return "objective-regular";
    }


    /**
     * Create a form object for the data in an objective
     * @global object $CFG
     * @param int $objectiveid
     * @return plan_objective_edit_form
     */
    function objective_form($objectiveid=null) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/totara/plan/components/objective/edit_form.php');
        $customdata = array(
            'plan' => $this->plan,
            'objective' => $this
        );
        if (empty($objectiveid)) {
            return new plan_objective_edit_form( null, $customdata );
        } else {

            if (!$objective = $DB->get_record('dp_plan_objective', array('id' => $objectiveid))) {
                print_error('error:objectiveidincorrect', 'totara_plan');
            }
            $objective->itemid = $objective->id;
            $objective->id = $objective->planid;
            unset($objective->planid);

            $mform = new plan_objective_edit_form(
                    null,
                    array(
                        'plan' => $this->plan,
                        'objective' => $this,
                        'objectiveid' => $objectiveid
                    )
            );
            $mform->set_data($objective);
            return $mform;
        }
    }


    /**
     * Process component's settings update
     *
     * @access  public
     * @param   bool    $ajax   Is an AJAX request (optional)
     * @return  void
     */
    public function process_settings_update($ajax = false) {
        global $CFG, $DB;

        if (!confirm_sesskey()) {
            return 0;
        }
        // @todo validation notices, including preventing empty due dates
        // if duedatemode is required
        $cansetduedates = ($this->get_setting('setduedate') == DP_PERMISSION_ALLOW);
        $cansetpriorities = ($this->get_setting('setpriority') == DP_PERMISSION_ALLOW);
        $cansetprofs = ($this->get_setting('setproficiency') == DP_PERMISSION_ALLOW);
        $canapprovecomps = ($this->get_setting('updateobjective') == DP_PERMISSION_APPROVE);
        $duedates = optional_param_array('duedate_objective', array(), PARAM_TEXT);
        $priorities = optional_param_array('priorities_objective', array(), PARAM_INT);
        $proficiencies = optional_param_array('proficiencies', array(), PARAM_INT);
        $approved_objectives = optional_param_array('approve_objective', array(), PARAM_INT);
        $reasonfordecision = optional_param_array('reasonfordecision_objective', array(), PARAM_TEXT);
        $currenturl = qualified_me();
        $currentuser = $this->plan->userid;
        $stored_records = array();
        $status = true;
        if (!empty($duedates) && $cansetduedates) {
            // Update duedates
            foreach ($duedates as $id => $duedate) {
                // allow empty due dates
                if ($duedate == '' || $duedate == get_string('datepickerlongyearplaceholder', 'totara_core')) {
                    if ($this->get_setting('duedatemode') == DP_DUEDATES_REQUIRED) {
                        $duedateout = $this->plan->enddate;
                    } else {
                        $duedateout = null;
                    }
                } else {
                    $datepattern = get_string('datepickerlongyearregexphp', 'totara_core');
                    if (preg_match($datepattern, $duedate, $matches) == 0) {
                        // skip badly formatted date strings
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

        if (!empty($proficiencies) && $cansetprofs) {
            foreach ($proficiencies as $id => $proficiency) {
                $proficiency = (int) $proficiency;
                if (array_key_exists($id, $stored_records)) {
                    // add to the existing update object
                    $stored_records[$id]->scalevalueid = $proficiency;
                } else {
                    // Create a new update object
                    $todb = new stdClass();
                    $todb->id = $id;
                    $todb->scalevalueid = $proficiency;
                    $stored_records[$id] = $todb;
                }
                require_once($CFG->dirroot.'/blocks/totara_stats/locallib.php');
                $count = $DB->count_records('block_totara_stats', array('userid' => $currentuser, 'eventtype' => STATS_EVENT_OBJ_ACHIEVED, 'data2' => $id));
                $scalevalue = $DB->get_record('dp_objective_scale_value', array('id' => $proficiency));
                if (empty($scalevalue)) {
                    print_error('error:priorityscalevalueidincorrect', 'totara_plan');
                    // checks objective can only be achieved once.
                } else if ($scalevalue->achieved == 1 && $count < 1) {
                    totara_stats_add_event(time(), $currentuser, STATS_EVENT_OBJ_ACHIEVED, '', $id);
                    // checks objective exists for removal
                } else if ($scalevalue->achieved == 0 && $count > 0) {
                    totara_stats_remove_event($currentuser, STATS_EVENT_OBJ_ACHIEVED, $id);
                }
            }
        }

        if (!empty($approved_objectives) && $canapprovecomps) {
            // Update approvals
            foreach ($approved_objectives as $id => $approved) {
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

        // save before snapshot of objectives

        if (!empty($stored_records)) {
            $orig_objectives = $DB->get_records_list('dp_plan_objective', 'id', array_keys($stored_records));
            $transaction = $DB->start_delegated_transaction();

            foreach ($stored_records as $itemid => $record) {
                $DB->update_record('dp_plan_objective', $record);
                if (isset($record->scalevalueid)) {
                    $scale_value_record = $DB->get_record('dp_objective_scale_value', array('id' => $record->scalevalueid));
                    if ($scale_value_record->achieved == 1) {
                        dp_plan_item_updated($currentuser, 'objective', $id);
                    }
                }
            }
            $transaction->allow_commit();

            // Process update alerts
            $updates = '';
            $approvals = array();
            $objheader = html_writer::tag('strong', format_string($orig_objectives[$itemid]->fullname) . ': ');
            $objheader = html_writer::tag('p', $objheader);
            $objprinted = false;
            $currentuserobj = $DB->get_record('user', array('id' => $currentuser));
            $stringmanager = get_string_manager();
            foreach ($stored_records as $itemid => $record) {
                // priority may have been updated
                if (!empty($record->priority) && array_key_exists($itemid, $orig_objectives) &&
                    $record->priority != $orig_objectives[$itemid]->priority) {

                    $oldpriority = $DB->get_field('dp_priority_scale_value', 'name', array('id' => $orig_objectives[$itemid]->priority));
                    $newpriority = $DB->get_field('dp_priority_scale_value', 'name', array('id' => $record->priority));
                    $updates .= $objheader;
                    $objprinted = true;
                    $updates .= get_string('priority', 'totara_plan').' - '.
                        get_string('changedfromxtoy', 'totara_plan',
                        (object)array('before' => $oldpriority, 'after' => $newpriority)) . html_writer::empty_tag('br');
                }

                // duedate may have been updated
                if (!empty($record->duedate) && array_key_exists($itemid, $orig_objectives) &&
                     $record->duedate != $orig_objectives[$itemid]->duedate) {
                    $updates .= $objprinted ? '' : $objheader;
                    $objprinted = true;
                    $updates .= $stringmanager->get_string('duedate', 'totara_plan', null, $currentuserobj->lang).' - '.
                            get_string('changedfromxtoy', 'totara_plan',
                            (object)array('before'=>empty($orig_objectives[$itemid]->duedate) ? '' :
                                userdate($orig_objectives[$itemid]->duedate, get_string('strftimedate'), $CFG->timezone, false),
                                'after' => userdate($record->duedate, get_string('strftimedate'), $CFG->timezone, false))) . html_writer::empty_tag('br');
                }

                // proficiency may have been updated
                if (!empty($record->scalevalueid) && array_key_exists($itemid, $orig_objectives) &&
                    $record->scalevalueid != $orig_objectives[$itemid]->scalevalueid) {

                    $oldprof = $DB->get_field('dp_objective_scale_value', 'name', array('id' => $orig_objectives[$itemid]->scalevalueid));
                    $newprof = $DB->get_field('dp_objective_scale_value', 'name', array('id' => $record->scalevalueid));
                    $updates .= $objprinted ? '' : $objheader;
                    $objprinted = true;
                    $updates .= $stringmanager->get_string('status', 'totara_plan', null, $currentuserobj->lang).' - '.
                        get_string('changedfromxtoy', 'totara_plan',
                        (object)array('before' => $oldprof, 'after' => $newprof)) . html_writer::empty_tag('br');
                }

                // approval status change
                if (!empty($record->approved) && array_key_exists($itemid, $orig_objectives) &&
                        $record->approved != $orig_objectives[$itemid]->approved) {
                    $approval = new stdClass();
                    $text = $objheader;
                    $text .= $stringmanager->get_string('approval', 'totara_plan', null, $currentuserobj->lang).' - '.
                            get_string('changedfromxtoy', 'totara_plan',
                            (object)array('before'=>dp_get_approval_status_from_code($orig_objectives[$itemid]->approved),
                            'after' => dp_get_approval_status_from_code($record->approved))) . html_writer::empty_tag('br');
                    $approval->text = $text;
                    $approval->itemname = $orig_objectives[$itemid]->fullname;
                    $approval->before = $orig_objectives[$itemid]->approved;
                    $approval->after = $record->approved;
                    $approval->reasonfordecision = $record->reasonfordecision;
                    $approvals[] = $approval;
                }
            }  // foreach

            // Send update alert
            if ($this->plan->status != DP_PLAN_STATUS_UNAPPROVED && strlen($updates)) {
                $this->send_component_update_alert($updates);
            }

            if ($this->plan->status != DP_PLAN_STATUS_UNAPPROVED && count($approvals)>0) {
                foreach ($approvals as $approval) {
                    $this->send_component_approval_alert($approval);
                    $action = ($approval->after == DP_APPROVAL_APPROVED) ? 'approved' : 'declined';
                    add_to_log(SITEID, 'plan', "{$action} objective", "component.php?id={$this->plan->id}&amp;c=objective", $approval->itemname);
                }
            }

            if ($this->plan->reviewing_pending) {
                return true;
            } else if (!$ajax) {
                totara_set_notification(get_string('objectivesupdated', 'totara_plan'), $currenturl, array('class' => 'notifysuccess'));
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
     * Returns true if any objectives use the scale given
     *
     * @param integer $scaleid
     * return boolean
     */
    public static function is_priority_scale_used($scaleid) {
        global $DB;

        $sql = "
            SELECT o.id
            FROM {dp_plan_objective} o
            LEFT JOIN
                {dp_priority_scale_value} psv
            ON o.priority = psv.id
            WHERE psv.priorityscaleid = ?";
        $params = array($scaleid);
        return $DB->record_exists_sql($sql, $params);
    }


    /**
     * Completely delete an objective
     * @param int $caid
     * @return true|exception
     */
    function delete_objective($caid) {
        global $USER, $DB;
        // need permission to remove this objective
        if (!$this->can_update_items()) {
            return false;
        }

        // store objective details for alerts
        $objective = $DB->get_record('dp_plan_objective', array('id' => $caid));

        $transaction = $DB->start_delegated_transaction();

        $DB->delete_records('dp_plan_objective', array('id' => $caid));
        $DB->delete_records('dp_plan_component_relation', array('component1' => 'objective', 'itemid1' => $caid));
        $DB->delete_records('dp_plan_component_relation', array('component2' => 'objective', 'itemid2' => $caid));

        // Remove linked evidence
        $params = array('planid' => $this->plan->id, 'component' => 'objective', 'itemid' => $caid);
        $DB->delete_records('dp_plan_evidence_relation', $params);

        $transaction->allow_commit();

        // are we OK? then send the alerts
        add_to_log(SITEID, 'plan', 'deleted objective', "component.php?id={$this->plan->id}&amp;c=objective", "{$objective->fullname} (ID:{$caid})");
        $this->send_deletion_alert($objective);
        dp_plan_check_plan_complete(array($this->plan->id));

        return true;
    }

    /**
     * Create a new objective. (Does not check for permissions)
     *
     * @param string $fullname Name of the objective
     * @param string $description A description of the objective (optional)
     * @param int $priority The objective's priority scale value (optional)
     * @param int $duedate The objective's due date (optional)
     * @param int $scalevalueid The objective's objective scale value (optional)
     *
     * @return true|exception
     */
    public function create_objective($fullname, $description=null, $priority=null, $duedate=null, $scalevalueid=null) {
        global $USER, $DB;
        if (!$this->can_update_items()) {
            return false;
        }

        $rec = new stdClass();
        $rec->planid = $this->plan->id;
        $rec->fullname = $fullname;
        $rec->description = $description;
        $rec->priority = $priority;
        $rec->duedate = $duedate;
        $rec->scalevalueid = $scalevalueid ? $scalevalueid : $DB->get_field('dp_objective_scale', 'defaultid', array('id' => $this->get_setting('objectivescale')));
        $rec->approved = $this->approval_status_after_update();

        $newid = $DB->insert_record('dp_plan_objective', $rec);
        $this->send_creation_alert($newid, $fullname);
        add_to_log(SITEID, 'plan', 'added objective', "component.php?id={$rec->planid}&amp;c=objective", $rec->fullname);
        dp_plan_item_updated($USER->id, 'objective', $newid);

        return $newid;
    }

    /**
     * send objective deletion alert
     * @param object $objective Objective details
     * @return nothing
     */
    function send_deletion_alert($objective) {

        global $USER, $CFG, $DB, $OUTPUT;
        require_once($CFG->dirroot.'/totara/message/messagelib.php');

        $event = new stdClass();
        $userfrom = $DB->get_record('user', array('id' => $USER->id));
        $event->userfrom = $userfrom;
        $event->contexturl = new moodle_url("/totara/plan/view.php", array('id' => $this->plan->id));
        $event->icon = 'objective-remove';
        $a = new stdClass();
        $a->objective = $objective->fullname;
        $a->userfrom = fullname($USER);
        $a->userfromhtml = $this->current_user_link();
        $a->plan = $this->plan->name;
        $a->planhtml = $OUTPUT->action_link($event->contexturl, $this->plan->name, null, array('title' => $this->plan->name));
        $stringmanager = get_string_manager();
        // did they delete it themselves?
        if ($USER->id == $this->plan->userid) {
            // don't bother if the plan is not active
            if ($this->plan->is_active()) {
                // notify their manager
                if ($manager = totara_get_manager($this->plan->userid)) {
                    $event->userto = $manager;
                    $event->subject = $stringmanager->get_string('objectivedeleteshortmanager', 'totara_plan', fullname($USER), $manager->lang);
                    $event->fullmessage = $stringmanager->get_string('objectivedeletelongmanager', 'totara_plan', $a, $manager->lang);
                    $event->fullmessagehtml = $stringmanager->get_string('objectivedeletelongmanagerhtml', 'totara_plan', $a, $manager->lang);
                    tm_alert_send($event);
                }
            }
        }
        // notify user that someone else did it
        else {
            $userto = $DB->get_record('user', array('id' => $this->plan->userid));
            $event->userto = $userto;
            $event->subject = $stringmanager->get_string('objectivedeleteshortlearner', 'totara_plan', $a->objective, $userto->lang);
            $event->fullmessage = $stringmanager->get_string('objectivedeletelonglearner', 'totara_plan', $a, $userto->lang);
            $event->fullmessagehtml = $stringmanager->get_string('objectivedeletelonglearnerhtml', 'totara_plan', $a, $userto->lang);
            tm_alert_send($event);
        }
    }

    /**
     * send objective creation alert
     * @param int $objid Objective Id
     * @param string $fullname the title of the objective
     * @return nothing
     */
    function send_creation_alert($objid, $fullname) {

        global $USER, $CFG, $DB, $OUTPUT;
        require_once($CFG->dirroot.'/totara/message/messagelib.php');

        $event = new stdClass();
        $userfrom = $DB->get_record('user', array('id' => $USER->id));
        $event->userfrom = $userfrom;
        $event->contexturl = new moodle_url('/totara/plan/components/objective/view.php', array('id' => $this->plan->id, 'itemid' => $objid));
        $event->icon = 'objective-add';
        $a = new stdClass();
        $a->objective = $fullname;
        $a->objectivehtml = $OUTPUT->action_link($event->contexturl, $fullname);
        $url = new moodle_url('/totara/plan/view.php', array('id' => $this->plan->id));
        $a->plan = $this->plan->name;
        $a->planhtml = $OUTPUT->action_link($url, $this->plan->name, null, array('title' => $this->plan->name));
        $a->userfrom = fullname($USER);
        $a->userfromhtml = $this->current_user_link();

        $stringmanager = get_string_manager();
        // did they create it themselves?
        if ($USER->id == $this->plan->userid) {
            // don't bother if the plan is not active
            if ($this->plan->is_active()) {
                // notify their manager
                if ($manager = totara_get_manager($this->plan->userid)) {
                    $event->userto = $manager;
                    $event->subject = $stringmanager->get_string('objectivenewshortmanager', 'totara_plan', fullname($USER), $manager->lang);
                    $event->fullmessage = $stringmanager->get_string('objectivenewlongmanager', 'totara_plan', $a, $manager->lang);
                    $event->fullmessagehtml = $stringmanager->get_string('objectivenewlongmanagerhtml', 'totara_plan', $a, $manager->lang);
                    tm_alert_send($event);
                }
            }
        }
        // notify user that someone else did it
        else {
            $userto = $DB->get_record('user', array('id' => $this->plan->userid));
            $event->userto = $userto;
            $event->subject = $stringmanager->get_string('objectivenewshortlearner', 'totara_plan', $fullname, $userto->lang);
            $event->fullmessage = $stringmanager->get_string('objectivenewlonglearner', 'totara_plan', $a, $userto->lang);
            $event->fullmessagehtml = $stringmanager->get_string('objectivenewlonglearnerhtml', 'totara_plan', $a, $userto->lang);
            tm_alert_send($event);
        }
    }


    /**
     * send objective edit alert
     * @param object $objective Objective record
     * @param string $field field updated
     * @return nothing
     */
    function send_edit_alert($objective, $field) {

        global $USER, $CFG, $DB, $OUTPUT;
        require_once($CFG->dirroot.'/totara/message/messagelib.php');

        $event = new stdClass();
        $userfrom = $DB->get_record('user', array('id' => $USER->id));
        $event->userfrom = $userfrom;
        $event->contexturl = new moodle_url("/totara/plan/components/objective/view.php", array('id' => $this->plan->id, 'itemid' => $objective->id));
        $event->icon = 'objective-update';
        $a = new stdClass();
        $a->objective = format_string($objective->fullname);
        $a->objectivehtml = $OUTPUT->action_link($event->contexturl, format_string($objective->fullname));
        $url = new moodle_url('/totara/plan/view.php', array('id' => $this->plan->id));
        $a->plan = $this->plan->name;
        $a->planhtml = $OUTPUT->action_link($url, $this->plan->name, null, array('title' => $this->plan->name));
        $a->userfrom = fullname($USER);
        $a->userfromhtml = $this->current_user_link();

        $stringmanager = get_string_manager();
        // did they edit it themselves?
        if ($USER->id == $this->plan->userid) {
            // don't bother if the plan is not active
            if ($this->plan->is_active()) {
                // notify their manager
                if ($manager = totara_get_manager($this->plan->userid)) {
                    $a->field = $stringmanager->get_string('objective'.$field, 'totara_plan', $manager->lang);
                    $event->userto = $manager;
                    $event->subject = $stringmanager->get_string('objectiveeditshortmanager', 'totara_plan', fullname($USER), $manager->lang);
                    $event->fullmessage = $stringmanager->get_string('objectiveeditlongmanager', 'totara_plan', $a, $manager->lang);
                    $event->fullmessagehtml = $stringmanager->get_string('objectiveeditlongmanagerhtml', 'totara_plan', $a, $manager->lang);
                    tm_alert_send($event);
                }
            }
        } else {
            // notify user that someone else did it
            $userto = $DB->get_record('user', array('id' => $this->plan->userid));
            $a->field = $stringmanager->get_string('objective'.$field, 'totara_plan', null, $userto->lang);
            $event->userto = $userto;
            $event->subject = $stringmanager->get_string('objectiveeditshortlearner', 'totara_plan', $a->objective, $userto->lang);
            $event->fullmessage = $stringmanager->get_string('objectiveeditlonglearner', 'totara_plan', $a, $userto->lang);
            $event->fullmessagehtml = $stringmanager->get_string('objectiveeditlonglearnerhtml', 'totara_plan', $a, $userto->lang);
            tm_alert_send($event);
        }
    }

    /**
     * send objective status alert
     *
     * handles both complete and incomplete
     *
     * @param object $objective Objective record
     * @return nothing
     */
    function send_status_alert($objective) {

        global $USER, $CFG, $DB, $OUTPUT;
        require_once($CFG->dirroot.'/totara/message/messagelib.php');

        // determined achieved/non-achieved status
        $achieved = $DB->get_field('dp_objective_scale_value', 'achieved', array('id' => $objective->scalevalueid));
        $status = ($achieved ? 'complete' : 'incomplete');

        // build event message
        $event = new stdClass();
        $userfrom = $DB->get_record('user', array('id' => $USER->id));
        $event->userfrom = $userfrom;
        $event->contexturl = new moodle_url("/totara/plan/components/objective/view.php", array('id' => $this->plan->id, 'itemid' => $objective->id));
        $event->icon = 'objective-'.($status == 'complete' ? 'complete' : 'fail');
        $a = new stdClass;
        $a->objective = format_string($objective->fullname);
        $a->objectivehtml = $OUTPUT->action_link($event->contexturl, format_string($objective->fullname));
        $url = new moodle_url('/totara/plan/view.php', array('id' => $this->plan->id));
        $a->plan = $this->plan->name;
        $a->planhtml = $OUTPUT->action_link($url, $this->plan->name, null, array('title' => $this->plan->name));
        $a->userfrom = fullname($USER);
        $a->userfromhtml = $this->current_user_link();

        $stringmanager = get_string_manager();
        // did they complete it themselves?
        if ($USER->id == $this->plan->userid) {
            // don't bother if the plan is not active
            if ($this->plan->is_active()) {
                // notify their manager
                if ($manager = totara_get_manager($this->plan->userid)) {
                    $event->userto = $manager;
                    $event->subject = $stringmanager->get_string('objective'.$status.'shortmanager', 'totara_plan', fullname($USER), $manager->lang);
                    $event->fullmessage = $stringmanager->get_string('objective'.$status.'longmanager', 'totara_plan', $a, $manager->lang);
                    $event->fullmessagehtml = $stringmanager->get_string('objective'.$status.'longmanagerhtml', 'totara_plan', $a, $manager->lang);
                    tm_alert_send($event);
                }
            }
        } else {
            // notify user that someone else did it
            $userto = $DB->get_record('user', array('id' => $this->plan->userid));
            $event->userto = $userto;
            $event->subject = $stringmanager->get_string('objective'.$status.'shortlearner', 'totara_plan', $a->objective, $userto->lang);
            $event->fullmessage = $stringmanager->get_string('objective'.$status.'longlearner', 'totara_plan', $a, $userto->lang);
            $event->fullmessagehtml = $stringmanager->get_string('objective'.$status.'longlearnerhtml', 'totara_plan', $a, $userto->lang);
            tm_alert_send($event);
        }
    }

    /**
     * Update instances of $componentupdatetype linked to the specified compoent,
     * delete links in db which aren't needed, and add links missing from db
     * which are needed
     *
     * specialised from super class to allow the hooking of alerts
     *
     * @param integer $thiscompoentid Identifies the component on one end of the link
     * @param string $componentupdatetype: the type of components on the other end of the links
     * @param array $componentids array of component ids that should be on the other end of the links in db
     *
     * @return void
     */
    function update_linked_components($thiscomponentid, $componentupdatetype, $componentids) {
        global $DB;

        parent::update_linked_components($thiscomponentid, $componentupdatetype, $componentids);

        if ($componentupdatetype == 'course') {
            $objective = $DB->get_record('dp_plan_objective', array('id' => $thiscomponentid));
            $this->send_edit_alert($objective, 'course');
        }

    }


    /**
     * Return just the "approval" field for an objective
     * @param int $caid
     * return int
     */
    public function get_approval($caid) {
        global $DB;

        return $DB->get_field('dp_plan_objective', 'approved', array('id' => $caid));
    }

    /**
     * Indicates what the objective's approval status should be if the approval
     * is updated.
     * @return int (or false on failure)
     */
    public function approval_status_after_update() {
        $perm = $this->can_update_items();
        if ($perm == DP_PERMISSION_REQUEST) {
            return DP_APPROVAL_UNAPPROVED;
        }
        if (in_array($perm, array( DP_PERMISSION_ALLOW, DP_PERMISSION_APPROVE))) {
            return DP_APPROVAL_APPROVED;
        }

        // In case something went wrong, fall back to unapproved status
        return DP_APPROVAL_UNAPPROVED;
    }


    /**
     * Indicates whether an update will revoke the "approved" status of the
     * component
     * @param <type> $caid
     * @return boolean
     */
    public function will_an_update_revoke_approval($caid) {
        // If the resource is already approved, and the user has only REQUEST
        // permission, then it will revoke the approved status. Otherwise,
        // no change.
        if ($this->can_update_items() == DP_PERMISSION_REQUEST && $this->get_approval($caid) != DP_APPROVAL_UNAPPROVED) {
            return true;
        } else {
            return false;
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
        return (bool) $item->achieved;
    }


    /**
     * Return the name of the component items table
     *
     * Overrides base class because objectives named differently
     *
     * @return string Name of the table containing item assignments
     */
    public function get_component_table_name() {
        return "dp_plan_objective";
    }


    /*********************************************************************************************
     *
     * Display methods
     *
     ********************************************************************************************/

    /**
     * Display an items progress status
     *
     * @access protected
     * @param object $item the item being checked
     * @return string the items status
     */
    protected function display_list_item_progress($item) {
        return $this->display_proficiency($item);
    }


    /**
     * Display an items available actions
     *
     * @access protected
     * @param object $item the item being checked
     * @return string $markup the display html
     */
    protected function display_list_item_actions($item) {
        global $OUTPUT;

        $markup = '';

        if ($this->can_delete_item($item)) {
            $deleteurl = new moodle_url('/totara/plan/components/objective/edit.php',
                array('id' => $this->plan->id, 'itemid' => $item->id, 'd' => 1));
            $strdelete = get_string('delete', 'totara_plan');
            $pixicon = new pix_icon('/t/delete', $strdelete, 'moodle', array('class' => 'iconsmall'));
            $markup .= $OUTPUT->action_icon($deleteurl, $pixicon);
        }

        return $markup;
    }


    /**
     * Return markup for javascript course picker
     *
     * @access public
     * @global object $CFG
     * @return string
     */
    public function display_picker() {
        global $OUTPUT;

        if (!$permission = $this->can_update_items()) {
            return '';
        }

        // Decide on button text
        if ($permission >= DP_PERMISSION_ALLOW) {
            $btntext = get_string('addnewobjective', 'totara_plan');
        } else {
            $btntext = get_string('requestednewobjective', 'totara_plan');
        }

        $button = $OUTPUT->single_button(new moodle_url("/totara/plan/components/objective/edit.php", array('id' => $this->plan->id)), $btntext, 'get');

        return $OUTPUT->container($button, "buttons plan-add-item-button-wrapper");
    }


    /*
     * Return markup for javascript course picker
     * objectiveid integer - the id of the objective for which selected& available courses should be displayed
     * @access  public
     * @return  string
     */
    public function display_course_picker($objectiveid) {
        global $OUTPUT;

        if (!$permission = $this->can_update_items()) {
            return '';
        }

        $btntext = get_string('addlinkedcourses', 'totara_plan');

        $button = $OUTPUT->container(
                html_writer::script('var objective_id = ' . $objectiveid . ';' . 'var plan_id = ' . $this->plan->id . ';') .
                $OUTPUT->single_submit($btntext, array('id' => "show-course-dialog")),
            'singlebutton dp-plan-assign-button'
        );

        return $OUTPUT->container($button, 'buttons');
    }


    /**
     * Print details about an objective
     * @global object $CFG
     * @param int $objectiveid
     * @return void
     */
    public function display_objective_detail($objectiveid) {
        global $DB, $OUTPUT;

        $priorityscaleid = ($this->get_setting('priorityscale')) ? $this->get_setting('priorityscale') : -1;
        $objectivescaleid = $this->get_setting('objectivescale');
        $priorityenabled = $this->get_setting('prioritymode') != DP_PRIORITY_NONE;
        $duedateenabled = $this->get_setting('duedatemode') != DP_DUEDATES_NONE;
        $requiresapproval = $this->get_setting('updateobjective') == DP_PERMISSION_REQUEST;

        $sql = "
            SELECT
                o.id,
                o.fullname,
                o.description,
                o.approved,
                o.duedate,
                o.priority,
                psv.name AS priorityname,
                osv.name AS profname,
                osv.achieved
            FROM
                {dp_plan_objective} o
                LEFT JOIN {dp_objective_scale_value} osv ON (o.scalevalueid = osv.id and osv.objscaleid = ?)
                LEFT JOIN {dp_priority_scale_value} psv ON (o.priority = psv.id and psv.priorityscaleid = ?)
            WHERE
                o.id = ?
        ";
        $item = $DB->get_record_sql($sql, array($objectivescaleid, $priorityscaleid, $objectiveid));

        if (!$item) {
            return get_string('error:objectivenotfound', 'totara_plan');
        }

        $out = '';

        // get the priority values used for competencies in this plan
        $priorityvalues = $DB->get_records('dp_priority_scale_value', array('priorityscaleid' => $priorityscaleid), 'sortorder', 'id,name,sortorder');

        $icon = $this->determine_item_icon($item);
        $icon = $OUTPUT->pix_icon("/msgicons/" . $icon, format_string($item->fullname), 'totara_core', array('class' => 'objective_state_icon'));
        $row = new html_table_row(array(new html_table_cell($OUTPUT->heading($icon . format_string($item->fullname), 3))));
        $table = new html_table();
        $table->data = array($row);
        $out .= html_writer::table($table);

        $plancompleted = $this->plan->status == DP_PLAN_STATUS_COMPLETE;

        if (!$plancompleted && ($canupdate = $this->can_update_items())) {

            if ($this->will_an_update_revoke_approval( $objectiveid )) {
                $buttonlabel = get_string('editdetailswithapproval', 'totara_plan');
            } else {
                $buttonlabel = get_string('editdetails', 'totara_plan');
            }
            $out .= $OUTPUT->container($OUTPUT->single_button(new moodle_url("/totara/plan/components/objective/edit.php", array('id' => $this->plan->id, 'itemid' => $objectiveid)), $buttonlabel), 'add-linked-course');
        }

        $row = new html_table_row();
        if ($priorityenabled && !empty($item->priority)) {
            $row->cells[] = get_string('priority', 'totara_plan') . ': ' . $this->display_priority_as_text($item->priority, $item->priorityname, $priorityvalues);
        }
        if ($duedateenabled && !empty($item->duedate)) {
            $cell = get_string('duedate', 'totara_plan') . ': ' . $this->display_duedate_as_text($item->duedate);
            if (!$item->achieved) {
                $cell .= html_writer::empty_tag('br') . $this->display_duedate_highlight_info($item->duedate);
            }
            $row->cells[] = $cell;
        }
        if (!empty($item->profname)) {
            $row->cells[] = get_string('status', 'totara_plan') .": \n" . "  {$item->profname}\n";
        }

        if ($requiresapproval) {
            $row->cells[] = get_string('status') .": \n" . $this->display_approval($item, false, false)."\n";
        }
        $table = new html_table();
        $table->border = "0";
        $table->attributes = array('class' => 'planiteminfobox');
        $table->data = array($row);
        $out .= html_writer::table($table);
        $item->description = file_rewrite_pluginfile_urls($item->description, 'pluginfile.php', context_system::instance()->id, 'totara_plan', 'dp_plan_objective', $item->id);
        $out .= html_writer::tag('p', format_text($item->description, FORMAT_HTML));

        print $out;
    }


    /**
     * Display a proficiency (or the dropdown menu for it)
     * @param object $ca The current objective
     * @return string
     */
    function display_proficiency($ca) {
        global $DB;

        // Get the proficiency values for this plan
        static $proficiencyvalues;
        if (!isset($proficiencyvalues)) {
            $proficiencyvalues = $DB->get_records('dp_objective_scale_value', array('objscaleid' => $this->get_setting('objectivescale')), 'sortorder', 'id,name,achieved');
        }

        $plancompleted = ($this->plan->status == DP_PLAN_STATUS_COMPLETE);
        $cansetprof = $this->get_setting('setproficiency') == DP_PERMISSION_ALLOW;

        $selected = $ca->scalevalueid;

        if (!$plancompleted && $cansetprof) {
            // Show the menu
            $options = array();
            foreach ($proficiencyvalues as $id => $val) {
                $options[$id] = $val->name;
            }

            return html_writer::select(
                $options,
                "proficiencies[{$ca->id}]",
                $selected,
                null,
                array()
            );

        } else {
            // They can't change the setting, so show it as-is
            $out = format_string($proficiencyvalues[$selected]->name);
            if ($proficiencyvalues[$selected]->achieved) {
                $out = html_writer::tag('b', $out);
            }
            return $out;
        }
    }

    function can_update_settings_extra($can) {
        $can['setproficiency'] = $this->get_setting('setproficiency') >= DP_PERMISSION_ALLOW;
        return $can;
    }


    /*
     * Return data about objective progress within this plan
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

        // array of all objective scale value ids that are 'achieved'
        $achieved_scale_values = $DB->get_records('dp_objective_scale_value', array('achieved' => '1'), '', 'id');
        $achieved_ids = ($achieved_scale_values) ? array_keys($achieved_scale_values) : array();

        $completedcount = 0;
        // Get courses assigned to this plan
        if ($objectives = $this->get_assigned_items()) {
            foreach ($objectives as $o) {
                if ($o->approved != DP_APPROVAL_APPROVED) {
                    continue;
                }

                // Determine proficiency
                $scalevalueid = $o->scalevalueid;
                if (empty($scalevalueid)) {
                    continue;
                }

                if (in_array($scalevalueid, $achieved_ids)) {
                    $completedcount++;
                }
            }
        }
        $progress_str = "{$completedcount}/" . count($objectives) . " " .
            get_string('objectivesmet', 'totara_plan') . "\n";

        $progress = new stdClass();
        $progress->complete = $completedcount;
        $progress->total = count($objectives);
        $progress->text = $progress_str;

        return $progress;
    }


    /**
     * Reactivates objective when re-activating a plan (stub to satisfy abstract method)
     *
     * @return bool $success
     */
    public function reactivate_items() {
        return true;
    }


    /**
     * Gets all plans containing specified objective
     *
     * @param int $objectiveid
     * @param int $userid
     * @return array $plans ids of plans with specified objective
     */
    public static function get_plans_containing_item($objectiveid, $userid) {
        global $DB;

        $sql = "SELECT DISTINCT
                planid
            FROM
                {dp_plan_objective} obj
            JOIN
                {dp_plan} p
              ON
                obj.planid = p.id
            WHERE
                p.userid = ?";
        $params = array($userid);

        return $DB->get_fieldset_sql($sql, $params);
    }
}
