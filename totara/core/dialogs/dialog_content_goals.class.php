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
require_once($CFG->dirroot.'/totara/hierarchy/lib.php');
require_once($CFG->dirroot.'/totara/hierarchy/prefix/goal/lib.php');

class totara_dialog_content_goals extends totara_dialog_content {

    /**
     * Hierarchy object instance
     *
     * @access  public
     * @var     object
     */
    public $hierarchy;

    /**
     * Supplied framework id is either company or person.
     *
     * @access  public
     * @var     object
     */
    public $frameworkid;

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
     * Switching frameworks
     *
     * @access  public
     * @var     boolean
     */
    public $switch_frameworks = false;

    /**
     * Show hidden frameworks
     *
     * @access public
     * @var    boolean
     */
    public $showhidden = false;

    /**
     * Show company framework
     *
     * @access public
     * @var    boolean
     */
    public $showcompany = true;

    /**
     * Show personal framework
     *
     * @access public
     * @var    boolean
     */
    public $showpersonal = true;

    /**
     * User id
     * @var int
     */
    public $userid = 0;


    public function __construct($frameworkid = goal::SCOPE_COMPANY, $showhidden = false, $userid = 0) {

        // Make some capability checks.
        if (!$this->skip_access_checks) {
            require_login();
            require_capability("totara/plan:accessplan", context_system::instance());
        }

        // Save supplied frameworkid.
        $this->frameworkid = $frameworkid;
        $this->showhidden = $showhidden;
        $this->userid = $userid;

        // Load hierarchy instance.
        $this->hierarchy = hierarchy::load_hierarchy('goal');
        $this->searchtype = 'this';

        $this->type = self::TYPE_CHOICE_MULTI;

        // Set lang file.
        $this->lang_file = 'totara_hierarchy';
        $this->string_nothingtodisplay = "goalerror:dialognotreeitems";

        // Load framework.
        $this->set_framework($frameworkid);

        // Check if switching frameworks.
        $this->switch_frameworks = optional_param('switchframework', false, PARAM_BOOL);
    }


    /**
     * Set framework hierarchy
     *
     * @access  public
     * @param   $frameworkid    int
     */
    public function set_framework($frameworkid) {
        $this->frameworkid = $frameworkid;
    }


    /**
     * Load plan items to display
     *
     * @access  public
     * @param   $parentid   int
     */
    public function load_items($parentid = 0, $userid = 0) {
        global $DB, $USER;

        if (!$userid) {
            $userid = $this->userid;
        }
        if ($this->frameworkid == goal::SCOPE_COMPANY) {
            $goalcompanys = goal::get_goal_items(array('userid' => $userid), goal::SCOPE_COMPANY);

            if (!empty($goalcompanys)) {
                $goalids = array();
                foreach ($goalcompanys as $goalcompany) {
                    $goalids[] = $goalcompany->goalid;
                }
                // Get the fullname of the goals from the goal table.
                list($insql, $inparam) = $DB->get_in_or_equal($goalids);
                $goalnames = $DB->get_records_select_menu('goal', "id {$insql}", $inparam, '', 'id,fullname');
                foreach ($goalcompanys as $goalcompany) {
                    $goalcompany->fullname = $goalnames[$goalcompany->goalid];
                }
                usort($goalcompanys, array('self', 'goal_fullname_sort'));

                $this->items = $goalcompanys;
            } else {
                $this->items = array();
            }
        } else if ($this->frameworkid == goal::SCOPE_PERSONAL) {
            if ($userid == 0) {
                $userid = $USER->id;
            }
            // Personal goals.
            $goalpersonals = goal::get_goal_items(array('userid' => $userid), goal::SCOPE_PERSONAL);

            // Put prefix infront of personal goal to distinguish between them and company goals.
            foreach ($goalpersonals as $goalpersonal) {
                $goalpersonal->id = 'personal_' . $goalpersonal->id;
                $goalpersonal->fullname = $goalpersonal->name;
            }
            usort($goalpersonals, array('self', 'goal_fullname_sort'));

            $this->items = $goalpersonals;
        }
    }

    /**
     * Custom sorting function to sort an array of objects by the fullname property.
     *
     * @param object The first object.
     * @param object The second object.
     * @return int Comparison result, same as returned by strcmp().
     */
    public static function goal_fullname_sort($a, $b) {
        return strcmp($a->fullname, $b->fullname);
    }

    /**
     * Prepare info for searc.php file
     * @param stdClass $search_info
     * @param array $formdata
     * @param array $keywords
     */
    public function put_search_info(stdClass $search_info, array &$formdata, array $keywords, $parentid = 0, $approved = null) {
        $this->put_search_params($formdata);
        $this->get_search_info($search_info, $keywords, $parentid, $approved);
    }

    /**
     * Additional search form parameters
     *
     * @param array $formdata
     */
    protected function put_search_params(array &$formdata) {
        if (!isset($formdata['hidden']) || !is_array($formdata['hidden'])) {
            $formdata['hidden'] = array();
        }
        foreach ($this->searchparams as $key => $value) {
            $formdata['hidden'][$key] = $value;
        }
        $prefix = $this->hierarchy->prefix;
        $frameworkid = $this->frameworkid;

        $formdata['hierarchy'] = $this->hierarchy;
        $formdata['hidden']['prefix'] = $prefix;
        $formdata['showpicker'] = !$this->disable_picker;
        $formdata['showhidden'] = $this->showhidden;
        $formdata['frameworkid'] = $frameworkid;
        // We need hierarchy searchtype for search form.
        $formdata['searchtype'] = 'hierarchy';
        $formdata['othertree'] = $this->get_frameworks(array(), false, true);
    }

    /**
     * Get frameworks
     *
     * @return array
     */
    public function get_frameworks() {
        $list = array();
        if ($this->showcompany) {
            $list[goal::SCOPE_COMPANY] = get_string('companygoals', 'totara_hierarchy');
        }
        if ($this->showpersonal) {
            $list[goal::SCOPE_PERSONAL] = get_string('personalgoals', 'totara_hierarchy');
        }
        return $list;
    }

    /**
     * Search information for search dialog box
     *
     * @param stdClass $search_info
     * @param array $keywords
     */
    public function get_search_info(stdClass $search_info, array $keywords) {
        global $DB;

        $frameworkid = $this->frameworkid;
        $requireevidence = $this->requireevidence;
        $shortprefix = hierarchy::get_short_prefix($this->hierarchy->prefix);
        $showhidden = $this->showhidden;
        if ($frameworkid == goal::SCOPE_PERSONAL && $this->userid > 0) {
            return $this->get_personal_search_info($search_info, $keywords);
        }

        // Generate search SQL.
        $fields = array('i.fullname', 'i.shortname', 'i.description', 'i.idnumber');
        list($searchsql, $params) = totara_search_get_keyword_where_clause($keywords, $fields);

        $search_info->id = 'i.id';
        $search_info->fullname = 'CASE WHEN (i.idnumber IS NULL OR i.idnumber = \'\' OR i.idnumber = \'0\') ' .
                                      'THEN i.fullname ' .
                                      'ELSE '. $DB->sql_concat('i.fullname', "' ('", 'i.idnumber', "')'") . ' END';

        $joinassignments = '';
        if ($this->userid) {
            $searchsql .= ($searchsql != '') ?  ' AND iua.userid = ?' : ' iua.userid = ?';
            $params[] = $this->userid;
            $joinassignments = "JOIN {{$shortprefix}_user_assignment} iua ON (iua.{$shortprefix}id = i.id)";
        }
        $search_info->sql = "
            FROM
                {{$shortprefix}} i
            JOIN
                {{$shortprefix}_framework} f
             ON i.frameworkid = f.id
            {$joinassignments}
            WHERE
                {$searchsql}
            AND i.visible = 1
        ";

        // Don't show hidden frameworks.
        if (!$showhidden) {
            $search_info->sql .= ' AND f.visible = 1 ';
        }

        // Only show hierarchy items with evidence.
        if ($requireevidence) {
            $search_info->sql .= ' AND i.evidencecount > 0 ';
        }

        if (isset($this->customdata['current_item_id'])) {
            $search_info->sql .= "
                AND i.id <> ?
                ";
            $params[] = $this->customdata['current_item_id'];
        }

        $search_info->order = " ORDER BY i.frameworkid, i.sortthread";
        $search_info->params = $params;
    }

    /**
     * Search information for personal goals
     *
     * @param stdClass $search_info
     * @param array $keywords
     * @param int $parentid
     * @param array $approved
     */
    public function get_personal_search_info(stdClass $search_info, array $keywords) {
        global $DB;

        $fields = array('name', 'description');
        list($searchsql, $params) = totara_search_get_keyword_where_clause($keywords, $fields, SQL_PARAMS_NAMED);
        $params['userid'] = $this->userid;
        if ($searchsql != '') {
            $searchsql = ' AND '.$searchsql;
        }

        $search_info->id = $DB->sql_concat("'personal_'", sql_cast2char('id'));
        $search_info->fullname = 'name';
        $search_info->sql = 'FROM {goal_personal} WHERE userid = :userid'.$searchsql.' AND deleted = 0';
        $search_info->order = " ORDER BY name";
        $search_info->params = $params;
    }

    /**
     * Prepend markup
     *
     * @return string html
     */
    protected function _prepend_markup() {
        if (!$this->display_picker) {
            return '';
        }
        $select_options = $this->get_frameworks();
        return display_dialog_selector($select_options, goal::SCOPE_COMPANY, 'simpleframeworkpicker');
    }


    /**
     * Should we show the treeview root?
     *
     * @access  protected
     * @return  boolean
     */
    protected function _show_treeview_root() {
        return !$this->show_treeview_only || $this->switch_frameworks;
    }
}
