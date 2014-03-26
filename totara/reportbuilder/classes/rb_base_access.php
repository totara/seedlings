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
 * @subpackage reportbuilder
 */

/**
 * Abstract base access class to be extended to create report builder access restrictions.
 *
 * Defines the properties and methods required by access restrictions
 *
 * This file also contains some core access restrictions
 * that can be used by any report builder source
 */
abstract class rb_base_access {

    public $foruser;

    /*
     * @param integer $foruser User ID to determine access for
     *                         Typically this will be $USER->id, except
     *                         in the case of scheduled reports run by cron
     */
    function __construct($foruser=null) {
        $this->foruser = $foruser;
    }

    /*
     * All sub classes must define the following functions
     */
    abstract function access_restriction($reportid);
    abstract function form_template(&$mform, $reportid);
    abstract function form_process($reportid, $fromform);
    abstract function get_accessible_reports();

} // end of rb_base_access class


/**
 * Role based access restriction
 *
 * Limit access to reports by user role (either in system context or any context)
 */
class rb_role_access extends rb_base_access {

    /**
    * Get list of reports this user is allowed to access by this restriction class
    * @return array of permitted report ids
    */
    function get_accessible_reports(){
        global $DB, $CFG;

        // remove the rb_ from class
        $type = substr(get_class($this), 3);
        $userid = $this->foruser;
        $anycontextcheck = false;
        $allowedreports = array();

        $sql =  "SELECT rb.id AS reportid, rbs.value AS activeroles, rbs2.value AS context
                   FROM {report_builder} rb
        LEFT OUTER JOIN {report_builder_settings} rbs
                     ON rb.id = rbs.reportid
                    AND rbs.type = ?
                    AND rbs.name = ?
        LEFT OUTER JOIN {report_builder_settings} rbs2
                     ON (rbs.reportid = rbs2.reportid
                    AND rbs2.type = ?
                    AND rbs2.name = ?)
                  WHERE rb.embedded = ?";

        $reports = $DB->get_records_sql($sql, array($type, 'activeroles', $type, 'context', 0));

        if (count($reports) > 0) {
            // site admins no longer have records in role_assignments to check: assume access to everything
            if (is_siteadmin($userid)) {
                foreach ($reports as $rpt) {
                    $allowedreports[] = $rpt->reportid;
                }
                return $allowedreports;
            } else {
                //not a siteadmin: pass through recordset, to see if we need to get the 'any context' array for any report
                foreach ($reports as $rpt) {
                    if (isset($rpt->context) && $rpt->context == 'any') {
                        $anycontextcheck = true;
                        break;
                    }
                }
            }
            //get default site context array
            $sql = "SELECT DISTINCT ra.roleid
                      FROM {role_assignments} ra
                 LEFT JOIN {context} c
                        ON ra.contextid = c.id
                     WHERE ra.userid = ?
                       AND c.contextlevel = ?";
            $siteuserroles = $DB->get_fieldset_sql($sql, array($userid, CONTEXT_SYSTEM));

            // Add defaultuserrole if necessary
            if (!in_array((int)$CFG->defaultuserroleid, $siteuserroles)) {
                $siteuserroles[] = $CFG->defaultuserroleid;
            }

            //only get any context roles if actually needed
            if ($anycontextcheck) {
                $sql = "SELECT DISTINCT roleid
                          FROM {role_assignments}
                         WHERE userid = ?";
                $anyuserroles = $DB->get_fieldset_sql($sql, array($userid));
            }
            //now loop through our reports again checking role permissions
            foreach ($reports as $rpt) {
                $allowed_roles = explode('|', $rpt->activeroles);
                $roles_to_compare = (isset($rpt->context) && $rpt->context == 'any') ? $anyuserroles : $siteuserroles;
                $matched_roles = array_intersect($allowed_roles, $roles_to_compare);
                if (!empty($matched_roles)) {
                    $allowedreports[] = $rpt->reportid;
                }
            }
        }
        return $allowedreports;
    }

    /**
     * Check if the user has rights for a particular access restriction
     *
     * @param integer $reportid ID of the report to check access for
     *
     * @return boolean True if user has access rights
     */
    function access_restriction($reportid) {
        global $DB;
        // return true if user has rights to access by role

        if (is_siteadmin($this->foruser)) {
            // site admins are boss - they always have access ;)
            return true;
        }

        // remove the rb_ from class
        $type = substr(get_class($this), 3);
        $allowedroles = explode('|',
            reportbuilder::get_setting($reportid, $type, 'activeroles'));
        $contextsetting = reportbuilder::get_setting($reportid, $type, 'context');
        $userid = $this->foruser;

        if ($contextsetting == 'any') {
            // find roles the user has in any context
            $userroles = $DB->get_records_sql('SELECT DISTINCT roleid
                FROM {role_assignments}
                WHERE userid = ?', array($userid));
        } else {
            // only find roles the user has in the site context
            // default to this if not set
            $context = context_system::instance();
            $userroles = array();
            $data = get_user_roles($context, $userid, false);
            foreach ($data as $item) {
                $userroles[] = $item->roleid;
            }
        }

        // see if user has any allowed roles
        return (count(array_intersect($allowedroles, $userroles)) != 0);
    }


    /**
     * Adds form elements required for this access restriction's settings page
     *
     * @param object &$mform Moodle form object to modify (passed by reference)
     * @param integer $reportid ID of the report being adjusted
     */
    function form_template(&$mform, $reportid) {
        global $DB;

        // remove the rb_ from class
        $type = substr(get_class($this), 3);
        $enable = reportbuilder::get_setting($reportid, $type, 'enable');
        $activeroles = explode('|',
            reportbuilder::get_setting($reportid, $type, 'activeroles'));
        $context = reportbuilder::get_setting($reportid, $type, 'context');

        // generate the check boxes for the access form
        $mform->addElement('header', 'accessbyroles', get_string('accessbyrole', 'totara_reportbuilder'));

        //TODO replace with checkbox once there is more than one option
        $mform->addElement('hidden', 'role_enable', 1);
        $mform->setType('role_enable', PARAM_INT);

        $systemcontext = context_system::instance();
        $roles = role_fix_names(get_all_roles(), $systemcontext);
        if (!empty($roles)) {
            $contextoptions = array('site' => get_string('systemcontext', 'totara_reportbuilder'), 'any' => get_string('anycontext', 'totara_reportbuilder'));

            // set context for role-based access
            $mform->addElement('select', 'role_context', get_string('context', 'totara_reportbuilder'), $contextoptions);
            $mform->setDefault('role_context', $context);
            $mform->disabledIf('role_context', 'accessenabled', 'eq', 0);
            $mform->addHelpButton('role_context', 'reportbuildercontext', 'totara_reportbuilder');

            $rolesgroup = array();
            foreach ($roles as $role) {
                $rolesgroup[] =& $mform->createElement('advcheckbox', "role_activeroles[{$role->id}]", '', $role->localname, null, array(0, 1));
                if (in_array($role->id, $activeroles)) {
                    $mform->setDefault("role_activeroles[{$role->id}]", 1);
                }
            }
            $mform->addGroup($rolesgroup, 'roles', get_string('roleswithaccess', 'totara_reportbuilder'), html_writer::empty_tag('br'), false);
            $mform->disabledIf('roles', 'accessenabled', 'eq', 0);
            $mform->addHelpButton('roles', 'reportbuilderrolesaccess', 'totara_reportbuilder');
        } else {
            $mform->addElement('html', html_writer::tag('p', get_string('error:norolesfound', 'totara_reportbuilder')));
        }

    }

    /**
     * Processes the form elements created by {@link form_template()}
     *
     * @param integer $reportid ID of the report to process
     * @param object $fromform Moodle form data received via form submission
     *
     * @return boolean True if form was successfully processed
     */
    function form_process($reportid, $fromform) {
        // save the results of submitting the access form to
        // report_builder_settings

        // remove the rb_ from class
        $type = substr(get_class($this), 3);

        // enable checkbox option
        // TODO not yet used as there is only one access criteria so far
        $enable = (isset($fromform->role_enable) &&
            $fromform->role_enable) ? 1 : 0;
        reportbuilder::update_setting($reportid, $type, 'enable', $enable);

        if (isset($fromform->role_context)) {
            $context = $fromform->role_context;
            reportbuilder::update_setting($reportid, $type, 'context', $context);
        }

        $activeroles = array();
        if (isset($fromform->role_activeroles)) {
            foreach ($fromform->role_activeroles as $roleid => $setting) {
                if ($setting == 1) {
                    $activeroles[] = $roleid;
                }
            }
            // implode into string and update setting
            reportbuilder::update_setting($reportid, $type,
                'activeroles', implode('|', $activeroles));

        }

        return true;
    }

} // end of rb_role_access
