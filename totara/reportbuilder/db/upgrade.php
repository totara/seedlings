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

require_once($CFG->dirroot.'/totara/core/db/utils.php');
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');

/**
 * Local database upgrade script
 *
 * @param   integer $oldversion Current (pre-upgrade) local db version timestamp
 * @return  boolean $result
 */
function xmldb_totara_reportbuilder_upgrade($oldversion) {
    global $CFG, $DB, $REPORT_BUILDER_EXPORT_OPTIONS;
    $dbman = $DB->get_manager(); // loads ddl manager and xmldb classes

    if ($oldversion < 2012071300) {
        // handle renaming of assignment field: description to intro
        foreach (array('columns', 'filters') as $table) {
            $sql = "SELECT rbt.id FROM {report_builder_{$table}} rbt
                JOIN {report_builder} rb
                ON rbt.reportid = rb.id
                WHERE
                (rbt.type = ? AND rbt.value = ? AND rb.source = ?) OR
                (rbt.type = ? AND rbt.value = ? AND rb.source = ?)";
            $items = $DB->get_fieldset_sql($sql, array(
                'assignment', 'description', 'assignment',
                'base', 'description', 'assignmentsummary'));

            if (!empty($items)) {
                list($insql, $inparams) = $DB->get_in_or_equal($items);
                $sql = "UPDATE {report_builder_{$table}} SET value = ? WHERE id {$insql}";
                $params = array_merge(array('intro'), $inparams);
                $DB->execute($sql, $params);
            }
        }
        totara_upgrade_mod_savepoint(true, 2012071300, 'totara_reportbuilder');
    }

    if ($oldversion < 2012071900) {
        require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');
        // rename the aggregated user columns/filters to avoid clashing with standard user fields
        reportbuilder_rename_data('columns', 'course_completion_by_org', 'user', 'fullname', 'user', 'allparticipants');
        reportbuilder_rename_data('filters', 'course_completion_by_org', 'user', 'fullname', 'user', 'allparticipants');
        totara_upgrade_mod_savepoint(true, 2012071900, 'totara_reportbuilder');
    }

    if ($oldversion < 2012073100) {
        // set global setting for financial year
        // default: July, 1
        set_config('financialyear', '0107', 'reportbuilder');
        totara_upgrade_mod_savepoint(true, 2012073100, 'totara_reportbuilder');
    }

    if ($oldversion < 2012081000) {
        // need to migrate saved search data from the database
        // to remove extraneous array that is no longer used
        $searches = $DB->get_recordset('report_builder_saved', null, '', 'id, search');
        foreach ($searches as $search) {
            $todb = new stdClass();
            $todb->id = $search->id;
            $currentfilters = unserialize($search->search);
            $newfilters = array();
            foreach ($currentfilters as $key => $filter) {
                // if the filter contains an array with only the [0] key set
                // assume it is no longer needed and remove it
                $newfilters[$key] = (isset($filter[0]) && count($filter) == 1) ? $filter[0] : $filter;
            }
            $todb->search = serialize($newfilters);
            $DB->update_record('report_builder_saved', $todb);
        }
        $searches->close();
        totara_upgrade_mod_savepoint(true, 2012081000, 'totara_reportbuilder');
    }

    if ($oldversion < 2012112300) {
        // Convert saved searches with status to the new status field
        $filter = 'course_completion-status';

        $like_sql = $DB->sql_like('rs.search', '?');

        $sql = "SELECT rs.id, rs.search
                FROM {report_builder_saved} AS rs
                JOIN {report_builder} AS rb ON rb.id = rs.reportid
                WHERE rb.source = ?
                AND {$like_sql}";

        $params = array('course_completion', '%' . $DB->sql_like_escape($filter) . '%');

        $searches = $DB->get_records_sql($sql, $params);

        require_once($CFG->dirroot . '/completion/completion_completion.php');

        foreach ($searches as $search) {
            $todb = new stdClass();
            $todb->id = $search->id;
            $data = unserialize($search->search);

            if (isset($data[$filter])) {
                $options = $data[$filter];
                if (isset($options['operator']) && isset($options['value']) && is_int($options['operator']) && is_string($options['value'])) {
                    $operator = $options['operator'];
                    $value = $options['value'];
                    if (($operator == 1 && $value == '0') || ($operator == 2 && $value == '1')) {
                        // Completion Status is equal to "Not completed" or
                        // Completion Status isn't equal to "Completed"
                        $options['value'] = array(
                            COMPLETION_STATUS_NOTYETSTARTED => "1",
                            COMPLETION_STATUS_INPROGRESS => "1",
                            COMPLETION_STATUS_COMPLETE => "0",
                            COMPLETION_STATUS_COMPLETEVIARPL => "0" );
                    } else if (($operator == 1 && $value == '1') || ($operator == 2 && $value == '0')) {
                        // Completion Status is equal to "Completed" or
                        // Completion Status isn't equal to "Not completed"
                        $options['value'] = array(
                            COMPLETION_STATUS_NOTYETSTARTED => "0",
                            COMPLETION_STATUS_INPROGRESS => "0",
                            COMPLETION_STATUS_COMPLETE => "1",
                            COMPLETION_STATUS_COMPLETEVIARPL => "1" );
                    } else {
                        // not the expected data so leave the data alone
                        continue;
                    }
                    // Set the operator to any of the following
                    $options['operator'] = 1;
                    $data[$filter] = $options;
                    $todb->search = serialize($data);
                    $DB->update_record('report_builder_saved', $todb);
                }
            }
        }
        totara_upgrade_mod_savepoint(true, 2012112300, 'totara_reportbuilder');
    }

    if ($oldversion < 2013021100) {
        $table = new xmldb_table('report_builder');
        $field1 = new xmldb_field('cache', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0, 'hidden');
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }

        $settingstable = new xmldb_table('report_builder_settings');
        $fieldcache = new xmldb_field('cachedvalue', XMLDB_TYPE_CHAR, '255', null, null, null, 0, 'value');
        if (!$dbman->field_exists($settingstable, $fieldcache)) {
            $dbman->add_field($settingstable, $fieldcache);
        }

        $tablecache = new xmldb_table('report_builder_cache');

        $tablecache->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $tablecache->add_field('reportid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $tablecache->add_field('cachetable', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $tablecache->add_field('frequency', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $tablecache->add_field('schedule', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $tablecache->add_field('lastreport', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $tablecache->add_field('nextreport', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $tablecache->add_field('config', XMLDB_TYPE_TEXT, '', null, null, null, null);
        $tablecache->add_field('changed', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
        $tablecache->add_field('genstart', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        $tablecache->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $tablecache->add_key('reportid', XMLDB_KEY_FOREIGN, array('reportid'), 'report_builder', array('id'));

        $tablecache->add_index('nextreport', XMLDB_INDEX_NOTUNIQUE, array('nextreport'));

        if (!$dbman->table_exists('report_builder_cache')) {
            $dbman->create_table($tablecache);
        }

        totara_upgrade_mod_savepoint(true, 2013021100, 'totara_reportbuilder');
    }

    if ($oldversion < 2013032700) {
        //add new column to check for pre-filtering
        $table = new xmldb_table('report_builder');
        $field = new xmldb_field('initialdisplay');
        $field->set_attributes(XMLDB_TYPE_INTEGER, 1, null, XMLDB_NOTNULL, null, 0, 'embedded');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        totara_upgrade_mod_savepoint(true, 2013032700, 'totara_reportbuilder');
    }

    if ($oldversion < 2013061000) {
        $table = new xmldb_table('report_builder_schedule');
        $field = new xmldb_field('exporttofilesystem', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'frequency');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        set_config('exporttofilesystem', 0, 'reportbuilder');
        totara_upgrade_mod_savepoint(true, 2013061000, 'totara_reportbuilder');
    }

    if ($oldversion < 2013092400) {
        $table = new xmldb_table('report_builder_filters');
        $namefield = new xmldb_field('filtername', XMLDB_TYPE_CHAR, '255');
        $customnamefield = new xmldb_field('customname', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $namefield)) {
            $dbman->add_field($table, $namefield);
        }
        if (!$dbman->field_exists($table, $customnamefield)) {
            $dbman->add_field($table, $customnamefield);
        }
        // Add pdf to the default export options.
        $currentexportoptions = get_config('reportbuilder', 'exportoptions');
        $currentexportoptions = is_null($currentexportoptions) ? 0 : $currentexportoptions;
        $newexportoptions = ($currentexportoptions | REPORT_BUILDER_EXPORT_PDF_PORTRAIT | REPORT_BUILDER_EXPORT_PDF_LANDSCAPE);
        set_config('exportoptions', $newexportoptions, 'reportbuilder');
        totara_upgrade_mod_savepoint(true, 2013092400, 'totara_reportbuilder');
    }

    if ($oldversion < 2013103000) {
        // Adding foreign keys.
        $tables = array(
            'report_builder_columns' => array(
                new xmldb_key('repobuilcolu_rep_fk', XMLDB_KEY_FOREIGN, array('reportid'), 'report_builder', 'id')),

            'report_builder_filters' => array(
                new xmldb_key('repobuilfilt_rep_fk', XMLDB_KEY_FOREIGN, array('reportid'), 'report_builder', 'id')),

            'report_builder_settings' => array(
                new xmldb_key('repobuilsett_rep_fk', XMLDB_KEY_FOREIGN, array('reportid'), 'report_builder', 'id')),

            'report_builder_saved' => array(
                new xmldb_key('repobuilsave_rep_fk', XMLDB_KEY_FOREIGN, array('reportid'), 'report_builder', 'id'),
                new xmldb_key('repobuilsave_use_fk', XMLDB_KEY_FOREIGN, array('userid'), 'user', 'id')),

            'report_builder_group_assign' => array(
                new xmldb_key('repobuilgrouassi_gro_fk', XMLDB_KEY_FOREIGN, array('groupid'), 'report_builder_group', 'id')),

            'report_builder_preproc_track' => array(
                new xmldb_key('repobuilpreptrac_gro_fk', XMLDB_KEY_FOREIGN, array('groupid'), 'report_builder_group', 'id')),

            'report_builder_schedule' => array(
                new xmldb_key('repobuilsche_rep_fk', XMLDB_KEY_FOREIGN, array('reportid'), 'report_builder', 'id'),
                new xmldb_key('repobuilsche_use_fk', XMLDB_KEY_FOREIGN, array('userid'), 'user', 'id'),
                new xmldb_key('repobuilsche_sav_fk', XMLDB_KEY_FOREIGN, array('savedsearchid'), 'report_builder_saved', 'id')));

        foreach ($tables as $tablename => $keys) {
            $table = new xmldb_table($tablename);
            foreach ($keys as $key) {
                $dbman->add_key($table, $key);
            }
        }

        // Report builder savepoint reached.
        totara_upgrade_mod_savepoint(true, 2013103000, 'totara_reportbuilder');
    }

    if ($oldversion < 2013121000) {
        require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');

        // Rename any existing records for the timecompleted column/filter in dp_certifications.
        reportbuilder_rename_data('columns', 'dp_certification', 'prog_completion', 'timecompleted', 'certif_completion', 'timecompleted');
        reportbuilder_rename_data('filters', 'dp_certification', 'prog_completion', 'timecompleted', 'certif_completion', 'timecompleted');

        // Report builder savepoint reached.
        totara_upgrade_mod_savepoint(true, 2013121000, 'totara_reportbuilder');
    }

    if ($oldversion < 2014012400) {
        // Changing length of field from 255 to 1024 to match length of hierarchy custom field names.
        $table = new xmldb_table('report_builder_columns');
        $field = new xmldb_field('heading', XMLDB_TYPE_CHAR, '1024', null, null, null, null, 'value');
        // Launch change of type for field heading
        $dbman->change_field_precision($table, $field);
        // Changing length of field from 255 to 1024 to match length of hierarchy custom field names.
        $table = new xmldb_table('report_builder_filters');
        $field = new xmldb_field('filtername', XMLDB_TYPE_CHAR, '1024', null, null, null, null, 'advanced');
        // Launch change of type for field filtername
        $dbman->change_field_precision($table, $field);
        totara_upgrade_mod_savepoint(true, 2014012400, 'totara_reportbuilder');
    }

    if ($oldversion < 2014021100) {
        $tempconfig = get_config('reportbuilder', 'exportoptions');

        if (!is_null($tempconfig)) {
            $selected = array();
            foreach ($REPORT_BUILDER_EXPORT_OPTIONS as $option => $code) {
                // Bitwise operator to see if option bit is set.
                if (($tempconfig & $code) == $code) {
                    $selected[] = $code;
                }
            }
            $selected = implode(',', $selected);
            set_config('exportoptions', $selected, 'reportbuilder');
        }
        // Report builder savepoint reached.
        totara_upgrade_mod_savepoint(true, 2014021100, 'totara_reportbuilder');
    }

    // Add table and fields for report builder search regions - standard, sidebar, toolbar.
    if ($oldversion < 2014030200) {

        // Define table report_builder_search_cols to be created.
        $table = new xmldb_table('report_builder_search_cols');

        // Adding fields to table report_builder_search_cols.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('reportid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('value', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table report_builder_search_cols.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('repobuilsear_rep_fk', XMLDB_KEY_FOREIGN, array('reportid'), 'report_builder', array('id'));

        // Conditionally launch create table for report_builder_search_cols.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define field region to be added to report_builder_filters.
        $table = new xmldb_table('report_builder_filters');
        $field = new xmldb_field('region', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'customname');

        // Conditionally launch add field region.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field toolbarsearch to be added to report_builder.
        $table = new xmldb_table('report_builder');
        $field = new xmldb_field('toolbarsearch', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1', 'initialdisplay');

        // Conditionally launch add field toolbarsearch.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2014030200, 'totara', 'reportbuilder');
    }

    // For upgrade (not fresh install) version we leave default course/programs catalog.
    // For fresh installs - new course/programs catalog
    if ($oldversion < 2014030500) {
        set_config('upgradetofaceted', 1);
        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2014030500, 'totara', 'reportbuilder');
    }

    if ($oldversion < 2014031400) {

        require_once($CFG->dirroot . '/totara/reportbuilder/classes/rb_base_content.php');
        $changes = array(
            'own' => rb_user_content::USER_OWN,
            'reports' => rb_user_content::USER_DIRECT_REPORTS,
            'ownandreports' => (rb_user_content::USER_OWN | rb_user_content::USER_DIRECT_REPORTS)
        );
        // Update both 'value' and 'cachedvalue'.
        $sql = "UPDATE {report_builder_settings}
            SET value = :newval
            WHERE type = :type AND name = :name AND value = :oldval";
        $sql2 = "UPDATE {report_builder_settings}
            SET cachedvalue = :newval
            WHERE type = :type AND name = :name AND cachedvalue = :oldval";
        $params = array('type' => 'user_content', 'name' => 'who');

        $transaction = $DB->start_delegated_transaction();
        foreach ($changes as $oldval => $newval) {
            $params['oldval'] = $oldval;
            $params['newval'] = $newval;
            $DB->execute($sql, $params);
            $DB->execute($sql2, $params);
        }
        $transaction->allow_commit();

        // Report builder savepoint reached.
        totara_upgrade_mod_savepoint(true, 2014031400, 'totara_reportbuilder');
    }

    if ($oldversion < 2014082200) {

        // Changing the default of field cachedvalue on table report_builder_settings to drop it.
        $table = new xmldb_table('report_builder_settings');
        $field = new xmldb_field('cachedvalue', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'value');

        // Launch change of default for field cachedvalue.
        $dbman->change_field_default($table, $field);

        // Changing the default of field exporttofilesystem on table report_builder_schedule to drop it.
        $table = new xmldb_table('report_builder_schedule');
        $field = new xmldb_field('exporttofilesystem', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'frequency');

        // Launch change of default for field exporttofilesystem.
        $dbman->change_field_default($table, $field);

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2014082200, 'totara', 'reportbuilder');
    }

    if ($oldversion < 2014082201) {

        // Changing precision of field cache on table report_builder to (4).
        $table = new xmldb_table('report_builder');
        $field = new xmldb_field('cache', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'hidden');

        // Launch change of precision for field cache.
        $dbman->change_field_precision($table, $field);

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2014082201, 'totara', 'reportbuilder');
    }

    if ($oldversion < 2014090900) {
        // Fix renamed columns in existing reports.
        $DB->set_field_select('report_builder_columns', 'value', 'program_previous_completion',
                'type = ? AND value= ?',
                array('program_completion_history', 'program_completion_history_link'));

         $DB->set_field_select('report_builder_columns', 'value', 'course_completion_previous_completion',
                'type = ? AND value= ?',
                array('course_completion_history', 'course_completion_history_link'));
        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2014090900, 'totara', 'reportbuilder');
    }

    if ($oldversion < 2014092400) {
        // Fix the column type.
        reportbuilder_rename_data('columns', 'dp_certification', 'prog_completion', 'timedue', 'certif_completion', 'timedue');
        // Fix the filter type.
        reportbuilder_rename_data('filters', 'dp_certification', 'prog_completion', 'timedue', 'certif_completion', 'timedue');
        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2014092400, 'totara', 'reportbuilder');
    }


    // This is the Totara 2.7 upgrade line.
    // All following versions need to be bumped up during merging from 2.6 until we have separate t2-release-27 branch!

    if ($oldversion < 2014100701) {

        // Define field transform to be added to report_builder_columns.
        $table = new xmldb_table('report_builder_columns');
        $field = new xmldb_field('transform', XMLDB_TYPE_CHAR, '30', null, null, null, null, 'value');

        // Conditionally launch add field transform.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field aggregate to be added to report_builder_columns.
        $table = new xmldb_table('report_builder_columns');
        $field = new xmldb_field('aggregate', XMLDB_TYPE_CHAR, '30', null, null, null, null, 'transform');

        // Conditionally launch add field aggregate.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Invalidate all caches.
        $DB->set_field('report_builder_cache', 'changed', 1, array('changed' => 0));

        // Drop unused config field.
        $table = new xmldb_table('report_builder_cache');
        $field = new xmldb_field('config');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Delete all old cache tables.
        $rs = $DB->get_recordset_select('report_builder_cache', "cachetable IS NOT NULL AND cachetable <> ''", array(), 'id ASC',
                                        'id, cachetable');
        foreach ($rs as $cache) {
            $table = trim($cache->cachetable, '{}');
            if (!$table) {
                continue;
            }
            if ($dbman->table_exists($table)) {
                $dbman->drop_table(new xmldb_table($table));
            }
        }
        $rs->close();
        $DB->set_field('report_builder_cache', 'cachetable', null, array());

        // Add query hash field.
        $table = new xmldb_table('report_builder_cache');
        $field = new xmldb_field('queryhash', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'nextreport');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Drop unused config field.
        $table = new xmldb_table('report_builder_settings');
        $field = new xmldb_field('cachedvalue');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2014100701, 'totara', 'reportbuilder');
    }

    if ($oldversion < 2014102300) {

        // Replace old category filter with the new one.
        $sql = "UPDATE {report_builder_filters}
        SET value = :newfiltervalue
        WHERE type = :filtertype
          AND value = :filtervalue
          AND region = :filterregion";
        $params = array(
            'newfiltervalue' => 'path',
            'filtertype' => 'course_category',
            'filtervalue' => 'id',
            'filterregion' => rb_filter_type::RB_FILTER_REGION_STANDARD
        );
        $DB->execute($sql, $params);

        // Replace old category filter in saved search.
        $sql = "SELECT rbs.*
        FROM {report_builder_saved} rbs
        INNER JOIN {report_builder_filters} rbf ON rbs.reportid = rbf.reportid
        WHERE rbf.type = :filtertype
          AND rbf.value = :filtervalue";
        $params = array('filtertype' => 'course_category', 'filtervalue' => 'path');
        $searches = $DB->get_records_sql($sql, $params);
        foreach ($searches as $search) {
            $todb = new stdClass();
            $todb->id = $search->id;
            $currentfilters = unserialize($search->search);
            $newfilters = array();
            foreach ($currentfilters as $key => $filter) {
                if ($key === 'course_category-id') {
                    // Change key and add recursive param to the filter.
                    $key = 'course_category-path';
                    $filter['recursive'] = 0;
                }
                $newfilters[$key] = $filter;
            }
            $todb->search = serialize($newfilters);
            $DB->update_record('report_builder_saved', $todb);
        }

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2014102300, 'totara', 'reportbuilder');
    }

    if ($oldversion < 2014110400) {

        // Define field timemodified to be added to report_builder.
        $table = new xmldb_table('report_builder');
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'toolbarsearch');

        // Conditionally launch add field timemodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2014110400, 'totara', 'reportbuilder');
    }

    if ($oldversion < 2014110401) {

        // Define field timemodified to be added to report_builder_saved.
        $table = new xmldb_table('report_builder_saved');
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'ispublic');

        // Conditionally launch add field timemodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2014110401, 'totara', 'reportbuilder');
    }

    if ($oldversion < 2014110402) {

        // Define table report_builder_graph to be created.
        $table = new xmldb_table('report_builder_graph');

        // Adding fields to table report_builder_graph.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('reportid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('stacked', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('maxrecords', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '500');
        $table->add_field('category', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('legend', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('series', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('settings', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table report_builder_graph.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('repobuilcolu_rep_fk', XMLDB_KEY_FOREIGN, array('reportid'), 'report_builder', array('id'));

        // Conditionally launch create table for report_builder_graph.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2014110402, 'totara', 'reportbuilder');
    }

    return true;
}
