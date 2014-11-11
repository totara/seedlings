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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @author Aaron Barnes <aaronb@catalyst.net.nz>
 * @package totara
 * @subpackage plan
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

/**
 * Flag for dp_base_component::can_update_settings()
 */
define('LP_CHECK_ITEMS_EXIST', true);


abstract class dp_base_component {

    /**
     * Component name
     *
     * @access  public
     * @var     string
     */
    public $component;


    /**
     * Reference to the plan object
     *
     * @access  public
     * @var     object
     */
    protected $plan;


    /**
     * Constructor, add reference to plan object and
     * check required properties are set
     *
     * @access  public
     * @param   object  $plan
     * @return  void
     */
    public function __construct($plan) {
        $this->plan = $plan;

        // Calculate component name from class name
        if (!preg_match('/^dp_([a-z]+)_component$/', get_class($this), $matches)) {
            throw new Exception('Classname incorrectly formatted');
        }

        $this->component = $matches[1];

        // Check that child classes implement required properties
        $properties = array(
            'component',
            'permissions',
        );
        foreach ($properties as $property) {
            if (!property_exists($this, $property) && !property_exists(get_class($this), $property)) {
                $string_properties = new stdClass();
                $string_properties->property = $property;
                $string_properties->class = get_class($this);
                throw new Exception(get_string('error:propertymustbeset', 'totara_plan', $string_properties));
            }
        }
    }


    /**
     * Initialize settings for the component
     *
     * @access  public
     * @param   array   $settings
     * @return  void
     */
    public function initialize_settings(&$settings) {
        // override this method in child classes to add component-specific
        // settings to plan's setting property
    }


    /**
     * Get setting value
     *
     * @access  public
     * @param   string  $key    Setting name
     * @return  mixed
     */
    public function get_setting($key) {
        return $this->plan->get_component_setting($this->component, $key);
    }


    /**
     * Can the logged in user update items in this component
     *
     * Returns false if they cannot, or a constant detailing their
     * exact permissions if they can
     *
     * @access  public
     * @return  false|int
     */
    public function can_update_items() {
        // Check plan is active
        if ($this->plan->is_complete()) {
            return false;
        }

        // Get permission
        $updateitem = $this->get_setting('update'.$this->component);

        // If user cannot edit/request items, no point showing picker
        if (!in_array($updateitem, array(DP_PERMISSION_ALLOW, DP_PERMISSION_REQUEST, DP_PERMISSION_APPROVE))) {
            return false;
        }

        // If the plan is in a draft state, skip the approval process
        if (!$this->plan->is_active()) {
            return DP_PERMISSION_ALLOW;
        }

        return $updateitem;
    }


    /**
     * Can the logged in user update settings for items in this component
     *
     * Returns false if they cannot, or an array detailing their
     * exact permissions if they can
     *
     * Optionally check if there are any items they can update also, and if
     * there are none return false
     *
     * @access  public
     * @param   boolean     $checkexists (optional)
     * @return  false|int
     */
    public function can_update_settings($checkexists = LP_CHECK_ITEMS_EXIST) {
        // Check plan is active
        if ($this->plan->is_complete()) {
            return false;
        }

        // Get permissions
        $can = array();

        $can['setduedate'] = $this->get_setting('duedatemode') && $this->get_setting('setduedate') >= DP_PERMISSION_ALLOW;
        $can['setpriority'] = $this->get_setting('prioritymode') && $this->get_setting('setpriority') >= DP_PERMISSION_ALLOW;
        $can['approve'.$this->component] = $this->get_setting('update'.$this->component) == DP_PERMISSION_APPROVE;

        if (method_exists($this, 'can_update_settings_extra')) {
            $can = $this->can_update_settings_extra($can);
        }

        // If user has no permissions, return false
        $noperms = true;
        foreach ($can as $c) {
            if (!empty($c)) {
                $noperms = false;
                break;
            }
        }
        if ($noperms) {
            return false;
        }
        unset($noperms);

        // If checkexists set, check for items
        if ($checkexists && !$this->get_assigned_items()) {
            return false;
        }

        // Otherwise, return permissions the user does have
        return $can;
    }


    /**
     * Get list of items assigned to plan
     *
     * Optionally, filtered by status
     *
     * @access  public
     * @param   mixed   $approved   (optional)
     * @param   string  $orderby    (optional)
     * @param   int     $limitfrom  (optional)
     * @param   int     $limitnum   (optional)
     * @return  array
     */
    abstract public function get_assigned_items($approved = null, $orderby='', $limitfrom='', $limitnum='');


    /**
     * Get count of items assigned to plan
     *
     * Optionally, filtered by status
     *
     * @access  public
     * @param   mixed   $approved   (optional)
     * @return  integer
     */
    public function count_assigned_items($approved = null) {
        global $CFG, $DB;

        // Generate where clause
        $where = "a.planid = ?";
        $params = array($this->plan->id);
        if ($approved !== null) {
            list($approved_sql, $approved_params) = $DB->get_in_or_equal($approved);
            $where .= " AND a.approved $approved_sql";
            $params = array_merge($params, $approved_params);
        }

        $tablename = $this->get_component_table_name();

        $count = $DB->count_records_sql(
            "
            SELECT
                COUNT(a.id)
            FROM
                {{$tablename}} a
            WHERE
                $where
            ", $params
        );

        return $count;
    }


    /**
     * Process an action
     *
     * General component actions can come in here
     *
     * @access  public
     * @return  void
     */
    abstract public function process_action_hook();


    /**
     * Process when plan is created
     *
     * Any actions that need to be processed on a component
     * when a plan is created.
     *
     * @access public
     * @return void
     */
    abstract public function plan_create_hook();


    /**
     * Process component's settings update
     *
     * @access  public
     * @param   bool    $ajax   Is an AJAX request (optional)
     * @return  void
     */
    abstract public function process_settings_update($ajax = false);

    /**
     * Returns true if any items from this component uses the scale given
     *
     * You should override this method in each child class.
     *
     * @param integer $scaleid
     * return boolean
     */
    public static function is_priority_scale_used($scaleid) {
        debugging('The component "' . $this->component . '" has not defined the method "is_priority_scale_used()". This should be defined to ensure that priority scales remain consistent. If not required, just return false.', DEBUG_DEVELOPER);
        return false;
    }

    /**
     * Code to run before after header is displayed
     *
     * @access  public
     * @return  void
     */
    public function post_header_hook() {}


    /**
     * Code to load the JS for the picker
     *
     * @access  public
     * @return  void
     */
    public function setup_picker() {}


    /**
     * Get url for component tab
     *
     * @access  public
     * @return  string
     */
    public function get_url() {

        return new moodle_url('/totara/plan/component.php', array('id' => $this->plan->id, 'c' => $this->component));
    }


    /**
     * Return markup to display component's assigned items in a table
     *
     * Optionally restrict results by approval status
     *
     * @access  public
     * @param   mixed   $restrict   Array or integer (optional)
     * @return  string
     */
    public function display_list($restrict = null) {
        // If no items, return message instead of table
        if (!$count = $this->count_assigned_items($restrict)) {
            $plural = mb_strtolower(get_string($this->component.'plural', 'totara_plan'), "UTF-8");
            return html_writer::tag('span', get_string('nox', 'totara_plan', $plural), array('class' => 'noitems-assign'.$this->component));
        }

        // Get table headers/columns
        $headers = $this->get_list_headers();
        // Return instead of outputting table contents
        ob_start();
        // Generate table
        $table = new flexible_table($this->component.'list');
        $table->define_columns($headers->columns);
        $table->define_headers($headers->headers);
        $table->define_baseurl($this->get_url());

        $table->set_attribute('class', 'dp-plan-component-items');
        $table->sortable(true, 'name');
        $table->no_sorting('status');
        $table->no_sorting('actions');
        $table->no_sorting('comments');
        $table->setup();
        $table->pagesize(DP_COMPONENTS_PER_PAGE, $count);

        // Load items for table
        $page_start = $table->get_page_start();
        $page_size = $table->get_page_size();
        $sort = $table->get_sql_sort();
        $items = $this->get_assigned_items($restrict, $sort, $page_start, $page_size);

        // Collect the rows/columns to display
        $rows = array();
        foreach ($items as $item) {
            $row = $this->display_list_row($headers->columns, $item);
            $rows[] = $row;
        }

        if (!empty($headers->hide_if_empty)) {
            // Check for empty columns

            // Set up columns to show
            $showcolumn = array();
            foreach ($headers->columns as $index => $column) {
                if (in_array($column, $headers->hide_if_empty)) {
                    // Don't show unless there is a value
                    $showcolumn[$index] = false;
                } else {
                    // Always show
                    $showcolumn[$index] = true;
                }
            }

            // Check each column for a value
            foreach ($rows as $row) {
                foreach ($headers->columns as $index => $column) {
                    if (!$showcolumn[$index]) {
                        // Check if this column has a value
                        // Note that this doesn't check if its just html, this should be up to the display_list_item_$col method
                        $value = (string)$row[$index];
                        if (strlen(trim($value)) > 0) {
                            // There is a value, so display it
                            $showcolumn[$index] = true;
                        }
                    }
                }
            }

            // Reset columns and headers
            $newcolumns = array();
            $newheaders = array();
            foreach ($headers->columns as $index => $column) {
                if ($showcolumn[$index]) {
                    // There is a value
                    $newcolumns[] = $column;
                    $newheaders[] = $headers->headers[$index];
                }
            }
            // Redefine columns
            $table->define_columns($newcolumns);
            $table->define_headers($newheaders);

            // Reset the data
            $newrows = array();
            foreach($rows as $row) {
                $newrow = array();
                foreach ($headers->columns as $index => $column) {
                    if ($showcolumn[$index]) {
                        // There is a value, so include this column
                        $newrow[] = $row[$index];
                    }
                }
                $newrows[] = $newrow;
            }
            $rows = $newrows;
        }

        // Send them off to the table for display
        $numberrows = count($rows);
        $rownumber = 0;
        foreach ($rows as $row) {
            if (++$rownumber >= $numberrows) {
                $table->add_data($row, 'last');
            } else {
                $table->add_data($row);
            }
        }

        $table->finish_html();
        $out = ob_get_contents();
        ob_end_clean();

        return $out;
    }


    /**
     * Get column headers array
     *
     * @access  protected
     * @return  object
     */
    protected function get_list_headers() {
        // Get plan / component data
        $plancompleted = $this->plan->is_complete();

        // Get display options
        $optreq = array(DP_DUEDATES_OPTIONAL, DP_DUEDATES_REQUIRED);
        $showduedates = in_array($this->get_setting('duedatemode'), $optreq);
        $showpriorities = in_array($this->get_setting('prioritymode'), $optreq);

        // Generate table headers
        $tableheaders = array(
            get_string($this->component.'name', 'totara_plan'),
            get_string('status', 'totara_plan'),
        );

        $tablecolumns = array(
            'name',
            'progress',
        );

        $tablehide = array();

        if (($this->component == 'competency' || $this->component == 'objective')
            && $this->plan->get_component('course')->get_setting('enabled')) {
            $tableheaders[] = get_string('numberoflinkedcourses', 'totara_plan');
            $tablecolumns[] = 'linkedcourses';
            $tablehide[] = 'linkedcourses';
        }

        $tableheaders[] = get_string('numberoflinkedevidence', 'totara_plan');
        $tablecolumns[] = 'linkedevidence';
        $tablehide[] = 'linkedevidence';

        if ($showpriorities) {
            $tableheaders[] = get_string('priority', 'totara_plan');
            $tablecolumns[] = 'priority';
            $tablehide[] = 'priority';
        }

        if ($showduedates) {
            $tableheaders[] = get_string('duedate', 'totara_plan');
            $tablecolumns[] = 'duedate';
            $tablehide[] = 'duedate';
        }

        if (!$plancompleted) {
            $tableheaders[] = '';  // don't show status header
            $tablecolumns[] = 'status';
            $tablehide[] = 'status';
        }

        // Comments
        $tableheaders[] = get_string('comments');
        $tablecolumns[] = 'comments';
        $tablehide[] = 'comments';

        $tableheaders[] = get_string('actions', 'totara_plan');
        $tablecolumns[] = 'actions';
        $tablehide[] = 'actions';

        $return = new stdClass();
        $return->headers = $tableheaders;
        $return->columns = $tablecolumns;
        $return->hide_if_empty = $tablehide;

        return $return;
    }


    /**
     * Display row in item list
     *
     * @access  protected
     * @param   $cols   array
     * @param   $item   object
     * @return  array   $row
     */
    protected function display_list_row($cols, $item) {

        // Generate markup
        $row = array();

        foreach ($cols as $col) {
            $method = "display_list_item_{$col}";
            $row[] = $this->$method($item);
        }

        return $row;
    }


    /**
     * Display items from this component that require approval
     *
     * Override within component class to add additional information
     * to approval confirmation
     */
    public function display_approval_list($pendingitems) {
        $controls = html_writer::start_tag('div', array('class' => 'fullwidth generaltable', 'style' => 'display: table'));

        foreach ($pendingitems as $item) {
            // @todo write abstracted display_item_name() and use here
            $controls .= html_writer::start_tag('div', array('style' => 'display: table-row'));
            $controls .= html_writer::tag('div', format_string($item->fullname), array('style' => 'display: table-cell'));
            $controls .= html_writer::tag('div', $this->display_approval_options($item, $item->approved), array('style' => 'display: table-cell'));
            $controls .= html_writer::start_div('', array('style' => 'display: table-cell'));
            $controls .= get_string('reasonfordecision', 'totara_message');
            $controls .= html_writer::empty_tag('input', array('type' => 'text', 'name' => "reasonfordecision_{$this->component}[$item->id]"));
            $controls .= html_writer::end_div();
            $controls .= html_writer::end_tag('div');
        }
        $controls .= html_writer::end_tag('div');
        return $controls;
    }


    /**
     * Get all instances of $componentrequired linked to the specified item
     *
     * @todo doesn't current exclude unapproved items
     * that is currently handled inside display_linked_*() methods
     * but might be better to do it here?
     *
     * @todo refactor to reuse {@link get_relation_array()}
     *
     * @param integer $id Identifies the item to get linked items for
     * @param string $componentrequired Get linked items of this type
     *
     * @return array Array of IDs of all linked items, or false
     */
    function get_linked_components($id, $componentrequired) {
        global $DB;
        // name of the current component
        $thiscomponent = $this->component;

        // component relations are stored alphabetically
        // first component is in component1
        // Figure out which order to perform query
        $cmp = strcmp($thiscomponent, $componentrequired);

        if ($cmp < 0) {
            $matchedcomp = 'component1';
            $matchedid = 'itemid1';
            $searchedcomp = 'component2';
            $searchedid = 'itemid2';
        } else if ($cmp > 0) {
            $matchedcomp = 'component2';
            $matchedid = 'itemid2';
            $searchedcomp = 'component1';
            $searchedid = 'itemid1';
        } else {
            // linking within the same component not supported
            return false;
        }

        // find all matching relations
        $sql = "
            SELECT
                id,
                $searchedid AS itemid
            FROM
                {dp_plan_component_relation}
            WHERE
                $matchedcomp = ?
            AND $matchedid = ?
            AND $searchedcomp = ?
        ";
        $params = array($thiscomponent, $id, $componentrequired);

        // return an array of IDs
        if ($result = $DB->get_records_sql($sql, $params)) {
            $out = array();
            foreach ($result as $item) {
                $out[] = $item->itemid;
            }
            return $out;
        } else {
            // no matches
            return false;
        }
    }

    /**
     * Retrieve the id of all mandatory links for a specified component
     *
     * @param integer $id           identifies the specific component to retrieve links for
     * @param string  $component    identifies the type of component (course/competency)
     *
     * @return        array         an array of the id's of all mandatory linked components
     */
    function get_mandatory_linked_components($id, $component) {
        global $DB;

        if ($id < 1) {
            return null;
        }

        $params = array('competency', $id, 'course', 'course');

        if ($component == "competency") {
            $sql = "SELECT DISTINCT itemid2 AS id FROM {dp_plan_component_relation} WHERE component1 = ? AND itemid1 = ? AND component2 = ? AND mandatory = ?";
        } else if ($component == "course") {
            $sql = "SELECT DISTINCT itemid1 AS id FROM {dp_plan_component_relation} WHERE component1 = ? AND itemid2 = ? AND component2 = ? AND mandatory = ?";
        } else {
            return null;
        }


        return $DB->get_fieldset_sql($sql, $params);
    }


    /**
     * Update instances of $componentupdatetype linked to the specified compoent,
     * delete links in db which aren't needed, and add links missing from db
     * which are needed
     *
     * @todo refactor to reuse {@link get_relation_array()}
     *
     * @param integer $thiscompoentid Identifies the component on one end of the link
     * @param string $componentupdatetype: the type of components on the other end of the links
     * @param array $componentids array of component ids that should be on the other end of the links in db
     *
     * @return void
     */
    function update_linked_components($thiscomponentid, $componentupdatetype, $componentids) {
        global $DB;
        // name of the current component
        $thiscomponent = $this->component;

        // component relations are stored alphabetically
        // first component is in component1
        // Figure out which order to perform query
        $cmp = strcmp($thiscomponent, $componentupdatetype);

        if ($cmp < 0) {
            $matchedcomp = 'component1';
            $matchedid = 'itemid1';
            $searchedcomp = 'component2';
            $searchedid = 'itemid2';
            $thiscomponentfirst = true;
        } else if ($cmp > 0) {
            $matchedcomp = 'component2';
            $matchedid = 'itemid2';
            $searchedcomp = 'component1';
            $searchedid = 'itemid1';
            $thiscomponentfirst = false;
        } else {
            // linking within the same component not supported
            return false;
        }

        // find all matching relations in db
        $sql = "SELECT id, $searchedid AS itemid
            FROM {dp_plan_component_relation}
            WHERE $matchedcomp = ? AND
                $matchedid = ? AND
                $searchedcomp = ?";
        $params = array($thiscomponent, $thiscomponentid, $componentupdatetype);
        $result = $DB->get_records_sql($sql, $params);
        foreach ($result as $item) {
            $position = array_search($item->itemid, $componentids);
            if ($position === false) {
                //Item in db isn't in the array of items to keep - delete from db:
                $DB->delete_records('dp_plan_component_relation', array('id' => $item->id));
            } else {
                //Item in array of items to keep is already in db - delete from keep array
                unset($componentids[$position]);
            }
        }
        if (!empty($componentids)) {
            $relation = new stdClass();
            // There are still required compoent links that are not already in the database:
            $relation->component1 = $thiscomponentfirst ? $thiscomponent : $componentupdatetype;
            $relation->component2 = $thiscomponentfirst ? $componentupdatetype : $thiscomponent;
            foreach ($componentids as $linkedcomponentid) {
                $relation->itemid1 = $thiscomponentfirst ? $thiscomponentid : $linkedcomponentid;
                $relation->itemid2 = $thiscomponentfirst ? $linkedcomponentid : $thiscomponentid;
                $DB->insert_record('dp_plan_component_relation', $relation);
            }
        }
    }


    /**
     * Count instances of $componentrequired linked to items of this component type
     *
     * @todo refactor to reuse {@link get_relation_array()}
     *
     * @param string $componentrequired Get linked items of this type
     * @return array Array of matches
     */
    function get_all_linked_components($componentrequired) {
        global $DB;
        // name of the current component
        $thiscomponent = $this->component;

        // component relations are stored alphabetically
        // first component is in component1
        // Figure out which order to perform query
        $cmp = strcmp($thiscomponent, $componentrequired);

        if ($cmp < 0) {
            $matchedcomp = 'component1';
            $matchedid = 'itemid1';
            $searchedcomp = 'component2';
            $searchedid = 'itemid2';
        } else if ($cmp > 0) {
            $matchedcomp = 'component2';
            $matchedid = 'itemid2';
            $searchedcomp = 'component1';
            $searchedid = 'itemid1';
        } else {
            // linking within the same component not supported
            return false;
        }

        // @todo doesn't current exclude unapproved items
        $sql = "SELECT $matchedid AS id,
                COUNT($searchedid) AS items
            FROM {dp_plan_component_relation}
            WHERE $matchedcomp = ? AND
                  $searchedcomp = ?
            GROUP BY $matchedid";
        $params = array($thiscomponent, $componentrequired);

        return $DB->get_records_sql_menu($sql, $params);

    }


    /**
     * Is the item a mandatory relation for something else?
     *
     * @access  protected
     * @param   int     $itemid
     * @return  bool
     */
    protected function is_mandatory_relation($itemid) {
        global $CFG, $DB;

        // Count mandatory records
        $sql = "
            SELECT
                *
            FROM
                {dp_plan_component_relation}
            WHERE
                mandatory = ?
            AND
            (
                (component1 = ? AND itemid1 = ?) OR
                (component2 = ? AND itemid2 = ?)
            )
        ";
        $params = array($this->component, $this->component, $itemid, $this->component, $itemid);

        return $DB->record_exists_sql($sql, $params);
    }


    /**
     * Update assigned items
     *
     * @access  public
     * @param   $items  array   Array of item ids
     * @return  void
     */
    public function update_assigned_items($items) {
        global $USER, $DB;
        $item_id_name = $this->component . 'id';

        // Get currently assigned items
        $assigned = $this->get_assigned_items();
        $assigned_ids = array();
        foreach ($assigned as $item) {
            $assigned_ids[$item->$item_id_name] = $item->$item_id_name;
        }
        $sendalert = (count(array_diff($items, $assigned_ids)) || count(array_diff($assigned_ids, $items)))
            && $this->plan->status != DP_PLAN_STATUS_UNAPPROVED && $this->plan->status != DP_PLAN_STATUS_PENDING;
        $updates = '';

        $transaction = $DB->start_delegated_transaction();

        if ($items) {
            foreach ($items as $itemid) {
                // Validate id
                if (!is_numeric($itemid)) {
                    throw new Exception(get_string('baddata', 'totara_plan'));
                }
                // Check if not already assigned
                if (!isset($assigned_ids[$itemid])) {
                    $result = $this->assign_new_item($itemid);
                    if (!$result) {
                        print_error('error:couldnotassignnewitem', 'totara_plan');
                    }
                    $updates .= get_string('addedx', 'totara_plan', $result->fullname).html_writer::empty_tag('br');
                }
                // Remove from list to prevent deletion
                unset($assigned_ids[$itemid]);
            }
        }
        // Remaining items to be deleted
        foreach ($assigned as $item) {
            if (!isset($assigned_ids[$item->$item_id_name])) {
                continue;
            }
            // Check the user has permission on each item individually
            if (!$this->can_delete_item($item)) {
                continue;
            }
            $this->unassign_item($item);
            $updates .= get_string('removedx', 'totara_plan', $assigned[$item->id]->fullname).html_writer::empty_tag('br');
        }
        $transaction->allow_commit();

        if ($sendalert) {
            $this->send_component_update_alert($updates);
        }
    }


    /**
     * Send update alerts
     *
     * @param string $updateinfo
     * @return void
     */
    function send_component_update_alert($update_info='') {

        global $USER, $CFG, $DB, $OUTPUT;
        require_once($CFG->dirroot.'/totara/message/messagelib.php');

        $event = new stdClass;
        $userfrom = $DB->get_record('user', array('id' => $USER->id));
        $event->userfrom = $userfrom;
        $event->contexturl = $this->get_url();
        $event->icon = $this->component.'-update';
        $a = new stdClass;
        $a->plan = format_string($this->plan->name);
        $a->planhtml = $OUTPUT->action_link(new moodle_url('/totara/plan/view.php', array('id' => $this->plan->id)),
            $this->plan->name, null, array('title' => $this->plan->name));
        $a->component = get_string($this->component.'plural', 'totara_plan');
        $a->updates = text_to_html($update_info, 75, false);
        $a->updateshtml = $update_info;

        $stringmanager = get_string_manager();
        // did they edit it themselves?
        if ($USER->id == $this->plan->userid) {
            // notify their manager
            if ($this->plan->is_active()) {
                if ($manager = totara_get_manager($this->plan->userid)) {
                    $event->userto = $manager;
                    $a->user = fullname($USER);
                    $event->subject = $stringmanager->get_string('componentupdateshortmanager', 'totara_plan', $a, $manager->lang);
                    $event->fullmessage = $stringmanager->get_string('componentupdatelongmanager', 'totara_plan', $a, $manager->lang);
                    $event->fullmessagehtml = $stringmanager->get_string('componentupdatelongmanagerhtml', 'totara_plan', $a, $manager->lang);
                    tm_alert_send($event);
                }
            }
        } else {
            // notify user that someone else did it
            $userto = $DB->get_record('user', array('id' => $this->plan->userid));
            $event->userto = $userto;
            $event->subject = $stringmanager->get_string('componentupdateshortlearner', 'totara_plan', $a->component, $userto->lang);
            $event->fullmessage = $stringmanager->get_string('componentupdatelonglearner', 'totara_plan', $a, $userto->lang);
            $event->fullmessagehtml = $stringmanager->get_string('componentupdatelonglearnerhtml', 'totara_plan', $a, $userto->lang);
            tm_alert_send($event);
        }
    }


    /**
     * Send approval alerts
     *
     * @param object $approval the approval type
     * @return void
     */
    function send_component_approval_alert($approval) {
        global $USER, $CFG, $DB, $OUTPUT;
        require_once($CFG->dirroot.'/totara/message/messagelib.php');
        if ($approval->after == DP_APPROVAL_DECLINED) {
            $type = 'decline';
        } else if ($approval->after == DP_APPROVAL_APPROVED) {
            $type = 'approve';
        }

        $event = new stdClass;
        $event->userfrom = $USER;
        $event->contexturl = $this->get_url();
        $event->icon = $this->component.'-'.$type;
        $a = new stdClass;

        $a->plan = format_string($this->plan->name);
        $a->planhtml = $OUTPUT->action_link(new moodle_url('/totara/plan/view.php', array('id' => $this->plan->id)),
            $this->plan->name, null, array('title' => $this->plan->name));
        $a->component = get_string($this->component.'plural', 'totara_plan');

        $a->updates = text_to_html($approval->text, 75, false);
        $a->updateshtml = $approval->text;
        $a->name = $approval->itemname;
        $reasonfordecision = $approval->reasonfordecision;

        // Did they edit it themselves?
        $stringmanager = get_string_manager();
        if ($USER->id == $this->plan->userid) {
            // Notify their manager.
            if ($this->plan->is_active()) {
                if ($manager = totara_get_manager($this->plan->userid)) {
                    $event->userto = $manager;
                    $a->user = fullname($USER);
                    $event->subject = $stringmanager->get_string('component'.$type.'shortmanager', 'totara_plan', $a, $manager->lang);
                    $event->fullmessage = $stringmanager->get_string('component'.$type.'longmanager', 'totara_plan', $a, $manager->lang);
                    $event->fullmessagehtml = $stringmanager->get_string('component'.$type.'longmanagerhtml', 'totara_plan', $a, $manager->lang);
                    if (!empty($reasonfordecision)) {
                        $breakline = html_writer::empty_tag('br') . html_writer::empty_tag('br');
                        $decision = $stringmanager->get_string('reasongivenfordecision', 'totara_plan', $reasonfordecision, $manager->lang);
                        $event->fullmessage .= $breakline . $decision;
                        $event->fullmessagehtml .= $breakline . $decision;
                    }
                    tm_alert_send($event);
                }
            }
        } else {
            // Notify user that someone else did it.
            $userto = $DB->get_record('user', array('id' => $this->plan->userid));
            $event->userto = $userto;
            $event->subject = $stringmanager->get_string('component'.$type.'shortlearner', 'totara_plan', $a, $userto->lang);
            $event->fullmessage = $stringmanager->get_string('component'.$type.'longlearner', 'totara_plan', $a, $userto->lang);
            $event->fullmessagehtml = $stringmanager->get_string('component'.$type.'longlearnerhtml', 'totara_plan', $a, $userto->lang);
            if (!empty($reasonfordecision)) {
                $breakline = html_writer::empty_tag('br') . html_writer::empty_tag('br');
                $decision = $stringmanager->get_string('reasongivenfordecision', 'totara_plan', $reasonfordecision, $userto->lang);
                $event->fullmessage .= $breakline . $decision;
                $event->fullmessagehtml .= $breakline . $decision;
            }
            tm_alert_send($event);
        }
    }


    /**
     * Send completion alerts
     *
     * @param object $completion containing completion data
     * @return void
     */
    function send_component_complete_alert($completion) {
        global $USER, $CFG, $DB, $OUTPUT;
        require_once($CFG->dirroot.'/totara/message/messagelib.php');

        $event = new stdClass;
        $event->userfrom = $USER;
        $event->contexturl = $this->get_url();
        $event->icon = $this->component.'-complete';
        $a = new stdClass;

        $a->plan = format_string($this->plan->name);
        $a->planhtml = $OUTPUT->action_link(new moodle_url('/totara/plan/view.php', array('id' => $this->plan->id)),
            $this->plan->name, null, array('title' => $this->plan->name));
        $a->component = get_string($this->component.'plural', 'totara_plan');

        $a->updates = text_to_html($completion->text, 75, false);
        $a->updateshtml = $completion->text;
        $a->name = $completion->itemname;

        // did they edit it themselves?
        $stringmanager = get_string_manager();
        if ($USER->id == $this->plan->userid) {
            // notify their manager
            if ($this->plan->is_active()) {
                if ($manager = totara_get_manager($this->plan->userid)) {
                    $event->userto = $manager;
                    $a->user = fullname($USER);
                    $event->subject = $stringmanager->get_string('componentcompleteshortmanager', 'totara_plan', $a, $manager->lang);
                    $event->fullmessage = $stringmanager->get_string('componentcompletelongmanager', 'totara_plan', $a, $manager->lang);
                    $event->fullmessagehtml = $stringmanager->get_string('componentcompletelongmanagerhtml', 'totara_plan', $a, $manager->lang);
                    tm_alert_send($event);
                }
            }
        } else {
            // notify user that someone else did it
            $userto = $DB->get_record('user', array('id' => $this->plan->userid));
            $event->userto = $userto;
            $event->subject = $stringmanager->get_string('componentcompleteshortlearner', 'totara_plan', $a, $userto->lang);
            $event->fullmessage = $stringmanager->get_string('componentcompletelonglearner', 'totara_plan', $a, $userto->lang);
            $event->fullmessagehtml = $stringmanager->get_string('componentcompletelonglearnerhtml', 'totara_plan', $a, $userto->lang);
            tm_alert_send($event);
        }
    }

    /**
     * Unassign an item from a plan
     *
     * @access  public
     * @return  boolean
     */
    public function unassign_item($item) {
        global $DB;

        // Get approval value for new item
        if (!$permission = $this->can_update_items()) {
            print_error('error:cannotupdateitems', 'totara_plan');
        }

        // If allowed, or assignment not yet approved, remove assignment
        if ($permission >= DP_PERMISSION_ALLOW || $item->approved <= DP_APPROVAL_UNAPPROVED) {
            $DB->delete_records('dp_plan_'.$this->component.'_assign', array('id' => $item->id, 'planid' => $this->plan->id));
            // Delete mappings
            $DB->delete_records('dp_plan_component_relation', array('component1' => $this->component, 'itemid1' => $item->id));
            $DB->delete_records('dp_plan_component_relation', array('component2' => $this->component, 'itemid2' => $item->id));
            return true;
        }

        return false;
    }


    /**
     * Return default priority for this component, or null if nothing set
     *
     * @access  public
     * @return  int
     */
    public function get_default_priority() {
        global $DB;

        if (!$comp = $this->plan->get_component($this->component)) {
            return null;
        }
        if ($comp->get_setting('prioritymode') != DP_PRIORITY_REQUIRED) {
            // Don't bother if priorities aren't required
            return null;
        }

        $scale = $DB->get_record('dp_priority_scale', array('id' => $comp->get_setting('priorityscale')));

        return $scale ? $scale->defaultid : null;
    }


    /**
     * Make unassigned items requested
     *
     * @access  public
     * @param   array   $items  Unassigned items to update
     * @return  array
     */
    public function make_items_requested($items) {
        global $DB;

        $table = $this->get_component_table_name();

        $updated = array();
        foreach ($items as $item) {
            // Attempt to load item
            $record = $DB->get_record($table, array('id' => $item->id));
            if (!$record) {
                continue;
            }

            // Attempt to update record
            $record->approved = DP_APPROVAL_REQUESTED;
            $DB->update_record($table, $record);

            // Save in updated list
            $updated[] = $item;
        }

        return $updated;
    }


    /**
     * Checks to see if an approval value is
     * approved or greater
     *
     * @access  public
     * @param   integer $value  Approval constant e.g. DP_APPROVAL_*
     * @return  boolean
     */
    public function is_item_approved($value) {
        return $value >= DP_APPROVAL_APPROVED;
    }


    /**
     * Check if item is "complete" or "finished"
     *
     * @access  public
     * @param   object  $item
     * @return  boolean
     */
    abstract protected function is_item_complete($item);


    /**
     * Reactivates item when re-activating a plan
     *
     * @return bool $success
     */
    abstract public function reactivate_items();


    /**
     * Gets ids of all plans that contain this item
     *
     * @param int $itemid id of item
     * @param int $userid id of user to find plans for
     * @return array $plans an array of plans or false if there are no plans
     */
    public static function get_plans_containing_item($itemid, $userid) {
        debugging('The component "' . $this->component . '" has not defined the method "get_plans_containing_item($itemid, $userid)". This should be defined in order for auto completion of plans to work correctly. Any component that doen\'t define this method will assume that all items in that component are complete when auto completion is turned on.', DEBUG_DEVELOPER);
        return true;
    }

    /**
     * Returns true if all items in a component are complete
     *
     * @return boolean $complete returns true if all assigned items are complete
     */
    public function items_all_complete() {
        $complete = true;
        $items = $this->get_assigned_items();

        foreach ($items as $i) {
            $complete = $complete && $this->is_item_complete($i);
        }

        return $complete;
    }

    /**
     * Returns true if the item is assigned to the current plan
     *
     * Only for use with assigned components (courses, competencies), not objectives. Assumes
     * a table 'dp_plan_[component]_assign' with a field of '[component]id'
     *
     * @param integer $itemid ID of the item being assigned (item id not assignment id)
     *
     * @return boolean true if is assigned
     */
    public function is_item_assigned($itemid) {
        global $DB;

        $component = $this->component;
        $table = "dp_plan_{$component}_assign";
        $itemname = "{$component}id";
        return $DB->record_exists($table, array('planid' => $this->plan->id, $itemname => $itemid));
    }


    /**
     * Check's if the logged in user can delete an item
     *
     * @access  public
     * @param   object  $item
     * @return  boolean
     */
    public function can_delete_item($item) {
        // Load permissions
        $canupdateitems = $this->can_update_items();

        // If user has full permissions (allow/approve)
        if ($canupdateitems >= DP_PERMISSION_ALLOW) {
            return true;
        }

        // Or if can't request items
        if ($canupdateitems != DP_PERMISSION_REQUEST) {
            return false;
        }

        // If can request, and item is not yet approved
        return in_array($item->approved, array(DP_APPROVAL_UNAPPROVED, DP_APPROVAL_DECLINED));
    }


    /**
     * Return the name of the component items table
     *
     * Override in subclass if component uses a different pattern
     *
     * @return string Name of the table containing item assignments
     */
    public function get_component_table_name() {
        return "dp_plan_{$this->component}_assign";
    }


    /**
     * Get priority values
     *
     * @access  public
     * @return  array
     */
    public function get_priority_values() {
        global $DB;

        static $values;
        if (!isset($values[$this->component])) {
            $priorityscaleid = $this->get_setting('priorityscale') ? $this->get_setting('priorityscale') : -1;
            $v = $DB->get_records('dp_priority_scale_value', array('priorityscaleid' => $priorityscaleid), 'sortorder', 'id,name,sortorder');
            $values[$this->component] = $v;
        }

        return $values[$this->component];
    }


    /*********************************************************************************************
     *
     * Display methods
     *
     ********************************************************************************************/

    /**
     * Display names of list items
     *
     * @access protected
     * @param object $item
     * @return string
     */
    protected function display_list_item_name($item) {
        return $this->display_item_name($item);
    }


    /**
     * Display priority of list items
     *
     * @access protected
     * @param object $item
     * @return string
     */
    protected function display_list_item_priority($item) {
        return $this->display_priority($item);
    }


    /**
     * Display due date of list items
     *
     * @access protected
     * @param object $item
     * @return string
     */
    protected function display_list_item_duedate($item) {
        return $this->display_duedate($item->id, $item->duedate);
    }

    /**
     * Display comments on list items
     *
     * @access protected
     * @param object $item
     * @return string html
     */
    protected function display_list_item_comments($item) {
        global $CFG, $OUTPUT;

        require_once($CFG->dirroot . '/comment/lib.php');

        $options = new stdClass;
        $options->area    = 'plan_'.$this->component.'_item';
        $options->context = context_system::instance();
        $options->itemid  = $item->id;
        $options->component = 'totara_plan';


        $comment = new comment($options);

        if ($count = $comment->count()) {
            $latestcomment = $comment->get_latest_comment();
            $tooltip = get_string('latestcommentby', 'totara_plan').' '.$latestcomment->firstname.' '.get_string('on', 'totara_plan').' '.userdate($latestcomment->ctimecreated).': '.format_string(substr($latestcomment->ccontent, 0, 50));
            $tooltip = format_string(strip_tags($tooltip));
            $commentclass = 'comments-icon-some';
        } else {
            $tooltip = get_string('nocomments', 'totara_plan');
            $commentclass = 'comments-icon-none';
        }
        return $OUTPUT->action_link(new moodle_url("/totara/plan/components/{$this->component}/view.php", array('id' => $this->plan->id, 'itemid' => $item->id), 'comments'),
            $count, null, array('class' => $commentclass, 'title' => $tooltip));
    }


    /**
     * Display status of list items
     *
     * @access protected
     * @param object $item
     * @return string
     */
    protected function display_list_item_status($item) {
        // If item already approved but not completed
        $approved = $this->is_item_approved($item->approved);
        $completed = $this->is_item_complete($item);
        $canapproveitems = $this->can_update_items() == DP_PERMISSION_APPROVE;

        if ($approved && !$completed) {
            return $this->display_duedate_highlight_info($item->duedate);
        } else if (!$approved) {
            return $this->display_approval($item, $canapproveitems);
        }

        return '';
    }


    /**
     * Display linked courses of linked items
     *
     * @param object $item
     * @return string
     */
    protected function display_list_item_linkedcourses($item) {
            global $OUTPUT;

            return $OUTPUT->container($item->linkedcourses, 'centertext');
    }

    /**
     * Display count of linked evidence
     *
     * @param object $item
     * @return string
     */
    protected function display_list_item_linkedevidence($item) {
        global $OUTPUT;
        $display = (isset($item->linkedevidence)) ? ($item->linkedevidence) : '';
        return $OUTPUT->container($display, 'centertext');
    }

    abstract protected function display_list_item_progress($item);
    abstract protected function display_list_item_actions($item);


    /**
     * Display item's name
     *
     * @access  public
     * @param   object  $item
     * @return  string
     */
    abstract public function display_item_name($item);


    /**
     * Return markup for javascript assignment picker
     *
     * @access  public
     * @return  string
     */
    public function display_picker() {
        global $OUTPUT;

        if (!$permission = $this->can_update_items()) {
            return '';
        }

        // Check for allow/approve permissions
        $canupdate = ($permission >= DP_PERMISSION_ALLOW ? 'true' : 'false');

        $add_button_text = get_string('add'.$this->component.'s', 'totara_plan');
        $html = html_writer::start_tag('div', array('class' => 'buttons plan-add-item-button-wrapper'));
        $html .= html_writer::start_tag('div', array('class' => 'singlebutton dp-plan-assign-button'));
        $js = html_writer::script('var plan_id = ' . $this->plan->id . '; var comp_update_allowed = ' . $canupdate . ';');
        $html .= html_writer::start_tag('div') . $js;
        $html .= $OUTPUT->single_submit($add_button_text, array('id' => 'show-'.$this->component.'-dialog', 'class' => 'plan-add-item-button'));
        $html .= html_writer::end_tag('div');
        $html .= html_writer::end_tag('div');
        $html .= html_writer::end_tag('div');
        return $html;
    }


    /**
     * Display due date for an item
     *
     * @param int $itemid
     * @param int $duedate
     * @return string
     */
    function display_duedate($itemid, $duedate) {
        $baddates = explode(',',optional_param('badduedates', null, PARAM_TEXT));

        $plancompleted = $this->plan->status == DP_PLAN_STATUS_COMPLETE;
        $cansetduedate = !$plancompleted && ($this->get_setting('setduedate') == DP_PERMISSION_ALLOW);

        $out = '';

        // only show a form if they have permission to change due dates
        if ($cansetduedate) {
            $class = in_array($itemid, $baddates) ? 'dp-plan-component-input-error' : '';
            $out .= $this->display_duedate_as_form($duedate, "duedate_{$this->component}[{$itemid}]", $class, $itemid);
        } else {
            $out .= $this->display_duedate_as_text($duedate);
        }

        return $out;

    }


    /**
     * Display duedate for an item as a form
     *
     * @param string $name
     * @param int $duedate
     * @param string $inputclass
     * @return string
     */
    function display_duedate_as_form($duedate, $name, $inputclass='', $itemid) {
        global $CFG;
        $duedatestr = !empty($duedate) ? userdate($duedate, get_string('datepickerlongyearphpuserdate', 'totara_core'), $CFG->timezone, false) : '';
        return html_writer::empty_tag('input',
            array('id' => $name, 'type' => "text", 'name' => $name, 'placeholder' => get_string('datepickerlongyearplaceholder', 'totara_core'),
                'value' => $duedatestr, 'size' => "8", 'maxlength' => "20", 'class' => $inputclass));
    }


    /**
     * Display duedate for an item as text
     *
     * @param int $duedate
     * @return string
     */
    function display_duedate_as_text($duedate) {
        global $CFG;
        if (!empty($duedate)) {
            return userdate($duedate, get_string('strftimedate'), $CFG->timezone, false);
        } else {
            return '';
        }
    }


    /**
     * Display duedate for an item with task info
     *
     * @param int $duedate
     * @return string
     */
    function display_duedate_highlight_info($duedate) {
        $out = '';
        $now = time();
        if (!empty($duedate)) {
            if (($duedate < $now) && ($now - $duedate < 60*60*24)) {
                $out .= html_writer::tag('span', get_string('duetoday', 'totara_plan'), array('class' => 'plan_highlight'));
            } else if ($duedate < $now) {
                $out .= html_writer::tag('span', get_string('overdue', 'totara_plan'), array('class' => 'plan_highlight'));
            } else if ($duedate - $now < 60*60*24*7) {
                $days = ceil(($duedate - $now)/(60*60*24));
                $out .= html_writer::tag('span', get_string('dueinxdays', 'totara_plan', $days), array('class' => 'plan_highlight'));
            }
        }
        return $out;
    }

    /**
     * Display priority as text or picker depending on permissions
     *
     * @access  public
     * @param   object  $item
     * @return  string
     */
    public function display_priority($item) {
        // Load priority values
        $priorityvalues = $this->get_priority_values();

        // Load permissions
        $plancompleted = $this->plan->is_complete();

        $cansetpriority = !$plancompleted && ($this->get_setting('setpriority') == DP_PERMISSION_ALLOW);
        $priorityenabled = $this->get_setting('prioritymode') != DP_PRIORITY_NONE;
        $priorityrequired = ($this->get_setting('prioritymode') == DP_PRIORITY_REQUIRED);
        $prioritydefaultid = $this->get_default_priority();
        $out = '';

        if (!$priorityenabled) {
            return $out;
        }

        if (!empty($item->priority)) {
            $priorityname = $priorityvalues[$item->priority]->name;
        } else {
            $priorityname = '';
        }

        if ($cansetpriority) {
            // show a pulldown menu of priority options
            $out .= $this->display_priority_picker("priorities_{$this->component}[{$item->id}]", $item->priority, $item->id, $priorityvalues, $prioritydefaultid, $priorityrequired);
        } else {
            // just display priority if no permissions to set it
            $out .= $this->display_priority_as_text($item->priority, $priorityname, $priorityvalues);
        }

        return $out;
    }


    /**
     * Display a selection field for picking a priority
     *
     * @param string $name
     * @param int $priorityid
     * @param int $itemid
     * @param array $priorityvalues
     * @param int $prioritydefaultid
     * @param boolean $priorityrequired
     * @return string
     */
    function display_priority_picker($name, $priorityid, $itemid, $priorityvalues, $prioritydefaultid, $priorityrequired=false) {

        if (!$priorityvalues) {
            return '';
        }
        $options = array();

        foreach ($priorityvalues as $id => $val) {
            $options[$id] = $val->name;

            if ($id == $prioritydefaultid) {
                $defaultchooseval = $id;
                $defaultchoose = $val->name;
            }
        }

        // only include 'none' option if priorities are optional
        $choose = ($priorityrequired) ? null : get_string('none', 'totara_plan');
        $chooseval = ($priorityrequired) ? null : 0;

        if ($priorityid) {
            $selected = $priorityid;
        } else {
            $selected = ($priorityrequired) ? $defaultchooseval : 0;
        }

        return html_writer::select($options, $name, $selected, array('choose' => $choose), array());
    }

    /**
     * Display a priority for an item as text
     *
     * @param int $priorityid
     * @param string $prioritynane
     * @param array $priorityvalues
     * @return string
     */
    function display_priority_as_text($priorityid, $priorityname, $priorityvalues) {

        // class (for styling priorities) is of the format:
        // priorityXofY
        // theme only defines styles up to DP_MAX_PRIORITY_OPTIONS so limit
        // the highest values set to this range
        if ($priorityid) {
            $class = 'priority' .
                min($priorityvalues[$priorityid]->sortorder, DP_MAX_PRIORITY_OPTIONS) .
                'of' .
                min(count($priorityvalues), DP_MAX_PRIORITY_OPTIONS);

            return html_writer::tag('span', $priorityname, array('class' => $class));
        } else {
            return ' ';
        }
    }


    /**
     * Display a link to the plan index page
     *
     * @return string
     */
    function display_back_to_index_link() {

        global $OUTPUT;
        $url = new moodle_url('/totara/plan/component.php', array('id' => $this->plan->id, 'c' => $this->component));
        $link = $OUTPUT->action_link($url,
            get_string('backtoallx', 'totara_plan', get_string("{$this->component}plural", 'totara_plan')));

        return html_writer::tag('p', $link);
    }

    /**
     * Display approval functionality for a component assignment
     *
     * @param $obj stdClass the assignment object
     * @param $canapprove boolean if approve/decline actions are allowed
     * @return $out string an html string
     */
    function display_approval($obj, $canapprove) {
        global $OUTPUT;

        // Get data
        $id = $obj->id;
        $approvalstatus = $obj->approved;
        $murl = new moodle_url(qualified_me());
        $out = '';

        // If reviewing pending page, just returning picker
        if ($this->plan->reviewing_pending) {
            return $this->display_approval_options($obj, $approvalstatus);
        }

        switch($approvalstatus) {
        case DP_APPROVAL_DECLINED:
            $out .= html_writer::tag('span', get_string('declined', 'totara_plan'), array('class' => 'plan_highlight'));
            break;
        case DP_APPROVAL_UNAPPROVED:
            $out .= $OUTPUT->pix_icon('/learning_plan_alert', get_string('unapproved', 'totara_plan'), 'totara_plan');
            $out .= get_string('unapproved', 'totara_plan');
            if ($canapprove) {
                $out .= ' '.$this->display_approval_options($obj, $approvalstatus);
            }
            break;
        case DP_APPROVAL_REQUESTED:
            $out .= html_writer::tag('span', get_string('pendingapproval', 'totara_plan'), array('class' => 'plan_highlight'));
            $out .= html_writer::empty_tag('br');
            if ($canapprove) {
                $out .= ' '.$this->display_approval_options($obj, $approvalstatus);
            }
            break;
        case DP_APPROVAL_APPROVED:
            $out .= get_string('approved', 'totara_plan');
        }

        return $out;
    }

    /**
     * Display approval options for most components
     *
     * This method is overridded by competency subclass to show links instead
     *
     * @param stdClass $obj, The assignment object
     * @param integer $approvalstatus The currently selected approval status
     *
     * @return string The html for an approval picker
     */
    function display_approval_options($obj, $approvalstatus) {
        $name = "approve_{$this->component}[{$obj->id}]";

        $options = array(
            DP_APPROVAL_APPROVED => get_string('approve', 'totara_plan'),
            DP_APPROVAL_DECLINED => get_string('decline', 'totara_plan'),
        );

        return html_writer::select(
            $options,
            $name,
            $approvalstatus,
            array(0 => 'choose'),
            array('class' => 'approval')
        );
    }

    /**
     * Construct the link for the current user
     * @return string user link
     */
    function current_user_link() {
        global $USER, $OUTPUT;

        $userfrom_link = new moodle_url('/user/view.php', array('id' => $USER->id));
        $fromname = fullname($USER);
        return $OUTPUT->action_link($userfrom_link, $fromname, null, array('title' => '$fromname'));
    }

    /**
     * Return associative array mapping assignment IDs to item IDs
     *
     * Only for use with assigned components (courses, competencies), not objectives. Assumes
     * a table 'dp_plan_[component]_assign' with a field of '[component]id'
     *
     * @return array Array with assignment IDs as the key and item IDs as the value or false if there are none
     */
    function get_item_assignments() {
        global $DB;

        $component = $this->component;
        $table = "dp_plan_{$component}_assign";
        $field = "{$component}id";

        return $DB->get_records_menu($table, array('planid' => $this->plan->id), 'id', "id, $field");
    }

    /**
     * Override this function in component classes to return statistics
     * giving progress in that component
     *
     * @return mixed Object containing stats, or false if no progress stats available
     *
     * Object should contain the following properties:
     *    $progress->complete => Integer count of number of items completed
     *    $progress->total => Integer count of total number of items in this plan
     *    $progress->text => String description of completion (for use in tooltip)
     */
    public function progress_stats() {
        return false;
    }
}
