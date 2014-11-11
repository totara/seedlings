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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package totara
 * @subpackage cohort
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}
require_once($CFG->dirroot . '/totara/message/messagelib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/totara/program/program_assignments.class.php');

define('COHORT_ALERT_NONE', 0);
define('COHORT_ALERT_AFFECTED', 1);
define('COHORT_ALERT_ALL', 2);

define('COHORT_COL_STATUS_ACTIVE', 0);
define('COHORT_COL_STATUS_DRAFT_UNCHANGED', 10);
define('COHORT_COL_STATUS_DRAFT_CHANGED', 20);
define('COHORT_COL_STATUS_OBSOLETE', 30);

define('COHORT_BROKEN_RULE_NONE', 0);
define('COHORT_BROKEN_RULE_NOT_NOTIFIED', 1);
define('COHORT_BROKEN_RULE_NOTIFIED', 2);

define('COHORT_MEMBER_SELECTOR_MAX_ROWS', 1000);

define('COHORT_OPERATOR_TYPE_COHORT', 25);
define('COHORT_OPERATOR_TYPE_RULESET', 50);


global $COHORT_ALERT;
$COHORT_ALERT = array(
    COHORT_ALERT_NONE => get_string('alertmembersnone', 'totara_cohort'),
    COHORT_ALERT_AFFECTED => get_string('alertmembersaffected', 'totara_cohort'),
    COHORT_ALERT_ALL => get_string('alertmembersall', 'totara_cohort'),
);

define('COHORT_ASSN_ITEMTYPE_COURSE', 50);
define('COHORT_ASSN_ITEMTYPE_PROGRAM', 45);
define('COHORT_ASSN_ITEMTYPE_CERTIF', 55);
global $COHORT_ASSN_ITEMTYPES;
$COHORT_ASSN_ITEMTYPES = array(
    COHORT_ASSN_ITEMTYPE_COURSE => 'course',
    COHORT_ASSN_ITEMTYPE_PROGRAM => 'program',
    COHORT_ASSN_ITEMTYPE_CERTIF => 'certification',
);

// This should be extended when adding other tabs.
define ('COHORT_ASSN_VALUE_VISIBLE', 10);
define ('COHORT_ASSN_VALUE_ENROLLED', 30);
global $COHORT_ASSN_VALUES;
$COHORT_ASSN_VALUES = array(
    COHORT_ASSN_VALUE_VISIBLE => 'visible',
    COHORT_ASSN_VALUE_ENROLLED => 'enrolled'
);

// Visibility constants.
define('COHORT_VISIBLE_ENROLLED', 0);
define('COHORT_VISIBLE_AUDIENCE', 1);
define('COHORT_VISIBLE_ALL', 2);
define('COHORT_VISIBLE_NOUSERS', 3);

global $COHORT_VISIBILITY;
$COHORT_VISIBILITY = array(
    COHORT_VISIBLE_NOUSERS => get_string('visiblenousers', 'totara_cohort'),
    COHORT_VISIBLE_ENROLLED => get_string('visibleenrolled', 'totara_cohort'),
    COHORT_VISIBLE_AUDIENCE => get_string('visibleaudience', 'totara_cohort'),
    COHORT_VISIBLE_ALL => get_string('visibleall', 'totara_cohort'),
);

/**
 * This function is called automatically when the totara cohort module is installed.
 *
 * @return bool Success
 */
function totara_cohort_install() {
    global $CFG, $DB;
    return true;
}

/**
 * Run the totara_cohort cron
 */
function totara_cohort_cron() {
    global $CFG;
    require_once($CFG->dirroot . '/totara/cohort/cron.php');
    tcohort_cron();
}

/**
 * This function updates the audience visibility of a learning component.
 *
 * @param int $type Type of the learning component we want to update (course, program, certification)
 * @param int $instanceid Learning component instance ID
 * @param int $visiblevalue The audience visibility value
 * @return bool $result Result of the operation
 */
function totara_cohort_update_audience_visibility($type, $instanceid, $visiblevalue) {
    global $DB, $CFG, $COHORT_VISIBILITY;

    $result = false;

    // Capabilities to update programs or certifications.
    $progcap = 'totara/program:configuredetails';
    $certcap = 'totara/certification:configuredetails';

    // Do nothing if audience visibility is off.
    if (empty($CFG->audiencevisibility)) {
        return $result;
    }

    // Check $visiblevalue is in the range.
    if (!isset($COHORT_VISIBILITY[$visiblevalue])) {
        return $result;
    }

    if ($type == COHORT_ASSN_ITEMTYPE_COURSE &&
        has_capability('moodle/course:update', context_course::instance($instanceid))) {
        // Update the database record.
        $result = $DB->update_record('course', array('id' => $instanceid, 'audiencevisible' => $visiblevalue));
    } else if (($type == COHORT_ASSN_ITEMTYPE_PROGRAM && has_capability($progcap, context_program::instance($instanceid))) ||
        ($type == COHORT_ASSN_ITEMTYPE_CERTIF  && has_capability($certcap, context_program::instance($instanceid)))) {
            // Update the database record.
            $result = $DB->update_record('prog', array('id' => $instanceid, 'audiencevisible' => $visiblevalue));
    }

    return $result;
}

/**
 * Add or update a cohort association
 * @param $cohortid
 * @param $instanceid course/program id
 * @param $instancetype COHORT_ASSN_ITEMTYPE_COURSE, COHORT_ASSN_ITEMTYPE_PROGRAM, etc.
 * @param $value COHORT_ASSN_VALUE_ENROLLED, COHORT_ASSN_VALUE_VISIBLE
 * @return boolean
 */
function totara_cohort_add_association($cohortid, $instanceid, $instancetype, $value=COHORT_ASSN_VALUE_ENROLLED) {
    global $CFG, $DB, $USER, $COHORT_ASSN_ITEMTYPES, $COHORT_ASSN_VALUES;

    if (!array_key_exists($instancetype, $COHORT_ASSN_ITEMTYPES)) {
        return false;
    }

    if (!array_key_exists($value, $COHORT_ASSN_VALUES)) {
        return false;
    }

    if ($value == COHORT_ASSN_VALUE_ENROLLED) {
        switch ($instancetype) {
            case COHORT_ASSN_ITEMTYPE_COURSE:
                $courseassociations = array_map(
                    function($assoc) {
                        return $assoc->instanceid;
                    },
                    totara_cohort_get_associations($cohortid, COHORT_ASSN_ITEMTYPE_COURSE)
                );
                if (in_array($instanceid, $courseassociations)) {
                    return true;
                }
                if (!$course = $DB->get_record('course', array('id' => $instanceid))) {
                    return false;
                }
                // Add a cohort enrol instance to the course
                $enrolplugin = enrol_get_plugin('cohort');
                $assid = $enrolplugin->add_instance($course, array('customint1' => $cohortid, 'roleid' => $CFG->learnerroleid));
                return $assid;
                break;
            case COHORT_ASSN_ITEMTYPE_CERTIF:
                $itemtype = COHORT_ASSN_ITEMTYPE_CERTIF;
            case COHORT_ASSN_ITEMTYPE_PROGRAM:
                $itemtype = isset($itemtype) ? $itemtype : COHORT_ASSN_ITEMTYPE_CERTIF;
                $progassociations = array_map(
                    function($assoc) {
                        return $assoc->instanceid;
                    },
                    totara_cohort_get_associations($cohortid, $itemtype)
                );
                if (in_array($instanceid, $progassociations)) {
                    return true;
                }
                if (!$program = $DB->get_record('prog', array('id' => $instanceid))) {
                    return false;
                }
                // Add program assignment
                $todb = new stdClass;
                $todb->programid = $instanceid;
                $todb->assignmenttype = ASSIGNTYPE_COHORT;
                $todb->assignmenttypeid = $cohortid;
                $assid = $DB->insert_record('prog_assignment', $todb);
                return $assid;
                break;
            default:
                break;
        }
    } else { // assigning visible learning
        if (!$DB->record_exists_sql('SELECT 1 FROM {cohort_visibility} WHERE cohortid = ? AND instanceid = ? AND '
                . 'instancetype = ? ', array($cohortid, $instanceid, $instancetype))) {
            $todb = new stdClass();
            $todb->cohortid = $cohortid;
            $todb->instanceid = $instanceid;
            $todb->instancetype = $instancetype;
            $todb->timemodified = time();
            $todb->timecreated = $todb->timemodified;
            $todb->usermodified = $USER->id;
            return $DB->insert_record('cohort_visibility', $todb);
        }
    }
    return true;
}

/**
 * Delete a cohort association
 * @param $cohortid
 * @param $assid
 * @param $asstype
 * @param $value COHORT_ASSN_VALUE_ENROLLED, COHORT_ASSN_VALUE_VISIBLE
 */
function totara_cohort_delete_association($cohortid, $assid, $instancetype, $value=COHORT_ASSN_VALUE_ENROLLED) {
    global $DB, $COHORT_ASSN_ITEMTYPES;

    if (!array_key_exists($instancetype, $COHORT_ASSN_ITEMTYPES)) {
        return false;
    }

    if ($value == COHORT_ASSN_VALUE_VISIBLE) {
        // directly delete from cohort_visibility
        return $DB->delete_records('cohort_visibility', array('id' => $assid));
    }

    switch ($instancetype) {
        case COHORT_ASSN_ITEMTYPE_COURSE:
            // Get cohort enrol plugin instance
            $enrolinstance = $DB->get_record('enrol', array('id' => $assid));
            if (!empty($enrolinstance)) {
                $transaction = $DB->start_delegated_transaction();

                $enrolplugin = enrol_get_plugin('cohort');
                $enrolplugin->delete_instance($enrolinstance);  // this also unenrols peeps - no need to sync

                $transaction->allow_commit();
            }
            break;
        case COHORT_ASSN_ITEMTYPE_PROGRAM:
        case COHORT_ASSN_ITEMTYPE_CERTIF:
            if ($progassid = $DB->get_field('prog_assignment', 'id', array('id' => $assid))) {
                $transaction = $DB->start_delegated_transaction();

                prog_exceptions_manager::delete_exceptions_by_assignment($progassid);
                $DB->delete_records('prog_assignment', array('id' => $progassid));

                $transaction->allow_commit();
            }
            break;
        default:
            break;
    }

    return true;
}

/**
 * Returns enrolled learning items of a cohort
 *
 * @param int $cohortid
 * @param int $instancetype e.g COHORT_ASSN_ITEMTYPE_COURSE, COHORT_ASSN_ITEMTYPE_PROGRAM
 * @param int $value eg. COHORT_ASSN_VALUE_VISIBLE, COHORT_ASSN_VALUE_ENROLLED
 * @return array of db objects
 */
function totara_cohort_get_associations($cohortid, $instancetype=null, $value=null) {
    global $DB;

    switch ($instancetype) {
        case COHORT_ASSN_ITEMTYPE_COURSE:
            if ($value == COHORT_ASSN_VALUE_VISIBLE) {
                $sql = "SELECT cas.id, cas.instanceid, c.fullname, 'course' AS type
                    FROM {cohort_visibility} cas
                    JOIN {course} c ON cas.instanceid = c.id AND cas.instancetype = ?
                    WHERE cas.cohortid = ?";
                $sqlparams = array(COHORT_ASSN_ITEMTYPE_COURSE, $cohortid);
            } else {
                // course associations
                $sql = "SELECT e.id, c.id AS instanceid, c.fullname, 'course' AS type
                    FROM {enrol} e
                    JOIN {course} c ON e.courseid = c.id
                    WHERE enrol = 'cohort'
                    AND customint1 = ?";
                $sqlparams = array($cohortid);
            }
            break;
        case COHORT_ASSN_ITEMTYPE_PROGRAM:
            if ($value == COHORT_ASSN_VALUE_VISIBLE) {
                $sql = "SELECT cas.id, cas.instanceid, p.fullname, 'program' AS type
                    FROM {cohort_visibility} cas
                    JOIN {prog} p ON cas.instanceid = p.id AND cas.instancetype = ?
                    WHERE cas.cohortid = ?";
                $sqlparams = array(COHORT_ASSN_ITEMTYPE_PROGRAM, $cohortid);
            } else {
                $sql = "SELECT pa.id, p.id AS instanceid, p.fullname, 'program' AS type
                    FROM {prog_assignment} pa
                    JOIN {prog} p ON pa.programid = p.id
                    WHERE pa.assignmenttype = ?
                    AND pa.assignmenttypeid = ?";
                $sqlparams = array(ASSIGNTYPE_COHORT, $cohortid);
            }
            break;
        case COHORT_ASSN_ITEMTYPE_CERTIF:
            if ($value == COHORT_ASSN_VALUE_VISIBLE) {
                $sql = "SELECT cas.id, cas.instanceid, p.fullname, 'program' AS type
                    FROM {cohort_visibility} cas
                    JOIN {prog} p ON cas.instanceid = p.id AND cas.instancetype = ?
                    WHERE cas.cohortid = ?";
                $sqlparams = array(COHORT_ASSN_ITEMTYPE_CERTIF, $cohortid);
            } else {
                $sql = "SELECT pa.id, p.id AS instanceid, p.fullname, 'program' AS type
                    FROM {prog_assignment} pa
                    JOIN {prog} p ON pa.programid = p.id
                    WHERE pa.assignmenttype = ?
                    AND pa.assignmenttypeid = ?";
                $sqlparams = array(ASSIGNTYPE_COHORT, $cohortid);
            }
            break;
        default:
            // return all associations. prefix ids to ensure uniqueness
            if ($value == COHORT_ASSN_VALUE_VISIBLE) {
                $sql = "SELECT cas.id, cas.instanceid, c.fullname, 'course' AS type
                    FROM {cohort_visibility} cas
                    JOIN {course} c ON cas.instanceid = c.id AND cas.instancetype = ?
                    WHERE cas.cohortid = ?

                    UNION

                    SELECT cas.id, cas.instanceid, p.fullname, 'program' AS type
                    FROM {cohort_visibility} cas
                    JOIN {prog} p ON cas.instanceid = p.id AND cas.instancetype = ?
                    WHERE cas.cohortid = ?";
                $sqlparams = array(COHORT_ASSN_ITEMTYPE_COURSE, $cohortid, COHORT_ASSN_ITEMTYPE_PROGRAM, $cohortid);
            } else {
                $sql = "SELECT " .$DB->sql_concat("'c'", 'c.id') . " AS instanceid, c.id, c.fullname AS fullname, 'course' AS type
                    FROM {enrol} e
                    JOIN {course} c ON e.courseid = c.id
                    WHERE enrol = 'cohort'
                    AND customint1 = ?

                    UNION

                    SELECT " . $DB->sql_concat("'p'", 'p.id') . " AS instanceid, p.id, p.fullname, 'program' AS type
                    FROM {prog_assignment} pa
                    JOIN {prog} p ON pa.programid = p.id
                    WHERE pa.assignmenttype = ?
                    AND pa.assignmenttypeid = ?";
                $sqlparams = array($cohortid, ASSIGNTYPE_COHORT, $cohortid);
            }
            break;
    }

    return $DB->get_records_sql($sql, $sqlparams);
}


/**
 * Cohort class which centrally stores a few bits of information about cohorts
 */
class cohort {
    const TYPE_STATIC = 1;
    const TYPE_DYNAMIC = 2;

    /**
     * Returns an array of the cohort types, used by the add/edit form
     * @return array
     */
    public static function getCohortTypes() {
        return array(
            self::TYPE_STATIC => get_string('set', 'totara_cohort'),
            self::TYPE_DYNAMIC => get_string('dynamic', 'totara_cohort')
        );
    }

    /**
     * Get a cohort record.
     *
     * @param int $itemid
     * @return stdClass
     */
    public function get_item($itemid) {
        global $DB;

        return $DB->get_record('cohort', array('id' => $itemid));
    }

    /**
     * Display the goal table.
     *
     * @param stdClass $cohort
     * @param bool $can_edit
     * @return string
     */
    public static function display_goal_table($cohort, $can_edit) {
        global $OUTPUT;

        $remove = get_string('remove');
        $out = '';

        // Goals Table.
        $table = new html_table();
        $table->head = array();

        // Set up the header row.
        $table->head[] = get_string('name');

        // Only show the delete column if they have permissions.
        if ($can_edit) {
            $table->head[] = get_string('delete');
        }

        // Get all assignments.
        $assignments = goal::get_modules_assigned_goals(GOAL_ASSIGNMENT_AUDIENCE, $cohort->id);

        foreach ($assignments as $assignment) {
            $nameurl = new moodle_url('/totara/hierarchy/item/view.php', array('prefix' => 'goal', 'id' => $assignment->goalid));
            $namewithlink = html_writer::link($nameurl, format_string($assignment->fullname));

            $row = array(new html_table_cell($namewithlink));

            // Only show the delete column if they have permissions.
            if ($can_edit) {
                $params = array('goalid' => $assignment->goalid, 'assigntype' => GOAL_ASSIGNMENT_AUDIENCE, 'modid' => $cohort->id);
                $url = new moodle_url('/totara/hierarchy/prefix/goal/assign/remove.php', $params);

                $delete = $OUTPUT->action_icon(
                    $url,
                    new pix_icon('t/delete', $remove, null, array('class' => 'iconsmall')),
                    null,
                    array('id' => 'goalassigdel', 'title' => $remove));
                $row[] = new html_table_cell($delete);
            } else {
                $delete = null;
            }

            $table->data[] = new html_table_row($row);
        }
        $out .= html_writer::start_tag('div', array('id' => 'print_assigned_goals', 'class' => 'cohort'));
        $out .= html_writer::table($table);
        $out .= html_writer::end_tag('div');

        return $out;
    }
}

/******************************************************************************
 * Cohort event handlers, called from /totara/cohort/db/events.php
 ******************************************************************************/
class totaracohort_event_handler {
    /**
     * Event handler for when a user custom profiler field is deleted.
     *
     * @param \totara_customfield\event\profilefield_deleted $event
     * @return boolean
     */
    public static function profilefield_deleted(\totara_customfield\event\profilefield_deleted $event) {
        // TODO: rewrite for new dynamic cohorts.
        return true;
    }

    /**
     * Event handler for when a position is updated or deleted
     *
     * Cohorts that have this position directly attached to them, and cohorts which
     * are attached to a parent of this position are affected.
     *
     * @param \core\event\base $event - using base since this is called by update and create
     * @return boolean
     */
    public static function position_updated(\core\event\base $event) {
        // TODO: rewrite for new dynamic cohorts.
        return true;
    }

    /**
     * Event handler for when an organisation is updated
     *
     * Cohorts that have this organisation directly attached to them, and cohorts which
     * are attached to a parent of this organisation are affected.
     *
     * @param \core\event\base $event - using base since this is called by update and create
     * @return boolean
     */
    public static function organisation_updated(\core\event\base $event) {
        // TODO: rewrite for new dynamic cohorts.
        return true;
    }
}

/**
 * Update cohort operator or ruleset operator.
 *
 * @param $cohortid Cohort id where the operator will be updated.
 * @param $id Object id to be updated. Cohortid or Rulesetid
 * @param $type Operator type to be updated. Must be one of these: COHORT_OPERATOR_TYPE_COHORT, COHORT_OPERATOR_TYPE_RULESET
 * @param $operatorvalue Operator value AND(0) or OR(10)
 * @return boolean $result True if success, false otherwise
 */
function totara_cohort_update_operator($cohortid, $id, $type, $operatorvalue) {
    global $DB, $USER;

    $sql = "SELECT c.idnumber, c.draftcollectionid, crc.rulesetoperator, crc.status
        FROM {cohort} c
        INNER JOIN {cohort_rule_collections} crc ON c.draftcollectionid = crc.id
        WHERE c.id = ?";
    $cohort = $DB->get_record_sql($sql, array($cohortid), '*', MUST_EXIST);
    $result = false;

    if (!$cohort || !in_array($type, array(COHORT_OPERATOR_TYPE_COHORT, COHORT_OPERATOR_TYPE_RULESET)) || empty($id)) {
        return $result;
    }

    if ($type === COHORT_OPERATOR_TYPE_COHORT) {
        // Update cohort operator.
        if ($operatorvalue != $cohort->rulesetoperator) {
            $rulecollection = new stdClass();
            $rulecollection->id = $cohort->draftcollectionid;
            $rulecollection->rulesetoperator = $operatorvalue;
            $rulecollection->status = COHORT_COL_STATUS_DRAFT_CHANGED;
            $rulecollection->timemodified = time();
            $rulecollection->modifierid = $USER->id;
            $result = $DB->update_record('cohort_rule_collections', $rulecollection);
        }
    } else if ($type === COHORT_OPERATOR_TYPE_RULESET) {
        $operator = $DB->get_field('cohort_rulesets', 'operator', array('id' => $id));
        if ($operator != $operatorvalue) {
            // Update ruleset operator.
            $ruleset = new stdClass();
            $ruleset->id = $id;
            $ruleset->operator = $operatorvalue;
            $ruleset->timemodified = time();
            $ruleset->modifierid = $USER->id;
            $result = $DB->update_record('cohort_rulesets', $ruleset);

            // Update cohort rule collection.
            $rulecollection = new stdClass;
            $rulecollection->id = $cohort->draftcollectionid;
            $rulecollection->status = COHORT_COL_STATUS_DRAFT_CHANGED;
            $result = $result && $DB->update_record('cohort_rule_collections', $rulecollection);
        }
    }

    return $result;
}

/**
 * Get the where clause for a dynamic cohort's query. The where clause will assume that
 * the "mdl_user" table has the alias "u".
 * @param $cohortid
 * @return string
 */
function totara_cohort_get_dynamic_cohort_whereclause($cohortid) {
    global $CFG, $COHORT_RULES_OP, $DB;
    require_once($CFG->dirroot.'/totara/cohort/rules/lib.php');

    $ruledefs = cohort_rules_list();

    $cohort = $DB->get_record('cohort', array('id'=>$cohortid));
    $rulesetoperator = $DB->get_field('cohort_rule_collections', 'rulesetoperator', array('id' => $cohort->activecollectionid));
    $rulesets = $DB->get_records('cohort_rulesets', array('rulecollectionid' => $cohort->activecollectionid), 'sortorder');
    $whereclause = new stdClass();
    if (empty($rulesets)) {
        // no rules, so no members!
        $whereclause->sql = '1 = 0';
        $whereclause->params = array();
        return $whereclause;
    }
    $whereclause->sql = (($rulesetoperator == COHORT_RULES_OP_AND) ? '1=1 ' : '1=0 ') . " \n";
    $whereclause->params = array();
    foreach ($rulesets as $ruleset) {
        $rules = $DB->get_records('cohort_rules', array('rulesetid' => $ruleset->id), 'sortorder');

        // Rulesets should never be empty, but if this one is, just skip it.
        if (!$rules) {
            continue;
        }

        // Add the operator that goes between rulesets
        $whereclause->sql .= '    ' . strtoupper($COHORT_RULES_OP[$rulesetoperator]) . ' ( ';
        $whereclause->sql .= (($ruleset->operator == COHORT_RULES_OP_AND) ? '1=1 ' : '1=0 ');
        $whereclause->sql .= "\n";

        foreach ($rules as $rulerec) {
            /* @var $rule cohort_rule_option */
            if (isset($ruledefs[$rulerec->ruletype][$rulerec->name])) {
                $rule = $ruledefs[$rulerec->ruletype][$rulerec->name];
                $sqlhandler = $rule->sqlhandler;
                $sqlhandler->fetch($rulerec->id);
                $snippet = $sqlhandler->get_sql_snippet();
                if (!empty($snippet->sql)) {
                    $whereclause->sql .= "        " . strtoupper($COHORT_RULES_OP[$ruleset->operator]) . " {$snippet->sql} \n";
                    $whereclause->params = array_merge($whereclause->params, $snippet->params);
                }
            }
        }

        $whereclause->sql .= "    ) \n";
    }

    return $whereclause;
}


/**
 * Update the member list of this cohort (and consequently enrolments and stuff too)
 * @param int $cohortid
 * @param int $userid (optional) If set, only update this user
 * @param int $delaymessages (optional) If true, queue the messages for the cron. If false, send them now. Defaults to false.
 * @return mixed Change in number of members, or false for failure
 */
function totara_cohort_update_dynamic_cohort_members($cohortid, $userid=0, $delaymessages=false, $updatenested=true) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/totara/cohort/rules/lib.php');

    /// update necessary nested cohorts first (if any)
    if ($updatenested) {
        $nestedcohorts = totara_cohort_get_nested_dynamic_cohorts($cohortid, array(), true);
        foreach ($nestedcohorts as $ncohortid) {
            totara_cohort_update_dynamic_cohort_members($ncohortid, $userid, $delaymessages, false);
        }
    }

    $beforecount = $DB->count_records('cohort_members', array('cohortid' => $cohortid));

    /// find members who should be added and deleted
    $sql = "
       SELECT userid AS id, MAX(inrules) AS addme, MAX(inmembers) AS deleteme
       FROM (
           SELECT u.id as userid, 1 AS inrules, 0 AS inmembers
           FROM {user} u
           WHERE u.username <> 'guest'
           and u.deleted = 0
           and u.confirmed = 1";
    $sqlparams = array();

    if ($userid) {
        $sql .= " AND u.id = :userid ";
        $sqlparams['userid'] = $userid;
    }

    $whereclause = totara_cohort_get_dynamic_cohort_whereclause($cohortid);
    if (empty($whereclause)) {
        // no whereclause, no members!
        return false;
    }
    $sql .= " AND ({$whereclause->sql})";

    $sql .= " UNION ALL
               SELECT cm.userid AS userid, 0 AS inrules, 1 AS inmembers
               FROM {cohort_members} cm
               WHERE cm.cohortid = :cohortid ";
    $sqlparams['cohortid'] = $cohortid;

    if ($userid) {
        $sql .= " AND cm.userid = :userid2 ";
        $sqlparams['userid2'] = $userid;
    }

    $sql .= "  ) q
       GROUP BY userid
       HAVING MAX(inrules) <> MAX(inmembers)
    ";

    $changedmembers = $DB->get_recordset_sql($sql, array_merge($sqlparams, $whereclause->params));
    if (!$changedmembers) {
        $changedmembers = array();
    }

    /// update memberships in batches
    $newmembers = array();
    $delmembers = array();
    $cmcount = 0;
    $numadd = 0;
    $numdel = 0;
    $currentcohortroles = totara_get_cohort_roles($cohortid); // Current roles assigned to this cohort.
    foreach ($changedmembers as $mem) {
        $cmcount++;
        if ($mem->addme) {
            $newmembers[$mem->id] = $mem;
        }
        if ($mem->deleteme) {
            $delmembers[$mem->id] = $mem;
        }
        if ($cmcount < 2000) {
            // continue to add records to current batches
            continue;
        }

        if (!empty($newmembers)) {
            $now = time();
            foreach ($newmembers as $i => $rec) {
                $newmembers[$i] = (object)array('cohortid' => $cohortid, 'userid' => $rec->id, 'timeadded' => $now);
            }
            $DB->insert_records_via_batch('cohort_members', $newmembers);
            totara_set_role_assignments_cohort($currentcohortroles, $cohortid, array_keys($newmembers));
            totara_cohort_notify_add_users($cohortid, array_keys($newmembers), $delaymessages);
            $numadd += count($newmembers);
            unset($newmembers);
        }

        if (!empty($delmembers)) {
            $delids = array_keys($delmembers);
            unset($delmembers);
            list($sqlin, $params) = $DB->get_in_or_equal($delids, SQL_PARAMS_NAMED);
            $params['cohortid'] = $cohortid;
            $DB->delete_records_select(
                'cohort_members',
                "cohortid = :cohortid AND userid ".$sqlin, $params
            );
            totara_unset_role_assignments_cohort($currentcohortroles, $cohortid, $delids);
            totara_cohort_notify_del_users($cohortid, $delids, $delaymessages);
            $numdel += count($delids);
            unset($delids);
        }

        // reset stuff for next batch
        $newmembers = array();
        $delmembers = array();
        $cmcount = 0;
    }
    $changedmembers->close();
    unset($changedmembers);

    /// process leftover batches (if any)
    if (!empty($newmembers)) {
        $now = time();
        foreach ($newmembers as $i => $rec) {
            $newmembers[$i] = (object)array('cohortid' => $cohortid, 'userid' => $rec->id, 'timeadded' => $now);
        }
        $DB->insert_records_via_batch('cohort_members', $newmembers);
        totara_set_role_assignments_cohort($currentcohortroles, $cohortid, array_keys($newmembers));
        totara_cohort_notify_add_users($cohortid, array_keys($newmembers), $delaymessages);
        $numadd += count($newmembers);
        unset($newmembers);
    }

    if (!empty($delmembers)) {
        $delids = array_keys($delmembers);
        unset($delmembers);
        list($sqlin, $params) = $DB->get_in_or_equal($delids, SQL_PARAMS_NAMED);
        $params['cohortid'] = $cohortid;
        $DB->delete_records_select(
            'cohort_members',
            "cohortid = :cohortid AND userid ".$sqlin, $params
        );
        totara_unset_role_assignments_cohort($currentcohortroles, $cohortid, $delids);
        totara_cohort_notify_del_users($cohortid, $delids, $delaymessages);
        $numdel += count($delids);
        unset($delids);
    }

    return array('add' => $numadd, 'del' => $numdel);
}

/**
 * Get all nested cohorts for the specified parent cohort
 *
 * @param int   $cohortid       The parent cohortid
 * @param array $current        The current list of found nested cohortids (used by recursion)
 * @param bool  $activeonly     A flag to exclude any audiences that either haven't started or have expired
 */
function totara_cohort_get_nested_dynamic_cohorts($cohortid, $current = array(), $activeonly = false) {
    global $DB;

    if (empty($current)) {
        $current = array($cohortid);
        $mastercohortid = $cohortid;
    }

    list($notinsql, $sqlparams) = $DB->get_in_or_equal($current, SQL_PARAMS_QM, 'param', false);
    array_unshift($sqlparams, $cohortid);

    $sql = "SELECT DISTINCT c.id
        FROM {cohort} c
        INNER JOIN {cohort_rule_params} crp ON c.id = " . $DB->sql_cast_char2int('crp.value') . "
            AND crp.name = 'cohortids'
        INNER JOIN {cohort_rules} cr ON crp.ruleid = cr.id
            AND cr.ruletype = 'cohort' AND cr.name = 'cohortmember'
        INNER JOIN {cohort_rulesets} crs ON cr.rulesetid = crs.id
        INNER JOIN {cohort_rule_collections} crc ON crs.rulecollectionid = crc.id
            AND crc.status = " . COHORT_COL_STATUS_ACTIVE . "
        WHERE crc.cohortid = ?
        AND c.id {$notinsql}
        AND c.cohorttype = " . cohort::TYPE_DYNAMIC;

    if ($activeonly) {
        $now = time();
        $sql .= "
            AND (c.startdate IS NULL OR c.startdate = 0 OR c.startdate < ?)
            AND (c.enddate IS NULL OR c.enddate = 0 OR c.enddate > ?)";
        $sqlparams[] = $now;
        $sqlparams[] = $now;
    }

    $cohorts = $DB->get_records_sql($sql, $sqlparams);
    $cohorts = array_keys($cohorts);

    $current = array_unique(array_merge($current, $cohorts));

    foreach ($cohorts as $ncohortid) {
        $current = array_merge($current, totara_cohort_get_nested_dynamic_cohorts($ncohortid, $current, $activeonly));
    }
    $current = array_unique($current);

    if (!empty($mastercohortid)) {
        // unset the top level cohortid
        foreach ($current as $i => $v) {
            if ($v == $mastercohortid) {
                unset($current[$i]);
                break;
            }
        }
    }

    return $current;
}


/**
 * Sends any cohort notifications that were stored in the queue table. (Called from the cohort cron)
 */
function totara_cohort_send_queued_notifications(){
    global $CFG, $DB;

    $sql = "SELECT " . $DB->sql_concat('cmq.cohortid', "'-'", 'cmq.action') . " AS id, cmq.cohortid, cmq.action, cohort.idnumber
              FROM {cohort_msg_queue} cmq
        INNER JOIN {cohort} cohort
                ON cohort.id = cmq.cohortid
             WHERE cmq.processed = 0
          GROUP BY cmq.cohortid, cmq.action, cohort.idnumber
          ORDER BY cohort.idnumber, cmq.action";

    $batchlist = $DB->get_records_sql($sql);
    if (empty($batchlist)) {
        return true;
    }

    foreach ($batchlist as $batch) {
        $timenow = time();
        mtrace(date("H:i:s", $timenow)."  Sending queued '{$batch->action}' messages for cohort '{$batch->idnumber}' (id:{$batch->cohortid}) ");

        // First flag the notices we're going to send, so that subsequent cron runs won't accidentally double-send them
        // if this takes a long time.
        $processtime = time();
        $sql = 'UPDATE {cohort_msg_queue} SET processed = ? WHERE cohortid = ? AND action = ? and processed = 0';
        $sqlparams = array($processtime, $batch->cohortid, $batch->action);
        $DB->execute($sql, $sqlparams);

        $sql = "SELECT userid
                  FROM {cohort_msg_queue}
                 WHERE cohortid = ?
                   AND action = ?
                   AND processed = ?
              GROUP BY userid";
        $msglist = $DB->get_records_sql($sql, array($batch->cohortid, $batch->action, $processtime));

        if (empty($msglist)) {
            continue;
        }

        $timenow = time();
        mtrace(date("H:i:s", $timenow)."    ... " . count($msglist) . ' queued to send out');
        totara_cohort_notify_users($batch->cohortid, array_keys($msglist), $batch->action);

        $DB->delete_records('cohort_msg_queue', array('cohortid' => $batch->cohortid, 'action' => $batch->action, 'processed' => $processtime));

        $timenow = time();
        mtrace(date("H:i:s", $timenow)."    ...sent!");
    }
}

/**
 * Ensure dynamic cohorts are up to date
 *
 * Used when running the cohort sync to make sure members are up to date.
 *
 * @param int $courseid one course, empty means all
 * @param progress_trace $trace
 * @return void
 */
function totara_cohort_check_and_update_dynamic_cohort_members($courseid, progress_trace $trace) {
    global $DB;

    $trace->output('removing user memberships of deleted users...');
    totara_cohort_clean_deleted_users();

    // first make sure dynamic cohort members are up to date
    if (empty($courseid)) {
        $dcohorts = $DB->get_records('cohort', array('cohorttype' => cohort::TYPE_DYNAMIC), 'idnumber');
    } else {
        // only update members of cohorts that is associated with this course
        $dcohorts = totara_cohort_get_course_cohorts($courseid, cohort::TYPE_DYNAMIC);
    }

    // Looking for cohorts with broken rules.
    $cohortwithbrokenrules = totara_cohort_broken_rules($courseid, null, $trace);

    $trace->output('... ' . count($cohortwithbrokenrules) . ' Audience(s) with broken rule(s) found.');
    $trace->output('updating dynamic cohort members...');

    if (!empty($cohortwithbrokenrules)) {
        // Ignore audience with broken rules.
        $dcohorts = array_udiff($dcohorts, $cohortwithbrokenrules,
                        function ($obj1, $obj2) {
                            return $obj1->id - $obj2->id;
                        }
        );

        // Look for all site administrators.
        $siteadmins = get_admins();
        $notifiedcohorts = array();
        foreach ($cohortwithbrokenrules as $brokencohort) {
            if ($brokencohort->broken == COHORT_BROKEN_RULE_NONE) { // Cohort has not been marked as broken.
                // Notify about the broken rules.
                if (totara_send_notification_broken_rule($siteadmins, $brokencohort)) {
                    $notifiedcohorts[$brokencohort->id] = $brokencohort;
                }
            } else if ($brokencohort->broken == COHORT_BROKEN_RULE_NOT_NOTIFIED) {
                // Cohort has been marked as broken but it hasn't been notified. So, notify.
                if (totara_send_notification_broken_rule($siteadmins, $brokencohort)) {
                    $notifiedcohorts[$brokencohort->id] = $brokencohort;
                }
            }
        }
        // Update broken field for cohorts that have been notified.
        totara_update_broken_field(array_keys($notifiedcohorts), COHORT_BROKEN_RULE_NOTIFIED);
        // Update broken field for cohorts that could have not been notified.
        $notnotified = totara_search_for_value($cohortwithbrokenrules, 'broken', TOTARA_SEARCH_OP_NOT_EQUAL,
                        COHORT_BROKEN_RULE_NOTIFIED);
        $notnotified = array_diff(array_keys($notnotified), array_keys($notifiedcohorts));
        totara_update_broken_field($notnotified, COHORT_BROKEN_RULE_NOT_NOTIFIED);
    }

    // Update broken field for active cohorts.
    $brokencohorts = totara_search_for_value($dcohorts, 'broken', TOTARA_SEARCH_OP_NOT_EQUAL, COHORT_BROKEN_RULE_NONE);
    totara_update_broken_field(array_keys($brokencohorts), COHORT_BROKEN_RULE_NONE);

    foreach ($dcohorts as $cohort) {
        $active = totara_cohort_is_active($cohort);
        if (!$active) {
            $trace->output("inactive cohort {$cohort->idnumber}");
            $trace->output("start-date: " . ($cohort->startdate === null ? 'null' : userdate($cohort->startdate)));
            $trace->output("end-date: " . ($cohort->enddate === null ? 'null:' : userdate($cohort->enddate)));
            continue;
        }
        try {
            $timenow = time();
            $trace->output(date("H:i:s", $timenow)." updating {$cohort->idnumber} members...");
            $result = totara_cohort_update_dynamic_cohort_members($cohort->id);
            if (is_array($result) && array_key_exists('add', $result) && array_key_exists('del', $result)) {
                $trace->output("{$result['add']} members added; {$result['del']} members deleted");
            } else {
                throw new Exception("error processing members: " . print_r($result, true));
            }
        } catch (Exception $e) {
            // Log it.
            $trace->output($e->getMessage());
        }
    } // foreach

    // Set lastrun time to be checked by totara_cohort cron - if $courseid is null we have arrived here from cron.
    if (empty($courseid)) {
        $timenow = time();
        if (!set_config('lasthourlycron', $timenow, 'totara_cohort')) {
            $trace->output("Error: could not update lasthourlycron timestamp for totara_cohort plugin.");
        }
    }
}


function totara_cohort_clean_deleted_users() {
    global $DB;

    $DB->delete_records_select('cohort_members', "userid IN (SELECT id FROM {user} WHERE deleted = 1)");
}


/**
 * Returns the where clause snippet for determining if a cohort is currently active, based on its start and end dates
 * @param string $cohorttablealias (optional) SQL alias for the mdl_cohort table
 * @param int $now (optional) timestamp to use in the comparison (defaults to time())
 * @return string
 */
function totara_cohort_date_where_clause($cohorttablealias = null, $now = null) {
    if ($now === null) {
        $now = time();
    }

    if ($cohorttablealias === null) {
        $alias = '';
    } else {
        $alias = "{$cohorttablealias}.";
    }

    return "("
        ."({$alias}startdate IS NULL OR {$alias}startdate = 0 OR {$alias}startdate < {$now}) "
        ."and ({$alias}enddate IS NULL OR {$alias}enddate = 0 OR {$alias}enddate > {$now})"
        .")";
}


/**
 * Deletes records from cohort_members which have a cohortid that doesn't match
 * any existing cohort in the cohort table.
 */
function totara_cohort_delete_stale_memberships() {
    global $DB;

    // Delete invalid members
    return $DB->delete_records_select('cohort_members', "cohortid NOT IN (SELECT id FROM {cohort})");
}

/**
 * Make an exact copy of a currently existing cohort
 * @param $cohortid
 */
function totara_cohort_clone_cohort($oldcohortid) {
    global $CFG, $DB, $USER;

    $transaction = $DB->start_delegated_transaction();

    $newcohort = new stdClass();

    $oldcohort = $DB->get_record('cohort', array('id' => $oldcohortid));

    // Create the base cohort record
    $newcohort->contextid =         $oldcohort->contextid;
    $newcohort->name =              get_string('clonename', 'totara_cohort', $oldcohort->name);
    $newcohort->idnumber =          totara_cohort_next_automatic_id();
    if (!$newcohort->idnumber) {
        $newcohort->idnumber = $oldcohort->idnumber . '.1';
    }
    $newcohort->description =       $oldcohort->description;
    $newcohort->descriptionformat = $oldcohort->descriptionformat;
    $newcohort->component =         $oldcohort->component;
    $newcohort->cohorttype =        $oldcohort->cohorttype;
    $newcohort->visibility =        $oldcohort->visibility;
    $newcohort->alertmembers =      $oldcohort->alertmembers;
    $newcohort->startdate =         $oldcohort->startdate;
    $newcohort->enddate =           $oldcohort->enddate;

    $newcohort->id = cohort_add_cohort($newcohort, $addcollections=false);

    // Copy tags
    require_once($CFG->dirroot . '/tag/lib.php');
    $tags = tag_get_tags_array('cohort', $oldcohortid, 'official');
    if (!empty($tags)) {
        tag_set('cohort', $newcohort->id, $tags);
    }

    // Copy the learning items
    $oldlearningitems = totara_cohort_get_associations($oldcohortid);
    foreach ($oldlearningitems as $olditem) {
        totara_cohort_add_association($newcohort->id, $olditem->instanceid, $olditem->type);
    }
    unset($oldlearningitems);
    unset($olditem);
    unset($newitem);

    // Copy the member list
    $oldmembers = $DB->get_records('cohort_members', array('cohortid' => $oldcohortid));
    foreach ($oldmembers as $oldmember) {

        $newmember = new stdClass();
        $newmember->cohortid =  $newcohort->id;
        $newmember->userid =    $oldmember->userid;
        $newmember->timeadded = time();
        $DB->insert_record('cohort_members', $newmember);
    }
    unset($oldmembers);
    unset($oldmember);
    unset($newmember);

    // If the cohort is dynamic, copy over the rules
    if ($newcohort->cohorttype == cohort::TYPE_DYNAMIC) {
        // Clone active rule collection
        $activecollid = cohort_rules_clone_collection($oldcohort->activecollectionid, null, true, $newcohort->id);

        // Clone draft rule collection
        $draftcollid = cohort_rules_clone_collection($oldcohort->draftcollectionid, null, true, $newcohort->id);

        // Update new cohort's collections to created clones
        $todb = new stdClass;
        $todb->id = $newcohort->id;
        $todb->activecollectionid = $activecollid;
        $todb->draftcollectionid = $draftcollid;
        $DB->update_record('cohort', $todb);
    }

    $transaction->allow_commit();

    return $newcohort->id;
}

function totara_cohort_notify_add_users($cohortid, $adduserids, $delaymessages=false) {
    return totara_cohort_notify_users($cohortid, $adduserids, 'membersadded', $delaymessages);
}

function totara_cohort_notify_del_users($cohortid, $deluserids, $delaymessages=false) {
    return totara_cohort_notify_users($cohortid, $deluserids, 'membersremoved', $delaymessages);
}

/**
 * Processor function to be passed in to {@link insert_records_via_batch()}. Used by
 * {@link totara_cohort_notify_users()}.
 *
 * @param integer $userid The userid of the current record.
 * @param object $templateobject An object containing the other fields to be inserted.
 *
 * @return object The object to insert into the database for this user.
 */
function totara_process_user_notifications($userid, $templateobject) {
    $templateobject->userid = $userid;
    return $templateobject;
}

/**
 * Send the notifications cohort members can receive when a user is added/removed from a cohort
 *
 * @param int $cohortid ID of cohort
 * @param array $userids Users beind addded/removed (NOT necessarily the message recipients!)
 * @param string $action "membersadded" or "membersremoved"
 * @param boolean $delaymessages True to queue messages for next cron run, false to send them now
 */
function totara_cohort_notify_users($cohortid, $userids, $action, $delaymessages=false) {
    global $CFG, $DB, $USER;

    $cohort = $DB->get_record('cohort', array('id' => $cohortid), 'id, name, alertmembers');
    if ($cohort->alertmembers == COHORT_ALERT_NONE) {
        return true;
    }

    if (!count($userids)) {
        return true;
    }

    if ($delaymessages) {
        // Don't send the messages now. Do a bulk insert to queue them for later sending.
        $now = time();
        $msg = new stdClass();
        $msg->cohortid = $cohortid;
        $msg->action = $action;
        $msg->processed = 0;
        $msg->modifierid = $USER->id;
        $msg->timecreated = $now;
        $msg->timemodified = $now;

        return $DB->insert_records_via_batch('cohort_msg_queue', $userids, 'totara_process_user_notifications', array($msg));
    }

    $memberlist = array();
    $usernamefields = get_all_user_name_fields(true);
    $users = $DB->get_records_select('user', 'id IN ('.implode(',', $userids).')', null, '', 'id, ' . $usernamefields);
    foreach ($users as $user) {
        $memberlist[] = fullname($user);
    }
    unset($users);
    sort($memberlist);

    $a = new stdClass();
    $a->cohortname = $cohort->name;
    $a->supportemail = $CFG->supportemail;
    $a->cohortmembers = implode("\n", $memberlist);
    $a->affectedcount = count($memberlist);
    unset($memberlist);

    //$fields = "u.id, u.username, u.firstname, u.lastname, u.maildisplay, u.mailformat, u.maildigest, u.emailstop, u.imagealt, u.email, u.city, u.country, u.lastaccess, u.lastlogin, u.picture, u.timezone, u.theme, u.lang, u.trackforums, u.mnethostid";
    $fields  = "id, username, maildisplay, mailformat, maildigest, emailstop, imagealt, email, city, country, lastaccess, lastlogin, picture, timezone, theme, lang, trackforums, mnethostid, ";
    $fields .= $usernamefields;
    switch ($cohort->alertmembers) {
        case COHORT_ALERT_AFFECTED:
            $towho = 'toaffected';
            $tousers = $DB->get_records_select('user', 'id IN ('.implode(',', $userids).')', null, 'id', $fields);
            break;
        case COHORT_ALERT_ALL:
            $towho = 'toall';
            $tousers = $DB->get_records_select('user', "id IN (SELECT userid FROM {cohort_members} WHERE cohortid=?)", array($cohortid), 'id', $fields);
            break;
        default:
            return false;
    }

    $strmgr = get_string_manager();
    $eventdata = new stdClass();

    foreach ($tousers as $touser) {
        // Send emails in user lang.
        $emailsubject = $strmgr->get_string("msg:{$action}_{$towho}_emailsubject", 'totara_cohort', $a, $touser->lang);
        $notice = $strmgr->get_string("msg:{$action}_{$towho}_notice", 'totara_cohort', $a, $touser->lang);
        $eventdata->subject = $emailsubject;
        $eventdata->fullmessage = $notice;

        $eventdata->userto = $touser;
        $eventdata->userfrom = $touser;
        tm_alert_send($eventdata);
    }

    return true;
}


/**
 * Returns whether or not this cohort should be considered active based on its start & end dates
 * @param $cohort object a row from the mdl_cohort table
 * @param int $now (optional) timestamp to use in the comparison (defaults to time())
 * @return bool
 */
function totara_cohort_is_active($cohort, $now = null){
    if ($now === null) {
        $now = time();
    }

    return
        (
            !$cohort->startdate || $cohort->startdate < $now
        ) && (
            !$cohort->enddate || $cohort->enddate > $now
        );
}


/**
 * Get the next suggested automatic id number.
 * NOTE: After using this function, make sure to call totara_cohort_increment_automatic_id
 * to "mark off" the id number you used.
 * @return str
 */
function totara_cohort_next_automatic_id() {
    global $CFG, $DB;

    // If these config variables aren't set just return null
    if (!isset($CFG->cohort_autoidformat) || !isset($CFG->cohort_lastautoidnumber)) {
        return '';
    }
    $idnum = (int) $CFG->cohort_lastautoidnumber;

    // If the autoid we generate is already in use, iterate to the next one.
    do {
        $idnum++;
        $idvalue = sprintf($CFG->cohort_autoidformat, $idnum);
    } while ($DB->record_exists('cohort', array('idnumber' => $idvalue)));

    return $idvalue;
}

/**
 * Increment the automatic id number counter from totara_cohort_next_automatic_id()
 * @param $idnumberused
 */
function totara_cohort_increment_automatic_id($idnumberused) {
    global $CFG;

    // Increment the cohort auto-id, if used
    if (isset($CFG->cohort_autoidformat) && isset($CFG->cohort_lastautoidnumber)) {
        // Check to see if the idnumber we used matches the autoidformat
        // Save the idnumber in the $idint variable if so
        if (
            sscanf($idnumberused, $CFG->cohort_autoidformat, $idint)
            && $idint > (int) $CFG->cohort_lastautoidnumber
        ) {
            set_config('cohort_lastautoidnumber', $idint);
        }
    }
}

/**
 * Generates the navlinks for a particular Moodle cohort
 *
 * @param $cohortid int (optional)
 * @param $cohortname str (optional)
 * @param $subpagetitle str (optional)
 * @return array suitable to pass as a $navlinks to Moodle lib functions
 */
function totara_cohort_navlinks($cohortid=false, $cohortname=false, $subpagetitle=false) {
    global $CFG, $PAGE;

    if ($cohortid && $cohortname) {
        $PAGE->navbar->add(s($cohortname), $CFG->wwwroot.'/cohort/view.php?id='.$cohortid);
    }
    if ($subpagetitle) {
        $PAGE->navbar->add(s($subpagetitle));
    }
}

/**
 * Returns a link showing the completion info for a given cohort in a program.
 * (used mainly by the cohort enrollment report)
 * @param $cohortid
 * @param $programid
 */
function totara_cohort_program_completion_link($cohortid, $programid){
    global $DB;
    $item = $DB->get_record('prog_assignment', array('assignmenttypeid' => $cohortid, 'programid' => $programid, 'assignmenttype' => ASSIGNTYPE_COHORT), 'assignmenttypeid as id, completiontime, completionevent, completioninstance');
    $cat = new cohorts_category();
    if (!$item) {
        $item = $cat->get_item($cohortid);
    }
    $html = $cat->get_completion($item);
    $html = '<input type="hidden" name="programid" value="'. $programid .'" />' . $html;
    return $html;
}

/**
 * Get cohorts associated with a certain course (excl programs)
 *
 * @param int $courseid course id
 * @param int $type the cohorttype e.g cohort::TYPE_DYNAMIC, cohort::TYPE_STATIC
 * @return object cohort database table records
 */
function totara_cohort_get_course_cohorts($courseid, $type=null, $fields='c.*') {
    global $DB;

    $sql = "SELECT {$fields}
        FROM {enrol} e
        JOIN {cohort} c ON e.customint1 = c.id
        WHERE e.enrol = 'cohort'
        AND e.courseid = ?";
    $sqlparams = array($courseid);

    if (!empty($type)) {
        $sql .= " AND c.cohorttype = ?";
        $sqlparams[] = $type;
    }

    return $DB->get_records_sql($sql, $sqlparams);
}

/**
 * Get course/programs associated with a certain cohort's visibility settings.
 *
 * @param int $instanceid Course or Program id
 * @param int $instancetype e.g COHORT_ASSN_ITEMTYPE_COURSE, COHORT_ASSN_ITEMTYPE_PROGRAM
 * @return array List of cohorts associated with this Course or Program
 */
function totara_cohort_get_visible_learning($instanceid, $instancetype = COHORT_ASSN_ITEMTYPE_COURSE) {
    global $DB;

    $sql = "SELECT cas.cohortid as id, cas.instanceid, c.name as fullname, c.cohorttype, cas.id as associd
        FROM {cohort_visibility} cas
            JOIN {cohort} c ON cas.cohortid = c.id
        WHERE cas.instanceid = :instanceid AND cas.instancetype = :instancetype";
    $sqlparams = array('instancetype' => $instancetype, 'instanceid' => $instanceid);

    return $DB->get_records_sql($sql, $sqlparams);
}

/**
 * Used when running the cohort sync to know which cohort has a broken rule.
 *
 * @param int $courseid one course, empty means all
 * @param int $cohortid one cohort, empty means all
 * @param progress_trace $trace
 * @return array $cohortwithbrokenrules list of cohorts with broken rules
 */
function totara_cohort_broken_rules($courseid, $cohortid, progress_trace $trace) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/totara/core/utils.php');
    $cohortwithbrokenrules = array();

    $trace->output('Checking audiences with broken rules...');

    if (empty($courseid)) {
        if (empty($cohortid)) {
            $dynamiccohorts = $DB->get_records('cohort', array('cohorttype' => cohort::TYPE_DYNAMIC), 'idnumber');
        } else {
            $dynamiccohorts = $DB->get_records('cohort', array('id' => $cohortid, 'cohorttype' => cohort::TYPE_DYNAMIC), 'idnumber');
        }
    } else {
        // Only get members of cohorts that is associated with this course.
        $dynamiccohorts = totara_cohort_get_course_cohorts($courseid, cohort::TYPE_DYNAMIC);
        if (!empty($cohortid)) {
            // Get cohort.
            $dynamiccohorts = totara_search_for_value($dynamiccohorts, 'id', TOTARA_SEARCH_OP_EQUAL, $cohortid);
        }
    }

    foreach ($dynamiccohorts as $cohort) {
        $rulesets = $DB->get_records('cohort_rulesets', array('rulecollectionid' => $cohort->activecollectionid), 'sortorder');
        foreach ($rulesets as $ruleset) {
            $rules = $DB->get_records('cohort_rules', array('rulesetid' => $ruleset->id));
            foreach ($rules as $rulerec) {
                $rule = cohort_rules_get_rule_definition($rulerec->ruletype, $rulerec->name);
                if (!$rule) { // There is a broken rule.
                    $cohortwithbrokenrules[$cohort->id] = $cohort;
                    break 2;
                }
            }
        }
    }

    return $cohortwithbrokenrules;
}

/**
 * Display message of broken rule found.
 *
 * @param string $class the box's class style.
 * @param string $style the box's style.
 * @return void
 */
function totara_display_broken_rules_box($class = 'notifyproblem clearfix', $style='display:block') {
    $contents =  html_writer::tag('span', get_string('cohortbrokenrulesnotice', 'totara_cohort'));
    echo html_writer::div($contents, $class, array('id' => 'cohort_broken_rules_box', 'style' => $style));
}

/**
 * Send notification about a cohort with broken rules.
 *
 * @param array $users.
 * @param object $cohort.
 * @return boolean $sent
 */
function totara_send_notification_broken_rule($users, $cohort) {
    $sent = false;

    $subject = get_string('cohortbrokenrulesubject', 'totara_cohort');
    $fullmessage = get_string('cohortbrokenrulesmessage', 'totara_cohort');
    $fullmessage .= html_writer::start_tag('ul');
    $url = html_writer::link(new moodle_url('/totara/cohort/rules.php', array('id' => $cohort->id)), $cohort->name);
    $fullmessage .= html_writer::tag('li', $url);
    $fullmessage .= html_writer::end_tag('ul');

    foreach ($users as $user) {
        $newevent = new stdClass();
        $newevent->userfrom    = null;
        $newevent->userto      = $user;
        $newevent->fullmessage = $fullmessage;
        $newevent->subject     = $subject;
        $newevent->urgency     = TOTARA_MSG_URGENCY_URGENT;
        if (tm_alert_send($newevent)) {
            $sent = true; // It has been successfully notified.
        }
    }

    return $sent;
}

/**
 * Update broken field.
 *
 * @param array $cohortids.
 * @param integer $status.
 * @return boolean.
 */
function totara_update_broken_field($cohortids, $status) {
    global $DB;

    if (!empty($cohortids)) {
        list($insql, $inparams) = $DB->get_in_or_equal($cohortids);
        $sql = "UPDATE {cohort} SET broken = ? WHERE id {$insql}";
        $params = array_merge(array($status), $inparams);
        return $DB->execute($sql, $params);
    }

    return false;
}

/**
 * Get members of a cohort
 *
 * @param int $cohortid The cohort id
 *
 * @return array List of userids(members) assigned to the cohort
 */
function totara_get_members_cohort($cohortid) {
    global $DB;

    return $DB->get_records('cohort_members', array('cohortid' => $cohortid), '', 'userid');
}

/**
 * Get roles assigned to a cohort
 *
 * @param int $cohortid The cohort id where the roles are assigned.
 *
 * @return array List of roleids assigned to the cohort.
 */
function totara_get_cohort_roles($cohortid) {
    global $DB;

    return $DB->get_records('cohort_role', array('cohortid' => $cohortid), '', 'roleid, contextid');
}

/**
 * Deletes role(s) assigned to a cohort
 *
 * @param array $roleids Array containing roles ids to be deleted
 * @param int $cohortid The cohort which the roles are assigned to
 * @param int $contexid The context id in which the roles are assigned
 *
 * @return bool True success, false otherwise.
 */
function totara_delete_roles_cohort($roleids, $cohortid) {
    global $DB;
    $success = true;

    if (!empty($roleids)) {
        list($sql, $params) = $DB->get_in_or_equal(array_keys($roleids));
        $params[] = $cohortid;
        $select = "roleid {$sql} AND cohortid = ?";
        $success = $DB->delete_records_select('cohort_role', $select, $params);
    }

    return $success;
}

/**
 * Processor function to be passed in to {@link insert_records_via_batch()}. Used by
 * {@link totara_create_roles_cohort()}.
 *
 * @param object $role Object containing role id and the context id of the current record.
 * @param object $templateobject An object containing the other fields to be inserted.
 *
 * @return object The object to insert into the database for this role.
 */
function totara_process_role_cohort($role, $templateobject) {
    $templateobject->roleid = $role->roleid;
    $templateobject->contextid = $role->contextid;
    return $templateobject;
}

/**
 * Saves role(s) assigned to a cohort
 *
 * @param array $roleids Array of object containing role id and context id
 * @param int $cohortid The cohort which the roles will be assigned to
 *
 * @return bool True success, false otherwise
 */
function totara_create_roles_cohort($roleids, $cohortid) {
    global $DB, $USER;

    $now = time();
    $cohortrole = new stdClass();
    $cohortrole->cohortid = $cohortid;
    $cohortrole->usermodified = $USER->id;
    $cohortrole->timecreated = $now;
    $cohortrole->timemodified = $now;

    return $DB->insert_records_via_batch('cohort_role', $roleids, 'totara_process_role_cohort', array($cohortrole));
}

/**
 * Un-assign roles to members that were previously assigned to a cohort in the role_assignments table
 *
 * @param array $roles Roles to be unassigned - Array of object containing role id and context id
 * @param int $cohortid The cohort where the roles were assigned
 * @param array $members The members of the cohort to which the role(s) will be removed
 *
 * @return void
 */
function totara_unset_role_assignments_cohort($roles, $cohortid, $members) {
    foreach ($roles as $role) {
        $params = array(
            'roleid' => $role->roleid,
            'userids' => $members,
            'contextid' => $role->contextid,
            'component' => 'totara_cohort',
            'itemid' => $cohortid
        );
        role_unassign_all_bulk($params);
    }
}

/**
 * Assign roles to members of a cohort in the role_assignments table
 *
 * @param array $roles Roles to be assigned - Array of object containing role id and context id
 * @param int $cohortid The cohort where the roles were assigned
 * @param array $members The members of the cohort to which the role(s) will be assigned
 *
 * @return void
 */
function totara_set_role_assignments_cohort($roles, $cohortid, $members) {
    foreach ($roles as $role) {
        role_assign_bulk($role->roleid, $members, $role->contextid, 'totara_cohort', $cohortid);
    }
}

/**
 * Assign roles to members of a cohort
 *
 * @param array $roles Roles to be assigned - Array of object containing role id and context id
 * @param int $cohortid The cohort where the roles were assigned
 * @param array $members The members of the cohort to which the role(s) will be assigned
 *
 * @return bool
 */
function totara_assign_roles_cohort($roles, $cohortid, $members) {
    global $DB;

    try {
        $transaction = $DB->start_delegated_transaction();

        if (!empty($members)) {
            totara_set_role_assignments_cohort($roles, $cohortid, $members);
        }
        totara_create_roles_cohort($roles, $cohortid);

        $transaction->allow_commit();
    } catch (Exception $e) {
        $transaction->rollback($e);
        return false;
    }

    return true;
}

/**
 * Un-assign roles to members of a cohort
 *
 * @param array $roles Roles to be unassigned - Array of object containing role id and context id
 * @param int $cohortid The cohort where the roles were assigned
 * @param array $members The members of the cohort to which the role(s) will be removed
 *
 * @return bool
 */
function totara_unassign_roles_cohort($roles, $cohortid, $members) {
    global $DB;

    try {
        $transaction = $DB->start_delegated_transaction();

        if (!empty($members)) {
            totara_unset_role_assignments_cohort($roles, $cohortid, $members);
        }
        totara_delete_roles_cohort($roles, $cohortid);

        $transaction->allow_commit();
    } catch (Exception $e) {
        $transaction->rollback($e);
        return false;
    }

    return true;
}

/**
 * Assign or unassign roles to/from a cohort.
 *
 * @param int $cohortid Cohort ID
 * @param array $roles An array of the roles to be assigned,
 * with the ID of the role as key and context (in which it will be assigned) as their value
 *
 * @return bool $success True if the assigned/unassigned process has been success, false otherwise
 */
function totara_cohort_process_assigned_roles($cohortid, $roles) {
    $success = true;

    // Make an array of object to use it later when inserting via batch.
    $selectedroles = array();
    foreach ($roles as $key => $value) {
        $roleobj = new stdClass();
        $roleobj->roleid = $key;
        $roleobj->contextid = $value;
        $selectedroles[$key] = $roleobj;
    }

    // Get members of the cohort.
    $memberids = array();
    if ($members = totara_get_members_cohort($cohortid)) {
        $memberids = array_keys($members);
    }

    // Current roles assigned to this cohort.
    $currentroles = totara_get_cohort_roles($cohortid);

    // Unassign roles.
    if (!empty($currentroles)) {
        $rolestounassign = array_diff_key($currentroles, $selectedroles);
        $success = totara_unassign_roles_cohort($rolestounassign, $cohortid, $memberids);
    }

    // Assign roles.
    $rolestoassign = array_diff_key($selectedroles, $currentroles);
    if (!empty($rolestoassign)) {
        $success = $success && totara_assign_roles_cohort($rolestoassign, $cohortid, $memberids);
    }

    return $success;
}

/** Check if the current user $USER can see the learning component.
 *
 * @param string $type course or program
 * @param mixed $instance Instance object or int ID of the learning component
 * @param mixed $userid User's ID
 *
 * @return bool True if the user can see the learning component based on the audience visibility setting
 */
function check_access_audience_visibility($type, $instance, $userid = null) {
    global $CFG, $DB, $USER;

    if (!$CFG->audiencevisibility) {
        return true;
    }

    if (empty($userid)) {
        $userid = $USER->id;
    }

    // Checking type of the learning component.
    if ($type === 'course') {
        $table = 'course';
        $alias = 'c';
        $fieldid = 'c.id';
        $fieldvis = 'c.visible';
        $fieldaudvis = 'c.audiencevisible';
        $itemcontext = CONTEXT_COURSE;
    } else {
        $table = 'prog';
        $alias = 'p';
        $fieldid = 'p.id';
        $fieldvis = 'p.visible';
        $fieldaudvis = 'p.audiencevisible';
        $itemcontext = CONTEXT_PROGRAM;
    }

    // Checking the learning component object or ID.
    if (is_numeric($instance)) {
        $object = $DB->get_record($table, array('id' => $instance), MUST_EXIST);
    } else if (is_object($instance) and isset($instance->id)) {
        $object = $instance;
    } else {
        return false;
    }

    $params = array('itemcontext' => $itemcontext, 'instanceid' => $object->id);
    list($visibilitysql, $visibilityparams) = totara_visibility_where($userid,
                                                                      $fieldid,
                                                                      $fieldvis,
                                                                      $fieldaudvis,
                                                                      $alias,
                                                                      $type);
    $params = array_merge($params, $visibilityparams);

    $sql = "SELECT {$alias}.id
            FROM {{$table}} {$alias}
            LEFT JOIN {context} ctx
              ON {$alias}.id = ctx.instanceid AND contextlevel = :itemcontext
            WHERE {$alias}.id = :instanceid
              AND {$visibilitysql}";

    return $DB->record_exists_sql($sql, $params);

}

class totara_cohort_visible_learning_cohorts extends totara_cohort_course_cohorts {
    function build_visible_learning_table($instanceid, $instancetype, $readonly = false) {
        $this->headers = array(
            get_string('cohortname', 'totara_cohort'),
            get_string('type', 'totara_cohort'),
            get_string('numlearners', 'totara_cohort')
        );

        $items = totara_cohort_get_visible_learning($instanceid, $instancetype);

        if (!empty($items)) {
            foreach ($items as $item) {
                $this->data[] = $this->build_row($item, $readonly);
            }
        }
    }
}

class totara_cohort_course_cohorts
{
    function build_table($courseid) {
        $this->headers = array(
            get_string('cohortname','totara_cohort'),
            get_string('type','totara_cohort'),
            get_string('numlearners','totara_cohort')
        );

        // Go to the database and gets the assignments
        $items = totara_cohort_get_course_cohorts($courseid, null,
            'c.id, c.name AS fullname, c.cohorttype');

        // Convert these into html
        if (!empty($items)) {
            foreach ($items as $item) {
                $this->data[] = $this->build_row($item);
            }
        }
    }

    function build_row($item, $readonly = false) {
        global $OUTPUT;

        if (is_int($item)) {
            $item = $this->get_item($item);
        }

        $cohorttypes = cohort::getCohortTypes();
        $cohortstring = $cohorttypes[$item->cohorttype];

        $row = array();
        $delete = '';
        if (!$readonly) {
            $delete = html_writer::link('#', $OUTPUT->pix_icon('t/delete', get_string('delete')),
                      array('title' => get_string('delete'), 'class'=>'coursecohortdeletelink'));
        }
        $row[] = html_writer::start_tag('div', array('id' => 'cohort-item-'.$item->id, 'class' => 'item')) .
                 format_string($item->fullname) . $delete . html_writer::end_tag('div');

        $row[] = $cohortstring;
        $row[] = $this->user_affected_count($item);

        return $row;
    }

    function get_item($itemid) {
        global $DB;
        return $DB->get_record('cohort', array('id' => $itemid), 'id, name as fullname, cohorttype');
    }

    function user_affected_count($item) {
        return $this->get_affected_users($item, 0, true);
    }

    function get_affected_users($item, $userid=0, $count=false) {
        global $DB;
        $select = $count ? 'COUNT(u.id)' : 'u.id';
        $params = array();
        $sql = "SELECT $select
                FROM {cohort_members} AS cm
                INNER JOIN {user} AS u ON cm.userid=u.id
                WHERE cm.cohortid = ?
                AND u.deleted=0";
        $params[] = $item->id;
        if ($userid) {
            $sql .= " AND u.id = ?";
            $params[] = $userid;
        }

        if ($count) {
            $num = $DB->count_records_sql($sql, $params);
            return !$num ? 0 : $num;
        } else {
            return $DB->get_records_sql($sql);
        }
    }

    /**
     * Prints out the actual html
     *
     * @param bool $return
     * @param string $type Type of the table
     * @return string html
     */
    function display($return = false, $type = 'enrolled') {
        $html = '<div id="course-cohort-assignments">
            <div id="assignment_categories">
            <fieldset class="assignment_category cohorts">';

        $table = new html_table();
        $table->attributes = array('class' => 'generaltable');
        $table->id = 'course-cohorts-table-' . $type;
        $table->head = $this->headers;

        if (!empty($this->data)) {
            $table->data = $this->data;
        }

        $html .= html_writer::table($table);
        $html .= '</fieldset></div></div>';

        if ($return) {
            return $html;
        }
        echo $html;
    }
}
