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
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @author Ciaran Irvine <ciaran.irvine@totaralms.com>
 * @package totara
 * @subpackage totara_core
 */

/**
* Totara Module upgrade savepoint, marks end of Totara module upgrade blocks
* It stores module version, resets upgrade timeout
*
* @global object $DB
* @param bool $result false if upgrade step failed, true if completed
* @param string or float $version main version
* @param string $modname name of module
* @return void
*/
function totara_upgrade_mod_savepoint($result, $version, $modname) {
    global $DB;

    if (!$result) {
        throw new upgrade_exception($modname, $version);
    }

    if (!$module = $DB->get_record('config_plugins', array('plugin'=>$modname, 'name'=>'version'))) {
        print_error('modulenotexist', 'debug', '', $modname);
    }

    if ($module->value >= $version) {
        // something really wrong is going on in upgrade script
        throw new downgrade_exception($modname, $module->value, $version);
    }
    $module->value = $version;
    $DB->update_record('config_plugins', $module);
    upgrade_log(UPGRADE_LOG_NORMAL, $modname, 'Upgrade savepoint reached');

    // reset upgrade timeout to default
    upgrade_set_timeout();
}

/**
 * Utility functions for performing Totara local db upgrades
 */


/**
* totara_get_capability_upgrade_map, returns map of 1.1 capabilities to 2.2 capabilities
*
* array in form array[oldcapname] = array(['newcap']=newcapname, ['component']=newcomponent)
* @return array
*/
function totara_get_capability_upgrade_map() {
    $upgrade_caps = array (
    'block/quicklinks:manageownlinks' => array('newcap'=>'block/totara_quicklinks:manageownlinks', 'component' => 'block_totara_quicklinks'),
    'block/quicklinks:managealllinks' => array('newcap'=>'block/totara_quicklinks:managealllinks', 'component' => 'block_totara_quicklinks'),
    'moodle/local:markcomplete' => array('newcap'=>'moodle/course:markcomplete', 'component' => 'moodle'),
    'local/comment:delete' => array('newcap'=>'moodle/comment:delete', 'component' => 'moodle'),
    'local/comment:post' => array('newcap'=>'moodle/comment:post', 'component' => 'moodle'),
    'local/comment:view' => array('newcap'=>'moodle/comment:view', 'component' => 'moodle'),
    'moodle/local:createcoursecustomfield' => array('newcap'=>'totara/core:createcoursecustomfield', 'component' => 'totara/core'),
    'moodle/local:deletecoursecustomfield' => array('newcap'=>'totara/core:deletecoursecustomfield', 'component' => 'totara/core'),
    'moodle/local:updatecoursecustomfield' => array('newcap'=>'totara/core:updatecoursecustomfield', 'component' => 'totara/core'),
    'local/cohort:assign' => array('newcap'=>'moodle/cohort:assign', 'component' => 'moodle'),
    'local/cohort:manage' => array('newcap'=>'moodle/cohort:manage', 'component' => 'moodle'),
    'local/cohort:view' => array('newcap'=>'moodle/cohort:view', 'component' => 'moodle'),
    'local/dashboard:admin' => array('newcap'=>'totara/dashboard:admin', 'component' => 'totara/dashboard'),
    'local/dashboard:edit' => array('newcap'=>'totara/dashboard:edit', 'component' => 'totara/dashboard'),
    'local/dashboard:view' => array('newcap'=>'totara/dashboard:view', 'component' => 'totara/dashboard'),
    'local/oauth:negotiate' => array('newcap'=>'totara/oauth:negotiate', 'component' => 'totara/oauth'),
    'local/plan:accessanyplan' => array('newcap'=>'totara/plan:accessanyplan', 'component' => 'totara/plan'),
    'local/plan:accessplan' => array('newcap'=>'totara/plan:accessplan', 'component' => 'totara/plan'),
    'local/plan:configureplans' => array('newcap'=>'totara/plan:configureplans', 'component' => 'totara/plan'),
    'local/plan:manageobjectivescales' => array('newcap'=>'totara/plan:manageobjectivescales', 'component' => 'totara/plan'),
    'local/plan:managepriorityscales' => array('newcap'=>'totara/plan:managepriorityscales', 'component' => 'totara/plan'),
    'local/program:accessanyprogram' => array('newcap'=>'totara/program:accessanyprogram', 'component' => 'totara/program'),
    'local/program:configureassignments' => array('newcap'=>'totara/program:configureassignments', 'component' => 'totara/program'),
    'local/program:configurecontent' => array('newcap'=>'totara/program:configurecontent', 'component' => 'totara/program'),
    'local/program:configuremessages' => array('newcap'=>'totara/program:configuremessages', 'component' => 'totara/program'),
    'local/program:configureprogram' => array('newcap'=>'totara/program:configureprogram', 'component' => 'totara/program'),
    'local/program:createprogram' => array('newcap'=>'totara/program:createprogram', 'component' => 'totara/program'),
    'local/program:handleexceptions' => array('newcap'=>'totara/program:handleexception', 'component' => 'totara/program'),
    'local/program:viewhiddenprograms' => array('newcap'=>'totara/program:viewhiddenprograms', 'component' => 'totara/program'),
    'local/program:viewprogram' => array('newcap'=>'totara/program:viewprogram', 'component' => 'totara/program'),
    'local/reportbuilder:managereports' => array('newcap'=>'totara/reportbuilder:managereports', 'component' => 'totara/reportbuilder'),
    'moodle/local:assignselfposition' => array('newcap'=>'totara/hierarchy:assignselfposition', 'component' => 'totara/hierarchy'),
    'moodle/local:assignuserposition' => array('newcap'=>'totara/hierarchy:assignuserposition', 'component' => 'totara/hierarchy'),
    'moodle/local:createcompetency' => array('newcap'=>'totara/hierarchy:createcompetency', 'component' => 'totara/hierarchy'),
    'moodle/local:createcompetencycustomfield' => array('newcap'=>'totara/hierarchy:createcompetencycustomfield', 'component' => 'totara/hierarchy'),
    'moodle/local:createcompetencyframeworks' => array('newcap'=>'totara/hierarchy:createcompetencyframeworks', 'component' => 'totara/hierarchy'),
    'moodle/local:createcompetencytemplate' => array('newcap'=>'totara/hierarchy:createcompetencytemplate', 'component' => 'totara/hierarchy'),
    'moodle/local:createcompetencytype' => array('newcap'=>'totara/hierarchy:createcompetencytype', 'component' => 'totara/hierarchy'),
    'moodle/local:createorganisation' => array('newcap'=>'totara/hierarchy:createorganisation', 'component' => 'totara/hierarchy'),
    'moodle/local:createorganisationcustomfield' => array('newcap'=>'totara/hierarchy:createorganisationcustomfield', 'component' => 'totara/hierarchy'),
    'moodle/local:createorganisationframeworks' => array('newcap'=>'totara/hierarchy:createorganisationframeworks', 'component' => 'totara/hierarchy'),
    'moodle/local:createorganisationtype' => array('newcap'=>'totara/hierarchy:createorganisationtype', 'component' => 'totara/hierarchy'),
    'moodle/local:createposition' => array('newcap'=>'totara/hierarchy:createposition', 'component' => 'totara/hierarchy'),
    'moodle/local:createpositioncustomfield' => array('newcap'=>'totara/hierarchy:createpositioncustomfield', 'component' => 'totara/hierarchy'),
    'moodle/local:createpositionframeworks' => array('newcap'=>'totara/hierarchy:createpositionframeworks', 'component' => 'totara/hierarchy'),
    'moodle/local:createpositiontype' => array('newcap'=>'totara/hierarchy:createpositiontype', 'component' => 'totara/hierarchy'),
    'moodle/local:deletecompetency' => array('newcap'=>'totara/hierarchy:deletecompetency', 'component' => 'totara/hierarchy'),
    'moodle/local:deletecompetencycustomfield' => array('newcap'=>'totara/hierarchy:deletecompetencycustomfield', 'component' => 'totara/hierarchy'),
    'moodle/local:deletecompetencyframeworks' => array('newcap'=>'totara/hierarchy:deletecompetencyframeworks', 'component' => 'totara/hierarchy'),
    'moodle/local:deletecompetencytemplate' => array('newcap'=>'totara/hierarchy:deletecompetencytemplate', 'component' => 'totara/hierarchy'),
    'moodle/local:deletecompetencytype' => array('newcap'=>'totara/hierarchy:deletecompetencytype', 'component' => 'totara/hierarchy'),
    'moodle/local:deleteorganisation' => array('newcap'=>'totara/hierarchy:deleteorganisation', 'component' => 'totara/hierarchy'),
    'moodle/local:deleteorganisationcustomfield' => array('newcap'=>'totara/hierarchy:deleteorganisationcustomfield', 'component' => 'totara/hierarchy'),
    'moodle/local:deleteorganisationframeworks' => array('newcap'=>'totara/hierarchy:deleteorganisationframeworks', 'component' => 'totara/hierarchy'),
    'moodle/local:deleteorganisationtype' => array('newcap'=>'totara/hierarchy:deleteorganisationtype', 'component' => 'totara/hierarchy'),
    'moodle/local:deleteposition' => array('newcap'=>'totara/hierarchy:deleteposition', 'component' => 'totara/hierarchy'),
    'moodle/local:deletepositioncustomfield' => array('newcap'=>'totara/hierarchy:deletepositioncustomfield', 'component' => 'totara/hierarchy'),
    'moodle/local:deletepositionframeworks' => array('newcap'=>'totara/hierarchy:deletepositionframeworks', 'component' => 'totara/hierarchy'),
    'moodle/local:deletepositiontype' => array('newcap'=>'totara/hierarchy:deletepositiontyp', 'component' => 'totara/hierarchy'),
    'moodle/local:updatecompetency' => array('newcap'=>'totara/hierarchy:updatecompetency', 'component' => 'totara/hierarchy'),
    'moodle/local:updatecompetencycustomfield' => array('newcap'=>'totara/hierarchy:updatecompetencycustomfield', 'component' => 'totara/hierarchy'),
    'moodle/local:updatecompetencyframeworks' => array('newcap'=>'totara/hierarchy:updatecompetencyframeworks', 'component' => 'totara/hierarchy'),
    'moodle/local:updatecompetencytemplate' => array('newcap'=>'totara/hierarchy:updatecompetencytemplate', 'component' => 'totara/hierarchy'),
    'moodle/local:updatecompetencytype' => array('newcap'=>'totara/hierarchy:updatecompetencytype', 'component' => 'totara/hierarchy'),
    'moodle/local:updateorganisation' => array('newcap'=>'totara/hierarchy:updateorganisation', 'component' => 'totara/hierarchy'),
    'moodle/local:updateorganisationcustomfield' => array('newcap'=>'totara/hierarchy:updateorganisationcustomfield', 'component' => 'totara/hierarchy'),
    'moodle/local:updateorganisationframeworks' => array('newcap'=>'totara/hierarchy:updateorganisationframeworks', 'component' => 'totara/hierarchy'),
    'moodle/local:updateorganisationtype' => array('newcap'=>'totara/hierarchy:updateorganisationtype', 'component' => 'totara/hierarchy'),
    'moodle/local:updateposition' => array('newcap'=>'totara/hierarchy:updateposition', 'component' => 'totara/hierarchy'),
    'moodle/local:updatepositioncustomfield' => array('newcap'=>'totara/hierarchy:updatepositioncustomfield', 'component' => 'totara/hierarchy'),
    'moodle/local:updatepositionframeworks' => array('newcap'=>'totara/hierarchy:updatepositionframeworks', 'component' => 'totara/hierarchy'),
    'moodle/local:updatepositiontype' => array('newcap'=>'totara/hierarchy:updatepositiontype', 'component' => 'totara/hierarchy'),
    'moodle/local:viewcompetency' => array('newcap'=>'totara/hierarchy:viewcompetency', 'component' => 'totara/hierarchy'),
    'moodle/local:vieworganisation' => array('newcap'=>'totara/hierarchy:vieworganisation', 'component' => 'totara/hierarchy'),
    'moodle/local:viewposition' => array('newcap'=>'totara/hierarchy:viewposition', 'component' => 'totara/hierarchy'));

    return $upgrade_caps;
}
/**
* totara_upgrade_capabilities, for fixing 1.1 capabilities when upgrading to 2.2
*
* @global object $DB
* @return bool $status
*/
function totara_upgrade_capabilities() {
    global $DB;
    $upgrade_caps = totara_get_capability_upgrade_map();
    foreach ($upgrade_caps as $oldcap => $val) {
        $sql = "UPDATE {capabilities} SET name=?, component=? WHERE name=?";
        $params = array($val['newcap'], $val['component'], $oldcap);
        $DB->execute($sql, $params);
        $sql = "UPDATE {role_capabilities} SET capability=? WHERE capability=?";
        $params = array($val['newcap'], $oldcap);
        $DB->execute($sql, $params);
    }
}
/**
* totara_set_charfield_nullable, for fixing 1.9 char fields where old definition was ISNULL=true and DEFAULT=""
*
* @global object $DB
* @param string $table the table name
* @param string $field the field name
* @param string $previous the field immediately previous to the char field in the table definition
* @param string $length length of the char field NB: pass as a string, not an int! 'small','medium','big' for TEXT fields
* @param bool or null $notnull null or false if CAN be null, XMLDB_NOTNULL if CANNOT be null
* * @param string or null $default either remove the default (null) or force a sane non-empty default
* @param array $indexes array of xmldb_index objects for all indexes on tables that contain the char field
* @return void
*/
function totara_fix_nullable_charfield($table, $field, $previous, $length='255', $notnull=null, $default=null, $indexes=array(), $fieldtype=XMLDB_TYPE_CHAR) {
    global $DB, $CFG;
    $dbman = $DB->get_manager();

    //sanity check
    if ($notnull == XMLDB_NOTNULL && empty($default)) {
        throw new upgrade_exception("$table $field set as NOT NULL with an empty default value!", '1.1 to 2.2 upgrade');
    }
    $xtable = new xmldb_table($table);
    if (count($indexes) > 0) {
        foreach ($indexes as $index) {
            $dbman->drop_index($xtable, $index);
        }
    }

    $xfield = new xmldb_field($field);
    $xfield->set_attributes($fieldtype, $length, null, $notnull, null, $default, $previous);
    $dbman->change_field_notnull($xtable, $xfield);
    $dbman->change_field_default($xtable, $xfield);

    if (count($indexes) > 0) {
        foreach ($indexes as $index) {
            $dbman->add_index($xtable, $index);
        }
    }
}
/**
 * Function for fixing records in the database caused by a bug that
 * introduced duplicates
 *
 * @param string $tablename Name of the table to fix (without prefix)
 * @param string $where_sql SQL snippet restricting which records are fixed
 *
 * @return boolean True if the operation completed successfully or there
 *                 was nothing to do
 */
function totara_data_object_duplicate_fix($tablename, $where_sql) {
    global $DB, $OUTPUT;

    // Check for duplicates
    $count_sql = "
        SELECT
            COUNT(*)
        FROM
            {$tablename}
        WHERE
            id NOT IN
            (
                {$where_sql}
            )
    ";

    // If any duplicates, keep correct version of record
    if (!$count = $DB->count_records_sql($count_sql)) {
        return true;
    }

    $a = new stdClass();
    $a->count = $count;
    $a->tablename = $tablename;
    echo $OUTPUT->notification(get_string('error:duplicaterecordsfound', 'totara_core', $a));

    $select_sql = "
        SELECT
            *
        FROM
            {$tablename}
        WHERE
            id NOT IN
            (
                {$where_sql}
            )
    ";

    // Select rows to be deleted, and dump their contents to the error log
    $duplicates = $DB->get_records_sql($select_sql);
    $ids = array();
    foreach ($duplicates as $dup) {
        error_log(get_string('error:duplicaterecordsdeleted', 'totara_core', $tablename) . var_export((array)$dup, true));
        $ids[] = $dup->id;
    }

    // Delete duplicate rows
    list($usql, $params) = $DB->get_in_or_equal($ids);
    $delete_sql = "
        DELETE FROM
            {$tablename}
        WHERE
            WHERE id $usql";

    if (!$DB->execute($delete_sql, $params)) {
        return false;
    }

    return true;
}

/**
 * Re-add changes to course completion for Totara
 *
 * Although these exist in lib/db/upgrade.php, anyone upgrading from Moodle 2.2.2 or above
 * would already have a higher version number so we need to apply them again:
 *
 * 1. when totara first is installed (to fix for anyone upgrading from 2.2.2+)
 * 2. in a totara core upgrade (to fix for anyone who has already upgraded from 2.2.2+)
 *
 * These changes will only be applied if they haven't been run previously so it's okay
 * to call this function multiple times
 */
function totara_readd_course_completion_changes() {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

    // Define index useridcourse (unique) to be added to course_completions
    $table = new xmldb_table('course_completions');
    $index = new xmldb_index('useridcourse', XMLDB_INDEX_UNIQUE, array('userid', 'course'));

    // Conditionally launch add index useridcourse
    if (!$dbman->index_exists($table, $index)) {
        // Clean up all instances of duplicate records
        // Add indexes to prevent new duplicates
        upgrade_course_completion_remove_duplicates(
            'course_completions',
            array('userid', 'course'),
            array('timecompleted', 'timestarted', 'timeenrolled')
        );

        $dbman->add_index($table, $index);
    }

    // Define index useridcoursecriteraid (unique) to be added to course_completion_crit_compl
    $table = new xmldb_table('course_completion_crit_compl');
    $index = new xmldb_index('useridcoursecriteraid', XMLDB_INDEX_UNIQUE, array('userid', 'course', 'criteriaid'));

    // Conditionally launch add index useridcoursecriteraid
    if (!$dbman->index_exists($table, $index)) {
        upgrade_course_completion_remove_duplicates(
            'course_completion_crit_compl',
            array('userid', 'course', 'criteriaid'),
            array('timecompleted')
        );

        $dbman->add_index($table, $index);
    }

    // Define index coursecriteratype (unique) to be added to course_completion_aggr_methd
    $table = new xmldb_table('course_completion_aggr_methd');
    $index = new xmldb_index('coursecriteriatype', XMLDB_INDEX_UNIQUE, array('course', 'criteriatype'));

    // Conditionally launch add index coursecriteratype
    if (!$dbman->index_exists($table, $index)) {
        upgrade_course_completion_remove_duplicates(
            'course_completion_aggr_methd',
            array('course', 'criteriatype')
        );

        $dbman->add_index($table, $index);
    }

    require_once("{$CFG->dirroot}/completion/completion_completion.php");

    /// Define field status to be added to course_completions
    $table = new xmldb_table('course_completions');
    $field = new xmldb_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, 0, 'reaggregate');
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);

        // Get all records
        $rs = $DB->get_recordset_sql('SELECT * FROM {course_completions}');
        foreach ($rs as $record) {
            // Update status column
            $status = completion_completion::get_status($record);
            if ($status) {
                $status = constant('COMPLETION_STATUS_'.strtoupper($status));
            }

            $record->status = $status;

            if (!$DB->update_record('course_completions', $record)) {
                break;
            }
        }
        $rs->close();
    }

}

/**
 * Fix badges and completion capabilities when upgrading from Totara 2.4.
 *
 * @return void
 */
function totara_fix_existing_capabilities() {
    global $DB;

    // Get all existing totara_core capabilities in the database.
    $cachedcaps = get_cached_capabilities('totara_core');
    if ($cachedcaps) {
        foreach ($cachedcaps as $cachedcap) {
            // If it is a moodle capability, update its component.
            if (strpos($cachedcap->name, 'moodle') === 0) {
                $updatecap = new stdClass();
                $updatecap->id = $cachedcap->id;
                $updatecap->component = 'moodle';
                $DB->update_record('capabilities', $updatecap);
            }
        }
    }
}

/**
 * Get tables and records which have non-unique idnumbers.
 * Used in upgrade script.
 *
 * @return object|false Array (table => list of idnumbers) or false if no duplicates found
 */
function totara_get_nonunique_idnumbers() {
    global $DB;

    $tables = array('user', 'comp', 'comp_framework', 'comp_scale_values', 'comp_type', 'org', 'org_framework', 'org_type',
                    'dp_priority_scale_value', 'dp_objective_scale_value', 'pos', 'pos_framework', 'pos_type', 'prog');

    $records = array();
    foreach ($tables as $table) {
        if ($DB->get_manager()->table_exists($table)) {
            $sql = "SELECT
                        idnumber
                    FROM
                        {{$table}}
                    WHERE " . $DB->sql_isnotempty($table, 'idnumber', true, false) . "
                    GROUP BY
                        idnumber
                    HAVING
                        COUNT(*) > 1";
            if ($fields = $DB->get_fieldset_sql($sql)) {
                $record = new stdClass();
                $record->table = $table;
                $record->idnumbers = implode(', ', $fields);
                $records[] = $record;
            }
        }
    }

    if (!empty($records)) {
        return $records;
    }
    return false;
}

/**
 * Get cohort rules that were broken by expansion of options in Totara 2.4.8.
 *
 * @return  array List of rules.
 */
function totara_get_text_broken_rules() {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/totara/cohort/lib.php');

    $userrulenames = "('idnumber', 'username', 'email', 'firstname', 'lastname', 'city', 'institution', 'department')";

    // Create sql snippet for rules based on users.
    $sqluserrules = "cr.ruletype = 'user' AND cr.name IN $userrulenames";

    // Create sql snippet for rules based on positions.
    $sqlposrules = "cr.ruletype = 'pos' AND cr.name IN ('idnumber', 'name')";

    // Create sql snippet for rules based on organisations.
    $sqlorgrules = "cr.ruletype = 'org' AND cr.name = 'idnumber'";

    // Create sql snippet for rules based on customfields.
    $sqlcustomrules = "cr.ruletype = :usercustomfield";

    // Find all active and draft rules in dynamic cohorts that could be affected by the expansion of rule options change.
    $sql = "SELECT crp.id, crp.ruleid, crp.name, crp.value, crp.timecreated, crp.timemodified, cr.ruletype, cr.name as rulename,
                   c.id as cohortid, c.name as cohortname, c.activecollectionid, crc.id as rulecollectionid
            FROM {cohort} c
            INNER JOIN {cohort_rule_collections} crc ON crc.id IN (c.activecollectionid, c.draftcollectionid)
            INNER JOIN {cohort_rulesets} crs ON crs.rulecollectionid = crc.id
            INNER JOIN {cohort_rules} cr ON cr.rulesetid = crs.id
            INNER JOIN {cohort_rule_params} crp ON cr.id = crp.ruleid
            WHERE c.cohorttype = :cohorttype
              AND crp.name = :equal
              AND ($sqluserrules
               OR $sqlposrules
               OR $sqlorgrules
               OR $sqlcustomrules)
            ORDER BY c.id, crp.id";

    $params = array('cohorttype' => cohort::TYPE_DYNAMIC, 'equal' => 'equal', 'usercustomfield' => 'usercustomfields');

    $rules = $DB->get_records_sql($sql, $params);

    // We might still have some rules that don't need to be fixed, check each rule is a text rule
    // before including it.
    $brokenrules = array();
    foreach ($rules as $rule) {
        // This type of rule doesn't need to be fixed (only text rules are affected).
        $ruledef = cohort_rules_get_rule_definition($rule->ruletype, $rule->rulename);
        if (get_class($ruledef->ui) === 'cohort_rule_ui_text') {
            $brokenrules[] = $rule;
        }
    }

    return $brokenrules;
}

/**
 * Remap the old rules to the new ones (expansion of option in 2.4.8)
 *
 * @param array $rulestofix Array of rules to fix as generated by {@link totara_get_text_broken_rules()}.
 *
 * @return  void
 */
function totara_cohort_remap_rules($rulestofix) {
    global $DB, $USER;

    foreach ($rulestofix as $ruleparam) {
        if ($ruleparam->value == COHORT_RULES_OP_IN_NOTEQUAL) {
            $ruleparam->value = COHORT_RULES_OP_IN_NOTEQUALTO;
        } else if ($ruleparam->value == COHORT_RULES_OP_IN_EQUAL) {
            $ruleparam->value = COHORT_RULES_OP_IN_ISEQUALTO;
        }

        // Remap rules.
        $todb = new stdClass();
        $todb->id = $ruleparam->id;
        $todb->value = $ruleparam->value;
        $todb->timemodified = time();
        $todb->modifierid = $USER->id;
        $DB->update_record('cohort_rule_params', $todb);
    }
}

/**
 * Determines if a broken cohort rule can be fixed automatically.
 *
 * @param object $brokenrule Rule as returned by {@link totara_get_text_broken_rules()}.
 * @return bool True if the rule can be fixed automatically, false otherwise.
 */
function totara_cohort_is_rule_fixable($brokenrule) {

    // They upgraded from a version before 2.4.8, we know the rules are wrong so we can fix them.
    $previousversion = get_config('totara_core', 'previous_version');
    if (!empty($previousversion) && version_compare('2.4.8', $previousversion, '>')) {
        return true;
    }

    // This timestamp is just before 2.4.8 release. Any rules where lastmodified is before this date *must*
    // be fixed.
    $bugreleasedate = 1377046800;
    if ($brokenrule->timemodified < $bugreleasedate) {
        return true;
    }

    // Otherwise we can't be sure if this rule needs fixing or not (since we don't know when they upgraded).
    return false;
}

/**
 * Function call before upgrade.
 *
 * @param $totarainfo Info obtained from totara_version_info function.
 * @return void.
 */
function totara_preupgrade($totarainfo) {

    // Save a copy of the version they are upgrading from.
    set_config('previous_version', $totarainfo->existingtotaraversion, 'totara_core');
}
