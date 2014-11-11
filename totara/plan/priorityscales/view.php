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
require_once('lib.php');
require_once($CFG->dirroot . '/totara/plan/lib.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

///
/// Setup / loading data
///

$id = required_param('id', PARAM_INT); // Priority Scale ID
$moveup = optional_param('moveup', 0, PARAM_INT); // Move up
$movedown = optional_param('movedown', 0, PARAM_INT); // Move down
$default = optional_param('default', 0, PARAM_INT); // Set default value
$delete = optional_param('delete', 0, PARAM_INT); //Item to be deleted
$confirm = optional_param('confirm', 0, PARAM_INT); //Confirmation of delete

// Page setup and check permissions
admin_externalpage_setup('priorityscales');
$context = context_system::instance();

require_capability('totara/plan:managepriorityscales', $context);

if (!$priority = $DB->get_record('dp_priority_scale', array('id' => $id))) {
    print_error('error:priorityscaleidincorrect', 'totara_plan');
}
$scale_used = dp_priority_scale_is_used($id);

// Delete logic
if ($delete) {
    if (!$value = $DB->get_record('dp_priority_scale_value', array('id' => $delete))) {
        print_error('error:invalidpriorityscalevalueid', 'totara_plan');
    }
    if ($scale_used) {
        print_error('error:nodeletepriorityscalevalueinuse', 'totara_plan');
    }

    if ($value->id == $priority->defaultid) {
        print_error('error:nodeletepriorityscalevaluedefault', 'totara_plan');
    }

    if ($confirm) {
        if (!confirm_sesskey()) {
            print_error('confirmsesskeybad', 'error');
        }

        $transaction = $DB->start_delegated_transaction();

        $DB->delete_records('dp_priority_scale_value', array('id' => $delete));
        $sql = "UPDATE {dp_priority_scale_value} SET sortorder = sortorder-1 WHERE priorityscaleid = ? AND sortorder > ?";
        $DB->execute($sql, array($priority->id, $value->sortorder));

        $transaction->allow_commit();
        totara_set_notification(get_string('deletedpriorityscalevalue', 'totara_plan', format_string($value->name)), $CFG->wwwroot.'/totara/plan/priorityscales/view.php?id='.$priority->id, array('class' => 'notifysuccess'));
    } else {
        $returnurl = new moodle_url('/totara/plan/priorityscales/view.php', array('id' => $priority->id));
        $deleteurl = new moodle_url('/totara/plan/priorityscales/view.php', array('id' => $priority->id, 'delete' => $delete, 'confirm' => '1', 'sesskey' => sesskey()));

        echo $OUTPUT->header();
        $strdelete = get_string('deletecheckpriorityvalue', 'totara_plan');
        $strbreak = html_writer::empty_tag('br') . html_writer::empty_tag('br');

        echo $OUTPUT->confirm("{$strdelete}{$strbreak}".format_string($value->name), $deleteurl, $returnurl);

        echo $OUTPUT->footer();
        exit;
    }
}


// Cache text
$str_edit = get_string('edit');
$str_delete = get_string('delete');
$str_moveup = get_string('moveup');
$str_movedown = get_string('movedown');
$str_changeto = get_string('changeto', 'totara_plan');
$str_set = get_string('set', 'totara_plan');


///
/// Process any actions
///

/// Move a value up or down
if ((!empty($moveup) or !empty($movedown))) {

    // Can't reorder a scale that's in use
    if  ($scale_used) {
        $returnurl = new moodle_url('/totara/plan/priorityscales/view.php', array('id' => $priority->id));
        print_error('error:noreorderpriorityinuse', 'totara_plan', $returnurl);
    }

    $move = NULL;
    $swap = NULL;

    // Get value to move, and value to replace
    if (!empty($moveup)) {
        $move = $DB->get_record('dp_priority_scale_value', array('id' => $moveup));
        $resultset = $DB->get_records_sql("
            SELECT *
            FROM {dp_priority_scale_value}
            WHERE
            priorityscaleid = ?
            AND sortorder < ?
            ORDER BY sortorder DESC", array($priority->id, $move->sortorder), 0, 1
        );
        if ($resultset && count($resultset)) {
            $swap = reset($resultset);
            unset($resultset);
        }
    } else {
        $move = $DB->get_record('dp_priority_scale_value', array('id' => $movedown));
        $resultset = $DB->get_records_sql("
            SELECT *
            FROM {dp_priority_scale_value}
            WHERE
            priorityscaleid = ?
            AND sortorder > ?
            ORDER BY sortorder ASC", array($priority->id, $move->sortorder), 0, 1
        );
        if ($resultset && count($resultset)) {
            $swap = reset($resultset);
            unset($resultset);
        }
    }

    if ($swap && $move) {
        // Swap sortorders
        $transaction = $DB->start_delegated_transaction();

        $DB->set_field('dp_priority_scale_value', 'sortorder', $move->sortorder, array('id' => $swap->id));
        $DB->set_field('dp_priority_scale_value', 'sortorder', $swap->sortorder, array('id' => $move->id));

        $transaction->allow_commit();
    }
}

// Handle default setting
if ($default) {
    $value = $default;

    // Check value exists
    $DB->get_record('dp_priority_scale_value', array('id' => $value));

    // Update
    $s = new stdClass();
    $s->id = $priority->id;
    $s->defaultid = $default;

    $DB->update_record('dp_priority_scale', $s);
    totara_set_notification(get_string('priorityscaledefaultupdated', 'totara_plan'), null, array('class' => 'notifysuccess'));
    // Fetch the update scale record so it'll show up to the user.
    $priority = $DB->get_record('dp_priority_scale', array('id' => $id));
}

///
/// Display page
///

// Load values
$values = $DB->get_records('dp_priority_scale_value', array('priorityscaleid' => $priority->id), 'sortorder');

$PAGE->navbar->add(format_string($priority->name));

echo $OUTPUT->header();

echo $OUTPUT->single_button(new moodle_url('/totara/plan/priorityscales/index.php'), get_string('allpriorityscales', 'totara_plan'), 'get');

// Display info about scale
echo $OUTPUT->heading(get_string('priorityscalex', 'totara_plan', format_string($priority->name)));
$priority->description = file_rewrite_pluginfile_urls($priority->description, 'pluginfile.php', $context->id, 'totara_plan', 'dp_priority_scale', $priority->id);
echo html_writer::tag('p', format_text($priority->description, FORMAT_HTML));

// Display warning if scale is in use
if ($scale_used) {
    echo $OUTPUT->container(get_string('priorityscaleinuse', 'totara_plan'), 'notifymessage');
}

// Display priority scale values
if ($values) {
    echo html_writer::start_tag('form', array('id' => "dppriorityscaledefaultform", 'action' => new moodle_url('/totara/plan/priorityscales/view.php', array('id' => $id)), 'method' => "POST"));
    echo html_writer::empty_tag('input', array('type' => "hidden", 'name' => "id", 'value' => $id));

    $table = new html_table();
    $table->attributes = array('class' => 'generaltable');

    // Headers
    $table->head = array(get_string('name'));
    $table->align = array('left');

    $table->head[] = get_string('defaultvalue', 'totara_plan').' '.
        $OUTPUT->help_icon('priorityscaledefault', 'totara_plan', false);
    $table->align[] = 'center';

    $table->head[] = get_string('edit');
    $table->align[] = 'center';

    $spacer = $OUTPUT->spacer(array('width' => 11, 'height' => 11));
    $numvalues = count($values);

    // Add rows to table
    $count = 0;
    foreach ($values as $value) {
        $count++;
        $row = array();
        $buttons = array();
        $row[] = format_string($value->name);


        // Is this the default value?
        $disabled = ($numvalues == 1) ? 'disabled' : '';
        if ($value->id == $priority->defaultid) {
            $row[] = html_writer::empty_tag('input', array('type' => "radio", 'name' => "default", 'value' => $value->id, 'checked' => "checked", 'disabled' => $disabled));
        }
        else {
            $row[] = html_writer::empty_tag('input', array('type' => "radio", 'name' => "default", 'value' => $value->id, $disabled => $disabled));
        }

        $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/plan/priorityscales/editvalue.php', array('id' => $value->id)), new pix_icon('t/edit', $str_edit));

        if (!$scale_used) {
            if ($value->id == $priority->defaultid) {
                $buttons[] = $OUTPUT->pix_icon('t/delete_grey', get_string('error:nodeletepriorityscalevaluedefault', 'totara_plan'), 'totara_core',
                    array('class' => 'iconsmall action-icon', 'title' => get_string('error:nodeletepriorityscalevaluedefault', 'totara_plan')));
            } else {
                $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/plan/priorityscales/view.php', array('id' => $priority->id, 'delete' => $value->id)), new pix_icon('t/delete', $str_delete));
            }
        }

        // If value can be moved up
        if ($count > 1 && !$scale_used) {
            $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/plan/priorityscales/view.php', array('id' => $priority->id, 'moveup' => $value->id)), new pix_icon('t/up', $str_moveup));
        } else {
            $buttons[] = $spacer;
        }

        // If value can be moved down
        if ($count < $numvalues && !$scale_used) {
            $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/plan/priorityscales/view.php', array('id' => $priority->id, 'movedown' => $value->id)), new pix_icon('t/down', $str_movedown));
        } else {
            $buttons[] = $spacer;
        }

        $row[] = implode($buttons, '');
        $table->data[] = $row;
    }

    if ($numvalues != 1) {
        $row = array();
        $row[] = '';
        $row[] = html_writer::empty_tag('input', array('type' => 'submit', 'value' => 'Update'));
        $row[] = '';
        $table->data[] = $row;
    }

    echo html_writer::table($table);
    echo html_writer::end_tag('form');
} else {
    echo html_writer::empty_tag('br') . $OUTPUT->container(get_string('nopriorityscalevalues', 'totara_plan')) . html_writer::empty_tag('br');
}

$button = '';
// Print button for creating new priority scale value
if (!$scale_used) {
    $options = array('priorityscaleid' => $priority->id);
    $button = $OUTPUT->single_button(new moodle_url('/totara/plan/priorityscales/editvalue.php', $options), get_string('addnewpriorityvalue', 'totara_plan'), 'get');
}

// Navigation / editing buttons
echo $OUTPUT->container($button, "buttons");

/// and proper footer
echo $OUTPUT->footer();
