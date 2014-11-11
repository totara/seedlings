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
 * @author Jonathan Newman
 * @package totara
 * @subpackage totara_hierarchy
 */

/**
 * totara/hierarchy/lib.php
 *
 * Library to construct hierarchies such as competencies, positions, etc
 */

require_once(dirname(dirname(__FILE__)) . '/core/utils.php');
require_once(dirname(dirname(__FILE__)) . '/customfield/fieldlib.php');

/**
 * Toggles the use of shortnames in addition to fullnames in hierarchy
 * forms. When true, hierarchies will include a shortname field in the
 * framework, item and type forms.
 */
define('HIERARCHY_DISPLAY_SHORTNAMES', false);

/**
 * Export option codes
 *
 * Bitwise flags, so new ones should be double highest value
 */
define('HIERARCHY_EXPORT_EXCEL', 1);
define('HIERARCHY_EXPORT_CSV', 2);
define('HIERARCHY_EXPORT_ODS', 4);

global $HIERARCHY_EXPORT_OPTIONS;
$HIERARCHY_EXPORT_OPTIONS = array(
    'xls' => HIERARCHY_EXPORT_EXCEL,
    'csv' => HIERARCHY_EXPORT_CSV,
    'ods' => HIERARCHY_EXPORT_ODS,
);

/**
 * Hierarchy item adjacent peer
 *
 * References either the item above or below the current item
 */
define('HIERARCHY_ITEM_ABOVE', 1);
define('HIERARCHY_ITEM_BELOW', -1);

/**
* Serves hierarchy file type files. Required for M2 File API
*
* @param object $course
* @param object $cm
* @param object $context
* @param string $filearea
* @param array $args
* @param bool $forcedownload
* @param array $options
* @return bool false if file not found, does not return if found - just send the file
*/
function totara_hierarchy_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options=array()) {
    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/{$context->id}/totara_hierarchy/$filearea/$args[0]/$args[1]";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }
    // finally send the file
    send_stored_file($file, 86400, 0, true, $options); // download MUST be forced - security!
}

/**
 * Execute cron functions related to hierarchies. This naming convention is required for it to run
 */
function totara_hierarchy_cron() {
    global $CFG;

    require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/cron.php');
    require_once($CFG->dirroot.'/totara/hierarchy/prefix/goal/cron.php');

    competency_cron();
    if (!totara_feature_disabled('goals')) {
        goal_cron();
    }
}

/**
 * Execute the SQL to create a default competency scale
 *
 * @returns true
 */
function totara_hierarchy_install_default_comp_scale() {
    global $USER, $DB;
    $now = time();

    $todb = new stdClass;
    $todb->name = get_string('competencyscale', 'totara_hierarchy');
    $todb->description = '';
    $todb->usermodified = $USER->id;
    $todb->timemodified = $now;
    $scaleid = $DB->insert_record('comp_scale', $todb);

    $comp_scale_vals = array(
        array('name'=>get_string('competent', 'totara_hierarchy'), 'scaleid' => $scaleid, 'sortorder' => 1, 'usermodified' => $USER->id, 'timemodified' => $now, 'proficient' => 1),
        array('name'=>get_string('competentwithsupervision', 'totara_hierarchy'), 'scaleid' => $scaleid, 'sortorder' => 2, 'usermodified' => $USER->id, 'timemodified' => $now),
        array('name'=>get_string('notcompetent', 'totara_hierarchy'), 'scaleid' => $scaleid, 'sortorder' => 3, 'usermodified' => $USER->id, 'timemodified' => $now)
        );

    foreach ($comp_scale_vals as $svrow) {
        $todb = new stdClass;
        foreach ($svrow as $key => $val) {
            // Insert default competency scale values, if non-existent
            $todb->$key = $val;
        }
        $svid = $DB->insert_record('comp_scale_values', $todb);
    }

    unset($comp_scale_vals, $scaleid, $svid, $todb);

    return true;
}

class hierarchy_event_handler {

    /**
     * Handler function called when a user_deleted event is triggered
     * Placed here so we can use this function for all hierarchy types.
     *
     * @param \core\event\user_deleted $event    The user object for the deleted user.
     */
    public static function user_deleted(\core\event\user_deleted $event) {
        global $DB, $POSITION_TYPES;

        $userid = $event->objectid;

        // Remove any existing temporary manager records related to the deleted user.
        $DB->delete_records('temporary_manager', array('tempmanagerid' => $userid));
        $DB->delete_records('temporary_manager', array('userid' => $userid));

        // Check if the deleted user is any other users primary manager and update them appropriately.
        foreach ($POSITION_TYPES as $typeid => $typename) {
            $teammembers = totara_get_staff($userid, $typeid);
            if (!empty($teammembers)) {
                foreach ($teammembers as $member) {
                    $pa = new position_assignment(array('userid' => $member, 'type' => $typeid));
                    $pa->managerid = null;
                    $pa->reportstoid = null;
                    $pa->managerpath = null;
                    $pa->save(true);
                }
            }
        }

        // Remove the deleted user from any appraisal roles.
        $appsql = "UPDATE {pos_assignment} SET appraiserid = NULL WHERE appraiserid = :uid";
        $appparam = array('uid' => $userid);
        $DB->execute($appsql, $appparam);

        // Remove the deleted user's position assignments.
        $DB->delete_records('pos_assignment', array('userid' => $userid));
    }
}

/**
 * An abstract object that holds methods and attributes common to all hierarchy objects.
 * @abstract
 */
class hierarchy {

    /**
     * The base prefix for the hierarchy
     * @var string
     */
    var $prefix;

    /**
     * Shortened version of the base prefix for table names. In order to meet the
     * Oracle 30-character limit, this should be no more than 4 characters.
     * @var string
     */
    var $shortprefix;

    const PREFIX = '';
    const SHORT_PREFIX = '';

    /**
     * The current framework id
     * @var int
     */
    var $frameworkid;

    /**
     * Get a framework
     *
     * @param integer $id (optional) ID of the framework to return. If not set returns the default (first) framework
     * @param $showhidden (optional) When returning the default framework, exclude hidden frameworks
     *                               Note: if a hidden framework is specified by id it will still be returned
     * @param $noframeworkok boolean (optional) If false, throw an error if no ID is supplied and there is no default framework
     *                                          If true, the function returns false but no error is generated
     * @return object|false The framework object. On failure returns false or throws an error
     */
    function get_framework($id = 0, $showhidden = false, $noframeworkok = false) {
        global $DB;

        // If no framework id supplied, use first in sortorder
        if ($id == 0) {
            $visible_sql = $showhidden ? '' : ' WHERE visible = 1';
            $sql = "SELECT * FROM {{$this->shortprefix}_framework}
                {$visible_sql}
                ORDER BY sortorder ASC";
            if (!$framework = $DB->get_record_sql($sql, null, true)) {
                if ($noframeworkok) {
                    return false;
                } else {
                    print_error('noframeworks', 'totara_hierarchy');
                }
            }
        } else {
            if (!$framework = $DB->get_record($this->shortprefix.'_framework', array('id' => $id))) {
                print_error('frameworkdoesntexist', 'totara_hierarchy', '', $this->prefix);
            }
        }

        $this->frameworkid = $framework->id; // specify the framework id
        return $framework;
    }

    /**
     * Get type by id
     * @return object|array
     */
    function get_type_by_id($id) {
        global $DB;
        return $DB->get_record($this->shortprefix.'_type', array('id' => $id));
    }

    /**
     * Get framework
     * @param array $extra_data optional - specifying extra info to be fetched and returned
     * @param bool $showhidden optional - specifying whether or not to include hidden frameworks
     * @return array
     * @uses $CFG when extra_data specified
     */
    function get_frameworks($extra_data=array(), $showhidden=false) {
        global $DB;

        if (!count($extra_data) && !$showhidden) {
            return $DB->get_records($this->shortprefix.'_framework', array('visible' => '1'), 'sortorder, fullname');
        } else if (!count($extra_data)) {
            return $DB->get_records($this->shortprefix.'_framework', array(), 'sortorder, fullname');
        }

        $sql = "SELECT f.* ";
        if (isset($extra_data['depth_count'])) {
            $sql .= ",(SELECT COALESCE(MAX(depthlevel), 0) FROM {{$this->shortprefix}} item
                        WHERE item.frameworkid = f.id) AS depth_count ";
        }
        if (isset($extra_data['item_count'])) {
            $sql .= ",(SELECT COUNT(*) FROM {{$this->shortprefix}} ic
                        WHERE ic.frameworkid=f.id) AS item_count ";
        }
        $sql .= "FROM {{$this->shortprefix}_framework} f ";
        if (!$showhidden) {
            $sql .= "WHERE f.visible=1 ";
        }
        $sql .= "ORDER BY f.sortorder, f.fullname";

        return $DB->get_records_sql($sql);

    }

    /**
     * Get types for a hierarchy
     * @var array $extra_data optional - specifying extra info to be fetched and returned
     * @return array
     * @uses $CFG when extra_data specified
     */
    function get_types($extra_data=array()) {
        global $DB;

        if (!count($extra_data)) {
           return $DB->get_records($this->shortprefix.'_type', array(), 'fullname');
        }

        $sql = "SELECT c.* ";
        if (isset($extra_data['custom_field_count'])) {
            $sql .= ", (SELECT COUNT(*) FROM {{$this->shortprefix}_type_info_field} cif
                        WHERE cif.typeid = c.id) AS custom_field_count ";
        }
        if (isset($extra_data['item_count'])) {
            $sql .= ", (SELECT COUNT(*) FROM {{$this->shortprefix}} ic
                 WHERE ic.typeid = c.id) AS item_count";
        }
        $sql .= " FROM {{$this->shortprefix}_type} c
                  ORDER BY c.fullname";
        return $DB->get_records_sql($sql);
    }


    /**
     * Get types for a hierarchy
     * @return array
     */
    function get_types_list() {
        global $DB;
        return $DB->get_records_menu($this->shortprefix.'_type', array(), 'fullname', 'id,fullname');
    }

    /**
     * Remove all custom field data for the specified hierarchy item
     *
     * @param int $itemid the hierarchy item id
     * @return boolean true if removal successful
     */
    function delete_custom_field_data($itemid) {
        global $DB;

        return $DB->delete_records($this->shortprefix . '_type_info_data',
            array($this->prefix . 'id' => $itemid));
    }

    /**
     * Get custom fields for an item
     * @param int $itemid id of the item
     * @return array
     */
    function get_custom_fields($itemid) {
        global $DB;
        $prefix = $this->prefix;

        $sql = "SELECT c.*, f.datatype, f.hidden, f.fullname
                FROM {{$this->shortprefix}_type_info_data} c
                INNER JOIN {{$this->shortprefix}_type_info_field} f ON c.fieldid = f.id
                WHERE c.{$prefix}id = ?
                ORDER BY f.sortorder";

        $customfields = $DB->get_records_sql($sql, array($itemid));

        return $customfields;
    }

    /**
     * Get the hierarchy item
     * @var int $id the id to move
     * @return object|false
     */
    function get_item($id) {
        global $DB;
        return $DB->get_record($this->shortprefix, array('id' => $id));
    }

    /**
     * Get items in a framework
     * @return array
     */
    function get_items() {
        global $DB;
        return $DB->get_records($this->shortprefix, array('frameworkid' => $this->frameworkid), 'sortthread, fullname');
    }

    /**
     * Static method to get all items.
     *
     * @param int $frameworkid
     * @return array
     */
    public static function get_framework_items($frameworkid = null) {
        global $DB;

        if (isset($frameworkid)) {
            return $DB->get_records(static::SHORT_PREFIX, array('frameworkid' => $frameworkid), 'sortthread, fullname');
        } else {
            return $DB->get_records(static::SHORT_PREFIX, array(), 'sortthread, fullname');
        }
    }

    /**
     * Get items in a framework by parent
     * @param int $parentid
     * @return array
     */
    function get_items_by_parent($parentid=false) {
        global $DB;
        if ($parentid) {
            // Parentid supplied, do not specify frameworkid as
            // sometimes it is not set correctly. And a parentid
            // is enough to get the right results
            return $DB->get_records_select($this->shortprefix, "parentid = ? AND visible = ?", array($parentid, 1), 'frameworkid, sortthread, fullname');
        }
        else {
            // If no parentid, grab the root node of this framework
            return $this->get_all_root_items();
        }
    }

    /*
     * Returns all items at the root level (parentid=0) for the current framework (obtained
     * from $this->frameworkid)
     * If no framework is specified, returns root items across all frameworks
     * This behaviour can also be forced by setting $all = true
     *
     * @param int $fwid Framework ID or null for all frameworks
     * @param boolean $all If true return root items for all frameworks even if $this->frameworkid is set
     * @return array
     */
    function get_all_root_items($all=false) {
        global $DB;
        if (empty($this->frameworkid) || $all) {
            // all root level items across frameworks
            return $DB->get_records_select($this->shortprefix, "parentid = ? AND visible = ?", array(0, 1), 'frameworkid, sortthread, fullname');
        } else {
            // root level items for current framework only
            $fwid = $this->frameworkid;
            return $DB->get_records_select($this->shortprefix, "parentid = ? AND frameworkid = ? AND visible = ?", array(0, $fwid, 1), 'sortthread, fullname');
        }
    }

    /**
     * Get descendants of an item
     * @param int $id
     * @return array
     */
    function get_item_descendants($id) {
        global $DB;
        $path = $DB->get_field($this->shortprefix, 'path', array('id' => $id));
        if ($path) {
            // the WHERE clause must be like this to avoid /1% matching /10
            $sql = "SELECT id, fullname, parentid, path, sortthread
                    FROM {{$this->shortprefix}}
                    WHERE path = ? OR " . $DB->sql_like('path', '?') . "
                    ORDER BY path";
            return $DB->get_records_sql($sql, array($path, "{$path}/%"));
        } else {
            print_error('nopathfoundforid', 'totara_hierarchy', '', (object)array('prefix' => $this->prefix, 'id' => $id));
        }
    }

    /**
     * Given an item id, returns the adjacent item at the same depth level
     * @param object $item An item object to find the peer for. Must include id,
     *                     frameworkid, sortthread, parentid and depthlevel
     * @param integer $direction Which direction to look for a peer, relative to $item.
     *                           Use constant HIERARCHY_ITEM_ABOVE or HIERARCHY_ITEM_BELOW
     * @return int|false Returns the ID of the peer or false if there isn't one
     *                   in the direction specified
     **/
    function get_hierarchy_item_adjacent_peer($item, $direction = HIERARCHY_ITEM_ABOVE) {
        global $DB;
        // check that item has required properties
        if (!isset($item->depthlevel) ||
            !isset($item->sortthread) || !isset($item->id)) {
            return false;
        }
        // try and use item's fwid if not set by hierarchy
        if (isset($this->frameworkid)) {
            $frameworkid = $this->frameworkid;
        } else if (isset($item->frameworkid)) {
            $frameworkid = $item->frameworkid;
        } else {
            return false;
        }

        $depthlevel = $item->depthlevel;
        $sortthread = $item->sortthread;
        $parentid = $item->parentid;
        $id = $item->id;

        // are we looking above or below for a peer?
        $sqlop = ($direction == HIERARCHY_ITEM_ABOVE) ? '<' : '>';
        $sqlsort = ($direction == HIERARCHY_ITEM_ABOVE) ? 'DESC' : 'ASC';

        $params = array(
            'frameworkid'   =>  $frameworkid,
            'depthlevel'    =>  $depthlevel,
            'parentid'      =>  $parentid,
            'sortthread'    =>  $sortthread,
        );

        $sql = "SELECT id FROM {{$this->shortprefix}}
            WHERE frameworkid = :frameworkid AND
            depthlevel = :depthlevel AND
            parentid = :parentid AND
            sortthread $sqlop :sortthread
            ORDER BY sortthread $sqlsort";
        // only return first match
        $dest = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);
        if ($dest) {
            return $dest->id;
        } else {
            // no peer in that direction
            return false;
        }
    }

    /**
     * Returns an object that can be used to
     * build a select form element based on the hierarchy
     *
     * Called recursively to get full hierarchy
     * @param array &$list Array used to build and return results. Passed by reference
     * @param integer $id ID of node to start from or null for all
     * @param boolean $showchildren If true select will include an additional option to
     *                          include item and all its children
     * @param boolean $shortname If true use shortname in select, otherwise fullname
     * @param string $path Current path for select, used recursively
     * @param array $records Records to be passed as function is recursively called. Generated the first
     *                       time it is called so no need to set this. Used to save db calls
     * @return Nothing returned, output obtained via reference to &$list
     */
    function make_hierarchy_list(&$list, $id = NULL, $showchildren=false, $shortname=false, $path = "", $records=null) {
        global $DB;
        // initialize the array if needed
        if (!is_array($list)) {
            $list = array();
        }
        if (empty($id)) {
            // start at top level
            $id = 0;
        }

        if (empty($records)) {
            // must be first time through function, get the records, and pass to
            // future uses to save db calls
            $records = $DB->get_records($this->shortprefix, array('visible' => '1'), 'path', 'id,fullname,shortname,parentid,sortthread,path');
        }

        if ($id == 0) {
            $children = $this->get_all_root_items(true);
        } else {
            $item = $records[$id];
            $name = ($shortname) ? $item->shortname : $item->fullname ;
            if ($path) {
                $path = $path.' / '.$name;
            } else {
                $path = $name;
            }
            // add item
            $list[$item->id] = $path;
            if ($showchildren === true) {
                // if children wanted and there are some
                // show a second option with children
                // does the same as:
                //$descendants = $this->get_item_descendants($id);
                // but without the db calls
                $descendants = array();
                foreach ($records as $key => $record) {
                    if (substr($record->path, 0, strlen($item->path . '/')) == $item->path . '/') {
                        $descendants[$key] = $record;
                    }
                }
                if (count($descendants)>1) {
                    // add comma separated list of all children too
                    $idstr = implode(',',array_keys($descendants));
                    $idstr = $item->id.','.$idstr;
                    $list[$idstr] = $path." (and all children)";
                }
            }
            // does the same as:
            // $children = $this->get_items_by_parent($id);
            // but without the db calls
            $children = array();
            foreach ($records as $key => $record) {
                if ($record->parentid == $id) {
                    $children[$key] = $record;
                }
            }
        }

        // now deal with children of this item
        if ($children) {
            foreach ($children as $child) {
                $this->make_hierarchy_list($list, $child->id, $showchildren, $shortname, $path, $records);
            }
        }
    }
    /**
     * Get items in a lineage
     * @param int $id
     * @return array
     * NOTE: does not check that lineage items are in same framework
     *       as $id specified or as hierarchy object this method is called from
     */
    function get_item_lineage($id) {
        global $DB;
        $path = $DB->get_field($this->shortprefix, 'path', array('id' => $id));
        if ($path) {
            $paths = explode('/', substr($path, 1));
            list($ids_sql, $ids_params) = $DB->get_in_or_equal($paths);
            $sql = "SELECT o.id, o.fullname, o.parentid, o.depthlevel
                    FROM {{$this->shortprefix}} o
                    WHERE o.id $ids_sql";
            return $DB->get_records_sql($sql, $ids_params);
        } else {
            print_error('nopathfoundforid', 'totara_hierarchy', '', (object)array('prefix' => $this->prefix, 'id' => $id));
        }
    }

    /**
    * Get editing button
    * @param signed int edit - is editing on or off?
    * @return button or ''
    */
    function get_editing_button($edit=-1, $options=array()) {
        global $USER, $OUTPUT;
        if ($edit !== -1) {
            $USER->{$this->prefix.'editing'} = $edit;
        }
        // Work out the appropriate action.
        if (empty($USER->{$this->prefix.'editing'})) {
            $label = get_string('turneditingon');
            $edit = 'on';
        } else {
            $label = get_string('turneditingoff');
            $edit = 'off';
        }

        // Generate the button HTML.
        $options['edit'] = $edit;
        $options['prefix'] = $this->prefix;
        return $OUTPUT->single_button(new moodle_url(qualified_me(), $options), $label, 'get');
    }

    /**
     * Display pulldown menu of frameworks
     * @param string $page Page to redirect to
     * @param boolean $simple optional Display simple selector
     * @param boolean $return optional Return instead of print
     * @param boolean $showhidden optional Include hidden frameworks in picker
     * @return boolean success
     */
    function display_framework_selector($page = 'index.php', $simple = false, $return = false, $showhidden = false) {
        global $OUTPUT;

        $frameworks = $this->get_frameworks(array(), $showhidden);

        if (count($frameworks) <= 1) {
            return;
        }

        if (!$simple) {

            $fwoptions = array();

            echo html_writer::start_tag('div', array('class' => 'frameworkpicker'));

            foreach ($frameworks as $fw) {
                $fwoptions[$fw->id] = $fw->fullname;
            }

            $select = new single_select(new moodle_url($page, array('prefix' => $this->prefix, 'frameworkid' => '')), 'frameworkid', $fwoptions, $this->frameworkid);
            $select->label = get_string('switchframework', 'totara_hierarchy');
            $select->formid = 'switchframework';

            echo $OUTPUT->single_select($select);
            echo html_writer::end_tag('div');

        }
        else {

            $options = array();
            foreach ($frameworks as $fw) {
                $options[$fw->id] = $fw->fullname;
            }

            $markup = display_dialog_selector($options, $this->frameworkid, 'simpleframeworkpicker');
            if ($return) {
                return $markup;
            }

            echo $markup;
        }
    }

    /**
     * Display add item button
     * @return boolean success
     */
    function display_add_item_button($page=0) {
        global $OUTPUT;
        $options = array('prefix' => $this->prefix, 'frameworkid' => $this->frameworkid, 'page' => $page);
        $url = new moodle_url('/totara/hierarchy/item/edit.php', $options);
        return $OUTPUT->single_button($url, get_string('addnew'.$this->prefix, 'totara_hierarchy'), 'get');
    }

    /**
     * Display add mulitple items button
     * @return boolean success
     */
    function display_add_multiple_items_button($page=0) {
        global $OUTPUT;
        $options = array('prefix' => $this->prefix, 'frameworkid' => $this->frameworkid, 'page' => $page);
        $url = new moodle_url('/totara/hierarchy/item/bulkadd.php', $options);
        echo $OUTPUT->single_button($url, get_string('addmultiplenew'.$this->prefix, $this->prefix), 'get');
    }

    /**
     * Displays a set of action buttons
     */
    function display_action_buttons($can_add_item, $page=0) {
        global $OUTPUT;
        $out = '';

        $out .= $OUTPUT->container_start(null, 'hierarchy-buttons');

        // Add buttons
        if ($can_add_item) {
            $out .= $this->display_add_item_button($page);
        }
        $out .= $OUTPUT->container_end();

        return $out;
    }

    /**
     * Displays a bulk actions selector
     */
    function display_bulk_actions_picker($can_add_item, $can_edit_item, $can_delete_item, $can_manage_type, $page=0) {
        global $OUTPUT;
        $out = '';

        $options = array();
        if ($can_add_item) {
            $options['/totara/hierarchy/item/bulkadd.php?prefix='.$this->prefix.'&frameworkid='.$this->frameworkid.'&page='.$page] = get_string('add');
        }
        if ($can_delete_item) {
            $options['/totara/hierarchy/item/bulkactions.php?action=delete&prefix='.$this->prefix.'&frameworkid='.$this->frameworkid] = get_string('delete');
        }
        if ($can_edit_item) {
            $options['/totara/hierarchy/item/bulkactions.php?action=move&prefix='.$this->prefix.'&frameworkid='.$this->frameworkid] = get_string('move');
        }
        if ($can_manage_type) {
            $options['/totara/hierarchy/type/index.php?prefix='.$this->prefix.'#bulkreclassify'] = get_string('reclassifyitems' ,'totara_hierarchy');
        }

        if (count($options) > 0) {
            $out .= $OUTPUT->container_start('hierarchy-bulk-actions-picker');
            $select = new url_select($options, '', array('' => get_string('bulkactions', 'totara_hierarchy')));
            $select->class = 'bulkactions';
            $out .= $OUTPUT->render($select);
            $out .= $OUTPUT->container_end();
        }
        return $out;
    }

    /**
     * Display add type button
     * @return boolean success
     */
    function display_add_type_button($page=0) {
        global $OUTPUT;
        $options = array('prefix' => $this->prefix, 'frameworkid' => $this->frameworkid, 'page' => $page);
        $url = new moodle_url('/totara/hierarchy/type/edit.php', $options);
        echo $OUTPUT->single_button($url, get_string('addtype', 'totara_hierarchy'), 'get');
    }


    /**
     * Swap the order of two items
     *
     * The items must be in the same framework and have the same parent. The
     * children of any items will be moved with them
     *
     * This method will fail if no item exists in the direction specified. Use
     * {@link get_hierarchy_item_adjacent_peer()} to check first
     *
     * @var int - the item id to move
     * @param integer $swapwith Which item to swap with, relative the the item id given.
     *                          Use constant HIERARCHY_ITEM_ABOVE or HIERARCHY_ITEM_BELOW
     * @var boolean $up - Direction to move: up if true, down if false
     *
     * @return boolean True if the reordering succeeds
     */
    function reorder_hierarchy_item($id, $swapwith) {
        global $DB, $OUTPUT;
        if (!$source = $DB->get_record($this->shortprefix, array('id' => $id))) {
            return false;
        }

        // get nearest neighbour in direction of move
        $destid = $this->get_hierarchy_item_adjacent_peer($source, $swapwith);
        if (!$destid) {
            // source not a valid record or no peer in that direction
            echo $OUTPUT->notification(get_string('error:couldnotmoveitemnopeer','totara_hierarchy',$this->prefix));
            return false;
        }

        // update the sortthreads
        return $this->swap_item_sortthreads($id, $destid);
    }


    /**
     * Hide the hierarchy item
     * @var int - the item id to hide
     * @return void
     */
    function hide_item($id) {
        global $DB;
        $item = $this->get_item($id);
        if ($item) {
            $visible = 0;
            $DB->set_field($this->shortprefix, 'visible', $visible, array('id' => $item->id));
        }
    }

    /**
     * Show the hierarchy item
     * @var int - the item id to show
     * @return void
     */
    function show_item($id) {
        global $DB;
        $item = $this->get_item($id);
        if ($item) {
            $visible = 1;
            $DB->set_field($this->shortprefix, 'visible', $visible, array('id' => $item->id));
        }
    }

    /**
     * Hide the hierarchy item
     * @var int - the item id to hide
     * @return void
     */
    function hide_framework($id) {
        global $DB;
        $framework = $this->get_framework($id);
        if ($framework) {
            $visible = 0;
            $DB->set_field($this->shortprefix.'_framework', 'visible', $visible, array('id' => $id));
        }
    }

    /**
     * Show the hierarchy item
     * @var int - the item id to show
     * @return void
     */
    function show_framework($id) {
        global $DB;
        $framework = $this->get_framework($id);
        if ($framework) {
            $visible = 1;
            $DB->set_field($this->shortprefix.'_framework', 'visible', $visible, array('id' => $framework->id));
        }
    }

    /**
     * Get the sortorder range for the framework
     * @return boolean success
     */
    function get_framework_sortorder_offset() {
        global $DB;
        $max = $DB->get_record_sql("SELECT MAX(sortorder) AS max, 1 FROM {{$this->shortprefix}_framework}");
        return $max->max + 1000;
    }

    /**
     * Move the framework in the sortorder
     * @var int - the framework id to move
     * @var boolean $up - up if true, down if false
     * @return boolean success
     */
    function move_framework($id, $up) {
        global $DB;
        $move = NULL;
        $swap = NULL;
        $sortoffset = $this->get_framework_sortorder_offset();
        $move = $DB->get_record("{$this->shortprefix}_framework", array('id' => $id));

        if ($up) {
            $swap = $DB->get_record_sql(
                    "SELECT *
                    FROM {{$this->shortprefix}_framework}
                    WHERE sortorder < ?
                    ORDER BY sortorder DESC", array($move->sortorder), IGNORE_MULTIPLE
                    );
        } else {
            $swap = $DB->get_record_sql(
                    "SELECT *
                    FROM {{$this->shortprefix}_framework}
                    WHERE sortorder > ?
                    ORDER BY sortorder ASC", array($move->sortorder), IGNORE_MULTIPLE
                    );
        }

        if ($move && $swap) {
            $transaction = $DB->start_delegated_transaction();

            $DB->set_field($this->shortprefix.'_framework', 'sortorder', $sortoffset, array('id' => $swap->id));
            $DB->set_field($this->shortprefix.'_framework', 'sortorder', $swap->sortorder, array('id' => $move->id));
            $DB->set_field($this->shortprefix.'_framework', 'sortorder', $move->sortorder, array('id' => $swap->id));

            $transaction->allow_commit();
            return true;
        }
        return false;
    }


    /**
     * Delete a framework item and all its children and associated data
     *
     * The exact data being deleted will depend on what sort of hierarchy it is
     * See {@link _delete_hierarchy_items()} in the child class for details
     *
     * @param integer $id the item id to delete
     * @param boolean $triggerevent If true, this command will trigger a "{$prefix}_added" event handler
     *
     * @return boolean success or failure
     */
    public function delete_hierarchy_item($id, $triggerevent = true) {
        global $DB, $USER;

        if (!$DB->record_exists($this->shortprefix, array('id' => $id))) {
            return false;
        }

        // get array of items to delete (the item specified *and* all its children)
        $delete_list = $this->get_item_descendants($id);
        // make a copy for triggering events
        $deleted_list = $delete_list;

        // make sure we know the item's framework id
        $frameworkid = isset($this->frameworkid) ? $this->frameworkid :
            $DB->get_field($this->shortprefix, 'frameworkid', array('id' => $id));

            $transaction = $DB->start_delegated_transaction();

            // iterate through 1000 items at a time, because oracle can't use
            // more than 1000 items in an sql IN clause
            while ($delete_items = totara_pop_n($delete_list, 1000)) {
                $delete_ids = array_keys($delete_items);
                if (!$this->_delete_hierarchy_items($delete_ids)) {
                    return false;
                }
            }
            $transaction->allow_commit();

        // Raise an event for each item deleted to let other parts of the system know
        if ($triggerevent) {
            foreach ($deleted_list as $deleted_item) {

                $eventname = '\totara_hierarchy\event\\' . $this->prefix . '_deleted';
                $event = $eventname::create(
                    array(
                        'objectid' => $id,
                        'context' => context_system::instance(),
                        'userid' => $USER->id,
                    )
                );
                $event->trigger();
            }
        }

        return true;
    }


    /**
     * Delete all data associated with the framework items provided
     *
     * This method is protected because it deletes the items, but doesn't use transactions.
     * Use {@link hierarchy::delete_hierarchy_item()} to recursively delete an item and
     * all its children. This method is extended or overridden in the lib file for each
     * hierarchy prefix to remove specific data for that hierarchy prefix.
     *
     * @param array $items Array of IDs to be deleted
     *
     * @return boolean True if items and associated data were successfully deleted
     */
    protected function _delete_hierarchy_items($items) {
        global $DB;
        list($insql, $inparams) = $DB->get_in_or_equal($items);

        // delete the actual items
        $items_sql = 'id ' . $insql;
        $DB->delete_records_select($this->shortprefix, $items_sql, $inparams);

        // delete custom field data associated with the items
        $data_sql = $this->prefix . 'id ' . $insql;
        $DB->delete_records_select("{$this->shortprefix}_type_info_data", $data_sql, $inparams);

        return true;
    }


    /**
     * Delete a framework and its contents
     * @param boolean $triggerevent Whether the delete item event should be triggered or not
     * @return  boolean
     */
    function delete_framework($triggerevent = true) {
        global $DB;

        // Get all items in the framework
        $items = $this->get_items();

        $transaction = $DB->start_delegated_transaction();

        if ($items) {
            foreach ($items as $item) {
                // Delete all top level items (which also deletes their children), and their info data
                if ($item->parentid == 0) {
                    if (!$this->delete_hierarchy_item($item->id, $triggerevent)) {
                        return false;
                    }
                }
            }
        }
        // Finally delete this framework
        $DB->delete_records($this->shortprefix.'_framework', array('id' => $this->frameworkid));

        // Rewrite the sort order to account for the missing framework
        $sortorder = 1;
        $records = $DB->get_records_sql("SELECT id FROM {{$this->shortprefix}_framework} ORDER BY sortorder ASC");
        if (is_array($records)) {
            foreach ( $records as $rec ) {
                $DB->set_field("{$this->shortprefix}_framework", 'sortorder', $sortorder, array('id' => $rec->id));
                $sortorder++;
            }
        }
        $transaction->allow_commit();

        return true;
    }


    /**
     * Delete a type.
     *
     * @param int $id id of type to delete
     * @return mixed Boolean true if successful, false otherwise
     */
    function delete_type($id) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        // remove any custom fields data
        if (!$this->delete_type_metadata($id)) {
            return false;
        }
        // unassign this type from all items (set to unclassified)
        $sql = "UPDATE {{$this->shortprefix}}
        SET typeid = 0
            WHERE typeid = ?";
        $DB->execute($sql, array($id));

        // finally delete the type itself
        $DB->delete_records("{$this->shortprefix}_type", array('id' => $id));

        $transaction->allow_commit();
        return true;
    }


    /**
     * Delete the metadata associated with a type (separated into a
     * separate function so that it can be called when all types are deleted
     *
     * @param int $id id of type with metadata to delete
     * @return void
     */
    function delete_type_metadata($id) {
        global $DB;
        $result = true;
        // delete all custom field data using fields in this type
        if ($fields = $DB->get_records($this->shortprefix.'_type_info_field', array('typeid' => $id))) {
            $fields = array_keys($fields);
            list($in_sql, $in_params) = $DB->get_in_or_equal($fields);
            $result = $result && $DB->delete_records_select($this->shortprefix.'_type_info_data', "fieldid $in_sql", $in_params);
        }
        // Delete all info fields in a type
        $result = $result && $DB->delete_records($this->shortprefix.'_type_info_field', array('typeid' => $id));

        return $result;
    }


    /**
     * Run any code before printing admin page header
     * @param $page string Unique identifier for admin page
     * @return void
     */
    function hierarchy_page_setup($page, $item) {}

    /**
     * Print any extra markup to display on the hierarchy view item page
     * @param $item object Item being viewed
     * @return void
     */
    function display_extra_view_info($item) {}

    /**
     * Return hierarchy prefix specific data about an item
     *
     * The returned array should have the structure:
     * array(
     *  0 => array('title' => $title, 'value' => $value),
     *  1 => ...
     * )
     *
     * @param $item object Item being viewed
     * @param $cols array optional Array of columns and their raw data to be returned
     * @return array
     */
    function get_item_data($item, $cols = NULL) {

        // Cols to loop through
        if (!is_array($cols)) {
            $cols = array('fullname', 'shortname', 'idnumber', 'description');
        }

        // Data to return
        $data = array();

        foreach ($cols as $datatype) {
            if ($datatype == 'description') {
                $value = file_rewrite_pluginfile_urls($item->$datatype, 'pluginfile.php', context_system::instance()->id, 'totara_hierarchy', $this->shortprefix, $item->id);
            } else {
                $value = $item->$datatype;
            }
            $data[] = array(
                'title' => get_string($datatype.'view', 'totara_hierarchy'),
                'value' => $value
            );
        }

        // Item's type
        $itemtype = $this->get_type_by_id($item->typeid);
        $typename = ($itemtype) ? $itemtype->fullname : get_string('unclassified', 'totara_hierarchy');
        $data[] = array(
            'title' => get_string('type', 'totara_hierarchy'),
            'value' => $typename,
        );

        return $data;
    }

    /**
     * Return the deepest depth in this framework
     *
     * @return int|false
     */
    function get_max_depth() {
        global $DB;

        return $DB->get_field($this->shortprefix, 'MAX(depthlevel)', array('frameworkid' => $this->frameworkid));
    }

    /**
     * Get all items that are parents
     * (Use in hierarchy treeviews to know if an item is a parent of others, and
     * therefore has children)
     *
     * @return  array
     */
    function get_all_parents() {
        global $DB;

        $parents = $DB->get_records_sql(
            "
            SELECT DISTINCT
                parentid AS id
            FROM
                {{$this->shortprefix}}
            WHERE
                parentid != 0
            "
        );
        return $parents;
    }

    /**
     * Returns the short prefix for the given prefix name. Note that it will error
     * out if the prefixname is for a non-existent hierarchy prefix.
     *
     * @param string $prefixname
     * @return string
     */
    static function get_short_prefix($prefixname) {
        global $CFG;
        $cleanprefixname = clean_param($prefixname, PARAM_ALPHA);
        $libpath = $CFG->dirroot.'/totara/hierarchy/prefix/'.$cleanprefixname.'/lib.php';
        if (!file_exists($libpath)) {
            print_error('error:hierarchyprefixnotfound', 'totara_hierarchy', $cleanprefixname);
        }
        require_once($libpath);
        $instance = new $cleanprefixname();
        return $instance->shortprefix;
    }


    /**
     * Helper function for loading a hierarchy library and
     * return an instance
     *
     * @access  public
     * @param   $prefix   string  Hierarchy prefix
     * @return  $object Instance of the hierarchy prefix object
     */
    static function load_hierarchy($prefix) {
        global $CFG;

        // $prefix could be user input so sanitize
        $prefix = clean_param($prefix, PARAM_ALPHA);

        // Check file exists
        $libpath = $CFG->dirroot.'/totara/hierarchy/prefix/'.$prefix.'/lib.php';
        if (!file_exists($libpath)) {
            print_error('error:hierarchyprefixnotfound', 'totara_hierarchy', '', $prefix);
        }

        // Load library
        require_once $libpath;

        // Check class exists
        if (!class_exists($prefix)) {
            print_error('error:hierarchyprefixnotfound', 'totara_hierarchy', '', $prefix);
        }

        return new $prefix();
    }


    /**
     * Returns the html to print a row of the hierarchy index table
     *
     * @param object $record A hierarchy item record
     * @param boolean $include_custom_fields Whether to display custom field info too (optional)
     * @param boolean $indicate_depth Whether to indent to show the hierarchy or not (optional)
     * @param array $cfields Array of custom fields associated with this hierarchy (optional)
     * @param array $types Array of type information (optional)
     * @return string HTML to display the item as a row in the hierarchy index table
     */
    function display_hierarchy_item($record, $include_custom_fields=false, $indicate_depth=true, $cfields=array(), $types=array()) {
        global $OUTPUT, $CFG;

        // never indent more than 10 levels as we only have 10 levels of CSS styles
        // and the table format would break anyway if indented too much
        $itemdepth = ($indicate_depth) ? 'depth' . min(10, $record->depthlevel) : 'depth1';
        // @todo get based on item type or better still, don't use inline styles :-(
        $itemicon = $OUTPUT->pix_url('/i/item');
        $cssclass = !$record->visible ? 'dimmed' : '';
        $out = html_writer::start_tag('div', array('class' => 'hierarchyitem ' . $itemdepth));
        $out .= $OUTPUT->action_link(new moodle_url('/totara/hierarchy/item/view.php', array('prefix' => $this->prefix, 'id' => $record->id)), format_string($record->fullname), null, array('class' => $cssclass));
        if ($include_custom_fields) {
            $out .= html_writer::empty_tag('br');
            // Print description if available.
            if ($record->description) {
                $record->description = file_rewrite_pluginfile_urls($record->description, 'pluginfile.php', context_system::instance()->id, 'totara_hierarchy', $this->shortprefix, $record->id);
                $safetext = format_text($record->description, FORMAT_HTML);
                $out .= html_writer::tag('div', html_writer::tag('strong', get_string('description') . ': ') . $safetext, array('class' => 'itemdescription ' . $cssclass));
            }

            // Display any fields unique to this type of hierarchy.
            $out .= $this->display_additional_item_form_fields($record, $cssclass);

            // Print type, unless unclassified.
            if ($record->typeid !=0 && is_array($types) && array_key_exists($record->typeid, $types)) {
                $out .= html_writer::tag('div', html_writer::tag('strong', get_string('type', 'totara_hierarchy') . ': ') . format_string($types[$record->typeid]->fullname), array('class' => 'itemtype' . $cssclass));
            }

            $out .= $this->display_hierarchy_item_custom_field_data($record, $cfields);
        }
        $out .= html_writer::end_tag('div');
        return $out;
    }


    /**
     * Returns the HTML to display the action icons for a hierarchy item on the index
     *
     * @param object $record A hierarchy item record
     * @param boolean $canedit Edit and show/hide buttons only shown if user can edit
     * @param boolean $candelete Delete button only shown if user can delete
     * @param boolean $canmove Move button only shown if user can move (and edit)
     * @param string $extraparam Additional string to append to action URLs (optional)
     *
     * @return string HTML to display action icons
     */
    function display_hierarchy_item_actions($record, $canedit=true, $candelete=true, $canmove=true, $extraparams=array()) {
        global $OUTPUT;
        $buttons = array();
        $str_edit = get_string('edit');
        $str_hide = get_string('hide');
        $str_show = get_string('show');
        $str_moveup = get_string('moveup');
        $str_movedown = get_string('movedown');
        $str_delete = get_string('delete');
        $str_spacer = $OUTPUT->spacer(array('height' => 11, 'width' => 11));
        $prefix = $this->prefix;
        $params = array_merge(array('prefix' => $prefix, 'frameworkid' => $record->frameworkid, 'sesskey' => sesskey()), $extraparams);

        if ($canedit) {
            $buttons[] = $OUTPUT->action_icon(new moodle_url('item/edit.php', array_merge($params, array('id' => $record->id))),
                    new pix_icon('t/edit', $str_edit));

            if ($record->visible) {
                $buttons[] = $OUTPUT->action_icon(new moodle_url('index.php', array_merge($params, array('hide' => $record->id))),
                    new pix_icon('t/hide', $str_hide));
            } else {
                $buttons[] = $OUTPUT->action_icon(new moodle_url('index.php', array_merge($params, array('show' => $record->id))),
                    new pix_icon('t/show', $str_show));
            }

            if ($canmove) {
                if ($this->get_hierarchy_item_adjacent_peer($record, HIERARCHY_ITEM_ABOVE)) {
                    $buttons[] = $OUTPUT->action_icon(new moodle_url('index.php', array_merge($params, array('moveup' => $record->id))),
                            new pix_icon('t/up', $str_moveup));
                } else {
                    $buttons[] = $str_spacer;
                }
                if ($this->get_hierarchy_item_adjacent_peer($record, HIERARCHY_ITEM_BELOW)) {
                    $buttons[] = $OUTPUT->action_icon(new moodle_url('index.php', array_merge($params, array('movedown' => $record->id))),
                            new pix_icon('t/down', $str_movedown));
                } else {
                    $buttons[] = $str_spacer;
                }
            }
        }
        if ($candelete) {
            $buttons[] = $OUTPUT->action_icon(new moodle_url('item/delete.php', array_merge($params, array('id' => $record->id))),
                    new pix_icon('t/delete', $str_delete));
        }
        return implode($buttons, '');
    }

    /**
     * Return the HTML needed to display custom field information
     * @param object $record A hierarchy record containing item and custom field information
     *                       The record must contain the hierarchy item's typeid field and
     *                       also custom field data stored in fields called cf_[FIELDID]
     * @param array $cfields Array of custom fields associated with this hierarchy
     *                       Key is fieldid, value is custom field object
     *                       Used to determine which field to display for this item
     *
     * @return HTML to display the custom field data
     */
    function display_hierarchy_item_custom_field_data($record, $cfields) {
        global $CFG, $OUTPUT;
        $out = '';
        $cssclass = !$record->visible ? 'dimmed' : '';

        if (!is_array($cfields)) {
            return false;
        }

        foreach ($cfields as $cf) {
            $cf_type = "customfield_{$cf->datatype}";
            require_once($CFG->dirroot.'/totara/customfield/field/'.$cf->datatype.'/field.class.php');
            if ($record->typeid != $cf->typeid) {
                // custom field not in this item's type
                continue;
            }
            // don't display hidden fields
            if ($cf->hidden) {
                continue;
            }
            $data = "cf_{$cf->id}";
            $itemid = "cf_{$cf->id}_itemid";
            // only show if there's data
            if ($record->$data) {
                $safetext = format_text($record->$data, FORMAT_HTML);
                $item_data = call_user_func(array($cf_type, 'display_item_data'), $safetext, array('prefix' => $this->prefix, 'itemid' => $record->$itemid));
                $item_name = html_writer::tag('strong', format_string($cf->fullname) . ': ');
                $out .= $OUTPUT->container($item_name . $item_data, 'customfield ' . $cssclass);
            }
        }

        return $out;
    }

    /**
     * Returns names of any extra fields that may be contained in a hierarchy
     * @return array array of extra fields
     */
    function get_extrafields() {
        return $this->extrafields;
    }

    /**
     * Displays the specified extrafield
     * @param object $item hierarchy item record
     * @param string $extrafield name of the extrafield to display
     * @return string html to display the hierarchy item
     */
    function display_hierarchy_item_extrafield($item, $extrafield) {
        global $OUTPUT;
        return $OUTPUT->action_link(new moodle_url("item/view.php", array('prefix' => $this->prefix, 'id' => $item->id)), $item->$extrafield);
    }

    /**
     * Add several new items to a particular hierarchy parent
     *
     * The $items_to_add array must contain a set of objects that are suitable for
     * inserting into the hierarchy items table. Hierarchy related data (such as
     * depthlevel, parentid and path) will be added when the record is created
     *
     * @param integer $parentid ID of the item to append the new children to
     * @param array $items_to_add Array of objects suitable for inserting
     * @param integer $frameworkid ID of the framework to add the items to (optional if set in hierarchy object)
     * @deprecated boolean $escapeitems If true, the objects in the $items_to_add array will be escaped before being passed to
     *                            insert_record(). If passing data from a form that has already been escaped,
     *                            this should be set to false. If passing in a raw object from a get_records()
     *                            call, this should be true (the default)
     * @param boolean $triggerevent If true, this command will trigger a "{$prefix}_added" event handler for each new item
     */
    function add_multiple_hierarchy_items($parentid, $items_to_add, $frameworkid = null, $triggerevent = true) {
        global $USER, $DB;
        $now = time();

        // we need the framework id to be set
        if (!isset($frameworkid)) {
            // try and get it from current hierarchy
            if (isset($this->frameworkid)) {
                $frameworkid = $this->frameworkid;
            } else {
                return false;
            }
        }

        if (!is_array($items_to_add)) {
            // items must be an array of objects
            return false;
        }

        $transaction = $DB->start_delegated_transaction();

        $new_ids = array();
        foreach ($items_to_add as $item) {
            if (!isset($item->visible)) {
                // default to visible if not set
                $item->visible = 1;
            }

            // Format any fields unique to this type of hierarchy.
            $item = $this->process_additional_item_form_fields($item);

            $item->timemodified = $now;
            $item->usermodified = $USER->id;
            if ($newitem = $this->add_hierarchy_item($item, $parentid, $frameworkid, false, $triggerevent)) {
                $new_ids[] = $newitem->id;
            } else {
                // fail if any new items fail to be added
                return false;
            }
        }
        $transaction->allow_commit();

        // everything worked -return the IDs
        return $new_ids;

    }

    /**
     * Add a new hierarchy item to an existing framework
     *
     * Given an object to insert and a parent id, create a new hierarchy item
     * and attach it to the appropriate location in the hierarchy
     *
     * @param object $item The item to insert. This should contain all data describing the object *except*
     *                     the information related to its location in the hierarchy:
     *                      - depthlevel
     *                      - path
     *                      - frameworkid
     *                      - sortthread
     *                      - parentid
     *                      - timecreated
     * @param integer $parentid The ID of the parent to attach to, or 0 for top level
     * @param integer $frameworkid ID of the parent's framework (optional, unless parentid == 0)
     * @deprecated boolean $escapeitem If true, the $item object will be escaped before being passed to
     *                            insert_record(). If passing data from a form that has already been escaped,
     *                            this should be set to false. If passing in a raw object from a get_records()
     *                            call, this should be true (the default)
     * @param boolean $usetransaction If true this function will use transactions (optional, default: true)
     * @param boolean $triggerevent If true, this command will trigger a "{$prefix}_added" event handler
     *
     * @return object|false A copy of the new item, or false if it could not be added
     */
    function add_hierarchy_item($item, $parentid, $frameworkid = null, $usetransaction = true, $triggerevent = true, $removedesc = true) {
        global $DB, $USER;

        // figure out the framework if not provided
        if (!isset($frameworkid)) {
            // try and use hierarchy's frameworkid, if not look it up based on parent
            if (isset($this->frameworkid)) {
                $frameworkid = $this->frameworkid;
            } else if ($parentid != 0) {
                if (!$frameworkid = $DB->get_field($this->shortprefix, 'frameworkid', array('id' => $parentid))) {
                    // can't determine parent's framework
                    return false;
                }
            } else {
                // we can't work out the framework based on parentid for parentid=0
                return false;
            }
        }

        // calculate where the new item fits into the hierarchy
        // handle top level items differently
        if ($parentid == 0) {
            $depthlevel = 1;
            $parentpath = '';
        } else {
            // parent item must exist
            $parent = $DB->get_record($this->shortprefix, array('id' => $parentid));
            $depthlevel = $parent->depthlevel + 1;
            $parentpath = $parent->path;
        }

        // fail if can't successfully determine the sort position
        if (!$sortthread = $this->get_next_child_sortthread($parentid, $frameworkid)) {
            return false;
        }

        // set the hierarchy specific data for the new item
        $item->frameworkid = $frameworkid;
        $item->depthlevel = $depthlevel;
        $item->parentid = $parentid;
        $item->path = $parentpath; // we'll add the item's ID to the end of this later
        $item->timecreated = time();
        $item->sortthread = $sortthread;
        //set description to NULL, will be fixed in the post-insert html editor operations
        if ($removedesc) {
            $item->description = NULL;
        }
        if ($usetransaction) {
            $transaction = $DB->start_delegated_transaction();
        }
        $newid = $DB->insert_record($this->shortprefix, $item);

        // Can't set the full path till we know the id!
        $DB->set_field($this->shortprefix, 'path', $item->path . '/' . $newid, array('id' => $newid));

        // load the new item from the db
        $newitem = $DB->get_record($this->shortprefix, array('id' => $newid));

        if ($usetransaction) {
            $transaction->allow_commit();
        }

        // trigger an event if required
        if ($triggerevent) {
            $eventname = '\totara_hierarchy\event\\' . $this->prefix . '_added';
            $event = $eventname::create(
                array(
                    'objectid' => $newitem->id,
                    'context' => context_system::instance(),
                    'userid' => $USER->id,
                )
            );
            $event->trigger();
        }

        return $newitem;

    }


    /**
     * Update an existing hierarchy item
     *
     * This can include moving to a new location in the hierarchy or changing some of its data values.
     * This method will not update an item's custom field data
     *
     * @param integer $itemid ID of the item to update
     * @param object $newitem An object containing details to be updated
     *                        Only a parentid is required to update the items location, other data such as
     *                        depthlevel, sortthread, path, etc will be handled internally
     * @param boolean $usetransaction If true this function will use transactions (optional, default: true)
     * @param boolean $triggerevent If true, this command will trigger a "{$prefix}_added" event handler.
     * @param boolean $removedesc If true this sets the description field to null,
     *                            descriptions should be set by post-update editor operations
     *
     * @return object|false The updated item, or false if it could not be updated
     */
    function update_hierarchy_item($itemid, $newitem, $usetransaction = true, $triggerevent = true, $removedesc = true) {
        global $USER, $DB;

        // the itemid must be a valid item
        $olditem = $DB->get_record($this->shortprefix, array('id' => $itemid));

        if ($newitem->parentid != $olditem->parentid || $newitem->frameworkid != $olditem->frameworkid) {
            // The item is being moved - first update item without changing parent or framework, then move afterwards
            $oldparentid = $olditem->parentid;
            $newparentid = $newitem->parentid;
            $newitem->parentid = $oldparentid;

            $oldframeworkid = $olditem->frameworkid;
            $newframeworkid = $newitem->frameworkid;
            $newitem->frameworkid = $oldframeworkid;
        }

        //set description to NULL, will be fixed in the post-update html editor operations
        if ($removedesc) {
            $newitem->description = NULL;
        }

        $newitem->id = $itemid;
        $newitem->timemodified = empty($newitem->timemodified) ? time() : $newitem->timemodified;
        $newitem->usermodified = empty($USER->id) ? get_admin()->id : $USER->id;

        if ($usetransaction) {
            $transaction = $DB->start_delegated_transaction();
        }
        $DB->update_record($this->shortprefix, $newitem);

        if (isset($newparentid) || isset($newframeworkid)) {
            // item is also being moved
            // get a new copy of the updatd item from the db
            $updateditem = $DB->get_record($this->shortprefix, array('id' => $itemid));
            $newparentid = isset($newparentid) ? $newparentid : 0;  // top-level
            $newframeworkid = isset($newframeworkid) ? $newframeworkid : $updateditem->frameworkid;  // same framework
            // move it
            $this->move_hierarchy_item($updateditem, $newframeworkid, $newparentid);
        }
        // get a new copy of the updated item from the db
        $updateditem = $DB->get_record($this->shortprefix, array('id' => $itemid));

        if ($usetransaction) {
            $transaction->allow_commit();
        }

        // Raise an event to let other parts of the system know
        if ($triggerevent) {
                $eventname = '\totara_hierarchy\event\\' . $this->prefix . '_updated';
                $event = $eventname::create(
                    array(
                        'objectid' => $updateditem->id,
                        'context' => context_system::instance(),
                        'userid' => $USER->id,
                    )
                );
                $event->trigger();
        }

        return $updateditem;

    }


    /**
     * Move an item within a hierarchy framework
     *
     * Given an item and a new parent ID, attach the item as a child of the parent.
     * Any children of the original item will move with it. This script handles updating:
     * - parent ID of moved item
     * - path of moved item and all descendants
     * - depthlevel of moved item and all descendants
     * - sortthread of all moved items
     *
     * @param object $item The item to move
     * @param integer $newframeworkid ID of the framework to attach it to
     * @param integer $newparentid ID of the item to attach it to
     */
    function move_hierarchy_item($item, $newframeworkid, $newparentid) {
        global $DB;

        if (!is_object($item)) {
            return false;
        }

        if (!$DB->record_exists($this->shortprefix.'_framework', array('id' => $newframeworkid))) {
            return false;
        }

        if ($item->parentid == 0) {
            // create a 'fake' old parent item for items at the top level
            $oldparent = new stdClass();
            $oldparent->id = 0;
            $oldparent->path = '';
            $oldparent->depthlevel = 0;
        } else {
            $oldparent = $DB->get_record($this->shortprefix, array('id' => $item->parentid));
        }

        if ($newparentid == 0) {
            // create a 'fake' new parent item for attaching to the top level
            $newparent = new stdClass();
            $newparent->id = 0;
            $newparent->path = '';
            $newparent->depthlevel = 0;
            $newparent->frameworkid = $newframeworkid;
        } else {
            $newparent = $DB->get_record($this->shortprefix, array('id' => $newparentid));

            if ($this->is_child_of($newparent, $item->id) || empty($newparent)) {
                // you can't move an item into its own child
                return false;
            }
        }

        // Ensure the new parent exists in the specified new framework
        if (!empty($newparent->id) && $newparent->frameworkid != $newframeworkid) {
            return false;
        }

        if (!$newsortthread = $this->get_next_child_sortthread($newparentid, $newframeworkid)) {
            // unable to calculate the new sortthread
            return false;
        }
        $oldsortthread = $item->sortthread;

        $transaction = $DB->start_delegated_transaction();

        // update the sort thread
        $this->move_sortthread($oldsortthread, $newsortthread, $item->frameworkid);
        // update the depthlevel of the item and its descendants
        // figure out how much the level will change after move
        $depthdiff = ($newparent->depthlevel + 1) - $item->depthlevel;
        // update the depthlevel of all affected items
        // the WHERE clause must be like this to avoid /1% matching /10
        $params = array('depthdiff' => $depthdiff,
            'itempath'  => $item->path,
            'itempath2'  => "$item->path/%",
            'frameworkid' => $item->frameworkid);

        $sql = "UPDATE {{$this->shortprefix}}
            SET depthlevel = depthlevel + :depthdiff
            WHERE (path = :itempath OR
            " . $DB->sql_like('path', ':itempath2') . ")
            AND frameworkid = :frameworkid";

        $DB->execute($sql, $params);
        // update the path of the item and its descendants
        // we need to:
        // - remove the 'old parent' segment of the path from the beginning of the path
        // - add the 'new parent' segment of the path instead
        // - do this for all items that start with the item's path
        // unfortunately this is a bit messy to do in the SQL in a single statement
        // in a cross platform way...
        // the WHERE clause must be like this to avoid /1% matching /10
        $length_sql = $DB->sql_length("'$oldparent->path'");
        $substr_sql = $DB->sql_substr('path', "{$length_sql} + 1");
        $updatepath = $DB->sql_concat("'{$newparent->path}'", $substr_sql);

        $params = array(
            'itempath'   => $item->path,
            'itempath2'  => "$item->path/%",
            'frameworkid' => $item->frameworkid);

        $sql = "UPDATE {{$this->shortprefix}}
            SET path = $updatepath
            WHERE (path = :itempath OR
            " . $DB->sql_like('path', ':itempath2') . ")
            AND frameworkid = :frameworkid";

        $DB->execute($sql, $params);

        // finally, update the item's parent- and framework id
        $todb = new stdClass();
        $todb->id = $item->id;
        $todb->parentid = $newparentid;
        $todb->frameworkid = $newframeworkid;
        $DB->update_record($this->shortprefix, $todb);

        $transaction->allow_commit();

        return true;
    }

    /**
     * Return items from this hierarchy that aren't assigned to a type
     *
     * @param boolean $countonly If true, only return how many items are unclassified
     *
     * @return array|integer Array of items, or the number of items, or empty array on failure
     */
    function get_unclassified_items($countonly=false) {
        global $DB;

        $select = "typeid IS NULL OR typeid = 0";
        if ($countonly) {
            return $DB->count_records_select($this->shortprefix, $select);
        } else {
            return $DB->get_records_select($this->shortprefix, $select);
        }
    }


    /**
     * Return the HTML to display a framework search form
     *
     * To get placeholder text to appear include the following in the source page:
     *
     * require_once($CFG->dirroot.'/totara/core/js/lib/setup.php');
     * local_js(array(TOTARA_JS_PLACEHOLDER));
     *
     * @param string $query An existing query to populate the search box with
     * @param string $placeholdertext Placeholder text to appear when the box is empty (optional)
     *
     * @return string The HTML to print the form
     *
     */
    function display_search_box($query, $placeholdertext=null) {
        global $PAGE, $OUTPUT;

        if (empty($placeholdertext)) {
            $placeholdertext = get_string('search');
        }
        $hiddenfields = array('prefix' => $this->prefix, 'frameworkid' => $this->frameworkid);

        $renderer = $PAGE->get_renderer('totara_core');
        $out = $renderer->print_totara_search('', $hiddenfields, $placeholdertext, $query, 'hierarchy-search-form', 'hierarchy-search-text-field');
        return $out;
    }

    /**
     * Return the HTML to display a button for showing or hiding hierarchy item details
     *
     * @param integer $displaymode If the page is currently hiding custom fields (1) or showing them (0)
     * @param string $query Any active search query
     * @param integer $page Page number so we return to the same place
     *
     * @return string The HTML to display the button
     */
    function display_showhide_detail_button($displaymode, $query='', $page=0) {
        global $OUTPUT;
        $newdisplaymode = ($displaymode) ? 0 : 1;
        $buttontext = ($displaymode) ? get_string('showdetails', 'totara_hierarchy') : get_string('hidedetails', 'totara_hierarchy');
        $urlparams = array(
            'prefix' => $this->prefix,
            'frameworkid' => $this->frameworkid,
            'query' => $query,
            'page' => $page,
            'setdisplay' => $newdisplaymode,
        );
        $buttonparams = array(
            'class' => 'showhide-button'
        );
        return $OUTPUT->single_button(new moodle_url('index.php', $urlparams), $buttontext, 'get', $buttonparams);

    }


    /**
     * Override in child class to add extra form elements in the add/edit form for items of
     * a particular prefix
     */
    function add_additional_item_form_fields(&$mform) {
        return;
    }

    /**
     * Override this function in prefix lib.php to set the data in the add/edit form
     * for type specific fields when updating.
     *
     * @param $data object      Database record of the hierarchy item
     */
    function set_additional_item_form_fields($data) {
        return;
    }

    /**
     * Override this function in prefix lib.php to add validation for
     * type specific fields when submitting an edit/add form.
     *
     * @param $data object      The forms returned dataobject
     * @return array            An array containing any errors
     */
    function validate_additional_item_form_fields($data) {
        return array();
    }

    /**
     * Override this function in prefix lib.php to format additional description fields.
     *
     * @param $item object      This should be the data object returned by the array for formatting
     * @return object
     */
    function process_additional_item_form_fields($item) {
        return $item;
    }

    /**
     * Override this function in prefix lib.php to display additional description fields.
     *
     * @param $item object      This should be the formatted database record for the hierarchy item
     * @param $cssclass string  Extra css to be applied, at the moment only uses 'dimmed'
     * @return string
     */
    function display_additional_item_form_fields($item, $cssclass) {
        return '';
    }

    /** Prints select box and Export button to export current report.
     *
     * A select is shown if the global settings allow exporting in
     * multiple formats. If only one format specified, prints a button.
     * If no formats are set in global settings, no export options are shown
     *
     * for this to work page must contain:
     * if ($format!='') {$report->export_data($format);die;}
     * before header printed
     *
     * @return No return value but prints export select form
     */
    function export_select($baseurl=null) {
        global $CFG;
        require_once($CFG->dirroot.'/totara/hierarchy/export_form.php');
        if (empty($baseurl)) {
            $baseurl = qualified_me();
        }
        $export = new hierarchy_export_form($baseurl, null, 'post', '', array('class' => 'hierarchy-export-form'));
        $export->display();
    }

    /**
     * Exports the data from the current results, maintaining
     * sort order and active filters but removing pagination
     *
     * @param string $format Format for the export ods/csv/xls
     * @return No return but initiates save dialog
     */
    function export_data($format) {
        global $DB;

        $query = optional_param('query', '', PARAM_TEXT);
        $searchactive = (strlen(trim($query)) > 0);
        $framework   = $this->get_framework($this->frameworkid, true);
        $showcustomfields = ($framework->hidecustomfields != 1);
        $params = array();

        $select = "SELECT hierarchy.id, hierarchy.fullname as hierarchyname, type.fullname as typename, hierarchy.depthlevel";
        $count = "SELECT COUNT(hierarchy.id)";
        $from   = " FROM {{$this->shortprefix}} hierarchy LEFT JOIN {{$this->shortprefix}_type} type ON hierarchy.typeid = type.id";
        $where  = " WHERE frameworkid = ?";
        $order  = " ORDER BY sortthread";

        if ($searchactive) {
            $headings = array('hierarchyname' => get_string('name'), 'typename' => get_string('type','totara_hierarchy'));
        } else {
            $headings = array('typename' => get_string('type', 'totara_hierarchy'));
        }
        //Add custom field data to select only if customfields are being shown
        if ($showcustomfields) {
            if ($custom_fields = $DB->get_records($this->shortprefix.'_type_info_field')) {
                foreach ($custom_fields as $field) {
                    $headings["cf_{$field->datatype}_{$field->id}"] = $field->fullname;
                    $select .= ", cf_{$field->id}.data as cf_{$field->datatype}_{$field->id}";
                    $from .= " LEFT JOIN {{$this->shortprefix}_type_info_data} cf_{$field->id} ON hierarchy.id = cf_{$field->id}.{$this->prefix}id AND cf_{$field->id}.fieldid = ?";
                    $params[] = $field->id;
                }
            }
        }
        $params[] = $this->frameworkid;
        // If search is active add search conditions to query
        if ($searchactive) {
            // extract quoted strings from query
            $keywords = totara_search_parse_keywords($query);
            // match search terms against the following fields
            $dbfields = array('hierarchy.fullname', 'hierarchy.shortname', 'hierarchy.description', 'hierarchy.idnumber');
            // Make sure custom fields are being displayed before searching them
            if ($showcustomfields && is_array($custom_fields)) {
                foreach ($custom_fields as $cf) {
                    $dbfields[] = "cf_{$cf->id}.data";
                }
            }
            list($searchsql, $searchparams) = totara_search_get_keyword_where_clause($keywords, $dbfields);
            $where .= ' AND (' . $searchsql. ')';
            $params = array_merge($params, $searchparams);
        }

        $shortname = $this->prefix;
        $sql = $select.$from.$where.$order;

        $maxdepth = $DB->get_field_sql("SELECT max(depthlevel) FROM {{$this->shortprefix}} WHERE frameworkid = ?", array($this->frameworkid));

        // need to create flexible table object to get sort order
        // from session var
        $table = new flexible_table($shortname);

        switch($format) {
            case 'ods':
                $this->download_ods($headings, $sql, $params, $maxdepth, null, $searchactive);
            case 'xls':
                $this->download_xls($headings, $sql, $params, $maxdepth, null, $searchactive);
            case 'csv':
                $this->download_csv($headings, $sql, $params, $maxdepth, null, $searchactive);
        }
        die;
    }

    /** parse custom fields and call the appropriate display_item_data function on the class
     * @param string $fieldid name of field
     * @param string $fieldvalue data in field to be parsed
     * @param bool $isexport true or false
     * @return Returns the properly-parsed customfield data
     */
    function parse_customfield($fieldid, $fieldvalue, $isexport=false) {
        global $CFG;
        list($prefix, $datatype, $itemid) = explode('_', $fieldid);
        $cf_class = 'customfield_' . $datatype;
        $data = '';

        $classfilepath = $CFG->dirroot . '/totara/customfield/field/' . $datatype . '/field.class.php';
        if (file_exists($classfilepath)) {
            require_once($classfilepath);
            $data = call_user_func(array($cf_class, 'display_item_data'), $fieldvalue, array('prefix' => $this->prefix, 'itemid' => $itemid, 'isexport' => $isexport));
        }
        return $data;
    }

    /** Download current table in ODS format
     * @param array $fields Array of column headings
     * @param string $query SQL query to run to get results
     * @param integer $maxdepth Number of the deepest depth in this hierarchy
     * @param string $file path to the directory where the file will be saved
     * @return Returns the ODS file
     */
    function download_ods($fields, $query, $params, $maxdepth, $file=null, $searchactive=false) {
        global $CFG, $DB;
        require_once("$CFG->libdir/odslib.class.php");
        $shortname = $this->prefix;
        $filename = clean_filename($shortname.'_hierarchy.ods');

        if (!$file) {
            header("Content-Type: application/download\n");
            header("Content-Disposition: attachment; filename=$filename");
            header("Expires: 0");
            header("Cache-Control: must-revalidate,post-check=0,pre-check=0");
            header("Pragma: public");

            $workbook = new MoodleODSWorkbook($filename);
        } else {
            $workbook = new MoodleODSWorkbook($file, true);
        }

        $worksheet = array();

        $worksheet[0] = $workbook->add_worksheet('');
        $row = 0;
        $col = 0;

        if (!$searchactive) {
            for ($depth = 1 ; $depth <= $maxdepth ; $depth++) {
                $worksheet[0]->write($row, $col, get_string('depth', 'totara_hierarchy', $depth));
                $col++;
            }
        }

        foreach ($fields as $fieldid => $fieldname) {
            $worksheet[0]->write($row, $col, $fieldname);
            $col++;
        }
        $row++;

        $numfields = count($fields);

        // Use recordset to keep memory use down.
        $data = $DB->get_recordset_sql($query, $params);
        if ($data) {
            foreach ($data as $datarow) {
                $curcol = 0;
                if (!$searchactive) {
                    $curcol = $maxdepth;
                    $worksheet[0]->write($row, $datarow->depthlevel-1, htmlspecialchars_decode($datarow->hierarchyname));
                }
                foreach ($fields as $fieldid => $fieldname) {
                    if (strpos($fieldid, 'cf_') === 0) {
                        $data = $this->parse_customfield($fieldid, $datarow->$fieldid, true);
                        $worksheet[0]->write($row, $curcol++, htmlspecialchars_decode($data));
                    } else {
                        $worksheet[0]->write($row, $curcol++, htmlspecialchars_decode($datarow->$fieldid));
                    }
                }
                $row++;
            }
        }

        $workbook->close();
        if (!$file) {
            die;
        }
    }


    /** Download current table in XLS format
     * @param array $fields Array of column headings
     * @param string $query SQL query to run to get results
     * @param integer $maxdepth Number of the deepest depth in this hierarchy
     * @param string $file path to the directory where the file will be saved
     * @return Returns the Excel file
     */
    function download_xls($fields, $query, $params, $maxdepth, $file=null, $searchactive=false) {
        global $CFG, $DB;

        require_once("$CFG->libdir/excellib.class.php");

        $shortname = $this->prefix;
        $filename = clean_filename($shortname.'_report.xls');

        if (!$file) {
            header("Content-Type: application/download\n");
            header("Content-Disposition: attachment; filename=$filename");
            header("Expires: 0");
            header("Cache-Control: must-revalidate,post-check=0,pre-check=0");
            header("Pragma: public");

            $workbook = new MoodleExcelWorkbook($filename);
        } else {
            $workbook = new MoodleExcelWorkbook($file, 'Excel2007', true);
        }

        $worksheet = array();

        $worksheet[0] = $workbook->add_worksheet('');
        $row = 0;
        $col = 0;

        if (!$searchactive) {
            for ($depth = 1 ; $depth <= $maxdepth ; $depth++) {
                $worksheet[0]->write($row, $col, get_string('depth', 'totara_hierarchy', $depth));
                $col++;
            }
        }

        foreach ($fields as $fieldname) {
            $worksheet[0]->write($row, $col, $fieldname);
            $col++;
        }
        $row++;

        $numfields = count($fields);

        // Use recordset to keep memory use down.
        $data = $DB->get_recordset_sql($query, $params);
        if ($data) {
            foreach ($data as $datarow) {
                $curcol = 0;
                if (!$searchactive) {
                    $curcol = $maxdepth;
                    $worksheet[0]->write($row, $datarow->depthlevel-1, htmlspecialchars_decode($datarow->hierarchyname));
                }
                foreach ($fields as $fieldid => $fieldname) {
                    if (strpos($fieldid, 'cf_') === 0) {
                        $data = $this->parse_customfield($fieldid, $datarow->$fieldid, true);
                        $worksheet[0]->write($row, $curcol++, htmlspecialchars_decode($data));
                    } else {
                        $worksheet[0]->write($row, $curcol++, htmlspecialchars_decode($datarow->$fieldid));
                    }
                }
                $row++;
            }
        }

        $workbook->close();
        if (!$file) {
            die;
        }
    }


    /** Download current table in CSV format
     * @param array $fields Array of column headings
     * @param string $query SQL query to run to get results
     * @param integer $maxdepth Number of the deepest depth in this hierarchy
     * @return Returns the CSV file
     */
    function download_csv($fields, $query, $params, $maxdepth, $file=null, $searchactive=false) {
        global $DB;
        $shortname = $this->prefix;
        $filename = clean_filename($shortname.'_report.csv');
        $csv = '';

        if (!$file) {
            header("Content-Type: application/download\n");
            header("Content-Disposition: attachment; filename=$filename");
            header("Expires: 0");
            header("Cache-Control: must-revalidate,post-check=0,pre-check=0");
            header("Pragma: public");
        }

        $delimiter = get_string('listsep', 'langconfig');
        $encdelim  = '&#'.ord($delimiter).';';
        $row = array();

        if (!$searchactive) {
            $headers = array();
            for ($depth = 1 ; $depth <= $maxdepth ; $depth++) {
                $headers[] = get_string('depth', 'totara_hierarchy' ,$depth);
            }
        }

        foreach ($fields as $fieldname) {
            $headers[] = str_replace($delimiter, $encdelim, $fieldname);
        }

        $csv .= implode($delimiter, $headers)."\n";

        //Use recordset to keep memory use down
        $data = $DB->get_recordset_sql($query, $params);
        if ($data) {
            foreach ($data as $datarow) {
                $row = array();
                if (!$searchactive) {
                    $depthstring = str_repeat(',', $datarow->depthlevel-1);
                    $depthstring .= str_replace($delimiter, $encdelim, $datarow->hierarchyname);
                    $depthstring .= str_repeat(',', $maxdepth - ($datarow->depthlevel - 1));
                }
                foreach ($fields as $fieldid => $fieldname) {
                    if ($fieldid == 'hierarchyname' && !$searchactive) {
                        continue;
                    } else if ($datarow->$fieldid) {
                        if (strpos($fieldid, 'cf_') === 0) {
                            $data = $this->parse_customfield($fieldid, $datarow->$fieldid, true);
                            $row[] = htmlspecialchars_decode(str_replace($delimiter, $encdelim, $data));
                        } else {
                            $row[] = htmlspecialchars_decode(str_replace($delimiter, $encdelim, $datarow->$fieldid));
                        }
                    } else {
                        $row[] = '';
                    }
                }
                if (!$searchactive) {
                    $csv .= $depthstring . implode($delimiter, $row)."\n";
                } else {
                    $csv .= implode($delimiter, $row)."\n";
                }
            }
        }

        if ($file) {
            $fp = fopen ($file, "w");
            fwrite($fp, $csv);
            fclose($fp);
        } else {
            echo $csv;
            die;
        }
    }

    /**
     * Returns various stats about an item, used for listed what will be deleted
     *
     * Overridden in child classes to add more specific info
     *
     * @param integer $id ID of the item to get stats for
     * @return array Associative array containing stats
     */
    public function get_item_stats($id) {
        global $DB;

        // should always include at least one item (itself)
        if (!$children = $this->get_item_descendants($id)) {
            return false;
        }

        $data = array();

        $data['itemname'] = $children[$id]->fullname;

        // number of children (exclude item itself)
        $data['children'] = count($children) - 1;

        $ids = array_keys($children);

        // number of custom field data records
        list($datasql, $dataparams) = sql_sequence($this->prefix.'id', $ids);
        $data['cf_data'] = $DB->count_records_select($this->shortprefix .
            '_type_info_data', $datasql, $dataparams);

        return $data;
    }


    /**
     * Given some stats about an item, return a formatted delete message
     *
     * Overridden in child classes to add more specific info
     *
     * @param array $stats Associative array of item stats
     * @return string Formatted delete message
     */
    public function output_delete_message($stats) {
        $message = '';

        $a = new stdClass();
        $a->childcount = $stats['children'];
        $a->children_string = $stats['children'] == 1 ? get_string('child', 'totara_hierarchy') : get_string('children', 'totara_hierarchy');

        if (isset($stats['itemcount'])) {
            $langstr = $this->prefix . 'deletemulticheckwithchildren';
            $a->num = $stats['itemcount'];
        } else if ($stats['children'] > 0) {
            $langstr = $this->prefix . 'deletecheckwithchildren';
            $a->itemname = $stats['itemname'];
        } else {
            $langstr = $this->prefix . 'deletecheck11';
            $a = $stats['itemname'];
        }
        $message .= get_string($langstr, 'totara_hierarchy', $a) . html_writer::empty_tag('br');

        if ($stats['cf_data'] > 0) {
            $message .= get_string('deleteincludexcustomfields', 'totara_hierarchy', $stats['cf_data']) . html_writer::empty_tag('br');
        }

        return $message;
    }


    /**
     * Returns a delete message to prompt the user when deleting one or more items
     *
     * @param integer|array ID or array of IDs to be deleted
     *
     * @return string Human-readable delete prompt text for the items given
     */
    public function get_delete_message($ids) {
        if (is_array($ids)) {
            // aggregate stats for multiple items
            $itemstats = array();
            foreach ($ids as $id) {
                $itemstats[] = $this->get_item_stats($id);
            }
            foreach ($itemstats as $item) {
                foreach ($item as $key => $value) {
                    if ($key == 'itemname') {
                        if (isset($stats['itemcount'])) {
                            $stats['itemcount'] += 1;
                        } else {
                            $stats['itemcount'] = 1;
                        }
                    } else {
                        if (isset($stats[$key])) {
                            $stats[$key] += $value;
                        } else {
                            $stats[$key] = $value;
                        }
                    }
                }
            }
        } else {
            // stats for a single item
            $stats = $this->get_item_stats($ids);
        }

        // output the stats
        return $this->output_delete_message($stats);
    }


    /**
     * Returns a move message to prompt the user when moving one or more items
     *
     * @param integer|array ID or array of IDs to be deleted
     *
     * @return string Human-readable move prompt text for the items given
     */
    public function get_move_message($ids, $parentid) {
        global $DB;
        if (is_array($ids) && count($ids) != 1) {
            $itemstr = get_string($this->prefix.'plural', 'totara_hierarchy');
            $num = count($ids);
        } else {
            $itemstr = get_string($this->prefix, 'totara_hierarchy');
            $num = 1;
        }

        $parentname = ($parentid == 0) ? get_string('top', 'totara_hierarchy') :
            format_string($DB->get_field($this->shortprefix, 'fullname', array('id' => $parentid)));

        $a = new stdClass();
        $a->num = $num;
        $a->items = strtolower($itemstr);
        $a->parentname = $parentname;

        return get_string('confirmmoveitems', 'totara_hierarchy', $a);
    }


    /**
     * Returns a list of items where none of the items are children of any of the others
     *
     * In cases where $ids contains both a parent and a child, the parent is retained.
     *
     * This method will also remove any duplicate IDs
     *
     * @param array $ids Array of item IDs
     * @return array Array of item IDs
     */
    public function get_items_excluding_children($ids) {
        global $DB;
        $out = array();
        list($itemsselectsql, $itemsselectparam) = sql_sequence('id', $ids);
        $items = $DB->get_recordset_select($this->shortprefix, $itemsselectsql, $itemsselectparam, 'id', 'id,depthlevel,path');
        if ($items) {
            // group records by their depthlevel
            $items_by_depth = totara_group_records($items, 'depthlevel');
            // sort ascending
            ksort($items_by_depth);

            $firstdepth = true;
            foreach ($items_by_depth as $depth => $items) {
                foreach ($items as $item) {
                    // add all the first level items without further checks
                    if ($firstdepth && !in_array($item->id, $out)) {
                        $out[] = $item->id;
                    }

                    // exclude any duplicates, or items who's parents are already added
                    if (!$this->is_child_of($item, $out) && !in_array($item->id, $out)) {
                        $out[] = $item->id;
                    }
                }
               $firstdepth = false;
            }
        }
        sort($out);
        return $out;
    }

    /**
     * Returns true if $item is a child of any of the item IDs given
     *
     * @param object $item An item object (must contain a path property)
     * @param integer|array $ids ID or array of IDs to check against the item
     *
     * @return boolean True if $item is a child of any of $ids
     */
    public function is_child_of($item, $ids) {
        if (!isset($item->path)) {
            return false;
        }

        $ids = (is_array($ids)) ? $ids : array($ids);

        $parents = explode('/', substr($item->path, 1));

        // remove the items ID
        $itemid = array_pop($parents);

        foreach ($parents as $parent) {
            if (in_array($parent, $ids)) {
                return true;
            }
        }
        return false;

    }

    /**
     * Generate a list of possible parents as an associative array
     *
     * The output is suitable for creating a pulldown for moving an item to a new
     * parent
     *
     * @param array $items An array of items, as produced by {@link get_items()}
     * @param integer|array $selected The current item ID, or if in bulk move
     *                     the current selected items array (optional)
     *                     If provided then the pulldown will exclude items that
     *                     the item can't be moved to (e.g. its own children)
     * @param boolean $inctop If true include the 'top' level (optional - default true)
     * @return array Returns an associative array of item names keyed on ID
     *               or an empty array if no items found
     */
    public function get_parent_list($items, $selected = array(), $inctop = true) {

        $out = array();
        //if an integer has been sent, convert to an array
        if (!is_array($selected)) {
            $selected = ($selected) ? array(intval($selected)) : array();
        }

        if ($inctop) {
            // Add 'top' as the first option
            $out[0] = get_string('top', 'totara_hierarchy');
        }

        if (is_array($items)) {
            foreach ($items as $parent) {
                // An item cannot be its own parent and cannot be moved inside itself or one of its own children
                // what we have in $selected is an array of the ids of the parent nodes of selected branches
                // so we must exclude these parents and all their children
                foreach ($selected as $key => $selectedid) {
                    if (preg_match("@/$selectedid(/|$)@", $parent->path)) {
                        continue 2;
                    }
                }
                //add using same spacing style as the bulkitems->move available & selected multiselects
                $out[$parent->id] = str_repeat('&nbsp;', 4 * ($parent->depthlevel - 1)) . format_string($parent->fullname);
            }
        }

        return $out;
    }

    /**
     * Redirect old URLs to the correct page
     *
     * Prior to version 1.1, 'type' was used to reference the hierarchy subclass (e.g. 'competency', 'organisation')
     * This was changed to 'prefix' in 1.1 to be more consistent with usage in the class, and to free up 'type' to
     * refer to the type of item in a hierarchy.
     *
     * This method provides backward compatibility by looking for 'old' URLs and silently redirects them to the correct page
     *
     * It should be called at the top of any page which used to rely on ?type=[prefix] in the URL (after includes, but before
     * anything else)
     */
    static public function support_old_url_syntax() {
        $prefix = optional_param('prefix', null, PARAM_SAFEDIR);
        $type = optional_param('type', null, PARAM_SAFEDIR);

        // only redirect if type is set but prefix is not
        if (isset($type) && !isset($prefix)) {
            $murl = new moodle_url(qualified_me());
            $murl->remove_params('type');
            $murl->param('prefix', $type);

            $referrer = isset($_SERVER['HTTP_REFERRER']) ? $_SERVER['HTTP_REFERRER'] : 'none';
            error_log('Visit to ' . qualified_me() . ' redirected to ' . $murl->out() . ' referrer: ' . $referrer);

            redirect($murl->out());
        }
    }

    /*
     * Protected methods for manipulating item sortthreads
     */

    /**
     * Returns the next available sortthread for a new child of the item provided
     *
     * This will work for a parentid of 0 (e.g. new top level item), but the frameworkid
     * must be provided, either explicitly as the second argument or loaded into the hierarchy
     * via $this->get_framework()
     *
     * @param integer $parentid ID of the parent you want to create a new child for
     * @param integer $frameworkid ID of the parent's framework (optional, unless parentid == 0)
     *
     * @return string sortthread for a new child of $parentid or false if it couldn't be calculated
     *
     */
    protected function get_next_child_sortthread($parentid, $frameworkid = null) {
        global $DB;
        // figure out the framework if not provided
        if (!isset($frameworkid)) {
            // try and use hierarchy's frameworkid, if not look it up based on parent
            if (isset($this->frameworkid)) {
                $frameworkid = $this->frameworkid;
            } else if ($parentid != 0) {
                if (!$frameworkid = $DB->get_field($this->shortprefix, 'frameworkid', array('id' => $parentid))) {
                    // can't determine parent's framework
                    return false;
                }
            } else {
                // we can't work out the framework based on parentid for parentid=0
                return false;
            }
        }

        $maxthread = $DB->get_record_sql("
            SELECT MAX(sortthread) AS sortthread
            FROM {{$this->shortprefix}}
            WHERE parentid = ?
            AND frameworkid = ?", array($parentid, $frameworkid));
        if (!$maxthread || strlen($maxthread->sortthread) == 0) {
            if ($parentid == 0) {
                // first top level item
                return totara_int2vancode(1);
            } else {
                // parent has no children yet
                return $DB->get_field($this->shortprefix, 'sortthread', array('id' => $parentid, 'frameworkid' => $frameworkid)) . '.' . totara_int2vancode(1);
            }
        }
        return $this->increment_sortthread($maxthread->sortthread);

    }

    /**
     * Alter the sortthread of an item and all its children to point to a new location
     *
     * Required when moving or swapping hierarchy items
     *
     * As an example, given the items with sortthreads of:
     *
     * 1.2
     * 1.2.1
     * 1.2.1.1
     * 1.2.1.2
     * 1.2.2
     *
     * Running this:
     *
     * move_sortthread('1.2', '4.5.6', $fwid) would update them to:
     *
     * 4.5.6
     * 4.5.6.1
     * 4.5.6.1.1
     * 4.5.6.1.2
     * 4.5.6.2
     *
     * @param string $oldsortthread The sortthread of the item to move
     * @param string $newsortthread The new sortthread to apply to the item
     * @param integer $frameworkid The framework ID containing the items to move
     *
     * @return boolean True if the sortthreads were successfully updated
     */
    protected function move_sortthread($oldsortthread, $newsortthread, $frameworkid) {
        global $DB;

        $length_sql = $DB->sql_length("'$oldsortthread'");
        $substr_sql = $DB->sql_substr('sortthread', "$length_sql + 1");
        $sortthread = $DB->sql_concat(":newsortthread", $substr_sql);
        $params = array(
            'newsortthread' => $newsortthread,
            'frameworkid' => $frameworkid,
            'oldsortthread' => $oldsortthread,
            'oldsortthreadmatch' => "{$oldsortthread}%"
        );
        $sql = "UPDATE {{$this->shortprefix}}
            SET sortthread = $sortthread
            WHERE frameworkid = :frameworkid
            AND (sortthread = :oldsortthread OR
            " . $DB->sql_like('sortthread', ':oldsortthreadmatch') . ')';

        return $DB->execute($sql, $params);

    }


    /**
     * Swap the order of two hierarchy items (and all of their children)
     *
     * This is designed to swap two items with the same parent only, since no other changes
     * made to the structure of the hierarchy (e.g. depthlevel and parentid are unchanged).
     *
     * If you want to swap items at different levels, use {@link move_hierarchy_item()} instead
     *
     * Because of the way move_sortthread() is implemented, this method switches
     * one items sortthread to a temporary location. This is done with a transaction
     * to prevent data corruption - if the temporary state manages to get left over
     * then this function will stop functioning and return false
     *
     * @param integer $itemid1 The first item to swap
     * @param integer $itemid2 The second item to swap
     *
     * @return boolean True if sortthreads are successfully swapped
     */
    protected function swap_item_sortthreads($itemid1, $itemid2) {
        global $DB;

        // get the item details
        list($insql, $inparams) = $DB->get_in_or_equal(array($itemid1, $itemid2));
        $items = $DB->get_records_select($this->shortprefix, "id $insql", $inparams);

        // both items must exist
        if (!isset($items[$itemid1]) || !isset($items[$itemid2])) {
            return false;
        }

        // items must belong to the same framework and have the same parent
        if ($items[$itemid1]->frameworkid != $items[$itemid2]->frameworkid ||
            $items[$itemid1]->parentid != $items[$itemid2]->parentid) {
            return false;
        }

        $frameworkid = $items[$itemid1]->frameworkid;
        $sortthread1 = $items[$itemid1]->sortthread;
        $sortthread2 = $items[$itemid2]->sortthread;

        // this indicates that a swap failed half way through, which shouldn't happen
        // if transactions are used below
        if ($DB->record_exists_select($this->shortprefix,
            "frameworkid = ? AND " . $DB->sql_like('sortthread', '?'),
            array($frameworkid, '%swaptemp%'))) {

            return false;
        }

        $transaction = $DB->start_delegated_transaction();

        $status = true;
        // need an placeholder when moving things round
        $status = $status && $this->move_sortthread($sortthread1, 'swaptemp', $frameworkid);
        $status = $status && $this->move_sortthread($sortthread2, $sortthread1, $frameworkid);
        $status = $status && $this->move_sortthread('swaptemp', $sortthread2, $frameworkid);
        if (!$status) {
            throw new exception('Error when swapping sortthreads');
        }
        $transaction->allow_commit();

        return true;
    }


    /**
     * Increment the last section of a sortthread vancode
     *
     * Examples:
     * 01 -> 02
     * 01.01 -> 01.02
     * 04.03 -> 04.04
     * 01.02.03 -> 01.02.04
     *
     * @param string $sortthread The sort thread to increment
     * @param integer $inc Amount to increment by (default 1)
     *
     * @return boolean True if increment successful
     */
    protected function increment_sortthread($sortthread, $inc = 1) {
        if (!$lastdot = strrpos($sortthread, '.')) {
            // root level, just increment the whole thing
            return totara_increment_vancode($sortthread, $inc);
        }
        $start = substr($sortthread, 0, $lastdot + 1);
        $last = substr($sortthread, $lastdot + 1);

        // increment the last vancode in the sequence
        return $start . totara_increment_vancode($last, $inc);
    }


    /**
     * Method for correcting invalid sortthreads, within a framework
     * or across the whole hierarchy
     *
     * @param integer $frameworkid Optional frameworkid to specify a single
     * framework to be updated. If not given all frameworks will be updated
     *
     * @return true if the operation succeeded
     * @throws exception if a problem is encountered
     */
    public function fix_sortthreads($frameworkid = null) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        $params = array();
        if ($frameworkid) {
            $select = 'frameworkid = ?';
            $params[] = $frameworkid;
            $updatewhere = ' WHERE frameworkid = ?';
        } else {
            $select = '';
            $updatewhere = '';
        }

        // loop through all records, sorted by framework, then depthlevel, then sortthread (in order to
        // retain as much of the current arbitrary sortorder as possible)
        $rs = $DB->get_recordset_select($this->shortprefix, $select, $params, 'frameworkid, depthlevel, sortthread');

        // try to clear all existing sortthreads
        $sql = "UPDATE {{$this->shortprefix}} SET sortthread = null $updatewhere";
        $DB->execute($sql, $params);

        foreach ($rs as $datarow) {
            $todb = new stdClass();
            $todb->id = $datarow->id;
            $todb->sortthread = $this->get_next_child_sortthread($datarow->parentid, $datarow->frameworkid);
            $DB->update_record($this->shortprefix, $todb);
        }
        $rs->close();

        $transaction->allow_commit();
        return true;
    }

    /**
     * Returns a keyed array of hierarchy permissions. By default for the current user in the system context.
     *
     * The return value is of the form:
     *
     * array(
     *     'cancreateframeworks' => true,
     *     'canupdateframeworks' => false,
     *     ...
     * );
     *
     * the command:
     *
     * extract(hierarchy::get_permission());
     *
     * can be used to create named variables with each permission.
     *
     * @param context $context A context object to check in the context of (optional).
     * @param integer|stdClass $user A user id or object to check for (optional).
     *
     * @return array Array containing boolean values for each permission checked.
     */
    public function get_permissions($context = null, $user = null) {
        global $CFG;

        $prefix = $this->prefix;
        if (is_null($context)) {
            $context = context_system::instance();
        }
        $cancreateframeworks = has_capability('totara/hierarchy:create' . $prefix . 'frameworks', $context, $user);
        $canupdateframeworks = has_capability('totara/hierarchy:update' . $prefix . 'frameworks', $context, $user);
        $candeleteframeworks = has_capability('totara/hierarchy:delete' . $prefix . 'frameworks', $context, $user);
        $canmanageframeworks = $cancreateframeworks || $canupdateframeworks || $candeleteframeworks;
        $canviewframeworks = has_capability('totara/hierarchy:view' . $prefix . 'frameworks', $context, $user);

        $cancreateitems = has_capability('totara/hierarchy:create' . $prefix, $context, $user);
        $canupdateitems = has_capability('totara/hierarchy:update' . $prefix, $context, $user);
        $candeleteitems = has_capability('totara/hierarchy:delete' . $prefix, $context, $user);
        $canmanageitems = $cancreateitems || $canupdateitems || $candeleteitems;
        $canviewitems = has_capability('totara/hierarchy:view' . $prefix, $context, $user);

        if (file_exists($CFG->dirroot.'/totara/hierarchy/prefix/'.$prefix.'/scale/lib.php')) {
            $cancreatescales = has_capability('totara/hierarchy:create' . $prefix . 'scale', $context, $user);
            $canupdatescales = has_capability('totara/hierarchy:update' . $prefix . 'scale', $context, $user);
            $candeletescales = has_capability('totara/hierarchy:delete' . $prefix . 'scale', $context, $user);
            $canmanagescales = $cancreatescales || $canupdatescales || $candeletescales;
            $canviewscales = has_capability('totara/hierarchy:view' . $prefix . 'scale', $context, $user);
            $hierarchyhasscales = true;
        } else {
            $cancreatescales = false;
            $canupdatescales = false;
            $candeletescales = false;
            $canmanagescales = false;
            $canviewscales = false;
            $hierarchyhasscales = false;
        }

        $canmanage = $canmanageframeworks || $canmanageitems || $canmanagescales;
        $canview = $canviewframeworks || $canviewitems || $canviewscales;

        $out = compact('cancreateframeworks', 'canupdateframeworks', 'candeleteframeworks',
            'canmanageframeworks',
            'canviewframeworks', 'cancreateitems', 'canupdateitems', 'candeleteitems', 'canmanageitems',
            'canviewitems', 'cancreatescales', 'canupdatescales', 'candeletescales', 'canmanagescales',
            'hierarchyhasscales', 'canviewscales', 'canmanage', 'canview');
        return $out;
    }

    /**
     * Check if the hierarchy is enabled
     *
     * @access  public
     * @param   $prefix   string  Hierarchy prefix
     */
    public static function check_enable_hierarchy($prefix) {
        global $CFG;

        // $prefix could be user input so sanitize.
        $prefix = clean_param($prefix, PARAM_ALPHA);

        // Check file exists.
        $libpath = $CFG->dirroot.'/totara/hierarchy/prefix/'.$prefix.'/lib.php';
        if (file_exists($libpath)) {
            require_once $libpath;
            if (method_exists($prefix, 'check_feature_enabled')) {
                call_user_func(array($prefix, 'check_feature_enabled'));
            }
        }
    }
}
