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
 * Totata navigation library page
 *
 * @package    totara
 * @subpackage navigation
 * @author     Oleg Demeshev <oleg.demeshev@totaralms.com>
 */
namespace totara_core\totara\menu;

use \totara_core\totara\menu\menu as menu;

defined('MOODLE_INTERNAL') || die();

class build {

    // @var array tree of main menu objects.
    private $tree = array();

    // @var array database records.
    private $records = array();

    // @var array collection of parent menu items.
    private $parent = array();

    // @var array collection of child menu items.
    private $children = array();

    // @var object totara_core_menu object for requested menu item.
    private $item = null;

     /**
     * Adds a item to totara menu tree
     *
     * @param string $classname
     */
    public function add($classname) {
        // Generate new item object.
        $item = new \stdClass();
        $item->id = 0;
        $item->classname  = $classname;
        $item->custom = menu::DEFAULT_ITEM;
        $node = menu::node_instance($item);

        $this->tree[$classname] = $node;
    }

    /**
     * Create/upgrade/update the totara main menu navigation
     */
    public function upgrade() {
        // Get root property.
        $this->item = menu::get();

        $rs = (array)menu::get_records('tn.classname <> :classname', array('classname' => ''));
        $this->set_records($rs);

        $this->parent_sync();

        $this->children_sync();

        $this->cleandbup_sync();
    }

    /**
     * Parent main menu synchronisation process
     *
     * Loop through the menu items and check if a parent menu item exists
     * if does not exists then create one, if exists then check for modified
     * data and update accordingly.
     * Collect children menu items to synchronise.
     */
    protected function parent_sync() {
        foreach ($this->tree as $nameclass => $node) {
            $classname = $node->get_classname();
            if (!($record = $this->exists($classname))) {
                if ($node->get_parent() == 'root') {
                    $node->set_parentid(0);
                    try {
                        $data = $this->get_data($node);
                    } catch (\Exception $e) {
                        totara_set_notification($e->getMessage());
                        continue;
                    }
                    try {
                        $this->parent[$classname] = ($this->records ? $this->item->sync($data) : $this->create($data));
                    } catch (\Exception $e) {
                        totara_set_notification($e->getMessage());
                        continue;
                    }
                } else {
                    // Collect children which does not exists.
                    $this->children[$classname] = $node;
                }
            } else {
                if ($node->get_parent() == 'root') {
                    $this->parent[$classname] = $record;
                }
            }
        }
    }

    /**
     * Children main menu synchronisation process
     *
     * Loop through the menu items and check if a child menu item exists
     * if does not exists then create one, if exists then check for modified
     * data and update accordingly.
     */
    protected function children_sync() {
        foreach ($this->children as $classname => $node) {
            if (isset($this->parent[$node->get_parent()])) {
                $parentid = $this->parent[$node->get_parent()]->id;
                $node->set_parentid($parentid);
            } else {
                $node->set_parentid(0);
                totara_set_notification(get_string('error:parentnotexists', 'totara_core', $node->get_parent()));
            }
            try {
                $data = $this->get_data($node);
            } catch (\Exception $e) {
                totara_set_notification($e->getMessage());
                continue;
            }
            try {
                // Upgrade.
                if ($this->records) {
                    $this->item->sync($data);
                } else {
                    // Fresh install.
                    $this->create($data);
                }
            } catch (\Exception $e) {
                totara_set_notification($e->getMessage());
                continue;
            }
        }
    }

    /**
     * Find and remove old existing menu items
     *
     * This can happen when "mymenuitem".php object is deleted
     */
    protected function cleandbup_sync() {
        // Remove the old items from db if exists.
        $rs = (array)menu::get_records('tn.classname <> :classname', array('classname' => ''));
        $this->set_records($rs);
        $todelete = array_diff(array_keys($this->records), array_keys($this->tree));
        if (empty($todelete)) {
            return;
        }

        foreach ($todelete as $classname) {
            $item = menu::get($this->records[$classname]->id);
            // If item was created through UI, do not delete it.
            if ($item->custom == menu::DB_ITEM) {
                continue;
            }
            $children = $item->get_children();
            if ($children) {
                foreach ($children as $child) {
                    $child->set_parent();
                }
            }
            try {
                // Set item as a custom node to enable delete from db.
                $item->set_custom(menu::DB_ITEM);
                $item->delete();
            } catch (\Exception $e) {
                totara_set_notification($e->getMessage());
            }
        }
    }

    /**
     * Generate data from database record or from "mymenuitem".php object
     *
     * @param object $node
     * @return \stdClass $data
     */
    private function get_data($node) {
        $data = new \stdClass();
        $data->parentid    = $node->get_parentid();
        $data->title       = $node->get_title();
        $data->classname   = $node->get_classname();
        $data->url         = $node->get_url();
        $data->custom      = menu::DEFAULT_ITEM;
        $data->customtitle = 0;
        $data->visibility  = $node->get_visibility(false);
        $data->targetattr  = $node->get_targetattr();
        $data->sortorder   = $node->get_default_sortorder();
        return $data;
    }

    /**
     * Check if menu item object exists in database records
     *
     * @param string $classname
     * @return object|false
     */
    private function exists($classname = '') {
        if (isset($this->records[$classname])) {
            return $this->records[$classname];
        }
        return false;
    }

    /**
     * Set menu items from database records
     *
     * @param array $records
     */
    private function set_records($records = array()) {
        foreach ($records as $id => $item) {
            $this->records[$item->classname] = $item;
        }
    }

    /**
     * Validate data before creating a new record
     *
     * @param object $data
     * @return object database record
     * @throws \moodle_exception
     */
    private function create($data) {
        $errors = menu::validation($data);
        if (!empty($errors)) {
            throw new \moodle_exception(reset($errors));
        }
        return $this->item->create($data);
    }
}
