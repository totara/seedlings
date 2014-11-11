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
 * @package totara
 * @subpackage plan
 */

/**
 * Workflow settings page for development plan templates
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/totara/plan/lib.php');
require_once('template_forms.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

$id = optional_param('id', null, PARAM_INT);
$save = optional_param('save', false, PARAM_BOOL);
$moveup = optional_param('moveup', 0, PARAM_INT);
$movedown = optional_param('movedown', 0, PARAM_INT);
$hide = optional_param('hide', 0, PARAM_INT);
$show = optional_param('show', 0, PARAM_INT);

admin_externalpage_setup('managetemplates');

if (!$template = $DB->get_record('dp_template', array('id' => $id))) {
    print_error('error:invalidtemplateid', 'totara_plan');
}

$returnurl = $CFG->wwwroot . '/totara/plan/template/components.php?id=' . $id;

if ($save) {
    if (update_plan_component_name('componentname', $id)) {
        totara_set_notification(get_string('update_components_settings', 'totara_plan'), $returnurl, array('class' => 'notifysuccess'));
    } else {
        totara_set_notification(get_string('error:update_components_settings', 'totara_plan'), $returnurl);
    }
}

if ((!empty($moveup) or !empty($movedown))) {

    $move = NULL;
    $swap = NULL;

    // Get value to move, and value to replace
    if (!empty($moveup)) {
        $move = $DB->get_record('dp_component_settings', array('id' => $moveup));
        $resultset = $DB->get_records_sql("
            SELECT *
            FROM {dp_component_settings}
            WHERE
            templateid = ?
            AND sortorder < ?
            ORDER BY sortorder DESC", array($template->id, $move->sortorder), 0, 1
        );
        if ($resultset && count($resultset)) {
            $swap = reset($resultset);
            unset($resultset);
        }
    } else {
        $move = $DB->get_record('dp_component_settings', array('id' => $movedown));
        $resultset = $DB->get_records_sql("
            SELECT *
            FROM {dp_component_settings}
            WHERE
            templateid = ?
            AND sortorder > ?
            ORDER BY sortorder ASC", array($template->id, $move->sortorder), 0, 1
        );
        if ($resultset && count($resultset)) {
            $swap = reset($resultset);
            unset($resultset);
        }
    }

    if ($swap && $move) {
        // Swap sortorders
        $transaction = $DB->start_delegated_transaction();
        $DB->set_field('dp_component_settings', 'sortorder', $move->sortorder, array('id' => $swap->id));
        $DB->set_field('dp_component_settings', 'sortorder', $swap->sortorder, array('id' => $move->id));

        $transaction->allow_commit();
    }
}

if ($show) {
    if ($component = $DB->get_record('dp_component_settings', array('id' => $show))) {
        $transaction = $DB->start_delegated_transaction();
        $enabled = 1;
        $DB->set_field('dp_component_settings', 'enabled', $enabled, array('id' => $component->id));
        $transaction->allow_commit();
        $plans = $DB->get_records('dp_plan', array('templateid' => $template->id), '', 'id');
        dp_plan_check_plan_complete(array_keys($plans));
    }

}

if ($hide) {
    if ($component = $DB->get_record('dp_component_settings', array('id' => $hide))) {
        $transaction = $DB->start_delegated_transaction();
        $enabled = 0;
        $DB->set_field('dp_component_settings', 'enabled', $enabled, array('id' => $component->id));
        $transaction->allow_commit();
        $plans = $DB->get_records('dp_plan', array('templateid' => $template->id), '', 'id');
        dp_plan_check_plan_complete(array_keys($plans));
    }
}

$PAGE->navbar->add(get_string("managetemplates", "totara_plan"), new moodle_url("/totara/plan/template/index.php"));
$PAGE->navbar->add(format_string($template->fullname));

echo $OUTPUT->header();

if ($template) {
    echo $OUTPUT->heading(format_string($template->fullname));
} else {
    echo $OUTPUT->heading(get_string('newtemplate', 'totara_plan'));
}

$currenttab = 'components';
require('tabs.php');

echo $OUTPUT->heading_with_help(get_string('componentsettings', 'totara_plan'), 'templatecomponentsettings', 'totara_plan');

$components = $DB->get_records('dp_component_settings', array('templateid' => $id), 'sortorder');

if ($components) {
    $str_disable = get_string('disable');
    $str_enable = get_string('enable');
    $str_moveup = get_string('moveup');
    $str_movedown = get_string('movedown');

    $columns[] = 'component';
    $headers[] = get_string('component', 'totara_plan');
    $columns[] = 'options';
    $headers[] = get_string('options', 'totara_plan');

    $table = new flexible_table('components');
    $table->define_baseurl('/totara/plan/template/components.php?id=' . $id);
    $table->define_columns($columns);
    $table->define_headers($headers);
    $table->set_attribute('id', 'dpcomponents');
    $table->column_class('component', 'component');
    $table->column_class('options', 'options');

    $table->setup();
    $spacer = $OUTPUT->spacer(array('width' => 11, 'height' => 11));
    $count = 0;
    $numvalues = count($components);
    foreach ($components as $component) {
        $count++;
        $tablerow = array();
        $buttons = array();
        $configsetting = get_config(null, 'dp_'.$component->component);
        $cssclass = !$component->enabled ? 'dimmed' : '';
        $compname = $configsetting ? $configsetting : get_string($component->component.'plural', 'totara_plan');
        $tablerow[] = html_writer::tag('span', $compname, array('class' => $cssclass));

        if ($component->enabled) {
            $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/plan/template/components.php', array('id' => $id, 'hide' => $component->id)), new pix_icon('t/hide', $str_disable));
        } else {
            $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/plan/template/components.php', array('id' => $id, 'show' => $component->id)), new pix_icon('t/show', $str_enable));
        }

        if ($count > 1) {
            $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/plan/template/components.php', array('id' => $id, 'moveup' => $component->id)), new pix_icon('t/up', $str_moveup));
        } else {
            $buttons[] = $spacer;
        }

        // If value can be moved down
        if ($count < $numvalues) {
            $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/plan/template/components.php', array('id' => $id, 'movedown' => $component->id)), new pix_icon('t/down', $str_movedown));
        } else {
            $buttons[] = $spacer;
        }

        $tablerow[] = implode($buttons, '');
        $table->add_data($tablerow);
    }
    $table->finish_html();
}

echo $OUTPUT->footer();
