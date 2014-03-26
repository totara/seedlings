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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage totara_hierarchy
 */
/**
 * template/lib.php
 *
 * Library of functions related to competency templates.
 *
 * Note: Functions in this library should have names beginning with "competency_template",
 * in order to avoid name collisions
 *
 */

/**
 * A function to display a table list of competency templates
 *
 * @param array $templates
 * @param int $frameworkid
 * @return html
 */
function competency_template_display_table($templates, $frameworkid) {
    global $OUTPUT;

    $sitecontext = context_system::instance();
    $editing     = optional_param('edit', -1, PARAM_BOOL);

    // Cache user capabilities
    $can_add = has_capability('totara/hierarchy:createcompetencytemplate', $sitecontext);
    $can_edit = has_capability('totara/hierarchy:updatecompetencytemplate', $sitecontext);
    $can_delete = has_capability('totara/hierarchy:deletecompetencytemplate', $sitecontext);

    if (($can_add || $can_edit || $can_delete) && $editing) {
        $editingon = $USER->templateediting = 1;
    } else {
        $editingon = $USER->templateediting = 0;
    }

    ///
    /// Generate / display page
    ///
    $str_edit     = get_string('edit');
    $str_delete   = get_string('delete');
    $str_hide     = get_string('hide');
    $str_show     = get_string('show');

    if ($templates) {

        // Create display table
        $table = new html_table();

        // Setup column headers
        $table->head = array();
        $table->align = array();
        $table->head[] = get_string('template', 'totara_hierarchy');
        $table->align[] = 'left';
        $table->head[] = get_string('competencies', 'totara_hierarchy');
        $table->align[] = 'center';
        $table->head[] = get_string('createdon', 'totara_hierarchy');
        $table->align[] = 'left';

        // Add edit column
        if ($editingon && $can_edit) {
            $table->head[] = get_string('edit');
            $table->align[] = 'center';
        }

        // Add rows to table
        foreach ($templates as $template) {
            $row = array();

            $cssclass = !$template->visible ? 'dimmed' : '';

            $row[] = $OUTPUT->action_link(new moodle_url('prefix/competency/template/view.php', array('id' => $template->id)), $template->fullname, null, array('class' => $cssclass));
            $row[] = $OUTPUT->action_link(new moodle_url('prefix/competency/template/view.php', array('id' => $template->id)), $template->competencycount, null, array('class' => $cssclass));
            $row[] = userdate($template->timecreated, get_string('strftimedaydate', 'langconfig'));

            // Add edit link
            $buttons = array();
            if ($editingon && $can_edit) {
                $buttons[] = $OUTPUT->action_icon(new moodle_url('prefix/competency/template/edit.php', array('id' => $template->id)),
                    new pix_icon('t/edit', $stredit, null, array('class' => 'iconsmall', 'title' => $str_edit)));
            }
            if ($editingon && $can_delete) {
                $buttons[] = $OUTPUT->action_icon(new moodle_url('prefix/competency/template/delete.php', array('id' => $template->id)),
                    new pix_icon('t/delete', $strdelete, null, array('class' => 'iconsmall', 'title' => $str_delete)));
            }

            if ($buttons) {
                $row[] = implode($buttons, '');
            }

            $table->data[] = $row;
        }
    }

    // Display page

    echo $OUTPUT->heading(get_string('competencytemplates', 'totara_hierarchy'));

    if ($templates) {
        echo html_writer::table($table);
    } else {
        echo html_writer::tag('p', get_string('notemplateinframework', 'totara_hierarchy'));
    }

    // Editing buttons
    if ($can_add) {
        $data = array('frameworkid' => $frameworkid);

        // Print button for creating new template
        echo html_writer::tag('div',
        $OUTPUT->single_button(new moodle_url('prefix/competency/template/edit.php', $data), get_string('addnewtemplate', 'totara_hierarchy'), 'get'),
        array('class' => 'buttons'));
    }
}
