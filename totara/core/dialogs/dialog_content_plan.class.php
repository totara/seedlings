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

require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content.class.php');
require_once($CFG->dirroot.'/totara/plan/development_plan.class.php');

class totara_dialog_content_plan extends totara_dialog_content {
    public $planid;

    public $plan;

    public $type;

    public $userid;

    /**
     * Flag to disable learning plan picker
     *
     * @access  public
     * @var     boolean
     */
    public $display_picker = true;

    /**
     * If you are making access checks seperately, you can disable
     * the internal checks by setting this to true
     *
     * @access  public
     * @var     boolean
     */
    public $skip_access_checks = false;

    /**
     * Show hidden frameworks
     *
     * @access public
     * @var    boolean
     */
    public $showhidden = false;

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
     * @param string $component
     * @param int $planid
     * @param bool $showhidden
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
        $this->showhidden = $showhidden;
        $this->userid = $userid;

        $this->component = $component;

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
     * Set framework hierarchy
     *
     * @param int $planid
     */
    public function set_plan($planid) {
        $this->plan = new development_plan($planid);
    }


    /**
     * Load plan items to display
     *
     * @param int $parentid (not used)
     * @param mixed $approved
     */
    public function load_items($parentid = 0, $approved = null) {
        $this->items = $this->plan->get_component($this->component)->get_assigned_items($approved);
    }


    /**
     * Prepare info for search.php file
     *
     * @param object $search_info
     * @param array $formdata
     * @param array $keywords
     * @param int $parentid
     * @param mixed $approved
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
        $this->plan->get_component($this->component)->get_search_info($search_info, $keywords, $parentid, $approved);
    }

    /**
     * Returns user plans
     *
     * @return array
     */
    protected function get_plan_list() {
        global $USER, $DB;

        $userid = isset($this->userid) ? $this->userid : $USER->id;

        list($sql, $params) = $DB->get_in_or_equal(array(DP_PLAN_STATUS_APPROVED, DP_PLAN_STATUS_COMPLETE));
        $params[] = $userid;

        return $DB->get_records_select_menu('dp_plan', 'status ' . $sql . 'AND userid = ?', $params, '', 'id,name');
    }

    /**
     * Prepend custom markup before treeview
     *
     * @return string
     */
    protected function _prepend_markup() {
        if (!$this->display_picker) {
            return '';
        }
        $plans = $this->get_plan_list();
        return display_dialog_selector($plans, '', 'planselector');
    }


    /**
     * Should we show the treeview root?
     *
     * @return  boolean
     */
    protected function _show_treeview_root() {
        return !$this->show_treeview_only || $this->switch_plans;
    }
}
