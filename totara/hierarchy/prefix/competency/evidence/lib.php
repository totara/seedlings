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
 * @subpackage hierarchy
 */
require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/evidence/evidence.php');
require_once($CFG->dirroot.'/blocks/totara_stats/locallib.php');

/**
 * Determine whether the current logged-in user is able to rate a competency proficiency for the given competency
 *
 * If a null competencyid is given, just does basic permission checks to ensure they
 * can modify evidence in this plan. Without checking individual competencies.
 *
 * @access  public
 * @param   object      $plan       Development plan object
 * @param   object      $component  Full plan component class instance
 * @param   int         $userid     Plan owner (checked)
 * @param   int         $competencyid
 * @return  true|array  True if you can add it, and if false an array where the first element is a lang
 *                      string name and the second element is the lang string file
 */
function hierarchy_can_add_competency_evidence($plan, $component, $userid, $competencyid) {
    global $DB;

    $systemcontext = context_system::instance();
    if (!has_capability('totara/plan:accessanyplan', $systemcontext) && ($plan->get_setting('view') < DP_PERMISSION_ALLOW)) {
        return array('error:nopermissions', 'totara_plan');
    }

    if ($component->get_setting('setproficiency') != DP_PERMISSION_ALLOW) {
        return array('error:competencystatuspermission', 'totara_plan');
    }

    // Validate whether the plan belongs to the specified user
    if (!$plan->userid == $userid) {
        return array('error:usernotfound','totara_plan');
    }

    //no competency specified, they generally have permission so that's okay
    if (is_null($competencyid)) {
        return true;
    }

    // Validate whether this competency is even in the plan
    $compassign = $DB->get_record('dp_plan_competency_assign', array('planid' => $plan->id, 'competencyid' => $competencyid), 'id, approved');
    if (!$compassign) {
        return array('error:competencynotfound','totara_plan');
    }

    // Check whether the plan's competencies can still be updated
    if (!$component->can_update_competency_evidence($compassign)) {
        return array('error:cannotupdatecompetencies','totara_plan');
    }

    return true;
}


/**
 * Add competency evidence records
 *
 * @access  public
 * @param   int         $competencyid
 * @param   int         $userid
 * @param   int         $prof
 * @param   object      $component      Full plan component class instance
 * @param   object      $details        Object containing the (all optional) params positionid, organisationid, assessorid, assessorname, assessmenttype, manual
 * @param   true|int    $reaggregate (optional) time() if set to true, otherwise timestamp supplied
 * @param   bool        $notify (optional)
 * @return  int
 */
function hierarchy_add_competency_evidence($competencyid, $userid, $prof, $component, $details, $reaggregate = true, $notify = true) {
    global $DB;

    $todb = new competency_evidence(
        array(
            'competencyid'  => $competencyid,
            'userid'        => $userid
        )
    );

    // Cleanup data
    if (isset($details->positionid)) {
        $todb->positionid = $details->positionid;
    }
    if (isset($details->organisationid)) {
        $todb->organisationid = $details->organisationid;
    }
    if (isset($details->assessorid)) {
        $todb->assessorid = $details->assessorid;
    }
    if (isset($details->assessorname)) {
        $todb->assessorname = $details->assessorname;
    }
    if (isset($details->assessmenttype)) {
        $todb->assessmenttype = $details->assessmenttype;
    }

    if (!empty($details->manual)) {
        $todb->manual = 1;
    } else {
        $todb->manual = 0;
    }
    if ($reaggregate === true) {
        $todb->reaggregate = time();
    } else {
        $todb->reaggregate = (int) $reaggregate;
    }

    // Update the proficiency
    $todb->update_proficiency($prof);

    // update stats block
    $currentuser = $userid;
    $event = STATS_EVENT_COMP_ACHIEVED;
    $data2 = $competencyid;
    $time = $todb->reaggregate;
    $count = $DB->count_records('block_totara_stats', array('userid' => $currentuser, 'eventtype' => $event, 'data2' => $data2));
    $isproficient = $DB->get_field('comp_scale_values', 'proficient', array('id' => $prof));

    if ($notify) {
        // check the proficiency is set to "proficient" and check for duplicate data
        if ($isproficient && $count == 0) {
            totara_stats_add_event($time, $currentuser, $event, '', $data2);
            //Send Alert
            $alert_detail = new stdClass();
            $alert_detail->itemname = $DB->get_field('comp', 'fullname', array('id' => $data2));
            $alert_detail->text = get_string('competencycompleted', 'totara_plan');
            $component->send_component_complete_alert($alert_detail);
        }
        // check record exists for removal and is set to "not proficient"
        else if ($isproficient == 0 && $count > 0) {
            totara_stats_remove_event($currentuser, $event, $data2);
        }
    }

    return $todb->id;
}

/**
 * Delete evidence records associated with a specified course
 *
 * @param $courseid ID of the course that is no longer required
 * @return boolean True if all delete operations succeeded, false otherwise
 */
function hierarchy_delete_competency_evidence($courseid) {
    global $DB;

    if (empty($courseid)) {
        return false;
    }

    // Remove all competency evidence items evidence
    $like_sql = $DB->sql_like('itemtype', '?');
    $like_param = 'course%';
    $DB->delete_records_select("comp_criteria_record",
        "itemid IN (SELECT id FROM {comp_criteria} WHERE $like_sql AND iteminstance = ?)", array($like_param, $courseid));

    $DB->delete_records_select("comp_criteria",
        "(itemtype = 'coursecompletion' OR itemtype='coursegrade') AND iteminstance = ?", array($courseid));

    return true;
}
