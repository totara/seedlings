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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @author Aaron Barnes <aaronb@catalyst.net.nz>
 * @author Chris Wharton <chrisw@catalyst.net.nz>
 * @package totara
 * @subpackage plan
 */

require_once($CFG->dirroot . '/totara/plan/development_plan.class.php');
require_once($CFG->dirroot . '/totara/plan/role.class.php');
require_once($CFG->dirroot . '/totara/plan/component.class.php');
require_once($CFG->dirroot . '/totara/plan/workflow.class.php');
require_once($CFG->dirroot . '/totara/program/lib.php'); // needed to display required learning in plans menu
require_once($CFG->libdir . '/tablelib.php');

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

// Plan status values
define('DP_PLAN_STATUS_UNAPPROVED', 10);
define('DP_PLAN_STATUS_PENDING', 30);
define('DP_PLAN_STATUS_APPROVED', 50);
define('DP_PLAN_STATUS_COMPLETE', 100);

// Permission values
define('DP_PERMISSION_DENY', 10);
define('DP_PERMISSION_REQUEST', 30);
define('DP_PERMISSION_ALLOW', 50);
define('DP_PERMISSION_APPROVE', 70);

// Due date modes
define('DP_DUEDATES_NONE', 0);
define('DP_DUEDATES_OPTIONAL', 1);
define('DP_DUEDATES_REQUIRED', 2);

// Priority modes
define('DP_PRIORITY_NONE', 0);
define('DP_PRIORITY_OPTIONAL', 1);
define('DP_PRIORITY_REQUIRED', 2);

// Maximum number of priority options
define('DP_MAX_PRIORITY_OPTIONS', 5);

// Number of components displayed per page.
define('DP_COMPONENTS_PER_PAGE', 20);

// Maximum number of required learning to display (programs and certifications)
define('DP_MAX_PROGS_TO_DISPLAY', 5);

//// Plan item Approval status (Note that you should usually check *Plan status* as well as item status)
// Item was added to an approved plan, but declined by manager
define('DP_APPROVAL_DECLINED',          10);
// Item was added to an approved plan by a user with "Request" permission
define('DP_APPROVAL_UNAPPROVED',        20);
// Item was added to an approved plan by a user with "Request" permission, and a
// request for approval was sent to their manager
define('DP_APPROVAL_REQUESTED',         30);
// Item was added to an Unapproved plan, or added to an Approved plan by a user
// with Allow or Approve permission
define('DP_APPROVAL_APPROVED',          50);

// Plan notices
define('DEVELOPMENT_PLAN_GENERAL_CONFIRM_UPDATE', 2);
define('DEVELOPMENT_PLAN_GENERAL_FAILED_UPDATE', 3);

// Plan reasons
define('DP_PLAN_REASON_CREATE', 10);
define('DP_PLAN_REASON_MANUAL_APPROVE', 20);
define('DP_PLAN_REASON_MANUAL_COMPLETE', 40);
define('DP_PLAN_REASON_AUTO_COMPLETE_DATE', 50);
define('DP_PLAN_REASON_AUTO_COMPLETE_ITEMS', 60);
define('DP_PLAN_REASON_MANUAL_REACTIVATE', 80);
define('DP_PLAN_REASON_MANUAL_DECLINE', 90);
define('DP_PLAN_REASON_APPROVAL_REQUESTED', 100);

// Types of competency evidence items
define('PLAN_LINKTYPE_MANDATORY', 1);
define('PLAN_LINKTYPE_OPTIONAL', 0);

// Way a plan has been created
define('PLAN_CREATE_METHOD_MANUAL', 0);
define('PLAN_CREATE_METHOD_COHORT', 1);

// roles available to development plans
// each must have a class definition in
// totara/plan/roles/[ROLE]/[ROLE].class.php
global $DP_AVAILABLE_ROLES;
$DP_AVAILABLE_ROLES = array(
    'learner',
    'manager',
);

global $DP_AVAILABLE_COMPONENTS;
$DP_AVAILABLE_COMPONENTS = array(
    'course',
    'competency',
    'objective',
    'program',
);

// note that new templates will default to the first workflow in this list
global $DP_AVAILABLE_WORKFLOWS;
$DP_AVAILABLE_WORKFLOWS = array(
    'basic',
    'userdriven',
    'managerdriven',
);

global $PLAN_AVAILABLE_LINKTYPES;
$PLAN_AVAILABLE_LINKTYPES = array(
    PLAN_LINKTYPE_MANDATORY,
    PLAN_LINKTYPE_OPTIONAL
);

/**
* Serves plan file type files. Required for M2 File API
*
* @param object $course
* @param object $cm
* @param object $context
* @param string $filearea
* @param array $args
* @param bool $forcedownload
* @param array $options
* @return bool false if file not found, does not return if found - just send the file
*/
function totara_plan_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options=array()) {
    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/{$context->id}/totara_plan/$filearea/$args[0]/$args[1]";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }
    // finally send the file
    send_stored_file($file, 86400, 0, true, $options); // download MUST be forced - security!
}


/**
 * Can logged in user view user's plans
 *
 * @access  public
 * @param   int     $ownerid   Plan's owner
 * @return  boolean
 */
function dp_can_view_users_plans($ownerid) {
    global $USER, $DB;

    if (!isloggedin()) {
        return false;
    }

    $systemcontext = context_system::instance();

    // Check plan templates exist
    static $templateexists;
    if (!isset($templateexists)) {
        $templateexists = (bool) $DB->count_records('dp_template');
    }

    if (!$templateexists) {
        return false;
    }

    // If the user can view any plans
    if (has_capability('totara/plan:accessanyplan', $systemcontext)) {
        return true;
    }

    // If the user cannot view any plans
    if (!has_capability('totara/plan:accessplan', $systemcontext)) {
        return false;
    }

    // If this is the current user's own plans
    if ($ownerid == $USER->id) {
        return true;
    }

    // If this user is their manager
    if (totara_is_manager($ownerid)) {
        return true;
    }
    return false;
}


/**
 * Return plans for a user with a specific status
 *
 * @access  public
 * @param   int     $userid     Owner of plans
 * @param   array   $statuses   Plan statuses
 * @return  array
 */
function dp_get_plans($userid, $statuses=array(DP_PLAN_STATUS_APPROVED)) {
    global $DB;
    list($insql, $inparams) = $DB->get_in_or_equal($statuses);
    $sql = "userid = ? AND status $insql";
    $params = array($userid);
    $params = array_merge($params, $inparams);
    return $DB->get_records_select('dp_plan', $sql, $params);
}

/**
 * Gets Priorities
 *
 * @access  public
 * @return  array a recordset object
 */
function dp_get_priorities() {
    global $DB;

    return $DB->get_records('dp_priority_scale', null, 'sortorder');
}


/**
 * Gets learning plan objectives
 *
 * @access public
 * @return array a recordset object
 */
function dp_get_objectives() {
    global $DB;

    return $DB->get_records('dp_objective_scale', null, 'sortorder');
}

/**
 * Get a list of user IDs of users who can receive alert emails
 *
 * @access  public
 * @param   object       $contextuser  context object
 * @param   string       $type         type of user
 * @return array         $receivers    the users which receive the alert
 */
function dp_get_alert_receivers($contextuser, $type) {
    global $USER;

    $receivers = array();

    $users = get_users_by_capability($contextuser, "totara/plan:receive{$type}alerts");
    if ($users and count($users) > 0) {
        foreach ($users as $key => $user) {
            if ($user->id != $USER->id) {
                $receivers[] = $user->id;
            }
        }
    }

    return $receivers;
}

/**
 * Adds permission selector to the form
 *
 * @access  public
 * @param   object  $form  the form object
 * @param   string  $name  the form element name
 * @param   boolean $requestable
 */
function dp_add_permissions_select(&$form, $name, $requestable) {
    global $OUTPUT;
    $select_options = array();

    $select_options[DP_PERMISSION_ALLOW] = get_string('allow', 'totara_plan');
    $select_options[DP_PERMISSION_DENY] = get_string('deny', 'totara_plan');

    if ($requestable) {
        $select_options[DP_PERMISSION_REQUEST] = get_string('request', 'totara_plan');
        $select_options[DP_PERMISSION_APPROVE] = get_string('approve', 'totara_plan');
    }

    $form->addElement('select', $name, null, $select_options);
    // modify the renderer to remove unnecessary label divs
    $renderer =& $form->defaultRenderer();
    $select_elementtemplate = $OUTPUT->container('{element}', 'fitem');
    $renderer->setElementTemplate($select_elementtemplate, $name);

}

/**
 * Adds permissions table headings to the form
 *
 * @access  public
 * @param   object  $form  the form object
 */
function dp_add_permissions_table_headings(&$form) {
    global $DP_AVAILABLE_ROLES, $OUTPUT;
    $out = html_writer::start_tag('div', array('id' => 'planpermissionsform'));
    $out .= html_writer::start_tag('table', array('class' => "planpermissions"));
    $out .= html_writer::start_tag('tr') . html_writer::tag('th', get_string('action', 'totara_plan'));

    foreach ($DP_AVAILABLE_ROLES as $role) {
        $out .= html_writer::tag('th', get_string($role, 'totara_plan'));
    }
    $out .= html_writer::end_tag('tr');
    $form->addElement('html', $out);
    return;
}

/**
 * Adds permissions table row to the form
 *
 * @access  public
 * @param   object  $form  the form object
 * @param   string  $name  the form element name
 * @param   string  $label the form element label
 * @param   boolean $requestable
 */
function dp_add_permissions_table_row(&$form, $name, $label, $requestable) {
    global $DP_AVAILABLE_ROLES;
    $out = html_writer::start_tag('tr') . html_writer::tag('td', $label, array('id' => 'action'));
    $form->addElement('html', $out);
    foreach ($DP_AVAILABLE_ROLES as $role) {
        $form->addElement('html', html_writer::start_tag('td'));
        dp_add_permissions_select($form, $name.$role, $requestable);
        $form->addElement('html', html_writer::end_tag('td'));
    }
    $form->addElement('html', html_writer::end_tag('tr'));
    return;
}

/**
 * Determines which components are visible
 *
 * @param    int     $userid    component visibility for this user
 * @return   array              the components that are visible
 */
function dp_get_rol_tabs_visible($userid) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/totara/cohort/lib.php');

    $visible = array();

    $show_course_tab = false;
    $show_competency_tab = false;
    $show_objective_tab = false;
    $show_program_tab = false;

    $plans = dp_get_plans($userid);
    foreach ($plans as $p) {
        $plan = new development_plan($p->id);

        foreach ($plan->get_components() as $component) {
            if (!${'show_' . $component->component . '_tab'}) {
                ${'show_' . $component->component . '_tab'} = display_rol_tab_for_component($component);
            }
        }
    }

    $course_count = enrol_get_users_courses($userid);
    if (!empty($course_count) || $show_course_tab) {
        $visible[] = 'courses';
    }

    $assigned_comps = $DB->count_records('comp_record', array('userid' => $userid));
    if ($assigned_comps > 0 || $show_competency_tab) {
        $visible[] = 'competencies';
    }

    if ($show_objective_tab) {
        $visible[] = 'objectives';
    }

    $params = array('contextlevel' => CONTEXT_PROGRAM, 'uid' => $userid, 'eid' => PROGRAM_EXCEPTION_RAISED);
    list($visibilitysql, $visibilityparams) = totara_visibility_where($userid,
                                                                      'p.id',
                                                                      'p.visible',
                                                                      'p.audiencevisible',
                                                                      'p',
                                                                      'program');
    $params = array_merge($params, $visibilityparams);
    $sql = "SELECT COUNT(pua.programid)
            FROM {prog_user_assignment} pua
            INNER JOIN {prog} p ON p.id = pua.programid
            INNER JOIN {context} ctx ON (p.id = ctx.instanceid AND ctx.contextlevel =:contextlevel)
            WHERE pua.userid = :uid
              AND pua.exceptionstatus != :eid
              AND p.certifid IS NULL
              AND {$visibilitysql}";
    $assigned_progs = $DB->count_records_sql($sql, $params);
    if (($assigned_progs > 0 || $show_program_tab) && totara_feature_visible('programs')) {
        $visible[] = 'programs';
    }

    $visible[] = 'evidence';

    $certification_progs = prog_get_certification_programs($userid, '', '', '', true, true);
    $unassignedcertifications = $DB->record_exists('certif_completion_history', array('userid' => $userid, 'unassigned' => 1));
    if (($certification_progs > 0 || $unassignedcertifications) && totara_feature_visible('certifications')) {
        $visible[] = 'certifications';
    }

    return $visible;
}

/**
 * Prints the tabs for record of learning
 *
 * @param string $rolstatus - what record of learning status
 * @param type $currenttab - current tab where are on
 * @param type $userid - userid if any
 */
function dp_print_rol_tabs($rolstatus = null, $currenttab = null, $userid = '') {
    global $USER;

    if (is_null($rolstatus)) {
        $rolstatus = 'all';
    }

    $params = array('userid' => $userid, 'status' => $rolstatus);

    $userid = !empty($userid) ? $userid : $USER->id;

    // Tab bar.
    $tabs = array();
    $row = array();

    if ($visible = dp_get_rol_tabs_visible($userid)) {
        foreach ($visible as $element) {
            if ($element !== 'evidence') {
                $row[] = new tabobject(
                        $element,
                        new moodle_url("/totara/plan/record/{$element}.php", $params),
                        get_string($rolstatus . $element, 'totara_plan')
                );
            } else {
                $row[] = new tabobject(
                        'evidence',
                        new moodle_url('/totara/plan/record/evidence/index.php', $params),
                        get_string($rolstatus . 'evidence', 'totara_plan')
                );
            }
        }
    }

    $tabs[] = $row;

    print_tabs($tabs, $currenttab);
}

/**
 * Prints the workflow settings table
 *
 * @access public
 * @param  array  $diff_array holds the workflow setting values
 * @return string $return     the text data to be displayed
 */
function dp_print_workflow_diff($diff_array) {
    global $OUTPUT;
    $columns[] = 'component';
    $headers[] = get_string('component', 'totara_plan');
    $columns[] = 'setting';
    $headers[] = get_string('setting', 'totara_plan');
    $columns[] = 'role';
    $headers[] = get_string('role', 'totara_plan');
    $columns[] = 'before';
    $headers[] = get_string('before', 'totara_plan');
    $columns[] = 'after';
    $headers[] = get_string('after', 'totara_plan');

    $baseurl = new moodle_url( '/totara/plan/template/workflow.php');
    $table = new flexible_table('Templates');
    $table->define_columns($columns);
    $table->define_headers($headers);
    $table->define_baseurl($baseurl);
    $return = html_writer::start_tag('p');
    $return .= $OUTPUT->heading(get_string('changes', 'totara_plan'), 3);

    $table->setup();

    $permission_options = array(DP_PERMISSION_ALLOW => get_string('allow', 'totara_plan'),
        DP_PERMISSION_DENY => get_string('deny', 'totara_plan'),
        DP_PERMISSION_REQUEST => get_string('request', 'totara_plan'),
        DP_PERMISSION_APPROVE => get_string('approve', 'totara_plan')
    );

    $duedate_options = array(DP_DUEDATES_NONE => get_string('none'),
        DP_DUEDATES_OPTIONAL => get_string('optional', 'totara_plan'),
        DP_DUEDATES_REQUIRED => get_string('required', 'totara_plan')
    );

    $priority_options = array(DP_PRIORITY_NONE => get_string('none'),
        DP_PRIORITY_OPTIONAL => get_string('optional', 'totara_plan'),
        DP_PRIORITY_REQUIRED => get_string('required', 'totara_plan')
    );

    foreach ($diff_array as $item => $values) {
        $parts = explode('_', $item);
        $tablerow = array();
        if ($parts[0] == 'perm') {
            if ($parts[1] != 'plan') {
                $configsetting = get_config(null, 'dp_'.$parts[1]);
                $compname = $configsetting ? $configsetting : get_string($parts[1], 'totara_plan');
                $tablerow[] = $compname;
            } else {
                $tablerow[] = get_string($parts[1], 'totara_plan');
            }
            $tablerow[] = get_string($parts[2], 'totara_plan');
            $tablerow[] = get_string($parts[3], 'totara_plan');
            $tablerow[] = $permission_options[$values['before']];
            $tablerow[] = $permission_options[$values['after']];
        } else {
            if ($parts[1] != 'plan') {
                $configsetting = get_config(null, 'dp_'.$parts[1]);
                $compname = $configsetting ? $configsetting : get_string($parts[1], 'totara_plan');
                $tablerow[] = $compname;
            } else {
                $tablerow[] = get_string($parts[1], 'totara_plan');
            }
            $tablerow[] = get_string($parts[2], 'totara_plan');
            $tablerow[] = get_string('na', 'totara_plan');
            switch($parts[2]) {
                case 'duedatemode':
                    $tablerow[] = $duedate_options[$values['before']];
                    $tablerow[] = $duedate_options[$values['after']];
                    break;

                case 'prioritymode':
                    $tablerow[] = $priority_options[$values['before']];
                    $tablerow[] = $priority_options[$values['after']];
                    break;

                case 'priorityscale':
                    $tablerow[] = $values['before'];
                    $tablerow[] = $values['after'];
                    break;

                case 'objectivescale':
                    $tablerow[] = $values['before'];
                    $tablerow[] = $values['after'];
                    break;

                case 'autoassignpos':
                    $tablerow[] = $values['before'] == 0 ? get_string('no') : get_string('yes');
                    $tablerow[] = $values['after'] == 0 ? get_string('no') : get_string('yes');
                    break;

                case 'autoassignorg':
                    $tablerow[] = $values['before'] == 0 ? get_string('no') : get_string('yes');
                    $tablerow[] = $values['after'] == 0 ? get_string('no') : get_string('yes');
                    break;

                case 'includecompleted':
                    $tablerow[] = $values['before'] == 0 ? get_string('no') : get_string('yes');
                    $tablerow[] = $values['after'] == 0 ? get_string('no') : get_string('yes');
                    break;

                case 'autoassigncourses':
                    $tablerow[] = $values['before'] == 0 ? get_string('no') : get_string('yes');
                    $tablerow[] = $values['after'] == 0 ? get_string('no') : get_string('yes');
                    break;
            }
        }

        $table->add_data($tablerow);
    }

    ob_start();
    $table->finish_html();
    echo html_writer::empty_tag('br');
    $return = ob_get_contents();
    ob_end_clean();

    return $return;
}


/**
 * Return markup for displaying a user's plans
 *
 * Optionally filter by plan status, and chose columns to display
 *
 * @access  public
 * @param   int     $userid     Plan owner
 * @param   array   $statuses   Plan status to filter by
 * @param   array   $cols       Columns to display
 * @return  string
 */
function dp_display_plans($userid, $statuses=array(DP_PLAN_STATUSAPPROVED), $cols=array('enddate', 'status', 'completed'), $firstcolheader='') {
    global $CFG, $USER, $DB;

    $statuses_string = is_array($statuses) ? implode(',', $statuses) : $statuses;
    $statuses_undrsc = str_replace(',', '_', $statuses_string);
    $cols = is_array($cols) ? $cols : array($cols);

    // Construct sql query
    $count = 'SELECT COUNT(*) ';
    $select = 'SELECT p.id, p.name AS "name_'.$statuses_undrsc.'"';
    foreach ($cols as $c) {
        if ($c == 'completed') {
            continue;
        }
        $select .= ", p.{$c} AS \"{$c}_{$statuses_undrsc}\"";
    }
    if (in_array('completed', $cols)) {
        $select .= ", phmax.timemodified
            AS timemodified_{$statuses_undrsc} ";
    }

    $from = "FROM {dp_plan} p ";

    if (in_array('completed', $cols)) {
        $from .= "LEFT JOIN (SELECT planid, max(timemodified) as timemodified FROM {dp_plan_history} GROUP BY planid) phmax ON p.id = phmax.planid ";
    }
    list($insql, $inparams) = $DB->get_in_or_equal($statuses);
    $where = "WHERE userid = ? AND status $insql ";
    $params = array($userid);
    $params = array_merge($params, $inparams);
    $count = $DB->count_records_sql($count.$from.$where, $params);

    // Set up table
    $tableheaders = array();
    $tablename = 'plans-list-'.$statuses_undrsc;
    $tablecols = array('name_'.$statuses_undrsc);

    // Determine what the first column header should be
    if (empty($firstcolheader)) {
        $tableheaders[] = get_string('plan', 'totara_plan');
    } else {
        $tableheaders[] = $firstcolheader;
    }

    if (in_array('enddate', $cols)) {
        $tableheaders[] = get_string('duedate', 'totara_plan');
        $tablecols[] = 'enddate_'.$statuses_undrsc;
    }
    if (in_array('status', $cols)) {
        $tableheaders[] = get_string('status', 'totara_plan');
        $tablecols[] = 'status_'.$statuses_undrsc;
    }
    if (in_array('completed', $cols)) {
        $tableheaders[] = get_string('completed', 'totara_plan');
        $tablecols[] = 'timemodified_'.$statuses_undrsc;
    }

    // Actions
    $tableheaders[] = get_string('actions', 'totara_plan');
    $tablecols[] = 'actioncontrols';

    $baseurl = $CFG->wwwroot . '/totara/plan/index.php';
    if ($userid != $USER->id) {
        $baseurl .= '?userid=' . $userid;
    }
    ob_start();
    $table = new flexible_table($tablename);
    $table->define_headers($tableheaders);
    $table->define_columns($tablecols);
    $table->define_baseurl($baseurl);
    $table->set_attribute('class', 'logtable generalbox');
    $table->set_attribute('width', '100%');
    $table->set_control_variables(array(
        TABLE_VAR_SORT    => 'tsort',
    ));
    $table->sortable(true);
    $table->no_sorting('actioncontrols');
    if (in_array('status', $cols)) {
        $table->no_sorting('status_'.$statuses_undrsc);
    }
    $table->setup();
    $table->pagesize(15, $count);
    $sort = $table->get_sql_sort();
    $sort = empty($sort) ? '' : ' ORDER BY '.$sort;

    // Add table data
    $plans = $DB->get_records_sql($select.$from.$where.$sort, $params, $table->get_page_start(), $table->get_page_size());
    if (empty($plans)) {
        return '';
    }
    $rownumber = 0;
    foreach ($plans as $p) {
        $plan = new development_plan($p->id);
        if ($plan->get_setting('view') == DP_PERMISSION_ALLOW) {
            $row = array();
            $row[] = $plan->display_summary_widget();
            if (in_array('enddate', $cols)) {
                $row[] = $plan->display_enddate();
            }
            if (in_array('status', $cols)) {
                $row[] = $plan->display_progress();
            }
            if (in_array('completed', $cols)) {
                $row[] = $plan->display_completeddate();
            }
            $row[] = $plan->display_actions();

            if (++$rownumber >= $count) {
                $table->add_data($row, 'last');
            } else {
                $table->add_data($row);
            }
        }
    }
    unset($plans);

    $table->finish_html();
    $out = ob_get_contents();
    ob_end_clean();

    return $out;
}

/**
 * Displays the plan menu
 *
 * @access public
 * @param  int    $userid           the id of the current user
 * @param  int    $selectedid       the selected id
 * @param  string $role             the role of the user
 * @param  string $rolpage          the record of learning page (to keep track of which tab is selected)
 * @param  string $rolstatus        the record of learning status (to keep track of which menu item is selected)
 * @param  bool   $showrol          determines if the record of learning should be shown
 * @param  int    $selectedprogid   the selected program id
 * @param  bool   $showrequired     determines if the record of learning should be shown
 * @return string $out              the form to display
 */
function dp_display_plans_menu($userid, $selectedid=0, $role='learner', $rolpage='courses', $rolstatus='none', $showrol=true, $selectedprogid=0, $showrequired=true) {
    global $OUTPUT, $DB, $CFG;
    $list = array();
    $attr = array();
    $enableplans = totara_feature_visible('learningplans');

    $out = $OUTPUT->container_start(null, 'dp-plans-menu');

    if ($role == 'manager') {
        // Print out the All team members link
        $out .= $OUTPUT->heading(get_string('teammembers', 'totara_plan'), 3, 'main');
        $class = $userid == 0 ? 'dp-menu-selected' : '';
        $out .= html_writer::alist(array($OUTPUT->action_link(new moodle_url('/my/teammembers.php'), get_string('allteammembers', 'totara_plan'))), array('class' => $class));
        if ($userid) {
            // Display who we are currently viewing if appropriate
            $out .= $OUTPUT->heading(get_string('currentlyviewing', 'totara_plan'), 3, 'main');
            // TODO: make this more efficient
            $user = $DB->get_record('user', array('id' => $userid));
            $class = $selectedid == 0 ? 'dp-menu-selected' : '';
            $out .= html_writer::alist(array($OUTPUT->action_link(new moodle_url('/totara/plan/index.php', array('userid' => $userid)), "$user->firstname $user->lastname")), array('class' => $class));
        }
    }

    // Display active plans
    if ($enableplans && $plans = dp_get_plans($userid, array(DP_PLAN_STATUS_APPROVED))) {
        if ($role == 'manager') {
            $out .= $OUTPUT->container_start(null, 'dp-plans-menu-section');
            $out .= $OUTPUT->heading(get_string('activeplans', 'totara_plan'), 4, 'dp-plans-menu-sub-header');
        }
        else {
            $out .= $OUTPUT->heading(get_string('activeplans', 'totara_plan'), 3, 'main');
        }

        $list = array();
        foreach ($plans as $p) {
            $attr['class'] = $p->id == $selectedid ? 'dp-menu-selected' : '';
            $list[] = $OUTPUT->action_link(new moodle_url('/totara/plan/view.php', array('id' => $p->id)), $p->name);
        }
        $out .= html_writer::alist($list, $attr);
        if ($role == 'manager') {
            $out .= $OUTPUT->container_end();
        }
    }

    // Display unapproved plans
    if ($enableplans && $plans = dp_get_plans($userid, array(DP_PLAN_STATUS_UNAPPROVED, DP_PLAN_STATUS_PENDING))) {
        if ($role == 'manager') {
            $out .= $OUTPUT->container_start(null, 'dp-plans-menu-section');
            $out .= $OUTPUT->heading(get_string('unapprovedplans', 'totara_plan'), 4, 'dp-plans-menu-sub-header');
        }
        else {
            $out .= $OUTPUT->heading(get_string('unapprovedplans', 'totara_plan'), 3, 'main');
        }

        $list = array();
        foreach ($plans as $p) {
            $attr['class'] = $p->id == $selectedid ? 'dp-menu-selected' : '';
            $list[] = $OUTPUT->action_link(new moodle_url('/totara/plan/view.php', array('id' => $p->id)), $p->name);
        }
        $out .= html_writer::alist($list, $attr);

        if ($role == 'manager') {
            $out .= $OUTPUT->container_end();
        }
    }

    // Display completed plans
    if ($enableplans && $plans = dp_get_plans($userid, DP_PLAN_STATUS_COMPLETE)) {
        if ($role == 'manager') {
            $out .= $OUTPUT->container_start(null, 'dp-plans-menu-section');
            $out .= $OUTPUT->heading(get_string('completedplans', 'totara_plan'), 4, 'dp-plans-menu-sub-header');
        }
        else {
            $out .= $OUTPUT->heading(get_string('completedplans', 'totara_plan'), 3, 'main');
        }

        $list = array();
        foreach ($plans as $p) {
            $attr['class'] = $p->id == $selectedid ? 'dp-menu-selected' : '';
            $list[] = $OUTPUT->action_link(new moodle_url('/totara/plan/view.php', array('id' => $p->id)), $p->name);
        }
        $out .= html_writer::alist($list, $attr);

        if ($role == 'manager') {
            $out .= $OUTPUT->container_end();
        }
    }

    // Print Required Learning menu
    if ($showrequired) {
        $programs = prog_get_required_programs($userid, ' ORDER BY fullname ASC ', '', '', false, true);
        $certifications = prog_get_certification_programs($userid, ' ORDER BY fullname ASC ', '', '', false, true, true);
        if ($programs || $certifications) {
            $canviewprograms = totara_feature_visible('programs');
            $canviewcertifications = totara_feature_visible('certifications');
            $extraparams = array();
            $headingclass = 'main';
            if ($role == 'manager') {
                $extraparams['userid'] = $userid;
                $out .= $OUTPUT->container_start(null, 'dp-plans-menu-section');
                $headingclass = 'dp-plans-menu-sub-header';
            }
            $out .= $OUTPUT->heading(get_string('requiredlearning', 'totara_program'), 3, $headingclass);

            if ($programs && $canviewprograms) {
                $list = dp_display_plans_menu_required($programs, $extraparams);
                $out .= $OUTPUT->heading(get_string('programs', 'totara_program'), 5);
                $out .= html_writer::alist($list, $attr);
            }
            if ($certifications && $canviewcertifications) {
                $list = dp_display_plans_menu_required($certifications, $extraparams, count($list));
                $out .= $OUTPUT->heading(get_string('certifications', 'totara_program'), 5);
                $out .= html_writer::alist($list, $attr);
            }
            if ($role == 'manager') {
                $out .= $OUTPUT->container_end();
            }
        }
    }

    // Print Record of Learning menu
    if ($showrol) {
        $out .= dp_record_status_menu($rolpage, $rolstatus, $userid);
    }

    $out .= $OUTPUT->container_end();

    return $out;
}

function dp_display_plans_menu_required($programs, $extraparams, $progcount=0) {
    global $OUTPUT;
    $list = array();
    foreach ($programs as $p) {
        $urlparams = $extraparams;

        if (count($list) + $progcount >= DP_MAX_PROGS_TO_DISPLAY) {
            $list[] = $OUTPUT->action_link(new moodle_url('/totara/program/required.php', $urlparams), get_string('viewallrequiredlearning', 'totara_program'));
            break;
        }
        // hide inaccessible programs
        $prog = new program($p->id);
        if (!$prog->is_accessible()) {
            continue;
        }
        $urlparams['id'] = $p->id;
        $list[] = $OUTPUT->action_link(new moodle_url('/totara/program/required.php', $urlparams), $p->fullname);
    }
    return($list);
}

/**
 * Display the user message box
 *
 * @access public
 * @param  int    $planuser the id of the user
 * @return string $out      the display code
 */
function dp_display_user_message_box($planuser) {
    global $OUTPUT, $CFG, $DB;
    $user = $DB->get_record('user', array('id' => $planuser));
    if (!$user) {
        return false;
    }

    $a = new stdClass();
    $a->name = fullname($user);
    $a->userid = $planuser;
    $a->site = $CFG->wwwroot;

    $r = new html_table_row(array(
        $OUTPUT->user_picture($user),
        get_string('youareviewingxsplans', 'totara_plan', $a),
    ));

    $t = new html_table();
    $t->attributes['class'] = 'invisiblepadded';
    $t->data[] = $r;
    return html_writer::tag('div', html_writer::table($t), array('class' => "plan_box notifymessage"));
}

/*
 * Deletes a plan
 *
 * @access public
 * @param  int    $planid  the id of the plan to be deleted
 * @return false|true
 */
function dp_plan_delete($planid) {
    $plan = new development_plan($planid);

    return $plan->delete();
}

/**
 * Gets the first template in the table
 *
 * @access public
 * @return array
 */
function dp_get_default_template() {
    global $DB;
    $template = $DB->get_record('dp_template', array('isdefault' => 1));

    return $template;
}

/**
 * Gets a list of templates
 *
 * @access public
 * @return array
 */
function dp_get_templates() {
    global $DB;

    $templates = $DB->get_records('dp_template', array('visible' => 1), 'sortorder');

    return $templates;
}

/**
 * Gets the template permission value
 *
 * @access public
 * @param  int    $templateid the id of the template
 * @param  string $component  the component type to check
 * @param  string $action     the action to check
 * @param  string $role       the user role
 * @return false|int $permission->value
 */
function dp_get_template_permission($templateid, $component, $action, $role) {
    global $DB;

    $sql = "templateid = ? AND role = ? AND component = ? AND action = ?";
    $params = array($templateid, $role, $component, $action);
    $permission = $DB->get_record_select('dp_permissions', $sql, $params, 'value');

    return $permission->value;
}

/**
 * Gets all templates with the given permission value
 *
 * @access public
 * @param  string $component  the component type
 * @param  string $action     the action to perform
 * @param  string $role       the user role
 * @param  int $permission    the permission value
 * @return array $templates an array if template ids
 */
function dp_template_has_permission($component, $action, $role, $permission) {
    global $DB;

    $sql = 'role = ? AND component = ? AND action = ? AND value = ?';
    $params = array($role, $component, $action, $permission);
    $templates = $DB->get_records_select('dp_permissions', $sql, $params, 'id', 'templateid');

    return array_keys($templates);
}

/**
 * Display a pulldown for filtering record of learning page
 *
 * @param string $pagename Name of the current page (filename without .php)
 * @param string $status The status for the current page
 *
 * @return string HTML to display the picker
 */
function dp_record_status_picker($pagename, $status, $userid=null) {
    global $OUTPUT;

    // generate options for status pulldown
    $options = array();
    $selected = null;
    foreach (array('all','active','completed') as $s) {
        if ($status == $s) {
            $selected = $s;
        }
        $options[$s] = get_string($s . 'learning', 'totara_plan');
    }

    $label = html_writer::tag('strong', get_string('filterbystatus', 'totara_plan')) . '&nbsp;';

    // display status pulldown
    $form = $OUTPUT->single_select(
        new moodle_url("/totara/plan/record/{$pagename}.php", array('userid' => $userid, 'status' => '')),
        'status',
        $options,
        $selected
    );

    return html_writer::tag('div', $label . $form, array('id' => 'recordoflearning_statuspicker'));
}

/**
 * Display a menu for filtering record of learning page
 *
 * @param string $pagename Name of the current page (filename without .php)
 * @param string $status The status for the current page
 * @param int $userid The current users id
 *
 * @return string HTML to display the menu
 */

function dp_record_status_menu($pagename, $status, $userid=null) {
    global $OUTPUT;

    $out = $OUTPUT->heading(get_string('recordoflearning', 'totara_core'), 3, 'main');

    // generate options for menu display
    $filter = array();
    $items = array();
    foreach (array('all','active','completed') as $s) {
        $filter[$s] = get_string($s . 'learning', 'totara_plan');
        $class = $status == $s ? "dp-menu-selected" : '';
        $items[] = $OUTPUT->action_link(new moodle_url("/totara/plan/record/{$pagename}.php", array('userid' => $userid, 'status' => $s)), $filter[$s], null, array('class' => $class));
    }
    $out .= html_writer::alist($items);
    return $out;
}

/**
 * Add lowest levels of breadcrumbs to plan
 *
 * Exact links added depends on if the plan belongs to the current
 * user or not.
 *
 * @param integer $userid ID of the plan's owner
 *
 * @return boolean True if it is the user's own plan
 */
function dp_get_plan_base_navlinks($userid) {
    global $USER, $PAGE, $DB;
    // the user is viewing their own plan
    if ($userid == $USER->id) {
        $PAGE->navbar->add(get_string('mylearning', 'totara_core'), new moodle_url('/my/'));
        $PAGE->navbar->add(get_string('learningplans', 'totara_plan'), new moodle_url('/totara/plan/index.php'));
        return true;
    }

    // the user is viewing someone else's plan
    $user = $DB->get_record('user', array('id' => $userid));
    if ($user) {
        $PAGE->navbar->add(get_string('myteam', 'totara_core'), new moodle_url('/my/teammembers.php'));
        $PAGE->navbar->add(get_string('xslearningplans', 'totara_plan', fullname($user)), new moodle_url('/totara/plan/index.php', array('userid' => $userid)));
    } else {
        $PAGE->navbar->add(get_string('unknownuserslearningplans', 'totara_plan'), new moodle_url('/totara/plan/index.php', array('userid' => $userid)));
    }
}

/**
 * Gets the approval status, given the approval code (e.g 50)
 *
 * @access public
 * @param  int    $code   the status code
 * @return string $status the plan approval status
 */
function dp_get_approval_status_from_code($code) {
    switch ($code) {
        case DP_APPROVAL_DECLINED:
            $status = get_string('declined', 'totara_plan');
            break;
        case DP_APPROVAL_UNAPPROVED:
            $status = get_string('unapproved', 'totara_plan');
            break;
        case DP_APPROVAL_REQUESTED:
            $status = get_string('pendingapproval', 'totara_plan');
            break;
        case DP_APPROVAL_APPROVED:
            $status = get_string('approved', 'totara_plan');
            break;
        default:
            $status = get_string('unknown', 'totara_plan');
            break;
    }

    return $status;
}


/**
 * Create a new template based on a template object
 *
 * @param string $templatename Name for the template
 * @param integer $enddate Unix timestamp of template enddate
 *
 * @return integer|false ID of new template or false if unsuccessful
 */
function dp_create_template($templatename, $enddate, &$error) {
    global $CFG, $DB, $DP_AVAILABLE_WORKFLOWS, $DP_AVAILABLE_COMPONENTS;

    $transaction = $DB->start_delegated_transaction();

    $todb = new stdClass();
    $todb->fullname = $templatename;
    $todb->enddate = $enddate;
    $sortorder = $DB->get_field('dp_template', 'MAX(sortorder)', array()) + 1;
    $todb->sortorder = $sortorder;
    $todb->visible = 1;
    $todb->isdefault = 0;
    // by default use first listed workflow
    reset($DP_AVAILABLE_WORKFLOWS);
    $workflow = current($DP_AVAILABLE_WORKFLOWS);
    $todb->workflow = $workflow;
    $newtemplateid = $DB->insert_record('dp_template', $todb);

    foreach ($DP_AVAILABLE_COMPONENTS as $component) {
        $classfile = $CFG->dirroot .
            "/totara/plan/components/{$component}/{$component}.class.php";
        if (!is_readable($classfile)) {
            $string_properties = new stdClass();
            $string_properties->classfile = $classfile;
            $string_properties->component = $component;
            $error = get_string('noclassfileforcomponent', 'totara_plan', $string_properties);
            return false;
        }
        include_once($classfile);
        // check class exists
        $class = "dp_{$component}_component";
        if (!class_exists($class)) {
            $string_properties = new stdClass();
            $string_properties->class = $class;
            $string_properties->component = $component;
            $error = get_string('noclassforcomponent', 'totara_plan', $string_properties);
            return false;
        }
        $cn = new stdClass();
        $cn->templateid = $newtemplateid;
        $cn->component = $component;
        $cn->enabled = 1;
        $sortorder = $DB->get_field_sql("SELECT max(sortorder) FROM {dp_component_settings}");
        $cn->sortorder = $sortorder + 1;
        $componentsettingid = $DB->insert_record('dp_component_settings', $cn);
    }
    $classfile = $CFG->dirroot . "/totara/plan/workflows/{$workflow}/{$workflow}.class.php";
    if (!is_readable($classfile)) {
        $string_properties = new stdClass();
        $string_properties->classfile = $classfile;
        $string_properties->workflow = $workflow;
        $error = get_string('noclassfileforworkflow', 'totara_plan', $string_properties);
        return false;
    }
    include_once($classfile);
    // check class exists
    $class = "dp_{$workflow}_workflow";
    if (!class_exists($class)) {
        $string_properties = new stdClass();
        $string_properties->class = $classfile;
        $string_properties->workflow = $workflow;
        $error = get_string('noclassforworkflow', 'totara_plan', $string_properties);
        return false;
    }
    // create an instance and save as a property for easy access
    $workflow_class = new $class();
    if (!$workflow_class->copy_to_db($newtemplateid)) {
        $error = get_string('error:newdptemplate', 'totara_plan');
        return false;
    }
    $transaction->allow_commit();
    return $newtemplateid;
}


/**
 * Find all plans a specified item is part of
 *
 * @param int $userid ID of the user updating the item
 * @param string $component Name of the component (eg. course, competency, objective)
 * @param int $componentid ID of the component item (eg. competencyid, objectiveid)
 *
 */
function dp_plan_item_updated($userid, $component, $componentid) {
    global $CFG;
    // Include component class file
    $component_include = $CFG->dirroot . '/totara/plan/components/' . $component . '/' . $component . '.class.php';
    if (file_exists($component_include)) {
        require_once($component_include);
    }
    $plans = call_user_func(array("dp_{$component}_component","get_plans_containing_item"), $componentid, $userid);
    dp_plan_check_plan_complete($plans);
}

/**
 * Checks if any of the plans is complete and if the auto completion by plans option is set
 * then the plan is completed
 *
 * @param array $plans list of plans to be checked
 *
 */
function dp_plan_check_plan_complete($plans) {
    if ($plans) {
        foreach ($plans as $planid) {
            $plan = new development_plan($planid);
            if ($plan->is_plan_complete() && $plan->get_setting('autobyitems') && $plan->is_active()) {
                $plan->set_status(DP_PLAN_STATUS_COMPLETE, DP_PLAN_REASON_AUTO_COMPLETE_ITEMS);
            }
        }
    }
}


///
/// Comments callback functions
///

function totara_plan_comment_permissions($details) {
    global $DB;


    $validareas = array('plan_overview', 'plan_course_item', 'plan_competency_item', 'plan_objective_item', 'plan_program_item');
    if (!in_array($details->commentarea, $validareas)) {
        throw new comment_exception('invalidcommentarea');
    }

    $planid = 0;
    switch ($details->commentarea) {
        case 'plan_overview' :
            $planid = $details->itemid;
            break;
        case 'plan_course_item':
            $planid = $DB->get_field('dp_plan_course_assign', 'planid', array('id' => $details->itemid));
            break;
        case 'plan_competency_item':
            $planid = $DB->get_field('dp_plan_competency_assign', 'planid', array('id' => $details->itemid));
            break;
        case 'plan_objective_item':
            $planid = $DB->get_field('dp_plan_objective', 'planid', array('id' => $details->itemid));
            break;
        case 'plan_program_item':
            $planid = $DB->get_field('dp_plan_program_assign', 'planid', array('id' => $details->itemid));
        default:
            break;

    }

    if (!$planid) {
        throw new comment_exception('invalidcommentitemid');
    }

    $plan = new development_plan($planid);
    if (!has_capability('totara/plan:accessanyplan', $details->context) && ($plan->get_setting('view') < DP_PERMISSION_ALLOW)) {
        return array('post' => false, 'view' => false);
    } else {
        return array('post' => true, 'view' => true);
    }
}

function totara_plan_comment_template() {
    global $OUTPUT, $PAGE;

    // Use the totara default comment template
    $renderer = $PAGE->get_renderer('totara_core');

    return $renderer->comment_template();
}

/**
 * Validates the comment parameters
 *
 * @param stdClass $comment {
 *              context  => context the context object
 *              courseid => int course id
 *              cm       => stdClass course module object
 *              commentarea => string comment area
 *              itemid      => int itemid
 * }
 *
 * @return boolean
 */
function totara_plan_comment_validate($comment_param) {
    global $DB;
    // comment itemid and comment area already validated as part of permission check (totara_plan_comment_permissions)

    // validation for comment deletion
    if (!empty($comment_param->commentid)) {
        if ($record = $DB->get_record('comments', array('id'=>$comment_param->commentid))) {
            $validareas = array('plan_overview', 'plan_course_item', 'plan_competency_item', 'plan_objective_item',
                'plan_program_item');
            if (!in_array($record->commentarea, $validareas)) {
                throw new comment_exception('invalidcommentarea');
            }
            if ($record->contextid != $comment_param->context->id) {
                throw new comment_exception('invalidcontext');
            }
            if ($record->itemid != $comment_param->itemid) {
                throw new comment_exception('invalidcommentitemid');
            }
        } else {
            throw new comment_exception('invalidcommentid');
        }
    }
    return true;
}


function totara_plan_comment_add($comment) {
    global $CFG, $DB, $USER;

    /// Get the right message data
    $commentuser = $DB->get_record('user', array('id' => $comment->userid));
    switch ($comment->commentarea) {
        case 'plan_overview':
            $plan = $DB->get_record('dp_plan', array('id' => $comment->itemid));

            $msgobj = new stdClass();
            $msgobj->plan = $plan->name;
            $msgobj->planowner = fullname($DB->get_record('user', array('id' => $plan->userid)));
            $msgobj->comment = format_text($comment->content);
            $msgobj->commentby = fullname($commentuser);
            $msgobj->commentdate = userdate($comment->timecreated);
            $contexturl = new moodle_url('/totara/plan/view.php', array('id' => $plan->id.'#comments'));
            $contexturlname = $plan->name;
            $icon = 'learningplan-newcomment';
            break;
        case 'plan_course_item':
            $sql = "SELECT ca.id, ca.planid, c.fullname
                FROM {dp_plan_course_assign} ca
                INNER JOIN {course} c ON ca.courseid = c.id
                WHERE ca.id = ?";
            $params = array($comment->itemid);
            if (!$record = $DB->get_record_sql($sql, $params)) {
                print_error('commenterror:itemnotfound', 'totara_plan');
            }
            $plan = $DB->get_record('dp_plan', array('id' => $record->planid));

            $msgobj = new stdClass();
            $msgobj->plan = $plan->name;
            $msgobj->planowner = fullname($DB->get_record('user', array('id' => $plan->userid)));
            $msgobj->component = get_string('course', 'totara_plan');
            $msgobj->componentname = $record->fullname;
            $msgobj->comment = format_text($comment->content);
            $msgobj->commentby = fullname($commentuser);
            $msgobj->commentdate = userdate($comment->timecreated);
            $contexturl = new moodle_url('/totara/plan/components/course/view.php', array('id' => $plan->id, 'itemid' => $comment->itemid.'#comments'));
            $contexturlname = $record->fullname;
            $icon = 'course-newcomment';
            break;
        case 'plan_competency_item':
            $sql = "SELECT ca.id, ca.planid, c.fullname
                FROM {dp_plan_competency_assign} ca
                INNER JOIN {comp} c ON ca.competencyid = c.id
                WHERE ca.id = ?";
            $params = array($comment->itemid);
            if (!$record = $DB->get_record_sql($sql, $params)) {
                print_error('commenterror:itemnotfound', 'totara_plan');
            }
            $plan = $DB->get_record('dp_plan', array('id' => $record->planid));

            $msgobj = new stdClass();
            $msgobj->plan = $plan->name;
            $msgobj->planowner = fullname($DB->get_record('user', array('id' => $plan->userid)));
            $msgobj->component = get_string('competency', 'totara_plan');
            $msgobj->componentname = $record->fullname;
            $msgobj->comment = format_text($comment->content);
            $msgobj->commentby = fullname($commentuser);
            $msgobj->commentdate = userdate($comment->timecreated);
            $contexturl = new moodle_url('/totara/plan/components/competency/view.php', array('id' => $plan->id, 'itemid' => $comment->itemid.'#comments'));
            $contexturlname = $record->fullname;
            $icon = 'competency-newcomment';
            break;
        case 'plan_objective_item':
            if (!$record = $DB->get_record('dp_plan_objective', array('id' => $comment->itemid))) {
                print_error('commenterror:itemnotfound', 'totara_plan');
            }
            $plan = $DB->get_record('dp_plan', array('id' => $record->planid));

            $msgobj = new stdClass();
            $msgobj->plan = $plan->name;
            $msgobj->planowner = fullname($DB->get_record('user', array('id' => $plan->userid)));
            $msgobj->component = get_string('objective', 'totara_plan');
            $msgobj->componentname = $record->fullname;
            $msgobj->comment = format_text($comment->content);
            $msgobj->commentby = fullname($commentuser);
            $msgobj->commentdate = userdate($comment->timecreated);
            $contexturl = new moodle_url('/totara/plan/components/objective/view.php', array('id' => $plan->id, 'itemid' => $comment->itemid.'#comments'));
            $contexturlname = $record->fullname;
            $icon = 'objective-newcomment';
            break;
        case 'plan_program_item':
            $sql = "SELECT pa.id, pa.planid, p.fullname
                FROM {dp_plan_program_assign} pa
                INNER JOIN {prog} p ON pa.programid = p.id
                WHERE pa.id = ?";
            $params = array($comment->itemid);
            if (!$record = $DB->get_record_sql($sql, $params)) {
                print_error('comment_error:itemnotfound', 'totara_plan');
            }
            $plan = $DB->get_record('dp_plan', array('id' => $record->planid));

            $msgobj = new stdClass();
            $msgobj->plan = $plan->name;
            $msgobj->planowner = fullname($DB->get_record('user', array('id' => $plan->userid)));
            $msgobj->component = get_string('program', 'totara_plan');
            $msgobj->componentname = $record->fullname;
            $msgobj->comment = format_text($comment->content);
            $msgobj->commentby = fullname($commentuser);
            $msgobj->commentdate = userdate($comment->timecreated);

            $contexturl = new moodle_url('/totara/plan/components/program/view.php', array('id' => $plan->id, 'itemid' => $comment->itemid.'#comments'));
            $contexturlname = $record->fullname;
            $icon = 'program-newcomment';

            break;
        default:
            print_error('commenterror:unsupportedcomment', 'totara_plan');
            break;
    }

    /// Get subscribers
    $sql = "commentarea = ? AND itemid = ? AND userid != ?";
    $params = array($comment->commentarea, $comment->itemid, $comment->userid);
    $subscribers = $DB->get_records_select('comments', $sql, $params, '', 'DISTINCT userid');
    $subscribers = !empty($subscribers) ? array_keys($subscribers) : array();
    $subscriberkeys = array();
    foreach ($subscribers as $s) {
        $subscriberkeys[$s] = $s;
    }
    $subscribers = $subscriberkeys;
    unset($subscriberkeys);

    $manager = totara_get_manager($plan->userid);
    $learner = $DB->get_record('user', array('id' => $plan->userid));
    if ($comment->userid == $learner->id) {
        // Make sure manager is added to subscriber list
        if (!empty($manager)) {
            $subscribers[$manager->id] = $manager->id;
        }
    } else if (!empty($manager) && $comment->userid == $manager->id) {
        // Make sure learner is added to subscriber list
        $subscribers[$learner->id] = $learner->id;
    } else {
        // Other commenter, so ensure learner and manager are added
        $subscribers[$learner->id] = $learner->id;
        if (!empty($manager)) {
            $subscribers[$manager->id] = $manager->id;
        }
    }

    /// Send message
    require_once($CFG->dirroot . '/totara/message/eventdata.class.php');
    require_once($CFG->dirroot . '/totara/message/messagelib.php');
    $result = true;
    $stringmanager = get_string_manager();
    foreach ($subscribers as $sid) {
        $userto = $DB->get_record('user', array('id' => $sid));
        $event = new stdClass();
        //ensure the message is actually coming from $commentuser, default to support
        $event->userfrom = ($USER->id == $commentuser->id) ? $commentuser : core_user::get_support_user();
        $event->userto = $userto;
        $event->contexturl = $contexturl;
        $event->contexturlname = $contexturlname;
        $event->icon = $icon;

        if ($comment->commentarea == 'plan_overview') {
            $subject = $stringmanager->get_string('commentmsg:planoverview', 'totara_plan', $msgobj, $userto->lang);
            $fullmsg = $stringmanager->get_string('commentmsg:planoverviewdetail', 'totara_plan', $msgobj, $userto->lang);
        } else {
            $subject = $stringmanager->get_string('commentmsg:componentitem', 'totara_plan', $msgobj, $userto->lang);
            $fullmsg = $stringmanager->get_string('commentmsg:componentitemdetail', 'totara_plan', $msgobj, $userto->lang);
        }

        $event->subject = $subject;
        $event->fullmessage = format_text_email($fullmsg, FORMAT_HTML);
        $event->fullmessagehtml = $fullmsg;
        $event->fullmessageformat = FORMAT_HTML;

        $result = $result && tm_alert_send($event);
    }

    return $result;
}


/**
 * Update an assigned competency with an evidence with a default proficiency
 *
 * @access  public
 * @param   int     $competencyid
 * @param   int     $userid
 * @param   object  $component
 * @return  bool
 */
function plan_mark_competency_default($competencyid, $userid, $component) {
    global $DB, $CFG;

    if (($DB->count_records('comp_record', array('userid' => $userid, 'competencyid' => $competencyid))) > 0) {
        return;
    }

    // Identify the "default" value for this scale value
    $sql = "
        SELECT
            scale.defaultid
        FROM
            {comp} comp
        INNER JOIN
            {comp_scale_assignments} scaleasn
         ON scaleasn.frameworkid = comp.frameworkid
        INNER JOIN
            {comp_scale} scale
         ON scale.id = scaleasn.scaleid
        WHERE
            comp.id = ?
    ";

    $records = $DB->get_records_sql($sql, array($competencyid));

    // If no value, just keep on walking
    if (empty($records)) {
        return;
    }

    $rec = array_pop($records);
    $default = $rec->defaultid;
    require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/evidence/lib.php');

    $details = new stdClass();
    $details->assessmenttype = get_string('automateddefault', 'totara_plan');
    hierarchy_add_competency_evidence($competencyid, $userid, $default, $component, $details, true, false);
}


/**
 * Set "default" evidence for all the competencies in the plan when it changes to active status
 *
 * @access  public
 * @param   object  $plan
 * @return  void
 */
function plan_activate_plan($plan) {
    $component = $plan->get_component('competency');
    $items = $component->get_assigned_items(DP_APPROVAL_APPROVED);
    foreach ($items as $compasn) {
        if (!$compasn->profscalevalueid) {
            plan_mark_competency_default($compasn->competencyid, $plan->userid, $component);
        }
    }
}

/**
 * Remove learning plan items that are associated with a particular course.
 *
 * @param int $courseid The id of the course that is being deleted
 * @return bool true if all the removals succeeded. false if there were any failures.
 */
function plan_remove_dp_course_assignments($courseid) {
    global $DB;
    return $DB->delete_records('dp_plan_course_assign', array('courseid' => $courseid));
}


/**
 * Run the plan cron
 */
function totara_plan_cron() {
    global $CFG;

    // Run cron if Learning plans are enabled.
    if (!totara_feature_disabled('learningplans')) {
        require_once($CFG->dirroot . '/totara/plan/cron.php');
        plan_cron();
    }
}


/**
 * Decide if the Record of Learning tab should be shown
 *
 * @param  object   $component   The component to check
 * @return bool     true if the component is enabled and has assigned items
 */
function display_rol_tab_for_component($component) {
    $items = count($component->get_assigned_items()) > 0;

    $enabled = $component->get_setting('enabled');

    return $enabled && $items;
}

/**
 * Prints an error if Learning Plan is not enabled
 *
 */
function check_learningplan_enabled() {
    if (totara_feature_disabled('learningplans')) {
        print_error('learningplansdisabled', 'totara_plan');
    }
}
