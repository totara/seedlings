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
 * @subpackage message
 */

defined('MOODLE_INTERNAL') || die();


require_once($CFG->dirroot.'/totara/program/lib.php');

/**
* Extend the base plugin class
* This class contains the action for facetoface onaccept/onreject message processing
*/
class totara_message_workflow_prog_extension extends totara_message_workflow_plugin_base {

    /**
     * Action called on accept for a program extension action
     *
     * @param array $eventdata
     * @param object $msg
     */
    function onaccept($eventdata, $msg) {
        global $SITE;

        // Load course.
        $userid = $eventdata['userid'];
        $extensionid = $eventdata['extensionid'];
        $programid = $eventdata['programid'];
        $reasonfordecision = (isset($eventdata['reasonfordecision'])) ? $eventdata['reasonfordecision'] : '';

        $extensions = array($extensionid => 1);  // 1 = grant, 2 = deny
        $reason = array($extensionid => $reasonfordecision);

        // Approve extensions
        if (prog_process_extensions($extensions, $reason)) {
            add_to_log($SITE->id, 'program', 'grant extension', "view.php?id=$programid", $programid);
        }

        return true;
    }


    /**
     * Action called on reject of a program extension action
     *
     * @param array $eventdata
     * @param object $msg
     */
    function onreject($eventdata, $msg) {
        global $SITE;

        // Can manipulate the language by setting $SESSION->lang temporarily.
        // Load course.
        $userid = $eventdata['userid'];
        $extensionid = $eventdata['extensionid'];
        $programid = $eventdata['programid'];
        $reasonfordecision = (isset($eventdata['reasonfordecision'])) ? $eventdata['reasonfordecision'] : '';

        $extensions = array($extensionid => 2);  // 1 = grant, 2 = deny
        $reason = array($extensionid => $reasonfordecision);

        // Decline extensions.
        if (prog_process_extensions($extensions, $reason)) {
            add_to_log($SITE->id, 'program', 'deny extensions', "view.php?id=$programid", $programid);
        }

        return true;
    }
}
