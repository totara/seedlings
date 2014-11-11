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
 * Course completion status for a particular user/course
 *
 * @package core_completion
 * @category completion
 * @copyright 2009 Catalyst IT Ltd
 * @author Aaron Barnes <aaronb@catalyst.net.nz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Course completion status constants
 *
 * For translating database recorded integers to strings and back
 */
define('COMPLETION_STATUS_NOTYETSTARTED',   10);
define('COMPLETION_STATUS_INPROGRESS',      25);
define('COMPLETION_STATUS_COMPLETE',        50);
define('COMPLETION_STATUS_COMPLETEVIARPL',  75);

global $COMPLETION_STATUS;
$COMPLETION_STATUS = array(
    COMPLETION_STATUS_NOTYETSTARTED => 'notyetstarted',
    COMPLETION_STATUS_INPROGRESS => 'inprogress',
    COMPLETION_STATUS_COMPLETE => 'complete',
    COMPLETION_STATUS_COMPLETEVIARPL => 'completeviarpl',
);


defined('MOODLE_INTERNAL') || die();
require_once("{$CFG->dirroot}/completion/data_object.php");
require_once("{$CFG->libdir}/completionlib.php");
require_once("{$CFG->dirroot}/blocks/totara_stats/locallib.php");
require_once("{$CFG->dirroot}/totara/plan/lib.php");

/**
 * Course completion status for a particular user/course
 *
 * @package core_completion
 * @category completion
 * @copyright 2009 Catalyst IT Ltd
 * @author Aaron Barnes <aaronb@catalyst.net.nz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_completion extends data_object {

    /* @var string $table Database table name that stores completion information */
    public $table = 'course_completions';

    /* @var array $required_fields Array of required table fields, must start with 'id'. */
    public $required_fields = array('id', 'userid', 'course', 'organisationid', 'positionid',
        'timeenrolled', 'timestarted', 'timecompleted', 'reaggregate', 'status', 'rpl', 'rplgrade', 'renewalstatus', 'invalidatecache');

    /* @var array $optional_fields Array of optional table fields */
    public $optional_fields = array('name' => '');


    /* @var int $userid User ID */
    public $userid;

    /* @var int $course Course ID */
    public $course;

    /* @var int $organisationid Origanisation ID user had when completed */
    public $organisationid;

    /* @var int $positionid Position ID user had when completed */
    public $positionid;


    /* @var int Time of course enrolment {@link completion_completion::mark_enrolled()} */
    public $timeenrolled;

    /* @var int Time the user started their course completion {@link completion_completion::mark_inprogress()} */
    public $timestarted;

    /* @var int Timestamp of course completion {@link completion_completion::mark_complete()} */
    public $timecompleted;

    /* @var int Flag to trigger cron aggregation (timestamp) */
    public $reaggregate;

    /* @var str Course name (optional) */
    public $name;

    /* @var int Completion status constant */
    public $status;

    /* @var string Record of prior learning, leave blank if none */
    public $rpl;

    /* @var string Grade for record of prior learning, leave blank if none */
    public $rplgrade;

    /**
     * Finds and returns a data_object instance based on params.
     *
     * @param array $params associative arrays varname = >value
     * @return data_object instance of data_object or false if none found.
     */
    public static function fetch($params) {
        return self::fetch_helper('course_completions', __CLASS__, $params);
    }


    /**
     * Return user's status
     *
     * Uses the following properties to calculate:
     *  - $timeenrolled
     *  - $timestarted
     *  - $timecompleted
     *  - $rpl
     *
     * @static static
     *
     * @param   object  $completion  Object with at least the described columns
     * @return  str     Completion status lang string key
     */
    public static function get_status($completion) {
        // Check if a completion record was supplied
        if (!is_object($completion)) {
            throw new coding_exception('Incorrect data supplied to calculate Completion status');
        }

        // Check we have the required data, if not the user is probably not
        // participating in the course
        if (empty($completion->timeenrolled) &&
            empty($completion->timestarted) &&
            empty($completion->timecompleted))
        {
            return '';
        }

        // Check if complete
        if ($completion->timecompleted) {

            // Check for RPL
            if (isset($completion->rpl) && strlen($completion->rpl)) {
                return 'completeviarpl';
            }
            else {
                return 'complete';
            }
        }

        // Check if in progress
        elseif ($completion->timestarted) {
            return 'inprogress';
        }

        // Otherwise not yet started
        elseif ($completion->timeenrolled) {
            return 'notyetstarted';
        }

        // Otherwise they are not participating in this course
        else {
            return '';
        }
    }


    /**
     * Return status of this completion
     *
     * @return bool
     */
    public function is_complete() {
        return (bool) $this->timecompleted;
    }

    /**
     * Mark this user as started (or enrolled) in this course
     *
     * If the user is already marked as started, no change will occur
     *
     * @param integer $timeenrolled Time enrolled (optional)
     */
    public function mark_enrolled($timeenrolled = null) {
        global $DB;

        if ($this->timeenrolled === null) {

            if ($timeenrolled === null) {
                $timeenrolled = time();
            }

            $this->timeenrolled = $timeenrolled;
        }

        if (!$this->aggregate()) {
            return false;
        }

        $data = array();
        $data['userid'] = $this->userid;
        $data['eventtype'] = STATS_EVENT_COURSE_STARTED;
        $data['data2'] = $this->course;
        if (!$DB->record_exists('block_totara_stats', $data)) {
            totara_stats_add_event(time(), $this->userid, STATS_EVENT_COURSE_STARTED, '', $this->course);
        }
    }

    /**
     * Mark this user as inprogress in this course
     *
     * If the user is already marked as inprogress, the time will not be changed
     *
     * @param integer $timestarted Time started (optional)
     */
    public function mark_inprogress($timestarted = null) {
        global $DB;

        $timenow = time();

        if (!$this->timestarted) {

            if (!$timestarted) {
                $timestarted = $timenow;
            }

            $this->timestarted = $timestarted;
        }

        $wasenrolled = $this->timeenrolled;

        if (!$this->aggregate()) {
            return false;
        }

        if (!$wasenrolled) {
            $data = array();
            $data['userid'] = $this->userid;
            $data['eventtype'] = STATS_EVENT_COURSE_STARTED;
            $data['data2'] = $this->course;
            if (!$DB->record_exists('block_totara_stats', $data)) {
                totara_stats_add_event($timenow, $this->userid, STATS_EVENT_COURSE_STARTED, '', $this->course);
            }
        }

        // Mark as in progress for the certification
        inprogress_certification_stage($this->course, $this->userid);
    }

    /**
     * Mark this user complete in this course
     *
     * This generally happens when the required completion criteria
     * in the course are complete.
     *
     * @param integer $timecomplete Time completed (optional)
     * @return void
     */
    public function mark_complete($timecomplete = null) {
        global $USER, $CFG, $DB;

        // Never change a completion time.
        if ($this->timecompleted) {
            return;
        }

        // Use current time if nothing supplied.
        if (!$timecomplete) {
            $timecomplete = time();
        }

        // Set time complete.
        $this->timecompleted = $timecomplete;

        // Get user's positionid and organisationid if not already set
        if ($this->positionid === null) {
            require_once("{$CFG->dirroot}/totara/hierarchy/prefix/position/lib.php");
            $ids = pos_get_current_position_data($this->userid);

            $this->positionid = $ids['positionid'];
            $this->organisationid = $ids['organisationid'];
        }

        // Save record.
        if ($result = $this->_save()) {
            $data = $this->get_record_data();
            \core\event\course_completed::create_from_completion($data)->trigger();

            $data = array();
            $data['userid'] = $this->userid;
            $data['eventtype'] = STATS_EVENT_COURSE_COMPLETE;
            $data['data2'] = $this->course;
            if (!$DB->record_exists('block_totara_stats', $data)) {
                totara_stats_add_event(time(), $this->userid, STATS_EVENT_COURSE_COMPLETE, '', $this->course);
            }

            //Auto plan completion hook
            dp_plan_item_updated($this->userid, 'course', $this->course);

            // Program completion hook.
            prog_update_completion($this->userid);
        }

        return $result;
    }

    /**
     * Save course completion status
     *
     * This method creates a course_completions record if none exists
     * and also calculates the timeenrolled date if the record is being
     * created
     *
     * @access  private
     * @return  bool
     */
    private function _save() {
        // Make sure timeenrolled is not null
        if (!$this->timeenrolled) {
            $this->timeenrolled = 0;
        }

        // Update status column
        $status = completion_completion::get_status($this);
        if ($status) {
            $status = constant('COMPLETION_STATUS_'.strtoupper($status));
        }

        $this->status = $status;

        // Save record
        if ($this->id) {
            // Update
            return $this->update();
        } else {
            // Create new
            if (!$this->timeenrolled) {
                global $DB;

                // Get earliest current enrolment start date
                // This means timeend > now() and timestart < now()
                $sql = "
                    SELECT
                        ue.timestart
                    FROM
                        {user_enrolments} ue
                    JOIN
                        {enrol} e
                    ON (e.id = ue.enrolid AND e.courseid = :courseid)
                    WHERE
                        ue.userid = :userid
                    AND ue.status = :active
                    AND e.status = :enabled
                    AND (
                        ue.timeend = 0
                     OR ue.timeend > :now
                    )
                    AND ue.timestart < :now2
                    ORDER BY
                        ue.timestart ASC
                ";
                $params = array(
                    'enabled'   => ENROL_INSTANCE_ENABLED,
                    'active'    => ENROL_USER_ACTIVE,
                    'userid'    => $this->userid,
                    'courseid'  => $this->course,
                    'now'       => time(),
                    'now2'      => time()
                );

                if ($enrolments = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE)) {
                    $this->timeenrolled = $enrolments->timestart;
                }

                // If no timeenrolled could be found, use current time
                if (!$this->timeenrolled) {
                    $this->timeenrolled = time();
                }
            }

            // We should always be reaggregating when new course_completions
            // records are created as they might have already completed some
            // criteria before enrolling
            if (!$this->reaggregate) {
                $this->reaggregate = time();
            }

            // Make sure timestarted is not null
            if (!$this->timestarted) {
                $this->timestarted = 0;
            }

            return $this->insert();
        }
    }
    /**
     * Aggregate completion
     *
     * @return bool
     */
    public function aggregate() {
        global $DB;
        static $courses = array();

        // Check if already complete.
        if ($this->timecompleted) {
            return $this->_save();
        }

        // Cached course completion enabled and aggregation method.
        if (!isset($courses[$this->course])) {
            $c = new stdClass();
            $c->id = $this->course;
            $info = new completion_info($c);
            $courses[$this->course] = new stdClass();
            $courses[$this->course]->enabled = $info->is_enabled();
            $courses[$this->course]->agg = $info->get_aggregation_method();
        }

        // No need to do this if completion is disabled.
        if (!$courses[$this->course]->enabled) {
            return false;
        }

        // Get user's completions.
        $sql = "
            SELECT
                cr.id AS criteriaid,
                cr.criteriatype,
                co.timecompleted,
                a.method AS agg_method
            FROM
                {course_completion_criteria} cr
            LEFT JOIN
                {course_completion_crit_compl} co
             ON co.criteriaid = cr.id
            AND co.userid = :userid
            LEFT JOIN
                {course_completion_aggr_methd} a
             ON a.criteriatype = cr.criteriatype
            AND a.course = cr.course
            WHERE
                cr.course = :course
        ";

        $params = array(
            'userid' => $this->userid,
            'course' => $this->course
        );

        $completions = $DB->get_records_sql($sql, $params);

        // If no criteria, no need to aggregate.
        if (empty($completions)) {
            return $this->_save();
        }

        // Get aggregation methods.
        $agg_overall = $courses[$this->course]->agg;

        $overall_status = null;
        $activity_status = null;
        $prerequisite_status = null;
        $role_status = null;

        // Get latest timecompleted.
        $timecompleted = null;

        // Check each of the criteria.
        foreach ($completions as $completion) {
            $timecompleted = max($timecompleted, $completion->timecompleted);
            $iscomplete = (bool) $completion->timecompleted;

            // Handle aggregation special cases.
            switch ($completion->criteriatype) {
                case COMPLETION_CRITERIA_TYPE_ACTIVITY:
                    completion_status_aggregate($completion->agg_method, $iscomplete, $activity_status);
                    break;

                case COMPLETION_CRITERIA_TYPE_COURSE:
                    completion_status_aggregate($completion->agg_method, $iscomplete, $prerequisite_status);
                    break;

                case COMPLETION_CRITERIA_TYPE_ROLE:
                    completion_status_aggregate($completion->agg_method, $iscomplete, $role_status);
                    break;

                default:
                    completion_status_aggregate($agg_overall, $iscomplete, $overall_status);
            }
        }

        // Include role criteria aggregation in overall aggregation.
        if ($role_status !== null) {
            completion_status_aggregate($agg_overall, $role_status, $overall_status);
        }

        // Include activity criteria aggregation in overall aggregation.
        if ($activity_status !== null) {
            completion_status_aggregate($agg_overall, $activity_status, $overall_status);
        }

        // Include prerequisite criteria aggregation in overall aggregation.
        if ($prerequisite_status !== null) {
            completion_status_aggregate($agg_overall, $prerequisite_status, $overall_status);
        }

        // If overall aggregation status is true, mark course complete for user.
        if ($overall_status) {
            return $this->mark_complete($timecompleted);
        } else {
            return $this->_save();
        }
    }
}


/**
 * Aggregate criteria status's as per configured aggregation method
 *
 * @param int $method COMPLETION_AGGREGATION_* constant
 * @param bool $data Criteria completion status
 * @param bool|null $state Aggregation state
 */
function completion_status_aggregate($method, $data, &$state) {
    if ($method == COMPLETION_AGGREGATION_ALL) {
        if ($data && $state !== false) {
            $state = true;
        } else {
            $state = false;
        }
    } else if ($method == COMPLETION_AGGREGATION_ANY) {
        if ($data) {
            $state = true;
        } else if (!$data && $state === null) {
            $state = false;
        }
    }
}

/**
 * Triggered by changing course completion criteria, this function
 * bulk marks users as started in the course completion system.
 *
 * @param   integer     $courseid       Course ID
 * @return  bool
 */
function completion_start_user_bulk($courseid) {
    global $DB;

    /*
     * A quick explaination of this horrible looking query
     *
     * It's purpose is to locate all the active participants
     * of a course with course completion enabled, but without
     * a course_completions record.
     *
     * We want to record the user's enrolment start time for the
     * course. This gets tricky because there can be multiple
     * enrolment plugins active in a course, hence the fun
     * case statement.
     */
    $sql = "
        INSERT INTO
            {course_completions}
            (course, userid, timeenrolled, timestarted, reaggregate, status)
        SELECT
            c.id AS course,
            ue.userid AS userid,
            CASE
                WHEN MIN(ue.timestart) <> 0
                THEN MIN(ue.timestart)
                ELSE ?
            END,
            0,
            ?,
            ?
        FROM
            {user_enrolments} ue
        INNER JOIN
            {enrol} e
         ON e.id = ue.enrolid
        INNER JOIN
            {course} c
         ON c.id = e.courseid
        LEFT JOIN
            {course_completions} crc
         ON crc.course = c.id
        AND crc.userid = ue.userid
        WHERE
            c.enablecompletion = 1
        AND c.completionstartonenrol = 1
        AND crc.id IS NULL
        AND c.id = ?
        AND ue.status = ?
        AND e.status = ?
        AND (ue.timeend > ? OR ue.timeend = 0)
        GROUP BY
            c.id,
            ue.userid
    ";

    $now = time();
    $params = array(
        $now,
        $now,
        COMPLETION_STATUS_NOTYETSTARTED,
        $courseid,
        ENROL_USER_ACTIVE,
        ENROL_INSTANCE_ENABLED,
        $now
    );
    $DB->execute($sql, $params, true);
}
