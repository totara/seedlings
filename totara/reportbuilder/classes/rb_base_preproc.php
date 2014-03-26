<?php // $Id$
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

/**
 * Abstract base preprocessor class to be extended to create report builder
 * pre-processors
 *
 * Defines the properties and methods required by pre-processors and
 * implements some core methods used by all child classes
 */
abstract class rb_base_preproc {

    public $groupid;

/**
 * Class constructor
 *
 * Call from the constructor of all child classes with:
 *
 * <code>parent::__construct($groupid)</code>
 *
 * to ensure child class has implemented everything necessary to work.
 *
 */
    function __construct($groupid) {

        $this->groupid = $groupid;

        // check that child classes implement required properties
        $properties = array(
            'name',
            'prefix',
        );
        foreach ($properties as $property) {
            if (!property_exists($this, $property)) {
                throw new Exception("Property '$property' must be set in class " .
                    get_class($this));
            }
        }

    }

    /*
     * All sub classes must define the following functions
     */
    abstract function run($item, $lastchecked, &$message);
    abstract function get_all_items();
    abstract function is_initialized();
    abstract function initialize_group($item=null);
    abstract function drop_group_tables();


    /**
     * Given a group ID, return an array of items in that group
     *
     * @return array Array of items (usually IDs) in that group
     */
    function get_group_items() {
        global $DB;
        $groupid = $this->groupid;

        // group id of zero refers to all items
        // delegate getting all items to the specific pre-processor
        if ($groupid == 0) {
            return $this->get_all_items();
        }

        $items = $DB->get_records('report_builder_group_assign', array('groupid' => $groupid), 'itemid', 'itemid');
        return array_keys($items);
    }


    /**
     * Disable a particular item
     *
     * @param string $item Reference (usually an ID) to the item to disable
     *
     * @return boolean True if succeeds in disabling, false otherwise
     */
    function disable_item($item) {
        global $DB;

        $groupid = $this->groupid;
        // single record assured by unique index on fields
        if ($record = $DB->get_record('report_builder_preproc_track', array('groupid' => $groupid, 'itemid' => $item))) {
            $todb = new stdClass();
            $todb->id = $record->id;
            $todb->disabled = 1;
            return $DB->update_record('report_builder_preproc_track', $todb);
        } else {
            $todb = new stdClass();
            $todb->groupid = $groupid;
            $todb->itemid = $item;
            $todb->disabled = 1;
            $todb->lastchecked = time();
            return $DB->insert_record('report_builder_preproc_track', $todb);
        }
    }


    /**
     * Return associative array of items and when they were last processed
     *
     * Used to determine if an item needs to be preprocessed again
     *
     * @return array Associative array where key is itemid and value is
     *               timestamp of last process time
     */
    function get_track_info() {
        global $DB;

        $groupid = $this->groupid;
        // groupid/itemid fields have a unique index so every itemid
        // returned by this query will be unique
        return $DB->get_records('report_builder_preproc_track', array('groupid' => $groupid), 'itemid', 'itemid, lastchecked, disabled');
    }


    /**
     * Update or create tracking info for the item given
     * by setting the lastchecked to the current time.
     *
     * @param string $itemid Identifier (usually the ID) of the item to update
     *
     * @return boolean True if successful, false otherwise
     */
    function update_track_info($itemid) {
        global $DB;

        $groupid = $this->groupid;
        if ($record = $DB->get_record('report_builder_preproc_track', array('groupid' => $groupid, 'itemid' => $itemid))) {
            // update existing record
            $todb = new stdClass();
            $todb->id = $record->id;
            $todb->lastchecked = time();
            return $DB->update_record('report_builder_preproc_track', $todb);
        } else {
            // create a new record
            $todb = new stdClass();
            $todb->groupid = $groupid;
            $todb->itemid = $itemid;
            $todb->lastchecked = time();
            $todb->disabled = 0;
            return $DB->insert_record('report_builder_preproc_track', $todb);
        }
    }

} // end of rb_base_preproc class

