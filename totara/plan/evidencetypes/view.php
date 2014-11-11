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
 * @author Simon Coggins <simonc@totaralms.com>
 * @author Russell England <russell.england@totaralms.com>
 * @package totara
 * @subpackage plan
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('lib.php');
require_once($CFG->dirroot . '/totara/plan/lib.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

$id = required_param('id', PARAM_INT);

// Page setup and check permissions
admin_externalpage_setup('evidencetypes');
$context = context_system::instance();
require_capability('totara/plan:manageevidencetypes', $context);

if (!$item = $DB->get_record('dp_evidence_type', array('id' => $id))) {
    print_error('error:evidencetypeidincorrect', 'totara_plan');
}

// Display page
$navlinks = array();    // Breadcrumbs
$navlinks[] = array('name' => get_string("evidencetypes", 'totara_plan'),
                    'link' => new moodle_url('/totara/plan/evidencetypes/index.php'),
                    'type' => 'misc');
$navlinks[] = array('name' => format_string($item->name), 'link' => '', 'type' => 'misc');

echo $OUTPUT->header();

echo $OUTPUT->single_button(
        new moodle_url('/totara/plan/evidencetypes/index.php'),
        get_string('allevidencetypes', 'totara_plan'), 'get');

// Display info about evidence type
echo $OUTPUT->heading(get_string('evidencetypex', 'totara_plan', format_string($item->name)));

$item->description = file_rewrite_pluginfile_urls($item->description, 'pluginfile.php', $context->id, 'totara_plan', 'dp_evidence_type', $item->id);
echo html_writer::tag('p', format_text($item->description, FORMAT_HTML));

// Display warning if evidence type is in use
if (dp_evidence_type_is_used($item->id)) {
    echo $OUTPUT->container(get_string('evidencetypeinuse', 'totara_plan'), 'notifysuccess');
}

echo $OUTPUT->footer();
