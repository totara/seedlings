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
 * @author Aaron Barnes <aaronb@catalyst.net.nz>
 * @package totara
 * @subpackage plan
 */

/**
 * Devplan linked competencies specific competency dialog generator
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content.class.php');

class totara_dialog_linked_competencies_content_competencies extends totara_dialog_content {

    /**
     * PHP file to use for search tab content
     *
     * @access  public
     * @var     string
     */
    public $search_code = '';

    /**
     * Load competencies to display
     *
     * @access  public
     * @var     integer planid  id of development plan for which linked competencies should be loaded
     */
    public function load_competencies($planid) {
        global $DB;

        $planid = (int) $planid;

        $sql = "
            SELECT
                dppca.id AS id,
                c.fullname AS fullname
            FROM
                {dp_plan_competency_assign} dppca
            INNER JOIN
                {comp} c
             ON c.id = dppca.competencyid
            WHERE
                dppca.planid = ?
            ORDER BY
                c.fullname
        ";
        $params = array($planid);

        $this->items = $DB->get_records_sql($sql, $params);

    }
}
