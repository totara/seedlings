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
 * @author Ciaran Irvine <ciaran.irvine@totaralms.com>
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage plan
 */

/**
 * Local db upgrades for Totara Core
 */

require_once($CFG->dirroot.'/totara/core/db/utils.php');


/**
 * Local database upgrade script
 *
 * @param   integer $oldversion Current (pre-upgrade) local db version timestamp
 * @return  boolean $result
 */
function xmldb_totara_plan_upgrade($oldversion) {
    global $CFG, $DB;
    $dbman = $DB->get_manager(); // loads ddl manager and xmldb classes


    if ($oldversion < 2013021400) {
        $table = new xmldb_table('dp_template');
        $field = new xmldb_field('isdefault', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0);

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Make the first record on the list default to keep current
        // default
        $record = $DB->get_record_select('dp_template', 'sortorder = (SELECT MIN(sortorder) FROM {dp_template})');

        if ($record) {
            $todb = new stdClass();
            $todb->id = $record->id;
            $todb->isdefault = 1;
            $DB->update_record('dp_template', $todb);
        }

        // Add column to plan table to record how a plan was created
        $table = new xmldb_table('dp_plan');
        $field = new xmldb_field('createdby', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0);

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add template foreign key
        $key = new xmldb_key('templateid', XMLDB_KEY_FOREIGN, array('templateid'), 'dp_template', array('id'));
        $dbman->add_key($table, $key);

        // Add user foreign key
        $key = new xmldb_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
        $dbman->add_key($table, $key);

        totara_upgrade_mod_savepoint(true, 2013021400, 'totara_plan');
    }

    if ($oldversion < 2013040200) {
        // Evidence types
        $table = new xmldb_table('dp_evidence_type');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $field = new xmldb_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('description', XMLDB_TYPE_TEXT, 'medium');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Evidence
        $table = new xmldb_table('dp_plan_evidence');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $field = new xmldb_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('evidencetypeid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('evidencelink', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('institution', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('datecompleted', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('dpplanev_userid_ix', XMLDB_INDEX_NOTUNIQUE, array('userid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $key = new xmldb_key('evidencetypeid', XMLDB_KEY_FOREIGN, array('evidencetypeid'), 'dp_evidence_type', array('id'));
        $dbman->add_key($table, $key);

        // Evidence + Item component relation
        $table = new xmldb_table('dp_plan_evidence_relation');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $field = new xmldb_field('evidenceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('planid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'evidenceid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('component', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'planid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'component');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $key = new xmldb_key('evidenceid', XMLDB_KEY_FOREIGN, array('evidenceid'), 'dp_plan_evidence', array('id'));
        $dbman->add_key($table, $key);

        $key = new xmldb_key('planid', XMLDB_KEY_FOREIGN, array('planid'), 'dp_plan', array('id'));
        $dbman->add_key($table, $key);

        $index = new xmldb_index('component', XMLDB_INDEX_NOTUNIQUE, array('planid', 'component', 'itemid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2013040200, 'totara', 'plan');
    }

    if ($oldversion < 2013040201) {

        // Define field readonly to be added to dp_plan_evidence
        $table = new xmldb_table('dp_plan_evidence');
        $field = new xmldb_field('readonly', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'userid');

        // Conditionally launch add field readonly
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // plan savepoint reached
        upgrade_plugin_savepoint(true, 2013040201, 'totara', 'plan');
    }

    if ($oldversion < 2013103000) {
        // Adding foreign keys.
        $tables = array(
        'dp_permissions' => array(
            new xmldb_key('dpperm_tem_fk', XMLDB_KEY_FOREIGN, array('templateid'), 'dp_template', 'id')),
        'dp_component_settings' => array(
            new xmldb_key('dpcompsett_tem_fk', XMLDB_KEY_FOREIGN, array('templateid'), 'dp_template', 'id')),
        'dp_course_settings' => array(
            new xmldb_key('dpcoursett_tem_fk', XMLDB_KEY_FOREIGN_UNIQUE, array('templateid'), 'dp_template', 'id'),
            new xmldb_key('dpcoursett_pri_fk', XMLDB_KEY_FOREIGN, array('priorityscale'), 'dp_priority_scale', 'id')),
        'dp_plan_course_assign' => array(
            new xmldb_key('dpplancourassi_pla_fk', XMLDB_KEY_FOREIGN, array('planid'), 'dp_plan', 'id'),
            new xmldb_key('dpplancourassi_cou_fk', XMLDB_KEY_FOREIGN, array('courseid'), 'course', 'id'),
            new xmldb_key('dpplancourassi_pri_fk', XMLDB_KEY_FOREIGN, array('priority'), 'dp_priority_scale_value', 'id'),
            new xmldb_key('dpplancourassi_com_fk', XMLDB_KEY_FOREIGN, array('completionstatus'), 'course_completions', 'id')),
        'dp_plan_competency_assign' => array(
            new xmldb_key('dpplancompassi_pla_fk', XMLDB_KEY_FOREIGN, array('planid'), 'dp_plan', 'id'),
            new xmldb_key('dpplancompassi_com_fk', XMLDB_KEY_FOREIGN, array('competencyid'), 'comp', 'id'),
            new xmldb_key('dpplancompassi_pri_fk', XMLDB_KEY_FOREIGN, array('priority'), 'dp_priority_scale_value', 'id'),
            new xmldb_key('dpplancompassi_sca_fk', XMLDB_KEY_FOREIGN, array('scalevalueid'), 'comp_scale_values', 'id')),
        'dp_competency_settings' => array(
            new xmldb_key('dpcompsett_tem_fk', XMLDB_KEY_FOREIGN_UNIQUE, array('templateid'), 'dp_template', 'id'),
            new xmldb_key('dpcompsett_pri_fk', XMLDB_KEY_FOREIGN, array('priorityscale'), 'dp_priority_scale', 'id')),
        'dp_priority_scale' => array(
            new xmldb_key('dpprioscal_def_fk', XMLDB_KEY_FOREIGN, array('defaultid'), 'dp_priority_scale_value', 'id'),
            new xmldb_key('dpprioscal_use_fk', XMLDB_KEY_FOREIGN, array('usermodified'), 'user', 'id')),
        'dp_priority_scale_value' => array(
            new xmldb_key('dpprioscalvalu_use_fk', XMLDB_KEY_FOREIGN, array('usermodified'), 'user', 'id'),
            new xmldb_key('dpprioscalvalu_pri_fk', XMLDB_KEY_FOREIGN, array('priorityscaleid'), 'dp_priority_scale', 'id')),
        'dp_objective_scale' => array(
            new xmldb_key('dpobjescal_use_fk', XMLDB_KEY_FOREIGN, array('usermodified'), 'user', 'id'),
            new xmldb_key('dpobjescal_def_fk', XMLDB_KEY_FOREIGN, array('defaultid'), 'dp_objective_scale_value', 'id')),
        'dp_objective_scale_value' => array(
            new xmldb_key('dpobjescalvalu_obj_fk', XMLDB_KEY_FOREIGN, array('objscaleid'), 'dp_objective_scale', 'id'),
            new xmldb_key('dpobjescalvalu_use_fk', XMLDB_KEY_FOREIGN, array('usermodified'), 'user', 'id')),
        'dp_plan_history' => array(
            new xmldb_key('dpplanhist_pla_fk', XMLDB_KEY_FOREIGN, array('planid'), 'dp_plan', 'id'),
            new xmldb_key('dpplanhist_use_fk', XMLDB_KEY_FOREIGN, array('usermodified'), 'user', 'id')),
        'dp_plan_evidence' => array(
            new xmldb_key('dpplanevid_user_fk', XMLDB_KEY_FOREIGN, array('usermodified'), 'user', 'id'),
            new xmldb_key('dpplanevid_use_fk', XMLDB_KEY_FOREIGN, array('userid'), 'user', 'id')),
        'dp_objective_settings' => array(
            new xmldb_key('dpobjesett_tem_fk', XMLDB_KEY_FOREIGN, array('templateid'), 'dp_template', 'id'),
            new xmldb_key('dpobjesett_pri_fk', XMLDB_KEY_FOREIGN, array('priorityscale'), 'dp_priority_scale', 'id'),
            new xmldb_key('dpobjesett_obj_fk', XMLDB_KEY_FOREIGN, array('objectivescale'), 'dp_objective_scale', 'id')),
        'dp_plan_objective' => array(
            new xmldb_key('dpplanobje_pla_fk', XMLDB_KEY_FOREIGN, array('planid'), 'dp_plan', 'id'),
            new xmldb_key('dpplanobje_pri_fk', XMLDB_KEY_FOREIGN, array('priority'), 'dp_priority_scale_value', 'id'),
            new xmldb_key('dpplanobje_sca_fk', XMLDB_KEY_FOREIGN, array('scalevalueid'), 'dp_objective_scale_value', 'id')),
        'dp_plan_settings' => array(
            new xmldb_key('dpplansett_tem_fk', XMLDB_KEY_FOREIGN_UNIQUE, array('templateid'), 'dp_template', 'id')),
        'dp_plan_program_assign' => array(
            new xmldb_key('dpplanprogassi_pla_fk', XMLDB_KEY_FOREIGN, array('planid'), 'dp_plan', 'id'),
            new xmldb_key('dpplanprogassi_pro_fk', XMLDB_KEY_FOREIGN, array('programid'), 'prog', 'id'),
            new xmldb_key('dpplanprogassi_pri_fk', XMLDB_KEY_FOREIGN, array('priority'), 'dp_priority_scale_value', 'id')),
        'dp_program_settings' => array(
            new xmldb_key('dpprogsett_tem_fk', XMLDB_KEY_FOREIGN_UNIQUE, array('templateid'), 'dp_template', 'id')),
        'dp_evidence_type' => array(
            new xmldb_key('dpevidtype_use_fk', XMLDB_KEY_FOREIGN, array('usermodified'), 'user', 'id')));

        foreach ($tables as $tablename => $keys) {
            $table = new xmldb_table($tablename);
            foreach ($keys as $key) {
                $dbman->add_key($table, $key);
            }
        }

        // Plan savepoint reached.
        upgrade_plugin_savepoint(true, 2013103000, 'totara', 'plan');
    }

    if ($oldversion < 2013111500) {
        // Conditionally remove some fields that are no longer used and no longer exist in the install.xml.

        $table = new xmldb_table('dp_plan_evidence');
        $fields = array('planid', 'type', 'filepath');

        // Can't drop the planid field while this index still exists, so bombs away.
        $index = new xmldb_index('dpplanevid_pla', XMLDB_INDEX_NOTUNIQUE, array('planid'));
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        foreach ($fields as $fieldname) {
            $field = new xmldb_field($fieldname);

            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2013111500, 'totara', 'plan');
    }

    if ($oldversion < 2014030600) {
        // Add reason for denying or approving a program extension.
        $table = new xmldb_table('dp_plan_history');
        $field = new xmldb_field('reasonfordecision', XMLDB_TYPE_TEXT, 'medium', null, null, null, null, 'reason');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('dp_plan_competency_assign');
        $field = new xmldb_field('reasonfordecision', XMLDB_TYPE_TEXT, 'medium', null, null, null, null, 'approved');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('dp_plan_course_assign');
        $field = new xmldb_field('reasonfordecision', XMLDB_TYPE_TEXT, 'medium', null, null, null, null, 'approved');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('dp_plan_program_assign');
        $field = new xmldb_field('reasonfordecision', XMLDB_TYPE_TEXT, 'medium', null, null, null, null, 'approved');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('dp_plan_objective');
        $field = new xmldb_field('reasonfordecision', XMLDB_TYPE_TEXT, 'medium', null, null, null, null, 'approved');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        upgrade_plugin_savepoint(true, 2014030600, 'totara', 'plan');
    }

    if ($oldversion < 2014082200) {
        // Make sure there are no nulls before preventing nulls.
        $DB->set_field_select('dp_plan_evidence', 'name', '', "name IS NULL");

        // Fix nulls before setting to nul null.
        $DB->execute("UPDATE {dp_plan_evidence} SET name = '' WHERE name IS NULL");

        // Changing nullability of field name on table dp_plan_evidence to not null.
        $table = new xmldb_table('dp_plan_evidence');
        $field = new xmldb_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'id');

        // Launch change of nullability for field name.
        $dbman->change_field_notnull($table, $field);

        // Plan savepoint reached.
        upgrade_plugin_savepoint(true, 2014082200, 'totara', 'plan');
    }

    return true;
}
