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
 * @author Jonathan Newman
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage totara_hierarchy
 */

/**
 * hierarchy/prefix/position/lib.php
 *
 * Library to construct position hierarchies
 */
require_once("{$CFG->dirroot}/totara/hierarchy/lib.php");
require_once("{$CFG->dirroot}/totara/core/utils.php");
require_once("{$CFG->dirroot}/completion/data_object.php");

define('POSITION_TYPE_PRIMARY',         1);
define('POSITION_TYPE_SECONDARY',       2);
define('POSITION_TYPE_ASPIRATIONAL',    3);

// List available position types
global $POSITION_TYPES;
$POSITION_TYPES = array(
    POSITION_TYPE_PRIMARY       => 'primary',
    POSITION_TYPE_SECONDARY     => 'secondary',
    POSITION_TYPE_ASPIRATIONAL  => 'aspirational'
);

global $POSITION_CODES;
$POSITION_CODES = array_flip($POSITION_TYPES);


/**
 * Oject that holds methods and attributes for position operations.
 * @abstract
 */
class position extends hierarchy {

    /**
     * The base table prefix for the class
     */
    var $prefix = 'position';
    var $shortprefix = 'pos';
    protected $extrafields = null;

    /**
     * Run any code before printing header
     * @param $page string Unique identifier for page
     * @return void
     */
    function hierarchy_page_setup($page = '', $item) {
        global $CFG, $USER, $PAGE;

        if ($page !== 'item/view') {
            return;
        }

        // Setup custom javascript
        require_once($CFG->dirroot.'/totara/core/js/lib/setup.php');

        // Setup lightbox
        local_js(array(
            TOTARA_JS_DIALOG,
            TOTARA_JS_TREEVIEW
        ));

        $args = array('args'=>'{"id":' . $item->id . ','
                             . '"frameworkid":' . $item->frameworkid . ','
                             . '"userid":' . $USER->id . ','
                             . '"sesskey":"' . sesskey() . '",'
                             . '"can_edit": true}');

        $PAGE->requires->strings_for_js(array('assigncompetencies', 'assigncompetencytemplate', 'assigngoals'), 'totara_hierarchy');

        // Include position user js modules.
        $jsmodule = array(
                'name' => 'totara_positionitem',
                'fullpath' => '/totara/core/js/position.item.js',
                'requires' => array('json'));
        $PAGE->requires->js_init_call('M.totara_positionitem.init',
            $args, false, $jsmodule);
    }

    /**
     * Print any extra markup to display on the hierarchy view item page
     * @param $item object Position being viewed
     * @return void
     */
    function display_extra_view_info($item, $frameworkid=0) {
        global $CFG, $OUTPUT, $PAGE;

        require_once($CFG->dirroot . '/totara/hierarchy/prefix/goal/lib.php');

        $sitecontext = context_system::instance();
        $can_edit = has_capability('totara/hierarchy:updateposition', $sitecontext);
        $comptype = optional_param('comptype', 'competencies', PARAM_TEXT);

        // Spacing.
        echo html_writer::empty_tag('br');

        echo html_writer::start_tag('div', array('class' => "list-assignedcompetencies"));
        echo $OUTPUT->heading(get_string('assignedcompetencies', 'totara_hierarchy'));

        echo $this->print_comp_framework_picker($item->id, $frameworkid);

        if ($comptype == 'competencies') {
            // Display assigned competencies
            $items = $this->get_assigned_competencies($item, $frameworkid);
            $addurl = new moodle_url('/totara/hierarchy/prefix/position/assigncompetency/find.php', array('assignto' => $item->id));
            $displaytitle = 'assignedcompetencies';
        } elseif ($comptype == 'comptemplates') {
            // Display assigned competencies
            $items = $this->get_assigned_competency_templates($item, $frameworkid);
            $addurl = new moodle_url('/totara/hierarchy/prefix/position/assigncompetencytemplate/find.php', array('assignto' => $item->id));
            $displaytitle = 'assignedcompetencytemplates';
        }

        $renderer = $PAGE->get_renderer('totara_hierarchy');
        echo $renderer->print_hierarchy_items($frameworkid, $this->prefix, $this->shortprefix, $displaytitle, $addurl, $item->id, $items, $can_edit);
        echo html_writer::end_tag('div');

        // Spacing.
        echo html_writer::empty_tag('br');

        // Display all goals assigned to this item.
        if (totara_feature_visible('goals') && !is_ajax_request($_SERVER)) {
            $addgoalparam = array('assignto' => $item->id, 'assigntype' => GOAL_ASSIGNMENT_POSITION, 'sesskey' => sesskey());
            $addgoalurl = new moodle_url('/totara/hierarchy/prefix/goal/assign/find.php', $addgoalparam);
            echo html_writer::start_tag('div', array('class' => 'list-assigned-goals'));
            echo $OUTPUT->heading(get_string('goalsassigned', 'totara_hierarchy'));
            echo $renderer->print_assigned_goals($this->prefix, $this->shortprefix, $addgoalurl, $item->id);
            echo html_writer::end_tag('div');
        }
    }

    /**
     * Returns a list of competencies that are assigned to a position
     * @param $item object|int Position being viewed
     * @param $frameworkid int If set only return competencies for this framework
     * @param $excluded_ids array an optional set of ids of competencies to exclude
     * @return array List of assigned competencies
     */
    function get_assigned_competencies($item, $frameworkid=0, $excluded_ids=false) {
        global $DB;

        if (is_object($item)) {
            $itemid = $item->id;
        } else if (is_numeric($item)) {
            $itemid = $item;
        } else {
            return false;
        }

        $params = array($itemid);

        $sql = "SELECT
                    c.*,
                    cf.id AS fid,
                    cf.fullname AS framework,
                    ct.fullname AS type,
                    pc.id AS aid,
                    pc.linktype as linktype
                FROM
                    {pos_competencies} pc
                INNER JOIN
                    {comp} c
                 ON pc.competencyid = c.id
                INNER JOIN
                    {comp_framework} cf
                 ON c.frameworkid = cf.id
                LEFT JOIN
                    {comp_type} ct
                 ON c.typeid = ct.id
                WHERE
                    pc.templateid IS NULL
                AND pc.positionid = ?";

        if (!empty($frameworkid)) {
            $sql .= " AND c.frameworkid = ?";
            $params[] = $frameworkid;
        }
        $ids = null;
        if (is_array($excluded_ids) && !empty($excluded_ids)) {
            list($excluded_sql, $excluded_params) = $DB->get_in_or_equal($excluded_ids, SQL_PARAMS_QM, 'param', false);
            $sql .= " AND c.id {$excluded_sql}";
            $params = array_merge($params, $excluded_params);
        }

        $sql .= " ORDER BY c.fullname";

        return $DB->get_records_sql($sql, $params);
    }

   /**
    * get assigne competency templates for an item
    *
    * @param int|object $item
    * @param int $frameworkid
    */
    function get_assigned_competency_templates($item, $frameworkid=0) {
        global $DB;

        if (is_object($item)) {
            $itemid = $item->id;
        } elseif (is_numeric($item)) {
            $itemid = $item;
        }

        $params = array($itemid);

        $sql = "SELECT
                    c.*,
                    cf.id AS fid,
                    cf.fullname AS framework,
                    pc.id AS aid
                FROM
                    {pos_competencies} pc
                INNER JOIN
                    {comp_template} c
                 ON pc.templateid = c.id
                INNER JOIN
                    {comp_framework} cf
                 ON c.frameworkid = cf.id
                WHERE
                    pc.competencyid IS NULL
                AND pc.positionid = ?";

        if (!empty($frameworkid)) {
            $sql .= " AND c.frameworkid = ?";
            $params[] = $frameworkid;
        }

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Returns array of positions assigned to a user,
     * indexed by assignment type
     *
     * @param   $user   object  User object
     * @return  array
     */
    function get_user_positions($user) {
        global $DB;

        $sql =
            "
                SELECT
                    pa.type,
                    p.*
                FROM
                    {pos} p
                INNER JOIN
                    {pos_assignment} pa
                 ON p.id = pa.positionid
                WHERE
                    pa.userid = ?
                ORDER BY
                   pa.type ASC
            ";
        return $DB->get_records_sql($sql, array($user->id));
    }

    /**
     * Return markup for user's assigned positions picker
     *
     * @param   $user       object  User object
     * @param   $selected   int     Id of currently selected position
     * @return  $html
     */
    function user_positions_picker($user, $selected) {
        global $POSITION_TYPES;

        // Get user's positions
        $positions = $this->get_user_positions($user);

        if (!$positions || count($positions) < 2) {
            return '';
        }

        // Format options
        $options = array();
        foreach ($positions as $type => $pos) {
            $text = get_string('type'.$POSITION_TYPES[$type], 'totara_hierarchy').': '.$pos->fullname;
            $options[$pos->id] = $text;
        }

        return display_dialog_selector($options, $selected, 'simpleframeworkpicker');
    }


    /**
     * Delete all data associated with the positions
     *
     * This method is protected because it deletes the positions, but doesn't use transactions
     *
     * Use {@link hierarchy::delete_hierarchy_item()} to recursively delete an item and
     * all its children
     *
     * @param array $items Array of IDs to be deleted
     *
     * @return boolean True if items and associated data were successfully deleted
     */
    protected function _delete_hierarchy_items($items) {
        global $CFG, $DB;

        // First call the deleter for the parent class
        if (!parent::_delete_hierarchy_items($items)) {
            return false;
        }

        list($items_sql, $items_params) = $DB->get_in_or_equal($items);

        // delete all of the positions links to completencies
        $wheresql = "positionid {$items_sql}";
        if (!$DB->delete_records_select($this->shortprefix . "_competencies", $wheresql, $items_params)) {
            return false;
        }

        // Delete any relevant prog_pos_assignment
        if (!$DB->delete_records_select("prog_{$this->shortprefix}_assignment", $wheresql, $items_params)) {
            return false;
        }

        // delete any relevant position relations
        $wheresql = "id1 {$items_sql} OR id2 {$items_sql}";
        if (!$DB->delete_records_select($this->shortprefix . "_relations", $wheresql, array_merge($items_params, $items_params))) {
            return false;
        }

        // set position id to null in all these tables
        $db_data = array(
            $this->shortprefix.'_assignment' => 'positionid',
            $this->shortprefix.'_assignment_history' => 'positionid',
            hierarchy::get_short_prefix('competency').'_record' => 'positionid',
            'course_completions' => 'positionid',
        );

        foreach ($db_data as $table => $field) {
            $update_sql = "UPDATE {{$table}}
                           SET {$field} = NULL
                           WHERE {$field} {$items_sql}";

            if (!$DB->execute($update_sql, $items_params)) {
                return false;
            }
        }

        return true;

    }

    /**
     * prints the competency framework picker
     *
     * @param int $positionid
     * @param int $currentfw
     * @return object html for the picker
     */
    function print_comp_framework_picker($positionid, $currentfw) {
      global $CFG, $DB, $OUTPUT;

        require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/lib.php');

        $edit = optional_param('edit', 'off', PARAM_TEXT);

        $competency = new competency();
        $frameworks = $competency->get_frameworks();

        $assignedcounts = $DB->get_records_sql_menu("SELECT comp.frameworkid, COUNT(*)
                                                FROM {pos_competencies} poscomp
                                                INNER JOIN {comp} comp
                                                ON poscomp.competencyid=comp.id
                                                WHERE poscomp.positionid = ?
                                                GROUP BY comp.frameworkid", array($positionid));

        $out = '';

        $out .= html_writer::start_tag('div', array('class' => "frameworkpicker"));
        if (!empty($frameworks)) {
            $fwoptions = array();
            foreach ($frameworks as $fw) {
                $count = isset($assignedcounts[$fw->id]) ? $assignedcounts[$fw->id] : 0;
                $fwoptions[$fw->id] = $fw->fullname . " ({$count})";
            }
            $fwoptions = count($fwoptions) > 1 ? array(0 => get_string('all')) + $fwoptions : $fwoptions;
            $out .= html_writer::start_tag('div', array('class' => "hierarchyframeworkpicker"));

            $url = new moodle_url('/totara/hierarchy/item/view.php', array('id' => $positionid, 'edit' => $edit, 'prefix' => 'position'));
            $options = $fwoptions;
            $selected = $currentfw;
            $formid = 'switchframework';
            $out .= get_string('filterframework', 'totara_hierarchy') . $OUTPUT->single_select($url, 'framework', $options, $selected, null, $formid);

            $out .= html_writer::end_tag('div');
        } else {
            $out .= get_string('competencynoframeworks', 'totara_hierarchy');
        }
        $out .= html_writer::end_tag('div');

        return $out;
   }


    /**
     * Returns various stats about an item, used for listed what will be deleted
     *
     * @param integer $id ID of the item to get stats for
     * @return array Associative array containing stats
     */
    public function get_item_stats($id) {
        global $DB;

        if (!$data = parent::get_item_stats($id)) {
            return false;
        }

        // should always include at least one item (itself)
        if (!$children = $this->get_item_descendants($id)) {
            return false;
        }

        $ids = array_keys($children);

        list($idssql, $idsparams) = sql_sequence('positionid', $ids);
        // number of organisation assignment records
        $data['pos_assignment'] = $DB->count_records_select('pos_assignment', $idssql, $idsparams);

        // number of assigned competencies
        $data['assigned_comps'] = $DB->count_records_select('pos_competencies', $idssql, $idsparams);

        return $data;
    }


    /**
     * Given some stats about an item, return a formatted delete message
     *
     * @param array $stats Associative array of item stats
     * @return string Formatted delete message
     */
    public function output_delete_message($stats) {
        $message = parent::output_delete_message($stats);

        if ($stats['pos_assignment'] > 0) {
            $message .= get_string('positiondeleteincludexposassignments', 'totara_hierarchy', $stats['pos_assignment']) . html_writer::empty_tag('br');
        }

        if ($stats['assigned_comps'] > 0) {
            $message .= get_string('positiondeleteincludexlinkedcompetencies', 'totara_hierarchy', $stats['assigned_comps']). html_writer::empty_tag('br');
        }

        return $message;
    }

    /**
     * @param $posassignment
     * @param $POSITION_TYPES
     * @return string
     * @throws coding_exception
     */
    public static function position_label($posassignment) {
        global $POSITION_TYPES;

        $label = '';
        if ($posassignment->positionassignmentname) {
            $label .= $posassignment->positionname;
        } else {
            $label .= get_string('type' . $POSITION_TYPES[$posassignment->positiontype], 'totara_hierarchy');
        }
        if ($posassignment->positionname) {
            $label .= "($posassignment->positionname)";
            return $label;
        }
        return $label;
    }
}  // class


/**
 * Position assignments
 */
class position_assignment extends data_object {

    /**
     * DB Table
     * @var string $table
     */
    public $table = 'pos_assignment';

    /**
     * Array of required table fields, must start with 'id'.
     * @var array $required_fields
     */
    public $required_fields = array(
        'id',
        'userid',
        'type',
        'fullname',
        'shortname',
        'description',
        'positionid',
        'organisationid',
        'managerid',
        'appraiserid',
        'reportstoid',
        'timecreated',
        'timemodified',
        'usermodified',
        'timevalidfrom',
        'timevalidto'
    );

    /**
     * Array of text table fields.
     * @var array $text_fields
     */
    public $text_fields = array('fullname', 'description');

    public $optional_fields = array(
        'managerpath' => null,
    );

    /**
     * Unique fields to be used in where clauses
     * when the ID is not known
     *
     * @access  public
     * @var     array       $unique fields
    */
    public $unique_fields = array('userid', 'type');

    public $userid;
    public $type;
    public $fullname;
    public $shortname;
    public $description;
    public $positionid;
    public $organisationid;
    public $managerid;
    public $appraiserid;
    public $reportstoid;
    public $managerpath;
    public $timecreated;
    public $timemodified;
    public $usermodified;
    public $timevalidfrom;
    public $timevalidto;

    /**
     * Finds and returns a data_object instance based on params.
     * @static abstract
     *
     * @param array $params associative arrays varname => value
     * @return object data_object instance or false if none found.
     */
    public static function fetch($params) {
        global $DB;
        $position_assignment = self::fetch_helper('pos_assignment', __CLASS__, $params);
        // If a record has been returned, do basic sanity checking.
        if ($position_assignment) {
            // If there is a manager assigned, check manager is valid.
            if (!empty($position_assignment->managerid)) {
                $validmanager = $DB->get_field('user', 'deleted', array('id' => $position_assignment->managerid));
                if ($validmanager != 0) {
                    $position_assignment->managerid = null;
                    $position_assignment->reportstoid = null;
                    $position_assignment->managerpath = null;
                }
            }
            // If there is an appraiser assigned, check appraiser is valid.
            if (!empty($position_assignment->appraiserid)) {
                $validmanager = $DB->get_field('user', 'deleted', array('id' => $position_assignment->appraiserid));
                if ($validmanager != 0) {
                    $position_assignment->appraiserid = null;
                }
            }
        }
        return $position_assignment;
    }

    public function save($managerchanged = true) {
        global $USER, $DB;

        // Get time (expensive on vservers)
        $time = time();

        $this->timemodified = $time;
        $this->usermodified = $USER->id;

        if (!$this->fullname) {
            $this->fullname = '';
        }

        if (!$this->shortname) {
            $this->shortname = '';
        }

        if (!$this->positionid) {
            $this->positionid = null;
        }

        // If no manager set, reset reportstoid and managerpath
        if (!$this->managerid) {
            $this->managerid = null;
            $this->reportstoid = null;
            $this->managerpath = null;
        }

        if (!$this->appraiserid) {
            $this->appraiserid = null;
        }

        if (!$this->organisationid) {
            $this->organisationid = null;
        }

        if (!$this->reportstoid) {
            $this->reportstoid = null;
        }

        if (!$this->timevalidfrom) {
            $this->timevalidfrom = null;
        }

        if (!$this->timevalidto) {
            $this->timevalidto = null;
        }

        if ($managerchanged) {
            // now recalculate managerpath
            $manager_relations = $DB->get_records_menu('pos_assignment', array('type' => $this->type),
                'userid', 'userid,managerid');
            //Manager relation for this assignment's user is wrong so we have to fix it
            $manager_relations[$this->userid] = $this->managerid;
            $this->managerpath = '/' . implode(totara_get_lineage($manager_relations, $this->userid), '/');

            $newpath = $this->managerpath;

            // Update child items
            $length_sql = $DB->sql_length("'/{$this->userid}/'");
            $position_sql = $DB->sql_position("'/{$this->userid}/'", 'managerpath');
            $substr_sql = $DB->sql_substr('managerpath', "$position_sql + $length_sql");

            $managerpath = $DB->sql_concat("'{$newpath}/'", $substr_sql);
            $like = $DB->sql_like('managerpath', '?');
            $sql = "UPDATE {pos_assignment}
                SET managerpath = {$managerpath}
                WHERE type = ? AND $like";
            $params = array(
                $this->type,
                "%/{$this->userid}/%"
            );

            if (!$DB->execute($sql, $params)) {
                error_log('assign_user_position: Could not update manager path of child items in manager hierarchy');
                return false;
            }
        }

        // Check if updating or inserting new
        if ($this->id) {
            $this->update();
        }
        else {
            $this->timecreated = $time;
            $this->insert();
        }

        return true;
    }
}

/**
 * Setup Position links in navigation - called from navigationlib.php generate_user_settings()
 *
 * @param $courseid id of current course to obtain course context
 * @param $userid the id of the user - may be the current user or an admin viewing the profile of another user
 * @param $usersetting the navigation node we add the Positions links to
 * @return void
 */
function pos_add_nav_positions_links($courseid, $userid, $usersetting) {
    global $CFG, $USER, $POSITION_CODES, $POSITION_TYPES;

    $systemcontext   = context_system::instance();
    $usercontext = context_user::instance($userid);
    $coursecontext = context_course::instance($courseid);

    $canview = false;
    if (!empty($USER->id) && ($userid == $USER->id) && has_capability('totara/hierarchy:viewposition', $systemcontext)) {
        // Can view own profile.
        $canview = true;
    } else if (has_capability('moodle/user:viewdetails', $coursecontext)) {
        $canview = true;
    } else if (has_capability('moodle/user:viewdetails', $usercontext)) {
        $canview = true;
    }

    $positionsenabled = get_config('totara_hierarchy', 'positionsenabled');
    if ($canview && $positionsenabled) {
        $posbaseargs['user'] = $userid;

        $enabled_positions = explode(',', $positionsenabled);
        // Get default enabled position type.
        foreach ($POSITION_CODES as $ptype => $poscode) {
            if (in_array($poscode, $enabled_positions)) {
                $dtype = $ptype;
                break;
            }
        }
        $url = new moodle_url('/user/positions.php', array_merge($posbaseargs, array('type' => $dtype)));

        // Link to users Positions page.
        $positions = $usersetting->add(get_string('positions', 'totara_hierarchy'), null, navigation_node::TYPE_CONTAINER);

        foreach ($POSITION_TYPES as $pcode => $ptype) {
            if (in_array($pcode, $enabled_positions)) {
                $url = new moodle_url('/user/positions.php', array_merge($posbaseargs, array('type' => $ptype)));
                $positions->add(get_string('type' . $ptype, 'totara_hierarchy'), $url, navigation_node::TYPE_USER);
            }
        }
    }
}
/**
 * Calcuates if a user can edit a position assignment
 *
 * @param int $userid The user ID of the position being edited
 * @return bool True if a user is allowed to edit assignment
 */
function pos_can_edit_position_assignment($userid) {
    global $USER;

    $personalcontext = context_user::instance($userid);

    // can assign any user's position
    if (has_capability('totara/hierarchy:assignuserposition', context_system::instance())) {
        return true;
    }

    // can assign this particular user's position
    if (has_capability('totara/hierarchy:assignuserposition', $personalcontext)) {
        return true;
    }

    // editing own position and have capability to assign own position
    if ($USER->id == $userid && has_capability('totara/hierarchy:assignselfposition', context_system::instance())) {
        return true;
    }

    return false;
}

/**
 * Return the specified user's position and organisation ids, or 0 if not currently set
 *
 * @param integer $userid ID of the user to get the data for (defaults to current user)
 * @param integer $type Position type (primary, secondary, etc) to get data for
 *
 * @return array Associative array with positionid and organisationid keys
 */
function pos_get_current_position_data($userid = false, $type = POSITION_TYPE_PRIMARY) {
    global $USER;
    if ($userid === false) {
        $userid = $USER->id;
    }
    // Attempt to load user's position assignment
    $pa = new position_assignment(array('userid' => $userid, 'type' => $type));

    // If no position assignment present, set values to 0
    if (!$pa->id) {
        $positionid = 0;
        $organisationid = 0;
    } else {
        $positionid = $pa->positionid ? $pa->positionid : 0;
        $organisationid = $pa->organisationid ? $pa->organisationid : 0;
    }

    return array('positionid' => $positionid, 'organisationid' => $organisationid);

}

/**
 * Return the specified user's most primary position assignment
 *
 * @param integer $userid ID of the user to get the data for (defaults to current user)
 *
 * @return mixed position assignment object or false if none are available
 */
function pos_get_most_primary_position_assignment($userid = false) {
    global $USER;
    if ($userid === false) {
        $userid = $USER->id;
    }

    $positionassignments = get_position_assignments(false, $userid);

    if (is_array($positionassignments) && count($positionassignments) > 0) {
        $mostprimary = null;

        foreach ($positionassignments as $positionassignment) {
            if ($mostprimary === null || $positionassignment->positiontype < $mostprimary->positiontype) {
                $mostprimary = $positionassignment;
            }
        }
        return $mostprimary;
    } else {
        return false;
    }
}

/**
 * Return all of a user's position assignments
 *
 * @param bool $managerreqd If true then filter out any positions with no manager
 * @param integer $userid ID of the user to get the data for (defaults to current user)
 *
 * @return array array of position assignment objects (potentially empty)
 */
function get_position_assignments($managerreqd = false, $userid = false) {
    global $DB, $USER;

    if ($userid === false) {
        $userid = $USER->id;
    }

    $now = time();
    $sql = "SELECT pa.id as id, p.id as positionid, pa.fullname as positionassignmentname,
                       p.fullname as positionname, pa.managerid, pa.type as positiontype
                FROM {pos_assignment} pa
                LEFT JOIN {pos} p ON p.id = pa.positionid
                WHERE pa.userid = :userid
                  AND (pa.timevalidfrom is null OR pa.timevalidfrom <= :from)
                  AND (pa.timevalidto is null OR pa.timevalidto >= :to)
                ORDER BY pa.type ASC";

    $userposassignments = $DB->get_records_sql($sql, array('userid' => $userid, 'from' => $now, 'to' => $now));

    if (!$userposassignments) {
        return $userposassignments;
    }

    $validpossitionassignments = array();
    // Get any temporary manager.
    $tempmanager = totara_get_manager($userid, null, false, true);

    foreach ($userposassignments as $positionassignment) {
        // Return the position if a manager is not required, a manager is set for the position or a temp manager exists.
        if (!$managerreqd || $positionassignment->managerid !== null || $tempmanager) {
            $validpossitionassignments[$positionassignment->id] = $positionassignment;
        }
    }

    return $validpossitionassignments;
}