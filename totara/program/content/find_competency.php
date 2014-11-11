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
 * @author Ben Lobo <ben.lobo@kineo.com>
 * @package totara
 * @subpackage program
 */


require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content_hierarchy.class.php');
require_once("{$CFG->dirroot}/totara/program/lib.php");

$PAGE->set_context(context_system::instance());
require_login();

///
/// Setup / loading data
///

// Program id
$id = required_param('id', PARAM_INT);

// Parent id
$parentid = optional_param('parentid', 0, PARAM_INT);

// Only return generated tree html
$treeonly = optional_param('treeonly', false, PARAM_BOOL);

require_capability('totara/program:configurecontent', program_get_context($id));

///
/// Setup dialog
///

// Load dialog content generator
$dialog = new totara_dialog_content_hierarchy('competency');
$dialog->requireevidence = true;
$dialog->disable_picker = true;

$dialog->lang_file = 'totara_hierarchy';
// Toggle treeview only display
$dialog->show_treeview_only = $treeonly;

// Load items to display
$select = "
    SELECT
        c.id as id,
        c.fullname as fullname
    FROM
        {comp} c
    WHERE
        c.evidencecount > 0
    AND c.visible = 1
    ORDER BY
        c.fullname
";

$dialog->items = $DB->get_records_sql($select);

// Set title
$dialog->selected_title = 'currentlyselected';

// Addition url parameters
$dialog->urlparams = array('id' => $id);

// Display
echo $dialog->generate_markup();
