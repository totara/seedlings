<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**
 * Reminder functionality
 *
 * @package   totara
 * @copyright 2010 Catalyst IT Ltd
 * @author    Aaron Barnes <aaronb@catalyst.net.nz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->dirroot.'/completion/data_object.php');


/**
 * Return an array of all reminders set for a course
 *
 * @access  public
 * @param   $courseid   int
 * @return  array
 */
function get_course_reminders($courseid) {

    // Get all reminder objects
    $where = array(
        'courseid'  => $courseid,
        'deleted'   => 0
    );

    $reminders = reminder::fetch_all($where);

    // Make sure we always return an array
    if ($reminders) {
        return $reminders;
    }
    else {
        return array();
    }
}


/**
 * Reminder object, defines what the reminder
 * is tracking, it's title, etc.
 *
 * No much use by itself, but is required to
 * associate reminder_message's with
 *
 * @access  public
 */
class reminder extends data_object {

    /**
     * DB table
     * @var string  $table
     */
    public $table = 'reminder';

    /**
     * Array of required table fields, must start with 'id'.
     * @var array   $required_fields
     */
    public $required_fields = array('id', 'courseid', 'title', 'type',
        'timecreated', 'timemodified', 'modifierid', 'config', 'deleted');

    /**
     * Array of text table fields.
     * @var array   $text_fields
     */
    public $text_fields = array('title', 'config');

    /**
     * The course this reminder is associated with
     * @access  public
     * @var     int
     */
    public $courseid;

    /**
     * Reminder title, for configuration display purposes
     * @access  public
     * @var     string
     */
    public $title;

    /**
     * Reminder message type - needs to be supported in code
     * @access  public
     * @var     string
     */
    public $type;

    /**
     * Time the reminder was created
     * @access  public
     * @var     int
     */
    public $timecreated;

    /**
     * Time the reminder or it's messages were last modified
     * @access  public
     * @var     int
     */
    public $timemodified;

    /**
     * ID of the last user to modifiy the reminder or messages
     * @access  public
     * @var     int
     */
    public $modifierid;

    /**
     * Config data, used by the code handling the reminder's "type"
     * @access  public
     * @var     mixed
     */
    public $config;

    /**
     * Deleted flag
     * @access  public
     * @var     int
     */
    public $deleted;

    /**
     * Reminder period
     * @access public
     * @var int
     */
    public $period;


    /**
     * Finds and returns all data_object instances based on params.
     *
     * @param array $params associative arrays varname=>value
     * @return array array of data_object insatnces or false if none found.
     */
    public static function fetch_all($params) {
        return self::fetch_all_helper(
            'reminder',
            'reminder',
            $params
        );
    }


    /**
     * Get all associated reminder_message objects
     *
     * @access  public
     * @return  array
     */
    public function get_messages() {
        // Get any non-deleted messages
        $messages = reminder_message::fetch_all(
            array(
                'reminderid'    => $this->id,
                'deleted'       => 0
            )
        );

        // Make sure we always return an array
        if ($messages) {
            return $messages;
        }
        else {
            return array();
        }
    }


    /**
     * Return an object containing all the reminder and
     * message data in a format that suits the reminder_edit_form
     * definition
     *
     * @access  public
     * @return  object
     */
    public function get_form_data() {

        $formdata = clone $this;

        // Get tracked activity/course
        if (!empty($this->config)) {
            $config = unserialize($this->config);
            $formdata->tracking = $config['tracking'];
            $formdata->requirement = isset($config['requirement']) ? $config['requirement'] : '';
        }

        // Get an existing reminder messages
        foreach (array('invitation', 'reminder', 'escalation') as $mtype) {

            // Generate property names
            $nosend = "{$mtype}dontsend";
            $p = "{$mtype}period";
            $sm = "{$mtype}skipmanager";
            $s = "{$mtype}subject";
            $m = "{$mtype}message";

            $message = new reminder_message(
                array(
                    'reminderid'    => $this->id,
                    'deleted'       => 0,
                    'type'          => $mtype
                )
            );

            $formdata->$p = $message->period;
            $formdata->$sm = $message->copyto;
            $formdata->$s = $message->subject;
            $formdata->$m = $message->message;

            // If the message doesn't exist, and this is
            // a saved reminder - mark it as nosend
            if ($this->id && !$message->id) {
                $formdata->$nosend = 1;
            }
        }

        return $formdata;
    }
}


/**
 * Reminder_message object, defines the reminder
 * period, and email contents
 *
 * @access  public
 */
class reminder_message extends data_object {

    /**
     * DB table
     * @var string  $table
     */
    public $table = 'reminder_message';

    /**
     * Array of required table fields, must start with 'id'.
     * @var array   $required_fields
     */
    public $required_fields = array('id', 'reminderid', 'type', 'period',
        'subject', 'message', 'copyto', 'deleted');

    /**
     * Array of text table fields.
     * @var array   $text_fields
     */
    public $text_fields = array('copyto', 'subject', 'message');

    /**
     * Reminder record this message is associated with
     * @access  public
     * @var     int
     */
    public $reminderid;

    /**
     * Reminder message type - needs to be supported in code
     * @access  public
     * @var     string
     */
    public $type;

    /**
     * # of days after the tracked event occurs the message
     * needs to be sent
     * @access  public
     * @var     int
     */
    public $period;

    /**
     * Email message subject
     *
     * Will be run through reminder_email_substitutions()
     * @access  public
     * @var     string
     */
    public $subject;

    /**
     * Email message content
     *
     * Will be run through reminder_email_substitutions()
     * @access  public
     * @var     string
     */
    public $message;

    /**
     * Toggle where the email is copied to the users manager
     *
     * Badly named at the moment, as the only time the email
     * is copied is when the message is of type "escalation" and
     * $copyto is set to 0
     *
     * @TODO FIX COL NAME
     *
     * @access  public
     * @var     int
     */
    public $copyto;

    /**
     * Deleted flag
     * @access  public
     * @var     int
     */
    public $deleted;


    /**
     * Finds and returns a data_object instance based on params.
     * @static abstract
     *
     * @param array $params associative arrays varname=>value
     * @return object data_object instance or false if none found.
     */
    public static function fetch($params) {
        return self::fetch_helper(
            'reminder_message',
            'reminder_message',
            $params
        );
    }


    /**
     * Finds and returns all data_object instances based on params.
     *
     * @param array $params associative arrays varname=>value
     * @return array array of data_object insatnces or false if none found.
     */
    public static function fetch_all($params) {
        return self::fetch_all_helper(
            'reminder_message',
            'reminder_message',
            $params
        );
    }
}


/**
 * Cron function for sending out reminder messages, runs every cron iteration
 *
 * Loops through reminders, checking if the trigger event has required period
 * fore each of the messages has passed, then sends emails out recording
 * success in the reminder_sent table
 *
 * Called from admin/cron.php
 *
 * @access  public
 */
function reminder_cron() {
    global $DB, $CFG;

    // Get reminders
    $reminders = reminder::fetch_all(
        array(
            'deleted'   => 0
        )
    );

    // Check if any reminders found
    if (empty($reminders)) {
        return;
    }

    // Loop through reminders
    foreach ($reminders as $reminder) {

        // Get messages
        $messages = $reminder->get_messages();

        switch ($reminder->type) {
            case 'completion':

                // Check completion is still enabled in this course
                $course = $DB->get_record('course', array('id' => $reminder->courseid));
                $completion = new completion_info($course);

                if (!$completion->is_enabled()) {
                    mtrace('Completion no longer enabled in course: '.$course->id.', skipping');
                    continue;
                }

                mtrace('Processing reminder "'.$reminder->title.'" for course "'.$course->fullname.'" ('.$course->id.')');

                // Get the tracked activity/course
                $config = unserialize($reminder->config);

                // Get the required feedback's id
                $requirementid = $DB->get_field(
                    'course_modules',
                    'instance',
                    array('id' => $config['requirement'])
                );

                if (empty($requirementid)) {
                    mtrace('ERROR: No feedback requirement found for this reminder... SKIPPING');
                    continue;
                }

                // Check if we are tracking the course
                if ($config['tracking'] == 0) {
                    $tsql = "
                        INNER JOIN {course_completions} cc
                                ON cc.course = ?
                               AND cc.userid = u.id
                        ";
                    $tparams = array($course->id);
                } else {
                    // Otherwise get the activity
                    // Load moduleinstance
                    $cm = $DB->get_record('course_modules', array('id' => $config['tracking']));
                    $module = $DB->get_field('modules', 'name', array('id' => $cm->module));

                    $tsql = "
                        INNER JOIN {course_completion}_criteria cr
                                ON cr.course = ?
                               AND cr.criteriatype = ?
                               AND cr.module = ?
                               AND cr.moduleinstance = ?
                        INNER JOIN {course_completion_crit_compl} cc
                                ON cc.course = ?
                               AND cc.userid = u.id
                               AND cc.criteriaid = cr.id
                        ";
                    $tparams = array($course->id, COMPLETION_CRITERIA_TYPE_ACTIVITY, $module, $config['tracking'], $course->id);
                }

                // Process each message
                foreach ($messages as $message) {

                    // If it's a weekend, send no reminders except "Same day" ones.
                    if ($message->period && !reminder_is_businessday(time())){
                        continue;
                    }
                    // # of seconds after completion (for timestamp comparison)
                    if ($message->period) {
                        $periodsecs = (int) $message->period * 24 * 60 * 60;
                    } else {
                        $periodsecs = 0;
                    }

                    $now = time();

                    // Get anyone that needs a reminder sent that hasn't had one already
                    // and has yet to complete the required feedback
                    $sql = "
                        SELECT u.*, cc.timecompleted
                          FROM {user} u
                              {$tsql}
                     LEFT JOIN {reminder_sent} rs
                            ON rs.userid = u.id
                           AND rs.reminderid = ?
                           AND rs.messageid = ?
                     LEFT JOIN {feedback_completed} fc
                            ON fc.feedback = ?
                           AND fc.userid = u.id
                         WHERE fc.id IS NULL
                           AND rs.id IS NULL
                           AND (cc.timecompleted + ?) >= ?
                           AND (cc.timecompleted + ?) < ?
                    ";
                    $params = array_merge($tparams, array($reminder->id, $message->id, $requirementid, $periodsecs, $reminder->timecreated, $periodsecs, $now));
                    // Check if any users found
                    $rs = $DB->get_recordset_sql($sql, $params);
                    if (!$rs->valid()) {
                        mtrace("WARNING: no users to send reminder message to (message id {$message->id})... SKIPPING");
                        continue;
                    }

                    // Get deadline
                    $escalationtime = $DB->get_field(
                        'reminder_message',
                        'period',
                        array('reminderid' => $reminder->id,
                              'type' => 'escalation',
                              'deleted' => 0)
                    );

                    // Calculate days from now
                    $message->deadline = $escalationtime - $message->period;

                    // Message sent counts
                    $msent = 0;
                    $mfail = 0;

                    // Loop through results and send emails
                    foreach ($rs as $user) {

                        // Check that even with weekends accounted for the period has still passed.
                        if (!reminder_check_businessdays($user->timecompleted, $message->period)) {
                            continue;
                        }

                        // Get user's manager.
                        $manager = totara_get_manager($user->id);

                        // Generate email content.
                        $user->manager = $manager;
                        $content = reminder_email_substitutions($message->message, $user, $course, $message, $reminder);
                        $subject = reminder_email_substitutions($message->subject, $user, $course, $message, $reminder);

                        // Get course contact.
                        $rusers = array();
                        if (!empty($CFG->coursecontact)) {
                            $context = context_course::instance($course->id);
                            $croles = explode(',', $CFG->coursecontact);
                            list($sort, $sortparams) = users_order_by_sql('u');
                            $rusers = get_role_users($croles, $context, true, '', 'r.sortorder ASC, ' . $sort, null, '', '', '', '', $sortparams);
                        }
                        if ($rusers) {
                            $contact = reset($rusers);
                        } else {
                            $contact = generate_email_supportuser();
                        }

                        // Prepare message object.
                        $eventdata = new stdClass();
                        $eventdata->component         = 'moodle';
                        $eventdata->name              = 'instantmessage';
                        $eventdata->userfrom          = $contact;
                        $eventdata->userto            = $user;
                        $eventdata->subject           = $subject;
                        $eventdata->fullmessage       = $content;
                        $eventdata->fullmessageformat = FORMAT_PLAIN;
                        $eventdata->fullmessagehtml   = text_to_html($content, null, false, true);
                        $eventdata->smallmessage      = '';

                        // Send user email.
                        if (message_send($eventdata)) {
                            $sent = new stdClass();
                            $sent->reminderid = $reminder->id;
                            $sent->messageid = $message->id;
                            $sent->userid = $user->id;
                            $sent->timesent = time();

                            // Record in database.
                            if (!$DB->insert_record('reminder_sent', $sent)) {
                                mtrace('ERROR: Failed to insert reminder_sent record for userid '.$user->id);
                                ++$mfail;
                            } else {
                                ++$msent;
                            }
                        } else {
                            ++$mfail;
                            mtrace('Could not send email to ' . $user->email);
                        }

                        // Check if we need to send to their manager also.
                        if ($message->type === 'escalation' && empty($message->copyto)) {

                            if ($manager !== false) {
                                // Send manager email.
                                $eventdata->userto = $manager;
                                if (message_send($eventdata)) {
                                    ++$msent;
                                } else {
                                    ++$mfail;
                                    mtrace('Could not send email to ' . fullname($user) . '\'s manager at ' . $manager->email);
                                }
                            } else {
                                ++$mfail;
                                mtrace(fullname($user) . ' does not have a manager... Skipping manager email.');
                            }
                        }
                    }
                    $rs->close();
                    // Show stats for message
                    mtrace($msent.' "'.$message->type.'" type messages sent');
                    if ($mfail) {
                        mtrace($mfail.' "'.$message->type.'" type messages failed');
                    }
                }

                break;

            default:
                mtrace('Unsupported reminder type: '.$reminder->type);
        }
    }
}


/**
 * Make placeholder substitutions to a string (for make=ing emails dynamic)
 *
 * @access  private
 * @param   $content    string  String to make substitutions to
 * @param   $user       object  Recipients details
 * @param   $course     object  The reminder's course
 * @param   $message    object  The reminder message object
 * @param   $reminder   object  The reminder object
 * @return  string
 */
function reminder_email_substitutions($content, $user, $course, $message, $reminder) {
    global $CFG;

    // Generate substitution array
    $place = array();
    $subs = array();

    // User details
    $place[] = get_string('placeholder:firstname', 'totara_coursecatalog');
    $subs[] = $user->firstname;
    $place[] = get_string('placeholder:lastname', 'totara_coursecatalog');
    $subs[] = $user->lastname;

    // Course details
    $place[] = get_string('placeholder:coursepageurl', 'totara_coursecatalog');
    $subs[] = "{$CFG->wwwroot}/course/view.php?id={$course->id}";
    $place[] = get_string('placeholder:coursename', 'totara_coursecatalog');
    $subs[] = $course->fullname;

    // Manager name
    $place[] = get_string('placeholder:managername', 'totara_coursecatalog');
    $subs[] = $user->manager ? fullname($user->manager) : get_string('nomanagermessage', 'totara_coursecatalog');

    // Day counts
    $place[] = get_string('placeholder:dayssincecompletion', 'totara_coursecatalog');
    $subs[] = $message->period;
    $place[] = get_string('placeholder:daysuntildeadline', 'totara_coursecatalog');
    $subs[] = $message->deadline;

    // Make substitutions
    $content = str_replace($place, $subs, $content);

    return $content;
}


/**
 * Check that required time has still passed even if ignoring weekends
 *
 * @access  private
 * @param   $timestamp  int Event timestamp
 * @param   $period     int Number of days since
 * @param   $check      int Timestamp to check against (optional, used in tests)
 * @return  boolean
 */
function reminder_check_businessdays($timestamp, $period, $check = null) {
    // If no period, then it's instantaneous and has already passed
    if (!$period) {
        return true;
    }

    // Setup current time
    if (!$check) {
        $check = time();
    }

    // Loop through each day and if not a weekend, add it to the timestamp
    for ($reminderday = 1; $reminderday <= $period; $reminderday++ ) {

        // Add 24 hours to the timestamp
        $timestamp += (24 * 3600);

        // Saturdays and Sundays are not included in the
        // reminder period as entered by the user, extend
        // that period by 1
        if (!reminder_is_businessday($timestamp)) {
            $period++;
        }

        // If the timestamp move into the future after ignoring weekends,
        // return false
        if ($timestamp > $check) {
            return false;
        }
    }

    // Timestamp must still be in the past
    return true;
}

/**
 * Determines whether or not the given timestamp was on a business day.
 *
 * @param $timestamp
 * @return boolean
 */
function reminder_is_businessday($timestamp){
    // Converts the timestamp to the day of the week running from 0 = Sunday to 6 = Saturday
    //use %w instead of %u for Windows compatability
    $day = userdate($timestamp, '%w');
    return ($day != 0 && $day != 6);
}
