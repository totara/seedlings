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
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 *
 * Reportbuilder generators.
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir  . '/testing/generator/data_generator.php');

/**
 * This class intended to generate different mock entities
 *
 */
class totara_reportbuilder_cache_generator extends testing_data_generator {
    protected static $ind = 0;
    /**
     * Add particular mock params to cohort rules
     *
     * @staticvar int $paramid
     * @param int $ruleid
     * @param array $params Params to add
     * @param array $listofvalues List of values
     */
    public function create_cohort_rule_params($ruleid, $params, $listofvalues) {
        global $DB;
        $data = array($params);
        foreach ($listofvalues as $l) {
            $data[] = array('listofvalues' => $l);
        }
        foreach ($data as $d) {
            foreach ($d as $name => $value) {
                $todb = new stdClass();
                $todb->id = self::$ind;
                $todb->ruleid = $ruleid;
                $todb->name = $name;
                $todb->value = $value;
                $todb->timecreated = time();
                $todb->timemodified = time();
                $todb->modifierid = 2;
                $DB->insert_record('cohort_rule_params', $todb);
                self::$ind++;
            }
        }
    }

    /**
     * Create mock program
     *
     * @param array $data Override default properties
     * @return stdClass Program record
     */
    public function create_program($data = array()) {
        global $DB;
        self::$ind++;
        $now = time();
        $sortorder = $DB->get_field('prog', 'MAX(sortorder) + 1', array());
        $default = array('fullname' => 'Program ' . self::$ind,
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
                         'certifid' => null
                        );
        $properties = array_merge($default, $data);

        $todb = (object)$properties;
        $newid = $DB->insert_record('prog', $todb);
        $program = new program($newid);
        $messagemanager = new prog_messages_manager($newid, true);

        return $program;
    }

    /**
     * Create mock user with assigned manager
     *
     * @see phpunit_util::create_user
     * @global stdClass $DB
     * @param  array|stdClass $record
     * @param  array $options
     * @return stdClass
     */
    public function create_user($record = null, array $options = null) {
        global $DB;
        $user = parent::create_user($record, $options);

        if (is_object($record)) {
            $record = (array)$record;
        }
        // Assign manager for correct event messaging handler work.
        $managerid = isset($record['managerid']) ? $record['managerid'] : 2;
        $manager = array('managerid' => $managerid, 'fullname' => '', 'timecreated' => time(),
            'timemodified' => time(), 'usermodified' => 2, 'userid' => $user->id);
        $DB->insert_record('pos_assignment', (object)$manager);

        return $user;
    }

    /**
     * Get empty program assignment
     *
     * @param int $programid
     * @return stdClass
     */
    protected function get_empty_prog_assignment($programid) {
        $data = new stdClass();
        $data->id = $programid;
        $data->item = array(ASSIGNTYPE_INDIVIDUAL => array());
        $data->completiontime = array(ASSIGNTYPE_INDIVIDUAL => array());
        $data->completionevent = array(ASSIGNTYPE_INDIVIDUAL => array());
        $data->completioninstance = array(ASSIGNTYPE_INDIVIDUAL => array());
        return $data;
    }

    /**
     * Assign users to a program
     * @todo remove this when program generator is merged in.
     *
     * @param int $programid Program id
     * @param int $assignmenttype Assignment type
     * @param int $itemid item to be assigned to the program. e.g Audience, position, organization, individual
     * @param null $record
     */
    public function assign_to_program($programid, $assignmenttype, $itemid, $record = null) {
        // Set completion values.
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
        $program = new program($programid);
        $program->update_learner_assignments();
    }

    /**
     * Add mock program to user
     *
     * @param int $programid Program id
     * @param array $userids User ids array of int
     */
    public function assign_program($programid, $userids) {
        $data = $this->get_empty_prog_assignment($programid);
        $category = new individuals_category();
        $a = 0;
        foreach ($userids as $key => $userid) {
            $data->item[ASSIGNTYPE_INDIVIDUAL][$userid] = 1;
            $data->completiontime[ASSIGNTYPE_INDIVIDUAL][$userid] = -1;
            $data->completionevent[ASSIGNTYPE_INDIVIDUAL][$userid] = 0;
            $data->completioninstance[ASSIGNTYPE_INDIVIDUAL][$userid] = 0;
            unset($userids[$key]);
            $a++;
            if ($a > 500) {
                $a = 0;
                // Write chunk.
                $category->update_assignments($data);
            }
        }
        // Last chunk.
        $category->update_assignments($data);

        $program = new program($programid);
        $assignments = $program->get_assignments();
        $assignments->init_assignments($programid);
        $program->update_learner_assignments();
    }

    /**
     * Add course to program
     *
     * @param int $programid Program id
     * @param array $courseids of int Course id
     * @param int $certifpath
     */
    public function add_courseset_program($programid, $courseids, $certifpath = CERTIFPATH_CERT) {
        global $CERTIFPATHSUF;

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
     * @param int $userid User id
     * @param array|stdClass $record Ovveride default properties
     * @return stdClass Program record
     */
    public function create_plan($userid, $record = array()) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/totara/plan/lib.php');

        if (is_object($record)) {
            $record = (array)$record;
        }
        self::$ind++;

        $default = array(
            'templateid' => 0,
            'userid' => $userid,
            'name' => 'Learning plan '. self::$ind,
            'description' => '',
            'startdate' => null,
            'enddate' => time() + 23328000,
            'timecompleted' => null,
            'status' => DP_PLAN_STATUS_COMPLETE
        );
        $properties = array_merge($default, $record);

        $todb = (object)$properties;
        $newid = $DB->insert_record('dp_plan', $todb);

        $plan = new development_plan($newid);
        $plan->set_status(DP_PLAN_STATUS_UNAPPROVED, DP_PLAN_REASON_CREATE);
        $plan->set_status(DP_PLAN_STATUS_APPROVED);

        return $plan;
    }

    /**
     * Add multi-select custom field. All fields have default icon and are not default
     *
     * @param array $cfdef Format: array('fieldname' => array('option1', 'option2', ...), ...)
     * @param string $tableprefix
     * @return array id's of custom fields. Format: array('fieldname' => id, ...)
     */
    public function add_multiselect_cf($cfdef, $tableprefix) {
        global $DB;
        $result = array();
        foreach ($cfdef as $name => $options) {
            $data = new stdClass();
            $data->id = 0;
            $data->datatype = 'multiselect';
            $data->fullname = $name;
            $data->description = '';
            $data->defaultdata = '';
            $data->forceunique = 0;
            $data->hidden = 0;
            $data->locked = 0;
            $data->required = 0;
            $data->description_editor = array('text' => '', 'format' => 0);
            $data->multiselectitem = array();
            foreach ($options as $opt) {
                $data->multiselectitem[] = array('option' => $opt, 'icon' => 'default',
                        'default' => 0, 'delete' => 0);
            }
            $formfield = new customfield_define_multiselect();
            $formfield->define_save($data, $tableprefix);
            $sql = "SELECT id FROM {{$tableprefix}_info_field} WHERE ".
                    $DB->sql_compare_text('fullname') . ' = ' . $DB->sql_compare_text(':fullname');

            $result[$name] = $DB->get_field_sql($sql, array('fullname' => $name));
        }
        return $result;
    }

    /**
     * Enable one or more option for selected customfield
     *
     * @param stdClass $item - course/prog or other supported object
     * @param int $cfid - customfield id
     * @param array $options - option names to enable
     * @param string $prefix
     * @param string $tableprefix
     */
    public function set_multiselect_cf($item, $cfid, array $options, $prefix, $tableprefix) {
        $field = new customfield_multiselect($cfid, $item, $prefix, $tableprefix);
        $field->inputname = 'cftest';

        $data = new stdClass();
        $data->id = $item->id;
        $cfdata = array();
        foreach ($field->options as $key => $option) {
            if (in_array($option['option'], $options)) {
                $cfdata[$key] = 1;
            } else {
                $cfdata[$key] = 0;
            }
        }
        $data->cftest = $cfdata;
        $field->edit_save_data($data, $prefix, $tableprefix);
    }
}
