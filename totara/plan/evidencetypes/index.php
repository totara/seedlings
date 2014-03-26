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
 * @author Alastair Munro <alastair@catalyst.net.nz>
 * @author Simon Coggins <simonc@catalyst.net.nz>
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

$deleteid = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$moveup = optional_param('moveup', 0, PARAM_INT);
$movedown = optional_param('movedown', 0, PARAM_INT);

// Permissions
admin_externalpage_setup('evidencetypes');
$context = context_system::instance();
require_capability('totara/plan:manageevidencetypes', $context);

if (!empty($deleteid)) {
    $indexurl = new moodle_url('/totara/plan/evidencetypes/index.php');

    if (!$item = $DB->get_record('dp_evidence_type', array('id' => $deleteid))) {
        print_error('error:invalidevidencetypeid', 'totara_plan');
    }

    if ($confirm) {
        if (!confirm_sesskey()) {
            print_error('confirmsesskeybad', 'error');
        }

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('dp_evidence_type', array('id' => $item->id));
        $sql = "UPDATE {dp_plan_evidence} SET evidencetypeid = NULL WHERE evidencetypeid = ?";
        $DB->execute($sql, array($deleteid));
        $transaction->allow_commit();
        totara_set_notification(get_string('deletedevidencetype', 'totara_plan',
                format_string($item->name)),
                $indexurl,
                array('class' => 'notifysuccess'));
    } else {
        $deletemsg = get_string('deletecheckevidencetype', 'totara_plan');
        if (dp_evidence_type_is_used($deleteid)) {
            $deletemsg .= '<br/><br/>' . get_string('deletecheckevidencetypeinuse', 'totara_plan');
        }
        $deleteurl = new moodle_url('/totara/plan/evidencetypes/index.php',
            array('delete' => $deleteid, 'confirm' => '1', 'sesskey' => sesskey()));
        echo $OUTPUT->header();
        echo $OUTPUT->confirm($deletemsg . "<br /><br />" . format_string($item->name), $deleteurl, $indexurl);
        echo $OUTPUT->footer();
    }
} else {
    if ((!empty($moveup) || !empty($movedown))) {
        if (!empty($moveup)) {
            $thisid = $moveup;
            // Get the sort order previous to this one
            $sqlminmax = "SELECT MAX(sortorder) FROM {dp_evidence_type} WHERE sortorder < ?";
        } else {
            $thisid = $movedown;
            // Get the sort order after this one
            $sqlminmax = "SELECT MIN(sortorder) FROM {dp_evidence_type} WHERE sortorder > ?";
        }

        $swapid = 0;

        // Get the current sort order
        if ($thissortorder = $DB->get_field('dp_evidence_type', 'sortorder', array('id' => $thisid))) {
            // Get the previous/next sort order

            if ($swapsortorder = $DB->get_field_sql($sqlminmax, array($thissortorder))) {
                // Get the id of the previous/next record
                $swapid = $DB->get_field('dp_evidence_type', 'id', array('sortorder' => $swapsortorder));

                if (!empty($swapid)) {
                    // We have a winner
                    $transaction = $DB->start_delegated_transaction();
                    if (!($DB->set_field('dp_evidence_type', 'sortorder', $thissortorder, array('id' => $swapid))
                        && $DB->set_field('dp_evidence_type', 'sortorder', $swapsortorder, array('id' => $thisid)) )) {
                        print_error('error:updateevidencetypeordering', 'totara_plan');
                    }
                    $transaction->allow_commit();
                }
            }
        }
    }

    $items = $DB->get_records('dp_evidence_type', null, 'sortorder');

    $table = new html_table();
    $table->head  = array(
        get_string('evidencetypename', 'totara_plan'),
        get_string('used'),
        get_string('options', 'totara_core'));

    $table->data = array();
    $count = 0;
    $numvalues = count($items);
    foreach ($items as $item) {
        $count++;
        $line = array();

        // View
        $line[] = $OUTPUT->action_link(
                new moodle_url("/totara/plan/evidencetypes/view.php", array('id' => $item->id)),
                format_string($item->name));

        if (dp_evidence_type_is_used($item->id)) {
            $line[] = get_string('yes');
        } else {
            $line[] = get_string('no');
        }

        $buttons = array();

        // Edit
        $buttons[] = $OUTPUT->action_icon(
                new moodle_url("/totara/plan/evidencetypes/edit.php", array('id' => $item->id)),
                new pix_icon('t/edit', get_string('edit')));

        // Delete
        $buttons[] = $OUTPUT->action_icon(
                new moodle_url("/totara/plan/evidencetypes/index.php", array('delete' => $item->id)),
                new pix_icon('t/delete', get_string('delete')));

        // If value can be moved up
        if ($count > 1) {
            $buttons[] = $OUTPUT->action_icon(
                    new moodle_url("/totara/plan/evidencetypes/index.php", array('moveup' => $item->id)),
                    new pix_icon('t/up', get_string('moveup')));
        } else {
            $buttons[] = $OUTPUT->spacer(array('width' => 11, 'height' => 11));
        }

        // If value can be moved down
        if ($count < $numvalues) {
            $buttons[] = $OUTPUT->action_icon(
                    new moodle_url("/totara/plan/evidencetypes/index.php", array('movedown' => $item->id)),
                    new pix_icon('t/down', get_string('movedown')));
        } else {
            $buttons[] = $OUTPUT->spacer(array('width' => 11, 'height' => 11));
        }
        $line[] = implode($buttons, ' ');

        $table->data[] = $line;
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('evidencetypes', 'totara_plan'));

    if ($items) {
        echo html_writer::table($table);
    } else {
        echo html_writer::tag('p', get_string('noevidencetypesdefined', 'totara_plan'));
    }

    // Add type
    echo $OUTPUT->single_button(
            new moodle_url("/totara/plan/evidencetypes/edit.php"),
            get_string('evidencetypecreate', 'totara_plan'), 'get');

    echo $OUTPUT->footer();
}
