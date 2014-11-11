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
 * @author Ciaran Irvine <ciaran.irvine@totaralms.com>
 * @package enrol
 * @subpackage totara_program
 */

defined('MOODLE_INTERNAL') || die();

class enrol_totara_program_plugin extends enrol_plugin {

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     * @param int $courseid
     * @return moodle_url page url
     */
    public function get_newinstance_link($courseid) {
        global $DB;

        $context = context_course::instance($courseid);

        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/guest:config', $context)) {
            return NULL;
        }

        if ($DB->record_exists('enrol', array('courseid' => $courseid, 'enrol' => 'totara_program'))) {
            return NULL;
        }

        return new moodle_url('/enrol/totara_program/addinstance.php', array('sesskey' => sesskey(), 'id' => $courseid));
    }

    /**
     * Add new instance of enrol plugin with default settings.
     * @param object $course
     * @return int id of new instance, null if can not be created
     */
    public function add_default_instance($course) {
        $fields = array('enrolperiod' => $this->get_config('enrolperiod', 0), 'roleid' => $this->get_config('roleid', 0));
        return $this->add_instance($course, $fields);
    }

    /**
     * Add new instance of enrol_totara_program plugin.
     * @param object $course
     * @param array instance fields
     * @return int id of new instance, or id of existing instance
     */
    public function add_instance($course, array $fields = NULL) {

        $instance = $this->get_instance_for_course($course->id);
        if (!$instance) {
            return parent::add_instance($course);
        } else {
            return $instance->id;
        }
    }

    /**
     * Get the name of the enrolment plugin
     *
     * @return string
     */
    public function get_name() {
        return 'totara_program';
    }

    /**
     * Users are able to be un-enroled from a course
     *
     * @return bool
     */
    public function allow_unenrol(stdClass $instance) {
        return true;
    }

    /**
     * Get the instance of this plugin attached to a course if any
     * @param int $courseid id of course
     * @return object|bool $instance or false if not found
     */
    public function get_instance_for_course($courseid) {
        global $DB;
        return $DB->get_record('enrol', array('enrol' => 'totara_program', 'courseid' => $courseid));
    }

    /**
     * Attempt to automatically enrol current user in course without any interaction,
     * calling code has to make sure the plugin and instance are active.
     *
     * This should return either a timestamp in the future or false.
     *
     * @param stdClass $instance course enrol instance
     * @return bool|int false means not enrolled, integer means timeend
     */
    public function try_autoenrol(stdClass $instance) {
        global $CFG, $OUTPUT, $USER, $DB;

        if ($course = $DB->get_record('course', array('id' => $instance->courseid))) {
            //because of use of constants and program class functions, best to leave the prog_can_enter_course function where it is
            require_once($CFG->dirroot . '/totara/program/lib.php');
            $result = prog_can_enter_course($USER, $course);

            if ($result->enroled) {
                //if we just enrolled them, set a notification
                if ($result->notify) {
                    $a = new stdClass();
                    $a->course = $course->fullname;
                    $a->program = $result->program;
                    require_once($CFG->dirroot . '/course/lib.php');
                    $courseformat = course_get_format($course);
                    if ($courseformat->get_format() == 'singleactivity') {
                        $viewurl =  new moodle_url('/course/view.php', array('id' => $course->id));
                        $a->url = $viewurl->out();
                        totara_set_notification($OUTPUT->container(get_string('nowenrolledcontinue', 'enrol_totara_program', $a), 'plan_box'), null, array('class' => 'notifysuccess'));
                    } else {
                        totara_set_notification($OUTPUT->container(get_string('nowenrolled', 'enrol_totara_program', $a), 'plan_box'), null, array('class' => 'notifysuccess'));
                    }
                }
                //return 0 sets enrolment with no time limit
                return 0;
            }
        }
        return false;
    }

    /**
     * Gets an array of the user enrolment actions
     *
     * @param course_enrolment_manager $manager
     * @param stdClass $ue A user enrolment object
     * @return array An array of user_enrolment_actions
     */
    public function get_user_enrolment_actions(course_enrolment_manager $manager, $ue) {
        $actions = array();
        $context = $manager->get_context();
        $instance = $ue->enrolmentinstance;
        $params = $manager->get_moodlepage()->url->params();
        $params['ue'] = $ue->id;
        if ($this->allow_unenrol($instance) && has_capability("enrol/totara_program:unenrol", $context)) {
            $url = new moodle_url('/enrol/unenroluser.php', $params);
            $actions[] = new user_enrolment_action(new pix_icon('t/delete', ''), get_string('unenrol', 'enrol'), $url, array('class'=>'unenrollink', 'rel'=>$ue->id));
        }
        return $actions;
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        global $DB;
        // There is only one totara_program enrol instance allowed per course.
        if ($instances = $DB->get_records('enrol', array('courseid' => $data->courseid, 'enrol' => 'manual'), 'id')) {
            $instance = reset($instances);
            $instanceid = $instance->id;
        } else {
            $instanceid = $this->add_instance($course, (array)$data);
        }
        $step->set_mapping('enrol', $oldid, $instanceid);
    }

    /**
     * Restore user enrolment.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $instance
     * @param int $oldinstancestatus
     * @param int $userid
     */
    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid, $oldinstancestatus) {
        global $DB;

        $ue = $DB->get_record('user_enrolments', array('enrolid' => $instance->id, 'userid' => $userid));
        $enrol = false;
        if ($ue and $ue->status == ENROL_USER_ACTIVE) {
            // We do not want to restrict current active enrolments, let's kind of merge the times only.
            // This prevents some teacher lockouts too.
            if ($data->status == ENROL_USER_ACTIVE) {
                if ($data->timestart > $ue->timestart) {
                    $data->timestart = $ue->timestart;
                    $enrol = true;
                }

                if ($data->timeend == 0) {
                    if ($ue->timeend != 0) {
                        $enrol = true;
                    }
                } else if ($ue->timeend == 0) {
                    $data->timeend = 0;
                } else if ($data->timeend < $ue->timeend) {
                    $data->timeend = $ue->timeend;
                    $enrol = true;
                }
            }
        }

        if ($enrol) {
            $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, $data->status);
        }
    }

    /**
     * Restore role assignment.
     *
     * @param stdClass $instance
     * @param int $roleid
     * @param int $userid
     * @param int $contextid
     */
    public function restore_role_assignment($instance, $roleid, $userid, $contextid) {
        role_assign($roleid, $userid, $contextid, 'enrol_'.$this->get_name(), $instance->id);
    }

}

/**
 * Indicates API features that the enrol plugin supports.
 *
 * @param string $feature
 * @return mixed True if yes (some features may use other values)
 */
function enrol_totara_program_supports($feature) {
    switch($feature) {
        case ENROL_RESTORE_TYPE:
            return ENROL_RESTORE_EXACT;

        default:
            return null;
    }
}
