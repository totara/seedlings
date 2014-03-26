<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010 onwards Totara Learning Solutions LTD
 * Copyright (C) 1999 onwards Martin Dougiamas
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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage plan
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('../lib.php');
require_once('lib.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$moveup = optional_param('moveup', null, PARAM_INT);
$movedown = optional_param('movedown', null, PARAM_INT);

/// Setup / loading data
$sitecontext = context_system::instance();

// Setup page and check permissions
admin_externalpage_setup('priorityscales');

if ((!empty($moveup) or !empty($movedown))) {

    $move = NULL;
    $swap = NULL;

    // Get value to move, and value to replace
    if (!empty($moveup)) {
        $move = $DB->get_record('dp_priority_scale', array('id' => $moveup));
        $resultset = $DB->get_records_sql("
            SELECT *
            FROM {dp_priority_scale}
            WHERE
            sortorder < ?
            ORDER BY sortorder DESC", array($move->sortorder), 0, 1
        );
        if ($resultset && count($resultset)) {
            $swap = reset($resultset);
            unset($resultset);
        }
    } else {
        $move = $DB->get_record('dp_priority_scale', array('id' => $movedown));
        $resultset = $DB->get_records_sql("
            SELECT *
            FROM {dp_priority_scale}
            WHERE
            sortorder > ?
            ORDER BY sortorder ASC", array($move->sortorder), 0, 1
        );
        if ($resultset && count($resultset)) {
            $swap = reset($resultset);
            unset($resultset);
        }
    }

    if ($swap && $move) {
        // Swap sortorders
        $transaction = $DB->start_delegated_transaction();

        $DB->set_field('dp_priority_scale', 'sortorder', $move->sortorder, array('id' => $swap->id));
        $DB->set_field('dp_priority_scale', 'sortorder', $swap->sortorder, array('id' => $move->id));

        $transaction->allow_commit();
    }
}

if ($delete) {
    if (!$scale = $DB->get_record('dp_priority_scale', array('id' => $delete))) {
        print_error('error:invalidpriorityscaleid', 'totara_plan');
    }
    if (dp_priority_scale_is_used($delete)) {
        print_error('error:nodeletepriorityscaleinuse', 'totara_plan');
    }
    if (dp_priority_scale_is_assigned($delete)) {
        print_error('error:nodeletepriorityscaleassigned', 'totara_plan');
    }

    if ($confirm) {
        if (!confirm_sesskey()) {
            print_error('confirmsesskeybad', 'error');
        }

        $DB->delete_records('dp_priority_scale_value', array('priorityscaleid' => $scale->id)); // Delete scale values
        $DB->delete_records('dp_priority_scale', array('id' => $scale->id)); // Delete scale itself
        totara_set_notification(get_string('deletedpriorityscale', 'totara_plan', format_string($scale->name)), $CFG->wwwroot.'/totara/plan/priorityscales/index.php', array('class' => 'notifysuccess'));

    } else {
        $returnurl = new moodle_url('/totara/plan/priorityscales/index.php');
        $deleteurl = new moodle_url('/totara/plan/priorityscales/index.php', array('delete' => $delete, 'confirm' => '1', 'sesskey' => sesskey()));

        echo $OUTPUT->header();
        $strdelete = get_string('deletecheckpriority', 'totara_plan');
        $strbreak = html_writer::empty_tag('br') . html_writer::empty_tag('br');

        echo $OUTPUT->confirm("{$strdelete}{$strbreak}".format_string($scale->name), $deleteurl, $returnurl);

        echo $OUTPUT->footer();
        exit;
    }
}

/// Build page
echo $OUTPUT->header();

$priorities = dp_get_priorities();
dp_priority_display_table($priorities, $editingon=1);

echo $OUTPUT->footer();
