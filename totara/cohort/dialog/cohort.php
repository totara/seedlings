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
 * @subpackage cohort
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot .'/totara/core/dialogs/dialog_content.class.php');
require_once($CFG->dirroot .'/cohort/lib.php');

require_login();
$context = context_system::instance();
// Get program id and check capabilities
require_capability('moodle/cohort:manage', $context);

$PAGE->set_context($context);

// Already selected items
$selected = optional_param('selected', array(), PARAM_SEQUENCE);
if (!empty($selected)) {
    $selected = $DB->get_records_select('cohort', "id IN ({$selected})", array(), '', 'id, name as fullname');
}

$items = $DB->get_records('cohort');

// Don't let them remove the currently selected ones
$unremovable = $selected;

// Setup dialog
// Load dialog content generator; skip access, since it's checked above
$dialog = new totara_dialog_content();
$dialog->type = totara_dialog_content::TYPE_CHOICE_MULTI;
$dialog->items = $items;

// Set disabled/selected items
$dialog->selected_items = $selected;

// Set unremovable items
$dialog->unremovable_items = $unremovable;

// Set title
$dialog->selected_title = 'itemstoadd';

// Setup search
$dialog->searchtype = 'cohort';

// Display
echo $dialog->generate_markup();
