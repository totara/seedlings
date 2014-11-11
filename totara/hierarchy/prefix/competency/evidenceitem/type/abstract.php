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
 * @package totara
 * @subpackage totara_hierarchy
 */

require_once("{$CFG->dirroot}/completion/data_object.php");

/**
 * Competency evidence type constants
 * Primarily for storing evidence type in the database
 */
define('COMPETENCY_EVIDENCE_TYPE_ACTIVITY_COMPLETION',  'activitycompletion');
define('COMPETENCY_EVIDENCE_TYPE_COURSE_COMPLETION',    'coursecompletion');
define('COMPETENCY_EVIDENCE_TYPE_COURSE_GRADE',         'coursegrade');

/**
 * Competency evidence type constant to class name mapping
 */
global $COMPETENCY_EVIDENCE_TYPES;
$COMPETENCY_EVIDENCE_TYPES = array(
    COMPETENCY_EVIDENCE_TYPE_ACTIVITY_COMPLETION    => 'activitycompletion',
    COMPETENCY_EVIDENCE_TYPE_COURSE_COMPLETION      => 'coursecompletion',
    COMPETENCY_EVIDENCE_TYPE_COURSE_GRADE           => 'coursegrade',
);

/**
 * An abstract object that holds methods and attributes common to all
 * competency evidence criteria type objects
 * @abstract
 */
abstract class competency_evidence_type extends data_object {

    /**
     * Database table
     * @var string
     */
    public $table = 'comp_criteria';

    /**
     * Evidence item type, to be defined in child classes
     * @var string
     */
    public $itemtype;

    /**
     * Database required fields
     * @var array
     */
    public $required_fields = array(
        'id', 'competencyid', 'itemtype', 'itemmodule', 'iteminstance', 'timecreated', 'timemodified', 'usermodified', 'linktype'
    );

    /**
     * Create and return new class appropriate to evidence type
     *
     * @param   $data   object|int  Database record or record pkey
     * @return  object  comptency_evidence_type_*
     */
    public static function factory($data) {
        global $CFG, $DB, $COMPETENCY_EVIDENCE_TYPES;

        // If supplied an ID, load record
        if (is_numeric($data)) {
            $data = (array)$DB->get_record('comp_criteria', array('id' => $data));
        }

        // Check this competency evidence type is installed
        if (!isset($data['itemtype']) || !isset($COMPETENCY_EVIDENCE_TYPES[$data['itemtype']])) {
            print_error('invalidevidencetype', 'totara_hierarchy');
        }

        // Load class file
        require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/evidenceitem/type/'.$data['itemtype'].'.php');
        $class = 'competency_evidence_type_'.$data['itemtype'];

        // Create new and return
        return new $class($data, false);
    }

    /**
     * Add this evidence to a competency
     *
     * @param   $competency Competency object
     * @return  mixed The ID of the newly created evidence record, or false if the record is a duplicate
     */
    public function add($competency) {
        global $USER, $DB;

        // Don't allow duplicate evidence items
        $params = array(
            'competencyid' => $competency->id,
            'iteminstance' => $this->iteminstance
        );

        $wherestr = "
            competencyid = :competencyid
            AND iteminstance = :iteminstance
        ";

        if (isset($this->itemtype)) {
            $params['itemtype'] = $this->itemtype;
            $wherestr .= 'AND itemtype = :itemtype';
        }

        if (isset($this->itemmodule)) {
            $params['itemmodule'] = $this->itemmodule;
            $wherestr .= 'AND itemmodule = :itemmodule';
        }

        if ($DB->count_records_select('comp_criteria', $wherestr, $params) ) {
            return false;
        }

        $now = time();

        // Set up some stuff
        $this->competencyid = $competency->id;
        $this->timecreated = $now;
        $this->timemodified = $now;
        $this->usermodified = $USER->id;

        // Insert into database
        $newid = parent::insert();
        if (!$newid) {
            print_error('insertevidenceitem', 'totara_hierarchy');
        }

        // Update evidence count
        // Get latest count
        $count = $DB->get_field('comp_criteria', 'COUNT(*)', array('competencyid' => $competency->id));
        $todb = new stdClass();
        $todb->id = $competency->id;
        $todb->evidencecount = (int) $count;

        if (!$DB->update_record('comp', $todb)) {
            print_error('updatecompetencyevidencecount', 'totara_hierarchy');
        }
        return $newid;
    }

    /**
     * Delete this evidence item from a competency
     *
     * @param   $competency Competency object
     * @return  void
     */
    public function delete($competency = null) {
        global $DB;

        // Delete evidence item from database
        if (!parent::delete()) {
            print_error('deleteevidencetype', 'totara_hierarchy');
        }

        // Delete any evidence items evidence
        $DB->delete_records('comp_criteria_record', array('itemid' => $this->id));

        // Update evidence count
        // Get latest count
        $count = $DB->get_field('comp_criteria', 'COUNT(*)', array('competencyid' => $competency->id));
        $todb = new stdClass();
        $todb->id = $competency->id;
        $todb->evidencecount = (int) $count;

        if (!$DB->update_record('comp', $todb)) {
            print_error('updatecompetencyevidencecount', 'totara_hierarchy');
        }
    }

    /**
     * Get human readable type name
     *
     * @return  string
     */
    public function get_type_name() {
        return get_string('evidence'.$this->itemtype, 'totara_hierarchy');
    }

    /**
     * Get human activity type
     *
     * @return  string
     */
    public function get_activity_type() {
        return $this->itemmodule;
    }

    /**
     * Return evidence name and link
     * Defined by child classes
     *
     * @return  string
     */
    abstract public function get_name();

    /**
     * Return evidence item type and link
     * Defined by child classes
     *
     * @return  string
     */
    abstract public function get_type();
}
