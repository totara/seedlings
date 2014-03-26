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
 * @package totara
 * @subpackage program
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content.class.php');
require_once("{$CFG->dirroot}/totara/program/lib.php");

$PAGE->set_context(context_system::instance());
require_login();

// Get program id and check capabilities
$programid = required_param('programid', PARAM_INT);
require_capability('totara/program:configureassignments', program_get_context($programid));


// Already selected items
$selected = optional_param('selected', array(), PARAM_SEQUENCE);
if ($selected != false) {
    list($selectedsql, $selectedparams) = $DB->get_in_or_equal(explode(',', $selected));
    $selected = $DB->get_records_select('cohort', "id {$selectedsql}", $selectedparams, '', 'id, name as fullname');
}

$items = $DB->get_records('cohort');

// Don't let them remove the currently selected ones
$unremovable = $selected;


///
/// Setup dialog
///

// Load dialog content generator; skip access, since it's checked above
$dialog = new totara_dialog_content();
$dialog->type = totara_dialog_content::TYPE_CHOICE_MULTI;
$dialog->searchtype = 'cohort';

$dialog->items = $items;

// Set disabled/selected items
$dialog->selected_items = $selected;

// Set unremovable items
$dialog->unremovable_items = $unremovable;

// Set title
$dialog->selected_title = 'itemstoadd';

$dialog->urlparams = array('programid' => $programid);

// Display
echo $dialog->generate_markup();
