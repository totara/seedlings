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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package totara
 * @subpackage cohort/rules/sqlhandlers
 */
/**
 * This file contains sqlhandlers for rules involving cohort members
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

/**
 * A rule for determining whether or not a user belongs to a specified cohort
 */
class cohort_rule_sqlhandler_cohortmember extends cohort_rule_sqlhandler {
    public $params = array(
        'cohortids' => 1,
        'incohort' => 0,
    );

    public function get_sql_snippet() {
        global $DB;
        $params = array();
        $sqlhandler = new stdClass();
        $not = $this->incohort ? '' : 'NOT';
        list($sqlin, $params) = $DB->get_in_or_equal($this->cohortids, SQL_PARAMS_NAMED,
            'ccm' . $this->ruleid);
        $sqlhandler->sql = "{$not} EXISTS (
            SELECT 1 FROM {cohort_members} cm
            WHERE cm.userid = u.id
            AND cm.cohortid {$sqlin}";
        $sqlhandler->sql .= ')';
        $sqlhandler->params = $params;

        return $sqlhandler;
    }
}
