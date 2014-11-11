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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @package totara
 * @subpackage plan
 */

/**
 * Page containing list of plan templates
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/totara/plan/lib.php');
require_once($CFG->dirroot.'/totara/core/js/lib/setup.php');
require_once('template_forms.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

$notice = optional_param('notice', 0, PARAM_INT); // notice flag
$hide = optional_param('hide', 0, PARAM_INT);
$show = optional_param('show', 0, PARAM_INT);
$moveup = optional_param('moveup', 0, PARAM_INT);
$movedown = optional_param('movedown', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$default = optional_param('default' , 0, PARAM_INT);
$confirm = optional_param('confirm', false, PARAM_BOOL);

admin_externalpage_setup('managetemplates');

// Javascript include
local_js(array(
    TOTARA_JS_DIALOG
));

$returnurl = new moodle_url('/totara/plan/template/index.php');

if ($show) {
    if ($template = $DB->get_record('dp_template', array('id' => $show))) {
        $visible = 1;
        $DB->set_field('dp_template', 'visible', $visible, array('id' => $template->id));
    }
}

if ($hide) {
    if ($template = $DB->get_record('dp_template', array('id' => $hide))) {
        if ($template->isdefault == 1) {
            $message = get_string('cannothidedefault', 'totara_plan');
            totara_set_notification($message, new moodle_url('/totara/plan/template/index.php'));
        }
        $visible = 0;
        $DB->set_field('dp_template', 'visible', $visible, array('id' => $template->id));
    }
}

if ((!empty($moveup) or !empty($movedown))) {

    $move = NULL;
    $swap = NULL;

    // Get value to move, and value to replace
    if (!empty($moveup)) {
        $move = $DB->get_record('dp_template', array('id' => $moveup));
        $resultset = $DB->get_records_sql("
            SELECT *
            FROM {dp_template}
            WHERE
            sortorder < ?
            ORDER BY sortorder DESC", array($move->sortorder), 0, 1
        );
        if (!empty($resultset)) {
            $swap = reset($resultset);
            unset($resultset);
        }
    } else {
        $move = $DB->get_record('dp_template', array('id' => $movedown));
        $resultset = $DB->get_records_sql("
            SELECT *
            FROM {dp_template}
            WHERE
            sortorder > ?
            ORDER BY sortorder ASC", array($move->sortorder), 0, 1
        );
        if (!empty($resultset)) {
            $swap = reset($resultset);
            unset($resultset);
        }
    }

    if ($swap && $move) {
        // Swap sortorders
            $transaction = $DB->start_delegated_transaction();

            $DB->set_field('dp_template', 'sortorder', $move->sortorder, array('id' => $swap->id));
            $DB->set_field('dp_template', 'sortorder', $swap->sortorder, array('id' => $move->id));

            $transaction->allow_commit();
    }
}

if ($default && $DB->record_exists('dp_template', array('id' => $default, 'visible' => true))) {
    $transaction = $DB->start_delegated_transaction();
    // Unset current default
    $DB->execute('UPDATE {dp_template} SET isdefault = 0 WHERE isdefault = 1');

    // Set new current
    $todb = new stdClass();
    $todb->id = $default;
    $todb->isdefault = 1;
    $DB->update_record('dp_template', $todb);

    $transaction->allow_commit();
}

if ($delete && $confirm) {
    if (confirm_sesskey()) {
        $transaction = $DB->start_delegated_transaction();

        $DB->delete_records('dp_template',              array('id'         => $delete));
        $DB->delete_records('dp_component_settings',    array('templateid' => $delete));
        $DB->delete_records('dp_competency_settings',   array('templateid' => $delete));
        $DB->delete_records('dp_course_settings',       array('templateid' => $delete));
        $DB->delete_records('dp_objective_settings',    array('templateid' => $delete));
        $DB->delete_records('dp_permissions',           array('templateid' => $delete));

        $transaction->allow_commit();
        totara_set_notification(get_string('deletedp', 'totara_plan'), new moodle_url('/totara/plan/template/index.php'), array('class' => 'notifysuccess'));
    }
} else if ($delete) {
    $template = $DB->get_record('dp_template', array('id' => $delete));

    if ($DB->count_records('dp_plan', array('templateid' => $template->id)) > 0) {
        totara_set_notification(get_string('cannotdelete_inuse', 'totara_plan'), $CFG->wwwroot.'/totara/plan/template/index.php');
    }

    if ($template->isdefault == 1) {
        totara_set_notification(get_string('cannotdeletetemplate_default', 'totara_plan'), new moodle_url('/totara/plan/template/index.php'));
    }

    echo $OUTPUT->header();
    $deleteurl = new moodle_url('/totara/plan/template/index.php', array('delete' => $delete, 'confirm' => 'true', 'sesskey' => sesskey()));
    $returnurl = new moodle_url('/totara/plan/template/index.php');
    $strdelete = get_string('deletecheckdptemplate', 'totara_plan');
    $strbreak = html_writer::empty_tag('br') . html_writer::empty_tag('br');

    echo $OUTPUT->confirm("{$strdelete}{$strbreak}".format_string($template->fullname), $deleteurl, $returnurl);

    echo $OUTPUT->footer();
    exit;
}

$mform = new dp_template_new_form();

if ($mform->is_cancelled()) {
    redirect($returnurl);
}
if ($fromform = $mform->get_data()) {
    if (empty($fromform->submitbutton)) {
        redirect($returnurl);
    }
    else {
        if (!$DB->count_records('dp_priority_scale')) {
            print_error('error:notemplatewithoutpriorityscale', 'totara_plan');
        }
        if (!$DB->count_records('dp_objective_scale')) {
            print_error('error:notemplatewithoutobjectivescale', 'totara_plan');
        }

        $error = '';
        $newtemplateid = dp_create_template($fromform->templatename, $fromform->enddate, $error);

        if ($newtemplateid) {
            redirect(new moodle_url('/totara/plan/template/general.php', array('id' => $newtemplateid)));
        } else {
            totara_set_notification($error, $CFG->wwwroot . '/totara/plan/template/index.php');
        }
    }
}

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('managetemplates', 'totara_plan'));

$templates = $DB->get_records('dp_template', null, 'sortorder');

if ($templates) {
    $str_hide = get_string('hide');
    $str_show = get_string('show');
    $str_edit = get_string('edit');
    $str_default = get_string('default');
    $str_remove = get_string('delete');
    $str_remove_default = get_string('deletedefault', 'totara_plan');
    $str_moveup = get_string('moveup');
    $str_movedown = get_string('movedown');

    $columns[] = 'name';
    $headers[] = get_string('name', 'totara_plan');
    $columns[] = 'default';
    $headers[] = get_string('default');
    $columns[] = 'instances';
    $headers[] = get_string('instances', 'totara_plan');
    $columns[] = 'options';
    $headers[] = get_string('options', 'totara_plan');
    $baseurl = $CFG->wwwroot . '/totara/plan/template/index.php';

    $table = new flexible_table('Templates');
    echo html_writer::start_tag('form', array('id' => 'plantemplatedefaultform', 'action' => new moodle_url('/totara/plan/template/index.php'), 'method' => 'POST'));
    $table->define_columns($columns);
    $table->define_headers($headers);
    $table->define_baseurl($baseurl);
    $table->set_attribute('class', 'generalbox dp-templates');

    $table->setup();
    $spacer = $OUTPUT->spacer(array('width' => 11, 'height' => 11));
    $count = 0;
    $numvalues = count($templates);
    foreach ($templates as $template) {
        $tablerow = array();
        $buttons = array();
        $count++;

        $cssclass = !$template->visible ? 'dimmed' : '';

        $title = html_writer::link(new moodle_url('/totara/plan/template/general.php', array('id' => $template->id)), format_string($template->fullname), array('class' => $cssclass));

        if ($template->isdefault == 1) {
            $title .= ' ('.get_string('default').')';
        }
        $tablerow[] = $title;

        $disabled = ($template->visible != 1) ? 'disabled' : '';

        if ($template->isdefault == 1) {
            $tablerow[] = html_writer::empty_tag('input', array('type' => 'radio', 'name' => 'default', 'value' => $template->id, 'checked' => 'checked'));
        }
        else {
            $tablerow[] = html_writer::empty_tag('input', array('type' => 'radio', 'name' => 'default', 'value' => $template->id, $disabled => $disabled));
        }

        $instancecount = $DB->count_records('dp_plan', array('templateid' => $template->id));
        if ($instancecount) {
            $tablerow[] = html_writer::link(new moodle_url('/totara/plan/template/templateinstances.php', array('id' => $template->id)), $instancecount);
        } else {
            $tablerow[] = $instancecount;
        }

        $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/plan/template/general.php', array('id' => $template->id)), new pix_icon('t/edit', $str_edit));

        if ($template->isdefault == 1) {
            $buttons[] = $OUTPUT->pix_icon('t/delete_grey', $str_remove_default, 'totara_core', array('class' => 'action-icon iconsmall'));
        } else {
            $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/plan/template/index.php', array('delete' => $template->id)), new pix_icon('t/delete', $str_remove));
        }

        if ($template->isdefault == 1) {
            $buttons[] = $spacer;
        } else {
            if (!empty($template->visible)) {
                $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/plan/template/index.php', array('hide' => $template->id)), new pix_icon('t/hide', $str_hide));
            } else {
                $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/plan/template/index.php', array('show' => $template->id)), new pix_icon('t/show', $str_show));
            }
        }

        if ($count > 1) {
            $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/plan/template/index.php', array('moveup' => $template->id)), new pix_icon('t/up', $str_moveup));
        } else {
            $buttons[] = $spacer;
        }

        // If value can be moved down
        if ($count < $numvalues) {
            $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/plan/template/index.php', array('movedown' => $template->id)), new pix_icon('t/down', $str_movedown));
        } else {
            $buttons[] = $spacer;
        }

        $tablerow[] = implode($buttons, '');

        $table->add_data($tablerow);
    }

    $updaterow = array();

    $updaterow[] = '';
    $updaterow[] = html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('update')));
    $updaterow[] = '';
    $updaterow[] = '';
    $table->add_data($updaterow, 'last');

    $table->finish_html();
    echo html_writer::end_tag('form');
}
else {
    echo get_string('notemplates', 'totara_plan');
}

$mform->display();

echo $OUTPUT->footer();
