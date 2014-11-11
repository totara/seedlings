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
 * This file contains sqlhandlers for rules involving managers
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

/**
 * A rule which indicates whether or not a user has anyone who reports directly to them.
 */
class cohort_rule_sqlhandler_hasreports extends cohort_rule_sqlhandler {

    // We actually only need one scalar param for this ruletype; but using these ones allows us to re-use the "Checkbox" ui type
    public $params = array(
        'equal' => 0,
        'listofvalues' => 1
    );

    public function get_sql_snippet() {
        $sqlhandler = new stdClass();
        $hasreports = array_pop($this->listofvalues);
        $sqlhandler->sql = ($hasreports ? '' : 'NOT ') . "exists ("
                ."select 1 from {pos_assignment} pa "
                ."where pa.managerid = u.id  "
                ."and pa.type=" . POSITION_TYPE_PRIMARY
            .") ";
        $sqlhandler->params = array();
        return $sqlhandler;
    }
}

/**
 * A rule for determining whether or not a user reports to another user.
 */
class cohort_rule_sqlhandler_reportsto extends cohort_rule_sqlhandler {
    public $params = array(
        'isdirectreport' => 0,
        'managerid' => 1
    );

    public function get_sql_snippet() {
        global $DB;
        $params = array();
        $sqlhandler = new stdClass();
        $sqlhandler->sql = "exists ("
                ."select 1 from {pos_assignment} pa "
                ."where pa.userid=u.id "
                ."and pa.type=" . POSITION_TYPE_PRIMARY . ' ';
        // Both branches of the if statement below need the results of get_in_or_equal.
        list($sqlin, $params) = $DB->get_in_or_equal($this->managerid, SQL_PARAMS_NAMED, 'rt' . $this->ruleid);
        if ($this->isdirectreport) {
            $sqlhandler->sql .= 'and pa.managerid '.$sqlin;
        } else {
            $sqlhandler->sql .= "and (";
            $needor = 0;
            $index = 1;
            // We need to get the actual managerpath for each manager for this to work properly.
            $mgrpaths = $DB->get_records_sql_menu("SELECT userid, managerpath FROM {pos_assignment} WHERE userid {$sqlin} AND type=" . POSITION_TYPE_PRIMARY, $params);
            foreach ($this->managerid as $mid) {
                if (!empty($needor)) { //don't add on first iteration.
                    $sqlhandler->sql .= ' OR ';
                }
                $mgrpath = (!empty($mgrpaths[$mid])) ? $mgrpaths[$mid] : "/{$mid}";
                $sqlhandler->sql .= $DB->sql_like('pa.managerpath', ':rtm'.$this->ruleid.$index);
                $params['rtm'.$this->ruleid.$index] = $mgrpath . '/%';
                $needor = true;
                $index++;
            }
            $sqlhandler->sql .= ")";
        }
        $sqlhandler->sql .= ')';
        $sqlhandler->params = $params;
        return $sqlhandler;
    }
}
