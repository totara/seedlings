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
 * @subpackage totara_sync
 */

require_once($CFG->dirroot.'/admin/tool/totara_sync/lib.php');

abstract class totara_sync_source {
    protected $config;

    /**
     * The temp table name to be used for holding data from external source
     * Set this in the child class constructor
     */
    public $temptablename;

    /**
     * Directory root for all elements
     * @var string
     */
    public $filesdir;

    abstract function has_config();

    /**
     * Hook for adding source plugin-specific config form elements
     */
    abstract function config_form(&$mform);

    /**
     * Hook for saving source plugin-specific data
     */
    abstract function config_save($data);

    /**
     * Implementation of data import to the sync table
     *
     * @return sync table name (without prefix), e.g totara_sync_org
     * @throws totara_sync_exception if error
     */
    abstract function get_sync_table();

    /**
     * Define and create temp table necessary for element syncing
     */
    abstract function prepare_temp_table($clone = false);

    /**
     * Returns the name of the element this source applies to
     */
    abstract function get_element_name();

    /**
     * Returns whether the source uses files (e.g CSV) for syncing or not (e.g LDAP)
     *
     * @return boolean
     */
    abstract function uses_files();

    /**
     * Returns the source file location (used if uses_files returns true)
     *
     * @return string
     */
    abstract function get_filepath();


    /**
     * Remember to call parent::__construct() in child classes
     */
    function __construct() {
        $this->config = get_config($this->get_name());
        if (empty($this->config->delimiter)) {
            $this->config->delimiter = ',';
        }
        $this->filesdir = rtrim(get_config('totara_sync', 'filesdir'), '/');

        // Ensure child class specified temptablename
        if (!isset($this->temptablename)) {
            throw totara_sync_exception($this->get_element_name, 'setup', 'error',
                'Programming error - source class for ' . $this->get_name() .
                ' needs to specify temptablename in constructor');
        }
    }

    /**
     * Gets the class name of the element source
     *
     * @return string the child class name
     */
    function get_name() {
        return get_class($this);
    }

    /**
     * Method for setting source plugin config settings
     */
    function set_config($name, $value) {
        return set_config($name, $value, $this->get_name());
    }

    /**
     * Method for getting source plugin config settings
     */
    function get_config($name) {
        return get_config($this->get_name(), $name);
    }
    /**
     * Add source sync log entries to the sync database with this method
     */
    function addlog($info, $type='info', $action='') {
        // Avoid getting an error from the database trying to save a value longer than length limit (255 characters)
        if (strlen($info) > 255) {
            $info = substr($info, 0, 255);
        }
        totara_sync_log($this->get_element_name(), $info, $type, $action);
    }

    /**
     * drop the temporary source table (if applicable)
     *
     * @return true
     * @throws dml_exception if error
     */
    function drop_table() {
        global $DB;

        if (empty($this->temptablename)) {
            // no temptable
            return true;
        }

        $dbman = $DB->get_manager(); // We are going to use database_manager services

        $table = new xmldb_table($this->temptablename);
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table); // And drop it
        }

        // drop any clones
        $table = new xmldb_table($this->temptablename . '_clone');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table); // And drop it
        }

        return true;
    }

    /**
     * Create clone of temp table because MySQL cannot reference temp
     * table twice in a query
     *
     * @return mixed Returns false if failed or the name of temporary table if successful
     */
    function get_sync_table_clone() {
        global $DB;

        try {
            $temptable_clone = $this->prepare_temp_table(true);
        } catch (dml_exception $e) {
            throw new totara_sync_exception($this->get_element_name(), 'importdata',
                'temptableprepfail', $e->getMessage());
        }

        // Can't reuse $this->import_data($temptable) because the CSV file gets renamed,
        // so it fails when calling again
        //to be cross-database compliant especially for MSSQL we need to use the $temptable column names
        $fields = $temptable_clone->getFields();
        $fieldnames = array();
        foreach ($fields as $field) {
            if ($field->getName() != 'id') {
                $fieldnames[] = $field->getName();
            }
        }
        $fieldlist = implode(",", $fieldnames);
        $sql = "INSERT INTO {{$temptable_clone->getName()}} ($fieldlist) SELECT $fieldlist FROM {{$this->temptablename}}";
        $DB->execute($sql);

        return $temptable_clone->getName();
    }

    /**
     * Return OS formatted path to sync files
     *
     * @param string $path path to file in filesdir (optional)
     * @return string
     */
    function get_canonical_filesdir($path = '') {
        // Make canonical name if possible.
        $realdir = realpath($this->filesdir);
        if ($realdir != false) {
            // Canonize rest of name when we sure that path is recognized by OS.
            $realdir .= str_replace('/', DIRECTORY_SEPARATOR, $path);
        } else {
            // Leave as is.
            $realdir = $this->filesdir . $path;
        }
        return $realdir;
    }

    /**
     * Check if length limit for a field is exceeded
     *
     * @param array $datarows contains all rows from the CSV file
     * @param array $columnsinfo contains the metadata of the fields to import from the CSV file
     * @param array $fieldmappings contains mapped fields from the CSV file
     * @param string $source source type (user, org, pos)
     */
    function check_length_limit(&$datarows, $columnsinfo, $fieldmappings, $source) {
        foreach ($datarows as $i => $datarow) {
            $isexceeded = false;
            foreach ($datarow as $name => $value) {
                if ((($columnsinfo[$name]->type == 'varchar') && strlen($value)) && (strlen($value) > $columnsinfo[$name]->max_length)) {
                    $field = in_array($name, $fieldmappings) ? array_search($name, $fieldmappings) : $name;
                    $this->addlog(get_string('lengthlimitexceeded', 'tool_totara_sync', (object)array('idnumber' => $datarow['idnumber'], 'field' => $field,
                        'value' => $value, 'length' => $columnsinfo[$name]->max_length, 'source' => $source)), 'error', 'populatesynctablecsv');
                    $isexceeded = true;
                }
            }
            if ($isexceeded) {
                unset($datarows[$i]);
            }
        }
    }
}
