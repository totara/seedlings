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
 * @author Aaron Barnes <aaronb@catalyst.net.nz>
 * @author Jake Salmon <jake.salmon@kineo.com>
 * @package totara
 * @subpackage program
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content_courses.class.php');
require_once($CFG->dirroot.'/totara/plan/lib.php');

require_login();
$PAGE->set_context(context_system::instance());
///
/// Setup / loading data
///


// Category id
$categoryid = optional_param('parentid', 'cat0', PARAM_ALPHANUM);

// Strip cat from begining of categoryid
$categoryid = (int) substr($categoryid, 3);

///
/// Setup dialog
///

// Load dialog content generator
$dialog = new totara_dialog_content_courses($categoryid);

// Set type to multiple
$dialog->selected_title = 'currentlyselected';

// Add data
$dialog->load_courses();

// Display page
echo $dialog->generate_markup();
