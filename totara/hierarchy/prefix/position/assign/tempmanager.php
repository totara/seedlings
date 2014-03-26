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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package totara
 * @subpackage hierarchy
 */


require_once(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/config.php');
require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content_hierarchy.class.php');

$userid = required_param('userid', PARAM_INT);

/*
 * Setup / loading data.
 */

// Setup page.
$PAGE->set_context(context_system::instance());
require_login();

// Get guest user for exclusion purposes.
$guest = guest_user();

// Load potential managers for this user.
$currentmanager = totara_get_manager($userid, null, true);
$currentmanagerid = empty($currentmanager) ? 0 : $currentmanager->id;
if (empty($CFG->tempmanagerrestrictselection)) {
    // All users.
    $sql = "SELECT u.id, u.email, ".$DB->sql_fullname('u.firstname', 'u.lastname')." AS fullname
              FROM {user} u
             WHERE u.deleted = 0
               AND u.suspended = 0
               AND u.id NOT IN(?, ?, ?)
          ORDER BY fullname, u.id";
} else {
    $sql = "SELECT DISTINCT u.id, u.email, ".$DB->sql_fullname('u.firstname', 'u.lastname')." AS fullname
              FROM {pos_assignment} pa
              JOIN {user} u ON pa.managerid = u.id
             WHERE u.deleted = 0
               AND u.suspended = 0
               AND u.id NOT IN(?, ?, ?)
          ORDER BY fullname, u.id";
}
$managers = $DB->get_records_sql($sql, array($guest->id, $userid, $currentmanagerid));

/*
 * Display page.
 */

$dialog = new totara_dialog_content();
$dialog->searchtype = 'temporary_manager';
$dialog->items = $managers;
$dialog->disabled_items = array($userid => true, $currentmanagerid => true);
$dialog->customdata['current_user'] = $userid;
$dialog->customdata['current_manager'] = $currentmanagerid;
$dialog->urlparams['userid'] = $userid;

echo $dialog->generate_markup();
