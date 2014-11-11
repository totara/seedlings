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
 * @author Andrew Hancox <andrewdchancox@googlemail.com> on behalf of Synergy Learning
 * @package totara
 * @subpackage enrol_totara_facetoface
 */

/**
 * Face-to-Face Direct enrolment external functions.
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
class enrol_totara_facetoface_external extends external_api {

    /**
     * Returns description of get_instance_info() parameters.
     *
     * @return external_function_parameters
     */
    public static function get_instance_info_parameters() {
        return new external_function_parameters(
            array('instanceid' => new external_value(PARAM_INT, 'instance id of totara_facetoface enrolment plugin.'))
        );
    }

    /**
     * Return totara_facetoface-enrolment instance information.
     *
     * @param int $instanceid instance id of totara_facetoface enrolment plugin.
     * @return array instance information.
     */
    public static function get_instance_info($instanceid) {
        global $DB, $CFG;

        require_once($CFG->libdir . '/enrollib.php');

        $params = self::validate_parameters(self::get_instance_info_parameters(), array('instanceid' => $instanceid));

        // Retrieve totara_facetoface enrolment plugin.
        $enrolplugin = enrol_get_plugin('totara_facetoface');
        if (empty($enrolplugin)) {
            throw new moodle_exception('invaliddata', 'error');
        }

        $enrolinstance = $DB->get_record('enrol', array('id' => $params['instanceid']), '*', MUST_EXIST);
        $coursecontext = context_course::instance($enrolinstance->courseid);
        $categorycontext = $coursecontext->get_parent_context();
        self::validate_context($categorycontext);

        $instanceinfo = (array) $enrolplugin->get_enrol_info($enrolinstance);

        unset($instanceinfo->requiredparam);

        return $instanceinfo;
    }

    /**
     * Returns description of get_instance_info() result value.
     *
     * @return external_description
     */
    public static function get_instance_info_returns() {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'id of course enrolment instance'),
                'courseid' => new external_value(PARAM_INT, 'id of course'),
                'type' => new external_value(PARAM_PLUGIN, 'type of enrolment plugin'),
                'name' => new external_value(PARAM_RAW, 'name of enrolment plugin'),
                'status' => new external_value(PARAM_RAW, 'status of enrolment plugin'),
            )
        );
    }
}
