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
 * @author Jake Salmon <jake.salmon@kineo.com>
 * @package totara
 * @subpackage totara_core/dialogs
 */


require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content.class.php');
require_once($CFG->dirroot.'/totara/hierarchy/prefix/position/lib.php');

class totara_dialog_content_manager extends totara_dialog_content {

    /**
     * If you are making access checks seperately, you can disable
     * the internal checks by setting this to true
     *
     * @access  public
     * @var     boolean
     */
    public $skip_access_checks = true;


    /**
     * Type of search to perform (generally relates to dialog type)
     *
     * @access  public
     * @var     string
     */
    public $searchtype = 'manager';


    /**
     * Construct
     */
    public function __construct() {

        // Make some capability checks
        if (!$this->skip_access_checks) {
            require_login();
        }

        $this->type = self::TYPE_CHOICE_MULTI;
    }

    /**
     * Load hierarchy items to display
     *
     * @access  public
     * @param   $parentid   int
     */
    public function load_items($parentid) {
        $this->items = $this->get_items_by_parent($parentid);

        // If we are loading non-root nodes, tell the dialog_content class not to
        // return markup for the whole dialog
        if ($parentid > 0) {
            $this->show_treeview_only = true;
        }

        // Also fill parents array
        $this->parent_items = $this->get_all_parents();
    }


    /**
     * Should we show the treeview root?
     *
     * @access  protected
     * @return  boolean
     */
    protected function _show_treeview_root() {
        return !$this->show_treeview_only;
    }


    /**
     * Return all possible managers
     *
     * @return array Array of managers
     */
    function get_items() {
        global $DB;
        return $DB->get_records_sql("
            SELECT DISTINCT pa.managerid AS sortorder, pa.managerid AS id, u.lastname
            FROM {pos_assignment} pa
            INNER JOIN {user} u
            ON pa.managerid = u.id
            WHERE pa.type = ?
            ORDER BY u.lastname", array(POSITION_TYPE_PRIMARY)
        );
    }

    /**
     * Get items in a framework by parent
     * @param int $parentid
     * @return array
     */
    function get_items_by_parent($parentid=false) {
        global $DB;

        if ($parentid) {
            // returns users who *are* managers, who's manager is user $parentid
            return $DB->get_records_sql("
                SELECT u.id, " . $DB->sql_fullname() . " AS fullname, u.email
                FROM (
                    SELECT DISTINCT managerid AS id
                    FROM {pos_assignment}
                    WHERE type = ?
                ) managers
                INNER JOIN {pos_assignment} pa on managers.id = pa.userid
                INNER JOIN {user} u on u.id = pa.userid
                WHERE pa.managerid = ?
                AND pa.type = ?
                ORDER BY u.lastname, u.id
            ", array(POSITION_TYPE_PRIMARY, $parentid, POSITION_TYPE_PRIMARY));
        }
        else {
            // If no parentid, grab the root node of this framework
            return $this->get_all_root_items();
        }
    }


    /**
     * Returns all users who are managers but don't have managers, e.g.
     * the top level of the management hierarchy
     *
     * @return array The records for the top level managers
     */
    function get_all_root_items() {
        global $DB;

        // returns users who *are* managers, but don't *have* a manager
        return $DB->get_records_sql("
            SELECT u.id, " . $DB->sql_fullname() . " as fullname, u.email
            FROM (
                SELECT DISTINCT managerid AS id
                FROM {pos_assignment}
                WHERE type = ?
            ) managers
            LEFT JOIN {pos_assignment} pa on managers.id = pa.userid
            INNER JOIN {user} u on u.id = managers.id
            WHERE pa.managerid IS NULL OR pa.managerid = 0
            GROUP BY u.id, u.firstname, u.lastname, u.email
            ORDER BY u.firstname, u.lastname
        ", array(POSITION_TYPE_PRIMARY));
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

        // returns users who *are* managers, who also have staff who *are* managers
        $parents = $DB->get_records_sql("
            SELECT DISTINCT managers.id
            FROM (
                SELECT DISTINCT managerid AS id
                FROM {pos_assignment}
                WHERE type = ?
            ) managers
            INNER JOIN {pos_assignment} staff on managers.id = staff.managerid
            INNER JOIN {pos_assignment} pa ON staff.userid = pa.managerid AND pa.type = ?
            ", array(POSITION_TYPE_PRIMARY, POSITION_TYPE_PRIMARY));

        return $parents;
    }
}
