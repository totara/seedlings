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

abstract class totara_sync_element {
    public $config;

    /**
     * Returns the element's name to be used for construction of classes, etc.
     *
     * To be implemented in child classes
     */
    abstract function get_name();

    abstract function has_config();

    /**
     * Element config form elements
     *
     * To be implemented in child classes
     */
    abstract function config_form(&$mform);

    abstract function config_save($data);

    /**
     * Function that handles sync between external sources and Totara
     *
     * To be implemented in child classes
     *
     * @throws totara_sync_exception
     */
    abstract function sync();

    function __construct() {
        if ($this->has_config()) {
            $this->config = get_config($this->get_classname());
        }
    }

    function get_classname() {
        return get_class($this);
    }

    function get_sources() {
        global $CFG;

        $elname = $this->get_name();

        // Get all available sync element files
        $sdir = $CFG->dirroot.'/admin/tool/totara_sync/sources/';
        $pattern = '/^source_' . $elname . '_(.*?)\.php$/';
        $sfiles = preg_grep($pattern, scandir($sdir));
        $sources = array();
        foreach ($sfiles as $f) {
            require_once($sdir.$f);

            $basename = basename($f, '.php');
            $sname = str_replace("source_{$elname}_", '', $basename);

            $sclass = "totara_sync_{$basename}";
            if (!class_exists($sclass)) {
                continue;
            }

            $sources[$sname] = new $sclass;
        }

        return $sources;
    }

    /**
     * Get the enabled source for the element
     *
     * @param string sourceclass name
     * @return stdClass source object or false if no source could be detemined
     * @throws totara_sync_exception
     */
    function get_source($sourceclass=null) {
        global $CFG;

        $elname = $this->get_name();

        if (empty($sourceclass)) {
            // Get enabled source
            if (!$sourceclass = get_config('totara_sync', 'source_' . $elname)) {
                throw new totara_sync_exception($elname, 'getsource', 'nosourceenabled');
            }
        }
        $sourcefilename = str_replace('totara_sync_' ,'', $sourceclass);

        $sourcefile = $CFG->dirroot.'/admin/tool/totara_sync/sources/'.$sourcefilename.'.php';
        if (!file_exists($sourcefile)) {
            throw new totara_sync_exception($elname, 'getsource', 'sourcefilexnotfound', $sourcefile);
        }

        require_once($sourcefile);

        if (!class_exists($sourceclass)) {
            throw new totara_sync_exception($elname, 'getsource', 'sourceclassxnotfound', $sourceclass);
        }

       return new $sourceclass;
    }

    /**
     * Gets the element's source's sync table
     *
     * @return string sync table name, e.g mdl_totara_sync_org
     * @throws totara_sync_exception
     */
    function get_source_sync_table() {
        $source = $this->get_source();
        if (!method_exists($source, 'get_sync_table')) {
            // Method to retrieve recordset does not exist, die!
            throw new totara_sync_exception($this->get_name(), 'getsource', 'nosynctablemethodforsourcex', $source->get_name());
        }

        return $source->get_sync_table();
    }

    /**
     * Gets the element's source's sync table clone
     *
     * @return string name of sync table clone, e.g mdl_totara_sync_org
     * @throws totara_sync_exception
     */
    function get_source_sync_table_clone($temptable) {
        $source = $this->get_source();
        if (!method_exists($source, 'get_sync_table_clone')) {
            // Don't continue if no recordset can be retrieved
            throw new totara_sync_exception($this->get_name(), 'getsource', 'nosynctablemethodforsourcex', $source->get_name());
        }

        return $source->get_sync_table_clone();
    }

    /**
     * Is element syncing enabled?
     *
     * @return boolean
     */
    function is_enabled() {
        return get_config('totara_sync', 'element_'.$this->get_name().'_enabled');
    }

    /**
     * Enable element syncing
     */
    function enable() {
        return set_config('element_'.$this->get_name().'_enabled', '1', 'totara_sync');
    }

    /**
     * Disable element syncing
     */
    function disable() {
        return set_config('element_'.$this->get_name().'_enabled', '0', 'totara_sync');
    }

    /**
     * Add sync log message
     */
    function addlog($info, $type='info', $action='') {
        // false param avoid showing error messages on the main page when running sync
        totara_sync_log($this->get_name(), $info, $type, $action, false);
    }

    /**
     * Set element config value
     */
    function set_config($name, $value) {
        return set_config($name, $value, $this->get_classname());
    }
}
