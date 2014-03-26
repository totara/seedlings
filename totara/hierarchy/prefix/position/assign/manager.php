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
 * @package totara
 * @subpackage totara_hierarchy
 */

require_once(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/config.php');
require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content_hierarchy.class.php');

$userid = required_param('userid', PARAM_INT);

$PAGE->set_context(context_system::instance());

if (!(get_config('totara_hierarchy', 'allowsignupmanager') && $userid == 0)) {
    require_login();
}

///
/// Setup / loading data
///

//get guest user for exclusion purposes
$guest = guest_user();

// Load potential managers for this user
$managers = $DB->get_records_sql(
    "
        SELECT
            u.id, u.email,
            ".$DB->sql_fullname('u.firstname', 'u.lastname')." AS fullname
        FROM
            {user} u
        WHERE
            u.deleted = 0
        AND u.suspended = 0
        AND u.id != ?
        AND u.id != ?
        ORDER BY
            u.firstname,
            u.lastname
    ",
    array($guest->id, $userid), 0, TOTARA_DIALOG_MAXITEMS + 1);
// Limit results to 1 more than the maximum number that might be displayed
// there is no point returning any more as we will never show them

///
/// Display page
///

$dialog = new totara_dialog_content();
$dialog->searchtype = 'user';
$dialog->items = $managers;
$dialog->customdata['current_user'] = $userid;
$dialog->urlparams['userid'] = $userid;

echo $dialog->generate_markup();
