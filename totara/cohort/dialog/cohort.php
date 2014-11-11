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
require_once($CFG->dirroot .'/cohort/lib.php');

$selected   = optional_param('selected', array(), PARAM_SEQUENCE);
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$courseid   = optional_param('courseid', 0, PARAM_INT);

require_login();
try {
    require_sesskey();
} catch (moodle_exception $e) {
    echo html_writer::tag('div', $e->getMessage(), array('class' => 'notifyproblem'));
    die();
}

// Check user capabilities.
$contextsystem = context_system::instance();
if ((int)$courseid > 0) {
    $context = context_course::instance((int)$courseid);
} else if ((int)$categoryid > 0) {
    $context = context_coursecat::instance((int)$categoryid);
} else {
    $context = $contextsystem;
}
$capable = false;
if (has_capability('moodle/cohort:view', $context) || has_capability('moodle/cohort:manage', $contextsystem)) {
    $capable = true;
}

$PAGE->set_context($context);
$PAGE->set_url('/totara/cohort/dialog/cohort.php');

if (!$capable) {
    echo html_writer::tag('div', get_string('error:capabilitycohortview', 'totara_cohort'), array('class' => 'notifyproblem'));
    die();
}

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

$dialog->customdata['courseid'] = (int)$courseid;
$dialog->customdata['categoryid'] = (int)$categoryid;

// Display
echo $dialog->generate_markup();
