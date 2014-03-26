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
 * @package mod
 * @subpackage facetoface
 */

function xmldb_facetoface_install() {
    global $DB;

    //Create default notification templates
    $tpl_confirmation = new stdClass();
    $tpl_confirmation->status = 1;
    $tpl_confirmation->title = get_string('setting:defaultconfirmationsubjectdefault', 'facetoface');
    $tpl_confirmation->body = text_to_html(get_string('setting:defaultconfirmationmessagedefault', 'facetoface'));
    $tpl_confirmation->managerprefix = text_to_html(get_string('setting:defaultconfirmationinstrmngrdefault', 'facetoface'));
    $DB->insert_record('facetoface_notification_tpl', $tpl_confirmation);

    $tpl_cancellation = new stdClass();
    $tpl_cancellation->status = 1;
    $tpl_cancellation->title = get_string('setting:defaultcancellationsubjectdefault', 'facetoface');
    $tpl_cancellation->body = text_to_html(get_string('setting:defaultcancellationmessagedefault', 'facetoface'));
    $tpl_cancellation->managerprefix = text_to_html(get_string('setting:defaultcancellationinstrmngrdefault', 'facetoface'));
    $DB->insert_record('facetoface_notification_tpl', $tpl_cancellation);

    $tpl_waitlist = new stdClass();
    $tpl_waitlist->status = 1;
    $tpl_waitlist->title = get_string('setting:defaultwaitlistedsubjectdefault', 'facetoface');
    $tpl_waitlist->body = text_to_html(get_string('setting:defaultwaitlistedmessagedefault', 'facetoface'));
    $DB->insert_record('facetoface_notification_tpl', $tpl_waitlist);

    $tpl_reminder = new stdClass();
    $tpl_reminder->status = 1;
    $tpl_reminder->title = get_string('setting:defaultremindersubjectdefault', 'facetoface');
    $tpl_reminder->body = text_to_html(get_string('setting:defaultremindermessagedefault', 'facetoface'));
    $tpl_reminder->managerprefix = text_to_html(get_string('setting:defaultreminderinstrmngrdefault', 'facetoface'));
    $DB->insert_record('facetoface_notification_tpl', $tpl_reminder);

    $tpl_request = new stdClass();
    $tpl_request->status = 1;
    $tpl_request->title = get_string('setting:defaultrequestsubjectdefault', 'facetoface');
    $tpl_request->body = text_to_html(get_string('setting:defaultrequestmessagedefault', 'facetoface'));
    $tpl_request->managerprefix = text_to_html(get_string('setting:defaultrequestinstrmngrdefault', 'facetoface'));
    $DB->insert_record('facetoface_notification_tpl', $tpl_request);

    $tpl_decline = new stdClass();
    $tpl_decline->status = 1;
    $tpl_decline->title = get_string('setting:defaultdeclinesubjectdefault', 'facetoface');
    $tpl_decline->body = text_to_html(get_string('setting:defaultdeclinemessagedefault', 'facetoface'));
    $tpl_decline->managerprefix = text_to_html(get_string('setting:defaultdeclineinstrmngrdefault', 'facetoface'));
    $DB->insert_record('facetoface_notification_tpl', $tpl_decline);

    // Setting room, building, and address as default filters.
    set_config('facetoface_calendarfilters', 'room,building,address');
}
?>
