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
 * @author Ciaran Irvine <ciaran.irvine@totaralms.com>
 * @author David Curry <david.curry@totaralms.com>
 * @package totara
 * @subpackage totara_hierarchy
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

/**
* Standard HTML output renderer for totara_hierarchy module
*/
class totara_hierarchy_renderer extends plugin_renderer_base {

    /**
     * Outputs a table containing evidence for a this item
    *
    * @param object $item competency item
    * @param boolean $can_edit If the user has edit permissions
    * @param array $evidence array of evidence ids
    * @return string HTML to output.
    */
    public function print_competency_view_evidence($item, $evidence=null, $can_edit=false) {
        global $CFG;
        require_once($CFG->dirroot . '/totara/plan/lib.php');
        $out = html_writer::start_tag('div', array('id' => 'evidence-list-container'));
        $out .= $this->output->heading(get_string('evidenceitems', 'totara_hierarchy'));

        $table = new html_table();
        $table->id = 'list-evidence';
        $table->attributes = array('class' => 'generaltable boxaligncenter');
        // Set up table header.
        $table->head = array();
        $table->head[] = get_string('name');
        if (!empty($CFG->competencyuseresourcelevelevidence)) {
            $table->head[] = get_string('type', 'totara_hierarchy');
            $table->head[] = get_string('activity');
        }
        if ($can_edit) {
            $table->head[] = get_string('linktype', 'totara_plan');
            $table->head[] = get_string('options', 'totara_hierarchy');
        }

        // Now the rows if any.
        if ($evidence) {
            $oddeven = 1;
            foreach ($evidence as $eitem) {
                $cells = array();
                $oddeven = ++$oddeven % 2;
                $eitem = competency_evidence_type::factory((array)$eitem);
                $cells[] = new html_table_cell($eitem->get_name());
                if (!empty($CFG->competencyuseresourcelevelevidence)) {
                    $cells[] = new html_table_cell($eitem->get_type());
                    $cells[] = new html_table_cell($eitem->get_activity_type());
                }
                if ($can_edit) {

                    $content = html_writer::select(
                    array( //$options
                    PLAN_LINKTYPE_MANDATORY => get_string('mandatory','totara_hierarchy'),
                    PLAN_LINKTYPE_OPTIONAL => get_string('optional','totara_hierarchy'),
                    ),
                    'linktype', //$name,
                    (isset($eitem->linktype) ? $eitem->linktype : PLAN_LINKTYPE_OPTIONAL), //$selected,
                    false, //$nothing,
                    array('onchange' => "\$.get(".
                                "'{$CFG->wwwroot}/totara/plan/update-linktype.php".
                                "?type=course&c={$eitem->id}".
                                "&sesskey=".sesskey().
                                "&t=' + $(this).val()".
                            ");")
                    );

                    $cell = new html_table_cell($content);
                    $cell->attributes['style'] = 'text-align: center;';
                    $cells[] = $cell;
                    $str_remove = get_string('remove');
                    $link = $this->output->action_link(
                        new moodle_url('/totara/hierarchy/prefix/competency/evidenceitem/remove.php', array('id' => $eitem->id)),
                        $this->output->pix_icon('t/delete', $str_remove, null, array('class' => 'iconsmall')),
                        null,
                        array('title' => $str_remove)
                    );
                    $cell = new html_table_cell($link);
                    $cell->attributes['style'] = 'text-align: center;';
                    $cells[] = $cell;
                }

                $row = new html_table_row($cells);
                $row->attributes['class'] = 'r'.$oddeven;
                $table->data[] = $row;
            }

        } else {
            // # cols varies
            $cols = $can_edit ? 4 : 3;
            $cell = new html_table_cell(html_writer::start_tag('i') . get_string('noevidenceitems', 'totara_hierarchy') . html_writer::end_tag('i'));
            $cell->colspan = $cols;
            $row = new html_table_row(array($cell));
            $row->attributes['class'] = 'noitems-evidence';
            $table->data[] = $row;
        }
        $out .= html_writer::table($table);

        $out .= html_writer::end_tag('div');
        // Navigation / editing buttons
        $out .= html_writer::start_tag('div', array('class' => 'buttons'));

        $context = context_system::instance();
        $can_edit = has_capability('totara/hierarchy:updatecompetency', $context);
        // Display add evidence item button
        if ($can_edit) {
            $out .= html_writer::start_tag('div', array('class' => 'singlebutton'));

            $action = new moodle_url('/totara/hierarchy/prefix/competency/evidenceitem/edit.php', array('id' => $item->id));
            $out .= html_writer::start_tag('form', array('action' => $action->out(), 'method' => 'get'));
            $out .= html_writer::start_tag('div');
            if (!empty($CFG->competencyuseresourcelevelevidence)) {
                $btnstr = get_string('assignnewevidenceitem', 'totara_hierarchy');
            } else {
                $btnstr = get_string('assigncoursecompletions', 'totara_hierarchy');
            }
            $out .= html_writer::empty_tag('input', array('type' => 'submit', 'id' => "show-evidence-dialog", 'value' => $btnstr));
            $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "id", 'value' => $item->id));
            $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "nojs", 'value' => '1'));
            $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "returnurl", 'value' => qualified_me()));
            $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "s", 'value' => sesskey()));
            $out .= html_writer::end_tag('div');
            $out .= html_writer::end_tag('form');
            $out .= html_writer::end_tag('div');
        }

        $out .= html_writer::end_tag('div');
        return $out;
    }

    /**
    * Outputs a table containing competencies that are related to this item
    *
    * @param object $item competency item
    * @param boolean $can_edit If the user has edit permissions
    * @param array $related array of related items
    * @return string HTML to output.
    */
    public function print_competency_view_related($item, $can_edit=false, $related=null) {

        $out = $this->output->heading(get_string('relatedcompetencies', 'totara_hierarchy'));

        $table = new html_table();
        $table->attributes = array('id' => 'list-related', 'class' => 'generaltable boxaligncenter');
        // Set up table header.
        $table->head = array(
            get_string('competencyframework', 'totara_hierarchy'),
            get_string('name'),
        );

        if ($can_edit) {
            $table->head[] = get_string('options', 'totara_plan');
        }

        // Now the rows if any.
        if ($related) {
            $sitecontext = context_system::instance();
            $can_manage_fw = has_capability('totara/hierarchy:updatecompetencyframeworks', $sitecontext);

            $oddeven = 1;
            foreach ($related as $ritem) {
                $cells = array();
                $framework_text = ($can_manage_fw) ?
                    $this->output->action_link(new moodle_url('/totara/hierarchy/index.php', array('prefix' => 'competency', 'frameworkid' => $ritem->fid)),
                    format_string($ritem->framework)) : format_string($ritem->framework);
                $oddeven = ++$oddeven % 2;

                $cells[] = new html_table_cell($framework_text);
                $link = html_writer::link(new moodle_url('/totara/hierarchy/item/view.php', array('prefix' => 'competency', 'id' => $ritem->id)), format_string($ritem->fullname));
                $cells[] = new html_table_cell($link);

                if ($can_edit) {
                    $str_remove = get_string('remove');
                    $content = $this->output->action_link(
                        new moodle_url("/totara/hierarchy/prefix/competency/related/remove.php", array('id' => $item->id, 'related' => $ritem->id)),
                        new pix_icon('t/delete', $str_remove, null, array('class' => 'iconsmall')),
                        null,
                        array('title' => $str_remove)
                    );
                    $cell = new html_table_cell($content);
                    $cell->attributes['style'] = 'text-align: center;';
                    $cells[] = $cell;
                }
                $row = new html_table_row($cells);
                $row->attributes['class'] = 'r'.$oddeven;
                $table->data[] = $row;
            }

        } else {
            // # cols varies
            $cols = $can_edit ? 4 : 3;
            $cell = new html_table_cell(html_writer::start_tag('i') . get_string('norelatedcompetencies', 'totara_hierarchy') . html_writer::end_tag('i'));
            $cell->colspan = $cols;
            $row = new html_table_row(array($cell));
            $row->attributes['class'] = 'noitems-related';
            $table->data[] = $row;
        }
        $out .= html_writer::table($table);

        // Add related competencies button
        if ($can_edit) {
            $out .= html_writer::start_tag('div', array('class' => 'buttons'));
            $out .= html_writer::start_tag('div', array('class' => 'singlebutton'));

            $action = new moodle_url('/totara/hierarchy/prefix/competency/related/find.php', array('id' => $item->id, 'frameworkid' => $item->frameworkid));
            $out .= html_writer::start_tag('form', array('action' => $action->out(), 'method' => 'get'));
            $out .= html_writer::start_tag('div');
            $out .= html_writer::empty_tag('input', array('type' => 'submit', 'id' => "show-related-dialog", 'value' => get_string('assignrelatedcompetencies', 'totara_hierarchy')));
            $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "id", 'value' => $item->id));
            $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "nojs", 'value' => '1'));
            $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "returnurl", 'value' => qualified_me()));
            $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "s", 'value' => sesskey()));
            $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "frameworkid", 'value' => $item->frameworkid));
            $out .= html_writer::end_tag('div');
            $out .= html_writer::end_tag('form');
            $out .= html_writer::end_tag('div');
            $out .= html_writer::end_tag('div');
        }

        return $out;
    }

    /**
     * Outputs a table containing all of the assignments for a given goal ($item)
     *
     * @param object $item          The goal object to show the assignments for
     * @param bool $can_edit        Whether or not the viewing user can delete/add assignments
     * @param array $assignments    A list of current assignments for the goal
     * @param bool $dialog_box      Is the function called from ajx/dialog or when the page is loaded
     * @return string HTML to output
     */
    public function print_goal_view_assignments($item, $can_edit = false, $assignments = null, $dialog_box = false) {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/totara/hierarchy/prefix/goal/lib.php');

        // Display table heading.
        $heading = '';
        if (!$dialog_box) {
            $heading = $this->output->heading(get_string('goalassignments', 'totara_hierarchy'), 3);
        }

        // Initialise table and add header row.
        $table = new html_table();
        $table->head = array(
            get_string('goaltable:name', 'totara_hierarchy'),
            get_string('goaltable:type', 'totara_hierarchy'),
            get_string('goaltable:numusers', 'totara_hierarchy')
        );

        $remove = get_string('remove');
        if ($can_edit) {
            $table->head[] = get_string('delete');
        }

        $andchildstr = get_string('andchildren', 'totara_hierarchy');
        foreach ($assignments as $assignment) {

            $assigntype = goal::grp_type_to_assignment($assignment->grouptype);

            // Check permissions.
            if ($can_edit) {
                // Add a delete action button.
                $params = array('goalid' => $item->id, 'assigntype' => $assigntype,
                        'modid' => $assignment->sourceid, 'view' => true);
                $url = new moodle_url('/totara/hierarchy/prefix/goal/assign/remove.php', $params);
                $delete = $this->output->action_icon(
                    $url,
                    new pix_icon('t/delete', $remove, null, array('class' => 'iconsmall')),
                    null,
                    array('id' => 'goalassigdel', 'title' => $remove)
                );
            } else {
                $delete = null;
            }

            if ($assignment->includechildren) {
                $namestr = format_string($assignment->sourcefullname) . ' ' . $andchildstr;
            } else {
                $namestr = format_string($assignment->sourcefullname);
            }

            $cells = array();
            $cells['name'] = new html_table_cell($namestr);
            $cells['type'] = new html_table_cell($assignment->grouptypename);
            $cells['users'] = new html_table_cell($assignment->groupusers);
            if ($can_edit) {
                $cells['delete'] = new html_table_cell($delete);
            }
            $row = new html_table_row($cells);
            $table->data[] = $row;
        }

        return $heading . $this->output->container(html_writer::table($table), 'clearfix', 'assignedgroups');
    }

    /**
    * Outputs a table containing items in this organisation
    *
    * @param int $framework current framework id
    * @param string $type shortprefix e.g. 'pos' or 'org'
    * @param string $displaytitle
    * @param moodle_url $addurl
    * @param int $itemid id of current item being viewed
    * @param array $items array of assigned competencies
    * @param boolean $can_edit if the user has edit permissions
    * @return string HTML to output.
    */
    function print_hierarchy_items($framework, $prefix, $shortprefix, $displaytitle, $addurl, $itemid, $items, $can_edit=false){
        global $CFG;

        require_once($CFG->libdir . '/tablelib.php');
        require_once($CFG->dirroot . '/totara/plan/lib.php');

        if ($displaytitle == 'assignedcompetencies') {
            $columns = array('type', 'name');
            $headers = array(
            get_string('type', 'totara_hierarchy'),
            get_string('name', 'totara_hierarchy')
            );
        } else if ($displaytitle == 'assignedcompetencytemplates') {
            $columns = array('name');
            $headers = array(
            get_string('name', 'totara_hierarchy'),
            );
        }
        $displayprefix = 'competency';

        if ($can_edit) {
            $str_edit = get_string('edit');
            $str_remove = get_string('remove');
            $columns[] = 'linktype';
            $headers[] = get_string('linktype', 'totara_plan');
            $columns[] = 'options';
            $headers[] = get_string('options', 'totara_hierarchy');
        }
        $out = '';
        if (is_array($items) && count($items)) {
            //output buffering because flexible_table uses echo() internally
            ob_start();
            $table = new flexible_table($displaytitle);
            $table->define_baseurl("{$CFG->wwwroot}/totara/hierarchy/item/view.php?prefix={$displayprefix}&id={$itemid}");
            $table->define_columns($columns);
            $table->define_headers($headers);
            $table->set_attribute('id', 'list-'.$displaytitle);
            $table->set_attribute('cellspacing', '0');
            $table->set_attribute('class', 'generalbox boxaligncenter edit'.$displayprefix);
            $table->setup();
            // Add one blank line
            $table->add_data(NULL);
            foreach ($items as $ritem) {
                $content = array();
                $content[] = empty($ritem->type) ? get_string('unclassified', 'totara_hierarchy') : $ritem->type;

                if ($displaytitle == 'assignedcompetencies') {
                    $content[] = $this->output->action_link(new moodle_url('/totara/hierarchy/item/view.php', array('prefix' => $displayprefix, 'id' => $ritem->id)), format_string($ritem->fullname));
                } elseif ($displaytitle == 'assignedcompetencytemplates') {
                    $content[] = $this->output->action_link(new moodle_url('/totara/hierarchy/prefix/competency/template/view.php', array('id' => $ritem->id)), format_string($ritem->fullname));
                }

                if ($can_edit) {
                    // TODO: Rewrite to use a component_action object
                    $content[] = html_writer::select(
                    array( //$options
                    PLAN_LINKTYPE_OPTIONAL => get_string('optional', 'totara_hierarchy'),
                    PLAN_LINKTYPE_MANDATORY => get_string('mandatory', 'totara_hierarchy'),
                    ),
                    'linktype', //$name,
                    ($ritem->linktype ? $ritem->linktype : PLAN_LINKTYPE_OPTIONAL), //$selected,
                    false, //$nothing,
                    array('onChange' => "\$.get(".
                                "'{$CFG->wwwroot}/totara/plan/update-linktype.php".
                                "?type={$shortprefix}&c={$ritem->aid}".
                                "&sesskey=".sesskey().
                                "&t=' + $(this).val()".
                            ");")
                    );
                    $content[] = $this->output->action_icon(
                        new moodle_url('/totara/hierarchy/prefix/' . $prefix . '/assigncompetency/remove.php',
                                array('id' => $ritem->aid, $prefix => $itemid, 'framework' => $framework)),
                        new pix_icon('t/delete', $str_remove, null, array('class' => 'iconsmall')),
                        null,
                        array('title' => $str_remove)
                    );
                }
                $table->add_data($content);
            }
            $table->finish_html();
            $out .= ob_get_clean();
        } else {
            $out .= $this->output->box_start('boxaligncenter boxwidthnormal centerpara nohierarchyitems noitems-'.$displaytitle);
            $out .= get_string('no'.$displaytitle, 'totara_hierarchy');
            $out .= $this->output->box_end();
        }

        // Add button
        if ($can_edit) {
            // need to be done manually (not with single_button) to get correct ID on input button element
            $add_button_text = get_string('add'.$displayprefix, 'totara_hierarchy');
            $out .= html_writer::start_tag('div',
                    array('class' => 'buttons'));
            $out .= html_writer::start_tag('div',
                    array('class' => 'singlebutton'));
            $out .= html_writer::start_tag('form',
                    array('action' => $addurl, 'method' => 'get'));
            $out .= html_writer::start_tag('div');
            $out .= html_writer::empty_tag('input',
                    array('type' => 'submit', 'id' => "show-{$displaytitle}-dialog", 'value' => $add_button_text));
            $out .= html_writer::empty_tag('input',
                    array('type' => 'hidden', 'name' => "assignto", 'value' => $itemid));
            $out .= html_writer::empty_tag('input',
                    array('type' => 'hidden', 'name' => "nojs", 'value' => '1'));
            $out .= html_writer::empty_tag('input',
                    array('type' => 'hidden', 'name' => "returnurl", 'value' => qualified_me()));
            $out .= html_writer::empty_tag('input',
                    array('type' => 'hidden', 'name' => "s", 'value' => sesskey()));
            $out .= html_writer::end_tag('div');
            $out .= html_writer::end_tag('form');
            $out .= html_writer::end_tag('div');
            $out .= html_writer::end_tag('div');
        }
        return $out;
    }

    /**
     * Print out the table of assigned goals for a given pos/org
     *
     * @param string  $prefix       The prefix of the hierarchy type
     * @param string  $shortprefix  The short prefix of the hierarhcy type
     * @param string  $addgoalurl   The url used to add goal assignments to the hierarchy type
     * @param int     $itemid       The id of the hierarchy instance
     */
    public function print_assigned_goals($prefix, $shortprefix, $addgoalurl, $itemid) {
        global $CFG;

        require_once($CFG->dirroot . '/totara/hierarchy/prefix/goal/lib.php');

        // Set up some variables.
        $table = 'goal_grp_' . $shortprefix;
        $field = $shortprefix . 'id';
        $remove = get_string('remove');
        $level_only = get_string('goalassignthislevelonly', 'totara_hierarchy');
        $level_below = get_string('goalassignthislevelbelow', 'totara_hierarchy');
        $can_edit = has_capability('totara/hierarchy:managegoalassignments', context_system::instance());
        $out = html_writer::start_tag('div', array('id' => 'print_assigned_goals', 'class' => $prefix));

        $assignment_type = goal::grp_type_to_assignment($shortprefix);
        $assigned_goals = goal::get_modules_assigned_goals($assignment_type, $itemid);

        if (empty($assigned_goals)) {
            // Don't show the table just print No Competencies Assigned.
            $out .= html_writer::start_tag('div', array('class' => 'nogoals'));
            $out .= html_writer::tag('p', get_string('noassignedgoals', 'totara_hierarchy'));
            $out .= html_writer::end_tag('div');
        } else {
            // Initialise table and add header row.
            $table = new html_table();
            $table->head = array(
                get_string('goaltable:name', 'totara_hierarchy'),
                get_string('goaltable:assignmentlevel', 'totara_hierarchy')
            );

            if ($can_edit) {
                $table->head[] = get_string('delete');
            }

            // Add each assignment to the table.
            foreach ($assigned_goals as $goal) {

                // Check permissions.
                if ($can_edit && $goal->$field == $itemid) {
                    // Add a delete action button.
                    $params = array('goalid' => $goal->goalid, 'assigntype' => $assignment_type, 'modid' => $itemid);
                    $url = new moodle_url('/totara/hierarchy/prefix/goal/assign/remove.php', $params);
                    $delete = $this->output->action_icon(
                        $url,
                        new pix_icon('t/delete', $remove, null, array('class' => 'iconsmall')),
                        null,
                        array('id' => 'goalassigdel', 'title' => $remove)
                    );
                } else {
                    $delete = null;
                }

                $nameurl = new moodle_url('/totara/hierarchy/item/view.php', array('prefix' => 'goal', 'id' => $goal->goalid));
                $namewithlink = html_writer::link($nameurl, format_string($goal->fullname));

                if ($goal->includechildren) {
                    if ($goal->$field == $itemid) {
                        $level = $level_below;
                    } else {
                        $parentid = $goal->$field;
                        $parent_params = array('prefix' => $prefix, 'id' => $parentid);
                        $parent_url = new moodle_url('/totara/hierarchy/item/view.php', $parent_params);
                        $parent_link = html_writer::link($parent_url, format_string($goal->parentname));
                        $level = get_string('goalassignlevelparent', 'totara_hierarchy', $parent_link);
                    }
                } else {
                    $level = $level_only;
                }

                $cellname = new html_table_cell($namewithlink);
                $celltype = new html_table_cell($level);
                $celldelete = new html_table_cell($delete);
                $row = new html_table_row(array($cellname, $celltype, $celldelete));
                $table->data[] = $row;
            }

            $out .= html_writer::table($table);
        }

        if ($can_edit) {
            // Need to be done manually (not with single_button) to get correct ID on input button element.
            $add_button_text = get_string('addgoal', 'totara_hierarchy');
            $out .= html_writer::start_tag('div',
                    array('class' => 'buttons'));
            $out .= html_writer::start_tag('div',
                    array('class' => 'singlebutton'));
            $out .= html_writer::start_tag('form',
                    array('action' => $addgoalurl, 'method' => 'get'));
            $out .= html_writer::start_tag('div');
            $out .= html_writer::empty_tag('input',
                    array('type' => 'submit', 'id' => "show-assignedgoals-dialog", 'value' => $add_button_text));
            $out .= html_writer::empty_tag('input',
                    array('type' => 'hidden', 'name' => "assignto", 'value' => $itemid));
            $out .= html_writer::empty_tag('input',
                    array('type' => 'hidden', 'name' => "assigntype", 'value' => $assignment_type));
            $out .= html_writer::empty_tag('input',
                    array('type' => 'hidden', 'name' => "nojs", 'value' => '1'));
            $out .= html_writer::empty_tag('input',
                    array('type' => 'hidden', 'name' => "returnurl", 'value' => qualified_me()));
            $out .= html_writer::empty_tag('input',
                    array('type' => 'hidden', 'name' => "s", 'value' => sesskey()));
            $out .= html_writer::end_tag('div');
            $out .= html_writer::end_tag('form');
            $out .= html_writer::end_tag('div');
            $out .= html_writer::end_tag('div');
        }

        // Close the print_assigned_goals div.
        $out .= html_writer::end_tag('div');

        return $out;
    }

    /**
     * Create the assigned company goals table for the mygoals page
     *
     * @param int $user         The id of the user whos page we are viewing
     * @param bool $can_edit    Whether or not the person viewing the page can edit it
     */
    public function mygoals_company_table($userid, $can_edit, $display = false) {
        global $CFG, $DB;

        $out = '';

        $bgcolour = true;
        $bglighter = 'mygoals_lighter';
        $bgdarker = 'mygoals_darker';

        $assignments = goal::get_user_assignments($userid, $can_edit, $display);
        $company_table = new html_table();

        $company_table->head = array(
            get_string('goaltable:name', 'totara_hierarchy'),
            get_string('goaltable:status', 'totara_hierarchy'),
            get_string('goaltable:assigned', 'totara_hierarchy')
        );

        $goaltypes = $DB->get_records('goal_type');
        $customfields = $DB->get_records('goal_type_info_field');

        // Add any company goals the user has assigned to the table.
        foreach ($assignments as $goalid => $assignment) {

            // Set up the scale value selector.
            if ($can_edit) {
                // Get the current scale value id.
                $params = array('userid' => $userid, 'goalid' => $goalid);
                $goalrecord = goal::get_goal_item($params, goal::SCOPE_COMPANY);
                $currentscalevalueid = $goalrecord->scalevalueid;
                // Use the current scale value id to get the current scale value record.
                $currentscalevalue = $DB->get_record('goal_scale_values', array('id' => $currentscalevalueid));
                // User the current scale value record to get the scale.
                $scalevalues = $DB->get_records('goal_scale_values', array('scaleid' => $currentscalevalue->scaleid),
                        null, 'id, name');
                // Set up the array of options.
                $options = array();
                foreach ($scalevalues as $scalevalue) {
                    $options[$scalevalue->id] = format_string($scalevalue->name);
                }

                $attributes = array(
                    'class' => 'company_scalevalue_selector',
                    'itemid' => $assignment->assignmentid,
                    'onChange' => "\$.get(".
                        "'{$CFG->wwwroot}/totara/hierarchy/prefix/goal/update-scalevalue.php" .
                        "?scope=" . goal::SCOPE_COMPANY .
                        "&sesskey=" . sesskey() .
                        "&goalitemid={$goalrecord->id}" .
                        "&userid={$userid}" .
                        "&scalevalueid=' + $(this).val()" .
                        ");"
                    );

                $update_text = get_string('update');
                $scaleurl = new moodle_url('/totara/hierarchy/prefix/goal/update-scalevalue.php', array('nojs' => true));

                $scalevalue = html_writer::start_tag('form',
                        array('action' => $scaleurl, 'method' => 'get'));
                $scalevalue .= html_writer::empty_tag('input',
                        array('type' => 'hidden', 'name' => "nojs", 'value' => '1'));
                $scalevalue .= html_writer::empty_tag('input',
                        array('type' => 'hidden', 'name' => "scope", 'value' => goal::SCOPE_COMPANY));
                $scalevalue .= html_writer::empty_tag('input',
                        array('type' => 'hidden', 'name' => "userid", 'value' => $userid));
                $scalevalue .= html_writer::empty_tag('input',
                        array('type' => 'hidden', 'name' => "goalitemid", 'value' => $goalrecord->id));
                $scalevalue .= html_writer::empty_tag('input',
                        array('type' => 'hidden', 'name' => "sesskey", 'value' => sesskey()));
                $scalevalue .= html_writer::select($options, 'scalevalueid', $currentscalevalueid, false, $attributes);
                $scalevalue .= html_writer::start_tag('noscript');
                $scalevalue .= html_writer::empty_tag('input',
                        array('type' => 'submit', 'id' => "update-{$assignment->assignmentid}", 'value' => $update_text));
                $scalevalue .= html_writer::end_tag('noscript');
                $scalevalue .= html_writer::end_tag('form');
            } else {
                $scalevalue = $DB->get_field('goal_scale_values', 'name', array('id' => $assignment->scalevalueid));
            }

            $cells = array();

            if ($display) {
                $hierarchy = hierarchy::load_hierarchy('goal');
                $namedata = $hierarchy->display_hierarchy_item($assignment->goal, true, false, $customfields, $goaltypes, true);
            } else {
                $namedata = $assignment->goalname;
            }


            $cells['name'] = new html_table_cell($namedata);
            $cells['status'] = new html_table_cell($scalevalue);
            $cells['assign'] = new html_table_cell($assignment->via);

            $row_bg = $bgcolour ? $bglighter : $bgdarker;
            $bgcolour = !$bgcolour;

            $row = new html_table_row($cells);
            $row->attributes = array('class' => "company_row {$row_bg}");

            $company_table->data[] = $row;
            $company_table->attributes = array('class' => 'company_table fullwidth generaltable');
        }

        $out .= html_writer::start_tag('div', array('id' => 'company_goals_table', 'class' => 'individual'));
        $out .= html_writer::table($company_table);
        $out .= html_writer::end_tag('div');

        return $out;
    }

    /**
     * Create the assigned personal goals table for the mygoals page
     *
     * @param int $user         The id of the user whos page we are viewing
     * @param bool $can_edit    Whether or not the person viewing the page can edit it
     */
    public function mygoals_personal_table($userid, $can_edit) {
        global $DB, $CFG;

        $out = '';

        $bgcolour = true;
        $bglighter = 'mygoals_lighter';
        $bgdarker = 'mygoals_darker';

        // Set up the personal goal data.
        $goalpersonals = goal::get_goal_items(array('userid' => $userid), goal::SCOPE_PERSONAL);
        $personal_table = new html_table();
        $personal_table->head = array(
            get_string('goaltable:name', 'totara_hierarchy'),
            get_string('goaltable:due', 'totara_hierarchy'),
            get_string('goaltable:status', 'totara_hierarchy'),
            get_string('goaltable:assigned', 'totara_hierarchy'),
            get_string('edit')
        );

        // Add any personal goals the user has assigned to the table.
        foreach ($goalpersonals as $goalpersonal) {

            if ($can_edit[$goalpersonal->assigntype]) {
                // Set up the edit and delete icons.
                $edit_url = new moodle_url('/totara/hierarchy/prefix/goal/item/edit_personal.php',
                        array('userid' => $goalpersonal->userid, 'goalpersonalid' => $goalpersonal->id));
                $edit_str = get_string('edit');
                $edit_button = $this->output->action_icon($edit_url, new pix_icon('t/edit', $edit_str));
                $delete_url = new moodle_url('/totara/hierarchy/prefix/goal/item/delete.php',
                        array('goalpersonalid' => $goalpersonal->id, 'userid' => $goalpersonal->userid));
                $delete_str = get_string('delete');
                $delete_button = $this->output->action_icon($delete_url, new pix_icon('t/delete', $delete_str));
            } else {
                // Set up greyed out buttons.
                $edit_button = $this->output->pix_icon('t/edit_gray',
                        get_string('error:editgoals', 'totara_hierarchy'), 'moodle', array('class' => 'iconsmall action-icon'));
                $delete_button = $this->output->pix_icon('t/delete_gray',
                        get_string('error:deletegoalassignment', 'totara_hierarchy'), 'moodle', array('class' => 'iconsmall action-icon'));
            }

            $duedate = !empty($goalpersonal->targetdate) ? userdate($goalpersonal->targetdate,
                    get_string('datepickerlongyearphpuserdate', 'totara_core'),
                $CFG->timezone, false) : '';
            $assign = goal::get_assignment_string(goal::SCOPE_PERSONAL, $goalpersonal);
            $nameurl = new moodle_url('/totara/hierarchy/prefix/goal/item/view.php', array('goalpersonalid' => $goalpersonal->id));
            $namelink = html_writer::link($nameurl, format_string($goalpersonal->name));

            // Set up the scale value selector.
            if (!empty($goalpersonal->scaleid)) {
                if ($can_edit[$goalpersonal->assigntype]) {
                    $values = $DB->get_records('goal_scale_values', array('scaleid' => $goalpersonal->scaleid));
                    $options = array();
                    foreach ($values as $value) {
                        $options[$value->id] = format_string($value->name);
                    }

                    $attributes = array(
                        'class' => 'personal_scalevalue_selector',
                        'itemid' => $goalpersonal->id,
                        'onChange' => "\$.get(".
                            "'{$CFG->wwwroot}/totara/hierarchy/prefix/goal/update-scalevalue.php" .
                            "?scope=" . goal::SCOPE_PERSONAL .
                            "&sesskey=" . sesskey() .
                            "&goalitemid={$goalpersonal->id}" .
                            "&userid={$userid}" .
                            "&scalevalueid=' + $(this).val()" .
                            ");"
                    );

                    $update_text = get_string('update');
                    $scaleurl = new moodle_url('/totara/hierarchy/prefix/goal/update-scalevalue.php', array('nojs' => true));

                    $scalevalue = html_writer::start_tag('form',
                            array('action' => $scaleurl, 'method' => 'get'));
                    $scalevalue .= html_writer::empty_tag('input',
                            array('type' => 'hidden', 'name' => "nojs", 'value' => '1'));
                    $scalevalue .= html_writer::empty_tag('input',
                            array('type' => 'hidden', 'name' => "scope", 'value' => goal::SCOPE_PERSONAL));
                    $scalevalue .= html_writer::empty_tag('input',
                            array('type' => 'hidden', 'name' => "userid", 'value' => $userid));
                    $scalevalue .= html_writer::empty_tag('input',
                            array('type' => 'hidden', 'name' => "goalitemid", 'value' => $goalpersonal->id));
                    $scalevalue .= html_writer::empty_tag('input',
                            array('type' => 'hidden', 'name' => "sesskey", 'value' => sesskey()));
                    $scalevalue .= html_writer::select($options, 'scalevalueid',
                            $goalpersonal->scalevalueid, false, $attributes);
                    $scalevalue .= html_writer::start_tag('noscript');
                    $scalevalue .= html_writer::empty_tag('input',
                            array('type' => 'submit', 'id' => "update-{$goalpersonal->id}", 'value' => $update_text));
                    $scalevalue .= html_writer::end_tag('noscript');
                    $scalevalue .= html_writer::end_tag('form');
                } else {
                    $scalevalue = $DB->get_field('goal_scale_values', 'name', array('id' => $goalpersonal->scalevalueid));
                }
            } else {
                $scalevalue = '';
            }

            $cells = array();
            $cells['name'] = new html_table_cell($namelink);
            $cells['due'] = new html_table_cell($duedate);
            $cells['status'] = new html_table_cell($scalevalue);
            $cells['assign'] = new html_table_cell($assign);
            $cells['edit'] = new html_table_cell($edit_button . ' ' . $delete_button);

            $row_bg = $bgcolour ? $bglighter : $bgdarker;
            $bgcolour = !$bgcolour;

            $row = new html_table_row($cells);
            $row->attributes = array('class' => "company_row {$row_bg}");

            $personal_table->data[] = $row;
            $personal_table->attributes = array('class' => 'personal_table fullwidth generaltable');
        }

        $out .= html_writer::start_tag('div', array('id' => 'personal_goals_table'));
        $out .= html_writer::table($personal_table);
        $out .= html_writer::end_tag('div');

        return $out;
    }
}
