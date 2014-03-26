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

require_once($CFG->dirroot.'/admin/tool/totara_sync/sources/classes/source.user.class.php');
require_once($CFG->dirroot.'/admin/tool/totara_sync/lib.php');
require_once($CFG->dirroot.'/totara/core/js/lib/setup.php');
require_once($CFG->dirroot.'/admin/tool/totara_sync/sources/databaselib.php');

class totara_sync_source_user_database extends totara_sync_source_user {

    function config_form(&$mform) {
        global $PAGE;

        $this->config->import_idnumber = "1";
        $this->config->import_username = "1";
        $this->config->import_timemodified = "1";
        $this->config->import_deleted = (isset($this->element->config->sourceallrecords) && $this->element->config->sourceallrecords == 0) ? "1" : '0';

        // Display required db table columns
        $fieldmappings = array();

        foreach ($this->fields as $f) {
            if (!empty($this->config->{'fieldmapping_'.$f})) {
                $fieldmappings[$f] = $this->config->{'fieldmapping_'.$f};
            }
        }
        foreach ($this->customfields as $key => $f) {
            if (!empty($this->config->{'fieldmapping_'.$key})) {
                $fieldmappings[$key] = $this->config->{'fieldmapping_'.$key};
            }
        }

        $dbstruct = array();
        foreach ($this->fields as $f) {
            if (!empty($this->config->{'import_'.$f})) {
                $dbstruct[] = !empty($fieldmappings[$f]) ? $fieldmappings[$f] : $f;
            }
        }
        foreach (array_keys($this->customfields) as $f) {
            if (!empty($this->config->{'import_'.$f})) {
                $dbstruct[] = !empty($fieldmappings[$f]) ? $fieldmappings[$f] : $f;;
            }
        }

        $db_table = isset($this->config->{'database_dbtable'}) ? $this->config->{'database_dbtable'} : false;

        if (!$db_table) {
            $description = get_string('dbconnectiondetails', 'tool_totara_sync');
        } else {
            $db_table = $this->config->{'database_dbtable'};
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
        //Check database connection when saving
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

        // Get list of fields to be imported
        $fields = array();
        foreach ($this->fields as $f) {
            if (!empty($this->config->{'import_'.$f})) {
                $fields[] = $f;
            }
        }

        // Same for customfields
        foreach ($this->customfields as $name => $value) {
            if (!empty($this->config->{'import_'.$name})) {
                $fields[] = $name;
            }
        }

        // Sort out field mappings
        $fieldmappings = array();
        foreach ($fields as $i => $f) {
            if (empty($this->config->{'fieldmapping_'.$f})) {
                $fieldmappings[$f] = $f;
            } else {
                $fieldmappings[$f] = $this->config->{'fieldmapping_'.$f};
            }
        }

        // Finally, perform externaldb to totara db field mapping
        foreach ($fields as $i => $f) {
            if (in_array($f, array_keys($fieldmappings))) {
                $fields[$i] = $fieldmappings[$f];
            }
        }

        // Check that all fields exists in database
        foreach ($fields as $f) {
            try {
                $database_connection->get_field_sql("SELECT $f from $db_table", array(), IGNORE_MULTIPLE);
            } catch (Exception $e) {
                $this->addlog(get_string('dbmissingcolumnx', 'tool_totara_sync', $f), 'error', 'importdata');
                return false;
            }
        }

        unset($fieldmappings);


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

            foreach ($this->fields as $f) {
                if (!empty($this->config->{'import_'.$f})) {
                    if (!empty($this->config->{'fieldmapping_'.$f})) {
                        $dbrow[$f] = $extdbrow[$this->config->{'fieldmapping_'.$f}];
                    } else {
                        $dbrow[$f] = $extdbrow[$f];
                    }
                }
            }

            if (empty($extdbrow['timemodified'])) {
                $dbrow['timemodified'] = $now;
            } else {
                //try to parse the contents - if parse fails assume a unix timestamp and leave unchanged
                $parsed_date = totara_date_parse_from_format($csvdateformat, trim($extdbrow['timemodified']));
                if ($parsed_date) {
                    $dbrow['timemodified'] = $parsed_date;
                }
            }

            // Custom fields are special - needs to be json-encoded
            if (!empty($this->customfields)) {
                $cfield_data = array();
                foreach (array_keys($this->customfields) as $cf) {
                    if (!empty($this->config->{'import_'.$cf})) {
                        if (!empty($this->config->{'fieldmapping_'.$cf})) {
                            $value = trim($extdbrow[$this->config->{'fieldmapping_'.$cf}]);
                        } else {
                            $value = trim($extdbrow[$cf]);
                        }
                        if (!empty($value)) {
                            //get shortname and check if we need to do field type processing
                            $shortname = str_replace("customfield_", "", $cf);
                            $datatype = $DB->get_field('user_info_field', 'datatype', array('shortname' => $shortname));
                            switch ($datatype) {
                                case 'datetime':
                                    //try to parse the contents - if parse fails assume a unix timestamp and leave unchanged
                                    $parsed_date = totara_date_parse_from_format($csvdateformat, $value);
                                    if ($parsed_date) {
                                        $value = $parsed_date;
                                    }
                                    break;
                                default:
                                    break;
                            }
                        }
                        $cfield_data[$cf] = $value;
                        unset($dbrow[$cf]);
                    }
                }
                $dbrow['customfields'] = json_encode($cfield_data);
                unset($cfield_data);
            }

            $datarows[] = $dbrow;

            $rowcount++;

            if ($rowcount >= TOTARA_SYNC_DBROWS) {
                // bulk insert
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
