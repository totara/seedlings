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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package totara
 * @subpackage cohort
 */

/**
 * DB upgrades for Totara Sync
 */

function xmldb_tool_totara_sync_upgrade($oldversion) {

    global $CFG, $DB;

    $dbman = $DB->get_manager();

    // Totara 2.2+ upgrade

    if ($oldversion < 2012101100) {
        // Rename to deleted
        $sql = "UPDATE {config_plugins}
            SET name = 'fieldmapping_deleted'
            WHERE plugin = 'totara_sync_source_user_csv'
            AND name = 'fieldmapping_delete' ";
        $DB->execute($sql);

        // Rename to deleted
        $sql = "UPDATE {config_plugins}
            SET name = 'import_deleted'
            WHERE plugin = 'totara_sync_source_user_csv'
            AND name = 'import_delete' ";
        $DB->execute($sql);

        // Set "delete" as the default source name if no field mapping already exists
        // This will allow the existing sources to remain unchanged.
        $sql = "UPDATE {config_plugins}
            SET value = 'delete'
            WHERE plugin = 'totara_sync_source_user_csv'
            AND name = 'fieldmapping_deleted'
            AND " . $DB->sql_compare_text('value') . " = ''";
        $DB->execute($sql);

        upgrade_plugin_savepoint(true, 2012101100, 'tool', 'totara_sync');
    }

    //manual modifying permissions in $DB to retain any existing permissions
    if ($oldversion < 2012121200) {
        $oldname = 'tool/totara_sync:setfilesdirectory';
        $newname = 'tool/totara_sync:setfileaccess';

        $sql_capability = "UPDATE {capabilities} SET name = ? WHERE name = ?";
        $DB->execute($sql_capability, array($newname, $oldname));

        $sql_role_capability = "UPDATE {role_capabilities} SET capability = ? WHERE capability = ?";
        $DB->execute($sql_role_capability, array($newname, $oldname));

        upgrade_plugin_savepoint(true, 2012121200, 'tool', 'totara_sync');
    }

    if ($oldversion < 2013031400) {
        $table = new xmldb_table('totara_sync_log');
        $field = new xmldb_field('runid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, 0, 'info');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('runid', XMLDB_INDEX_NOTUNIQUE, array('runid'));

        // Conditionally launch add index runid
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        //automatically add the column to the embedded sync_log
        $params = array('shortname' => 'totarasynclog', 'source' => 'totara_sync_log', 'embedded' => 1);
        if ($report = $DB->get_record('report_builder', $params)) {
            $sortorder = $DB->get_field('report_builder_columns', 'MAX(sortorder) + 1', array('reportid' => $report->id));
            $sortorder = !empty($sortorder) ? $sortorder : 1;

            $todb = new stdClass();
            $todb->reportid = $report->id;
            $todb->type = $report->source;
            $todb->value = 'runid';
            $todb->heading = get_string('runid', 'tool_totara_sync');
            $todb->sortorder = $sortorder;
            $todb->hidden = 0;
            $todb->customheading = 0;

            $params = array('reportid' => $report->id, 'type' => $report->source, 'value' => 'runid');
            if (!$DB->record_exists('report_builder_columns', $params)) {
                $DB->insert_record('report_builder_columns', $todb);
            }
        }

        upgrade_plugin_savepoint(true, 2013031400, 'tool', 'totara_sync');
    }

    if ($oldversion < 2013092000) {
        // Set the lastnotify flag so emails with entire log file don't get sent out on first run.
        set_config('lastnotify', time(), 'totara_sync');

        upgrade_plugin_savepoint(true, 2013092000, 'tool', 'totara_sync');
    }

    if ($oldversion < 2013101500) {
        // Add sync actions for all elements.
        $actions = array('allow_create', 'allow_update', 'allow_delete');

        $params = array('plugin' => 'totara_sync_element_user', 'name' => 'removeuser');
        if ($oldsetting = $DB->get_record('config_plugins', $params)) {
            $DB->delete_records('config_plugins', $params);
        }

        foreach ($actions as $action) {
            $params = array('plugin' => 'totara_sync_element_user', 'name' => $action);
            if (!$DB->record_exists('config_plugins', $params)) {
                $newsetting = new stdClass();
                $newsetting->plugin = 'totara_sync_element_user';
                $newsetting->name   = $action;
                $newsetting->value  = $action == 'allow_delete' ? !empty($oldsetting->value) : 1; // Keep the previously set value.
                $DB->insert_record('config_plugins', $newsetting);
            }
        }

        foreach (array('org', 'pos') as $element) {
            $params = array('plugin' => "totara_sync_element_{$element}", 'name' => 'removeitems');
            if ($oldsetting = $DB->get_record('config_plugins', $params)) {
                $DB->delete_records('config_plugins', $params);
            }

            foreach ($actions as $action) {
                $params = array('plugin' => "totara_sync_element_{$element}", 'name' => $action);
                if (!$DB->record_exists('config_plugins', $params)) {
                    $newsetting = new stdClass();
                    $newsetting->plugin = "totara_sync_element_{$element}";
                    $newsetting->name   = $action;
                    $newsetting->value  = $action == 'allow_delete' ? !empty($oldsetting->value) : 1; // Keep the previously set value.
                    $DB->insert_record('config_plugins', $newsetting);
                }
            }
        }

        upgrade_plugin_savepoint(true, 2013101500, 'tool', 'totara_sync');
    }

    return true;
}
