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
 * @package totara_program
 * @subpackage tests_generator
 */

/**
* Data generator.
*
* @package    totara_program
* @category   test
*/

defined('MOODLE_INTERNAL') || die();

class totara_program_generator extends component_generator_base {
    protected $programcount = 0;

    /**
     * Assign users to a program
     *
     * @param int $programid Program id
     * @param int $assignmenttype Assignment type
     * @param int $itemid item to be assigned to the program. e.g Audience, position, organization, individual
     * @param null $record
     */
    public function assign_to_program($programid, $assignmenttype, $itemid, $record = null) {
        // Set completion values. (No sure what to put in here)
        $completiontime = (isset($record['completiontime'])) ? $record['completiontime'] : -1;
        $completionevent = (isset($record['completionevent'])) ? $record['completionevent'] : 0;
        $completioninstance = (isset($record['completioninstance'])) ? $record['completioninstance'] : 0;

        // Create data.
        $data = new stdClass();
        $data->id = $programid;
        $data->item = array($assignmenttype => array($itemid => 1));
        $data->completiontime = array($assignmenttype => array($itemid => $completiontime));
        $data->completionevent = array($assignmenttype => array($itemid => $completionevent));
        $data->completioninstance = array($assignmenttype => array($itemid => $completioninstance));

        // Assign item to program.
        $assignmenttoprog = prog_assignments::factory($assignmenttype);
        $assignmenttoprog->update_assignments($data, false);

    }

    /**
     * Add course to program
     *
     * @param int $programid id Program id
     * @param array $courseids of int Course id
     */
    public function add_courseset_program($programid, $courseids, $certifpath = CERTIFPATH_CERT) {
        global $CFG, $CERTIFPATHSUF;
        require_once($CFG->dirroot . '/totara/certification/lib.php');

        $rawdata = new stdClass();
        $rawdata->id = $programid;
        $rawdata->contentchanged = 1;
        $rawdata->contenttype = 1;
        $rawdata->setprefixes = '999';
        $rawdata->{'999courses'} = implode(',', $courseids);
        $rawdata->{'999contenttype'} = 1;
        $rawdata->{'999id'} = 0;
        $rawdata->{'999label'} = '';
        $rawdata->{'999sortorder'} = 2;
        $rawdata->{'999contenttype'} = 1;
        $rawdata->{'999nextsetoperator'} = '';
        $rawdata->{'999completiontype'} = 1;
        $rawdata->{'999timeallowedperiod'} = 2;
        $rawdata->{'999timeallowednum'} = 1;

        if ($certifpath === CERTIFPATH_RECERT) { // Re-certification path.
            $rawdata->setprefixes_rc = 999;
            $rawdata->certifpath_rc = CERTIFPATH_RECERT;
            $rawdata->iscertif = 1;
            $rawdata->contenttype_rc = 1;
            $rawdata->{'999certifpath'} = 2;
            $rawdata->contenttype_rc = 1;
        } else { // Certification path.
            $rawdata->setprefixes_ce = 999;
            $rawdata->certifpath_ce = CERTIFPATH_CERT;
            $rawdata->iscertif = 0;
            $rawdata->{'999certifpath'} = 1;
            $rawdata->contenttype_ce = 1;
        }

        $program = new program($programid);
        $programcontent = $program->get_content();
        $programcontent->setup_content($rawdata);
        $programcontent->save_content();
    }

    /**
     * Create mock program
     *
     * @param array $data Override default properties
     * @return stdClass Program record
     */
    public function create_program($data = array()) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/totara/program/program_messages.class.php');

        $this->programcount++;
        $now = time();
        $sortorder = $DB->get_field('prog', 'MAX(sortorder) + 1', array());
        $default = array(
            'fullname' => 'Program ' . $this->programcount,
            'availablefrom' => 0,
            'availableuntil' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => 2,
            'category' => 1,
            'shortname' => '',
            'idnumber' => '',
            'available' => 1,
            'sortorder' => !empty($sortorder) ? $sortorder : 0,
            'icon' => 1,
            'exceptionssent' => 0,
            'visible' => 1,
            'summary' => '',
            'endnote' => '',
            'audiencevisible' => 2,
            'certifid' => null,
            'category' => $DB->get_field_select('course_categories', "MIN(id)", "parent=0")
        );
        $properties = array_merge($default, $data);

        $todb = (object)$properties;
        $newid = $DB->insert_record('prog', $todb);
        $program = new program($newid);

        // Create message manager to add default messages.
        new prog_messages_manager($newid, true);

        return $program;
    }

    public function fix_program_sortorder($categoryid = 0) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/totara/program/lib.php');

        if (empty($categoryid)) {
            $categoryid = $DB->get_field_select('course_categories', "MIN(id)", "parent=0");
        }

        // Call prog_fix_program_sortorder to ensure new program is displayed properly and the counts are updated.
        // Needs to be called at the very end!
        prog_fix_program_sortorder($categoryid);
    }

    /**
     * Assign users to a program by method.
     *
     * @param int $programid Program id
     * @param string $method method to  id to be added to the audience of last user
     * @param $items
     */
    public function assign_users_by_method($programid, $method, $items) {
        foreach ($items as $item) {
            $this->assign_to_program($programid, $method, $item);
        }
    }

    /**
     * Create certification settings.
     *
     * @param int $programid Program id
     * @param string $activeperiod
     * @param string $windowperiod
     * @param int $recertifydatetype
     */
    public function create_certification_settings($programid, $activeperiod, $windowperiod, $recertifydatetype) {
        global $DB;

        $certification_todb = new stdClass;
        $certification_todb->learningcomptype = CERTIFTYPE_PROGRAM;
        $certification_todb->activeperiod = $activeperiod;
        $certification_todb->windowperiod = $windowperiod;
        $certification_todb->recertifydatetype = $recertifydatetype;
        $certification_todb->timemodified = time();
        $certifid = $DB->insert_record('certif', $certification_todb);
        if ($certifid) {
            $DB->set_field('prog', 'certifid', $certifid , array('id' => $programid));
        }
    }

    /**
     * Get random certification setting.
     */
    public function get_random_certification_setting() {
        global $CFG;
        require_once($CFG->dirroot . '/totara/certification/lib.php');

        $certifsettings = array(
            array('3 day',   '3 day',   CERTIFRECERT_EXPIRY),
            array('3 day',   '2 day',   CERTIFRECERT_EXPIRY),
            array('5 day',   '2 day',   CERTIFRECERT_EXPIRY),
            array('1 week',  '3 day',   CERTIFRECERT_EXPIRY),
            array('1 year',  '2 month', CERTIFRECERT_EXPIRY),
            array('2 month', '1 week',  CERTIFRECERT_COMPLETION),
        );

        return $certifsettings[rand(0, count($certifsettings) - 1)];
    }

}
