<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Local stuff for cohort enrolment plugin.
 *
 * @package    enrol_cohort
 * @copyright  2010 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->dirroot . '/totara/cohort/lib.php');
require_once($CFG->dirroot . '/group/lib.php');


/**
 * Event handler for cohort enrolment plugin.
 *
 * We try to keep everything in sync via listening to events,
 * it may fail sometimes, so we always do a full sync in cron too.
 */
class enrol_cohort_handler {
    /**
     * Event processor - cohort member added.
     * @param \core\event\cohort_member_added $event
     * @return bool
     */
    public static function member_added(\core\event\cohort_member_added $event) {
        global $DB, $CFG;
        require_once("$CFG->dirroot/group/lib.php");

        if (!enrol_is_enabled('cohort')) {
            return true;
        }

        // Does any enabled cohort instance want to sync with this cohort?
        $sql = "SELECT e.*, r.id as roleexists
                  FROM {enrol} e
             LEFT JOIN {role} r ON (r.id = e.roleid)
                 WHERE e.customint1 = :cohortid AND e.enrol = 'cohort'
              ORDER BY e.id ASC";
        if (!$instances = $DB->get_records_sql($sql, array('cohortid'=>$event->objectid))) {
            return true;
        }

        $plugin = enrol_get_plugin('cohort');
        foreach ($instances as $instance) {
            if ($instance->status != ENROL_INSTANCE_ENABLED ) {
                // No roles for disabled instances.
                $instance->roleid = 0;
            } else if ($instance->roleid and !$instance->roleexists) {
                // Invalid role - let's just enrol, they will have to create new sync and delete this one.
                $instance->roleid = 0;
            }
            unset($instance->roleexists);
            // No problem if already enrolled.
            $plugin->enrol_user($instance, $event->relateduserid, $instance->roleid, 0, 0, ENROL_USER_ACTIVE);

            // Sync groups.
            if ($instance->customint2) {
                if (!groups_is_member($instance->customint2, $event->relateduserid)) {
                    if ($group = $DB->get_record('groups', array('id'=>$instance->customint2, 'courseid'=>$instance->courseid))) {
                        groups_add_member($group->id, $event->relateduserid, 'enrol_cohort', $instance->id);
                    }
                }
            }
        }

        return true;
    }

    /**
     * Event processor - cohort member removed.
     * @param \core\event\cohort_member_removed $event
     * @return bool
     */
    public static function member_removed(\core\event\cohort_member_removed $event) {
        global $DB;

        // Does anything want to sync with this cohort?
        if (!$instances = $DB->get_records('enrol', array('customint1'=>$event->objectid, 'enrol'=>'cohort'), 'id ASC')) {
            return true;
        }

        $plugin = enrol_get_plugin('cohort');
        $unenrolaction = $plugin->get_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);

        foreach ($instances as $instance) {
            if (!$ue = $DB->get_record('user_enrolments', array('enrolid'=>$instance->id, 'userid'=>$event->relateduserid))) {
                continue;
            }
            if ($unenrolaction == ENROL_EXT_REMOVED_UNENROL) {
                $plugin->unenrol_user($instance, $event->relateduserid);

            } else {
                if ($ue->status != ENROL_USER_SUSPENDED) {
                    $plugin->update_user_enrol($instance, $ue->userid, ENROL_USER_SUSPENDED);
                    $context = context_course::instance($instance->courseid);
                    role_unassign_all(array('userid'=>$ue->userid, 'contextid'=>$context->id, 'component'=>'enrol_cohort', 'itemid'=>$instance->id));
                }
            }
        }

        return true;
    }

    /**
     * Event processor - cohort deleted.
     * @param \core\event\cohort_deleted $event
     * @return bool
     */
    public static function deleted(\core\event\cohort_deleted $event) {
        global $DB;

        // Does anything want to sync with this cohort?
        if (!$instances = $DB->get_records('enrol', array('customint1'=>$event->objectid, 'enrol'=>'cohort'), 'id ASC')) {
            return true;
        }

        $plugin = enrol_get_plugin('cohort');
        $unenrolaction = $plugin->get_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);

        foreach ($instances as $instance) {
            if ($unenrolaction == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
                $context = context_course::instance($instance->courseid);
                role_unassign_all(array('contextid'=>$context->id, 'component'=>'enrol_cohort', 'itemid'=>$instance->id));
                $plugin->update_status($instance, ENROL_INSTANCE_DISABLED);
            } else {
                $plugin->delete_instance($instance);
            }
        }

        return true;
    }
}


/**
 * Sync all cohort course links.
 * @param progress_trace $trace
 * @param int $courseid one course, empty mean all
 * @return int 0 means ok, 1 means error, 2 means plugin disabled
 */
function enrol_cohort_sync(progress_trace $trace, $courseid = NULL) {
    global $CFG, $DB;
    require_once("$CFG->dirroot/group/lib.php");

    // Purge all roles if cohort sync disabled, those can be recreated later here by cron or CLI.
    if (!enrol_is_enabled('cohort')) {
        $trace->output('Cohort sync plugin is disabled, unassigning all plugin roles and stopping.');
        role_unassign_all(array('component'=>'enrol_cohort'));
        return 2;
    }

    // Unfortunately this may take a long time, this script can be interrupted without problems.
    core_php_time_limit::raise();
    raise_memory_limit(MEMORY_HUGE);

    // Ensure dynamic cohorts are up to date before starting.
    totara_cohort_check_and_update_dynamic_cohort_members($courseid, $trace);

    $trace->output('Starting user enrolment synchronisation...');

    $allroles = get_all_roles();

    $plugin = enrol_get_plugin('cohort');
    $unenrolaction = $plugin->get_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);


    // Iterate through all not enrolled yet users.
    $onecourse = $courseid ? "AND e.courseid = :courseid" : "";

    // get enrol instances where peeps need to be enrolled
    $sql = "SELECT DISTINCT e.id
              FROM {cohort_members} cm
              JOIN {enrol} e ON (e.customint1 = cm.cohortid AND e.status = :statusenabled AND e.enrol = 'cohort' $onecourse)
              JOIN {user} u ON (u.id = cm.userid AND u.deleted = 0)
         LEFT JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = cm.userid)
             WHERE ue.id IS NULL OR ue.status = :suspended";
    $params = array();
    $params['courseid'] = $courseid;
    $params['statusenabled'] = ENROL_INSTANCE_ENABLED;
    $params['suspended'] = ENROL_USER_SUSPENDED;
    $rseids = $DB->get_recordset_sql($sql, $params);

    // enrol the necessary users in the enrol instances
    foreach ($rseids as $enrol) {
        $sql = "SELECT DISTINCT cm.userid, ue.status
                  FROM {cohort_members} cm
                  JOIN {enrol} e ON (e.customint1 = cm.cohortid AND e.status = :statusenabled AND e.enrol = 'cohort' $onecourse)
             LEFT JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = cm.userid)
                 JOIN {user} u ON (u.id = cm.userid AND u.deleted = 0)
             WHERE e.id = :enrolid AND ue.id IS NULL";
        $params['enrolid'] = $enrol->id;
        $rsuserids = $DB->get_recordset_sql($sql, $params);

        $instance = $DB->get_record('enrol', array('id' => $enrol->id));

        $uecount = 0;
        $ue = array();
        foreach ($rsuserids as $u) {
            if ($u->status == ENROL_USER_SUSPENDED) {
                // TODO We are not bulk unsuspending users yet.
                $plugin->update_user_enrol($instance, $u->userid, ENROL_USER_ACTIVE);
                $trace->output("  unsuspending: $u->userid ==> $instance->courseid via cohort $instance->customint1");
            } else {
                $ue[] = $u;
                $uecount++;
                if ($uecount == BATCH_INSERT_MAX_ROW_COUNT) {
                    // bulk enrol in batches
                    $plugin->enrol_user_bulk($instance, $ue, $instance->roleid);
                    $uecount = 0;
                    $ue = array();
                }
            }
        }
        if (!empty($ue)) {
            // enrol remaining batch
            $plugin->enrol_user_bulk($instance, $ue, $instance->roleid);
            unset($ue);
        }

        $rsuserids->close();
    }
    $rseids->close();

    // get enrol instances where peeps need to be unenrolled
    $sql = "SELECT DISTINCT e.id
              FROM {user_enrolments} ue
              JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'cohort' $onecourse)
         LEFT JOIN {cohort_members} cm ON (cm.cohortid  = e.customint1 AND cm.userid = ue.userid)
             WHERE cm.id IS NULL";
    $params = array();
    $params['courseid'] = $courseid;
    $rseids = $DB->get_recordset_sql($sql, $params);

    // unenrol the necessary users from the enrol instances
    foreach ($rseids as $enrol) {
        // unenrol as necessary
        $sql = "SELECT DISTINCT ue.*
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'cohort' $onecourse)
             LEFT JOIN {cohort_members} cm ON (cm.cohortid = e.customint1 AND cm.userid = ue.userid)
                 WHERE e.id = :enrolid AND cm.id IS NULL";
        $params['enrolid'] = $enrol->id;
        $rsuserids = $DB->get_recordset_sql($sql, $params);

        $instance = $DB->get_record('enrol', array('id' => $enrol->id));

        $uuecount = 0;
        $uue = array();
        foreach ($rsuserids as $ue) {
            if ($unenrolaction == ENROL_EXT_REMOVED_UNENROL) {
                // remove enrolment together with group membership, grades, preferences, etc.
                $uue[] = $ue->userid;
                $uuecount++;
                if ($uuecount == BATCH_INSERT_MAX_ROW_COUNT) {
                    // bulk unenrol in batches
                    $plugin->unenrol_user_bulk($instance, $uue);
                    $uuecount = 0;
                    $uue = array();
                }
            } else { // ENROL_EXT_REMOVED_SUSPENDNOROLES
                // TODO no bulk action for this mode yet
                // just disable and ignore any changes
                if ($ue->status != ENROL_USER_SUSPENDED) {
                    $plugin->update_user_enrol($instance, $ue->userid, ENROL_USER_SUSPENDED);
                    $context = context_course::instance($instance->courseid);
                    role_unassign_all(array('userid'=>$ue->userid, 'contextid'=>$context->id, 'component'=>'enrol_cohort', 'itemid'=>$instance->id));
                    $trace->output("  suspending and unassigning all roles: $ue->userid ==> $instance->courseid");
                }
            }
        }
        if (!empty($uue)) {
            // enrol remaining batch
            $plugin->unenrol_user_bulk($instance, $uue);
            unset($uue);
        }

        $rsuserids->close();
    }
    $rseids->close();

    // remove unwanted roles - sync role can not be changed, we only remove role when unenrolled
    $onecourse = $courseid ? "AND e.courseid = :courseid" : "";
    $sql = "SELECT ra.roleid, ra.userid, ra.contextid, ra.itemid, e.courseid
              FROM {role_assignments} ra
              JOIN {context} c ON (c.id = ra.contextid AND c.contextlevel = :coursecontext)
              JOIN {enrol} e ON (e.id = ra.itemid AND e.enrol = 'cohort' $onecourse)
         LEFT JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = ra.userid AND ue.status = :useractive)
             WHERE ra.component = 'enrol_cohort' AND (ue.id IS NULL OR e.status <> :statusenabled)";
    $params = array();
    $params['statusenabled'] = ENROL_INSTANCE_ENABLED;
    $params['useractive'] = ENROL_USER_ACTIVE;
    $params['coursecontext'] = CONTEXT_COURSE;
    $params['courseid'] = $courseid;


    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $ra) {
        role_unassign($ra->roleid, $ra->userid, $ra->contextid, 'enrol_cohort', $ra->itemid);
        $trace->output("  unassigning role: $ra->userid ==> $ra->courseid as ".$allroles[$ra->roleid]->shortname);
    }
    $rs->close();


    // Finally sync groups.
    // TODO Add bulk version of group syncing
    $onecourse = $courseid ? "AND e.courseid = :courseid" : "";

    // Remove invalid.
    $sql = "SELECT gm.*, e.courseid, g.name AS groupname
              FROM {groups_members} gm
              JOIN {groups} g ON (g.id = gm.groupid)
              JOIN {enrol} e ON (e.enrol = 'cohort' AND e.courseid = g.courseid $onecourse)
              JOIN {user_enrolments} ue ON (ue.userid = gm.userid AND ue.enrolid = e.id)
             WHERE gm.component='enrol_cohort' AND gm.itemid = e.id AND g.id <> e.customint2";
    $params = array();
    $params['courseid'] = $courseid;

    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $gm) {
        groups_remove_member($gm->groupid, $gm->userid);
        $trace->output("  removing user from group: $gm->userid ==> $gm->courseid - $gm->groupname");
    }
    $rs->close();

    // Add missing.
    $sql = "SELECT ue.*, g.id AS groupid, e.courseid, g.name AS groupname
              FROM {user_enrolments} ue
              JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'cohort' $onecourse)
              JOIN {groups} g ON (g.courseid = e.courseid AND g.id = e.customint2)
              JOIN {user} u ON (u.id = ue.userid AND u.deleted = 0)
         LEFT JOIN {groups_members} gm ON (gm.groupid = g.id AND gm.userid = ue.userid)
             WHERE gm.id IS NULL";
    $params = array();
    $params['courseid'] = $courseid;

    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $ue) {
        groups_add_member($ue->groupid, $ue->userid, 'enrol_cohort', $ue->enrolid);
        $trace->output("  adding user to group: $ue->userid ==> $ue->courseid - $ue->groupname");
     }
     $rs->close();


    // Program cohort memberships will be handled by the programs cron ;)

    // Delete any stale memberships due to deleted cohort(s)
    $trace->output('removing user memberships for deleted cohorts...');
    totara_cohort_delete_stale_memberships();


    $trace->output('...user enrolment synchronisation finished.');

    return 0;
}

/**
 * Enrols all of the users in a cohort through a manual plugin instance.
 *
 * In order for this to succeed the course must contain a valid manual
 * enrolment plugin instance that the user has permission to enrol users through.
 *
 * @global moodle_database $DB
 * @param course_enrolment_manager $manager
 * @param int $cohortid
 * @param int $roleid
 * @return int
 */
function enrol_cohort_enrol_all_users(course_enrolment_manager $manager, $cohortid, $roleid) {
    global $DB;
    $context = $manager->get_context();
    require_capability('moodle/course:enrolconfig', $context);

    $instance = false;
    $instances = $manager->get_enrolment_instances();
    foreach ($instances as $i) {
        if ($i->enrol == 'manual') {
            $instance = $i;
            break;
        }
    }
    $plugin = enrol_get_plugin('manual');
    if (!$instance || !$plugin || !$plugin->allow_enrol($instance) || !has_capability('enrol/'.$plugin->get_name().':enrol', $context)) {
        return false;
    }
    $sql = "SELECT com.userid
              FROM {cohort_members} com
         LEFT JOIN (
                SELECT *
                  FROM {user_enrolments} ue
                 WHERE ue.enrolid = :enrolid
                 ) ue ON ue.userid=com.userid
             WHERE com.cohortid = :cohortid AND ue.id IS NULL";
    $params = array('cohortid' => $cohortid, 'enrolid' => $instance->id);
    $rs = $DB->get_recordset_sql($sql, $params);
    $count = 0;
    foreach ($rs as $user) {
        $count++;
        $plugin->enrol_user($instance, $user->userid, $roleid);
    }
    $rs->close();
    return $count;
}

/**
 * Gets all the cohorts the user is able to view.
 *
 * @global moodle_database $DB
 * @param course_enrolment_manager $manager
 * @return array
 */
function enrol_cohort_get_cohorts(course_enrolment_manager $manager) {
    global $DB;
    $context = $manager->get_context();
    $cohorts = array();
    $instances = $manager->get_enrolment_instances();
    $enrolled = array();
    foreach ($instances as $instance) {
        if ($instance->enrol == 'cohort') {
            $enrolled[] = $instance->customint1;
        }
    }
    list($sqlparents, $params) = $DB->get_in_or_equal($context->get_parent_context_ids());
    $sql = "SELECT id, name, idnumber, contextid
              FROM {cohort}
             WHERE contextid $sqlparents
          ORDER BY name ASC, idnumber ASC";
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $c) {
        $context = context::instance_by_id($c->contextid);
        if (!has_capability('moodle/cohort:view', $context)) {
            continue;
        }
        $cohorts[$c->id] = array(
            'cohortid'=>$c->id,
            'name'=>format_string($c->name, true, array('context'=>context::instance_by_id($c->contextid))),
            'users'=>$DB->count_records('cohort_members', array('cohortid'=>$c->id)),
            'enrolled'=>in_array($c->id, $enrolled)
        );
    }
    $rs->close();
    return $cohorts;
}

/**
 * Check if cohort exists and user is allowed to enrol it.
 *
 * @global moodle_database $DB
 * @param int $cohortid Cohort ID
 * @return boolean
 */
function enrol_cohort_can_view_cohort($cohortid) {
    global $DB;
    $cohort = $DB->get_record('cohort', array('id' => $cohortid), 'id, contextid');
    if ($cohort) {
        $context = context::instance_by_id($cohort->contextid);
        if (has_capability('moodle/cohort:view', $context)) {
            return true;
        }
    }
    return false;
}

/**
 * Gets cohorts the user is able to view.
 *
 * @global moodle_database $DB
 * @param course_enrolment_manager $manager
 * @param int $offset limit output from
 * @param int $limit items to output per load
 * @param string $search search string
 * @return array    Array(more => bool, offset => int, cohorts => array)
 */
function enrol_cohort_search_cohorts(course_enrolment_manager $manager, $offset = 0, $limit = 25, $search = '') {
    global $DB;
    $context = $manager->get_context();
    $cohorts = array();
    $instances = $manager->get_enrolment_instances();
    $enrolled = array();
    foreach ($instances as $instance) {
        if ($instance->enrol == 'cohort') {
            $enrolled[] = $instance->customint1;
        }
    }

    list($sqlparents, $params) = $DB->get_in_or_equal($context->get_parent_context_ids());

    // Add some additional sensible conditions.
    $tests = array('contextid ' . $sqlparents);

    // Modify the query to perform the search if required.
    if (!empty($search)) {
        $conditions = array(
            'name',
            'idnumber',
            'description'
        );
        $searchparam = '%' . $DB->sql_like_escape($search) . '%';
        foreach ($conditions as $key=>$condition) {
            $conditions[$key] = $DB->sql_like($condition, "?", false);
            $params[] = $searchparam;
        }
        $tests[] = '(' . implode(' OR ', $conditions) . ')';
    }
    $wherecondition = implode(' AND ', $tests);

    $sql = "SELECT id, name, idnumber, contextid, description
              FROM {cohort}
             WHERE $wherecondition
          ORDER BY name ASC, idnumber ASC";
    $rs = $DB->get_recordset_sql($sql, $params, $offset);

    // Produce the output respecting parameters.
    foreach ($rs as $c) {
        // Track offset.
        $offset++;
        // Check capabilities.
        $context = context::instance_by_id($c->contextid);
        if (!has_capability('moodle/cohort:view', $context)) {
            continue;
        }
        if ($limit === 0) {
            // We have reached the required number of items and know that there are more, exit now.
            $offset--;
            break;
        }
        $cohorts[$c->id] = array(
            'cohortid' => $c->id,
            'name'     => shorten_text(format_string($c->name, true, array('context'=>context::instance_by_id($c->contextid))), 35),
            'users'    => $DB->count_records('cohort_members', array('cohortid'=>$c->id)),
            'enrolled' => in_array($c->id, $enrolled)
        );
        // Count items.
        $limit--;
    }
    $rs->close();
    return array('more' => !(bool)$limit, 'offset' => $offset, 'cohorts' => $cohorts);
}
