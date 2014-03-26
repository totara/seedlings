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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage plan
 */

/**
 * Functions for creating/processing the settings form for a development plan
 */

/**
 * Build settings form for configurating this component
 *
 * @access  public
 * @param   object  $mform  Moodle form object
 * @param   array $customdata mform customdata
 * @return  void
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

function development_plan_build_settings_form(&$mform, $customdata) {
    global $DP_AVAILABLE_ROLES, $DB;

    //Settings
    $mform->addElement('header', 'plansettings', get_string('plansettings', 'totara_plan'));
    $mform->addHelpButton('plansettings', 'advancedsettingsplansettings', 'totara_plan', '', true);

    if ($templatesettings = $DB->get_record('dp_plan_settings', array('templateid' => $customdata['id']))) {
        $defaultmanualcomplete = $templatesettings->manualcomplete;
        $defaultautobyitems = $templatesettings->autobyitems;
        $defaultautobyplandate = $templatesettings->autobyplandate;
    } else {
        $defaultmanualcomplete = 1;
        $defaultautobyitems = null;
        $defaultautobyplandate = null;
    }

    $plancompletiongroup = array();
    $plancompletiongroup[] =& $mform->createElement('advcheckbox', 'manualcomplete', null, get_string('manualcomplete', 'totara_plan'));
    $plancompletiongroup[] =& $mform->createElement('advcheckbox', 'autobyitems', null, get_string('autobyitems', 'totara_plan'));
    $plancompletiongroup[] =& $mform->createElement('advcheckbox', 'autobyplandate', null, get_string('autobyplandate', 'totara_plan'));

    $mform->addGroup($plancompletiongroup, 'plancomplete', get_string('planmarkedcomplete', 'totara_plan'), array(html_writer::empty_tag('br')), false);
    $mform->setDefault('manualcomplete', $defaultmanualcomplete);
    $mform->setDefault('autobyitems', $defaultautobyitems);
    $mform->setDefault('autobyplandate', $defaultautobyplandate);


    //Permissions
    $mform->addElement('header', 'planpermissions', get_string('planpermissions', 'totara_plan'));
    $mform->addHelpButton('planpermissions', 'advancedsettingsplanpermissions', 'totara_plan', '', true);

    dp_add_permissions_table_headings($mform);
    foreach (development_plan::$permissions as $action => $requestable) {
        dp_add_permissions_table_row($mform, $action, get_string($action, 'totara_plan'), $requestable);
    }
    foreach (development_plan::$permissions as $action => $requestable) {
        foreach ($DP_AVAILABLE_ROLES as $role) {
            $sql = "SELECT value FROM {dp_permissions} WHERE role = ? AND component = ? AND action = ? AND templateid = ?";
            $params = array($role, 'plan', $action, $customdata['id']);
            $defaultvalue = $DB->get_field_sql($sql, $params);
            $mform->setDefault($action.$role, $defaultvalue);
        }
    }
    $mform->addElement('html', html_writer::end_tag('table') . html_writer::end_tag('div'));
}


/**
 * Process settings form for configurating this component
 *
 * @access  public
 * @param   object  $fromform   Submitted form's content
 * @param   integer $id         Template ID
 * @return  void
 */
function development_plan_process_settings_form($fromform, $id) {
    global $CFG, $DP_AVAILABLE_ROLES, $DB;

    $currenturl = new moodle_url('/totara/plan/template/advancedworkflow.php', array('id' => $id, 'component' => 'plan'));
        $transaction = $DB->start_delegated_transaction();

        // process plan settings here
        $currentworkflow = $DB->get_field('dp_template', 'workflow', array('id' => $id));
        if ($currentworkflow != 'custom') {
            $template_update = new stdClass();
            $template_update->id = $id;
            $template_update->workflow = 'custom';
            $DB->update_record('dp_template', $template_update);
        }
        $todb = new stdClass();
        $todb->templateid = $id;
        $todb->manualcomplete = $fromform->manualcomplete;
        $todb->autobyitems = $fromform->autobyitems;
        $todb->autobyplandate = $fromform->autobyplandate;
        if ($plansettings = $DB->get_record('dp_plan_settings', array('templateid' => $id))) {
            //update
            $todb->id = $plansettings->id;
            $DB->update_record('dp_plan_settings', $todb);
        } else {
            //insert
            $DB->insert_record('dp_plan_settings', $todb);
        }
        foreach (development_plan::$permissions as $action => $requestable) {
            foreach ($DP_AVAILABLE_ROLES as $role) {
                $permission_todb = new stdClass();
                $permission_todb->templateid = $id;
                $permission_todb->component = 'plan';
                $permission_todb->action = $action;
                $permission_todb->role = $role;
                $temp = $action . $role;
                $permission_todb->value = $fromform->$temp;
                $sql = "SELECT * FROM {dp_permissions} WHERE templateid = ? AND component = ? AND action = ? AND role = ?";
                $params = array($id, 'plan', $action, $role);
                if ($permission_setting = $DB->get_record_sql($sql, $params, IGNORE_MISSING)) {
                    //update
                    $permission_todb->id = $permission_setting->id;
                    $DB->update_record('dp_permissions', $permission_todb);
                } else {
                    //insert
                    $DB->insert_record('dp_permissions', $permission_todb);
                }
            }
        }
        $transaction->allow_commit();
    add_to_log(SITEID, 'plan', 'changed workflow', "template/workflow.php?id={$id}", "Template ID:{$id}");
    totara_set_notification(get_string('update_plan_settings', 'totara_plan'), $currenturl, array('class' => 'notifysuccess'));
}
