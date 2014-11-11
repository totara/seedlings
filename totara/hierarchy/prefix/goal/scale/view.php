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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage totara_hierarchy
 */

require_once(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/totara/hierarchy/lib.php');
require_once($CFG->dirroot . '/totara/hierarchy/prefix/goal/lib.php');
require_once('lib.php');

// Check if Goals are enabled.
goal::check_feature_enabled();

//
// Setup / loading data.
//

// Goal scale id.
$id = required_param('id', PARAM_INT);
// Move up / down.
$moveup = optional_param('moveup', 0, PARAM_INT);
$movedown = optional_param('movedown', 0, PARAM_INT);
// Set default value.
$default = optional_param('default', 0, PARAM_INT);
$prefix = required_param ('prefix', PARAM_ALPHA);
$scale_used = goal_scale_is_used($id);

$sitecontext = context_system::instance();
$hierarchy = hierarchy::load_hierarchy($prefix);

// Cache user capabilities.
extract($hierarchy->get_permissions());

$goalscalestr = get_string('goalscale', 'totara_hierarchy');
$pageurl = new moodle_url('/totara/hierarchy/prefix/goal/scale/view.php', array('id' => $id, 'prefix' => $prefix));

// Permissions.
if (!$canviewscales) {
    print_error('accessdenied', 'admin');
}

// Set up the page.
if ($canmanage) {
    // If the user can update the framework then show the admin link.
    admin_externalpage_setup($prefix.'manage');
} else {
    $PAGE->set_url($pageurl);
    $PAGE->set_context($sitecontext);
    $PAGE->set_pagelayout('admin');
    $PAGE->set_totara_menu_selected('mygoals');
    $PAGE->set_title($goalscalestr);
    $PAGE->set_heading($goalscalestr);
}

if (!$scale = $DB->get_record('goal_scale', array('id' => $id))) {
    print_error('incorrectgoalscaleid', 'totara_hierarchy');
}

// Cache text.
$str_edit = get_string('edit');
$str_delete = get_string('delete');
$str_moveup = get_string('moveup');
$str_movedown = get_string('movedown');


//
// Process any actions.
//

if ($canupdatescales || $candeletescales) {
    // Move a value up or down.
    if ((!empty($moveup) or !empty($movedown))) {

        // Can't reorder a scale that's in use.
        if (goal_scale_is_used($scale->id)) {
            $returnurl = new moodle_url('/totara/hierarchy/prefix/goal/scale/view.php',
                    array('id' => $scale->id, 'prefix' => 'goal'));
            print_error('error:noreorderscaleinuse', 'totara_hierarchy', $returnurl);
        }

        $move = null;
        $swap = null;

        // Get value to move, and value to replace.
        if (!empty($moveup)) {
            $move = $DB->get_record('goal_scale_values', array('id' => $moveup));
            $resultset = $DB->get_records_sql("
                SELECT *
                FROM {goal_scale_values}
                WHERE
                    scaleid = ?
                    AND sortorder < ?
                ORDER BY sortorder DESC",
                array($scale->id, $move->sortorder), 0, 1
            );
            if ($resultset && count($resultset)) {
                $swap = reset($resultset);
                unset($resultset);
            }
        } else {
            $move = $DB->get_record('goal_scale_values', array('id' => $movedown));
            $resultset = $DB->get_records_sql("
                SELECT *
                FROM {goal_scale_values}
                WHERE
                    scaleid = ?
                    AND sortorder > ?
                ORDER BY sortorder ASC", array($scale->id, $move->sortorder), 0, 1
            );
            if ($resultset && count($resultset)) {
                $swap = reset($resultset);
                unset($resultset);
            }
        }

        if ($swap && $move) {
            // Swap sortorders.
            $transaction = $DB->start_delegated_transaction();

            if ($DB->set_field('goal_scale_values', 'sortorder', $move->sortorder, array('id' => $swap->id))
                && $DB->set_field('goal_scale_values', 'sortorder', $swap->sortorder, array('id' => $move->id))
            ) {
                $transaction->allow_commit();
            }
        }
    }

    // Handle default settings.
    if ($default) {

        // Check value exists.
        if (!$DB->get_record('goal_scale_values', array('id' => $default))) {
            print_error('incorrectgoalscalevalueid', 'totara_hierarchy');
        }

        // Update.
        $s = new stdClass();
        $s->id = $scale->id;

        if ($default) {
            $s->defaultid = $default;
        }

        if (!$DB->update_record('goal_scale', $s)) {
            print_error('updategoalscale', 'totara_hierarchy');
        } else {
            // Fetch the update scale record so it'll show up to the user.
            $scale = $DB->get_record('goal_scale', array('id' => $id));
            totara_set_notification(get_string('scaledefaultupdated', 'totara_hierarchy'), null,
                    array('class' => 'notifysuccess'));
        }
    }
}

//
// Display page.
//

// Load values.
$values = $DB->get_records('goal_scale_values', array('scaleid' => $scale->id), 'sortorder');

$PAGE->navbar->add(get_string("goalframeworks", 'totara_hierarchy'),
                    new moodle_url('/totara/hierarchy/framework/index.php', array('prefix' => 'goal')));
$PAGE->navbar->add(format_string($scale->name));

echo $OUTPUT->header();

echo $OUTPUT->action_link(new moodle_url('/totara/hierarchy/framework/index.php', array('prefix' => 'goal')),
    '&laquo; ' . get_string('allgoalscales', 'totara_hierarchy'));

// Display info about scale.
echo $OUTPUT->heading(get_string('scalex', 'totara_hierarchy', format_string($scale->name)));
$scale->description = file_rewrite_pluginfile_urls($scale->description, 'pluginfile.php', $sitecontext->id,
    'totara_hierarchy', 'goal_scale', $scale->id);
echo html_writer::tag('p', $scale->description);

// Display warning if scale is in use.
if ($canupdatescales && $scale_used) {
    echo $OUTPUT->container(get_string('goalscaleinuse', 'totara_hierarchy'), 'notifysuccess');
}


// Display warning if proficient values don't make sense.
$maxprof = $DB->get_field('goal_scale_values', 'MAX(sortorder)', array('proficient' => 1, 'scaleid' => $scale->id));
$minnoneprof = $DB->get_field('goal_scale_values', 'MIN(sortorder)', array('proficient' => 0, 'scaleid' => $scale->id));
if (isset($maxprof) && isset($minnoneprof) && $maxprof > $minnoneprof) {
    echo $OUTPUT->container(get_string('nonsensicalproficientvalues', 'totara_hierarchy'), 'notifyproblem');
}

// Display scale values.
if ($values) {
    if ($canupdatescales) {
        echo html_writer::start_tag('form', array('id' => 'compscaledefaultprofform',
            'action' => new moodle_url('/totara/hierarchy/prefix/goal/scale/view.php'), 'method' => 'POST'));
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $id));
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'prefix', 'value' => 'goal'));
        echo html_writer::empty_tag('br');
    }
    $table = new html_table();
    $table->data = array();
    // Headers.
    $table->head = array(get_string('name'));
    $table->align = array('left');

    $table->head[] = get_string('goalscaledefault', 'totara_hierarchy').' '.
        $OUTPUT->help_icon('goalscaledefault', 'totara_hierarchy');
    $table->align[] = 'center';

    $table->head[] = get_string('goalscaleproficient', 'totara_hierarchy').' '.
        $OUTPUT->help_icon('goalscaleproficient', 'totara_hierarchy');
    $table->align[] = 'center';

    if ($canupdatescales || $candeletescales) {
        $table->head[] = get_string('edit');
        $table->align[] = 'center';
    }

    $numvalues = count($values);

    // Add rows to table.
    $count = 0;
    // Get ID of the proficient scale value, if there is only one.
    $onlyprof = goal_scale_only_proficient_value($scale->id);
    foreach ($values as $value) {
        $count++;

        $row = array();
        $row[] = format_string($value->name);

        if ($canupdatescales || $candeletescales) {
            $buttons = array();
            $attributes = array('type' => 'radio', 'name' => 'default', 'value' => $value->id, 'id' => 'scaledefault' . $value->id);

            // There is only one value or they can't update.
            if ($numvalues == 1 || !$canupdatescales) {
                $attributes['disabled'] = 'disabled';
            }
            // It is the default value.
            if ($value->id == $scale->defaultid) {
                $attributes['checked'] = 'checked';
            }

            $labelattrs = array('for' => 'scaledefault' . $value->id, 'class' => 'accesshide');
            $label = html_writer::tag('label', get_string('goalscalesetdefault', 'totara_hierarchy', $value->name), $labelattrs);
            $row[] = html_writer::empty_tag('input', $attributes) . $label;


            // It is the proficient value.
            if ($value->proficient) {
                $row[] = get_string('yes');
            } else {
                $row[] = get_string('no');
            }

            if ($canupdatescales) {
            $buttons[] = $OUTPUT->action_icon(
                new moodle_url('/totara/hierarchy/prefix/goal/scale/editvalue.php', array('id' => $value->id, 'prefix' => 'goal')),
                new pix_icon('t/edit', $str_edit),
                null,
                array('class' => 'action-icon', 'title' => $str_edit));
            }

            if (!$scale_used && $candeletescales) {
                if ($value->id == $scale->defaultid) {
                    // Prevent deleting default value.
                    $buttons[] = $OUTPUT->pix_icon('t/delete_grey',
                            get_string('error:nodeletegoalscalevaluedefault', 'totara_hierarchy'), 'totara_core',
                            array('class' => 'iconsmall action-icon'));
                } else if ($value->id == $onlyprof) {
                    // Prevent deleting last proficient value.
                    $buttons[] = $OUTPUT->pix_icon('t/delete_grey',
                            get_string('error:nodeletegoalscalevalueonlyprof', 'totara_hierarchy'), 'totara_core',
                            array('class' => 'iconsmall action-icon'));
                } else {
                    $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/hierarchy/prefix/goal/scale/deletevalue.php',
                        array('id' => $value->id, 'prefix' => 'goal')), new pix_icon('t/delete', $str_delete), null,
                        array('class' => 'action-icon', 'title' => $str_delete));
                }
            }

            // If value can be moved up.
            if ($count > 1 && !$scale_used && $canupdatescales) {
                $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/hierarchy/prefix/goal/scale/view.php',
                    array('id' => $scale->id, 'moveup' => $value->id, 'prefix' => 'goal')),
                    new pix_icon('t/up', $str_moveup), null, array('class' => 'action-icon', 'title' => $str_moveup));
            } else {
                $buttons[] = $OUTPUT->spacer(array('height' => 11, 'width' => 11));
            }

            // If value can be moved down.
            if ($count < $numvalues && !$scale_used && $canupdatescales) {
                $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/hierarchy/prefix/goal/scale/view.php',
                    array('id' => $scale->id, 'movedown' => $value->id, 'prefix' => 'goal')),
                    new pix_icon('t/down', $str_movedown), null, array('class' => 'action-icon', 'title' => $str_movedown));
            } else {
                $buttons[] = $OUTPUT->spacer(array('height' => 11, 'width' => 11));
            }

            $row[] = implode($buttons, '');
        } else {
            // Show the default and complete values as uneditble values.
            // It is the default value.
            if ($value->id == $scale->defaultid) {
                $row[] = get_string('yes');
            } else {
                $row[] = get_string('no');
            }

            if ($value->proficient) {
                $row[] = get_string('yes');
            } else {
                $row[] = get_string('no');
            }
        }

        $table->data[] = $row;
    }

    if ($canupdatescales && $numvalues != 1) {
        $row = array();
        $row[] = '';
        $row[] = html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('update')));
        $row[] = '';
        $row[] = '';
        $table->data[] = $row;
    }
    echo html_writer::table($table);
    if ($canupdatescales) {
        echo html_writer::end_tag('form');
    }
} else {
    echo html_writer::empty_tag('br');
    echo html_writer::tag('div', get_string('noscalevalues', 'totara_hierarchy'));
    echo html_writer::empty_tag('br');
}


// Print button for creating new scale value.
$button_editscale = '';
if ($canupdatescales && !$scale_used) {
    $button_editscale = $OUTPUT->single_button(new moodle_url('/totara/hierarchy/prefix/goal/scale/editvalue.php',
        array('scaleid' => $scale->id, 'prefix' => 'goal')), get_string('addnewscalevalue', 'totara_hierarchy'), 'get');
}

// Navigation / editing buttons.
echo html_writer::tag('div', $button_editscale, array('class' => "buttons"));

// And proper footer.
echo $OUTPUT->footer();
