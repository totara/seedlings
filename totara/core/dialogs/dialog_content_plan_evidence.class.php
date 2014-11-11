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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage totara_core/dialogs
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content_plan.class.php');
require_once($CFG->dirroot.'/totara/plan/development_plan.class.php');

class totara_dialog_content_plan_evidence extends totara_dialog_content_plan {

    /**
     * Constructor
     *
     * @param string $component (not used for evidence)
     * @param int $planid
     * @param bool $showhidden (not used for evidence)
     * @param int $userid
     */
    public function __construct($component, $planid = 0, $showhidden = false, $userid = 0) {
        // Make some capability checks.
        if (!$this->skip_access_checks) {
            require_login();
            require_capability("totara/plan:accessplan", context_system::instance());
        }

        // Save supplied planid.
        $this->planid = $planid;
        $this->userid = $userid;

        $this->type = self::TYPE_CHOICE_MULTI;

        // Set lang file.
        $this->lang_file = 'totara_hierarchy';
        $this->string_nothingtodisplay = "error:dialognotreeitems";

        // Load plan.
        $this->set_plan($planid);

        if (empty($this->plan)) {
            echo '<p>' . get_string('noplansavailable', 'totara_plan') . '</p>';
            die();
        }

        // Check if switching frameworks.
        $this->switch_plans = optional_param('switchplan', false, PARAM_BOOL);
    }

    /**
     * Load plan items to display
     *
     * @param int $parentid (unused in evidence)
     * @param mixed $approved (unused in evidence)
     */
    public function load_items($parentid = 0, $approved = null) {
        global $DB;
        $where = "per.planid = :planid";
        $params = array('planid' => $this->plan->id);

        $sql = "SELECT DISTINCT pe.id, pe.name
                  FROM {dp_plan_evidence} pe
                  JOIN {dp_plan_evidence_relation} per
                    ON pe.id = per.evidenceid
                 WHERE " . $where . "
                 GROUP BY pe.id, pe.name";
        $this->items = $DB->get_records_sql($sql, $params);
    }


    /**
     * Prepare info for searc.php file
     *
     * @param stdClass $search_info
     * @param array $formdata
     * @param array $keywords
     * @param int $parentid (unused in evidence)
     * @param mixed $approved (unused in evidence)
     */
    public function put_search_info(stdClass $search_info, array &$formdata, array $keywords, $parentid = 0, $approved = null) {
        if (!isset($formdata['hidden']) || !is_array($formdata['hidden'])) {
            $formdata['hidden'] = array();
        }
        foreach ($this->searchparams as $key => $value) {
            $formdata['hidden'][$key] = $value;
        }

        $plans = $this->get_plan_list();
        if (is_array($plans) && count($plans) > 1) {
            $formdata['othertree'] = array_merge(array('0' => get_string('alllearning', 'totara_plan')), $plans);
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
        $where = "per.planid = :planid";
        $params = array('planid' => $this->plan->id);

        if ($keywords) {
            list($searchsql, $searchparams) = totara_search_get_keyword_where_clause($keywords, array('pe.name'),
                SQL_PARAMS_NAMED);
            $params = array_merge($params, $searchparams);
            $where .= ' AND '.$searchsql;
        }

        $sql = "FROM {dp_plan_evidence} pe
                JOIN {dp_plan_evidence_relation} per ON
                    pe.id = per.evidenceid
                WHERE $where";

        $search_info->id = 'pe.id';
        $search_info->fullname = 'pe.name';
        $search_info->sql = $sql;
        $search_info->order = 'ORDER BY pe.name';
        $search_info->params = $params;
    }

}
