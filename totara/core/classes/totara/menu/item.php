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
 * Totara navigation edit page.
 *
 * @package    totara
 * @subpackage navigation
 * @author     Oleg Demeshev <oleg.demeshev@totaralms.com>
 */
namespace totara_core\totara\menu;

use \totara_core\totara\menu\menu as menu;

class item {

    // @var properties of menu node.
    protected $id, $parentid, $title, $url, $classname, $sortorder;
    protected $depth, $path, $custom, $customtitle, $visibility, $targetattr;
    protected $name, $parent;

    /**
     * Set values for node's properties.
     *
     * @param object $node
     */
    public function __construct($node) {
        foreach ((object)$node as $key => $value) {
            $this->{$key} = $value;
        }
    }

    /**
     * Returns node id.
     *
     * @return int id
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Returns node parent id.
     *
     * @return int parent id
     */
    public function get_parentid() {
        return $this->parentid;
    }

    /**
     * Set node parent id.
     */
    public function set_parentid($parentid = 0) {
        $this->parentid = $parentid;
    }

    /**
     * Returns node formatted title.
     * Check for customtitle flag, if it is not set then returns default title.
     * Otherwise returns modified title by client.
     *
     * @return string node title
     */
    public function get_title() {
        if (empty($this->customtitle)) {
            $this->title = $this->get_default_title();
        }
        return format_string($this->title);
    }

    /**
     * Check if get_default_title() method exists, if not throw exception.
     *
     * @throws \moodle_exception
     */
    protected function get_default_title() {
        throw new \moodle_exception('error:menuitemtitleismissing', 'totara_core', null, get_called_class());
    }

    /**
     * Returns node url.
     * Check for custom flag, if it is not set then returns default url.
     * Otherwise returns modified url by client.
     *
     * @return string node url
     */
    public function get_url() {
        if ((int)$this->custom == 0) {
            $this->url = $this->get_default_url();
        }
        return $this->url;
    }

    /**
     * Check if get_default_url() method exists, if not throw exception.
     *
     * @throws \moodle_exception
     */
    protected function get_default_url() {
        throw new \moodle_exception('error:menuitemurlismissing', 'totara_core', null, get_called_class());
    }

    /**
     * Returns node classname.
     *
     * @return string node classname
     */
    public function get_classname() {
        return $this->classname;
    }

    /**
     * Returns the visibility of a particular node.
     *
     * If $calculated is true, this method calls {@link check_visibility()} to assess
     * the visibility and always returns menu::SHOW_ALWAYS or menu::HIDE_ALWAYS.
     *
     * If $calculated is false, this method returns the raw visibility, which could
     * also be menu::SHOW_WHEN_REQUIRED.
     *
     * @param bool $calculated Whether or not to convert SHOW_WHEN_REQUIRED to an actual state.
     * @return int One of menu::SHOW_WHEN_REQUIRED, menu::SHOW_ALWAYS or menu::HIDE_ALWAYS
     */
    public function get_visibility($calculated = true) {
        if (!isset($this->visibility)) {
            $this->visibility = $this->get_default_visibility();
        }
        if ($calculated && $this->visibility == menu::SHOW_WHEN_REQUIRED) {
            return $this->check_visibility();
        }
        return $this->visibility;
    }

    /**
     * Real-time check of visibility for SHOW_WHEN_REQUIRED. Override
     * in subclasses with specific visibility rules for the class.
     *
     * @return int Either menu::SHOW_ALWAYS or menu::HIDE_ALWAYS.
     */
    protected function check_visibility() {
        return menu::SHOW_ALWAYS;
    }

    /**
     * Check if get_default_visibility() method exists, if not throw exception.
     *
     * @throws \moodle_exception
     */
    public function get_default_visibility() {
        throw new \moodle_exception('error:menuitemvisibilityismissing', 'totara_core', null, get_called_class());
    }

    /**
     * Returns node original class name, wihtout namespace string.
     *
     * @return string node class name
     */
    public function get_name() {
        if ((int)$this->custom == 1) {
            $this->name = 'item' . $this->id;
        } else {
            $this->name = $this->get_original_classname($this->classname);
        }
        return $this->name;
    }

    /**
     * Returns node original parent class name, wihtout namespace string.
     *
     * @return string node parent class name
     */
    public function get_parent() {
        // If parent is empty then it is database record or new item from class.
        if ((int)$this->id == 0) {
            $this->parent = $this->get_default_parent();
        } else {
            if ((int)$this->parentid > 0) {
                $this->parent = $this->get_original_classname($this->parent);
                // Check if menu item created through UI
                if ($this->parent == 'item') {
                    $this->parent .= $this->parentid;
                }
            }
        }
        return $this->parent;
    }

    /*
     * Returns the default parent of this type of menu item. Defaults to top level ('root')
     * unless overridden in a subclass.
     *
     * @return string Name of parent classname or 'root' for top level.
     */
    protected function get_default_parent() {
        return 'root';
    }

    /*
     * Returns the default sort order of this type of menu item. Defaults to null (no preference)
     * unless overridden in a subclass.
     *
     * @return int|null Preferred sort order when this item is first added, or null for no preference.
     */
    public function get_default_sortorder() {
        return null;
    }

    /**
     * Returns node html target attribute.
     * Values for target attributes are _blank|_parent|_top|framename
     *
     * @return string node html target attribute.
     */
    public function get_targetattr() {
        if ((int)$this->id == 0) {
            $this->targetattr = $this->get_default_targetattr();
        }
        return $this->targetattr;
    }

    /*
     * Return value for url link target attribute.
     * Default value _self, <a href="http://mydomain.com">My page</a>.
     * Customer value _blank|_parent|_top|framename, <a target="_blank" href="http://mydomain.com">My page</a>.
     *
     * @return string node target attribute
     */
    protected function get_default_targetattr() {
        return menu::TARGET_ATTR_SELF;
    }

    /**
     * Parse namespace classname and returns original class name.
     * Coming string is "totara_core\totara\menu\myclassname".
     * Returns "myclassname".
     *
     * @param string $classname
     * @return string
     */
    private function get_original_classname($classname) {
        $path = \core_text::strrchr($classname, "\\");
        return \core_text::substr($path, 1);
    }
}
