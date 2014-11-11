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
 * @author Aaron Wells <aaronw@catalyst.net.nz>
 * @package totara
 * @subpackage cohort/rules/sqlhandlers
 */
/**
 * This file contains sqlhandlers for rules based on course completion and program completion
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

define('COHORT_RULE_COMPLETION_OP_NONE', 0);
define('COHORT_RULE_COMPLETION_OP_ANY', 10);
define('COHORT_RULE_COMPLETION_OP_NOTALL', 30);
define('COHORT_RULE_COMPLETION_OP_ALL', 40);

define('COHORT_RULE_COMPLETION_OP_DATE_LESSTHAN', 50);
define('COHORT_RULE_COMPLETION_OP_DATE_GREATERTHAN', 60);
define('COHORT_RULE_COMPLETION_OP_BEFORE_PAST_DURATION', 70);
define('COHORT_RULE_COMPLETION_OP_WITHIN_PAST_DURATION', 80);
define('COHORT_RULE_COMPLETION_OP_WITHIN_FUTURE_DURATION', 90);
define('COHORT_RULE_COMPLETION_OP_AFTER_FUTURE_DURATION', 100);

define ('COHORT_PICKER_PROGRAM_COMPLETION', 0);
define ('COHORT_PICKER_COURSE_COMPLETION', 1);

global $COHORT_RULE_COMPLETION_OP;
$COHORT_RULE_COMPLETION_OP = array(
    COHORT_RULE_COMPLETION_OP_DATE_LESSTHAN => 'before',
    COHORT_RULE_COMPLETION_OP_DATE_GREATERTHAN => 'after',
    COHORT_RULE_COMPLETION_OP_BEFORE_PAST_DURATION => 'beforepastduration',
    COHORT_RULE_COMPLETION_OP_WITHIN_PAST_DURATION => 'inpastduration',
    COHORT_RULE_COMPLETION_OP_WITHIN_FUTURE_DURATION => 'inpastduration',
    COHORT_RULE_COMPLETION_OP_AFTER_FUTURE_DURATION => 'beforepastduration',
);

require_once($CFG->dirroot . '/totara/program/program.class.php');
/**
 * A rule for checking whether a user's completed any/all/some/none of the courses/progs
 * in a list
 */
abstract class cohort_rule_sqlhandler_completion_list extends cohort_rule_sqlhandler {
    public $params = array(
        'operator' => 0,
        'listofids' => 1
    );

    public function get_sql_snippet() {

        if (count($this->listofids) == 0){
            // todo: error message?
            return '1=0';
        }

        switch ($this->operator) {
            case COHORT_RULE_COMPLETION_OP_NONE:
                $goalnum = 0;
                $operator = '=';
                break;
            case COHORT_RULE_COMPLETION_OP_ANY:
                $goalnum = 0;
                $operator = '<';
                break;
            case COHORT_RULE_COMPLETION_OP_NOTALL:
                $goalnum = count($this->listofids);
                $operator = '<';
                break;
            case COHORT_RULE_COMPLETION_OP_ALL:
                $goalnum = count($this->listofids);
                $operator = '=';
                break;
            default:
                //todo: error message here?
                return false;
        }

        return $this->construct_sql_snippet($goalnum, $operator, $this->listofids);
    }

    protected abstract function construct_sql_snippet($goalnum, $operator, $lov);
}

/**
 * Rule for completing all/any/some/none of the courses in a list
 */
class cohort_rule_sqlhandler_completion_list_course extends cohort_rule_sqlhandler_completion_list {
    protected function construct_sql_snippet($goalnum, $operator, $lov) {
        global $DB;
        $sqlhandler = new stdClass();
        list($sqlin, $params) = $DB->get_in_or_equal($lov, SQL_PARAMS_NAMED, 'clc'.$this->ruleid);
        if ($goalnum != 0) {
            $sqlhandler->sql = "
                  (
                  SELECT count(*)
                    FROM {course_completions} cc
                   WHERE cc.userid = u.id
                     AND cc.course {$sqlin}
                     AND cc.timecompleted > 0
                  GROUP BY userid
                  ) {$operator} {$goalnum}";

        } else {
            $sqlhandler->sql = "{$goalnum} {$operator}
                  (
                  SELECT count(*)
                    FROM {course_completions} cc
                   WHERE cc.userid = u.id
                     AND cc.course {$sqlin}
                     AND cc.timecompleted > 0
                  )";
        }
        $sqlhandler->params = $params;
        return $sqlhandler;
    }
}

/**
 * Rule for completing all/any/some/none of the programs in a list
 */
class cohort_rule_sqlhandler_completion_list_program extends cohort_rule_sqlhandler_completion_list {
    protected function construct_sql_snippet($goalnum, $operator, $lov) {
        global $DB;
        $sqlhandler = new stdClass();
        list($sqlin, $params) = $DB->get_in_or_equal($lov, SQL_PARAMS_NAMED, 'clp'.$this->ruleid);
        if ($goalnum != 0) {
            $sqlhandler->sql = "
                  (
                  SELECT count(*)
                  FROM {prog_completion} pc
                  WHERE pc.userid = u.id
                    AND pc.programid {$sqlin}
                    AND pc.coursesetid = 0
                    AND pc.status = " . STATUS_PROGRAM_COMPLETE . "
                    AND pc.timecompleted > 0
                  GROUP BY userid
                  ) {$operator} {$goalnum}";
        } else {
            $sqlhandler->sql = "{$goalnum} {$operator}
                  (
                  SELECT count(*)
                  FROM {prog_completion} pc
                  WHERE pc.userid = u.id
                    AND pc.programid {$sqlin}
                    AND pc.coursesetid = 0
                    AND pc.status = " . STATUS_PROGRAM_COMPLETE . "
                    AND pc.timecompleted > 0
                  )";
        }
        $sqlhandler->params = $params;
        return $sqlhandler;
    }
}


/**
 * Abstract rule for handling date-based completion
 */
abstract class cohort_rule_sqlhandler_completion_date extends cohort_rule_sqlhandler {
    public $params = array(
        'operator' => 0,
        'date' => 0,
        'listofids' => 1
    );

    public function get_sql_snippet() {

        if (count($this->listofids) == 0){
            // todo: error message?
            return '1=0';
        }

        $time = time();
        switch ($this->operator) {
            case COHORT_RULE_COMPLETION_OP_DATE_LESSTHAN:
                $comparison = "<= {$this->date}";
                break;
            case COHORT_RULE_COMPLETION_OP_DATE_GREATERTHAN:
                $comparison = ">= {$this->date}";
                break;
            case COHORT_RULE_COMPLETION_OP_BEFORE_PAST_DURATION:
                $comparison = '<= ' . ($time - ($this->date * 24 * 60 * 60));
                break;
            case COHORT_RULE_COMPLETION_OP_WITHIN_PAST_DURATION:
                $comparison = 'BETWEEN ' . ($time - ($this->date * 24 * 60 * 60)) . ' AND ' . $time;
                break;
            case COHORT_RULE_COMPLETION_OP_WITHIN_FUTURE_DURATION:
                $comparison = 'BETWEEN ' . $time . ' AND ' . ($time + ($this->date * 24 * 60 * 60));
                break;
            case COHORT_RULE_COMPLETION_OP_AFTER_FUTURE_DURATION:
                $comparison = '>= ' . ($time + ($this->date * 24 * 60 * 60));
                break;
        }

        $goalnum = count($this->listofids);

        return $this->construct_sql_snippet($goalnum, $comparison, $this->listofids);
    }

    protected abstract function construct_sql_snippet($goalnum, $comparison, $lov);
}

/**
 * Rule for checking whether users has completed all the courses in a list before a fixed date
 */
class cohort_rule_sqlhandler_completion_date_course extends cohort_rule_sqlhandler_completion_date {
    protected function construct_sql_snippet($goalnum, $comparison, $lov) {
        global $DB;
        $sqlhandler = new stdClass();
        list($sqlin, $params) = $DB->get_in_or_equal($lov, SQL_PARAMS_NAMED, 'cdc'.$this->ruleid);
        $sqlhandler->sql = "{$goalnum} =
                  (
                  SELECT count(*)
                    FROM {course_completions} cc
                   WHERE cc.userid = u.id
                     AND cc.course {$sqlin}
                     AND cc.timecompleted > 0
                     AND cc.timecompleted {$comparison}
                  )";
        $sqlhandler->params = $params;
        return $sqlhandler;
    }
}

/**
 * Rule for checking whether user has completed all the programs in a list before a fixed date
 */
class cohort_rule_sqlhandler_completion_date_program extends cohort_rule_sqlhandler_completion_date {
    protected function construct_sql_snippet($goalnum, $comparison, $lov) {
        global $DB;
        $sqlhandler = new stdClass();
        list($sqlin, $params) = $DB->get_in_or_equal($lov, SQL_PARAMS_NAMED, 'cdp'.$this->ruleid);
        $sqlhandler->sql = "{$goalnum} =
                  (
                  SELECT count(*)
                    FROM {prog_completion} pc
                   WHERE pc.userid = u.id
                     AND pc.programid {$sqlin}
                     AND pc.coursesetid = 0
                     AND pc.status = " . STATUS_PROGRAM_COMPLETE . "
                     AND pc.timecompleted > 0
                     AND pc.timecompleted {$comparison}
                  )";
        $sqlhandler->params = $params;
        return $sqlhandler;
    }
}


/**
 * Rule for checking whether user took longer than a specified duration to complete all
 * the courses in a list
 */
class cohort_rule_sqlhandler_completion_duration_course extends cohort_rule_sqlhandler_completion_date {
    protected function construct_sql_snippet($goalnum, $comparison, $lov){
        global $DB;
        $sqlhandler = new stdClass();
        list($sqlin1, $params) = $DB->get_in_or_equal($lov, SQL_PARAMS_NAMED, 'cdc1'.$this->ruleid);
        list($sqlin2, $params2) = $DB->get_in_or_equal($lov, SQL_PARAMS_NAMED, 'cdc2'.$this->ruleid);
        $params = array_merge($params, $params2);
        $sqlhandler->sql = "( {$goalnum} =
                  (
                  SELECT count(*)
                    FROM {course_completions} cc
                   WHERE cc.userid = u.id
                     AND cc.course {$sqlin1}
                     AND cc.timecompleted > 0
                  ) AND (
                     SELECT ((MAX(cc.timecompleted) - MIN(cc.timestarted)) / ". DAYSECS .")
                       FROM {course_completions} cc
                      WHERE cc.userid = u.id
                        AND cc.course {$sqlin2}
                        AND cc.timecompleted > 0
                  ) {$comparison})";
        $sqlhandler->params = $params;
        return $sqlhandler;
    }
}

/**
 * Rule for checking whether user took longer than a specified duration to complete all
 * the programs in a list
 */
class cohort_rule_sqlhandler_completion_duration_program extends cohort_rule_sqlhandler_completion_date {
    protected function construct_sql_snippet($goalnum, $comparison, $lov){
        global $DB;
        $sqlhandler = new stdClass();
        list($sqlin1, $params) = $DB->get_in_or_equal($lov, SQL_PARAMS_NAMED, 'cdp1'.$this->ruleid);
        list($sqlin2, $params2) = $DB->get_in_or_equal($lov, SQL_PARAMS_NAMED, 'cdp2'.$this->ruleid);
        $params = array_merge($params, $params2);
        $sqlhandler->sql = "( {$goalnum} =
                  (
                  SELECT count(*)
                    FROM {prog_completion} pc
                   WHERE pc.userid = u.id
                     AND pc.programid {$sqlin1}
                     AND pc.coursesetid = 0
                     AND pc.status = " . STATUS_PROGRAM_COMPLETE . "
                     AND pc.timecompleted > 0
                  ) AND (
                     SELECT ((MAX(pc.timecompleted) - MIN(pc.timestarted)) / ". DAYSECS .")
                       FROM {prog_completion} pc
                      WHERE pc.userid = u.id
                        AND pc.programid {$sqlin2}
                        AND pc.coursesetid = 0
                        AND pc.status = " . STATUS_PROGRAM_COMPLETE . "
                        AND pc.timecompleted > 0
                  ) {$comparison})";
        $sqlhandler->params = $params;
        return $sqlhandler;
    }
}
