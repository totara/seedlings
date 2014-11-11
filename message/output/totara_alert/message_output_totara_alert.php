<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010 onwards Totara Learning Solutions LTD
 * Copyright (C) 1999 onwards Martin Dougiamas
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
 * @author Piers Harding <piers@catalyst.net.nz>
 * @author Luis Rodrigues
 * @package totara
 * @subpackage message
 */

/**
 * Alert message processor - stores the message to be shown using the totara alert notification system
 */

require_once($CFG->dirroot.'/message/output/lib.php');
require_once($CFG->dirroot.'/totara/message/messagelib.php');

class message_output_totara_alert extends message_output{

    /**
     * Process the alert message.
     * @param object $eventdata the event data submitted by the message sender plus $eventdata->savedmessageid
     * @return true if ok, false if error
     */
    public function send_message($eventdata) {
        global $DB;

        //hold onto the alert processor id because /admin/cron.php sends a lot of messages at once
        static $processorid = null;

        //prevent users from getting alert alerts of messages to themselves (happens with forum alerts)
        if (empty($processorid)) {
            $processor = $DB->get_record('message_processors', array('name' => 'totara_alert'), '*', MUST_EXIST);
            $processorid = $processor->id;
        }
        $procmessage = new stdClass();
        $procmessage->unreadmessageid = $eventdata->savedmessageid;
        $procmessage->processorid     = $processorid;

        //save this message for later delivery
        $workid = $DB->insert_record('message_working', $procmessage);

        // save the metadata
        tm_insert_metadata($eventdata, $processorid);

        return true;
    }

    /**
     * @param  object $user the user object, defaults to $USER.
     * @return bool has the user made all the necessary settings
     * in their profile to allow this plugin to be used.
     */
    public function is_user_configured($user = null) {
        return true;
    }

    function config_form($preferences) {
        return null;
    }

    public function process_form($form, &$preferences) {
        return true;
    }
    public function load_data(&$preferences, $userid) {
        global $USER;
        return true;
    }
}
