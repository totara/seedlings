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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage totara_sync
 */

require_once($CFG->dirroot.'/admin/tool/totara_sync/sources/classes/source.pos.class.php');
require_once($CFG->dirroot.'/admin/tool/totara_sync/lib.php');
require_once($CFG->dirroot.'/totara/core/js/lib/setup.php');
require_once($CFG->dirroot.'/admin/tool/totara_sync/sources/databaselib.php');

class totara_sync_source_pos_database extends totara_sync_source_pos {

    function config_form(&$mform) {
        global $PAGE;

        $this->config->import_idnumber = "1";
        $this->config->import_fullname = "1";
        $this->config->import_frameworkidnumber = "1";
        $this->config->import_timemodified = "1";

        // Display required db table columns
        $fieldmappings = array();

        foreach ($this->fields as $field) {
            if (!empty($this->config->{'fieldmapping_'.$field})) {
                $fieldmappings[$field] = $this->config->{'fieldmapping_'.$field};
            }
        }

        $dbstruct = array();
        foreach ($this->fields as $field) {
            if (!empty($this->config->{'import_'.$field})) {
                $dbstruct[] = !empty($fieldmappings[$field]) ? $fieldmappings[$field] : $field;
            }
        }

        $db_table = isset($this->config->{'database_dbtable'}) ? $this->config->{'database_dbtable'} : false;

        if (!$db_table) {
            $description = get_string('dbconnectiondetails', 'tool_totara_sync');
        } else {
            $dbstruct = implode(', ', $dbstruct);
            $description = get_string('tablemustincludexdb', 'tool_totara_sync', $db_table);
            $description .= html_writer::empty_tag('br') . $dbstruct;
        }

        $mform->addElement('html', html_writer::tag('div', html_writer::tag('p', $description), array('class' => 'informationbox')));

        $db_options = get_installed_db_drivers();

        // Database details
        $mform->addElement('select', 'database_dbtype', get_string('dbtype', 'tool_totara_sync'), $db_options);
        $mform->addElement('text', 'database_dbname', get_string('dbname', 'tool_totara_sync'));
        $mform->addRule('database_dbname', get_string('err_required', 'form'), 'required');
        $mform->setType('database_dbname', PARAM_ALPHANUMEXT);
        $mform->addElement('text', 'database_dbhost', get_string('dbhost', 'tool_totara_sync'));
        $mform->setType('database_dbhost', PARAM_ALPHANUMEXT);
        $mform->addElement('text', 'database_dbuser', get_string('dbuser', 'tool_totara_sync'));
        $mform->addRule('database_dbuser', get_string('err_required', 'form'), 'required');
        $mform->setType('database_dbuser', PARAM_ALPHANUMEXT);
        $mform->addElement('password', 'database_dbpass', get_string('dbpass', 'tool_totara_sync'));
        $mform->setType('database_dbpass', PARAM_TEXT);

        // Table name
        $mform->addElement('text', 'database_dbtable', get_string('dbtable', 'tool_totara_sync'));
        $mform->addRule('database_dbtable', get_string('err_required', 'form'), 'required');
        $mform->setType('database_dbtable', PARAM_ALPHANUMEXT);

        $mform->addElement('button', 'database_dbtest', get_string('dbtestconnection', 'tool_totara_sync'));

        // Javascript include
        local_js(array(TOTARA_JS_DIALOG));

        $PAGE->requires->strings_for_js(array('dbtestconnectsuccess', 'dbtestconnectfail'), 'tool_totara_sync');

        $jsmodule = array(
                'name' => 'totara_syncdatabaseconnect',
                'fullpath' => '/admin/tool/totara_sync/sources/sync_database.js',
                'requires' => array('json', 'totara_core'));

        $PAGE->requires->js_init_call('M.totara_syncdatabaseconnect.init', null, false, $jsmodule);

        parent::config_form($mform);
    }

    function config_save($data) {
        // Check database connection when saving
        try {
            setup_sync_DB($data->{'database_dbtype'}, $data->{'database_dbhost'}, $data->{'database_dbname'}, $data->{'database_dbuser'}, $data->{'database_dbpass'});
        } catch (Exception $e) {
            totara_set_notification(get_string('cannotconnectdbsettings', 'tool_totara_sync'), qualified_me());
        }

        $this->set_config('database_dbtype', $data->{'database_dbtype'});
        $this->set_config('database_dbname', $data->{'database_dbname'});
        $this->set_config('database_dbhost', $data->{'database_dbhost'});
        $this->set_config('database_dbuser', $data->{'database_dbuser'});
        $this->set_config('database_dbpass', $data->{'database_dbpass'});
        $this->set_config('database_dbtable', $data->{'database_dbtable'});

        parent::config_save($data);
    }

    function import_data($temptable) {
        global $CFG, $DB; // Careful using this in here as we have 2 database connections

        // Get database config
        $dbtype = $this->config->{'database_dbtype'};
        $dbname = $this->config->{'database_dbname'};
        $dbhost = $this->config->{'database_dbhost'};
        $dbuser = $this->config->{'database_dbuser'};
        $dbpass = $this->config->{'database_dbpass'};
        $db_table = $this->config->{'database_dbtable'};

        try {
            $database_connection = setup_sync_DB($dbtype, $dbhost, $dbname, $dbuser, $dbpass);
        } catch (Exception $e) {
            $this->addlog(get_string('databaseconnectfail', 'tool_totara_sync'), 'error', 'importdata');
        }

        // To get the row that are in the database table get one row then check the headers
        $db_fields = $database_connection->get_record_sql('SELECT * FROM ' . $db_table, array(), IGNORE_MULTIPLE);
        $db_columns = array_keys((array)$db_fields);

        // Get a list of customfields
        foreach ($db_columns as $index => $field) {
            if (!preg_match('/^customfield_/', $field)) {
                unset($db_columns[$index]);
            }
        }

        // Check which fields should be imported
        $fields = array();
        foreach ($this->fields as $field) {
            if (!empty($this->config->{'import_'.$field})) {
                $fields[] = $field;
            }
        }

        $fields = array_merge($fields, $db_columns);

        unset($db_columns);

        // Map fields
        $fieldmappings = array();
        foreach ($fields as $index => $field) {
            if (empty($this->config->{'fieldmapping_'.$field})) {
                $fieldmappings[$field] = $field;
            } else {
                $fieldmappings[$field] = $this->config->{'fieldmapping_'.$field};
            }
        }

        // Finally, perform externaldb to db field mapping
        foreach ($fields as $index => $field) {
            if (!preg_match('/^customfield_/', $field)) {
                if (in_array($field, array_keys($fieldmappings))) {
                    $fields[$index] = $fieldmappings[$field];
                }
            }
        }

        // Check that all fields exists in database
        foreach ($fields as $field) {
            try {
                $database_connection->get_field_sql("SELECT $field from $db_table", array(), IGNORE_MULTIPLE);
            } catch (Exception $e) {
                $this->addlog(get_string('dbmissingcolumnx', 'tool_totara_sync', $field), 'error', 'importdata');
                return false;
            }
        }


        // Populate temp sync table from remote database
        $now = time();
        $datarows = array();  // holds rows of data
        $rowcount = 0;
        $csvdateformat = (isset($CFG->csvdateformat)) ? $CFG->csvdateformat : get_string('csvdateformatdefault', 'totara_core');

        $columns = implode(', ', $fields);
        $fetch_sql = 'SELECT ' . $columns . ' FROM ' . $db_table;
        $data = $database_connection->get_recordset_sql($fetch_sql);

        foreach ($data as $row) {
            // Setup a db row
            $extdbrow = array_combine($fields, (array)$row);
            $dbrow = array();

            foreach ($this->fields as $field) {
                if (!empty($this->config->{'import_'.$field})) {
                    if (!empty($this->config->{'fieldmapping_'.$field})) {
                        $dbrow[$field] = $extdbrow[$this->config->{'fieldmapping_'.$field}];
                    } else {
                        $dbrow[$field] = $extdbrow[$field];
                    }
                }
            }

            $dbrow['parentidnumber'] = !empty($extdbrow['parentidnumber']) ? $extdbrow['parentidnumber'] : 0;
            $dbrow['typeidnumber'] = !empty($extdbrow['typeidnumber']) ? $extdbrow['typeidnumber'] : 0;
            if (empty($extdbrow['timemodified'])) {
                $dbrow['timemodified'] = $now;
            } else {
                //try to parse the contents - if parse fails assume a unix timestamp and leave unchanged
                $parsed_date = totara_date_parse_from_format($csvdateformat, trim($extdbrow['timemodified']));
                if ($parsed_date) {
                    $dbrow['timemodified'] = $parsed_date;
                }
            }
            // Custom fields - need to handle custom field formats like dates here
            $customfieldkeys = preg_grep('/^customfield_.*/', $fields);
            if (!empty($customfieldkeys)) {
                $customfields = array();
                foreach ($customfieldkeys as $key) {
                    // Get shortname and check if we need to do field type processing
                    $value = trim($extdbrow[$key]);
                    if (!empty($value)) {
                        $shortname = str_replace('customfield_', '', $key);
                        $datatype = $DB->get_field('pos_type_info_field', 'datatype', array('shortname' => $shortname));
                        switch ($datatype) {
                            case 'datetime':
                                // Try to parse the contents - if parse fails assume a unix timestamp and leave unchanged
                                $parsed_date = totara_date_parse_from_format($csvdateformat, $value);
                                if ($parsed_date) {
                                    $value = $parsed_date;
                                }
                                break;
                            default:
                                break;
                        }
                    }
                    $customfields[$key] = $value;
                    unset($dbrow[$key]);
                }

                $dbrow['customfields'] = json_encode($customfields);
            }

            $datarows[] = $dbrow;
            $rowcount++;

            if ($rowcount >= TOTARA_SYNC_DBROWS) {
                // Bulk insert
                if (!totara_sync_bulk_insert($temptable, $datarows)) {
                    $this->addlog(get_string('couldnotimportallrecords', 'tool_totara_sync'), 'error', 'populatesynctabledb');
                    return false;
                }

                $rowcount = 0;
                unset($datarows);
                $datarows = array();

                gc_collect_cycles();
            }
        }

        // Insert remaining rows
        if (!totara_sync_bulk_insert($temptable, $datarows)) {
            $this->addlog(get_string('couldnotimportallrecords', 'tool_totara_sync'), 'error', 'populatesynctabledb');
            return false;
        }

        return true;
    }
}
