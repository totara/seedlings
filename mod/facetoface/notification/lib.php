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
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package modules
 * @subpackage facetoface
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/totara/message/messagelib.php');
require_once($CFG->dirroot . '/completion/data_object.php');

/**
 * Notification types
 */
define('MDL_F2F_NOTIFICATION_MANUAL',     1);
define('MDL_F2F_NOTIFICATION_SCHEDULED',  2);
define('MDL_F2F_NOTIFICATION_AUTO',       4);

/**
 * Booked recipient filters
 */
define('MDL_F2F_RECIPIENTS_ALLBOOKED',    1);
define('MDL_F2F_RECIPIENTS_ATTENDED',     2);
define('MDL_F2F_RECIPIENTS_NOSHOWS',      4);

/**
 * Notification schedule unit types
 */
define('MDL_F2F_SCHEDULE_UNIT_HOUR',     1);
define('MDL_F2F_SCHEDULE_UNIT_DAY',      2);
define('MDL_F2F_SCHEDULE_UNIT_WEEK',     4);

/**
 * Notification conditions
 */
define('MDL_F2F_CONDITION_BEFORE_SESSION',              1);
define('MDL_F2F_CONDITION_AFTER_SESSION',               2);
define('MDL_F2F_CONDITION_BOOKING_CONFIRMATION',        4);
define('MDL_F2F_CONDITION_CANCELLATION_CONFIRMATION',   8);
define('MDL_F2F_CONDITION_DECLINE_CONFIRMATION',        12);
define('MDL_F2F_CONDITION_WAITLISTED_CONFIRMATION',     16);
define('MDL_F2F_CONDITION_BOOKING_REQUEST',             32);
define('MDL_F2F_CONDITION_SESSION_DATETIME_CHANGE',     64);
define('MDL_F2F_CONDITION_TRAINER_CONFIRMATION',        128);
define('MDL_F2F_CONDITION_TRAINER_SESSION_CANCELLATION', 256);
define('MDL_F2F_CONDITION_TRAINER_SESSION_UNASSIGNMENT', 512);
define('MDL_F2F_CONDITION_RESERVATION_CANCELLED',        16384);
define('MDL_F2F_CONDITION_RESERVATION_ALL_CANCELLED',    32768);

/**
 * Notification sent state
 */
define('MDL_F2F_NOTIFICATION_STATE_NOT_SENT',       0);
define('MDL_F2F_NOTIFICATION_STATE_PARTIALLY_SENT', 1);
define('MDL_F2F_NOTIFICATION_STATE_FULLY_SENT',     2);


class facetoface_notification extends data_object {

    /**
     * DB Table
     * @var string $table
     */
    public $table = 'facetoface_notification';

    /**
     * Array of required table fields
     * @var array $required_fields
     */
    public $required_fields = array(
        'id', 'type', 'title', 'body', 'courseid', 'facetofaceid',
        'timemodified', 'usermodified'
    );

    /**
     * Array of text table fields
     * @var array $text_fields
     */
    public $text_fields = array('managerprefix', 'body');

    /**
     * Array of optional fields with default values - usually long text information that is not always needed.
     *
     * @access  public
     * @var     array   $optional_fields
     */
    public $optional_fields = array(
        'conditiontype' => null,
        'scheduleunit' => null,
        'scheduleamount' => null,
        'scheduletime' => null,
        'ccmanager' => 0,
        'managerprefix' => null,
        'booked' => 0,
        'waitlisted' => 0,
        'cancelled' => 0,
        'status' => 0,
        'issent' => 0
    );

    public $type;

    public $conditiontype;

    public $scheduleunit;

    public $scheduleamount;

    public $scheduletime;

    public $ccmanager;

    public $managerprefix;

    public $title;

    public $body;

    public $booked;

    public $waitlisted;

    public $cancelled;

    public $courseid;

    public $facetofaceid;

    public $status;

    public $issent;

    public $timemodified;

    public $usermodified;

    private $_event;

    private $_facetoface;

    private $_ical_attachment;

    /**
     * Finds and returns a data_object instance based on params.
     * @static static
     *
     * @param array $params associative arrays varname=>value
     * @return object data_object instance or false if none found.
     */
    public static function fetch($params) {
        return self::fetch_helper('facetoface_notification', __CLASS__, $params);
    }


    /**
     * Save to database
     *
     * @access  public
     * @return  bool
     */
    public function save() {
        global $USER;

        $no_zero = array('conditiontype', 'scheduleunit', 'scheduleamount', 'scheduletime');
        foreach ($no_zero as $nz) {
            if (empty($this->$nz)) {
                $this->$nz = null;
            }
        }

        // Calculate scheduletime
        if ($this->scheduleunit) {
            $this->scheduletime = $this->_get_timestamp();
        }

        // Set up modification data
        $this->usermodified = $USER->id;
        $this->timemodified = time();

        if ($this->id) {
            return $this->update();
        } else {
            return $this->insert();
        }
    }

    /**
    * Delete notification and any associated sent message data.
    *
    * @access public
    * @return bool
    */
    public function delete() {
        global $DB;
        //Delete message sent and history data.
        $DB->delete_records('facetoface_notification_sent', array('notificationid' => $this->id));
        $DB->delete_records('facetoface_notification_hist', array('notificationid' => $this->id));
        // Call main delete function in parent data_object class.
        parent::delete();
    }

    /**
     * Get timestamp from schedule data
     *
     * @access  private
     * @return  int
     */
    private function _get_timestamp() {
        switch ($this->scheduleunit) {
            case MDL_F2F_SCHEDULE_UNIT_HOUR:
                $unit = 60*60;
                break;

            case MDL_F2F_SCHEDULE_UNIT_DAY:
                $unit = 60*60*24;
                break;

            case MDL_F2F_SCHEDULE_UNIT_WEEK:
                $unit = 60*60*24*7;
                break;
        }

        return $unit * $this->scheduleamount;
    }


    /**
     * Get recipients list
     *
     * @access  private
     * @param   int     $sessionid  (optional)
     * @return  object|false    Recordset or false on error
     */
    private function _get_recipients($sessionid = null) {
        global $CFG, $MDL_F2F_STATUS, $DB;

        // Generate WHERE-clause
        $status = array();
        if ($this->booked) {
            switch ((int) $this->booked) {
                case MDL_F2F_RECIPIENTS_ALLBOOKED:
                    foreach ($MDL_F2F_STATUS as $key => $string) {
                        if ($key >= MDL_F2F_STATUS_BOOKED) {
                            $status[] = $key;
                        }
                    }
                    break;

                case MDL_F2F_RECIPIENTS_ATTENDED:
                    $status[] = MDL_F2F_STATUS_FULLY_ATTENDED;
                    break;

                case MDL_F2F_RECIPIENTS_NOSHOWS:
                    $status[] = MDL_F2F_STATUS_NO_SHOW;
                    break;
            }
        }

        if ($this->waitlisted) {
            $status[] = MDL_F2F_STATUS_WAITLISTED;
        }

        if ($this->cancelled) {
            $status[] = MDL_F2F_STATUS_USER_CANCELLED;
        }

        $where = 'f.id = ? ';
        $params = array($this->facetofaceid);

        if ($status) {
            list($statussql, $statusparams) = $DB->get_in_or_equal($status);
            $where .= ' AND sis.statuscode ' . $statussql;
            $params = array_merge($params, $statusparams);
        }

        if ($sessionid) {
            $where .= ' AND s.id = ? ';
            $params[] = $sessionid;
        }

        $where .= ' AND NOT EXISTS
            (SELECT id FROM
               {facetoface_notification_sent} ns
             WHERE
                 ns.userid = u.id
             AND ns.sessionid = s.id
             AND ns.notificationid = ?
            ) ';
        $params[] = $this->id;

        // Generate SQL
        $sql = '
            SELECT
                u.*,
                s.id AS sessionid
            FROM
                {user} u
            INNER JOIN
                {facetoface_signups} si
             ON si.userid = u.id
            INNER JOIN
                {facetoface_signups_status} sis
             ON si.id = sis.signupid
            AND sis.superceded = 0
            INNER JOIN
                {facetoface_sessions} s
             ON s.id = si.sessionid
            INNER JOIN
                {facetoface} f
             ON s.facetoface = f.id
            WHERE ' . $where;

        $recordset = $DB->get_recordset_sql($sql, $params);

        return $recordset;
    }


    /**
     * Check for scheduled notifications and send
     *
     * @access  public
     * @return  void
     */
    public function send_scheduled() {
        global $CFG, $DB;

        $notificationdisable = get_config(null, 'facetoface_notificationdisable');
        if (!empty($notificationdisable)) {
            return false;
        }

        echo "Checking for sessions to send notification to\n";

        // Find due scheduled notifications
        $sql = '
            SELECT
                s.id,
                sd.timestart,
                sd.timefinish
            FROM
                {facetoface_sessions} s
            INNER JOIN
                (
                    SELECT
                        sessionid,
                        MAX(timefinish) AS timefinish,
                        MIN(timestart) AS timestart
                    FROM
                        {facetoface_sessions_dates}
                    GROUP BY
                        sessionid
                ) sd
             ON sd.sessionid = s.id
             WHERE s.facetoface = ?
        ';

        $recordset = $DB->get_recordset_sql($sql, array($this->facetofaceid));
        if (!$recordset) {
            echo "No sessions found for scheduled notification\n";
            return false;
        }

        $time = time();
        $sent = 0;
        $sessions = array();
        foreach ($recordset as $session) {
            // Check if we have already processed and found at least one active signup for this session that needs sending.
            if (isset($sessions[$session->id])) {
                continue;
            }
            // Check if they aren't ready to have their notification sent
            switch ($this->conditiontype) {
                case MDL_F2F_CONDITION_BEFORE_SESSION:
                    if ($session->timestart < $time ||
                       ($session->timestart - $this->scheduletime) > $time) {
                        continue 2;
                    }
                    break;
                case MDL_F2F_CONDITION_AFTER_SESSION:
                    if ($session->timefinish > $time ||
                       ($session->timefinish + $this->scheduletime) > $time) {
                        continue 2;
                    }
                    break;
                default:
                    // Unexpected data, return and continue with next notification
                    return;
            }

            $sent++;
            if (!isset($sessions[$session->id])) {
                $sessions[$session->id] = $session->id;
            }
        }

        if (count($sessions) > 0) {
            foreach ($sessions as $sessionid => $session) {
                $this->send_to_users($sessionid);
            }
            echo "Sent scheduled notifications for {$sent} session(s)\n";
        } else {
            echo "No scheduled notifications need to be sent at this time\n";
        }

        $recordset->close();
    }


    /**
     * Send to all matching users
     *
     * @access  public
     * @param   int     $sessionid      (optional)
     * @return  void
     */
    public function send_to_users($sessionid = null) {
        global $DB;

        $notificationdisable = get_config(null, 'facetoface_notificationdisable');
        if (!empty($notificationdisable)) {
            return false;
        }

        // Get recipients
        $recipients = $this->_get_recipients($sessionid);

        if (!$recipients->valid()) {
            if (!CLI_SCRIPT) {
                echo get_string('norecipients', 'facetoface') . "\n";
            }
        } else {
            $count = 0;
            foreach ($recipients as $recipient) {
                $count++;
                $this->set_newevent($recipient, $recipient->sessionid);
                $this->send_to_user($recipient, $recipient->sessionid);
                $this->send_to_manager($recipient, $recipient->sessionid);
                $this->send_to_thirdparty($recipient, $recipient->sessionid);
                $this->delete_ical_attachment();
            }
            if (!CLI_SCRIPT) {
                echo get_string('sentxnotifications', 'facetoface', $count) . "\n";
            }

            $recipients->close();
        }
    }


    public function set_ical_attachment($ical_attachment) {
        $this->_ical_attachment = $ical_attachment;
    }

    public function set_facetoface($facetoface) {
        $this->_facetoface = $facetoface;
    }

    public function delete_ical_attachment() {
        if (!empty($this->_ical_attachment)) {
            $this->_ical_attachment->file->delete();
        }
    }

    /**
     * Send to a single user
     *
     * @access  public
     * @param   object  $user       User object
     * @param   int     $sessionid
     * @param   int     $sessiondate The specific sessiondate which this message is for.
     * @return  void
     */
    public function send_to_user($user, $sessionid, $sessiondate = null) {
        global $CFG, $USER, $DB;

        // Check notification or system notification is enabled.
        if (!$this->status || !empty($CFG->facetoface_notificationdisable)) {
            return;
        }

        $success = message_send($this->_event);
        if ($success) {
            if (!empty($sessiondate)) {
                $uid = (empty($this->_event->ical_uids) ? '' : array_shift($this->_event->ical_uids));
                $hist = new stdClass();
                $hist->notificationid = $this->id;
                $hist->sessionid = $sessionid;
                $hist->userid = $user->id;
                $hist->sessiondateid = $sessiondate->id;
                $hist->ical_uid = $uid;
                $hist->ical_method = $this->_event->ical_method;
                $hist->timecreated = time();
                $DB->insert_record('facetoface_notification_hist', $hist);
            } else {
                $dates = $this->_sessions[$sessionid]->sessiondates;
                foreach ($dates as $session_date) {
                    $uid = (empty($this->_event->ical_uids) ? '' : array_shift($this->_event->ical_uids));
                    $hist = new stdClass();
                    $hist->notificationid = $this->id;
                    $hist->sessionid = $sessionid;
                    $hist->userid = $user->id;
                    $hist->sessiondateid = $session_date->id;
                    $hist->ical_uid = $uid;
                    $hist->ical_method = $this->_event->ical_method;
                    $hist->timecreated = time();
                    $DB->insert_record('facetoface_notification_hist', $hist);
                }
            }

            // Mark notification as sent for user.
            $sent = new stdClass();
            $sent->sessionid = $sessionid;
            $sent->notificationid = $this->id;
            $sent->userid = $user->id;
            $DB->insert_record('facetoface_notification_sent', $sent);
        }
    }

    /**
     * Create a new event object
     *
     * @access  public
     * @param   object  $user       User object
     * @param   int     $sessionid
     * @param   int     $sessiondate The specific sessiondate which this message is for.
     * @return  object
     */
    public function set_newevent($user, $sessionid, $sessiondate = null) {
        global $CFG, $USER, $DB;

        // Load facetoface object
        if (empty($this->_facetoface)) {
            $this->_facetoface = $DB->get_record_sql("SELECT f2f.*, c.fullname AS coursename
                FROM {facetoface} f2f
                INNER JOIN {course} c ON c.id = f2f.course
                WHERE f2f.id = ?", array($this->facetofaceid));
        }
        if (!isset($this->_facetoface->coursename)) {
            $course = $DB->get_record('course', array('id' => $this->_facetoface->course), 'fullname');
            $this->_facetoface->coursename = $course->fullname;
        }

        // Load session object
        if (empty($this->_sessions[$sessionid])) {
            $this->_sessions[$sessionid] = facetoface_get_session($sessionid);
        }

        if (!empty($sessiondate)) {
            $this->_sessions[$sessionid]->sessiondates = array($sessiondate);
        }

        $options = array('context' => context_course::instance($this->_facetoface->course));
        $coursename = format_string($this->_facetoface->coursename, true, $options);

        // Note: $$text was failing randomly in PHP 5.6.0 me with undefined variable for some weird reason...
        $subject = facetoface_message_substitutions(
            $this->title,
            $coursename,
            $this->_facetoface->name,
            $user,
            $this->_sessions[$sessionid],
            $sessionid
        );
        $body = facetoface_message_substitutions(
            $this->body,
            $coursename,
            $this->_facetoface->name,
            $user,
            $this->_sessions[$sessionid],
            $sessionid
        );
        $managerprefix = facetoface_message_substitutions(
            $this->managerprefix,
            $coursename,
            $this->_facetoface->name,
            $user,
            $this->_sessions[$sessionid],
            $sessionid
        );
        $plaintext = format_text_email($body, FORMAT_HTML);

        $this->_event = new stdClass();
        $this->_event->component   = 'totara_message';
        $this->_event->name        = 'alert';
        $this->_event->userto      = $user;
        $this->_event->userfrom    = $USER;
        $this->_event->roleid      = $CFG->learnerroleid;
        $this->_event->subject     = $subject;
        $this->_event->fullmessage       = $plaintext;
        $this->_event->fullmessageformat = FORMAT_PLAIN;
        $this->_event->fullmessagehtml   = nl2br($body);
        $this->_event->smallmessage      = $plaintext;

        $this->_event->icon        = 'facetoface-regular';

        $plaintext = format_text_email($managerprefix, FORMAT_HTML);
        $this->_event->manager = new stdClass();
        $this->_event->manager->fullmessage       = $plaintext;
        $this->_event->manager->fullmessagehtml   = nl2br($managerprefix);
        $this->_event->manager->smallmessage      = $plaintext;

        // Speciality icons.
        if ($this->type == MDL_F2F_NOTIFICATION_AUTO) {
            switch ($this->conditiontype) {
            case MDL_F2F_CONDITION_CANCELLATION_CONFIRMATION:
                $this->_event->icon = 'facetoface-remove';
                break;
            case MDL_F2F_CONDITION_BOOKING_CONFIRMATION:
                $this->_event->icon = 'facetoface-add';
                break;
            case MDL_F2F_CONDITION_SESSION_DATETIME_CHANGE:
                $this->_event->icon = 'facetoface-update';
                break;
            case MDL_F2F_CONDITION_DECLINE_CONFIRMATION://KINEO #198 ad decline message
                $this->_event->icon = 'facetoface-decline';
                break;
            }
        }

        // Override normal email processor behaviour in order to handle attachments.
        $this->_event->sendemail = TOTARA_MSG_EMAIL_YES;
        $this->_event->msgtype   = TOTARA_MSG_TYPE_FACE2FACE;
        $this->_event->urgency   = TOTARA_MSG_URGENCY_NORMAL;
        $ical_content = '';
        $ical_uids = null;
        $ical_method = '';

        if (!empty($this->_ical_attachment) && $this->conditiontype != MDL_F2F_CONDITION_WAITLISTED_CONFIRMATION) {
            $this->_event->attachment = $this->_ical_attachment->file;

            if ($this->conditiontype == MDL_F2F_CONDITION_CANCELLATION_CONFIRMATION ||
                $this->conditiontype == MDL_F2F_CONDITION_DECLINE_CONFIRMATION) {
                $this->_event->attachname = 'cancel.ics';
            } else {
                $this->_event->attachname = 'invite.ics';
            }

            $ical_content = $this->_ical_attachment->content;

            if (!empty($ical_content)) {
                preg_match_all('/UID:([^\r\n ]+)/si', $ical_content, $matches);
                $ical_uids = $matches[1];
                preg_match('/METHOD:([a-z]+)/si', $ical_content, $matches);
                $ical_method = $matches[1];
            }
        }
        $this->_event->ical_uids  = $ical_uids;
        $this->_event->ical_method  = $ical_method;
    }

    /**
     * Send to a manager
     *
     * @access  public
     * @param   object  $user       User object
     * @param   int     $sessionid
     * @return  void
     */
    public function send_to_manager($user, $sessionid) {
        global $CFG, $DB;

        $params = array('userid'=>$user->id, 'sessionid'=>$sessionid);
        $positiontype = $DB->get_field('facetoface_signups', 'positiontype', $params);

        if ($this->ccmanager && $manager = totara_get_manager($user->id, $positiontype)) {

            $event = clone $this->_event;

            $event->userto = $manager;
            $event->roleid = $CFG->managerroleid;
            $event->fullmessage       = $event->manager->fullmessage;
            $event->fullmessagehtml   = $event->manager->fullmessagehtml;
            $event->smallmessage      = $event->manager->smallmessage;
            // Do not send iCal attachment.
            $event->attachment = $event->attachname = null;

            if ($this->conditiontype == MDL_F2F_CONDITION_BOOKING_REQUEST) {
                // Do the facetoface workflow event.
                $strmgr = get_string_manager();
                $onaccept = new stdClass();
                $onaccept->action = 'facetoface';
                $onaccept->text = $strmgr->get_string('approveinstruction', 'facetoface', null, $manager->lang);
                $onaccept->data = array('userid' => $user->id, 'session' => $this->_sessions[$sessionid], 'facetoface' => $this->_facetoface);
                $event->onaccept = $onaccept;
                $onreject = new stdClass();
                $onreject->action = 'facetoface';
                $onreject->text = $strmgr->get_string('rejectinstruction', 'facetoface', null, $manager->lang);
                $onreject->data = array('userid' => $user->id, 'session' => $this->_sessions[$sessionid], 'facetoface' => $this->_facetoface);
                $event->onreject = $onreject;

                $event->name = 'task';
                message_send($event);
            } else {
                message_send($event);
            }
        }
    }

    /**
     * Send to a third party
     *
     * @access  public
     * @param   object  $user       User object
     * @param   int     $sessionid
     * @return  void
     */
    public function send_to_thirdparty($user, $sessionid) {

        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
            return true;
        }

        // Third-party notification.
        if (!empty($this->_facetoface->thirdparty) && ($this->_sessions[$sessionid]->datetimeknown || !empty($this->_facetoface->thirdpartywaitlist))) {
            $event = clone $this->_event;
            $event->attachment = null; // Leave out the ical attachments in the third-parties notification.
            $recipients = array_map('trim', explode(',', $this->_facetoface->thirdparty));
            foreach ($recipients as $recipient) {
                $event->userto = \totara_core\totara_user::get_external_user($recipient);
                $event->fullmessage       = $event->manager->fullmessage;
                $event->fullmessagehtml   = $event->manager->fullmessagehtml;
                $event->smallmessage      = $event->manager->smallmessage;
                message_send($event);
            }
        }
    }

    /**
     * Get desciption of notification condition
     *
     * @access  public
     * @return  string
     */
    public function get_condition_description() {
        $html = '';

        $time = $this->scheduleamount;
        if ($time == 1) {
            $unit = get_string('schedule_unit_'.$this->scheduleunit.'_singular', 'facetoface');
        } elseif ($time > 1) {
            $unit = get_string('schedule_unit_'.$this->scheduleunit, 'facetoface', $time);
        }

        // Generate note
        switch ($this->type) {
            case MDL_F2F_NOTIFICATION_MANUAL:

                if ($this->status) {
                    $html .= get_string('occuredonx', 'facetoface', userdate($this->timemodified));
                } else {
                    $html .= get_string('occurswhenenabled', 'facetoface');
                }
                break;

            case MDL_F2F_NOTIFICATION_SCHEDULED:
            case MDL_F2F_NOTIFICATION_AUTO:

                switch ($this->conditiontype) {
                    case MDL_F2F_CONDITION_BEFORE_SESSION:
                        $html .= get_string('occursxbeforesession', 'facetoface', $unit);
                        break;
                    case MDL_F2F_CONDITION_AFTER_SESSION:
                        $html .= get_string('occursxaftersession', 'facetoface', $unit);
                        break;
                    case MDL_F2F_CONDITION_BOOKING_CONFIRMATION:
                        $html .= get_string('occurswhenuserbookssession', 'facetoface');
                        break;
                    case MDL_F2F_CONDITION_CANCELLATION_CONFIRMATION:
                        $html .= get_string('occurswhenusersbookingiscancelled', 'facetoface');
                        break;
                    case MDL_F2F_CONDITION_WAITLISTED_CONFIRMATION:
                        $html .= get_string('occurswhenuserwaitlistssession', 'facetoface');
                        break;
                    case MDL_F2F_CONDITION_BOOKING_REQUEST:
                        $html .= get_string('occurswhenuserrequestssessionwithmanagerapproval', 'facetoface');
                        break;
                    case MDL_F2F_CONDITION_DECLINE_CONFIRMATION:
                        $html .= get_string('occurswhenuserrequestssessionwithmanagerdecline', 'facetoface');
                        break;
                }

                break;
        }

        return $html;
    }


    /**
     * Get desciption of recipients
     *
     * @access  public
     * @return  string
     */
    public function get_recipient_description() {
        $recips = array();
        if ($this->booked) {
            switch ($this->booked) {
                case MDL_F2F_RECIPIENTS_ALLBOOKED:
                    $recips[] = get_string('recipients_allbooked', 'facetoface');
                    break;
                case MDL_F2F_RECIPIENTS_ATTENDED:
                    $recips[] = get_string('recipients_attendedonly', 'facetoface');
                    break;
                case MDL_F2F_RECIPIENTS_NOSHOWS:
                    $recips[] = get_string('recipients_noshowsonly', 'facetoface');
                    break;
            }
        }

        if (!empty($this->waitlisted)) {
            $recips[] = get_string('status_waitlisted', 'facetoface');
        }

        if (!empty($this->cancelled)) {
            $recips[] = get_string('status_user_cancelled', 'facetoface');
        }

        return implode(', ', $recips);
    }


    /**
     * Is this notification frozen (uneditable) or not?
     *
     * It should be if it is an existing, enabled manual notification
     *
     * @access  public
     * @return  boolean
     */
    public function is_frozen() {
        return $this->id && $this->status && $this->type == MDL_F2F_NOTIFICATION_MANUAL;
    }
}


/**
 * Send a notice.
 *
 * @param class $facetoface record from the facetoface table
 * @param class $session record from the facetoface_sessions table
 * @param integer $userid ID of the recipient of the email
 * @param array $params The parameters for the notification
 * @param int $icalattachmenttype The ical attachment type, or MDL_F2F_TEXT to disable ical attachments
 * @param int $icalattachmentmethod The ical method type: MDL_F2F_INVITE or MDL_F2F_CANCEL
 * @return string Error message (or empty string if successful)
 */
function facetoface_send_notice($facetoface, $session, $userid, $params, $icalattachmenttype = MDL_F2F_TEXT, $icalattachmentmethod = MDL_F2F_INVITE) {
    global $DB, $CFG;

    $notificationdisable = get_config(null, 'facetoface_notificationdisable');
    if (!empty($notificationdisable)) {
        return false;
    }

    $user = $DB->get_record('user', array('id' => $userid));
    if (!$user) {
        return 'userdoesnotexist';
    }

    $notice = new facetoface_notification($params);
    if (isset($facetoface->ccmanager)) {
        $notice->ccmanager = $facetoface->ccmanager;
    }
    $notice->set_facetoface($facetoface);

    if (!isset($session->notifyuser)) {
        $session->notifyuser = true;
    }

    if (get_config(null, 'facetoface_oneemailperday')) {
        // Keep track of all sessiondates.
        $sessiondates = $session->sessiondates;
        foreach ($sessiondates as $sessiondate) {
            $session->sessiondates = array($sessiondate); // One day at a time.
            if ((int)$icalattachmenttype == MDL_F2F_BOTH) {
                $ical_attach = facetoface_get_ical_attachment($icalattachmentmethod, $facetoface, $session, $userid);
                $notice->set_ical_attachment($ical_attach);
            }
            $notice->set_newevent($user, $session->id, $sessiondate);
            if ($session->notifyuser) {
                $notice->send_to_user($user, $session->id, $sessiondate);
            }
            $notice->send_to_manager($user, $session->id);
            $notice->send_to_thirdparty($user, $session->id);
            $notice->delete_ical_attachment();
        }
        // Restore session dates.
        $session->sessiondates = $sessiondates;
    } else {
        if ((int)$icalattachmenttype == MDL_F2F_BOTH) {
            $ical_attach = facetoface_get_ical_attachment($icalattachmentmethod, $facetoface, $session, $userid);
            $notice->set_ical_attachment($ical_attach);
        }
        $notice->set_newevent($user, $session->id);
        if ($session->notifyuser) {
            $notice->send_to_user($user, $session->id);
        }
        $notice->send_to_manager($user, $session->id);
        $notice->send_to_thirdparty($user, $session->id);
        $notice->delete_ical_attachment();
    }
    return '';
}


/**
 * Send a confirmation email to the user and manager regarding the
 * cancellation
 *
 * @param class $facetoface record from the facetoface table
 * @param class $session record from the facetoface_sessions table
 * @param integer $userid ID of the recipient of the email
 * @returns string Error message (or empty string if successful)
 */
function facetoface_send_cancellation_notice($facetoface, $session, $userid) {
    global $CFG;

    $params = array(
        'facetofaceid'  => $facetoface->id,
        'type'          => MDL_F2F_NOTIFICATION_AUTO,
        'conditiontype' => MDL_F2F_CONDITION_CANCELLATION_CONFIRMATION
    );

    $includeical = empty($CFG->facetoface_disableicalcancel);
    return facetoface_send_notice($facetoface, $session, $userid, $params, $includeical ? MDL_F2F_BOTH : MDL_F2F_TEXT, MDL_F2F_CANCEL);
}

/**
 * Send a confirmation email to the user and manager regarding the
 * cancellation
 *
 * @param class $facetoface record from the facetoface table
 * @param class $session record from the facetoface_sessions table
 * @param integer $userid ID of the recipient of the email
 * @returns string Error message (or empty string if successful)
 */
function facetoface_send_decline_notice($facetoface, $session, $userid) {
    global $CFG;

    $params = array(
            'facetofaceid'  => $facetoface->id,
            'type'          => MDL_F2F_NOTIFICATION_AUTO,
            'conditiontype' => MDL_F2F_CONDITION_DECLINE_CONFIRMATION
            );

    $includeical = empty($CFG->facetoface_disableicalcancel);
    return facetoface_send_notice($facetoface, $session, $userid, $params, $includeical ? MDL_F2F_BOTH : MDL_F2F_TEXT, MDL_F2F_CANCEL);
}

/**
 * Send a email to the user and manager regarding the
 * session date/time change
 *
 * @param class $facetoface record from the facetoface table
 * @param class $session record from the facetoface_sessions table
 * @param integer $userid ID of the recipient of the email
 * @returns string Error message (or empty string if successful)
 */
function facetoface_send_datetime_change_notice($facetoface, $session, $userid) {
    global $DB;

    $params = array(
        'facetofaceid'  => $facetoface->id,
        'type'          => MDL_F2F_NOTIFICATION_AUTO,
        'conditiontype' => MDL_F2F_CONDITION_SESSION_DATETIME_CHANGE
    );

    return facetoface_send_notice($facetoface, $session, $userid, $params, MDL_F2F_BOTH, MDL_F2F_INVITE);
}


/**
 * Send a confirmation email to the user and manager
 *
 * @param class $facetoface record from the facetoface table
 * @param class $session record from the facetoface_sessions table
 * @param integer $userid ID of the recipient of the email
 * @param integer $notificationtype Type of notifications to be sent @see {{MDL_F2F_INVITE}}
 * @param boolean $iswaitlisted If the user has been waitlisted
 * @returns string Error message (or empty string if successful)
 */
function facetoface_send_confirmation_notice($facetoface, $session, $userid, $notificationtype, $iswaitlisted) {
    global $DB;

    $params = array(
        'facetofaceid'  => $facetoface->id,
        'type'          => MDL_F2F_NOTIFICATION_AUTO
    );

    if ($iswaitlisted) {
        $params['conditiontype'] = MDL_F2F_CONDITION_WAITLISTED_CONFIRMATION;
    } else {
        $params['conditiontype'] = MDL_F2F_CONDITION_BOOKING_CONFIRMATION;
    }

    return facetoface_send_notice($facetoface, $session, $userid, $params, $notificationtype, MDL_F2F_INVITE);
}


/**
 * Send a confirmation email to the trainer
 *
 * @param class $facetoface record from the facetoface table
 * @param class $session record from the facetoface_sessions table
 * @param integer $userid ID of the recipient of the email
 * @param integer $notificationtype Type of notifications to be sent @see {{MDL_F2F_INVITE}}
 * @returns string Error message (or empty string if successful)
 */
function facetoface_send_trainer_confirmation_notice($facetoface, $session, $userid) {
    global $DB;

    $params = array(
        'facetofaceid'  => $facetoface->id,
        'type'          => MDL_F2F_NOTIFICATION_AUTO,
        'conditiontype' => MDL_F2F_CONDITION_TRAINER_CONFIRMATION
    );

    return facetoface_send_notice($facetoface, $session, $userid, $params, MDL_F2F_BOTH, MDL_F2F_INVITE);
}


/**
 * Send a cancellation email to the trainer
 *
 * @param class $facetoface record from the facetoface table
 * @param class $session record from the facetoface_sessions table
 * @param integer $userid ID of the recipient of the email
 * @param integer $notificationtype Type of notifications to be sent @see {{MDL_F2F_INVITE}}
 * @returns string Error message (or empty string if successful)
 */
function facetoface_send_trainer_session_cancellation_notice($facetoface, $session, $userid) {
    global $DB;

    $params = array(
        'facetofaceid'  => $facetoface->id,
        'type'          => MDL_F2F_NOTIFICATION_AUTO,
        'conditiontype' => MDL_F2F_CONDITION_TRAINER_SESSION_CANCELLATION
    );

    return facetoface_send_notice($facetoface, $session, $userid, $params, MDL_F2F_BOTH, MDL_F2F_CANCEL);
}


/**
 * Send a unassignment email to the trainer
 *
 * @param class $facetoface record from the facetoface table
 * @param class $session record from the facetoface_sessions table
 * @param integer $userid ID of the recipient of the email
 * @param integer $notificationtype Type of notifications to be sent @see {{MDL_F2F_INVITE}}
 * @returns string Error message (or empty string if successful)
 */
function facetoface_send_trainer_session_unassignment_notice($facetoface, $session, $userid) {
    global $DB;

    $params = array(
        'facetofaceid'  => $facetoface->id,
        'type'          => MDL_F2F_NOTIFICATION_AUTO,
        'conditiontype' => MDL_F2F_CONDITION_TRAINER_SESSION_UNASSIGNMENT
    );

    return facetoface_send_notice($facetoface, $session, $userid, $params, MDL_F2F_BOTH, MDL_F2F_CANCEL);
}


/**
 * Send booking request notice to user and their manager
 *
 * @param   object  $facetoface Facetoface instance
 * @param   object  $session    Session instance
 * @param   int     $userid     ID of user requesting booking
 * @return  string  Error string, empty on success
 */
function facetoface_send_request_notice($facetoface, $session, $userid) {
    global $DB, $USER;

    $positiontype = $DB->get_field('facetoface_signups', 'positiontype', array('userid' => $userid, 'sessionid' => $session->id));

    $selectpositiononsignupglobal = get_config(null, 'facetoface_selectpositiononsignupglobal');
    if ($selectpositiononsignupglobal && empty($positiontype)) {
        $manager = totara_get_most_primary_manager($USER->id);
    } else {
        $manager = totara_get_manager($userid, $positiontype);
    }

    if (empty($manager->email)) {
        return 'error:nomanagersemailset';
    }

    $params = array(
        'facetofaceid'  => $facetoface->id,
        'type'          => MDL_F2F_NOTIFICATION_AUTO,
        'conditiontype' => MDL_F2F_CONDITION_BOOKING_REQUEST
    );

    return facetoface_send_notice($facetoface, $session, $userid, $params);
}


/**
 * Subsitute the placeholders in message templates for the actual data
 *
 * Expects the following parameters in the $data object:
 * - datetimeknown
 * - details
 * - discountcost
 * - duration
 * - normalcost
 * - sessiondates
 *
 * @access  public
 * @param   string  $msg            Email message
 * @param   string  $facetofacename F2F name
 * @param   obj     $user           The subject of the message
 * @param   obj     $data           Session data
 * @param   int     $sessionid      Session ID
 * @return  string
 */
function facetoface_message_substitutions($msg, $coursename, $facetofacename, $user, $data, $sessionid) {
    global $CFG, $DB;

    if (empty($msg)) {
        return '';
    }

    // Get timezone setting.
    $displaytimezones = get_config(null, 'facetoface_displaysessiontimezones');

    if ($data->datetimeknown) {
        // Scheduled session
        $alldates = '';
        foreach ($data->sessiondates as $date) {
            if ($alldates != '') {
                $alldates .= "\n";
            }
            $startdate = userdate($date->timestart, get_string('strftimedate'), $date->sessiontimezone);
            $finishdate = userdate($date->timefinish, get_string('strftimedate'), $date->sessiontimezone);
            if ($startdate == $finishdate) {
                $alldates .= $startdate . ', ';
            } else {
                $alldates .= $startdate . ' - ' . $finishdate . ', ';
            }
            $starttime = userdate($date->timestart, get_string('strftimetime'), $date->sessiontimezone);
            $finishtime = userdate($date->timefinish, get_string('strftimetime'), $date->sessiontimezone);
            $timestr = $starttime . ' - ' . $finishtime . ' ';
            $timestr .= $displaytimezones ? $date->sessiontimezone : '';
            $alldates .= $timestr;
        }

        $startdate = userdate($data->sessiondates[0]->timestart, get_string('strftimedate'), $date->sessiontimezone);
        $finishdate = userdate($data->sessiondates[0]->timefinish, get_string('strftimedate'), $date->sessiontimezone);
        $sessiondate = ($startdate == $finishdate) ? $startdate : $startdate . ' - ' . $finishdate;
        $starttime = userdate($data->sessiondates[0]->timestart, get_string('strftimetime'), $date->sessiontimezone);
        $finishtime = userdate($data->sessiondates[0]->timefinish, get_string('strftimetime'), $date->sessiontimezone);

    } else {
        // Wait-listed session
        $startdate   = get_string('unknowndate', 'facetoface');
        $finishdate  = get_string('unknowndate', 'facetoface');
        $sessiondate = get_string('unknowndate', 'facetoface');
        $alldates    = get_string('unknowndate', 'facetoface');
        $starttime   = get_string('unknowntime', 'facetoface');
        $finishtime  = get_string('unknowntime', 'facetoface');
    }

    $msg = str_replace(get_string('placeholder:coursename', 'facetoface'), $coursename, $msg);
    $msg = str_replace(get_string('placeholder:facetofacename', 'facetoface'), $facetofacename, $msg);
    $msg = str_replace(get_string('placeholder:firstname', 'facetoface'), $user->firstname, $msg);
    $msg = str_replace(get_string('placeholder:lastname', 'facetoface'), $user->lastname, $msg);
    $msg = str_replace(get_string('placeholder:cost', 'facetoface'), facetoface_cost($user->id, $sessionid, $data), $msg);
    $msg = str_replace(get_string('placeholder:alldates', 'facetoface'), $alldates, $msg);
    $msg = str_replace(get_string('placeholder:sessiondate', 'facetoface'), $sessiondate, $msg);
    $msg = str_replace(get_string('placeholder:startdate', 'facetoface'), $startdate, $msg);
    $msg = str_replace(get_string('placeholder:finishdate', 'facetoface'), $finishdate, $msg);
    $msg = str_replace(get_string('placeholder:starttime', 'facetoface'), $starttime, $msg);
    $msg = str_replace(get_string('placeholder:finishtime', 'facetoface'), $finishtime, $msg);
    $msg = str_replace(get_string('placeholder:duration', 'facetoface'), format_duration($data->duration), $msg);

    // add placeholders that somehow have been forgetten since moodle
    $roomnull = 'N/A';  // Displayed if empty.

    // Defaults if values are empty
    $strlocation = $roomnull;
    $strvenue = $roomnull;
    $strroom = $roomnull;

    if ($room = facetoface_get_session_room($sessionid)) {
        $strlocation = isset($room->address) ? $room->address : $roomnull;
        $strvenue = isset($room->building) ? $room->building : $roomnull;
        $strroom = isset($room->name) ? $room->name : $roomnull;
    }

    $msg = str_replace(get_string('placeholder:location', 'facetoface'), $strlocation, $msg);
    $msg = str_replace(get_string('placeholder:venue', 'facetoface'), $strvenue, $msg);
    $msg = str_replace(get_string('placeholder:room', 'facetoface'), $strroom, $msg);

    if (empty($data->details)) {
        $msg = str_replace(get_string('placeholder:details', 'facetoface'), '', $msg);
    } else {
        $msg = str_replace(get_string('placeholder:details', 'facetoface'), html_to_text($data->details), $msg);
    }

    // Replace more meta data
    $attendees_url = new moodle_url('/mod/facetoface/attendees.php', array('s' => $sessionid, 'action' => 'approvalrequired'));
    $link = html_writer::link($attendees_url, $attendees_url, array('title' => get_string('attendees', 'facetoface')));
    $msg = str_replace(get_string('placeholder:attendeeslink', 'facetoface'), $link, $msg);

    if (strstr($msg, get_string('placeholder:reminderperiod', 'facetoface'))) {
        // Handle the legacy reminderperiod placeholder.
        $reminderperiod = $DB->get_field('facetoface_notification', 'MAX(scheduleamount)',
            array('facetofaceid' => $data->facetoface, 'conditiontype' => MDL_F2F_CONDITION_BEFORE_SESSION,
            'scheduleunit' => MDL_F2F_SCHEDULE_UNIT_DAY, 'status' => 1), IGNORE_MULTIPLE);
        $reminderperiod = empty($reminderperiod) ? 0 : $reminderperiod;
        $msg = str_replace(get_string('placeholder:reminderperiod', 'facetoface'), $reminderperiod, $msg);
    }

    // Custom session fields (they look like "session:shortname" in the templates)
    $customfields = facetoface_get_session_customfields();
    $customdata = $DB->get_records('facetoface_session_data', array('sessionid' => $sessionid), '', 'fieldid, data');
    foreach ($customfields as $field) {
        $placeholder = "[session:{$field->shortname}]";
        $value = '';
        if (!empty($customdata[$field->id])) {
            if (CUSTOMFIELD_TYPE_MULTISELECT == $field->type) {
                $value = str_replace(CUSTOMFIELD_DELIMITER, ', ', $customdata[$field->id]->data);
            } else {
                $value = $customdata[$field->id]->data;
            }
        }

        $msg = str_replace($placeholder, $value, $msg);
    }

    $msg = facetoface_message_substitutions_userfields($msg, $user);

    return $msg;
}

/**
 * Substitute placeholders for user fields in message templates for the actual data
 *
 * @param   string  $msg            Email message
 * @param   obj     $user           The subject of the message
 * @return  string                  Message with substitutions applied
 */
function facetoface_message_substitutions_userfields($msg, $user) {
    global $DB;
    static $customfields = null;

    $fields = array('username', 'email', 'institution', 'department', 'city', 'idnumber', 'icq', 'skype',
        'yahoo', 'aim', 'msn', 'phone1', 'phone2', 'address', 'url', 'description');

    $usernamefields = get_all_user_name_fields();
    $fields = array_merge($fields, array_values($usernamefields));

    // Process basic user fields.
    foreach ($fields as $field) {
        $msg = str_replace(get_string('placeholder:'.$field, 'mod_facetoface'), $user->$field, $msg);
    }

    $fullname = fullname($user);
    $msg = str_replace(get_string('placeholder:fullname', 'mod_facetoface'), $fullname, $msg);

    $langvalue = output_language_code($user->lang);
    $msg = str_replace(get_string('placeholder:lang', 'mod_facetoface'), $langvalue, $msg);

    $countryvalue = output_country_code($user->country);
    $msg = str_replace(get_string('placeholder:country', 'mod_facetoface'), $countryvalue, $msg);

    $timezone = usertimezone($user->timezone);
    $msg = str_replace(get_string('placeholder:timezone', 'mod_facetoface'), $timezone, $msg);

    // Check to see if we need to load and process custom profile fields.
    if (strpos($msg, '[user:') !== false) {
        // If static fields variable isn't already populated with custom profile fields then grab them.
        if ($customfields === null) {
            $customfields = $DB->get_records('user_info_field');
        }

        $sql = "SELECT f.shortname,d.*
                      FROM {user_info_data} d
                      JOIN {user_info_field} f ON d.fieldid = f.id
                     WHERE d.userid = :userid";

        $customfielddata = $DB->get_records_sql($sql, array('userid' => $user->id));

        // Iterate through custom profile fields.
        foreach ($customfields as $field) {
            if (array_key_exists($field->shortname, $customfielddata)) {
                $value = $customfielddata[$field->shortname]->data;
            } else {
                $value = $field->defaultdata;
            }

            // Use output functions for checkbox/datatime.
            switch ($field->datatype){
                case 'checkbox':
                    $value = output_checkbox($value);
                    break;
                case 'datetime':
                    $value = output_datetime($field, $value);
                    break;
            }

            $msg = str_replace('[user:'.$field->shortname.']', $value, $msg);
        }
    }

    return $msg;
}

/**
 * Write plain text yes or no for checkboxes.
 *
 * @param boolean $value
 * @return string
 */
function output_checkbox($value) {
    if ($value) {
        return get_string('yes');
    } else {
        return get_string('no');
    }
}

/**
 * Get plain text date for timestamps.
 *
 * @param int $value    Timestamp
 * @return string
 */
function output_datetime($field, $value) {
    // Variable param3 indicates wether or not to display time.
    if ($field->param3 && is_numeric($value)) {
        return userdate($value, get_string('strfdateattime', 'langconfig'));
    } else if (is_numeric($value) && $value > 0) {
        return userdate($value, get_string('strfdateshortmonth', 'langconfig'));
    } else {
        return '';
    }
}

/**
 * Get country name for country codes.
 *
 * @param string $code  Country code
 * @return string
 */
function output_country_code($code) {
    global $CFG;
    require_once($CFG->dirroot.'/lib/moodlelib.php');

    $countries = get_string_manager()->get_list_of_countries();

    if (isset($countries[$code])) {
        return $countries[$code];
    }
    return $code;
}

/**
 * Get language name for language codes
 *
 * @param string $code  Language code
 * @return string
 */
function output_language_code($code) {
    global $CFG;
    require_once($CFG->dirroot.'/lib/moodlelib.php');

    $languages = get_string_manager()->get_list_of_languages();

    if (isset($languages[$code])) {
        return $languages[$code];
    }
    return $code;
}


/**
 * Check if item has been selected via the dynamic report interface
 *
 * Data is stored in the session and updated via AJAX
 *
 * @access  public
 * @param   string      $type       'notification' or 'template'
 * @param   integer     $id         Optional facetoface id
 * @param   object      $item       Item
 * @return  bool
 */
function facetoface_is_report_item_selected($type, $id = null, $item) {
    // Check to see if selected
    if (facetoface_get_selected_report_items($type, $id, array($item))) {
        return true;
    } else {
        return false;
    }
}


/**
 * Filtered list of selected report items
 *
 * @access  public
 * @param   string      $type       'notification', 'template' or 'room'
 * @param   integer     $id         Optional facetoface id
 * @param   array       $items      Items
 * @return  array
 */
function facetoface_get_selected_report_items($type, $id = null, $items) {
    // Get session data
    switch ($type) {
        case 'notification':
            if (empty($_SESSION['f2f-notifications'][$id])) {
                return array();
            }

            $sess = $_SESSION['f2f-notifications'][$id];
            break;
        case 'template':
            if (empty($_SESSION['f2f-notification-templates'])) {
                return array();
            }

            $sess = $_SESSION['f2f-notification-templates'];
            break;
        case 'room':
            if (empty($_SESSION['f2f-rooms'])) {
                return array();
            }

            $sess = $_SESSION['f2f-rooms'];
            break;
        default:
            break;
    }

    // Loop through items
    foreach ($items as $index => $item) {
        // Check if there is a specific rule for this item
        if (!empty($sess['individual'])) {
            if (isset($sess['individual'][$item->id])) {
                $data = $sess['individual'][$item->id];
                if ($data['value'] == 'true') {
                    continue;
                } else {
                    unset($items[$index]);
                    continue;
                }
            }
        }

        // Check grouping rules
        if (!empty($sess['all'])) {
            continue;
        }

        // Check if there is a status specific group
        $status = !empty($item->status) ? 'active' : 'inactive';
        if (!empty($sess[$status])) {
            continue;
        }

        // If no checks
        unset($items[$index]);
    }

    return $items;
}


/**
 * Reset list of selected report items
 *
 * @access  public
 * @param   string      $type       'notification', 'template' or 'room'
 * @param   integer     $id         Optional facetoface id
 * @return  array
 */
function facetoface_reset_selected_report_items($type, $id = null) {
    switch ($type) {
        case 'notification':
            if (!empty($_SESSION['f2f-notifications'][$id])) {
                $_SESSION['f2f-notifications'][$id] = array();
            }
            break;
        case 'template':
            if (!empty($_SESSION['f2f-notification-templates'])) {
                $_SESSION['f2f-notification-templates'] = array();
            }
            break;
        case 'room':
            if (!empty($_SESSION['f2f-rooms'])) {
                $_SESSION['f2f-rooms'] = array();
            }
            break;
        default:
            break;
    }
}


/**
 * Check if a notification is frozen (uneditable) or not
 *
 * @access  public
 * @param   integer     $id         Notification ID
 * @return  boolean
 */
function facetoface_is_notification_frozen($id) {
    $notification = new facetoface_notification(array('id' => $id), true);
    return $notification->is_frozen();
}
