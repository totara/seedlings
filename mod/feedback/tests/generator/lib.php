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
 * feedback module PHPUnit data generator class
 *
 * @package    mod_feedback
 * @subpackage phpunit
 * @author     Russell England <russell.england@catalyst-eu.net>
 * @copyright  Catalyst IT Ltd 2013 <http://catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 *
 */

defined('MOODLE_INTERNAL') || die();

class mod_feedback_generator extends testing_module_generator {

    /**
     * Create new feedback module instance
     * @param array|stdClass $record
     * @param array $options
     * @return stdClass activity record with extra cmid field
     */
    public function create_instance($record = null, array $options = null) {
        global $CFG;
        require_once("$CFG->dirroot/mod/feedback/lib.php");

        $this->instancecount++;
        $i = $this->instancecount;

        $record = (object)(array)$record;
        $options = (array)$options;

        if (empty($record->course)) {
            throw new coding_exception('module generator requires $record->course');
        }

        $defaults = array();
        $defaults['name'] = get_string('pluginname', 'feedback').' '.$i;
        $defaults['intro'] = 'Test feedback '.$i;
        $defaults['introformat'] = FORMAT_MOODLE;

        $defaults['anonymous'] = 2; // Default to username
        $defaults['email_notification'] = 0;
        $defaults['multiple_submit'] = 0;
        $defaults['autonumbering'] = 0;
        $defaults['site_after_submit'] = '';
        $defaults['page_after_submit'] = '';
        $defaults['page_after_submitformat'] = 1;
        $defaults['publish_stats'] = 0;
        $defaults['timeopen'] = 0;
        $defaults['timeclose'] = 0;
        $defaults['completionsubmit'] = 0; // Set to 1 if completion on submit is required
        $defaults['page_after_submit_editor'] = array('itemid' => 0);
        foreach ($defaults as $field => $value) {
            if (!isset($record->$field)) {
                $record->$field = $value;
            }
        }

        if (isset($options['idnumber'])) {
            $record->cmidnumber = $options['idnumber'];
        } else {
            $record->cmidnumber = '';
        }

        $record->coursemodule = $this->precreate_course_module($record->course, $options);
        $id = feedback_add_instance($record, null);
        return $this->post_add_instance($id, $record->coursemodule);
    }
}