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
 * @author Ben Lobo <ben.lobo@kineo.com>
 * @package totara
 * @subpackage program
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

define('CONTENTTYPE_MULTICOURSE', 1);
define('CONTENTTYPE_COMPETENCY', 2);
define('CONTENTTYPE_RECURRING', 3);

class prog_content {

    // The $formdataobject is an object that will contains the values of any
    // submitted data so that the content edit form can be populated when it
    // is first displayed
    public $formdataobject;

    protected $programid;
    protected $coursesets;
    protected $coursesets_deleted_ids;

    // Used to determine if the content has changed since it was last saved
    protected $contentchanged = false;

    private $courseset_classnames = array(
        CONTENTTYPE_MULTICOURSE => 'multi_course_set',
        CONTENTTYPE_COMPETENCY  => 'competency_course_set',
        CONTENTTYPE_RECURRING   => 'recurring_course_set',
    );

    function __construct($programid) {
        global $DB;
        $this->programid = $programid;
        $this->coursesets = array();
        $this->coursesets_deleted_ids = array();
        $this->formdataobject = new stdClass();

        $sets = $DB->get_records('prog_courseset', array('programid' => $programid), 'sortorder ASC');

        foreach ($sets as $set) {
            if (!array_key_exists($set->contenttype, $this->courseset_classnames)) {
                throw new ProgramContentException(get_string('contenttypenotfound', 'totara_program'));
            }
            $courseset_classname = $this->courseset_classnames[$set->contenttype];
            $coursesetob = new $courseset_classname($programid, $set);
            $this->coursesets[] = $coursesetob;
        }

        $this->fix_set_sortorder($this->coursesets);
    }

    /**
     * Used by usort to sort the sets in the $coursesets array
     * by their sortorder properties
     *
     * @param <type> $a
     * @param <type> $b
     * @return <type>
     */
    static function cmp_set_sortorder( $a, $b ) {
        // sort by sortorder within certifpath
        if ($a->certifpath ==  $b->certifpath) {
            if ($a->sortorder ==  $b->sortorder) {
                return 0;
            } else {
                return ($a->sortorder < $b->sortorder) ? -1 : 1;
            }
        } else {
            return ($a->certifpath < $b->certifpath) ? -1 : 1;
        }
    }

    /**
     * Get the course sets
     *
     * @return <type>
     */
    public function get_course_sets() {
        return $this->coursesets;
    }

    /**
     * get coursesets for a certification pathtype
     * @param int $pathtype
     * @return array
     */
    public function get_course_sets_path($pathtype) {
        $csc = array();
        foreach ($this->coursesets as $cs) {
            if (!isset($cs->certifpath)) {
                $cs->certifpath=0;
            }
            if ($cs->certifpath == $pathtype) {
                $csc[] = $cs;
            }
        }
        return $csc;
    }

    /**
     * Deletes all the content for this program
     *
     * @return bool true|Exception
     */
    function delete() {
        global $DB;
        $transaction = $DB->start_delegated_transaction();

        foreach ($this->coursesets as $courseset) {
            $DB->delete_records('prog_courseset_course', array('coursesetid' => $courseset->id));
        }

        $DB->delete_records('prog_courseset', array('programid' => $this->programid));

        $transaction->allow_commit();

        return true;
    }

    /**
     * Makes sure that an array of course sets is in order in terms of each
     * set's sortorder property and resets the sortorder properties to ensure
     * that it begins from 1 and there are no gaps in the order.
     *
     * Also adds properties to enable the first and last set in the array to be
     * easily detected.
     *
     * @param <type> $coursesets
     */
    public function fix_set_sortorder(&$coursesets=null) {
        if ($coursesets == null) {
            $coursesets = $this->coursesets;
        }
        if ($coursesets == null) {
            return;
        }

        // Sort into sortorder within certifpath.
        usort($coursesets, array('prog_content', 'cmp_set_sortorder'));

        $pos = 1;

        // Courseset(s) are in order [CERTs] [RECERTs] (CERT or RECERT coursesets may not be present).
        $startcertifpath = $coursesets[0]->certifpath;
        foreach ($coursesets as $courseset) {
            $courseset->sortorder = $pos;
            unset($courseset->isfirstset);
            if ($pos == 1) {
                $courseset->isfirstset = true;
            }

            unset($courseset->islastset);
            if ($pos == count($coursesets)) {
                $courseset->islastset = true;
            }

            // check to see if now in recert group so can mark end of CERT group and start of RECERT
            if ($courseset->certifpath != $startcertifpath) {
                $courseset->isfirstset = true;
                $coursesets[$pos-2]->islastset = true;
                $startcertifpath = CERTIFPATH_RECERT;
            }

            $pos++;
        }
    }

    /**
     * Recieves the data submitted from the program content form and sets up
     * the course sets in an array so that they can be manipulated and/or
     * re-displayed in the form
     *
     * @param StdClass $formdata
     * @return bool
     */
    public function setup_content($formdata) {
        $courseset_prefixes = $this->get_courseset_prefixes($formdata);
        // If the form has been submitted then it's likely that some changes are
        // being made to the messages so we mark the messages as changed (this
        // is used by javascript to determine whether or not to warn te user
        // if they try to leave the page without saving first
        $this->contentchanged = true;

        $this->coursesets = array(); // clear the coursesets!

        foreach (array('_ce', '_rc') as $suffix) {
            if (!isset($courseset_prefixes[$suffix]) || $courseset_prefixes[$suffix] == null) {
                continue;
            }

            foreach ($courseset_prefixes[$suffix] as $prefix) {
                if (isset($formdata->{$prefix.'contenttype'})) {
                    $contenttype = $formdata->{$prefix.'contenttype'};
                } else {
                    continue;
                }

                if (!array_key_exists($contenttype, $this->courseset_classnames)) {
                    throw new ProgramContentException(get_string('contenttypenotfound', 'totara_program'));
                }

                $courseset_classname = $this->courseset_classnames[$contenttype];
                // Skeleton courseset.
                $courseset = new $courseset_classname($this->programid, null, $prefix);
                $courseset->certifpath = $formdata->{'certifpath'.$suffix};
                $courseset->init_form_data($prefix, $formdata);
                $this->coursesets[] = $courseset;
            }

        }

        $this->coursesets_deleted_ids = $this->get_deleted_coursesets($formdata);
        $this->fix_set_sortorder($this->coursesets);

        return true;
    }

    /**
     * Create copies of cert coursesets as recert coursesets
     *
     * store new in class
     *
     * @param StdClass $formdata
     */
    function copy_coursesets_to_recert($formdata) {

        $courseset_prefixes = $this->get_courseset_prefixes($formdata);

        foreach ($courseset_prefixes['_ce'] as $prefix) {
            if (isset($formdata->{$prefix.'contenttype'})) {
                $contenttype = $formdata->{$prefix.'contenttype'};
            } else {
                continue;
            }

            $courseset_classname = $this->courseset_classnames[$contenttype];

            // skeleton courseset eg 'multi_course_set' program_courseset.class.php
            $courseset = new $courseset_classname($this->programid, null, $prefix);
            $courseset->certifpath = CERTIFPATH_RECERT;

            // adds courses and parent::init_form_data() adds other members
            $formdata->{$prefix.'id'} = 0; // set courseset.id to 0 as new not created yet
            $courseset->init_form_data($prefix, $formdata);

            $this->coursesets[] = $courseset;
        }
    }


    public function update_content() {
        $this->fix_set_sortorder($this->coursesets);
    }

    /**
     * Returns the sort order of the last course set.
     *
     * @return <type>
     */
    public function get_last_courseset_pos() {
        $sortorder = null;
        foreach ($this->coursesets as $set) {
            $sortorder = max($sortorder, $set->sortorder);
        }
        return $sortorder;
    }

    /**
     * Retrieves the form name prefixes of all the existing course sets from
     * the submitted data and returns an array containing all the form name
     * prefixes
     *
     * @param object $formdata The submitted form data
     * @return array
     */
    public function get_courseset_prefixes($formdata) {
        $setprefs = array();
        foreach (array('_ce','_rc') as $suffix) {
            if (!isset($formdata->{'setprefixes'.$suffix}) || empty($formdata->{'setprefixes'.$suffix})) {
                continue;
            } else {
                foreach (explode(',', $formdata->{'setprefixes'.$suffix}) as $sp) {
                    $setprefs[$suffix][] = $sp;
                }
            }
        }
        return $setprefs;
    }


    /**
     * Retrieves the ids of any deleted course sets from the submitted data and
     * returns an array containing the id numbers or an empty array
     *
     * @param <type> $formdata
     * @return <type>
     */
    public function get_deleted_coursesets($formdata) {
        if (!isset($formdata->deleted_coursesets) || empty($formdata->deleted_coursesets)) {
            return array();
        }
        return explode(',', $formdata->deleted_coursesets);
    }


    /**
     * Determines whether or not an action button was clicked and, if so,
     * determines which set the action refers to (based on the set sortorder)
     * and returns the set order number.
     *
     * @param string $action The action that this relates to (moveup, movedown, delete, etc)
     * @param object $formdata The submitted form data
     * @return int|obj|false Returns set order number if a matching action was found or false for no action
     */
    public function check_set_action($action, $formdata) {

        $courseset_certifpath_prefixes = $this->get_courseset_prefixes($formdata);
        // if a submit button was clicked, try to determine if it relates to a
        // course set and, if so, return the course set sort order

        foreach ($courseset_certifpath_prefixes as $courseset_prefixes) {
            foreach ($courseset_prefixes as $prefix) {
                if (isset($formdata->{$prefix.$action})) {
                    return $formdata->{$prefix.'sortorder'};
                }
            }
        }

        // if a submit button was clicked, try to determine if it relates to a
        // course within a course set and, if so, return the course set sort
        // order and the course id in an object
        foreach ($this->coursesets as $courseset) {
            if ($courseid = $courseset->check_course_action($action, $formdata)) {
                $ob = new stdClass();
                $ob->courseid = $courseid;
                $ob->setnumber = $courseset->sortorder;
                return $ob;
            }
        }

        return false;
    }

    public function save_content() {
        global $DB;
        $this->fix_set_sortorder($this->coursesets);
        $program_plugin = enrol_get_plugin('totara_program');
        // first delete any course sets from the database that have been marked for deletion
        foreach ($this->coursesets_deleted_ids as $coursesetid) {
            if ($courseset = $DB->get_record('prog_courseset', array('id' => $coursesetid))) {

                // delete any courses linked to the course set
                // first get the list of courses to check later
                $courses = $DB->get_fieldset_select('prog_courseset_course', 'courseid', 'coursesetid = ?', array($coursesetid));
                //now delete the courseset
                if (!$DB->delete_records('prog_courseset_course', array('coursesetid' => $coursesetid))) {
                    return false;
                }
                //now check if any of those courses still exist in any other program and remove the enrolment plugin if required
                $courses_still_associated = prog_get_courses_associated_with_programs($courses);
                $courses_to_remove_plugin_from = array_diff($courses, array_keys($courses_still_associated));
                foreach ($courses_to_remove_plugin_from as $courseid) {
                    $instance = $program_plugin->get_instance_for_course($courseid);
                    if ($instance) {
                        $program_plugin->delete_instance($instance);
                    }
                }

                // delete the course set
                if (!$DB->delete_records('prog_courseset', array('id' => $coursesetid))) {
                    return false;
                }
            }
        }

        // then save the new and changed course sets
        foreach ($this->coursesets as $courseset) {
            if (!$courseset->save_set()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Moves a course set up one place in the array of course sets
     *
     * @param <type> $settomove_sortorder
     * @return <type>
     */
    public function move_set_up($settomove_sortorder) {

        foreach ($this->coursesets as $current_set) {

            if ($current_set->sortorder == $settomove_sortorder) {
                $settomoveup = $current_set;
            }

            if ($current_set->sortorder == $settomove_sortorder-1) {
                $settomovedown = $current_set;
            }
        }

        if ($settomoveup && $settomovedown) {
            $moveup_sortorder = $settomoveup->sortorder;
            $movedown_sortorder = $settomovedown->sortorder;
            $settomoveup->sortorder = $movedown_sortorder;
            $settomovedown->sortorder = $moveup_sortorder;
            $this->fix_set_sortorder($this->coursesets);
            return true;
        }

        return false;
    }

    /**
     * Moves a course set down one place in the array of course sets
     *
     * @param <type> $settomove_sortorder
     * @return <type>
     */
    public function move_set_down($settomove_sortorder) {

        foreach ($this->coursesets as $current_set) {

            if ($current_set->sortorder == $settomove_sortorder) {
                $settomovedown = $current_set;
            }

            if ($current_set->sortorder == $settomove_sortorder+1) {
                $settomoveup = $current_set;
            }
        }

        if ($settomovedown && $settomoveup) {
            $movedown_sortorder = $settomovedown->sortorder;
            $moveup_sortorder = $settomoveup->sortorder;
            $settomovedown->sortorder = $moveup_sortorder;
            $settomoveup->sortorder = $movedown_sortorder;
            $this->fix_set_sortorder($this->coursesets);
            return true;
        }

        return false;
    }

    /**
     * Adds a new course set to the array of course sets.
     *
     * @param <type> $contenttype
     * @return <type>
     */
    public function add_set($contenttype) {

        $lastsetpos = $this->get_last_courseset_pos();

        if (!array_key_exists($contenttype, $this->courseset_classnames)) {
            throw new ProgramContentException(get_string('contenttypenotfound', 'totara_program'));
        }

        $courseset_classname = $this->courseset_classnames[$contenttype];
        $courseset = new $courseset_classname($this->programid);

        if ($lastsetpos !== null) {
            $courseset->sortorder = $lastsetpos + 1;
        } else {
            $courseset->sortorder = 1;
        }

        $courseset->label = get_string('legend:courseset', 'totara_program', $courseset->sortorder);

        $this->coursesets[] = $courseset;
        $this->fix_set_sortorder($this->coursesets);

        return true;
    }

    /**
     * Deletes a course set from the array of course sets. If the set
     * has no id number (i.e. it does not yet exist in the database) it is
     * removed from the array but if it has an id number it is marked as
     * deleted but not actually removed from the array until the content is
     * saved
     *
     * @param <type> $set
     */
    public function delete_set($settodelete_sortorder) {

        $new_coursesets = array();
        $setfound = false;
        $previous_set = null;

        foreach ($this->coursesets as $courseset) {
            if ($courseset->sortorder == $settodelete_sortorder) {
                $setfound = true;
                if ($courseset->id > 0) { // if this set already exists in the database
                    $this->coursesets_deleted_ids[] = $courseset->id;
                }

                // if the set being deleted was the last set in the program
                // we have to set the previous set's nextsetoperator property
                // to 0
                if (isset($courseset->islastset) && $courseset->islastset) {
                    if (is_object($previous_set)) {
                        $previous_set->nextsetoperator = 0;
                    }
                } else {
                    // if this set's nextsetoperator property is 'then' we have to
                    // transfer this property to the previous set (if there was one)
                    // so that we don't break the flow of the content
                    if ($courseset->nextsetoperator == NEXTSETOPERATOR_THEN) {
                        if (is_object($previous_set)) {
                            $previous_set->nextsetoperator = NEXTSETOPERATOR_THEN;
                        }
                    }
                }

            } else {
                $previous_set = $courseset;
                $new_coursesets[] = $courseset;
            }
        }

        if ($setfound) {
            $this->coursesets = $new_coursesets;
            $this->fix_set_sortorder($this->coursesets);
            return true;
        }

        return false;
    }

    public function update_set($set_pos) {
        $this->fix_set_sortorder($this->coursesets);
    }

    /**
     * Locates the course set to which a course is being added and adds the course
     *
     * @param <type> $set_sortorder
     * @param <type> $formdata
     * @return <type>
     */
    public function add_course($set_sortorder, $formdata) {
        foreach ($this->coursesets as $courseset) {
            if ($courseset->sortorder == $set_sortorder) {
                if (!$courseset->add_course($formdata)) {
                    return false;
                } else {
                    $this->fix_set_sortorder($this->coursesets);
                    return true;
                }
            }
        }
        return false;
    }

    public function delete_course($set_sortorder, $courseid, $formdata) {
        foreach ($this->coursesets as $courseset) {
            if ($courseset->sortorder == $set_sortorder) {
                if (!$courseset->delete_course($courseid)) {
                    return false;
                } else {
                    $this->fix_set_sortorder($this->coursesets);
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Locates the course set to which a competency is being added and adds the competency
     *
     * @param <type> $set_sortorder
     * @param <type> $formdata
     * @return <type>
     */
    public function add_competency($set_sortorder, $formdata) {
        foreach ($this->coursesets as $courseset) {
            if ($courseset->sortorder == $set_sortorder) {
                if (!$courseset->add_competency($formdata)) {
                    return false;
                } else {
                    $this->fix_set_sortorder($this->coursesets);
                    return true;
                }
            }
        }
        return false;
    }


    /**
     * Returns the total maximum time allowance for the program by looking at the
     * different content time allowances
     *
     * @return int total_time_allowance
     */
    public function get_total_time_allowance($certifpath) {

        // Store the maximum time allowance to be returned
        $total_time_allowance = 0;
        // retrieve the course sets in the way that they are grouped in the program
        $courseset_groups = $this->get_courseset_groups($certifpath);

        if (empty($courseset_groups)) {
            return 0; // raise an exception? or give infinite time?
        }

        foreach ($courseset_groups as $courseset_group) {
            $max_time_allowance_in_group = 0;
            foreach ($courseset_group as $courseset) {
                if ($courseset->timeallowed > $max_time_allowance_in_group) {
                    $max_time_allowance_in_group = $courseset->timeallowed;
                }
            }
            $total_time_allowance += $max_time_allowance_in_group;
        }
        return $total_time_allowance;
    }

    /**
     * Returns an array of arrays containing the course sets in this program
     * grouped by their flow within the program. For example, if the content
     * flow is Set1 or Set2 then Set3 or Set4 then Set5, the returned array
     * will be:
     *
     * array(
     *      array(Set1, Set2),
     *      array(Set3, Set4),
     *      array(Set5),
     * };
     *
     * This can be used to determine whether or not a user has completed a
     * particular set and/or group of sets (which is necessary for working out
     * when to provide 'access tokens' to a user to let them into any of the
     * courses in a subsequent course set or group of course sets.
     */
    public function get_courseset_groups($certifpath) {

        $courseset_groups = array();

        if (empty($this->coursesets)) {
            return $courseset_groups;
        }

        // Helpers for handling the sets of OR's
        $last_handled_OR_operator = false;
        $courseset_group = array();

        foreach ($this->coursesets as $courseset) {
            if ($courseset->certifpath != $certifpath) {
                continue;
            }

            if ($courseset->nextsetoperator == NEXTSETOPERATOR_OR) {
                // Add to the outstanding 'or' list
                $last_handled_OR_operator = true;
                $courseset_group[] = $courseset;

                // slight hack to check if this is the last course set (nextsetoperator should not be set in this case but sometimes it is)
                if (isset($courseset->islastset) && $courseset->islastset) {
                    $courseset_groups[] = $courseset_group;
                }
            } else { // If THEN operator or no operator next..
                if ($last_handled_OR_operator) {
                    // Add each course set in the group of ORs to an array
                    $courseset_group[] = $courseset;

                    // Add this group of course sets to the array of groups
                    $courseset_groups[] = $courseset_group;

                    // Reset the OR bits
                    $last_handled_OR_operator = false;
                    $courseset_group = array();
                } else {
                    $courseset_group[] = $courseset;
                    $courseset_groups[] = $courseset_group;
                    $courseset_group = array();
                }

            }
        }

        return $courseset_groups;
    }

    /**
     * Receives an array containing the course sets in a course group and determines
     * the time allowance for the group based on the greatest time allowance of
     * all the course sets. A record is then added to the prog_completion table
     * setting the timedue property as the current time + time allowance.
     *
     * This date will be used to determine when to issue course set due reminders.
     *
     * @param array $courseset_group
     * @param int $userid
     * @return void
     */
    public function set_courseset_group_timedue($courseset_group, $userid) {
        global $DB;
        if (count($courseset_group) < 1) {
            return;
        }

        $now = time();
        $courseset_selected = $courseset_group[0];

        // select the course set with the greatest time allowance
        foreach ($courseset_group as $courseset) {
            $courseset_selected = ($courseset->timeallowed > $courseset_selected->timeallowed) ? $courseset : $courseset_selected;
        }

        $timeallowance = $courseset_selected->timeallowed;

        // insert a record to set the time that this course set will be due
        if ($timeallowance>0) {
            if (!$cc = $DB->get_record('prog_completion', array('programid' => $this->programid, 'userid' => $userid, 'coursesetid' => $courseset_selected->id))) {
                $cc = new stdClass();
                $cc->programid = $this->programid;
                $cc->userid = $userid;
                $cc->coursesetid = $courseset_selected->id;
                $cc->status = STATUS_COURSESET_INCOMPLETE;
                $cc->timestarted = $now;
                $cc->timedue = $now + $timeallowance;
                $DB->insert_record('prog_completion', $cc);
            }
        }

        return;

    }

    /**
     * Receives an array containing the course sets in a course group and determines
     * the time allowance for the group based on the greatest time allowance of
     * all the course sets. Records are then added to the prog_completion table
     * setting the timedue property as the current time + time allowance.
     *
     * This date will be used to determine when to issue course set due reminders.
     *
     * @param array $courseset_group
     * @param array $userids
     * @return void
     */
    public function set_courseset_group_timedue_bulk($courseset_group, $userids) {
        global $DB;

        if (count($courseset_group) < 1) {
            return;
        }

        $now = time();
        $courseset_selected = $courseset_group[0];

        // select the course set with the greatest time allowance
        foreach ($courseset_group as $courseset) {
            $courseset_selected = ($courseset->timeallowed > $courseset_selected->timeallowed) ? $courseset : $courseset_selected;
        }

        $timeallowance = $courseset_selected->timeallowed;

        // insert a record to set the time that this course set will be due
        if ($timeallowance > 0) {
            // first get a list of users who already have a record
            $existing_records = $DB->get_fieldset_select('prog_completion', 'userid',
                "programid = ? AND coursesetid = ?", array($this->programid, $courseset_selected->id));

            $prog_completions = array();
            foreach ($userids as $userid) {
                // don't add if they already have a record
                if (in_array($userid, $existing_records)) {
                    continue;
                }
                $cc = new stdClass();
                $cc->programid = $this->programid;
                $cc->userid = $userid;
                $cc->coursesetid = $courseset_selected->id;
                $cc->status = STATUS_COURSESET_INCOMPLETE;
                $cc->timestarted = $now;
                $cc->timedue = $now + $timeallowance;

                $prog_completions[] = $cc;
            }
            $DB->insert_records_via_batch('prog_completion', $prog_completions);
        }

        return;
    }

    public function get_content_form_template(&$mform, &$template_values, $coursesets=null, $updateform=true, $iscertif=false, $certifpath=CERTIFPATH_CERT) {
        global $OUTPUT;

        if ($coursesets == null) {
            if ($iscertif) {
                $coursesets = array();
            } else {
                $coursesets = $this->coursesets;
            }
        }

        $templatehtml = '';
        $numcoursesets = count($coursesets);
        $recurring = false;

        // see if first half of page (or only part of page if !iscertif), as only want to do once
        if (!$iscertif || $certifpath == CERTIFPATH_CERT) {
            $suffix = '_ce';
            // This update button is at the start of the form so that it catches any
            // 'return' key presses in text fields and acts as the default submit
            // behaviour. This is not official browser behaviour but in most browsers
            // this should result in this button being submitted (where a form has
            // multiple submit buttons like this one)
            if ($updateform) {
                $mform->addElement('submit', 'update', get_string('update', 'totara_program'));
                $template_values['%update%'] = array('name'=>'update', 'value'=>null);
            }
            $templatehtml .= '%update%'."\n";

            // Add the program id
            if ($updateform) {
                $mform->addElement('hidden', 'id');
                $mform->setType('id', PARAM_INT);
                $template_values['%programid%'] = array('name'=>'id', 'value'=>null);
            }
            $templatehtml .= '%programid%'."\n";
            $this->formdataobject->id = $this->programid;

            // Add a hidden field to show if the content has been changed
            // (used by javascript to determine whether or not to display a
            // dialog when the user leaves the page)
            $contentchanged = $this->contentchanged ? '1' : '0';
            if ($updateform) {
                $mform->addElement('hidden', 'contentchanged', $contentchanged);
                $mform->setType('contentchanged', PARAM_BOOL);
                $mform->setConstant('contentchanged', $contentchanged);
                $template_values['%contentchanged%'] = array('name'=>'contentchanged', 'value'=>null);
            }
            $templatehtml .= '%contentchanged%'."\n";
            $this->formdataobject->contentchanged = $contentchanged;

            if ($updateform) {
                $mform->addElement('hidden', 'iscertif', $iscertif);
                $mform->setType('iscertif', PARAM_BOOL);
                $mform->setConstant('iscertif', $iscertif);
                $template_values['%iscertif%'] = array('name'=>'iscertif', 'value'=>null);
            }
            $templatehtml .= '%iscertif%'."\n";
            $this->formdataobject->iscertif = $iscertif;

        } else {
            $suffix = '_rc';
        }

        if (!$iscertif) {
            $suffix = '_ce';
        }

        // Add certifpath
        if ($updateform) {
            $mform->addElement('hidden', 'certifpath'.$suffix);
            $mform->setType('certifpath'.$suffix, PARAM_INT);
            $mform->setConstant('certifpath'.$suffix, $certifpath);
            $template_values['%certifpath'.$suffix.'%'] = array('name'=>'certifpath'.$suffix, 'value'=>null);
        }
        $templatehtml .= '%certifpath'.$suffix.'%'."\n";
        $this->formdataobject->{'certifpath'.$suffix} = $certifpath;

        // Add the deleted course set ids
        if ($this->coursesets_deleted_ids) {
            $deletedcoursesetidsarray = array();
            foreach ($this->coursesets_deleted_ids as $deleted_courseset_id) {
                $deletedcoursesetidsarray[] = $deleted_courseset_id;
            }
            $deletedcourseidsstr = implode(',', $deletedcoursesetidsarray);
            if ($updateform) {
                $mform->addElement('hidden', 'deleted_coursesets', $deletedcourseidsstr);
                $mform->setType('deleted_coursesets', PARAM_SEQUENCE);
                $mform->setConstant('deleted_coursesets', $deletedcourseidsstr);
                $template_values['%deleted_coursesets%'] = array('name'=>'deleted_coursesets', 'value'=>null);
            }
            $templatehtml .= '%deleted_coursesets%'."\n";
            $this->formdataobject->deleted_coursesets = $deletedcourseidsstr;
        }

        if ($iscertif) {
            $templatehtml .= html_writer::start_tag('fieldset', array('id' => 'programcontent'));
            $templatehtml .= html_writer::start_tag('legend', array('class' => 'ftoggler', 'id' => 'certifpath'))
                . get_string(($certifpath == CERTIFPATH_CERT ? 'oricertpath' : 'recertpath'), 'totara_certification')
                . html_writer::end_tag('legend');
            $templatehtml .= html_writer::start_tag('p')
                . get_string(($certifpath == CERTIFPATH_CERT ? 'oricertpathdesc' : 'recertpathdesc'), 'totara_certification')
                . html_writer::end_tag('p');

            if ($certifpath == CERTIFPATH_RECERT && $numcoursesets == 0) {
                // ask for cert content to be copied to recert
                $mform->addElement('advcheckbox', 'sameascert'.$suffix, get_string('sameascert', 'totara_certification'),
                                '', array('disabled' => 'disabled', 'group' => 'sameascertgrp'), array(0, 1));
                // 5th param: set disabled initially (have to add (redundent) group else get error)
                // 6th param: checkbox settings, first value is default
                $mform->setType('sameascert'.$suffix, PARAM_INT);
                $template_values['%sameascert'.$suffix.'%'] = array('name'=>'sameascert'.$suffix, 'value' => 0);

                $templatehtml .= html_writer::start_tag('label', array('for' => 'sameascert'.$suffix))
                                . get_string('sameascert', 'totara_certification') . html_writer::end_tag('label');
                $templatehtml .= '%sameascert'.$suffix.'%';
            }
        }

        $templatehtml .= $OUTPUT->heading(get_string('programcontent', 'totara_program'));

        // Show the program total minimum time required.
        $program = new program($this->programid);
        $programtime = $program->content->get_total_time_allowance($certifpath);

        if ($programtime > 0) {
            $templatehtml .= prog_format_seconds($programtime);
        }

        if ($iscertif) {
            $templatehtml .= html_writer::start_tag('p') . get_string('certificationcontent', 'totara_certification') . html_writer::end_tag('p');
        } else {
            $templatehtml .= html_writer::start_tag('p') . get_string('instructions:programcontent', 'totara_program') . html_writer::end_tag('p');
        }

        $templatehtml .= html_writer::start_tag('div', array('id' => 'course_sets'.$suffix));
        $coursesetprefixesarray = array();

        if ($numcoursesets == 0) { // if there's no content yet
            $templatehtml .= html_writer::start_tag('p') . get_string('noprogramcontent', 'totara_program') . html_writer::end_tag('p');
        } else {
            foreach ($coursesets as $courseset) {
                $coursesetprefixesarray[] = $courseset->get_set_prefix();

                // Add the course sets
                $templatehtml .= $courseset->get_courseset_form_template($mform, $template_values, $this->formdataobject, $updateform);

                $recurring = $courseset->is_recurring();
            }
        }


        // Add the set prefixes
        $setprefixesstr = implode(',', $coursesetprefixesarray);
        if ($updateform) {
            $mform->addElement('hidden', 'setprefixes'.$suffix, $setprefixesstr);
            $mform->setType('setprefixes'.$suffix, PARAM_TEXT);
            $mform->setConstant('setprefixes'.$suffix, $setprefixesstr);
            $template_values['%setprefixes'.$suffix.'%'] = array('name'=>'setprefixes'.$suffix, 'value'=>null);
        }
        $templatehtml .= '%setprefixes'.$suffix.'%'."\n";
        $this->formdataobject->{'setprefixes'.$suffix} = $setprefixesstr;

        $templatehtml .= html_writer::end_tag('div');

        if (!$recurring) {
            // Add the add content drop down
            if ($updateform) {

                if ($iscertif) {
                    // only allow coursesets not recurring or competencies
                    $contentoptions = array(
                        CONTENTTYPE_MULTICOURSE => get_string('setofcourses', 'totara_program'),
                    );
                } else {
                    $contentoptions = array(
                        CONTENTTYPE_MULTICOURSE => get_string('setofcourses', 'totara_program'),
                        CONTENTTYPE_COMPETENCY => get_string('competency', 'totara_program')
                    );
                    if ($numcoursesets == 0) { // don't allow recurring course to be added if the program already has other content
                        $contentoptions[CONTENTTYPE_RECURRING] = get_string('recurringcourse', 'totara_program');
                    }
                }

                $mform->addElement('select', 'contenttype'.$suffix, get_string('addnew', 'totara_program'), $contentoptions,
                                array('id'=>'contenttype'.$suffix));
                $mform->setType('contenttype'.$suffix, PARAM_INT);
                $template_values['%contenttype'.$suffix.'%'] = array('name'=>'contenttype'.$suffix, 'value'=>null);
            }
            $templatehtml .= html_writer::start_tag('label', array('for' => 'contenttype'.$suffix)) . get_string('addnew', 'totara_program')
                                . html_writer::end_tag('label');
            $templatehtml .= '%contenttype'.$suffix.'%';
            $templatehtml .= ' '.get_string('toprogram', 'totara_program').' ';

            // Add the add content button
            if ($updateform) {
                $mform->addElement('submit', 'addcontent'.$suffix, get_string('add'), array('id'=>'addcontent'.$suffix));
                $template_values['%addcontent'.$suffix.'%'] = array('name'=>'addcontent'.$suffix, 'value'=>null);
            }
            $templatehtml .= '%addcontent'.$suffix.'%'."\n";
            if ($iscertif) {
                $helpbutton = $OUTPUT->help_icon('addcertifprogramcontent', 'totara_certification');
            } else {
                $helpbutton = $OUTPUT->help_icon('addprogramcontent', 'totara_program');
            }
            $templatehtml .= $helpbutton;
        }

        $templatehtml .= html_writer::empty_tag('br');

        return $templatehtml;
    }

    /**
     * Returns program coursesets based on the search parameters ($operator and $visibility)
     *
     * @param int $operator Constant to search for a value in totara_search_for_value function
     * @param int $visibility Audience visibility constant
     * @return array of coursesets
     */
    public function get_visibility_coursesets($operator, $visibility) {
        $courseaudiencevisibility = array();
        foreach ($this->coursesets as $set) {
            if (get_class($set) === $this->courseset_classnames[CONTENTTYPE_MULTICOURSE]) {
                $courseaudiencevisibility += totara_search_for_value($set->courses, 'audiencevisible', $operator, $visibility);
            } else if (get_class($set) === $this->courseset_classnames[CONTENTTYPE_COMPETENCY]){
                $courses = $set->get_competency_courses();
                $courseaudiencevisibility += totara_search_for_value($courses, 'audiencevisible', $operator, $visibility);
            }
        }
        if (!empty($courseaudiencevisibility)) {
            $courseaudiencevisibility = array_unique($courseaudiencevisibility, SORT_REGULAR);
        }

        return $courseaudiencevisibility;
    }
}

class ProgramContentException extends Exception { }
