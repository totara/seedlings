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
 * Cohort related management functions, this file needs to be included manually.
 *
 * @package    core_cohort
 * @copyright  2010 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/selector/lib.php');
require_once($CFG->dirroot.'/totara/cohort/lib.php');
require_once($CFG->dirroot.'/totara/cohort/rules/lib.php');

/**
 * Add new cohort.
 *
 * @param  stdClass $cohort
 * @param  boolean $addcollections indicate whether to add initial ruleset collections
 * @return int new cohort id
 */
function cohort_add_cohort($cohort, $addcollections=true) {
    global $DB, $USER;

    if (!isset($cohort->name)) {
        throw new coding_exception('Missing cohort name in cohort_add_cohort().');
    }
    if (!isset($cohort->idnumber)) {
        $cohort->idnumber = NULL;
    }
    if (!isset($cohort->description)) {
        $cohort->description = '';
    }
    if (!isset($cohort->descriptionformat)) {
        $cohort->descriptionformat = FORMAT_HTML;
    }
    if (empty($cohort->component)) {
        $cohort->component = '';
    }
    //todo: Fix this :)
    $cohort->active = 1;

    $cohort->timecreated = time();
    $cohort->timemodified = $cohort->timecreated;
    $cohort->modifierid = $USER->id;

    $cohort->id = $DB->insert_record('cohort', $cohort);
    $cohort = $DB->get_record('cohort', array('id' => $cohort->id));

    totara_cohort_increment_automatic_id($cohort->idnumber);

    if ($addcollections) {
        // Add initial collections
        $rulecol = new stdClass();
        $rulecol->cohortid = $cohort->id;
        $rulecol->status = COHORT_COL_STATUS_ACTIVE;
        $rulecol->timecreated = $rulecol->timemodified = $cohort->timecreated;
        $rulecol->modifierid = $USER->id;
        $activecolid = $DB->insert_record('cohort_rule_collections', $rulecol);

        unset($rulecol->id);
        $rulecol->status = COHORT_COL_STATUS_DRAFT_UNCHANGED;
        $draftcolid = $DB->insert_record('cohort_rule_collections', $rulecol);

        // Update cohort with new collections
        $cohortupdate = new stdClass;
        $cohortupdate->id = $cohort->id;
        $cohortupdate->activecollectionid = $cohort->activecollectionid = $activecolid;
        $cohortupdate->draftcollectionid = $cohort->draftcollectionid = $draftcolid;
        $DB->update_record('cohort', $cohortupdate);
    }

    $event = \core\event\cohort_created::create(array(
        'context' => context::instance_by_id($cohort->contextid),
        'objectid' => $cohort->id,
    ));
    $event->add_record_snapshot('cohort', $cohort);
    $event->trigger();

    return $cohort->id;
}

/**
 * Update existing cohort.
 * @param  stdClass $cohort
 * @return void
 */
function cohort_update_cohort($cohort) {
    global $DB, $USER;
    if (property_exists($cohort, 'component') and empty($cohort->component)) {
        // prevent NULLs
        $cohort->component = '';
    }
    if (isset($cohort->startdate) && empty($cohort->startdate)) {
        $cohort->startdate = null;
    }
    if (isset($cohort->enddate) && empty($cohort->enddate)) {
        $cohort->enddate = null;
    }

    //todo: Fix this :)
    $cohort->active = 1;

    $cohort->timemodified = time();
    $cohort->modifierid = $USER->id;
    $DB->update_record('cohort', $cohort);

    $event = \core\event\cohort_updated::create(array(
        'context' => context::instance_by_id($cohort->contextid),
        'objectid' => $cohort->id,
    ));
    $event->trigger();
}

/**
 * Delete cohort.
 * @param  stdClass $cohort
 * @return void
 */
function cohort_delete_cohort($cohort) {
    global $DB;

    if ($cohort->component) {
        // TODO: add component delete callback
    }
    $transaction = $DB->start_delegated_transaction();
    $DB->delete_records('cohort_members', array('cohortid' => $cohort->id));
    $DB->delete_records('cohort', array('id' => $cohort->id));

    $collections = $DB->get_records('cohort_rule_collections', array('cohortid' => $cohort->id));

    foreach ($collections as $collection) {
        // Delete all rulesets, all the rules of each ruleset, and all the params of each rule
        $rulesets = $DB->get_records('cohort_rulesets', array('rulecollectionid' => $collection->id));
        if ($rulesets) {
            foreach ($rulesets as $ruleset) {
                $rules = $DB->get_records('cohort_rules', array('rulesetid' => $ruleset->id));
                if ($rules) {
                    foreach ($rules as $rule) {
                        $DB->delete_records('cohort_rule_params', array('ruleid' => $rule->id));
                    }
                    $DB->delete_records('cohort_rules', array('rulesetid' => $ruleset->id));
                }
            }
        }
        $DB->delete_records('cohort_rulesets', array('rulecollectionid' => $collection->id));
    }
    $DB->delete_records('cohort_rule_collections', array('cohortid' => $cohort->id));

    //delete associations
    $associations = totara_cohort_get_associations($cohort->id);
    foreach ($associations as $ass) {
        totara_cohort_delete_association($cohort->id, $ass->id, $ass->type);
    }

    $transaction->allow_commit();

    $event = \core\event\cohort_deleted::create(array(
        'context' => context::instance_by_id($cohort->contextid),
        'objectid' => $cohort->id,
    ));
    $event->add_record_snapshot('cohort', $cohort);
    $event->trigger();
}

/**
 * Somehow deal with cohorts when deleting course category,
 * we can not just delete them because they might be used in enrol
 * plugins or referenced in external systems.
 * @param  stdClass|coursecat $category
 * @return void
 */
function cohort_delete_category($category) {
    global $DB;
    // TODO: make sure that cohorts are really, really not used anywhere and delete, for now just move to parent or system context

    $oldcontext = context_coursecat::instance($category->id);

    if ($category->parent and $parent = $DB->get_record('course_categories', array('id'=>$category->parent))) {
        $parentcontext = context_coursecat::instance($parent->id);
        $sql = "UPDATE {cohort} SET contextid = :newcontext WHERE contextid = :oldcontext";
        $params = array('oldcontext'=>$oldcontext->id, 'newcontext'=>$parentcontext->id);
    } else {
        $syscontext = context_system::instance();
        $sql = "UPDATE {cohort} SET contextid = :newcontext WHERE contextid = :oldcontext";
        $params = array('oldcontext'=>$oldcontext->id, 'newcontext'=>$syscontext->id);
    }

    $DB->execute($sql, $params);
}

/**
 * Add cohort member
 * @param  int $cohortid
 * @param  int $userid
 * @return bool
 */
function cohort_add_member($cohortid, $userid) {
    global $DB;
    if ($DB->record_exists('cohort_members', array('cohortid'=>$cohortid, 'userid'=>$userid))) {
        // No duplicates!
        return false;
    }
    $record = new stdClass();
    $record->cohortid  = $cohortid;
    $record->userid    = $userid;
    $record->timeadded = time();
    $DB->insert_record('cohort_members', $record);

    $cohort = $DB->get_record('cohort', array('id' => $cohortid), '*', MUST_EXIST);

    $event = \core\event\cohort_member_added::create(array(
        'context' => context::instance_by_id($cohort->contextid),
        'objectid' => $cohortid,
        'relateduserid' => $userid,
    ));
    $event->add_record_snapshot('cohort', $cohort);
    $event->trigger();
}

/**
 * Remove cohort member
 * @param  int $cohortid
 * @param  int $userid
 * @return void
 */
function cohort_remove_member($cohortid, $userid) {
    global $DB;
    $DB->delete_records('cohort_members', array('cohortid'=>$cohortid, 'userid'=>$userid));

    $cohort = $DB->get_record('cohort', array('id' => $cohortid), '*', MUST_EXIST);

    $event = \core\event\cohort_member_removed::create(array(
        'context' => context::instance_by_id($cohort->contextid),
        'objectid' => $cohortid,
        'relateduserid' => $userid,
    ));
    $event->add_record_snapshot('cohort', $cohort);
    $event->trigger();
}

/**
 * Is this user a cohort member?
 * @param int $cohortid
 * @param int $userid
 * @return bool
 */
function cohort_is_member($cohortid, $userid) {
    global $DB;

    return $DB->record_exists('cohort_members', array('cohortid'=>$cohortid, 'userid'=>$userid));
}

/**
 * Returns list of cohorts from course parent contexts.
 *
 * Note: this function does not implement any capability checks,
 *       it means it may disclose existence of cohorts,
 *       make sure it is displayed to users with appropriate rights only.
 *
 * @param  stdClass $course
 * @param  bool $onlyenrolled true means include only cohorts with enrolled users
 * @return array of cohort names with number of enrolled users
 */
function cohort_get_visible_list($course, $onlyenrolled=true) {
    global $DB;

    $context = context_course::instance($course->id);
    list($esql, $params) = get_enrolled_sql($context);
    list($parentsql, $params2) = $DB->get_in_or_equal($context->get_parent_context_ids(), SQL_PARAMS_NAMED);
    $params = array_merge($params, $params2);

    if ($onlyenrolled) {
        $left = "";
        $having = "HAVING COUNT(u.id) > 0";
    } else {
        $left = "LEFT";
        $having = "";
    }

    $sql = "SELECT c.id, c.name, c.contextid, c.idnumber, COUNT(u.id) AS cnt
              FROM {cohort} c
        $left JOIN ({cohort_members} cm
                   JOIN ($esql) u ON u.id = cm.userid) ON cm.cohortid = c.id
             WHERE c.contextid $parentsql
          GROUP BY c.id, c.name, c.contextid, c.idnumber
           $having
          ORDER BY c.name, c.idnumber";

    $cohorts = $DB->get_records_sql($sql, $params);

    foreach ($cohorts as $cid=>$cohort) {
        $cohorts[$cid] = format_string($cohort->name, true, array('context'=>$cohort->contextid));
        if ($cohort->cnt) {
            $cohorts[$cid] .= ' (' . $cohort->cnt . ')';
        }
    }

    return $cohorts;
}

/**
 * Get all the cohorts defined in given context.
 *
 * @param int $contextid
 * @param int $page number of the current page
 * @param int $perpage items per page
 * @param string $search search string
 * @return array    Array(totalcohorts => int, cohorts => array, allcohorts => int)
 */
function cohort_get_cohorts($contextid, $page = 0, $perpage = 25, $search = '') {
    global $DB;

    // Add some additional sensible conditions
    $tests = array();
    $params = array();
    if ($contextid) {
        $tests = array('contextid = ?');
        $params = array($contextid);
    }

    if (!empty($search)) {
        $conditions = array('name', 'idnumber', 'description');
        $searchparam = '%' . $DB->sql_like_escape($search) . '%';
        foreach ($conditions as $key=>$condition) {
            $conditions[$key] = $DB->sql_like($condition, "?", false);
            $params[] = $searchparam;
        }
        $tests[] = '(' . implode(' OR ', $conditions) . ')';
    }
    $wherecondition = implode(' AND ', $tests);

    $fields = "SELECT *";
    $countfields = "SELECT COUNT(1)";
    $sql = " FROM {cohort}
             WHERE $wherecondition";
    $order = " ORDER BY name ASC, idnumber ASC";
    $totalcohorts = $DB->count_records_sql($countfields . $sql, $params);
    $allcohorts = $DB->count_records('cohort', array('contextid'=>$contextid));
    $cohorts = $DB->get_records_sql($fields . $sql . $order, $params, $page*$perpage, $perpage);

    return array('totalcohorts' => $totalcohorts, 'cohorts' => $cohorts, 'allcohorts'=>$allcohorts);
}


/**
 * Print the tabs for an individual cohort
 * @param $currenttab string view, edit, viewmembers, editmembers, visiblelearning, enrolledlearning
 * @param $cohortid int
 * @param $cohorttype int
 */
function cohort_print_tabs($currenttab, $cohortid, $cohorttype, $cohort) {
    global $CFG;

    if ($cohort && totara_cohort_is_active($cohort)) {
        print html_writer::tag('div', '', array('class' => 'plan_box', 'style' => 'display:none;'));
    } else {
        if ($cohort->startdate && $cohort->startdate > time()) {
            $message = get_string('cohortmsgnotyetstarted', 'totara_cohort', userdate($cohort->startdate, get_string('strfdateshortmonth', 'langconfig')));
        }
        if ($cohort->enddate && $cohort->enddate < time()) {
            $message = get_string('cohortmsgalreadyended', 'totara_cohort', userdate($cohort->enddate, get_string('strfdateshortmonth', 'langconfig')));
        }
        print html_writer::tag('div', html_writer::tag('p', $message), array('class' => 'plan_box notifymessage clearfix'));
    }

    // Setup the top row of tabs
    $inactive = NULL;
    $activetwo = NULL;
    $toprow = array();
    $systemcontext = context_system::instance();
    $canmanage = has_capability('moodle/cohort:manage', $systemcontext);
    $canmanagerules = has_capability('totara/cohort:managerules', $systemcontext);
    $canmanagevisibility = has_capability('totara/coursecatalog:manageaudiencevisibility', $systemcontext);
    $canassign = has_capability('moodle/cohort:assign', $systemcontext);
    $canassignroles = has_capability('moodle/role:assign', $systemcontext);
    $canview = has_capability('moodle/cohort:view', $systemcontext);

    if ($canview) {

        $toprow[] = new tabobject('view', new moodle_url('/cohort/view.php', array('id' => $cohortid)),
                    get_string('overview','totara_cohort'));
    }

    if ($canmanage) {
        $toprow[] = new tabobject('edit', new moodle_url('/cohort/edit.php', array('id' => $cohortid)),
                    get_string('editdetails','totara_cohort'));
    }

    if ($canmanagerules && $cohorttype == cohort::TYPE_DYNAMIC) {
        $toprow[] = new tabobject(
            'editrules',
            new moodle_url('/totara/cohort/rules.php', array('id' => $cohortid)),
            get_string('editrules','totara_cohort')
        );
    }

    if ($canview) {
        $toprow[] = new tabobject('viewmembers', new moodle_url('/cohort/members.php', array('id' => $cohortid)),
            get_string('viewmembers','totara_cohort'));
    }

    if ($canassign && $cohorttype == cohort::TYPE_STATIC) {
        $toprow[] = new tabobject('editmembers', new moodle_url('/cohort/assign.php', array('id' => $cohortid)),
            get_string('editmembers','totara_cohort'));
    }

    if ($canview && $canmanage) {
        $toprow[] = new tabobject('enrolledlearning', new moodle_url('/totara/cohort/enrolledlearning.php', array('id' => $cohortid)),
            get_string('enrolledlearning', 'totara_cohort'));
    }

    if (!empty($CFG->audiencevisibility) && $canmanagevisibility) {
        $toprow[] = new tabobject('visiblelearning', new moodle_url('/totara/cohort/visiblelearning.php', array('id' => $cohortid)),
            get_string('visiblelearning', 'totara_cohort'));
    }

    if (totara_feature_visible('learningplans') && $canmanage) {
        $toprow[] = new tabobject('plans', new moodle_url('/totara/cohort/learningplan.php', array('id' => $cohortid)),
            get_string('learningplan', 'totara_cohort'));
    }

    if (totara_feature_visible('goals') && $canmanage) {
        $toprow[] = new tabobject('goals', new moodle_url('/totara/cohort/goals.php', array('id' => $cohortid)),
            get_string('goals', 'totara_hierarchy'));
    }

    if ($canassignroles) {
        $toprow[] = new tabobject('roles', new moodle_url('/totara/cohort/assignroles.php', array('id' => $cohortid)),
            get_string('assignroles', 'totara_cohort'));
    }

    $tabs = array($toprow);
    return print_tabs($tabs, $currenttab, $inactive, $activetwo, true);
}
