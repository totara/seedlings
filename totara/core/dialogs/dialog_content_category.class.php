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
 * @author Maria Torres <maria.torres@totaralms.com>
 * @package totara
 * @subpackage totara_core/dialogs
 */


require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content.class.php');

class totara_dialog_content_category extends totara_dialog_content {

    /**
     * If you are making access checks seperately, you can disable
     * the internal checks by setting this to true.
     *
     * @access  public
     * @var     boolean
     */
    public $skip_access_checks = false;


    /**
     * Type of search to perform (generally relates to dialog type).
     *
     * @access  public
     * @var     string
     */
    public $searchtype = 'category';

    /**
     * Construct.
     * @param bool $skipaccesschecks Indicate whether access checks should be performed
     */
    public function __construct($skipaccesschecks = false) {

        // Make some capability checks.
        $this->skip_access_checks = $skipaccesschecks;
        if (!$this->skip_access_checks) {
            require_login();
        }

        $this->type = self::TYPE_CHOICE_MULTI;
    }

    /**
     * Load hierarchy items to display.
     *
     * @access  public
     * @param   $parentid   int
     */
    public function load_items($parentid) {
        $this->items = $this->get_items_by_parent($parentid);

        // If we are loading non-root nodes, tell the dialog_content class not to
        // return markup for the whole dialog.
        if ($parentid > 0) {
            $this->show_treeview_only = true;
        }

        // Also fill parents array.
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
     * Return all categories.
     *
     * @return array Array of categories
     */
    public function get_items() {
        return $this->get_all_root_items();
    }

    /**
     * Get items by parent.
     * @param int $parentid
     * @return array
     */
    public function get_items_by_parent($parentid=0) {
        if ($parentid) {
            // Returns categories which parent is $parentid.
            return $this->get_subcategories_item($parentid);
        } else {
            // If no parentid, grab root items.
            return $this->get_all_root_items();
        }
    }


    /**
     * Returns categories which don't belong to another category.
     *
     * @return array The records for the top level categories
     */
    public function get_all_root_items() {
        global $DB;

        return $DB->get_records('course_categories', array('parent' => 0), '', 'id, name, path');
    }

    /**
     * Return subcategories for the item.
     *
     * @return array Array of subcategories
     */
    public function get_subcategories_item($itemid) {
        global $DB;

        return $DB->get_records('course_categories', array('parent' => $itemid), 'id', 'id, name, path');
    }

    /**
     * Get all items that are parents.
     *
     * @return  array
     */
    public function get_all_parents() {
        global $DB;

        // Returns categories which have another categories inside.
        $parents = $DB->get_records_sql("
            SELECT DISTINCT parent
            FROM {course_categories}
            WHERE parent != 0");

        return $parents;
    }
}
