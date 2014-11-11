<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2014 onwards Totara Learning Solutions LTD
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
 * @author Oleg Demeshev <oleg.demeshev@totaralms.com>
 * @package totara_core
 */

namespace totara_core\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The menu item sortorder event class.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - string shortname: Short name of menu item.
 * }
 *
 * @author Oleg Demeshev <oleg.demeshev@totaralms.com>
 * @package totara_core
 */
class menuitem_sortorder extends \core\event\base {
    /**
     * Flag for prevention of direct create() call.
     * @var bool
     */
    protected static $preventcreatecall = true;

    /**
     * Create instance of event.
     *
     * @param $itemid
     * @param $action moveup/movedown
     * @return menuitem_sortorder
     */
    public static function create_from_item($itemid, $action) {
        $data = array(
            'objectid' => $itemid,
            'context' => \context_system::instance(),
            'other' => array(
                'action' => $action,
            ),
        );
        self::$preventcreatecall = false;
        $event = self::create($data);
        self::$preventcreatecall = true;
        return $event;
    }

    /**
     * Initialise required event data properties.
     */
    protected function init() {
        $this->data['objecttable'] = 'totara_navigation';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventmenuitemupdated', 'totara_core');
    }

    /**
     * Returns non-localised event description with id's for admin use only.
     *
     * @return string
     */
    public function get_description() {
        return "Menu item with id '{$this->objectid}' changed sort order and {$this->other['action']}";
    }

    /**
     * Return name of the legacy event, which is replaced by this event.
     *
     * @return string legacy event name
     */
    public static function get_legacy_eventname() {
        return 'menuitem_sortorder';
    }

    /**
     * Return menuitem_sortorder legacy event data.
     *
     * @return \stdClass menu item data.
     */
    protected function get_legacy_eventdata() {
        $node = new \stdClass();
        $node->id = $this->objectid;
        return $node;
    }

    /**
     * Returns array of parameters to be passed to legacy add_to_log() function.
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        return array(SITEID, 'totara_core', 'menu item updated', "index.php?{$this->other['action']}={$this->objectid}", $this->objectid);
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/totara/core/menu/index.php', array($this->other['action'] => $this->objectid));
    }

    /**
     * Custom validation.
     *
     * @return void
     */
    protected function validate_data() {
        if (self::$preventcreatecall) {
            throw new \coding_exception('cannot call menuitem_sortorder::create() directly, use menuitem_sortorder::create_from_item() instead.');
        }
        parent::validate_data();
    }
}
