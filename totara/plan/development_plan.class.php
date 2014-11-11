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
 * @package totara
 * @subpackage plan
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->dirroot . '/totara/message/eventdata.class.php');
require_once($CFG->dirroot . '/totara/message/messagelib.php');
require_once($CFG->dirroot . '/totara/plan/lib.php');

class development_plan {
    public static $permissions = array(
        'view' => false,
        'create' => false,
        'update' => false,
        'delete' => false,
        'approve' => true,
        'completereactivate' => false
    );
    public $id, $templateid, $userid, $name, $description;
    public $startdate, $enddate, $timecompleted, $status, $role, $settings;
    public $viewas;

    /**
     * Flag the page viewing this plan as the reviewing pending page
     *
     * @access  public
     * @var     boolean
     */
    public $reviewing_pending = false;


    function __construct($id, $viewas=null) {
        global $USER, $CFG, $DB;

        // get plan db record
        $plan = $DB->get_record('dp_plan', array('id' => $id));
        if (!$plan) {
            throw new PlanException(get_string('planidnotfound', 'totara_plan', $id));
        }

        // get details about this plan
        $this->id = $id;
        $this->templateid = $plan->templateid;
        $this->userid = $plan->userid;
        $this->name = $plan->name;
        $this->description = $plan->description;
        $this->startdate = $plan->startdate;
        $this->enddate = $plan->enddate;
        $this->timecompleted = $plan->timecompleted;
        $this->status = $plan->status;

        // default to viewing as the current user
        // if $viewas not set
        if (empty($viewas)) {
            $this->viewas = $USER->id;
        } else {
            $this->viewas  = $viewas;
        }

        // store role and component objects for easy access
        $this->load_roles();
        $this->load_components();

        // get the user's role in this plan
        $this->role = $this->get_user_role($this->viewas);

        // lazy-load settings from database when first needed
        $this->settings = null;
    }


    /**
     * Is this plan currently active?
     *
     * @access  public
     * @return  boolean
     */
    public function is_active() {
        return $this->status == DP_PLAN_STATUS_APPROVED;
    }


    /**
     * Is this plan complete?
     *
     * @access  public
     * @return  boolean
     */
    public function is_complete() {
        return $this->status == DP_PLAN_STATUS_COMPLETE;
    }


    /**
     * Save an instance of each defined role to a property of this class
     *
     * This method creates a property $this->[role] for each entry in
     * $DP_AVAILABLE_ROLES, and fills it with an instance of that role.
     *
     */
    function load_roles() {

        global $CFG, $DP_AVAILABLE_ROLES;

        // loop through available roles
        foreach ($DP_AVAILABLE_ROLES as $role) {
            // include each class file
            $classfile = $CFG->dirroot .
                "/totara/plan/roles/{$role}/{$role}.class.php";

            if (!is_readable($classfile)) {
                $string_params = new stdClass();
                $string_params->classfile = $classfile;
                $string_params->role = $role;
                throw new PlanException(get_string('noclassfileforrole', 'totara_plan', $string_params));
            }
            include_once($classfile);

            // check class exists
            $class = "dp_{$role}_role";
            if (!class_exists($class)) {
                $string_params = new stdClass();
                $string_params->class = $class;
                $string_params->role = $role;
                throw new PlanException(get_string('noclassforrole', 'totara_plan', $string_params));
            }

            $rolename = "role_$role";

            // create an instance and save as a property for easy access
            $this->$rolename = new $class($this);
        }

    }


    /**
     * Get the rolename from the role
     *
     * @param string $role
     * @return string $rolename
     */
    function get_role($role) {
        $rolename = "role_$role";
        return $this->$rolename;
    }


    /**
     * Save an instance of each defined component to a property of this class
     *
     * This method creates a property $this->[component] for each entry in
     * $DP_AVAILABLE_COMPONENTS, and fills it with an instance of that component.
     *
     */
    function load_components() {
        global $CFG, $DP_AVAILABLE_COMPONENTS;

        foreach ($DP_AVAILABLE_COMPONENTS as $component) {
            // include each class file
            $classfile = $CFG->dirroot .
                "/totara/plan/components/{$component}/{$component}.class.php";
            if (!is_readable($classfile)) {
                $string_params = new stdClass();
                $string_params->classfile = $classfile;
                $string_params->component = $component;
                throw new PlanException(get_string('noclassfileforcomponent', 'totara_plan', $string_params));
            }
            include_once($classfile);

            // check class exists
            $class = "dp_{$component}_component";
            if (!class_exists($class)) {
                $string_params = new stdClass();
                $string_params->class = $class;
                $string_params->component = $component;
                throw new PlanException(get_string('noclassforcomponent', 'totara_plan', $string_params));
            }

            $componentname = "component_$component";

            // create an instance and save as a property for easy access
            $this->$componentname = new $class($this);
        }
    }


    /**
     * Return a single component
     *
     * @access  public
     * @param   string  $component  Component name
     * @return  object
     */
    public function get_component($component) {
        $componentname = "component_$component";
        return $this->$componentname;
    }


    /**
     * Return array of active component instances for a plan template
     *
     * @access  public
     * @return  array
     */
    public function get_components() {
        global $DP_AVAILABLE_COMPONENTS, $DB;
        $components = array();
        list($insql, $inparams) = $DB->get_in_or_equal($DP_AVAILABLE_COMPONENTS);
        $sql = "SELECT * FROM {dp_component_settings}
            WHERE component $insql
            AND templateid = ?
            AND enabled = 1
            ORDER BY sortorder";
        $params = array_merge($inparams, array($this->templateid));
        $active_components = $DB->get_records_sql($sql, $params);
        if (totara_feature_disabled('programs')) {
            $active_components = totara_search_for_value($active_components, 'component', TOTARA_SEARCH_OP_NOT_EQUAL, 'program');
        }
        foreach ($active_components as $component) {
            $componentname = "component_{$component->component}";
            $components[$component->component] = $this->$componentname;
        }

        return $components;
    }


    /**
     * Get a component setting
     *
     * @param array $component
     * @param string $action
     * @return void
     */
    function get_component_setting($component, $action) {
        // we need the know the template to get settings
        if (!$this->templateid) {
            return false;
        }
        $role = $this->role;
        $templateid = $this->templateid;

        // only load settings when first needed
        if (!isset($this->settings)) {
            $this->initialize_settings();
        }

        // return false the setting if it exists
        if (array_key_exists($component.'_'.$action, $this->settings)) {
            return $this->settings[$component.'_'.$action];
        }

        // return the role specific setting if it exists
        if (array_key_exists($component.'_'.$action.'_'.$role, $this->settings)) {
            return $this->settings[$component.'_'.$action.'_'.$role];
        }

        // return null if nothing set
        print_error('error:settingdoesnotexist', 'totara_plan', '', (object)array('component' => $component, 'action' => $action));
    }


    /**
     * Get a setting for an action
     *
     * @param string $action
     * @return string
     */
    function get_setting($action) {
        return $this->get_component_setting('plan', $action);
    }


    /**
     * Initialize settings for a component
     *
     * @return bool
     */
    function initialize_settings() {
        global $DP_AVAILABLE_COMPONENTS, $DB;
        // no need to initialize twice
        if (isset($this->settings)) {
            return true;
        }
        // can't initialize without a template id
        if (!$this->templateid) {
            return false;
        }

        // add role-based settings from permissions table
        $issuperuser = has_capability('totara/plan:manageanyplan', context_system::instance());
        $results = $DB->get_records('dp_permissions', array('templateid' => $this->templateid));

        foreach ($results as $result) {
            if ($issuperuser) {
                // override permissions to allow super users to do anything :D
                $componentclass = $result->component == 'plan' ? 'development_plan' : "dp_{$result->component}_component";
                if (!empty($componentclass::$permissions[$result->action])) {
                    // action is requestable
                    $result->value = DP_PERMISSION_APPROVE;
                } else {
                    $result->value = DP_PERMISSION_ALLOW;
                }
            }

            $this->settings[$result->component.'_'.$result->action.'_'.$result->role] = $result->value;
        }

        // add component-independent settings
        $components = $DB->get_records('dp_component_settings', array('templateid' => $this->templateid), 'sortorder');
        foreach ($components as $component) {
            // is this component enabled?
            $this->settings[$component->component.'_enabled'] = $component->enabled;

            // get the name from a config var, or use the default name if not set
            $configname = 'dp_'.$component->component;
            $name = get_config(null, $configname);
            $this->settings[$component->component.'_name'] = $name ? $name :
                get_string($component->component, 'totara_plan');
        }

        // also save the whole list together with sort order
        $this->settings['plan_components'] = $components;

        // add role-independent settings from individual component tables
        foreach ($DP_AVAILABLE_COMPONENTS as $component) {
            // only include if the component is enabled
            if (!$this->get_component($component)->get_setting('enabled')) {
                continue;
            }
            $this->get_component($component)->initialize_settings($this->settings);
        }

        // Initialise plan specific settings
        if ($plansettings = $DB->get_record('dp_plan_settings', array('templateid' => $this->templateid))) {
            $this->settings['plan_manualcomplete'] = $plansettings->manualcomplete;
            $this->settings['plan_autobyitems'] = $plansettings->autobyitems;
            $this->settings['plan_autobyplandate'] = $plansettings->autobyplandate;
        }
    }


    /**
     * Find the number of pending items this use can approve, if any
     *
     * @access  public
     * @return  integer
     */
    public function num_pendingitems() {
        // Check if plan is active
        if ($this->status == DP_PLAN_STATUS_COMPLETE) {
            return 0;
        }

        // Get all pending items
        $items = $this->has_pending_items(null, true, true);

        if (!$items) {
            return 0;
        }

        // Count all
        $count = 0;
        foreach ($items as $component) {
            $count += count($component);
        }

        return $count;
    }


    /**
     * Display widget containing a component summary
     *
     * @return string $out
     */
    function display_summary_widget() {
        global $OUTPUT, $DB, $CFG;

        $link = html_writer::link(new moodle_url('/totara/plan/view.php', array('id' => $this->id)), $this->name);
        $out = $OUTPUT->container($link, 'dp-summary-widget-title');
        $components = $DB->get_records_select('dp_component_settings', "templateid = ? AND enabled = 1", array($this->templateid), 'sortorder');
        $total = count($components);
        $pendingitems = $this->num_pendingitems();
        $content = '';
        $count = 1;

        if (!totara_feature_visible('programs')) {
            $components = totara_search_for_value($components, 'component', TOTARA_SEARCH_OP_NOT_EQUAL, 'program');
        }

        foreach ($components as $c) {
            $component = $this->get_component($c->component);
            $compname = get_string($component->component.'plural', 'totara_plan');
            $class = ($count == $total && !$pendingitems) ? "dp-summary-widget-component-name-last" : "dp-summary-widget-component-name";
            $assignments = $component->get_assigned_items();
            $assignments = !empty($assignments) ? '('.count($assignments).')' : '';

            $linktext = $compname;
            if ($assignments) {
                $linktext .= " $assignments";
            }

            $content .= html_writer::tag('span',html_writer::link($component->get_url(), $linktext), array('class' => $class));
            $count++;
        }

        if ($pendingitems) {
            $a = new stdClass();
            $a->count = $pendingitems;
            $link = new moodle_url('/totara/plan/approve.php', array('id' => $this->id));
            $a->link = $link->out();
            $content .= html_writer::tag('span', get_string('pendingitemsx', 'totara_plan', $a),
                    array('class' => 'dp-summary-widget-pendingitems-text'));
        }
        $out .= $OUTPUT->container($content, 'dp-summary-widget-components');
        $description = file_rewrite_pluginfile_urls($this->description, 'pluginfile.php', context_system::instance()->id, 'totara_plan', 'dp_plan', $this->id);
        $out .= $OUTPUT->container($description, 'dp-summary-widget-description');

        return $out;
    }


    /**
     * Display the add plan icon
     *
     * @return string $out
     */
    function display_add_plan_icon() {
        global $OUTPUT;

        return $OUTPUT->action_icon(new moodle_url('/totara/plan/edit.php'), new pix_icon('t/add', get_string('addplan', 'totara_plan')), null, array('title' => get_string('addplan', 'totara_plan')));
    }


    /**
     * Display end date for an item
     *
     * @param int $itemid
     * @param int $enddate
     * @return string
     */
    function display_enddate() {
        $out = '';

        $out .= $this->display_enddate_as_text($this->enddate);

        // highlight dates that are overdue or due soon
        $out .= $this->display_enddate_highlight_info($this->enddate);

        return $out;

    }


    /**
     * Display enddate for an item as text
     *
     * @param int $enddate
     * @return string
     */
    function display_enddate_as_text($enddate) {
        global $CFG;
        if (isset($enddate)) {
            return userdate($enddate, get_string('strfdateshortmonth', 'langconfig'), $CFG->timezone, false);
        } else {
            return '';
        }
    }


    /**
     * Display enddate for an item with task info
     *
     * @param int $enddate
     * @return string
     */
    function display_enddate_highlight_info($enddate) {
        $out = '';
        $now = time();
        if (isset($enddate)) {
            if (($enddate < $now) && ($now - $enddate < 60*60*24)) {
                $out .= html_writer::empty_tag('br');
                $out .= html_writer::tag('span', get_string('duetoday', 'totara_plan'), array('class' => 'plan_highlight'));
            } else if ($enddate < $now) {
                $out .= html_writer::empty_tag('br');
                $out .= html_writer::tag('span', get_string('overdue', 'totara_plan'), array('class' => 'plan_highlight'));
            } else if ($enddate - $now < 60*60*24*7) {
                $days = ceil(($enddate - $now)/(60*60*24));
                $out .= html_writer::empty_tag('br');
                $out .= html_writer::tag('span', get_string('dueinxdays', 'totara_plan', $days), array('class' => 'plan_highlight'));
            }
        }
        return $out;
    }

    /**
     * Determines and displays the progress of this plan.
     *
     * Progress is determined by course completion statuses.
     *
     * @access  public
     * @return  string
     */
    public function display_progress() {
        global $CFG, $OUTPUT, $PAGE;

        if ($this->status == DP_PLAN_STATUS_UNAPPROVED || $this->status == DP_PLAN_STATUS_PENDING) {
            $out = get_string('planstatusunapproved', 'totara_plan');
            if ($this->status == DP_PLAN_STATUS_UNAPPROVED) {
                // Approval request
                if ($this->get_setting('approve') == DP_PERMISSION_REQUEST) {
                    $out .= html_writer::empty_tag('br');
                    $url = new moodle_url('/totara/plan/action.php', array('id' => $this->id, 'approvalrequest' => 1, 'sesskey' => sesskey()));
                    $out .= $OUTPUT->action_link($url, get_string('requestapproval', 'totara_plan'));
                }
            } else {
                $out .= html_writer::empty_tag('br');
                $out .= get_string('approvalrequested', 'totara_plan');
            }


            return $out;
        }

        $overall_complete = 0;
        $overall_total = 0;
        $overall_strings = array();
        foreach ($this->get_components() as $component) {
            if ($stats = $component->progress_stats()) {
                $overall_complete += $stats->complete;
                $overall_total += $stats->total;
                $overall_strings[] = $stats->text;
            }
        }

        // Calculate plan progress
        if ($overall_complete > 0) {
            $overall_progress = $overall_complete / $overall_total * 100.0;
            $overall_progress = round($overall_progress, 2);
        } else {
            $overall_progress = $overall_complete;
        }

        array_unshift($overall_strings, 'Plan Progress: ' . $overall_progress . "%\n\n");
        $tooltipstr = implode(' | ', $overall_strings);

        // Get totara core renderer
        $totara_renderer = $PAGE->get_renderer('totara_core');

        // Get relevant progress bar and return for display
        return $totara_renderer->print_totara_progressbar($overall_progress, 'medium', false, $tooltipstr);
    }


    /**
     * Display completed date for a plan
     *
     * @return string
     */
    function display_completeddate() {
        global $CFG;

        // Ensure plan is currently completed
        if ($this->status != DP_PLAN_STATUS_COMPLETE) {
            return get_string('notcompleted', 'totara_plan');
        }

        // Get the last modification and make sure that it has DP_PLAN_STATUS_COMPLETE status
        $history = $this->get_history('id DESC');
        $latestmodification = reset($history);

        return ($latestmodification->status != DP_PLAN_STATUS_COMPLETE) ? get_string('notcompleted', 'totara_plan') : userdate($latestmodification->timemodified, get_string('datepickerlongyearphpuserdate', 'totara_core'), $CFG->timezone, false);
    }


    /**
     *  Displays icons of current actions the user can perform on the plan
     *
     *  @return string
     */
    function display_actions() {
        global $CFG, $OUTPUT;

        ob_start();

        // Approval
        if ($this->status == DP_PLAN_STATUS_UNAPPROVED || $this->status == DP_PLAN_STATUS_PENDING) {

            // Approve/Decline
            if (in_array($this->get_setting('approve'), array(DP_PERMISSION_ALLOW, DP_PERMISSION_APPROVE))) {
                echo $OUTPUT->action_icon(new moodle_url('/totara/plan/action.php', array('id' => $this->id, 'approve' => 1, 'sesskey' => sesskey())), new pix_icon('/t/go', get_string('approve', 'totara_plan')));
                echo $OUTPUT->action_icon(new moodle_url('/totara/plan/action.php', array('id' => $this->id, 'decline' => 1, 'sesskey' => sesskey())), new pix_icon('/t/stop', get_string('decline', 'totara_plan')));
            }
        }

        // Complete
        if ($this->status == DP_PLAN_STATUS_APPROVED && $this->get_setting('completereactivate') >= DP_PERMISSION_ALLOW  && $this->get_setting('manualcomplete')) {
            echo $OUTPUT->action_icon(new moodle_url('/totara/plan/action.php', array('id' => $this->id, 'complete' => 1, 'sesskey' => sesskey())), new pix_icon('/i/star', get_string('plancomplete', 'totara_plan'), 'totara_plan'));
        }

        // Reactivate
        if ($this->status == DP_PLAN_STATUS_COMPLETE && $this->get_setting('completereactivate') >= DP_PERMISSION_ALLOW) {
            echo $OUTPUT->action_icon(new moodle_url('/totara/plan/action.php', array('id' => $this->id, 'reactivate' => 1, 'sesskey' => sesskey())), new pix_icon('/i/star_grey', get_string('planreactivate', 'totara_plan'), 'totara_plan'));
        }

        // Delete
        if ($this->get_setting('delete') == DP_PERMISSION_ALLOW) {
            echo $OUTPUT->action_icon(new moodle_url('/totara/plan/action.php', array('id' => $this->id, 'delete' => 1, 'sesskey' => sesskey())), new pix_icon('/i/invalid', get_string('delete')));
        }

        $out = ob_get_contents();

        ob_end_clean();

        return $out;
    }


    /**
     * Gets history of a plan
     *
     * @return object
     */
    function get_history($orderby='timemodified') {
        global $DB;
        return $DB->get_records('dp_plan_history', array('planid' => $this->id), $orderby);
    }

    /**
     * Return a string containing the specified user's role in this plan
     *
     * This currently returns the first role that the user has, although
     * it would be easy to modify to return an array of all matched roles.
     *
     * @param integer $userid ID of the user to find the role of
     *                        If null uses current user's ID
     * @return string Name of the user's role within the current plan
     */
    function get_user_role($userid=null) {

        global $DP_AVAILABLE_ROLES;

        // loop through available roles
        foreach ($DP_AVAILABLE_ROLES as $role) {
            // call a method on each one to determine if user has that role
            if ($hasrole = $this->get_role($role)->user_has_role($userid)) {
                // return the name of the first role to match
                // could change to return an array of all matches
                return $hasrole;
            }
        }

        // no roles matched
        return false;
    }


    /**
     * Return true if the user of this plan has the specified role
     *
     * Typically the user of the plan is the current user, unless a different
     * userid was specified via the viewas parameter when the plan instance
     * was created.
     *
     * This method makes use of $this->role, which is populated by
     * {@link development_plan::get_user_role()} when a new plan is instantiated
     *
     * @param string $role Name of the role to check for
     * @return boolean True if the user has the role specified
     */
    function user_has_role($role) {
        // support array of roles in case we want to allow
        // a user to have multiple roles at some point
        if (is_array($this->role)) {
            if (in_array($role, $this->role)) {
                return true;
            }
        } else {
            if ($role == $this->role) {
                return true;
            }
        }
        return false;
    }


    /**
     * Returns all assigned items to components
     *
     * Optionally, filtered by status
     *
     * @access  public
     * @param   mixed   $approved   (optional)
     * @return  array
     */
    public function get_assigned_items($approved = null) {

        $out = array();
        // Get any pending items for each component
        foreach ($this->get_components() as $name => $component) {
            // Ignore if disabled
            if (!$component->get_setting('enabled')) {
                continue;
            }
            $items = $component->get_assigned_items($approved);
            // Ignore if no items
            if (empty($items)) {
                continue;
            }
            $out[$name] = $items;
        }

        return $out;
    }


    /**
     * Returns all unapproved items assigned to components
     *
     * @access  public
     * @return  array
     */
    public function get_unapproved_items() {
        return $this->get_assigned_items(
            array(
                DP_APPROVAL_DECLINED,
//                DP_APPROVAL_APPROVED,
                DP_APPROVAL_UNAPPROVED
            )
        );
    }


    /**
     * Returns all pending items assigned to components
     *
     * @access  public
     * @return  array
     */
    public function get_pending_items() {
        return $this->get_assigned_items(
            array(
                DP_APPROVAL_REQUESTED
            )
        );
    }


    /**
     * Check if the plan has any pending items
     *
     * @access  public
     * @param   array       $pendinglist    (optional)
     * @param   boolean     $onlyapprovable Only check approvable items
     * @param   boolean     $returnapprovable   Return array of approvable items
     * @return  boolean|array
     */
    public function has_pending_items($pendinglist=null, $onlyapprovable=false, $returnapprovable=false) {

        $components = $this->get_components();

        // Get the pending items, if it hasn't been passed to the method
        if (!isset($pendinglist)) {
            $pendinglist = $this->get_pending_items();
        }

        // See if any component has any pending items
        foreach ($components as $componentname => $component) {
            // Skip if empty
            if (empty($pendinglist[$componentname])) {
                continue;
            }

            // Not checking for approvable items?
            if (!$onlyapprovable) {
                return true;
            }

            // Check if approvable
            $canapprove = $component->get_setting("update{$componentname}") == DP_PERMISSION_APPROVE;

            // Returning boolean?
            if (!$returnapprovable && $canapprove) {
                return true;
            }

            // Returning array but can't approve this component
            if ($returnapprovable && !$canapprove) {
                // Remove component from array
                unset($pendinglist[$componentname]);
            }
        }

        if ($returnapprovable && !empty($pendinglist)) {
            return $pendinglist;
        }

        return false;
    }


    /**
     * Given a pair of id/component pairs, returns them in a correctly sorted array
     *
     * @param   string  $component1     Component name of the first item
     * @param   int     $itemid1        Assignment ID of the first item
     * @param   string  $component2     Component name of the second item
     * @param   int     $itemid2        Assignment ID of the second item
     *
     * @return array or false Array of arrays containing the items sorted by component name in the form:
     *  array(
     *    [0] => array ('id' => [firstid], 'component' => [firsttype]),
     *    [1] => array ('id' => [secondid], 'component' => [secondtype]),
     *  )
     *
     *  The array's elements will be sorted by component name, so [firsttype] will alphabetically
     *  preceed [secondtype].
     *
     *  Returns false if you provide the same component type for both items, as linked items
     *  must be of different types
     *
     */
    function get_relation_array($component1, $itemid1, $component2, $itemid2) {
        $unsorted = array(
            array(
                'id' => $itemid1,
                'component' => $component1,
            ),
            array(
                'id' => $itemid2,
                'component' => $component2,
            ),
        );

        $cmp = strcmp($unsorted[0]['component'], $unsorted[1]['component']);
        if ($cmp < 0) {
            // items in correct order already
            return $unsorted;
        } else if ($cmp > 0) {
            // reverse array order
            return array_reverse($unsorted);
        } else {
            // items are the same, not supported
            return false;
        }
    }


    /**
     * Adds a relation between two plan items
     *
     * This method checks if the relation is already set and returns the existing relations ID if found
     *
     * @param   string  $component1     Component name of the first item
     * @param   int     $itemid1        Assignment ID of the first item
     * @param   string  $component2     Component name of the second item
     * @param   int     $itemid2        Assignment ID of the second item
     * @param   string  $mandatory      Which, if any component is mandatory? (optional)
     *
     * @return integer or false ID of the new relation, or the existing relation, or false on failure
     */
    function add_component_relation($component1, $itemid1, $component2, $itemid2, $mandatory = '') {
        global $DB;
        $items = $this->get_relation_array($component1, $itemid1, $component2, $itemid2);

        // Couldn't generate items, probably because item 1 and item 2 have same component type
        if ($items === false) {
            return false;
        }

        // See if the relation already exists
        $sql = "itemid1 = ? AND component1 = ? AND itemid2 = ? AND component2 = ?";
        $params = array($items[0]['id'], $items[0]['component'], $items[1]['id'], $items[1]['component']);
        $existingrelation = $DB->get_record_select('dp_plan_component_relation', $sql, $params);

        // Relation already exists and mandatory value hasn't changed, return the relation ID
        if ($existingrelation && $existingrelation->mandatory == $mandatory) {
            return $existingrelation->id;
        }

        // Otherwise create/update the relation, returning the new ID
        $todb = new stdClass();
        $todb->itemid1 = $items[0]['id'];
        $todb->component1 = $items[0]['component'];
        $todb->itemid2 = $items[1]['id'];
        $todb->component2 = $items[1]['component'];
        $todb->mandatory = $mandatory;

        // If there but diff mandatory, update
        if ($existingrelation) {
            $todb->id = $existingrelation->id;
            $DB->update_record('dp_plan_component_relation', $todb);
        } else {
            $DB->insert_record('dp_plan_component_relation', $todb);
        }
        return true;
    }


    /**
     * Display plan message box
     *
     * Generally includes messages about the plan's status as a whole
     *
     * @access  public
     * @return  string
     */
    public function display_plan_message_box() {
        global $OUTPUT;
        $unapproved = ($this->status == DP_PLAN_STATUS_UNAPPROVED || $this->status == DP_PLAN_STATUS_PENDING);
        $completed = ($this->status == DP_PLAN_STATUS_COMPLETE);
        $viewingasmanager = $this->role == 'manager';
        $pending = $this->get_pending_items();
        $haspendingitems = $this->has_pending_items($pending);
        $canapprovepending = $this->has_pending_items($pending, true);
        $unapproveditems = $this->get_unapproved_items();
        $hasunapproveditems = !empty($unapproveditems);

        $canapproveplan = (in_array($this->get_setting('approve'), array(DP_PERMISSION_APPROVE, DP_PERMISSION_ALLOW)));

        $message = '';
        if ($viewingasmanager) {
            $message .= $this->display_viewing_users_plan($this->userid);
        }

        if ($completed) {
            $message .= $this->display_completed_plan_message();
            $style = 'notifymessage';
        } else {
            if (($haspendingitems && $canapprovepending) || ($unapproved && $canapproveplan)) {
                $style = 'notifynotice';
            } else {
                $style = 'notifymessage';
            }

            if (!$viewingasmanager && $hasunapproveditems) {
                $message .= $this->display_unapproved_items($unapproveditems);
                $style = 'notifynotice';
            }

            if ($unapproved) {
                if ($haspendingitems) {
                    if ($canapprovepending) {
                        $message .= $this->display_pending_items($pending);
                    } else if ($canapproveplan) {
                        $message .= $this->display_unapproved_plan_message();
                    } else {
                        $message .= $this->display_pending_items($pending);
                    }
                }

                $message .= $this->display_unapproved_plan_message();
            } else {
                if ($haspendingitems) {
                    $message .= $this->display_pending_items($pending);
                } else {
                    // nothing to report (no message)
                }
            }
        }

        if ($message == '') {
            return $OUTPUT->container(null, 'plan_box');
        }
        return $OUTPUT->container($message, "plan_box {$style} clearfix");
    }


    /**
     * Display completed plan message
     *
     * @return string
     */
    function display_completed_plan_message() {
        global $DB;

        $sql = "SELECT * FROM {dp_plan_history} WHERE planid = ? ORDER BY timemodified DESC";
        $history = $DB->get_records_sql($sql, array($this->id), 0, 1);
        $history = array_shift($history);
        switch ($history->reason) {
        case DP_PLAN_REASON_MANUAL_COMPLETE:
            $message = get_string('plancompleted', 'totara_plan');
            break;

        case DP_PLAN_REASON_AUTO_COMPLETE_ITEMS:
            $message = get_string('planautocompleteditems', 'totara_plan');
            break;

        case DP_PLAN_REASON_AUTO_COMPLETE_DATE:
            $message = get_string('planautocompleteddate', 'totara_plan');
            break;
        }

        if (!$message) {
            $message = get_string('plancompleted', 'totara_plan');
        }

        $extramessage = '';
        if ($this->get_setting('completereactivate') == DP_PERMISSION_ALLOW) {
            $url = new moodle_url('/totara/plan/action.php', array('id' => $this->id, 'reactivate' => 1, 'sesskey' => sesskey()));
            $extramessage = html_writer::tag('p', get_string('reactivateplantext', 'totara_plan', $url->out()));
        }
        return html_writer::tag('p',  $message) . $extramessage;
    }


    /**
     * Display unapproved plan message
     *
     * @return string
     */
    function display_unapproved_plan_message() {
        global $OUTPUT;

        $canapproveplan = (in_array($this->get_setting('approve'),  array(DP_PERMISSION_APPROVE, DP_PERMISSION_ALLOW)));
        $canrequestapproval = ($this->get_setting('approve') == DP_PERMISSION_REQUEST);
        $out = '';
        $out .= html_writer::start_tag('form', array('action' => new moodle_url('/totara/plan/action.php'), 'method' => 'post', 'class' => 'approvalform'));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $this->id));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
        $out .= get_string('plannotapproved', 'totara_plan');

        if ($canapproveplan) {
            $out .= html_writer::start_div();
            $out .= get_string('reasonfordecision', 'totara_message');
            $out .= html_writer::empty_tag('input', array('type' => 'text', 'name' => 'reasonfordecision'));
            $out .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'approve', 'value' => get_string('approve', 'totara_plan')));
            $out .= '&nbsp;' . html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'decline', 'value' => get_string('decline', 'totara_plan')));
            $out .= html_writer::end_div();
        } else if ($canrequestapproval) {
            if ($this->status == DP_PLAN_STATUS_UNAPPROVED) {
                $out .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'approvalrequest', 'value' => get_string('sendapprovalrequest', 'totara_plan')));
            } else {
                $out .= " " . get_string('approvalrequested', 'totara_plan');
            }
        }

        $out .= html_writer::end_tag('form');
        return $out;
    }


    /**
     * Display pending items list
     *
     * @param object $pendinglist
     * @return string
     */
    function display_pending_items($pendinglist=null) {
        global $DP_AVAILABLE_COMPONENTS, $OUTPUT;

        // If this is the pending review page, do not show list of items
        if ($this->reviewing_pending) {
            return '';
        }

        // get the pending items, if it hasn't been passed to the method
        if (!isset($pendinglist)) {
            $pendinglist = $this->get_pending_items();
        }

        $list = array();
        $itemscount = 0;

        $approval = false;

        foreach ($DP_AVAILABLE_COMPONENTS as $componentname) {
            if (!$component = $this->get_component($componentname)) {
                continue;
            }

            $canapprove = $component->get_setting('update'.$component->component) == DP_PERMISSION_APPROVE;
            $enabled = $component->get_setting('enabled');

            if ($enabled && !empty($pendinglist[$component->component])) {
                $a = new stdClass();
                $a->planid = $this->id;
                $a->number = count($pendinglist[$component->component]);
                $itemscount += $a->number;
                $a->component = $component->component;
                $name = $a->component;
                // determine plurality
                $langkey = $name . ($a->number > 1 ? 'plural' : '');
                $a->name = (get_string($langkey, 'totara_plan') ? get_string($langkey, 'totara_plan') : $name);
                $a->link = $component->get_url()->out();
                $list[] = get_string('xitemspending', 'totara_plan', $a);
            }
            $approval = $approval || $canapprove;
        }

        $descriptor = $approval ? 'thefollowingitemsrequireyourapproval' : 'thefollowingitemsarepending';

        // only print if there are pending items
        $out = '';
        if (count($list)) {
            $table = new html_table();
            $table->attributes['class'] = 'invisiblepadded';
            $row = new html_table_row();
            $descriptor .= ($itemscount > 1 ? '_p' : '_s');
            $description = $OUTPUT->container_start('plan_box_wrap');
            $description .= html_writer::tag('p', get_string($descriptor, 'totara_plan'));
            $description .= html_writer::alist($list);
            $description .= $OUTPUT->container_end();

            $url = new moodle_url('/totara/plan/approve.php', array('id' => $this->id));
            $actionbutton = $approval ? $OUTPUT->single_button($url, get_string('review', 'totara_plan'), 'get') : '';

            $table->data[] = new html_table_row(array($description, $actionbutton));
            $out = html_writer::table($table);
        }

        return $out;
    }


    /**
     * Display status of unapproved items
     *
     * @access  public
     * @param   array   $unapproved
     * @return  string
     */
    public function display_unapproved_items($unapproved) {
        global $OUTPUT;

        // Show list of items
        $list = array();
        $totalitems = 0;
        foreach ($unapproved as $component => $items) {

            $comp = $this->get_component($component);

            $a = new stdClass();
            $a->uri = $comp->get_url()->out();
            $a->number = count($items);
            $totalitems += $a->number;
            $a->name = $component;
            // determine plurality
            $langkey = $a->name . ($a->number > 1 ? 'plural' : '');
            $a->name = get_string($langkey, 'totara_plan');
            $list[] = get_string('xitemsunapproved', 'totara_plan', $a);
        }

        // put the heading on now we know how many
        $out = $OUTPUT->container(html_writer::tag('p', get_string(($totalitems > 1 ? 'planhasunapproveditems' : 'planhasunapproveditem'), 'totara_plan')) . html_writer::alist($list), 'plan_box_wrap');

        // Show request button if plan is active
        if ($this->status == DP_PLAN_STATUS_APPROVED) {
            $out .= html_writer::start_tag('form', array('action' => new moodle_url('/totara/plan/action.php'), 'method' => 'post'));
            $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $this->id));
            $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
            $out .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'approvalrequest', 'value' => get_string('sendapprovalrequest', 'totara_plan')));
            $out .= html_writer::end_tag('form');
        }

        return $out;
    }


    /**
     * Display the viewing users plan
     *
     * @access public
     * @param int $userid
     * @return string
     */
    static public function display_viewing_users_plan($userid) {
        global $CFG, $DB, $OUTPUT;
        $user = $DB->get_record('user', array('id' => $userid));
        if (!$user) {
            return '';
        }
        $a = new stdClass();
        $a->name = fullname($user);
        $a->userid = $userid;
        $a->site = $CFG->wwwroot;

        $table = new html_table();
        $table->attributes['class'] = 'invisiblepadded';
        $cells = array($OUTPUT->user_picture($user), get_string('youareviewingxsplan', 'totara_plan', $a));
        $table->data[] = new html_table_row($cells);
        return html_writer::table($table);
    }


    /**
     * Delete the plan and all of its relevant data
     *
     * @return boolean
     */
    function delete() {
        global $CFG, $DB, $DP_AVAILABLE_COMPONENTS, $USER;

        require_once("{$CFG->libdir}/ddllib.php");

        $dbman = $DB->get_manager();
        $transaction = $DB->start_delegated_transaction();

        // Delete plan
        $DB->delete_records('dp_plan', array('id' => $this->id));
        //Delete plan history
        $DB->delete_records('dp_plan_history', array('planid' => $this->id));
        // Delete related components
        foreach ($DP_AVAILABLE_COMPONENTS as $c) {
            $itemids = array();
            $table = new xmldb_table("dp_plan_{$c}");
            if ($dbman->table_exists($table)) {
                $field = new xmldb_field('planid');
                if ($dbman->field_exists($table, $field)) {
                    // Get record ids for later use in deletion of assign tables
                    $ids = $DB->get_records($table->getName(), array('planid' => $this->id), '', 'id');
                    $DB->delete_records($table->getName(), array('planid' => $this->id));
                    $table = new xmldb_table("dp_plan_{$c}_assign");
                    if ($dbman->table_exists($table)) {
                        foreach ($ids as $i) {
                            $itemids = array_merge($itemids, $DB->get_records($table->getName(), array("{$c}id" => $i), '', 'id'));
                            $DB->delete_records($table->getName(), array("{$c}id" => $i));
                        }
                    }
                }
            } else {
                $table = new xmldb_table("dp_plan_{$c}_assign");
                if ($dbman->table_exists($table)) {
                    $itemids = $DB->get_records($table->getName(), array('planid' => $this->id), '', 'id');
                    $DB->delete_records($table->getName(), array('planid' => $this->id));
                }
            }
            if (!empty($itemids)) {
                // Delete component relations
                foreach ($itemids as $id => $value) {
                    $DB->delete_records('dp_plan_component_relation', array('itemid1' => $id));
                    $DB->delete_records('dp_plan_component_relation', array('itemid2' => $id));
                }
            }
        }
        $DB->delete_records('dp_plan_evidence_relation', array('planid' => $this->id));
        $transaction->allow_commit();
        return true;
    }


    /**
     * Change plan's status
     *
     * @access  public
     * @param  int $status
     * @param  int $reason
     * @param  string $reasontext Reason for declining or approving a plan
     * @return  bool
     */
    public function set_status($status, $reason=DP_PLAN_REASON_MANUAL_APPROVE, $reasontext = '') {
        global $USER, $DB;

        $todb = new stdClass;
        $todb->id = $this->id;
        $todb->status = $status;

        $transaction = $DB->start_delegated_transaction();

        // Handle some status triggers
        switch ($status) {
            case DP_PLAN_STATUS_APPROVED:
                // Set the plan startdate to the approval time if not being reactivate
                if ($reason != DP_PLAN_REASON_MANUAL_REACTIVATE) {
                    $todb->startdate = time();
                }
                plan_activate_plan($this);
                break;
            case DP_PLAN_STATUS_COMPLETE:
                if ($assigned = $this->get_component('competency')->get_assigned_items()) {
                    // Set competency snapshots
                    foreach ($assigned as $a) {
                        $snap = new stdClass;
                        $snap->id = $a->id;
                        $snap->scalevalueid = !empty($a->profscalevalueid) ? $a->profscalevalueid : 0;
                        $DB->update_record('dp_plan_competency_assign', $snap);
                    }
                }
                if ($assigned = $this->get_component('course')->get_assigned_items()) {
                    // Set course completion snapshots
                    foreach ($assigned as $a) {
                        $snap = new stdClass;
                        $snap->id = $a->id;
                        $snap->completionstatus = !empty($a->coursecompletion) ? $a->coursecompletion : null;
                        $DB->update_record('dp_plan_course_assign', $snap);
                    }
                }
                $plantodb = new stdClass;
                $plantodb->id = $this->id;
                $plantodb->timecompleted = time();
                $DB->update_record('dp_plan', $plantodb);
                break;
            default:
                break;
        }
        $DB->update_record('dp_plan', $todb);
        // Update plan history
        $todb = new stdClass;
        $todb->planid = $this->id;
        $todb->status = $status;
        $todb->reason = $reason;
        $todb->reasonfordecision = $reasontext;
        $todb->timemodified = time();
        $todb->usermodified = $USER->id;
        $DB->insert_record('dp_plan_history', $todb);
        $transaction->allow_commit();
        if ($status == DP_PLAN_STATUS_APPROVED) {
            add_to_log(SITEID, 'plan', 'approved', "view.php?id={$this->id}", $this->name);
        }

        return true;
    }


    /**
     * Determine the manager for the user of this Plan
     *
     * @return string
     */
    function get_manager() {
        return totara_get_manager($this->userid);
    }


    /**
     * Send a task to the manager when a learner requests a plan approval
     * @global <type> $USER
     * @global object $CFG
     */
    function send_manager_plan_approval_request() {
        global $USER, $CFG, $DB;

        $manager = totara_get_manager($this->userid);
        $learner = $DB->get_record('user', array('id' => $this->userid));
        if ($manager && $learner) {
            // do the IDP Plan workflow event
            $data = array();
            $data['userid'] = $this->userid;
            $data['planid'] = $this->id;

            $event = new tm_task_eventdata($manager, 'plan', $data, $data);
            //ensure the message is actually coming from $learner, default to support
            $event->userfrom = ($USER->id == $learner->id) ? $learner : core_user::get_support_user();
            $event->contexturl = $this->get_display_url();
            $event->contexturlname = $this->name;
            $event->icon = 'learningplan-request';

            $a = new stdClass;
            $a->learner = fullname($learner);
            $a->plan = s($this->name);
            $stringmanager = get_string_manager();
            $managerlang = $manager->lang;
            $event->subject =      $stringmanager->get_string('plan-request-manager-short', 'totara_plan', $a, $managerlang);
            $event->fullmessage =  $stringmanager->get_string('plan-request-manager-long', 'totara_plan', $a, $managerlang);
            $event->acceptbutton = $stringmanager->get_string('approve', 'totara_plan', null, $managerlang) . ' ' . $stringmanager->get_string('plan', 'totara_plan', null, $managerlang);
            $event->accepttext =   $stringmanager->get_string('approveplantext', 'totara_plan', null, $managerlang);
            $event->rejectbutton = $stringmanager->get_string('decline', 'totara_plan', null, $managerlang) . ' ' . $stringmanager->get_string('plan', 'totara_plan', null, $managerlang);
            $event->rejecttext =   $stringmanager->get_string('declineplantext', 'totara_plan', null, $managerlang);
            $event->infobutton =   $stringmanager->get_string('review', 'totara_plan', null, $managerlang) . ' ' .  $stringmanager->get_string('plan', 'totara_plan', null, $managerlang);
            $event->infotext =     $stringmanager->get_string('reviewplantext', 'totara_plan', null, $managerlang);
            $event->data = $data;

            tm_workflow_send($event);
            $this->set_status(DP_PLAN_STATUS_PENDING, DP_PLAN_REASON_APPROVAL_REQUESTED);

            // Send alert to learner also
            $this->send_alert(true, 'learningplan-request', 'plan-request-learner-short', 'plan-request-learner-long');
        }
    }


    /**
     * Send a task to the manager when a learner requests item's approval
     *
     * @access  public
     * @global  object  $USER
     * @global  object  $CFG
     * @param   array   $unapproved
     * @return  void
     */
    public function send_manager_item_approval_request($unapproved) {
        global $USER, $CFG, $DB;

        $manager = totara_get_manager($this->userid);
        $learner = $DB->get_record('user', array('id' => $this->userid));

        if (!$manager || !$learner) {
            print_error('error:couldnotloadusers', 'totara_plan');
            die();
        }

        // Message data
        $message_data = array();
        $total_items = 0;

        $data = array();
        $data['userid'] = $this->userid;
        $data['planid'] = $this->id;

        // Change items to requested status
        // Loop through components, generating message
        $stringmanager = get_string_manager();
        foreach ($unapproved as $component => $items) {
            $comp = $this->get_component($component);
            $items = $comp->make_items_requested($items);

            // Generate message
            if ($items) {
                $total_items += count($items);
                $message_data[] = count($items).' '. $stringmanager->get_string($comp->component, 'totara_plan', null, $manager->lang);
            }
        }

        $event = new tm_task_eventdata($manager, 'plan', $data, $data);
        //ensure the message is actually coming from $learner, default to support
        $event->userfrom = ($USER->id == $learner->id) ? $learner : core_user::get_support_user();
        $event->contexturl = "{$CFG->wwwroot}/totara/plan/approve.php?id={$this->id}";
        $event->contexturlname = $this->name;
        $event->icon = 'learningplan-request';

        $a = new stdClass;
        $a->learner = fullname($learner);
        $a->plan = s($this->name);
        $a->data = html_writer::alist($message_data);
        $event->subject = $stringmanager->get_string('item-request-manager-short', 'totara_plan', $a, $manager->lang);
        $event->fullmessage = $stringmanager->get_string('item-request-manager-long', 'totara_plan', $a, $manager->lang);
        unset($event->acceptbutton);
        unset($event->onaccept);
        unset($event->rejectbutton);
        unset($event->onreject);
        $event->infobutton = $stringmanager->get_string('review', 'totara_plan', null, $manager->lang).' '.$stringmanager->get_string('items', 'totara_plan', null, $manager->lang);
        $event->infotext = $stringmanager->get_string('reviewitemstext', 'totara_plan', null, $manager->lang);
        $event->data = $data;

        tm_workflow_send($event);
    }


    /**
     * Send an alert relating to this plan
     *
     * @param boolean $tolearner To the learner if true, otherwise to the manager
     * @param string $icon filename of icon (in theme/totara/pix/msgicons/)
     * @param string $subjectstring lang string in totara_plan
     * @param string $fullmessagestring lang string in totara_plan
     * @return boolean
     */
    public function send_alert($tolearner, $icon, $subjectstring, $fullmessagestring) {
        global $CFG, $DB, $USER;
        $manager = totara_get_manager($this->userid);
        $learner = $DB->get_record('user', array('id' => $this->userid));
        if ($learner && $manager) {
            require_once($CFG->dirroot . '/totara/message/eventdata.class.php');
            require_once($CFG->dirroot . '/totara/message/messagelib.php');
            if ($tolearner) {
                $userto = $learner;
                $userfrom = $manager;
                $roleid = $CFG->learnerroleid;
            } else {
                $userto = $manager;
                $userfrom = $learner;
                $roleid = $CFG->managerroleid;
            }
            $event = new tm_alert_eventdata($userto);
            //ensure the message is actually coming from $userfrom, default to support
            $event->userfrom = ($USER->id == $userfrom->id) ? $userfrom : core_user::get_support_user();
            $event->contexturl = $this->get_display_url();
            $event->contexturlname = $this->name;
            $event->icon = $icon;

            $a = new stdClass();
            $a->plan = $this->name;
            $a->manager = fullname($manager);
            $a->learner = fullname($learner);
            $stringmanager = get_string_manager();
            $event->subject = $stringmanager->get_string($subjectstring,'totara_plan',$a, $userto->lang);
            $event->fullmessage = $stringmanager->get_string($fullmessagestring,'totara_plan',$a, $userto->lang);

            return tm_alert_send($event);
        } else {
            return false;
        }
    }


    /**
     * Send approved alerts
     *
     * @global $USER
     * @global $CFG
     * @return void
     */
    function send_approved_alert($reasonfordecision) {
        global $USER, $CFG, $DB;
        require_once($CFG->dirroot.'/totara/message/messagelib.php');

        $userto = $DB->get_record('user', array('id' => $this->userid));
        $userfrom = $DB->get_record('user', array('id' => $USER->id));
        $stringmanager = get_string_manager();

        $event = new stdClass;
        $event->userfrom = $userfrom;
        $event->userto = $userto;
        $event->icon = 'learningplan-approve';
        $event->contexturl = $CFG->wwwroot.'/totara/plan/view.php?id='.$this->id;
        $event->subject = $stringmanager->get_string('planapproved', 'totara_plan', $this->name, $userto->lang);
        $event->fullmessage = $stringmanager->get_string('approvedplanrequest', 'totara_plan', $this->name, $userto->lang);

        if (!empty($reasonfordecision)) {
            $event->fullmessage .= html_writer::empty_tag('br') . html_writer::empty_tag('br');
            $event->fullmessage .= $stringmanager->get_string('reasonapprovedplanrequest', 'totara_plan', $reasonfordecision, $userto->lang);
        }

        tm_alert_send($event);
    }


    /**
     * Send declined alerts
     *
     * @global $USER
     * @global $CFG
     * @param string $reasonfordecision Reason for declining the plan
     * @return void
     */
    function send_declined_alert($reasonfordecision = '') {
        global $USER, $CFG, $DB;
        require_once($CFG->dirroot.'/totara/message/messagelib.php');

        $userto = $DB->get_record('user', array('id' => $this->userid));
        $userfrom = $DB->get_record('user', array('id' => $USER->id));
        $stringmanager = get_string_manager();

        $event = new stdClass;
        $event->userfrom = $userfrom;
        $event->userto = $userto;
        $event->icon = 'learningplan-decline';
        $event->contexturl = $CFG->wwwroot.'/totara/plan/view.php?id='.$this->id;
        $event->subject = format_string($stringmanager->get_string('plandeclined', 'totara_plan', $this->name, $userto->lang));
        $event->fullmessage = $event->subject;
        $event->fullmessage = $stringmanager->get_string('declinedplanrequest', 'totara_plan', $this->name, $userto->lang);

        if (!empty($reasonfordecision)) {
            $event->fullmessage .= html_writer::empty_tag('br') . html_writer::empty_tag('br');
            $event->fullmessage .= $stringmanager->get_string('reasondeclinedplanrequest', 'totara_plan', $reasonfordecision, $userto->lang);
        }

        tm_alert_send($event);
    }


    /**
     * Send completion alerts
     *
     * @global $USER
     * @global $CFG
     * @return void
     */
    function send_completion_alert() {
        global $USER, $CFG, $DB;
        require_once($CFG->dirroot.'/totara/message/messagelib.php');

        $learner = $DB->get_record('user', array('id' => $this->userid));

        // Send alert to manager
        // But don't send it if they just manually performed
        // the completion
        $stringmanager = get_string_manager();
        $manager = totara_get_manager($this->userid);
        if ($manager && $manager->id != $USER->id) {
            $event = new stdClass();
            $event->userto = $manager;
            //ensure the message is actually coming from $learner, default to support
            $event->userfrom = ($USER->id == $learner->id) ? $learner : core_user::get_support_user();
            $event->icon = 'learningplan-complete';
            $event->contexturl = $CFG->wwwroot.'/totara/plan/view.php?id='.$this->id;
            $a = new stdClass();
            $a->learner = fullname($learner);
            $a->plan = $this->name;
            $event->subject = $stringmanager->get_string('plan-complete-manager-short','totara_plan',$a, $manager->lang);
            $event->fullmessage = $stringmanager->get_string('plan-complete-manager-long','totara_plan',$a, $manager->lang);
            tm_alert_send($event);
        }

        // Send alert to user
        $event = new stdClass();
        $event->userto = $learner;
        $event->icon = 'learningplan-complete';
        $event->contexturl = $CFG->wwwroot.'/totara/plan/view.php?id='.$this->id;
        $msg = $stringmanager->get_string('plancompletesuccess', 'totara_plan', $this->name, $learner->lang);
        $event->subject = $msg;
        $event->fullmessage = format_text($msg);
        tm_alert_send($event);
    }

    /**
     * Returns the URL for the page to view this plan
     * @global object $CFG
     * @return string
     */

    public function get_display_url(){
        global $CFG, $DB;
        if ($DB->record_exists('dp_plan', array('id' => $this->id))) {
            return "{$CFG->wwwroot}/totara/plan/view.php?id={$this->id}";
        } else {
            //If plan doesnt exist show plan index page for that user
            return "{$CFG->wwwroot}/totara/plan/index.php?userid={$this->userid}";
        }
    }


    /**
     * Display plan tabs
     *
     * @access  public
     * @param   string  $currenttab Currently selected tab's key
     * @return  string
     */
    public function display_tabs($currenttab) {
        global $CFG;

        $tabs = array();
        $row = array();
        $activated = array();
        $inactive = array();

        // Overview tab
        $row[] = new tabobject(
                'plan',
                "{$CFG->wwwroot}/totara/plan/view.php?id={$this->id}",
                get_string('overview', 'totara_plan')
        );

        // get active components in correct order
        $components = $this->get_components();

        if (!totara_feature_visible('programs')) {
            $components = totara_search_for_value($components, 'component', TOTARA_SEARCH_OP_NOT_EQUAL, 'program');
        }

        if ($components) {
            foreach ($components as $component) {

                $row[] = new tabobject(
                    $component->component,
                    $component->get_url(),
                    $componentname = get_string("{$component->component}plural", 'totara_plan')
                );
            }
        }

        // requested items tabs
        if ($pitems = $this->num_pendingitems()) {
            $row[] = new tabobject(
                'pendingitems',
                "{$CFG->wwwroot}/totara/plan/approve.php?id={$this->id}",
                get_string('pendingitems', 'totara_plan').' ('.$pitems.')'
            );
        }

        $tabs[] = $row;
        $activated[] = $currenttab;

        return print_tabs($tabs, $currenttab, $inactive, $activated, true);
    }


    /**
     * Prints plan header
     *
     * @access  public
     * @param   string  $currenttab Current tab key
     * @param   array   $navlinks   Additional navlinks (optional)
     * @return  void
     */
    public function print_header($currenttab, $navlinks = array(), $printinstructions=true) {
        global $CFG;
        require("{$CFG->dirroot}/totara/plan/header.php");
    }


    /**
     * Reactivates a completed plan
     *
     * @param  int $enddate When reactivating a plan a new enddate for the plan can be optionally set
     * @access public
     * @return bool
     */
    public function reactivate_plan($enddate=null) {
        global $USER, $DB;

        $transaction = $DB->start_delegated_transaction();

        $plan_todb = new stdClass;
        $plan_todb->id = $this->id;
        $plan_todb->timecompleted = null;
        if (!empty($enddate)) {
            $plan_todb->enddate = $enddate;
        }
        $DB->update_record('dp_plan', $plan_todb);
        $this->set_status(DP_PLAN_STATUS_APPROVED, DP_PLAN_REASON_MANUAL_REACTIVATE);
        $components = $this->get_components();
        // Reactivates items for all components
        foreach ($components as $component) {
            if (!$component->reactivate_items()) {
                return false;
            }
        }
        $transaction->allow_commit();

        // Send alerts to notify of reactivation
        $manager = totara_get_manager($this->userid);
        if ($manager && $manager->id != $USER->id) {
            $subjectstring = 'plan-reactivate-manager-short';
            $fullmessagestring = 'plan-reactivate-manager-long';
        } else {
            $subjectstring = 'plan-reactivate-learner-short';
            $fullmessagestring = 'plan-reactivate-learner-long';
        }
        $this->send_alert(true, 'learningplan-regular', $subjectstring, $fullmessagestring);

        return true;
    }

    /**
     * Returns true if all items in a plan are complete
     *
     * @return bool $complete returns true if all items in plan are completed
     */
    public function is_plan_complete() {
        $complete = true;
        $components = $this->get_components();
        foreach ($components as $component) {
            $complete = $complete && $component->items_all_complete();
        }
        return $complete;
    }
}


class PlanException extends Exception { }
