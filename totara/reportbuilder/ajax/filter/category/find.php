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
 * @author Maria Torres <maria.torres@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content_category.class.php');
require_once($CFG->dirroot.'/totara/core/js/lib/setup.php');

// Page title.
$pagetitle = 'categories';

// Parent ID.
$parentid = optional_param('parentid', 0, PARAM_INT);

// Only return generated tree html.
$treeonly = optional_param('treeonly', false, PARAM_BOOL);

$PAGE->set_context(context_system::instance());

// Permissions checks.
require_login();
require_sesskey();

// Output headers.
echo $OUTPUT->header();

// Load dialog content generator.
$dialog = new totara_dialog_content_category(true);

// Toggle treeview only display.
$dialog->show_treeview_only = $treeonly;

// Load items to display.
$dialog->load_items($parentid);

// Set title.
$dialog->selected_title = 'itemstoadd';
$dialog->select_title = '';

// Display.
echo $dialog->generate_markup();
