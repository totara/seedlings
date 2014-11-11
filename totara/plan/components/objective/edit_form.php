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

/**
 * The form for editing a plan's objective
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once("{$CFG->libdir}/formslib.php");

class plan_objective_edit_form extends moodleform {

    /**
     * Requires the following $_customdata to be passed in to the constructor:
     * plan, objective, objectiveid (optional)
     *
     * @global object $USER
     * @global object $DB
     */
    function definition() {
        global $USER, $DB, $TEXTAREA_OPTIONS;

        $mform =& $this->_form;

        // Determine permissions from objective
        $plan = $this->_customdata['plan'];
        $objective = $this->_customdata['objective'];
        // Figure out permissions & settings
        $duedatemode = $objective->get_setting('duedatemode');
        $duedateallow = in_array( $objective->get_setting('setduedate'), array(DP_PERMISSION_ALLOW, DP_PERMISSION_APPROVE));
        $prioritymode = $objective->get_setting('prioritymode');
        $priorityallow = in_array( $objective->get_setting('setpriority'), array(DP_PERMISSION_ALLOW, DP_PERMISSION_APPROVE));
        $profallow = in_array( $objective->get_setting('setproficiency'), array(DP_PERMISSION_ALLOW, DP_PERMISSION_APPROVE));

        // Generate list of priorities
        if ($prioritymode > DP_PRIORITY_NONE) {

            $scaleid = $objective->get_setting('priorityscale');
            if ($scaleid) {
                $priorityvalues = $DB->get_records('dp_priority_scale_value', array('priorityscaleid' => $scaleid), 'sortorder', 'id,name,sortorder');
                if ($prioritymode == DP_PRIORITY_OPTIONAL) {
                    $select[] = get_string('none', 'totara_plan');
                }
                foreach ($priorityvalues as $pv) {
                    $select[$pv->id] = $pv->name;
                }
                $prioritylist = $select;
                $prioritydefaultid = $DB->get_field('dp_priority_scale', 'defaultid', array('id' => $scaleid));
            } else {
                $prioritylist = array( get_string('none', 'totara_plan') );
            }
        }

        // Generate list of proficiencies
        $objscaleid = $objective->get_setting('objectivescale');
        $defaultobjscalevalueid = $DB->get_field('dp_objective_scale', 'defaultid', array('id' => $objscaleid));

        if ($objscaleid) {
            $vals = $DB->get_records('dp_objective_scale_value', array('objscaleid' => $objscaleid), 'sortorder', 'id, name, sortorder');
            foreach ($vals as $v) {
                $proflist[$v->id] = $v->name;
            }
        }

        // Add some hidden fields
        if (isset($this->_customdata['objectiveid'])) {
            $mform->addElement('hidden', 'itemid', $this->_customdata['objectiveid']);
            $mform->setType('itemid', PARAM_INT);
        }
        $mform->addElement('hidden', 'userid', $USER->id);
        $mform->setType('userid', PARAM_INT);
        $mform->addElement('hidden', 'id', $plan->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'fullname', get_string('objectivetitle', 'totara_plan'));
        $mform->setType('fullname', PARAM_TEXT);
        $mform->addRule('fullname', get_string('err_required', 'form'), 'required', '', 'client', false, false);
        $mform->addElement('editor', 'description_editor', get_string('objectivedescription', 'totara_plan'), null, $TEXTAREA_OPTIONS);
        $mform->setType('description_editor', PARAM_CLEANHTML);

        // Due dates
        if ($duedateallow && ($duedatemode == DP_DUEDATES_OPTIONAL || $duedatemode == DP_DUEDATES_REQUIRED)) {
            $mform->addElement('date_selector', 'duedate', get_string('duedate', 'totara_plan'));
            $mform->setType('duedate', PARAM_TEXT);

            // Whether to make the field optional
            if ($duedatemode == DP_DUEDATES_REQUIRED) {
                $mform->addRule('duedate', null, 'required');
            }
        }

        // Priorities
        if ($prioritymode == DP_PRIORITY_OPTIONAL || $prioritymode == DP_PRIORITY_REQUIRED) {
            $mform->addElement('select', 'priority', get_string('priority', 'totara_plan'), $prioritylist);
            $mform->setDefault('priority', $prioritydefaultid);
            if ($prioritymode == DP_PRIORITY_REQUIRED) {
                $mform->addRule('priority', get_string('err_required', 'form'), 'required', '', 'client', false, false);
            }
            if (!$priorityallow) {
                $mform->freeze(array('priority'));
            }
        }

        // Proficiency
        $mform->addElement('select', 'scalevalueid', get_string('status', 'totara_plan'), $proflist);
        $mform->addRule('scalevalueid', get_string('err_required', 'form'), 'required', '', 'client', false, false);
        $mform->setDefault('scalevalueid', $defaultobjscalevalueid);

        if (!$profallow) {
            $mform->freeze(array('scalevalueid'));
        }

        $this->add_action_buttons(true, empty($this->_customdata['objectiveid']) ?
            get_string('addobjective', 'totara_plan') : get_string('updateobjective', 'totara_plan'));
    }
}
