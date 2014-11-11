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
 * @author Nathan Lewis <nathan.lewis@totaralms.com>
 * @package totara
 * @subpackage totara_core/dialogs
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content_required_learning.class.php');

class totara_dialog_content_required_learning extends totara_dialog_content {

    public $userid;

    /**
     * Searchtype this means that search case will be given by instance
     * @var string
     */
    public $searchtype = 'this';

    /**
     * Additional parameters to add into search form
     * @var array
     */
    public $searchparams = array();

    /**
     * Constructor
     *
     * @param int $userid
     */
    public function __construct($userid = 0) {
        // Make some capability checks.
        require_login();

        $this->userid = $userid;

        $this->type = self::TYPE_CHOICE_MULTI;

        // Set lang file.
        $this->lang_file = 'totara_hierarchy';
        $this->string_nothingtodisplay = "error:dialognotreeitems";
    }

    /**
     * Load items to display
     */
    public function load_items() {
        global $DB;

        $select = 'SELECT pc.id, prog.fullname ';
        list($insql, $params) = $DB->get_in_or_equal(array(PROGRAM_EXCEPTION_RAISED, PROGRAM_EXCEPTION_DISMISSED),
                SQL_PARAMS_QM, 'param', false);
        $from = "FROM {prog} prog
                 JOIN {prog_completion} pc ON prog.id = pc.programid AND pc.coursesetid = 0
                 JOIN (SELECT DISTINCT userid, programid
                         FROM {prog_user_assignment}
                        WHERE exceptionstatus {$insql}) pua
                   ON (pc.programid = pua.programid AND pc.userid = pua.userid)";
        $where = " WHERE pc.userid = ?";
        $params[] = $this->userid;
        $this->items = $DB->get_records_sql($select . $from . $where, $params);
    }

    /**
     * Prepare info for search.php file
     *
     * @param stdClass $search_info
     * @param array $formdata
     * @param array $keywords
     */
    public function put_search_info(stdClass $search_info, array &$formdata, array $keywords) {
        if (!isset($formdata['hidden']) || !is_array($formdata['hidden'])) {
            $formdata['hidden'] = array();
        }
        foreach ($this->searchparams as $key => $value) {
            $formdata['hidden'][$key] = $value;
        }

        $this->get_search_info($search_info, $keywords);
    }

    /**
     * Search information for search dialog box
     *
     * @param stdClass $search_info
     * @param array $keywords
     */
    public function get_search_info(stdClass $search_info, array $keywords) {
        global $DB;

        list($insql, $params) = $DB->get_in_or_equal(array(PROGRAM_EXCEPTION_RAISED, PROGRAM_EXCEPTION_DISMISSED),
                SQL_PARAMS_QM, 'param', false);

        $sql = "FROM {prog} prog
                JOIN {prog_completion} pc ON prog.id = pc.programid AND pc.coursesetid = 0
                JOIN (SELECT DISTINCT userid, programid
                        FROM {prog_user_assignment}
                       WHERE exceptionstatus {$insql}) pua
                  ON (pc.programid = pua.programid AND pc.userid = pua.userid)";
        $where = " WHERE pc.userid = ?";
        $params[] = $this->userid;

        if ($keywords) {
            list($searchsql, $searchparams) = totara_search_get_keyword_where_clause($keywords, array('prog.fullname'));
            $params = array_merge($params, $searchparams);
            $where .= ' AND '.$searchsql;
        }

        $search_info->id = 'pc.id';
        $search_info->fullname = 'prog.fullname';
        $search_info->sql = $sql . $where;
        $search_info->order = 'ORDER BY prog.fullname';
        $search_info->params = $params;
    }

}
