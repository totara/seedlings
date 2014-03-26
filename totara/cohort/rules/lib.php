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
 * @subpackage cohort/rules
 */
/**
 * This file contains library functions relating to dynamic cohort rules
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->dirroot.'/cohort/lib.php');
require_once($CFG->dirroot.'/totara/cohort/rules/settings.php');


define('COHORT_RULES_OP_AND', 0);
define('COHORT_RULES_OP_OR', 10);
global $COHORT_RULES_OP;
$COHORT_RULES_OP = array(
    COHORT_RULES_OP_AND => 'and',
    COHORT_RULES_OP_OR => 'or'
);

define('COHORT_RULES_OP_IN_NOTEQUAL', 0);
define('COHORT_RULES_OP_IN_EQUAL', 1);

define('COHORT_RULES_OP_IN_CONTAINS', 0);
define('COHORT_RULES_OP_IN_NOTCONTAIN', 1);
define('COHORT_RULES_OP_IN_ISEQUALTO', 2);
define('COHORT_RULES_OP_IN_STARTSWITH', 3);
define('COHORT_RULES_OP_IN_ENDSWITH', 4);
define('COHORT_RULES_OP_IN_ISEMPTY', 5);
define('COHORT_RULES_OP_IN_NOTEQUALTO', 6);

global $COHORT_RULES_OP_IN_LIST;
global $COHORT_RULES_OP_IN;

$COHORT_RULES_OP_IN = array(
    COHORT_RULES_OP_IN_EQUAL    => 'equal',
    COHORT_RULES_OP_IN_NOTEQUAL => 'notequal',
);

$COHORT_RULES_OP_IN_LIST = array(
    COHORT_RULES_OP_IN_CONTAINS   => get_string('contains', 'totara_cohort'),
    COHORT_RULES_OP_IN_NOTCONTAIN => get_string('doesnotcontain', 'totara_cohort'),
    COHORT_RULES_OP_IN_ISEQUALTO  => get_string('isequalto', 'totara_cohort'),
    COHORT_RULES_OP_IN_STARTSWITH => get_string('startswith', 'totara_cohort'),
    COHORT_RULES_OP_IN_ENDSWITH   => get_string('endswith', 'totara_cohort'),
    COHORT_RULES_OP_IN_ISEMPTY    => get_string('isempty', 'totara_cohort'),
    COHORT_RULES_OP_IN_NOTEQUALTO => get_string('isnotequalto', 'totara_cohort'),
);

define('COHORT_RULES_UI_MENU_LIMIT', 2500);

/**
 * Get the definition of a specific rule
 * @param string $rulegroup
 * @param string $rulename
 * @return cohort_rule_option or false if not found
 */
function cohort_rules_get_rule_definition($rulegroup, $rulename) {
    $rulelist = cohort_rules_list(false);
    if (array_key_exists($rulegroup, $rulelist) && array_key_exists($rulename, $rulelist[$rulegroup])) {
        return $rulelist[$rulegroup][$rulename];
    } else {
        return false;
    }
}


/**
 * Get the list of available cohort rule types as an option suitable for a menu
 */
function cohort_rules_get_menu_options() {
    static $rulesmenu = false;

    if (!$rulesmenu) {
        $rules = cohort_rules_list();

        // Set up the list of rules for use in menus.
        $rulesmenu = array();
        foreach ($rules as $groupname => $group) {
            $curlabel = get_string("rulegroup-{$groupname}", 'totara_cohort');
            $rulesmenu[$curlabel] = array();

            foreach ($group as $typename => $option) {
                if (!$option->hiddenfrommenu) {
                    $rulesmenu[$curlabel]["{$groupname}-{$typename}"] = $option->getLabel();
                }
            }
        }
        $rulesmenu = array_merge(
            array(
                get_string('new') => array(
                    '' => get_string('addrule', 'totara_cohort')
                )
            ),
            $rulesmenu
        );
    }

    return $rulesmenu;
}


/**
 * Create a cohort ruleset
 * @param int $cohortid The id of the cohort to link it to
 * @return int The ID of the newly created ruleset
 */
function cohort_rule_create_ruleset($collectionid) {
    global $USER, $DB;

    $todb = new stdClass();
    $todb->rulecollectionid = $collectionid;
    $todb->operator = COHORT_RULES_OP_AND;
    $todb->sortorder = $DB->get_field(
        'cohort_rulesets',
        '(CASE WHEN MAX(sortorder) IS NULL THEN 0 ELSE MAX(sortorder) END) + 1',
        array('rulecollectionid' => $collectionid));
    $todb->name = get_string('rulesetname', 'totara_cohort', $todb->sortorder);
    $todb->timecreated = $todb->timemodified = time();
    $todb->modifierid = $USER->id;

    return $DB->insert_record('cohort_rulesets', $todb);
}


/**
 * Create a new cohort rule (with no params yet)
 *
 * Note that this creates an "empty" rule without any of the params filled in. I haven't
 * yet written a function to create a new rule with params because I don't have to do that
 * very often. But if you should need to create a new rule with its params filled in, here's
 * what to do:
 *
 * 1. Use this function to create the empty rule
 * 2. Use cohort_rules_get_definition() to get the rule's definition
 * 3. Pull the sqlhandler from the definition ($sqlhandler = $def->sqlhandler;)
 * 4. Assign the params into the sqlhandler ($sqlhandler->param1 = 'foo';)
 * 5. Do $sqlhandler->write($ruleid) to persist the params
 *
 * @param int $rulesetid The ID of the ruleset it goes in
 * @param string $type The rule's group in the rule definition list
 * @param string $name The rule's type name in the rule definition list
 */
function cohort_rule_create_rule($rulesetid, $type, $name) {
    global $USER, $DB;

    $todb = new stdClass();
    $todb->rulesetid = $rulesetid;
    $todb->ruletype = $type;
    $todb->name = $name;
    $todb->sortorder = $DB->get_field('cohort_rules', '(case when max(sortorder) is null then 0 else max(sortorder) end) + 1', array('rulesetid' => $rulesetid));
    $todb->timecreated = $todb->timemodified = time();
    $todb->modifierid = $USER->id;

    return $DB->insert_record('cohort_rules', $todb);
}


/**
 * Take a rule's description and wrap it in the HTML it needs to fit into the rules form
 * @param int $ruleid
 * @param string $description
 * @param string $rulegroup
 * @param string $rulename
 * @param boolean $first
 * @param string $operator
 * @param boolean $brokenrule
 * @return string
 */
function cohort_rule_form_html($ruleid, $description, $rulegroup = '', $rulename = '', $first = false, $operator = '',
                               $brokenrule = false) {
    global $CFG, $OUTPUT;

    $strdelete = get_string('deleterule', 'totara_cohort');
    $stredit = get_string('editrule', 'totara_cohort');

    if ($first) {
        $opstr = '&nbsp;';
    } else {
        switch ($operator) {
            case COHORT_RULES_OP_OR:
                $opstr = get_string('or', 'totara_cohort');
                break;
            case COHORT_RULES_OP_AND:
                $opstr = get_string('and', 'totara_cohort');
                break;
            default:
                $opstr = $operator;
        }
    }
    $return = html_writer::start_tag('tr');
    $return .= html_writer::tag('td', $opstr, array('class' => 'operator'));
    $return .= html_writer::start_tag('td', array('class' => 'rule'));
    $return .= html_writer::start_tag('div', array('id' => 'ruledef'.$ruleid, 'class' => 'fitem ruledef'));
    $return .= $description;
    if (!$brokenrule) {
        $image = html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('t/edit'), 'alt' => $stredit, 'class'=>'iconsmall'));
        $url = new moodle_url($CFG->wwwroot . '/totara/cohort/rules/ruledetail.php', array('type' => 'rule', 'id' => $ruleid));
        $return .= html_writer::link($url, $image, array('title' => $stredit, 'class' => 'ruledef-edit',
                                                                               'data-ruletype' => $rulegroup . '-' . $rulename,
                                                                               'data-ruleid' => $ruleid));
    }
    $image = html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('t/delete'), 'alt' => $strdelete, 'class'=>'iconsmall'));
    $return .= html_writer::link('#', $image, array('title' => $strdelete, 'class' => 'ruledef-delete', 'data-ruleid'=> $ruleid));

    $return .= html_writer::end_tag('div');
    $return .= html_writer::end_tag('td');
    $return .= html_writer::end_tag('tr');

    return $return;
}

function cohort_collection_get_rulesetoperator($cohortid, $collectionstatus='draft') {
    global $CFG, $DB;

    $collectionstatusfields = array('draft' => 'draftcollectionid',
        'active' => 'activecollectionid');
    if (!in_array($collectionstatus, array_keys($collectionstatusfields))) {
        return 0;
    }

    $sql = "SELECT rulesetoperator
        FROM {cohort} c
        INNER JOIN {cohort_rule_collections} crc ON c.{$collectionstatusfields[$collectionstatus]} = crc.id
        WHERE c.id = ? ";

    return $DB->get_field_sql($sql, array($cohortid));
}

function cohort_rules_approve_changes($cohort) {
    global $DB, $USER;

    $now = time();

    $transaction = $DB->start_delegated_transaction();

    // Mark current active cohort collection as obsolete.
    $todb = new stdClass;
    $todb->id = $cohort->activecollectionid;
    $todb->status = COHORT_COL_STATUS_OBSOLETE;
    $todb->timemodified = $now;
    $todb->modifierid = $USER->id;
    $DB->update_record('cohort_rule_collections', $todb);

    // Copy current draft cohort collection.
    $dcollid = cohort_rules_clone_collection($cohort->draftcollectionid, COHORT_COL_STATUS_DRAFT_UNCHANGED, false);

    // Mark current draft cohort collection as active.
    $todb = new stdClass;
    $todb->id = $cohort->draftcollectionid;
    $todb->status = COHORT_COL_STATUS_ACTIVE;
    $todb->timemodified = $now;
    $todb->modifierid = $USER->id;
    $DB->update_record('cohort_rule_collections', $todb);

    // Update cohort.
    $todb = new stdClass;
    $todb->id = $cohort->id;
    $todb->activecollectionid = $cohort->draftcollectionid;
    $todb->draftcollectionid = $dcollid;
    $todb->timemodified = $now;
    $todb->modifierid = $USER->id;
    $DB->update_record('cohort', $todb);

    $transaction->allow_commit();

    totara_cohort_update_dynamic_cohort_members($cohort->id, 0, true);

    return true;
}

function cohort_rules_cancel_changes($cohort) {
    global $DB;

    $transaction = $DB->start_delegated_transaction();

    cohort_rules_delete_collection($cohort->draftcollectionid, false);

    $newcollectionid = cohort_rules_clone_collection($cohort->activecollectionid, COHORT_COL_STATUS_DRAFT_UNCHANGED, false);

    $todb = new stdClass;
    $todb->id = $cohort->id;
    $todb->draftcollectionid = $newcollectionid;
    $DB->update_record('cohort', $todb);

    $transaction->allow_commit();

    return true;
}

function cohort_rules_delete_collection($collectionid, $usetrans=true) {
    global $DB;

    if ($usetrans) {
        $transaction = $DB->start_delegated_transaction();
    }

    // Delete rule params.
    $sql = "DELETE FROM {cohort_rule_params}
         WHERE ruleid IN (
            SELECT cr.id FROM {cohort_rules} cr
            WHERE cr.rulesetid IN (
                SELECT crs.id FROM {cohort_rulesets} crs
                WHERE crs.rulecollectionid = ?
            )
        )";
    $DB->execute($sql, array($collectionid));

    // Delete rules.
    $sql = "DELETE FROM {cohort_rules}
        WHERE rulesetid IN(
            SELECT crs.id FROM {cohort_rulesets} crs
            WHERE crs.rulecollectionid = ?
        )";

    $DB->execute($sql, array($collectionid));

    // Delete rulesets.
    $DB->delete_records('cohort_rulesets', array("rulecollectionid" => $collectionid));

    // Finally, delete rule collection.
    $DB->delete_records('cohort_rule_collections', array('id' => $collectionid));

    if ($usetrans) {
        $transaction->allow_commit();
    }

    return true;
}

/**
 * Clones a collection of cohort rules and attaches them to either
 * a specified cohort or the or the same cohort as the original collections
 *
 * @param   int       $collid     Collection id
 * @param   string    $status     Status of the collection
 * @param   boolean   $usetrans   Whether this should use transactions
 * @param   int       $cohortid   The id of a cohort to attach the collection to
 *
 * @return  int       The id of the created collection
 */
function cohort_rules_clone_collection($collid, $status=null, $usetrans=true, $cohortid=null) {
    global $DB, $USER;

    $now = time();

    if ($usetrans) {
        $transaction = $DB->start_delegated_transaction();
    }

    if (!$collection = $DB->get_record('cohort_rule_collections', array('id' => $collid))) {
        return false;
    }

    $newcollection = new stdClass;
    $newcollection->cohortid =        !empty($cohortid) ? $cohortid : $collection->cohortid;
    $newcollection->rulesetoperator = $collection->rulesetoperator;
    $newcollection->status =          !empty($status) ? $status : $collection->status;
    $newcollection->timecreated =     $now;
    $newcollection->timemodified =    $now;
    $newcollection->modifierid =      $USER->id;
    $newcollectionid = $DB->insert_record('cohort_rule_collections', $newcollection);

    $rulesets = $DB->get_records('cohort_rulesets', array('rulecollectionid' => $collection->id), 'sortorder');
    if (empty($rulesets)) {
        if ($usetrans) {
            $transaction->allow_commit();
        }
        return $newcollectionid;
    }
    foreach ($rulesets as $ruleset) {

        $newruleset = new stdClass();
        $newruleset->rulecollectionid = $newcollectionid;
        $newruleset->name =             $ruleset->name;
        $newruleset->operator =         $ruleset->operator;
        $newruleset->sortorder =        $ruleset->sortorder;
        $newruleset->timecreated =      $newruleset->timemodified = time();
        $newruleset->modifierid =       $USER->id;

        $newruleset->id = $DB->insert_record('cohort_rulesets', $newruleset);

        // Create rules for this ruleset.
        if (!$rules = $DB->get_records('cohort_rules', array('rulesetid' => $ruleset->id), 'sortorder')) {
            continue;
        }
        foreach ($rules as $rule) {

            $newrule = new stdClass();
            $newrule->rulesetid =   $newruleset->id;
            $newrule->ruletype =    $rule->ruletype;
            $newrule->name =        $rule->name;
            $newrule->sortorder =   $rule->sortorder;
            $newrule->timecreated = $newrule->timemodified = time();
            $newrule->modifierid =  $USER->id;

            $newrule->id = $DB->insert_record('cohort_rules', $newrule);

            if (!$ruleparams = $DB->get_records('cohort_rule_params', array('ruleid' => $rule->id), 'name, value')) {
                continue;
            }
            foreach ($ruleparams as $ruleparam) {

                $newruleparam = new stdClass();
                $newruleparam->ruleid =      $newrule->id;
                $newruleparam->name =        $ruleparam->name;
                $newruleparam->value =       $ruleparam->value;
                $newruleparam->timecreated = $newruleparam->timemodified = time();
                $newruleparam->modifierid =  $USER->id;

                $newruleparam->id = $DB->insert_record('cohort_rule_params', $newruleparam);

            }
            unset($ruleparams);
            unset($ruleparam);
            unset($newruleparam);
        }
        unset($rules);
        unset($rule);
        unset($newrule);
    }

    if ($usetrans) {
        $transaction->allow_commit();
    }

    return $newcollectionid;
}
