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
 * Face-to-Face Direct enrolment plugin.
 */
require_once($CFG->dirroot . '/mod/facetoface/lib.php');
require_once($CFG->dirroot . '/totara/hierarchy/prefix/position/lib.php');

class enrol_totara_facetoface_plugin extends enrol_plugin {

    const SETTING_LONGTIMENOSEE = 'customint2';
    const SETTING_MAXENROLLED = 'customint3';
    const SETTING_COURSEWELCOME = 'customint4';
    const SETTING_COHORTONLY = 'customint5';
    const SETTING_NEWENROLS = 'customint6';
    const SETTING_UNENROLWHENREMOVED = 'customint7';
    const SETTING_AUTOSIGNUP = 'customint8';

    protected $lastenroller = null;
    protected $lastenrollerinstanceid = 0;
    protected $sessions = array();
    protected $removednomanager = false; // Indicates that sessions were removed from the list because user has no manager.

    /**
     * Returns optional enrolment information icons.
     *
     * This is used in course list for quick overview of enrolment options.
     *
     * We are not using single instance parameter because sometimes
     * we might want to prevent icon repetition when multiple instances
     * of one type exist. One instance may also produce several icons.
     *
     * @param array $instances all enrol instances of this type in one course
     * @return array of pix_icon
     */
    public function get_info_icons(array $instances) {
        $icons = array();
        $icons[] = new pix_icon('withoutkey', get_string('pluginname', 'enrol_totara_facetoface'), 'enrol_totara_facetoface');
        return $icons;
    }

    /**
     * Returns localised name of enrol instance
     *
     * @param stdClass $instance (null is accepted too)
     * @return string
     */
    public function get_instance_name($instance) {
        global $DB;

        if (empty($instance->name)) {
            if (!empty($instance->roleid) and $role = $DB->get_record('role', array('id' => $instance->roleid))) {
                $role = ' (' . role_get_name($role, context_course::instance($instance->courseid, IGNORE_MISSING)) . ')';
            } else {
                $role = '';
            }
            $enrol = $this->get_name();
            return get_string('pluginname', 'enrol_'.$enrol) . $role;
        } else {
            return format_string($instance->name);
        }
    }

    /**
     * Users enroled through this plugin can have their roles edited
     *
     * @return bool
     */
    public function roles_protected() {
        return false;
    }

    /**
     * Get the name of the enrolment plugin
     *
     * @return string
     */
    public function get_name() {
        return 'totara_facetoface';
    }

    /**
     * Users enroled through this plugin are able to be un-enroled
     *
     * @return bool
     */
    public function allow_unenrol(stdClass $instance) {
        return true;
    }

    /**
     * Users enroled through this plugin can be edited
     *
     * @return bool
     */
    public function allow_manage(stdClass $instance) {
        return true;
    }

    public function show_enrolme_link(stdClass $instance) {
        return ($this->can_self_enrol($instance, false) === true);
    }

    /**
     * Sets up navigation entries.
     *
     * @param stdClass $instancesnode
     * @param stdClass $instance
     * @return void
     */
    public function add_course_navigation($instancesnode, stdClass $instance) {
        if ($instance->enrol !== 'totara_facetoface') {
             throw new coding_exception('Invalid enrol instance type!');
        }

        $context = context_course::instance($instance->courseid);
        if (has_capability('enrol/totara_facetoface:config', $context)) {
            $managelink = new moodle_url('/enrol/totara_facetoface/edit.php', array('courseid' => $instance->courseid, 'id' => $instance->id));
            $instancesnode->add($this->get_instance_name($instance), $managelink, navigation_node::TYPE_SETTING);
        }
    }

    /**
     * Returns edit icons for the page with list of instances
     * @param stdClass $instance
     * @return array
     */
    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;

        if ($instance->enrol !== 'totara_facetoface') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = context_course::instance($instance->courseid);

        $icons = array();

        if (has_capability('enrol/totara_facetoface:config', $context)) {
            $editlink = new moodle_url("/enrol/totara_facetoface/edit.php", array('courseid' => $instance->courseid, 'id' => $instance->id));
            $icons[] = $OUTPUT->action_icon($editlink, new pix_icon('t/edit', get_string('edit'), 'core',
                array('class' => 'iconsmall')));
        }

        return $icons;
    }

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course
     * or null if user lacks correct capabilities.
     * @param int $courseid
     * @return null|moodle_url page url
     */
    public function get_newinstance_link($courseid) {
        $context = context_course::instance($courseid, MUST_EXIST);

        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/totara_facetoface:config', $context)) {
            return null;
        }
        // Multiple instances supported - different roles with different password.
        return new moodle_url('/enrol/totara_facetoface/edit.php', array('courseid'=>$courseid));
    }

    /**
     * Enrol user on to course
     *
     * @param enrol_totara_facetoface_plugin $instance enrolment instance
     * @param stdClass $fromform data needed for enrolment.
     * @param stdClass $course course to enrol on.
     * @param stdClass $returnurl url to redirect to on completion.
     * @return bool|array true if enroled else error code and messege
     */
    public function enrol_totara_facetoface($instance, $fromform = null, $course, $returnurl) {
        global $DB, $USER;
        $sessions = $this->get_enrolable_sessions($course->id);
        $context = context_course::instance($course->id);
        $enrolled = false;

        // Load facetofaces.
        $f2fids = array();
        foreach ($sessions as $session) {
            $f2fids[$session->facetoface] = $session->facetoface;
        }
        list($idin, $params) = $DB->get_in_or_equal($f2fids);
        $facetofaces = $DB->get_records_select('facetoface', "ID $idin", $params);

        $selectpositiononsignupglobal = get_config(null, 'facetoface_selectpositiononsignupglobal');
        if ($selectpositiononsignupglobal) {
            $manager = totara_get_most_primary_manager($USER->id);
        } else {
            $manager = totara_get_manager($USER->id);
        }


        if (get_config(null, 'facetoface_addchangemanageremail') && !empty($manager)) {
            $manageremail = $manager->email;
        } else if (isset($fromform->manageremail)) {
            $manageremail = $fromform->manageremail;
        }

        $sid = empty($fromform->sid) ? false : $fromform->sid;

        $discountcodepropname = 'discountcode' . $sid;
        $discountcode = empty($fromform->$discountcodepropname) ? '' : $fromform->$discountcodepropname;
        $notificationtype = $fromform->notificationtype;

        $selfapprovalpropname = 'selfapprovaltc' . $sid;
        $selfapprovaltc = empty($fromform->$selfapprovalpropname) ? false : $fromform->$selfapprovalpropname;
        $settingautosignup = self::SETTING_AUTOSIGNUP;
        $autosignup = $instance->$settingautosignup;

        $signupparams = array();
        $signupparams['discountcode']     = $discountcode;
        $signupparams['notificationtype'] = $notificationtype;
        $signupparams['autoenrol'] = false;

        $timestart = time();
        if ($instance->enrolperiod) {
            $timeend = $timestart + $instance->enrolperiod;
        } else {
            $timeend = 0;
        }

        if (!empty($autosignup)) {
            $sessionstojoin = enrol_totara_facetoface_get_sessions_to_autoenrol($this, $course, $facetofaces);

            if (!is_array($sessionstojoin)) {
                $message = $sessionstojoin;
                $cssclass = 'notifymessage';
            } else {
                $joinedsessions = 0;
                foreach ($sessionstojoin as $session) {
                    $facetoface = $facetofaces[$session->facetoface];
                    $cm = get_coursemodule_from_instance('facetoface', $facetoface->id);
                    facetoface_user_import($course, $facetoface, $session, $USER->id, $signupparams);
                    add_to_log($course->id, 'facetoface', 'signup', "signup.php?s=$session->id", $session->id, $cm->id);
                    $joinedsessions++;
                }

                $message = get_string('autobookingcompleted', 'enrol_totara_facetoface', $joinedsessions);
                $cssclass = 'notifysuccess';
                $this->enrol_user($instance, $USER->id, $instance->roleid, $timestart, $timeend);
                $enrolled = true;
            }
        } else {
            $f2fselectedpositionelemid = 'selectedposition_' . $session->facetoface;
            if (property_exists($fromform, $f2fselectedpositionelemid)) {
                $signupparams['positionassignment'] = $fromform->$f2fselectedpositionelemid;
            }

            $session = $sessions[$sid];
            $facetoface = $facetofaces[$session->facetoface];
            $cm = get_coursemodule_from_instance('facetoface', $facetoface->id);

            $hasselfapproval = facetoface_session_has_selfapproval($facetoface, $session);
            if ($hasselfapproval && !$selfapprovaltc) {
                totara_set_notification(get_string('selfapprovalrequired', 'enrol_totara_facetoface'));
                redirect($returnurl);
            }

            // User can not update Manager's email (depreciated functionality).
            if (!empty($manageremail)) {
                add_to_log(
                    $course->id,
                    'facetoface',
                    'update manageremail (FAILED)',
                    "signup.php?s=$session->id",
                    $facetoface->id,
                    $cm->id
                );
            }

            // If multiple sessions are allowed then just check against this session.
            // Otherwise check against all sessions.
            $multisessionid = ($facetoface->multiplesessions ? $session->id : null);
            if (!facetoface_session_has_capacity($session, $context) && (!$session->allowoverbook)) {
                print_error('sessionisfull', 'facetoface', $returnurl);
            } else if (facetoface_get_user_submissions(
                $facetoface->id,
                $USER->id,
                MDL_F2F_STATUS_REQUESTED,
                MDL_F2F_STATUS_FULLY_ATTENDED,
                $multisessionid)
            ) {
                print_error('alreadysignedup', 'facetoface', $returnurl);
            } else if (facetoface_manager_needed($facetoface) && empty($manager->email) && !$hasselfapproval) {
                print_error('error:manageremailaddressmissing', 'facetoface', $returnurl);
            }

            $result = facetoface_user_import($course, $facetoface, $session, $USER->id, $signupparams);

            if ($result['result'] === true) {
                add_to_log($course->id, 'facetoface', 'signup', "signup.php?s=$session->id", $session->id, $cm->id);

                if (!empty($facetoface->approvalreqd) && !$hasselfapproval) {
                    $message = get_string('bookingcompleted_approvalrequired', 'facetoface');
                    $cssclass = 'notifymessage';
                } else {
                    $message = get_string('bookingcompleted', 'facetoface');
                    $cssclass = 'notifysuccess';
                }

                if ($session->datetimeknown
                    && isset($facetoface->confirmationinstrmngr)
                    && !empty($facetoface->confirmationstrmngr)) {
                    $message .= html_writer::start_tag('p');
                    $message .= get_string('confirmationsentmgr', 'facetoface');
                    $message .= html_writer::end_tag('p');
                } else {
                    if ($notificationtype != MDL_F2F_NONE) {
                        $message .= html_writer::start_tag('p');
                        $message .= get_string('confirmationsent', 'facetoface');
                        $message .= html_writer::end_tag('p');
                    }
                }

            } else {
                if ((isset($result['conflict']) && $result['conflict']) || isset($result['result'])) {
                    totara_set_notification($result['result'], $returnurl);
                } else {
                    add_to_log($course->id, 'facetoface', 'signup (FAILED)', "signup.php?s=$session->id", $session->id, $cm->id);
                    print_error('error:problemsigningup', 'facetoface', $returnurl);
                }
            }
            // Enrol or add pending enrolent.
            if (!empty($facetoface->approvalreqd) && !$hasselfapproval) {
                $toinsert = (object)array(
                    'enrolid' => $instance->id,
                    'userid' => $USER->id,
                    'timecreated' => time(),
                );
                $DB->insert_record('enrol_totara_f2f_pending', $toinsert);
                $returnurl = new moodle_url('/');
            } else {
                $this->enrol_user($instance, $USER->id, $instance->roleid, $timestart, $timeend);
                $enrolled = true;
                add_to_log(
                    $instance->courseid,
                    'course',
                    'enrol',
                    '../enrol/totara_facetoface/signup.phpusers.php?id='.$instance->courseid,
                    $instance->courseid
                );
            }
        }

        // Send welcome message.
        if ($enrolled && $instance->customint4) {
            $this->email_welcome_message($instance, $USER);
        }

        totara_set_notification($message, $returnurl, array('class' => $cssclass));

        return $enrolled;
    }

    /**
     * Creates course enrol form, checks if form submitted
     * and enrols user if necessary. It can also redirect.
     *
     * @param stdClass $instance
     * @return string html text, usually a form in a text box
     */
    public function enrol_page_hook(stdClass $instance) {
        global $CFG, $OUTPUT, $DB;

        require_once($CFG->dirroot . '/enrol/totara_facetoface/signup_form.php');

        $enrolstatus = $this->can_self_enrol($instance);

        // Don't show enrolment instance form, if user can't enrol using it.
        if (true === $enrolstatus) {
            $form = new enrol_totara_facetoface_signup_form(null, $instance);

            $instanceid = optional_param('instance', 0, PARAM_INT);
            if ($instance->id == $instanceid) {
                if ($data = $form->get_data()) {
                    $course = $DB->get_record('course', array('id' => $instance->courseid), '*', MUST_EXIST);
                    $returnurl = new moodle_url('/enrol/index.php', array('id' => $course->id));
                    $this->enrol_totara_facetoface($instance, $data, $course, $returnurl);
                }
            }

            ob_start();
            $form->display();
            $output = ob_get_clean();
            return $OUTPUT->box($output);
        }

        if ($enrolstatus == get_string('cannotenrolalreadyrequested', 'enrol_totara_facetoface')) {
            $url = new moodle_url('/enrol/totara_facetoface/withdraw.php', array('eid' => $instance->id));

            $output = html_writer::start_tag('p');
            $output .= $enrolstatus;
            $output .= html_writer::end_tag('p');
            $output .= html_writer::start_tag('p');
            $output .= html_writer::link($url, get_string('withdrawpending', 'enrol_totara_facetoface'), array('class' => 'link-as-button'));
            $output .= html_writer::end_tag('p');
            return $output;
        }
    }

    /**
     * Creates course enrol form, checks if form submitted
     * and enrols user if necessary. It can also redirect.
     *
     * @param $form
     * @return MoodleQuickForm Instance of the enrolment form if successful, else false.
     */
    public function course_expand_enrol_hook($form, $instance) {
        global $DB;

        $course = $DB->get_record('course', array('id'=>$instance->courseid), '*', MUST_EXIST);

        if ($data = $form->get_data()) {
            if ($this->enrol_totara_facetoface($instance, $data, $course, null)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Creates course enrol form, checks if form submitted
     * and enrols user if necessary. It can also redirect.
     *
     * @param stdClass $instance
     * @return moodleform Instance of the enrolment form if successful, else false.
     */
    public function course_expand_get_form_hook($instance) {
        global $CFG;

        require_once("$CFG->dirroot/enrol/totara_facetoface/signup_form.php");

        $enrolstatus = $this->can_self_enrol($instance);

        // Don't show enrolment instance form, if user can't enrol using it.
        if ($enrolstatus === true) {
            return new enrol_totara_facetoface_signup_form(null, $instance);
        }
        return $enrolstatus;
    }

    /**
     * Checks if user can self enrol.
     *
     * @param stdClass $instance enrolment instance
     * @param bool $checkuserenrolment if true will check if user enrolment is inactive.
     *             used by navigation to improve performance.
     * @return bool|string true if successful, else error message or false.
     */
    public function can_self_enrol(stdClass $instance, $checkuserenrolment = true) {
        global $DB, $USER, $CFG;

        $time = time();

        if ($checkuserenrolment) {
            if (isguestuser()) {
                // Can not enrol guest.
                return get_string('cannotenrol', 'enrol_totara_facetoface');
            }
            // Check if user is already enroled.
            if ($DB->get_record('user_enrolments', array('userid' => $USER->id, 'enrolid' => $instance->id))) {
                return get_string('cannotenrol', 'enrol_totara_facetoface');
            }
        }

        if ($instance->status != ENROL_INSTANCE_ENABLED) {
            return get_string('cannotenrol', 'enrol_totara_facetoface');
        }

        if ($instance->enrolstartdate != 0 and $instance->enrolstartdate > $time) {
            return get_string('cannotenrol', 'enrol_totara_facetoface');
        }

        if ($instance->enrolenddate != 0 and $instance->enrolenddate < $time) {
            return get_string('cannotenrol', 'enrol_totara_facetoface');
        }

        if (!$instance->customint6) {
            // New enrols not allowed.
            return get_string('cannotenrol', 'enrol_totara_facetoface');
        }

        if ($DB->record_exists('user_enrolments', array('userid' => $USER->id, 'enrolid' => $instance->id))) {
            return get_string('cannotenrol', 'enrol_totara_facetoface');
        }

        if ($instance->customint3 > 0) {
            // Max enrol limit specified.
            $count = $DB->count_records('user_enrolments', array('enrolid' => $instance->id));
            if ($count >= $instance->customint3) {
                // Bad luck, no more totara_facetoface enrolments here.
                return get_string('maxenrolledreached', 'enrol_totara_facetoface');
            }
        }

        if ($instance->customint5) {
            require_once($CFG->dirroot . '/cohort/lib.php');
            if (!cohort_is_member($instance->customint5, $USER->id)) {
                $cohort = $DB->get_record('cohort', array('id' => $instance->customint5));
                if (!$cohort) {
                    return null;
                }
                $a = format_string($cohort->name, true, array('context' => context::instance_by_id($cohort->contextid)));
                return markdown_to_html(get_string('cohortnonmemberinfo', 'enrol_totara_facetoface', $a));
            }
        }

        // Face-to-face-related condition checks.

        // Get sessions.
        $sessions = $this->get_enrolable_sessions($instance->courseid);
        if (empty($sessions)) {
            if ($this->sessions_require_manager()) {
                return true;
            }
            return get_string('cannotenrolnosessions', 'enrol_totara_facetoface');
        }

        // If I already have a pending request, cannot ask again.
        if ($DB->record_exists('enrol_totara_f2f_pending', array('enrolid' => $instance->id, 'userid' => $USER->id))) {
            return get_string('cannotenrolalreadyrequested', 'enrol_totara_facetoface');
        }

        return true;
    }

    /**
     * Return information for enrolment instance containing list of parameters required
     * for enrolment, name of enrolment plugin etc.
     *
     * @param stdClass $instance enrolment instance
     * @return stdClass instance info.
     */
    public function get_enrol_info(stdClass $instance) {

        $instanceinfo = new stdClass();
        $instanceinfo->id = $instance->id;
        $instanceinfo->courseid = $instance->courseid;
        $instanceinfo->type = $this->get_name();
        $instanceinfo->name = $this->get_instance_name($instance);
        $instanceinfo->status = $this->can_self_enrol($instance);

        return $instanceinfo;
    }

    /**
     * Add new instance of enrol plugin with default settings.
     * Returns id of instance or null if creation failed.
     * @param stdClass $course
     * @return int|null id of new instance
     */
    public function add_default_instance($course) {
        $fields = $this->get_instance_defaults();

        return $this->add_instance($course, $fields);
    }

    /**
     * Returns defaults for new instances.
     * @return array
     */
    public function get_instance_defaults() {
        $expirynotify = $this->get_config('expirynotify');
        if ($expirynotify == 2) {
            $expirynotify = 1;
            $notifyall = 1;
        } else {
            $notifyall = 0;
        }

        $fields = array();
        $fields['status']          = $this->get_config('status');
        $fields['roleid']          = $this->get_config('roleid');
        $fields['enrolperiod']     = $this->get_config('enrolperiod');
        $fields['expirynotify']    = $expirynotify;
        $fields['notifyall']       = $notifyall;
        $fields['expirythreshold'] = $this->get_config('expirythreshold');
        $fields['customint2']      = $this->get_config('longtimenosee');
        $fields['customint3']      = $this->get_config('maxenrolled');
        $fields['customint4']      = $this->get_config('sendcoursewelcomemessage');
        $fields['customint5']      = 0;
        $fields['customint6']      = $this->get_config('newenrols');
        $fields[self::SETTING_UNENROLWHENREMOVED] = $this->get_config('unenrolwhenremoved');

        return $fields;
    }

    /**
     * Send welcome email to specified user.
     *
     * @param stdClass $instance
     * @param stdClass $user user record
     * @return void
     */
    protected function email_welcome_message($instance, $user) {
        global $CFG, $DB;

        $course = $DB->get_record('course', array('id' => $instance->courseid), '*', MUST_EXIST);
        $context = context_course::instance($course->id);

        $a = new stdClass();
        $a->coursename = format_string($course->fullname, true, array('context' => $context));
        $a->profileurl = "$CFG->wwwroot/user/view.php?id={$user->id}&course={$course->id}";
        $strmgr = get_string_manager();

        if (trim($instance->customtext1) !== '') {
            $message = $instance->customtext1;
            $message = str_replace('{$a->coursename}', $a->coursename, $message);
            $message = str_replace('{$a->profileurl}', $a->profileurl, $message);
            if (strpos($message, '<') === false) {
                // Plain text only.
                $messagetext = $message;
                $messagehtml = text_to_html($messagetext, null, false, true);
            } else {
                // This is most probably the tag/newline soup known as FORMAT_MOODLE.
                $messagehtml = format_text(
                    $message,
                    FORMAT_MOODLE,
                    array('context' => $context, 'para' => false, 'newlines' => true, 'filter' => true)
                );
                $messagetext = html_to_text($messagehtml);
            }
        } else {
            $messagetext = $strmgr->get_string('welcometocoursetext', 'enrol_totara_facetoface', $a, $user->lang);
            $messagehtml = text_to_html($messagetext, null, false, true);
        }

        $subject = $strmgr->get_string(
            'welcometocourse',
            'enrol_totara_facetoface',
            format_string($course->fullname, true, array('context' => $context)),
            $user->lang
        );
        $subject =  str_replace('&amp;', '&', $subject);

        $rusers = array();
        if (!empty($CFG->coursecontact)) {
            $croles = explode(',', $CFG->coursecontact);
            list($sort, $sortparams) = users_order_by_sql('u');
            $rusers = get_role_users($croles, $context, true, '', 'r.sortorder ASC, ' . $sort, null, '', '', '', '', $sortparams);
        }
        if ($rusers) {
            $contact = reset($rusers);
        } else {
            $contact = core_user::get_support_user();
        }

        // Directly emailing welcome message rather than using messaging.
        email_to_user($user, $contact, $subject, $messagetext, $messagehtml);
    }

    /**
     * Enrol totara_facetoface cron support.
     * @return void
     */
    public function cron() {
        $trace = new text_progress_trace();
        $this->sync($trace, null);
        $this->send_expiry_notifications($trace);
    }

    /**
     * Sync all meta course links.
     *
     * @param progress_trace $trace
     * @param int $courseid one course, empty mean all
     * @return int 0 means ok, 1 means error, 2 means plugin disabled
     */
    public function sync(progress_trace $trace, $courseid = null) {
        global $DB;

        if (!enrol_is_enabled('totara_facetoface')) {
            $trace->finished();
            return 2;
        }

        // Unfortunately this may take a long time, execution can be interrupted safely here.
        @set_time_limit(0);
        raise_memory_limit(MEMORY_HUGE);

        $trace->output('Verifying totara_facetoface-enrolments...');

        $params = array('now' => time(), 'useractive' => ENROL_USER_ACTIVE, 'courselevel' => CONTEXT_COURSE);
        $coursesql = "";
        if ($courseid) {
            $coursesql = "AND e.courseid = :courseid";
            $params['courseid'] = $courseid;
        }

        // Note: the logic of totara_facetoface enrolment guarantees that user logged in at least once (=== u.lastaccess set)
        //       and that user accessed course at least once too (=== user_lastaccess record exists).

        // First deal with users that did not log in for a really long time - they do not have user_lastaccess records.
        $sql = "SELECT e.*, ue.userid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'totara_facetoface' AND e.customint2 > 0)
                  JOIN {user} u ON u.id = ue.userid
                 WHERE :now - u.lastaccess > e.customint2
                       $coursesql";
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $instance) {
            $userid = $instance->userid;
            unset($instance->userid);
            $this->unenrol_user($instance, $userid);
            $days = $instance->customint2 / DAYSECS;
            $msg = "unenrolling user $userid from course $instance->courseid as they have did not log in for at least $days days";
            $trace->output($msg, 1);
        }
        $rs->close();

        // Now unenrol from course user did not visit for a long time.
        $sql = "SELECT e.*, ue.userid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'totara_facetoface' AND e.customint2 > 0)
                  JOIN {user_lastaccess} ul ON (ul.userid = ue.userid AND ul.courseid = e.courseid)
                 WHERE :now - ul.timeaccess > e.customint2
                       $coursesql";
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $instance) {
            $userid = $instance->userid;
            unset($instance->userid);
            $this->unenrol_user($instance, $userid);
            $days = $instance->customint2 / DAYSECS;
            $msg = "unenrolling user $userid from course $instance->courseid as they did not access course for at least $days days";
            $trace->output($msg, 1);
        }
        $rs->close();

        $trace->output('...user totara_facetoface-enrolment updates finished.');
        $trace->finished();

        $this->process_expirations($trace, $courseid);

        return 0;
    }

    /**
     * Returns the user who is responsible for totara_facetoface enrolments in given instance.
     *
     * Usually it is the first editing teacher - the person with "highest authority"
     * as defined by sort_by_roleassignment_authority() having 'enrol/totara_facetoface:manage'
     * capability.
     *
     * @param int $instanceid enrolment instance id
     * @return stdClass user record
     */
    protected function get_enroller($instanceid) {
        global $DB;

        if ($this->lastenrollerinstanceid == $instanceid and $this->lastenroller) {
            return $this->lastenroller;
        }

        $instance = $DB->get_record('enrol', array('id' => $instanceid, 'enrol' => $this->get_name()), '*', MUST_EXIST);
        $context = context_course::instance($instance->courseid);

        if ($users = get_enrolled_users($context, 'enrol/totara_facetoface:manage')) {
            $users = sort_by_roleassignment_authority($users, $context);
            $this->lastenroller = reset($users);
            unset($users);
        } else {
            $this->lastenroller = parent::get_enroller($instanceid);
        }

        $this->lastenrollerinstanceid = $instanceid;

        return $this->lastenroller;
    }

    /**
     * Gets an array of the user enrolment actions.
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
        if ($this->allow_unenrol($instance) && has_capability("enrol/totara_facetoface:unenrol", $context)) {
            $url = new moodle_url('/enrol/unenroluser.php', $params);
            $actions[] = new user_enrolment_action(
                new pix_icon('t/delete', get_string('enroldelete', 'enrol_totara_facetoface')),
                get_string('unenrol', 'enrol'),
                $url,
                array('class' => 'unenrollink', 'rel' => $ue->id)
            );
        }
        if ($this->allow_manage($instance) && has_capability("enrol/totara_facetoface:manage", $context)) {
            $url = new moodle_url('/enrol/editenrolment.php', $params);
            $actions[] = new user_enrolment_action(
                new pix_icon('t/edit', get_string('enroledit', 'enrol_totara_facetoface')),
                get_string('edit'),
                $url,
                array('class' => 'editenrollink', 'rel' => $ue->id));
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
        if ($step->get_task()->get_target() == backup::TARGET_NEW_COURSE) {
            $merge = false;
        } else {
            $merge = array(
                'courseid'   => $data->courseid,
                'enrol'      => $this->get_name(),
                'roleid'     => $data->roleid,
            );
        }
        if ($merge and $instances = $DB->get_records('enrol', $merge, 'id')) {
            $instance = reset($instances);
            $instanceid = $instance->id;
        } else {
            if (!empty($data->customint5) && !$step->get_task()->is_samesite()) {
                // Use some id that can not exist in order to prevent totara_facetoface enrolment,
                // because we do not know what cohort it is in this site.
                $data->customint5 = -1;
            }
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
        $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, $data->status);
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
        // This is necessary only because we may migrate other types to this instance,
        // we do not use component in manual or totara_facetoface enrol.
        role_assign($roleid, $userid, $contextid, '', 0);
    }

    /*
     * Get list of enrolable sessions in the course of a given instance.
     * @param object $instance
     * @return array
     */
    public function get_enrolable_sessions($courseid, $user = null, $facetofaceid = null, $ignoreapprovals = false) {
        global $DB, $USER;

        if ($user === null) {
            $user = $USER;
        }
        if ($courseid !== null) {
            $cachekey = 'c' . $courseid;
        }
        if ($facetofaceid !== null) {
            $cachekey = 'f' . $facetofaceid;
        }

        if (!empty($this->sessions[$cachekey])) {
            return $this->sessions[$cachekey];
        }

        $params = array(
            'modulename' => 'facetoface',
            'courseid' => $courseid,
            'visible' => 1,
        );

        $sql = "
        SELECT ssn.*, f2f.id AS f2fid, f2f.approvalreqd
        FROM {course_modules} cm
        JOIN {modules} m ON (m.name = :modulename AND m.id = cm.module)
        JOIN {facetoface} f2f ON (f2f.id = cm.instance)
        JOIN {facetoface_sessions} ssn ON (ssn.facetoface = f2f.id)
        WHERE cm.visible = :visible";

        if ($courseid != null) {
            $sql .= " AND cm.course = :courseid";
            $params['courseid'] = $courseid;
        }

        if ($facetofaceid != null) {
            $sql .= " AND f2f.id = :facetofaceid";
            $params['facetofaceid'] = $facetofaceid;
        }

        $sql .= " ORDER BY f2f.id";

        $sessions = $DB->get_records_sql($sql, $params);
        $this->sessions[$cachekey] = array();
        if (empty($sessions)) {
            return $this->sessions[$cachekey];
        }

        $timenow = time();

        // Add dates.
        $sessids = array();
        foreach ($sessions as $sessid => $session) {
            $session->sessiondates = array();
            $sessids[] = $sessid;
        }
        list($idin, $params) = $DB->get_in_or_equal($sessids);
        $sessiondates = $DB->get_records_select('facetoface_sessions_dates', "sessionid $idin", $params);
        foreach ($sessiondates as $sessiondate) {
            $sessions[$sessiondate->sessionid]->sessiondates[] = $sessiondate;
        }

        $selectpositiononsignupglobal = get_config(null, 'facetoface_selectpositiononsignupglobal');
        if ($selectpositiononsignupglobal) {
            $manager = totara_get_most_primary_manager($USER->id);
        } else {
            $manager = totara_get_manager($USER->id);
        }

        foreach ($sessions as $session) {
            if ($session->roomid) {
                $room = $DB->get_record('facetoface_room', array('id' => $session->roomid));
                $session->room = $room;
            }

            $session->signupcount = facetoface_get_num_attendees($session->id, MDL_F2F_STATUS_REQUESTED);

            if ($session->datetimeknown && facetoface_has_session_started($session, $timenow)) {
                continue;
            }

            $hascapacity = $session->signupcount < $session->capacity;

            $cm = get_coursemodule_from_instance('facetoface', $session->facetoface);
            $context = context_module::instance($cm->id);
            $capabilitiesthatcanoverbook = array('mod/facetoface:overbook', 'mod/facetoface:addattendees');
            $canforceoverbook = has_any_capability($capabilitiesthatcanoverbook, $context, $user);

            // If there is no capacity, waitlist and user can't override capacity continue.
            if (!$hascapacity && !$session->allowoverbook && !$canforceoverbook) {
                continue;
            }
            $session->hasselfapproval = $session->approvalreqd && $session->selfapproval;
            if (!$ignoreapprovals && facetoface_manager_needed($session) && empty($manager->email) && !$session->hasselfapproval) {
                $this->removednomanager = true;
                continue;
            }
            $this->sessions[$cachekey][$session->id] = $session;
        }
        return $this->sessions[$cachekey];
    }

    /*
     * Indicates whether some sessions were not returned because user has no mamager.
     * @return bool;
     */
    public function sessions_require_manager() {
        return $this->removednomanager;
    }
}

/*
 * Handles change in signup status if relevant for enrolment
 * @param object $newstatus
 */
function enrol_totara_facetoface_statushandler($newstatus) {
    $status = enrol_totara_facetoface_enrol_on_approval($newstatus);
    $status = enrol_totara_facetoface_unenrol_on_removal($newstatus) && $status;

    return $status;
}

function enrol_totara_facetoface_deletedhandler($info) {
    global $DB;

    $status = true;
    if ($info->modulename == 'facetoface') { // Facetoface activity deleted.
        // Find all enrolment instances in this course of type totara_facetoface with 'unenrol when removed' enabled.
        $enrols = $DB->get_records('enrol', array('enrol' => 'totara_facetoface', 'courseid' => $info->courseid,
            enrol_totara_facetoface_plugin::SETTING_UNENROLWHENREMOVED => 1));
        foreach ($enrols as $enrolinst) {
            if (!$userids = $DB->get_fieldset_select('user_enrolments', 'userid', 'enrolid = ?', array($enrolinst->id))) {
                continue;
            }
            $status = enrol_totara_facetoface_unenrol_if_no_signups($enrolinst, $userids) && $status;
        }
    }
    return $status;
}

function enrol_totara_facetoface_enrol_on_approval($newstatus) {
    global $DB;

    $sql = "
    SELECT efd.*
    FROM {facetoface_signups} snp
    JOIN {facetoface_sessions} ssn ON (ssn.id = snp.sessionid)
    JOIN {facetoface} f2f ON (f2f.id = ssn.facetoface)
    JOIN {enrol} enr ON (enr.courseid = f2f.course)
    JOIN {enrol_totara_f2f_pending} efd ON (efd.enrolid = enr.id)
    WHERE snp.id = :signupid
    AND enr.enrol = :totara_facetoface
    AND efd.userid = snp.userid
    ";
    $params = array(
        'signupid' => $newstatus->signupid,
        'totara_facetoface' => 'totara_facetoface',
    );
    if (!$efdrec = $DB->get_record_sql($sql, $params)) {
        return true;
    }

    $DB->delete_records('enrol_totara_f2f_pending', array('id' => $efdrec->id));

    if ($newstatus->statuscode < MDL_F2F_STATUS_APPROVED) {
        return true;
    }

    // Enrol.
    if (!$enrol = $DB->get_record('enrol', array('id' => $efdrec->enrolid, 'enrol' => 'totara_facetoface'))) {
        return false;
    }

    $timestart = time();
    if ($enrol->enrolperiod) {
        $timeend = $timestart + $enrol->enrolperiod;
    } else {
        $timeend = 0;
    }

    $totara_facetoface = new enrol_totara_facetoface_plugin();
    $totara_facetoface->enrol_user($enrol, $efdrec->userid, $enrol->roleid, $timestart, $timeend);

    return true;
}

/**
 * If the enrol_totara_facetoface instance is set to unenrol users on removal:
 * Check if the user has been removed from the f2f session.
 * If they have, and are now removed from all sessions in the course, then unenrol them.
 *
 * @param object $newstatus
 * @return bool
 */
function enrol_totara_facetoface_unenrol_on_removal($newstatus) {
    global $DB;

    if ($newstatus->statuscode >= MDL_F2F_STATUS_REQUESTED) {
        return true; // Only interested in cancellations in this function.
    }

    // Look to see if the user is enroled via 'totara_facetoface' and 'unenrol when removed' is enabled.
    $sql = "SELECT e.*, su.userid
              FROM {facetoface_signups} su
              JOIN {facetoface_sessions} s ON s.id = su.sessionid
              JOIN {facetoface} f ON f.id = s.facetoface
              JOIN {enrol} e ON e.courseid = f.course AND e.enrol = :enrol
                   AND e.".enrol_totara_facetoface_plugin::SETTING_UNENROLWHENREMOVED." = 1
              JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = su.userid
             WHERE su.id = :signupid";
    $params = array('signupid' => $newstatus->signupid, 'enrol' => 'totara_facetoface');
    $enrolinst = $DB->get_record_sql($sql, $params);

    if (!$enrolinst) {
        return true; // User not enroled via 'totara_facetoface' OR 'unenrol when removed' not enabled for this instance.
    }

    // Check to see if the user is still signed up for any sessions in this course.
    enrol_totara_facetoface_unenrol_if_no_signups($enrolinst, $enrolinst->userid);

    return true;
}

/**
 * Check the userid(s) against f2f session sign ups + unenrol if none found.
 * Note: users who have pending session requests (but no confirmed sign-ups) will be unenroled.
 *
 * @param object $enrolinst
 * @param object[]|object $userids user(s) who are enroled in the course via totara_facetoface plugin
 * @return bool
 */
function enrol_totara_facetoface_unenrol_if_no_signups($enrolinst, $userids) {
    global $DB;

    if (!is_array($userids)) {
        $userids = array($userids);
    }

    list($usql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

    $sql = "SELECT DISTINCT su.userid
              FROM {facetoface} f
              JOIN {facetoface_sessions} s ON s.facetoface = f.id
              JOIN {facetoface_signups} su ON su.sessionid = s.id
              JOIN {facetoface_signups_status} sus ON sus.signupid = su.id AND sus.superceded = 0
                                                  AND sus.statuscode >= :approved
             WHERE f.course = :courseid AND su.userid $usql";
    $params['approved'] = MDL_F2F_STATUS_APPROVED;
    $params['courseid'] = $enrolinst->courseid;
    $signedup = $DB->get_fieldset_sql($sql, $params);

    $tounenrol = array_diff($userids, $signedup); // Remove any users who are still signed up to a f2f session.
    if (!$tounenrol) {
        return true; // No users to unenrol.
    }

    // Unenrol all users still in the list.
    $enrol = enrol_get_plugin('totara_facetoface');
    foreach ($tounenrol as $userid) {
        $enrol->unenrol_user($enrolinst, $userid);
    }

    return true;
}

/**
 * Get the 'best' session from a face to face for a user to be signed up to.
 * @param enrol_totara_facetoface_plugin $totara_facetoface
 * @param int $facetofaceid
 * @return object|null
 */
function enrol_totara_facetoface_find_best_session($totara_facetoface, $facetofaceid) {
    $facetofacesessions = $totara_facetoface->get_enrolable_sessions(null, null, $facetofaceid, true);

    $best = null;
    foreach ($facetofacesessions as $session) {

        $session->hascapacity = ($session->capacity - $session->signupcount) > 0;

        if ($session->hascapacity) {
            $session->waitlistcount = 0;
            $session->spaces = $session->capacity - $session->signupcount;
        } else {
            $session->waitlistcount = $session->signupcount - $session->capacity;
            $session->spaces = 0;
        }

        if ($best === null) { // If we dont have a best yet then this will do.
            $best = $session;
            continue;
        }

        if (!$best->hascapacity && $session->hascapacity) { // If best has no capacity and contender does then it wins.
            $best = $session;
            continue;
        } else if ($best->hascapacity && !$session->hascapacity) { // If best has capacity and contender doesn't we don't want it.
            continue;
        } else if (!$best->hascapacity && !$session->hascapacity) { // If neither have capacity take the shortest wait list.
            if ($best->waitlistcount > $session->waitlistcount) {
                $best = $session;
                continue;
            }
        } // If they both have capacity then we carry on to look at dates.

        if (!$best->datetimeknown && $session->datetimeknown) { // If best has no date and contender does then it wins.
            $best = $session;
            continue;
        } else if ($best->datetimeknown && !$session->datetimeknown) { // If best has date and contender doesn't we don't want it.
            continue;
        } else if (!$best->datetimeknown && !$session->datetimeknown) { // If neither have date go for most capacity.
            if ($best->spaces < $session->spaces) {
                $best = $session;
            }
            continue;
        } else if ($best->datetimeknown && $session->datetimeknown) { // If session is before best then it wins.
            $bestearliestsession = null;
            $sessionearliestsession = null;

            foreach ($best->sessiondates as $date) {
                if ($bestearliestsession === null || $bestearliestsession > $date->timestart) {
                    $bestearliestsession = $date->timestart;
                }
            }
            foreach ($session->sessiondates as $date) {
                if ($sessionearliestsession === null || $sessionearliestsession > $date->timestart) {
                    $sessionearliestsession = $date->timestart;
                }
            }

            if ($sessionearliestsession < $bestearliestsession) {
                $best = $session;
                continue;
            }
        }
    }

    return $best;
}

/**
 * Gets an array of sessions that the user should be signed up for when autoenrolling.
 * @param enrol_totara_facetoface_plugin $totara_facetoface
 * @param object $course
 * @param array $facetofaces
 * @param object|null $user
 * @return array
 */
function enrol_totara_facetoface_get_sessions_to_autoenrol($totara_facetoface, $course, $facetofaces, $user = null) {
    global $USER;
    $sessions = array();

    if ($user == null) {
        $user = $USER;
    }

    $autosessions = $totara_facetoface->get_enrolable_sessions($course->id, $user, null, true);
    $sessionstochoosefrom = array();

    // Move the sessions into an array grouped by face to face id.
    foreach ($autosessions as $session) {
        $sessionstochoosefrom[$session->facetoface][] = $session;
    }

    foreach ($sessionstochoosefrom as $facetofaceid => $facetofacesessions) {
        $facetoface = $facetofaces[$facetofaceid];
        $facetoface->approvalreqd = false; // No approval is ever required if you are being auto enrolled on sessions.

        $submissions = facetoface_get_user_submissions($facetofaceid, $user->id, MDL_F2F_STATUS_REQUESTED);

        // Signup to all sessions from a f2f with multiplesessions true that they haven't signed up to.
        if ($facetofaces[$facetofaceid]->multiplesessions) {
            $submissionsbysession = array();
            foreach ($submissions as $submission) {
                $submissionsbysession[$submission->sessionid] = $submission;
            }

            foreach ($facetofacesessions as $session) {
                if (!array_key_exists($session->id, $submissionsbysession)) {
                    $sessions[$session->id] = $session;
                }
            }
            continue;
        }

        if ($submissions) { // If the user has already signed for a session on this f2f then skip it.
            continue;
        }

        $best = enrol_totara_facetoface_find_best_session($totara_facetoface, $facetofaceid);

        if ($best != null) {
            $sessions[$best->id] = $best;
        }
    }

    return $sessions;
}
