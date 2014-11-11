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
 * @author Maria Torres <maria.torres@totaralms.com>
 * @package totara_hierarchy
 * @subpackage tests_generator
 */

/**
* Data generator.
*
* @package    totara_hierarchy
* @category   test
*/

defined('MOODLE_INTERNAL') || die();

class totara_hierarchy_generator extends component_generator_base {

    /**
     * Create a framework for the shortprefix.
     *
     * @param string $shortprefix Prefix that identify the type of hierarchy (pos, org, etc)
     * @param string $fullname
     * @param array $record
     * @return bool|int hierarchy id
     */
    public function create_framework($shortprefix, $fullname, $record = array()) {
        global $USER, $DB;

        $record = (array) $record;

        if (!isset($record['visible'])) {
            $record['visible'] = 1;
        }

        if (!isset($record['description'])) {
            $record['description'] = 'created by tool generator';
        }

        if (!isset($record['fullname'])) {
            $record['fullname'] = $fullname;
        }

        if (!isset($record['sortorder'])) {
            $record['sortorder'] = $DB->get_field($shortprefix.'_framework', 'MAX(sortorder) + 1', array());
        }

        if (!isset($record['hidecustomfields'])) {
            $record['hidecustomfields'] = '0';
        }

        $record['timecreated'] = time();
        $record['timemodified'] = $record['timecreated'];
        $record['usermodified'] = $USER->id;

        if ($record['sortorder'] == null) {
            $record['sortorder'] = 1;
        }

        if ($framework = $DB->get_record($shortprefix.'_framework', array('fullname' => $record['fullname']))) {
            return $framework->id;
        }

        return $DB->insert_record($shortprefix.'_framework', $record);
    }

    /**
     * Create a hierarchy based on the shortprefix and assign it to a framework.
     *
     * @param $frameworkid
     * @param $prefix
     * @param string $fullname
     * @param null $record
     * @internal param string $shortprefix use in hierarchy
     * @return bool|int herarchy id
     */
    public function create_hierarchy($frameworkid, $prefix, $fullname, $record = null) {
        global $DB, $USER;

        $record = (array) $record;

        if (!isset($record['fullname'])) {
            $record['fullname'] = $fullname;
        }

        if (!isset($record['description'])) {
            $record['description'] = $record['fullname'];
        }

        if (!isset($record['visible'])) {
            $record['visible'] = 1;
        }

        if (!isset($record['hidecustomfields'])) {
            $record['hidecustomfields'] = 0;
        }

        if (!isset($record['parentid'])) {
            $record['parentid'] = 0;
        }

        $record['frameworkid'] = $frameworkid;
        $record['timecreated'] = time();
        $record['timemodified'] = $record['timecreated'];
        $record['usermodified'] = $USER->id;

        $shortprefix = hierarchy::get_short_prefix($prefix);
        if ($hierarchy = $DB->get_record($shortprefix, array('fullname' => $record['fullname']))) {
            return $hierarchy->id; // Hierarchy id.
        }

        $record = (object) $record;
        $hierarchy = hierarchy::load_hierarchy($prefix);
        $itemnew = $hierarchy->process_additional_item_form_fields($record);
        $item = $hierarchy->add_hierarchy_item($itemnew, $itemnew->parentid, $itemnew->frameworkid, false);

        return $item->id;
    }

    /**
     * Creates/gets Hierarchies.
     *
     * @param int $from
     * @param int $to
     * @param string $shortprefix
     * @param int $frameworkid
     * @return array of hierarchies
     */
    private function create_hierarchies($from, $to, $prefix, $frameworkid) {
        for ($i = $from; $i <= $to; $i++) {
            $fullname = 'tool_generator_' . str_pad($i, 6, '0', STR_PAD_LEFT);
            if ($hierarchy = $this->create_hierarchy($frameworkid, $prefix, $fullname)) {
                $hierarchies[$i] = (int) $hierarchy;
            }
        }

        return $hierarchies;
    }

    /**
     * Creates/gets Hierarchies.
     *
     * @param string $shortprefix
     * @param int $frameworkid
     * @param int $count
     * @return array of hierarchies
     */
    public function get_hierarchies($prefix, $frameworkid, $count) {
        global $DB;

        $result = array();
        $fullname = 'tool_generator_%';
        $shortprefix = hierarchy::get_short_prefix($prefix);
        $rs = $DB->get_recordset_select($shortprefix, $DB->sql_like('fullname', '?'), array($fullname), 'fullname', 'id, fullname');
        $nextnumber = 1;
        foreach ($rs as $record) {
            $matches = tool_generator_backend::get_number_match($fullname, $record->fullname);
            if (empty($matches)) {
                continue;
            }

            // Create missing hierarchies in range up to this.
            $number = (int) $matches[1];
            if ($number != $nextnumber) {
                $result += $this->create_hierarchies($nextnumber, min($number - 1, $count), $shortprefix, $frameworkid);
            } else {
                $result[$number] = (int)$record->id;
            }

            // Stop if we've got enough hierarchies.
            $nextnumber = $number + 1;
            if ($number >= $count) {
                break;
            }
        }
        $rs->close();

        // Create hierarchies as per required.
        if ($nextnumber <= $count) {
            $result += $this->create_hierarchies($nextnumber, $count, $prefix, $frameworkid);
        }

        return $result;
    }

    /**
     * Assign primary positions to a user.
     *
     * @param $userid
     * @param $managerid
     * @param $organisationid
     * @param $positionid
     * @param null $record
     * @return void
     */
    public function assign_primary_position($userid, $managerid, $organisationid, $positionid, $record = null) {
        $data = new stdClass();
        $data->type = (isset($record['type'])) ? $record['type'] : POSITION_TYPE_PRIMARY;
        $data->userid = (isset($record['userid'])) ? $record['userid'] : $userid;
        $data->managerid = (isset($record['managerid'])) ? $record['managerid'] : $managerid; // Assign manager to user position.
        $data->organisationid = (isset($record['organisationid'])) ? $record['organisationid'] : $organisationid; // Assign org.
        $data->positionid = (isset($record['positionid'])) ? $record['positionid'] : $positionid; // Assign pos.

        // Other fields.
        if (isset($record['timevalidfrom'])) {
            $data->timevalidfrom = $record['timevalidfrom'];
        }

        if (isset($record['timevalidto'])) {
            $data->timevalidto = $record['timevalidto'];
        }

        // Attempt to load the assignment.
        $position_assignment = new position_assignment(
            array(
                'userid'    => $data->userid,
                'type'      => $data->type
            )
        );
        $position_assignment::set_properties($position_assignment, $data); // Setup data.
        assign_user_position($position_assignment);
    }

    function get_subordinates($managerid){
        global $DB;

        return $DB->get_fieldset_select('pos_assignment', 'userid', 'managerid = :manager', array('manager' => $managerid));
    }

    function get_manager_hierarchy($parentid) {
        $tree = Array();
        if (!empty($parentid)) {
            $tree = $this->get_subordinates($parentid);
            if (!empty($tree)) {
                foreach ($tree as $key => $value) {
                    $ids = $this->get_manager_hierarchy($value);
                    $tree = array_merge($tree, $ids);
                }
            }
        }
        return $tree;
    }
}
