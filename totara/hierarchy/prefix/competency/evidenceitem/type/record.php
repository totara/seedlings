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
require_once("{$CFG->dirroot}/totara/hierarchy/prefix/competency/evidence/evidence.php");

/**
 * Competency evidence criteria record
 */
class comp_criteria_record extends data_object {

    /**
     * Database table
     * @var string
     */
    public $table = 'comp_criteria_record';

    /**
     * Database required fields
     * @var array
     */
    public $required_fields = array(
        'id', 'userid', 'competencyid', 'itemid', 'status', 'proficiencymeasured', 'timecreated', 'timemodified'
    );

    /**
     * Add this evidence to a competency
     *
     * @return  void
     */
    public function save() {
        $now = time();

        // Set up some stuff
        if (!$this->timecreated) {
            $this->timecreated = $now;
        }

        $this->timemodified = $now;

        // Update database
        if (!$this->id) {
            if (!$this->insert()) {
                print_error('insertevidenceitem', 'totara_hierarchy');
            }
        }
        else {
            if (!$this->update()) {
                print_error('updateevidenceitem', 'totara_hierarchy');
            }
        }

        // Create competency evidence record (if does not already exist)
        $data = array(
            'competencyid'      => $this->competencyid,
            'userid'            => $this->userid
        );

        $competency = new competency_evidence($data);
        $competency->trigger_reaggregation();
    }
}
