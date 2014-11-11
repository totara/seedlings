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
 * @author Jonathan Newman
 * @package totara
 * @subpackage totara_hierarchy
 */

/**
 * competency/lib.php
 *
 * Library to construct competency hierarchies
 */
require_once("{$CFG->dirroot}/totara/hierarchy/lib.php");
require_once("{$CFG->dirroot}/totara/hierarchy/prefix/competency/evidenceitem/type/abstract.php");
require_once("{$CFG->dirroot}/totara/core/utils.php");
require_once("{$CFG->dirroot}/totara/core/js/lib/setup.php");

/**
 * Competency aggregation methods
 *
 * These are mapped to lang strings in the competency lang file
 * with the key as a suffix e.g. for ALL, 'aggregationmethod1'
 */
global $COMP_AGGREGATION;
$COMP_AGGREGATION = array(
    'ALL'       => 1,
    'ANY'       => 2,
    'OFF'       => 3,
/*
    'UNIT'      => 4,
    'FRACTION'  => 5,
    'SUM'       => 6,
    'AVERAGE'   => 7,
*/
);

/**
 * Oject that holds methods and attributes for competency operations.
 * @abstract
 */
class competency extends hierarchy {

    /**
     * The base table prefix for the class
     */
    const PREFIX = 'competency';
    const SHORT_PREFIX = 'comp';
    var $prefix = self::PREFIX;
    var $shortprefix = self::SHORT_PREFIX;
    protected $extrafields = array('evidencecount');

    /**
     * Get template
     * @param int Template id
     * @return object|false
     */
    function get_template($id) {
        global $DB;
        return $DB->get_record($this->shortprefix.'_template', array('id' => $id));
    }

    /**
     * Gets templates.
     *
     * @global object $CFG
     * @return array
     */
    function get_templates() {
        global $DB;
        return $DB->get_records($this->shortprefix.'_template', array('frameworkid' => $this->frameworkid), 'fullname');
    }

    /**
     * Hide the competency template
     * @var int - the template id to hide
     * @return void
     */
    function hide_template($id) {
        global $DB;
        $template = $this->get_template($id);
        if ($template) {
            $visible = 0;
            $DB->set_field($this->shortprefix.'_template', 'visible', $visible, array('id' => $template->id));
        }
    }

    /**
     * Show the competency template
     * @var int - the template id to show
     * @return void
     */
    function show_template($id) {
        global $DB;
        $template = $this->get_template($id);
        if ($template) {
            $visible = 1;
            $DB->set_field($this->shortprefix.'_template', 'visible', $visible, array('id' => $template->id));
        }
    }

    /**
     * Delete competency framework and updated associated scales
     * @access  public
     * @param boolean $triggerevent Whether the delete item event should be triggered or not
     * @return  void
     */
    function delete_framework($triggerevent = true) {
        global $DB;

        // Start transaction
        $transaction = $DB->start_delegated_transaction();

        // Run parent method
        parent::delete_framework();
        // Delete references to scales
        if ($DB->count_records($this->shortprefix.'_scale_assignments', array('frameworkid' => $this->frameworkid))) {
            $DB->delete_records($this->shortprefix.'_scale_assignments', array('frameworkid' => $this->frameworkid));
        }
        // End transaction
        $transaction->allow_commit();
        return true;
    }


    /**
     * Delete all data associated with the competencies
     *
     * This method is protected because it deletes the competencies, but doesn't use
     * transactions
     * Use {@link hierarchy::delete_hierarchy_item()} to recursively delete an item and
     * all its children
     *
     * @param array $items Array of IDs to be deleted
     *
     * @return boolean True if items and associated data were successfully deleted
     */
    protected function _delete_hierarchy_items($items) {
        global $DB;

        // First call the deleter for the parent class
        if (!parent::_delete_hierarchy_items($items)) {
            return false;
        }

        // delete rows from all these other tables:
        $db_data = array(
            $this->shortprefix.'_record_history' => 'competencyid',
            $this->shortprefix.'_record' => 'competencyid',
            $this->shortprefix.'_criteria' => 'competencyid',
            $this->shortprefix.'_criteria_record' => 'competencyid',
            $this->shortprefix.'_relations' => 'id1',
            $this->shortprefix.'_relations' => 'id2',
            hierarchy::get_short_prefix('position').'_competencies' => 'competencyid',
            hierarchy::get_short_prefix('organisation').'_competencies' => 'competencyid',
            'dp_plan_competency_assign' => 'competencyid',
        );
        list($items_sql, $items_params) = $DB->get_in_or_equal($items);
        foreach ($db_data as $table => $field) {
            $select = "$field {$items_sql}";
            $DB->delete_records_select($table, $select, $items_params);
        }


        // update the template count

        // start by getting a list of templates affected by the deletions
        $modified_templates = array();
        $sql = "
            SELECT DISTINCT templateid
            FROM {{$this->shortprefix}_template_assignment}
            WHERE type = ? AND instanceid {$items_sql}";
        $records = $DB->get_records_sql($sql, array_merge(array('1'), $items_params));
        if ($records) {
            foreach ($records as $template) {
                $modified_templates[] = $template->templateid;
            }
        }

        // now delete the template assignments
        $DB->delete_records_select($this->shortprefix.'_template_assignment',
            'type = ? AND instanceid ' . $items_sql, array_merge(array('1'), $items_params));


        // only continue if at least one template has changed
        if (count($modified_templates) > 0) {
            list($templates_sql, $templates_params) = $DB->get_in_or_equal($modified_templates);
            $templatecounts = $DB->get_records_sql(
                "SELECT templateid, COUNT(instanceid) AS count
                FROM {{$this->shortprefix}_template_assignment}
                WHERE type = ?
                GROUP BY templateid
                HAVING templateid {$templates_sql}", array_merge(array('1'), $templates_params));

            if ($templatecounts) {
                foreach ($templatecounts as $templatecount) {
                    // now update count for templates that still have at least one assignment
                    // this won't catch templates that now have zero competencies as there
                    // won't be any entries in comp_template_assignment
                    $sql = "UPDATE {{$this->shortprefix}_template}
                        SET competencycount = ?
                        WHERE id = ?";
                    $DB->execute($sql, array($templatecount->count, $templatecount->templateid));
                }
            }

            // figure out if any of the modified templates are now empty
            $empty_templates = $modified_templates;
            $sql = "SELECT DISTINCT templateid
                FROM {{$this->shortprefix}_template_assignment}";
            $records = $DB->get_recordset_sql($sql);
            foreach ($records as $record) {
                $key = array_search($record->templateid, $empty_templates);
                if ($key !== false) {
                    // it's not empty if there's an assignment
                    unset($empty_templates[$key]);
                }
            }
            $records->close();

            // finally, set the count to zero for any of the templates that no longer
            // have any assignments
            if (count($empty_templates) > 0) {
                list($in_sql, $in_params) = $DB->get_in_or_equal($empty_templates);
                $sql = "UPDATE {{$this->shortprefix}_template}
                    SET competencycount = 0
                    WHERE id {$in_sql}";
                $DB->execute($sql, $in_params);
            }
        }

        return true;

    }


    /**
     * Delete template and associated data
     * @var int - the template id to delete
     * @return  void
     */
    function delete_template($id) {
        global $DB;
        $DB->delete_records($this->shortprefix.'_template_assignment', array('templateid' => $id));
        $DB->delete_records(hierarchy::get_short_prefix('position').'_competencies', array('templateid' => $id));

        // Delete this item
        $DB->delete_records($this->shortprefix.'_template', array('id' => $id));
    }

    /**
     * Get competencies assigned to a template
     * @param int $id Template id
     * @return array
     */
    function get_assigned_to_template($id) {
        global $DB;

        return $DB->get_records_sql(
            "
            SELECT
                c.id AS id,
                c.fullname AS competency,
                c.fullname AS fullname    /* used in some places (for genericness) */
            FROM
                {{$this->shortprefix}_template_assignment} a
            LEFT JOIN
                {{$this->shortprefix}_template} t
             ON t.id = a.templateid
            LEFT JOIN
                {{$this->shortprefix}} c
             ON a.instanceid = c.id
            WHERE
                t.id = ?
            "
        , array($id));
    }

    /**
     * Get evidence items for a competency
     * @param $item object Competency
     * @return array
     */
    function get_evidence($item) {
        global $DB;
        return $DB->get_records($this->shortprefix.'_criteria', array('competencyid' => $item->id), 'id');
    }

    /**
     * Get related competencies
     * @param $item object Competency
     * @return array
     */
    function get_related($item) {
        global $DB;

        return $DB->get_records_sql(
            "
            SELECT DISTINCT
                c.id AS id,
                c.fullname,
                f.id AS fid,
                f.fullname AS framework,
                it.fullname AS itemtype
            FROM
                {{$this->shortprefix}_relations} r
            INNER JOIN
                {{$this->shortprefix}} c
             ON r.id1 = c.id
             OR r.id2 = c.id
            INNER JOIN
                {{$this->shortprefix}_framework} f
             ON f.id = c.frameworkid
            LEFT JOIN
                {{$this->shortprefix}_type} it
             ON it.id = c.typeid
            WHERE
                (r.id1 = ? OR r.id2 = ?)
            AND c.id != ?
            ORDER BY c.fullname
            ",
        array($item->id, $item->id, $item->id));
    }

    /**
     * Get competency evidence using in a course
     *
     * @param   $courseid   int
     * @return  array
     */
    function get_course_evidence($courseid) {
        global $DB;

        return $DB->get_records_sql(
                "
                SELECT DISTINCT
                    cc.id AS evidenceid,
                    c.id AS id,
                    c.fullname,
                    f.id AS fid,
                    f.fullname AS framework,
                    cc.itemtype AS evidencetype,
                    cc.iteminstance AS evidenceinstance,
                    cc.itemmodule AS evidencemodule,
                    cc.linktype as linktype
                FROM
                    {{$this->shortprefix}_criteria} cc
                INNER JOIN
                    {{$this->shortprefix}} c
                 ON cc.competencyid = c.id
                INNER JOIN
                    {{$this->shortprefix}_framework} f
                 ON f.id = c.frameworkid
                LEFT JOIN
                    {modules} m
                 ON cc.itemtype = 'activitycompletion'
                AND m.name = cc.itemmodule
                LEFT JOIN
                    {course_modules} cm
                 ON cc.itemtype = 'activitycompletion'
                AND cm.instance = cc.iteminstance
                AND cm.module = m.id
                WHERE
                (
                        cc.itemtype <> 'activitycompletion'
                    AND cc.iteminstance = ?
                )
                OR
                (
                        cc.itemtype = 'activitycompletion'
                    AND cm.course = ?
                )
                ORDER BY
                    c.fullname
                ",
        array($courseid, $courseid));
    }

    /**
     * Run any code before printing header
     * @param $page string Unique identifier for page
     * @return void
     */
    function hierarchy_page_setup($page = '', $item=null) {
        global $CFG, $USER, $PAGE;

        if (!in_array($page, array('template/view', 'item/view', 'item/add'))) {
            return;
        }

        // Setup custom javascript
        require_once($CFG->dirroot.'/totara/core/js/lib/setup.php');

        // Setup lightbox
        local_js(array(
            TOTARA_JS_DIALOG,
            TOTARA_JS_TREEVIEW
        ));

        switch ($page) {
            case 'item/view':

                $jargs = '{';
                if (!empty($item->id)) {
                    $jargs .= '"id":'.$item->id;
                }
                if (!empty($CFG->competencyuseresourcelevelevidence)) {
                    $jargs .= ', "competencyuseresourcelevelevidence":true';
                }
                $jargs .= '}';
                // Include competency item js module
                $PAGE->requires->strings_for_js(array('assignrelatedcompetencies',
                        'assignnewevidenceitem','assigncoursecompletions'), 'totara_hierarchy');
                $jsmodule = array(
                        'name' => 'totara_competencyitem',
                        'fullpath' => '/totara/core/js/competency.item.js',
                        'requires' => array('json'));
                $PAGE->requires->js_init_call('M.totara_competencyitem.init',
                         array('args'=>$jargs), false, $jsmodule);

                break;
            case 'template/view':

                $itemid = !(empty($item->id)) ? array('args'=>'{"id":'.$item->id.'}') : NULL;

                // Include competency template js module
                $PAGE->requires->string_for_js('assignnewcompetency', 'totara_competency');
                $jsmodule = array(
                        'name' => 'totara_competencytemplate',
                        'fullpath' => '/totara/core/js/competency.template.js',
                        'requires' => array('json'));
                $PAGE->requires->js_init_call('M.totara_competencytemplate.init',
                         $itemid, false, $jsmodule);

                break;
            case 'item/add':
                $selected_position = json_encode(dialog_display_currently_selected(get_string('selected', 'totara_hierarchy'), 'position'));
                $selected_organisation = json_encode(dialog_display_currently_selected(get_string("currentlyselected", "totara_hierarchy"), "organisation"));
                $args = array('args'=>'{"userid":'.$USER->id.','.
                              '"can_edit": true,'.
                              '"dialog_display_position":'.$selected_position.','.
                              '"dialog_display_organisation":'.$selected_organisation.'}');
                $PAGE->requires->strings_for_js(array('chooseposition', 'choosemanager','chooseorganisation'), 'totara_hierarchy');
                $jsmodule = array(
                        'name' => 'totara_competencyaddevidence',
                        'fullpath' => '/totara/plan/components/competency/competency.add_evidence.js',
                        'requires' => array('json'));
                $PAGE->requires->js_init_call('M.totara_competencyaddevidence.init', $args, false, $jsmodule);
                break;
        }
    }

    /**
     * Print any extra markup to display on the hierarchy view item page
     * @param $item object Competency being viewed
     * @return void
     */
    function display_extra_view_info($item, $section='') {
        global $CFG, $PAGE;
        $renderer = $PAGE->get_renderer('totara_hierarchy');

        $sitecontext = context_system::instance();
        $can_edit = has_capability('totara/hierarchy:updatecompetency', $sitecontext);
        if ($can_edit) {
            $str_edit = get_string('edit');
            $str_remove = get_string('remove');
        }

        if (!$section || $section == 'related') {
            // Display related competencies
            echo html_writer::start_tag('div', array('class' => 'list-related'));
            $related = $this->get_related($item);
            echo $renderer->print_competency_view_related($item, $can_edit, $related);
            echo html_writer::end_tag('div');
        }

        if (!$section || $section == 'evidence') {
            // Display evidence
            $evidence = $this->get_evidence($item);
            echo $renderer->print_competency_view_evidence($item, $evidence, $can_edit);
        }
    }

    /**
     * Return hierarchy prefix specific data about an item
     *
     * The returned array should have the structure:
     * array(
     *  0 => array('title' => $title, 'value' => $value),
     *  1 => ...
     * )
     *
     * @param $item object Item being viewed
     * @param $cols array optional Array of columns and their raw data to be returned
     * @return array
     */
    function get_item_data($item, $cols = NULL) {

        $data = parent::get_item_data($item, $cols);

        $prefix = get_string($this->prefix, 'totara_hierarchy');
        // Add aggregation method
        $data[] = array(
            'title' => get_string('aggregationmethodview', 'totara_hierarchy', $prefix),
            'value' => get_string('aggregationmethod'.$item->aggregationmethod, 'totara_hierarchy')
        );

        return $data;
    }

    /**
     * Get the competency scale for this competency (including all the scale's
     * values in an attribute called valuelist)
     *
     * @global object $CFG
     * @return object
     */
    function get_competency_scale() {
        global $DB;
        $sql = "
            SELECT scale.*
            FROM
                {{$this->shortprefix}_scale_assignments} sa,
                {{$this->shortprefix}_scale} scale
            WHERE
                sa.scaleid = scale.id
                AND sa.frameworkid = ?
        ";
        $scale = $DB->get_record_sql($sql, array($this->frameworkid));

        $valuelist = $DB->get_records($this->shortprefix.'_scale_values', array('scaleid' => $scale->id), 'sortorder');
        if ($valuelist) {
            $scale->valuelist = $valuelist;
        }
        return $scale;
    }


    /**
     * Get scales for a competency
     * @return array
     */
    function get_scales() {
        global $DB;
        return $DB->get_records($this->shortprefix.'_scale', null, 'name');
    }

    /**
     * Delete  a competency assigned to a template
     * @param $templateid
     * @param $competencyid
     * @return void;
     */
    function delete_assigned_template_competency($templateid, $competencyid) {
        global $DB;
        if (!$template = $this->get_template($templateid)) {
            return;
        }

        // Delete assignment
        $DB->delete_records('comp_template_assignment', array('templateid' => $template->id, 'instanceid' => $competencyid));

        // Reduce competency count for template
        $template->competencycount--;

        if ($template->competencycount < 0) {
            $template->competencycount = 0;
        }

        $DB->update_record('comp_template', $template);

        add_to_log(SITEID, 'competency', 'template remove competency assignment',
                    "prefix/competency/template/view.php?id={$template->id}", "Competency ID $competencyid");
    }


    /**
     * Returns an array of all competencies that a user has a comp_record
     * record for, keyed on the competencyid. Also returns the required
     * proficiency value and isproficient, which is 1 if the user meets the
     * proficiency and 0 otherwise
     */
    static function get_proficiencies($userid) {
        global $DB;
        $sql = "SELECT cr.competencyid, prof.proficiency, csv.proficient AS isproficient
            FROM {comp_record} cr
            LEFT JOIN {comp} c ON c.id=cr.competencyid
            LEFT JOIN {comp_scale_assignments} csa
                ON c.frameworkid = csa.frameworkid
            LEFT JOIN {comp_scale_values} csv
                ON csv.scaleid=csa.scaleid
                AND csv.id=cr.proficiency
            LEFT JOIN (
                SELECT scaleid, MAX(id) AS proficiency
                FROM {comp_scale_values}
                WHERE proficient=1
                GROUP BY scaleid
            ) prof on prof.scaleid=csa.scaleid
            WHERE cr.userid = ?";
        return $DB->get_records_sql($sql, array($userid));
    }


    /**
     * Prints the list of linked evidence
     *
     * @param int $courseid
     * @return string
     */
    function print_linked_evidence_list($courseid) {
        global $CFG, $DB, $OUTPUT, $PAGE;

        $system_context = context_system::instance();

        $can_edit = has_capability('totara/hierarchy:updatecompetency', $system_context);
        $can_manage_fw = has_capability('totara/hierarchy:updatecompetencyframeworks', $system_context);

        $course = $DB->get_record('course', array('id' => $courseid));

        // define the table
        $out = new html_table();
        $out->id = 'list-coursecompetency';
        $out->attributes = array(
                'class' => 'boxaligncenter',
        );
        $out->head = array();
        $out->rowclasses[0] = 'header';

        // header row
        $header = new html_table_row();
        $header->attributes = array('scope' => 'col');
        $header->cells = array();
        $head = array();

        // header cells
        $heading0 = new html_table_cell();
        $heading0->text = get_string('competencyframework', 'totara_hierarchy');
        $heading0->header = true;
        $head[] = $heading0;

        $heading1 = new html_table_cell();
        $heading1->text = get_string('name');
        $heading1->header = true;
        $head[] = $heading1;

        if (!empty($CFG->competencyuseresourcelevelevidence)) {
            $heading2 = new html_table_cell();
            $heading2->text = get_string('evidence', 'totara_hierarchy');
            $heading2->header = true;
            $head[] = $heading2;
        }

        if ($can_edit) {
            require_once($CFG->dirroot.'/totara/plan/lib.php');
            $heading3 = new html_table_cell();
            $heading3->text = get_string('linktype', 'totara_plan');
            $heading3->header = true;
            $head[] = $heading3;

            $heading4 = new html_table_cell();
            $heading4->text = get_string('options', 'totara_hierarchy');
            $heading4->header = true;
            $head[] = $heading4;
        } // if ($can_edit)
        // add the completed row to the table
        $out->head = $head;

        // Get any competencies used in this course
        $competencies = $this->get_course_evidence($course->id);
        if ($competencies) {

            $str_remove = get_string('remove');

            $activities = array();

            $data = array();
            foreach ($competencies as $competency) {
                $framework_text = ($can_manage_fw) ?
                     $OUTPUT->action_link(new moodle_url('/totara/hierarchy/index.php', array('prefix' => 'competency', 'frameworkid' => $competency->fid)), format_string($competency->framework))
                     : format_string($competency->framework);

                // define a data row
                $row = new html_table_row();

                //define data cells
                $cell = new html_table_cell($framework_text);
                $row->cells[] = $cell;

                $cell = new html_table_cell($OUTPUT->action_link(new moodle_url('/totara/hierarchy/item/view.php', array('prefix' => 'competency', 'id' => $competency->id)), format_string($competency->fullname)));
                $row->cells[] = $cell;

                // Create evidence object
                $evidence = new stdClass();
                $evidence->id = $competency->evidenceid;
                $evidence->itemtype = $competency->evidencetype;
                $evidence->iteminstance = $competency->evidenceinstance;
                $evidence->itemmodule = $competency->evidencemodule;

                if (!empty($CFG->competencyuseresourcelevelevidence)) {
                    $cell = new html_table_cell();

                    $evidence = competency_evidence_type::factory((array)$evidence);

                    $cell->text = $evidence->get_type();
                    if ($evidence->itemtype == 'activitycompletion') {
                        $cell->text .= ' - '.$evidence->get_name();
                    }

                    $row->cells[] = $cell;
                }

                // Options column
                if ($can_edit) {
                    $cell = new html_table_cell();

                    // TODO: Rewrite to use a component_action object
                    // the 't' param may need reworking, since it is applied via
                    // onChange using the old inline jQuery code below.
                    $select = html_writer::select(
                        $options = array(
                            PLAN_LINKTYPE_OPTIONAL => get_string('optional', 'totara_hierarchy'),
                            PLAN_LINKTYPE_MANDATORY => get_string('mandatory', 'totara_hierarchy'),
                        ),
                        'linktype', //$name,
                        (isset($competency->linktype) ? $competency->linktype : PLAN_LINKTYPE_MANDATORY), //$selected,
                        false, //$nothing,
                        array('onChange' => "\$.get(".
                                    "'{$CFG->wwwroot}/totara/plan/update-linktype.php".
                                    "?type=course&c={$competency->evidenceid}".
                                    "&sesskey=".sesskey().
                                    "&t=' + $(this).val()".
                            ");")
                    );

                    $cell->text = $select;
                    $row->cells[] = $cell;

                    $cell = new html_table_cell();
                    $cell->text = $OUTPUT->action_icon(new moodle_url('/totara/hierarchy/prefix/competency/evidenceitem/remove.php',
                            array('id' => $evidence->id, 'course' => $courseid, 'returnurl' => $PAGE->url->out())),
                        new pix_icon('t/delete', $str_remove), null, array('class' => 'iconsmall', 'alt' => $str_remove, 'title' => $str_remove));
                    $row->cells[] = $cell;
                }

                $data[] = $row;
            }
            $out->data = $data;

        } else {
            $row = new html_table_row();
            $row->attributes['class'] = 'noitems-coursecompetency';

            $cell = new html_table_cell();
            $cell->colspan = 5;
            $cell->text = html_writer::tag('i', get_string('nocoursecompetencies', 'totara_hierarchy'));
            $row->cell[0] = $cell;

            $out->data = array($row);
        }

        return html_writer::table($out);
    }

    /**
     * Returns an array of competency ids that have completed by the specified user
     * @param int $userid user to get competencies for
     * @return array list of ids of completed competencies
     */
    static function get_user_completed_competencies($userid) {
        global $DB;

        $proficient_sql = "SELECT
            cr.competencyid
            FROM
                {comp_record} cr
            JOIN
                {comp_scale_values} csv ON csv.id = cr.proficiency
            WHERE csv.proficient = 1
              AND cr.userid = ?
              ";
        $completed = $DB->get_records_sql($proficient_sql, array($userid));

        return is_array($completed) ? array_keys($completed) : array();
    }


    /**
     * Extra form elements to include in the add/edit form for items of this prefix
     *
     * @param object &$mform Moodle form object (passed by reference)
     */
    function add_additional_item_form_fields(&$mform) {
        global $DB;

        $frameworkid = $this->frameworkid;

        // Get all aggregation methods
        global $COMP_AGGREGATION;
        $aggregations = array();
        foreach ($COMP_AGGREGATION as $title => $key) {
            $aggregations[$key] = get_string('aggregationmethod'.$key, 'totara_hierarchy');
        }

        // Get the name of the framework's scale. (Note this code expects there
        // to be only one scale per framework, even though the DB structure
        // allows there to be multiple since we're using a go-between table)
        $scaledesc = $DB->get_field_sql("
                SELECT s.name
                FROM
                {{$this->shortprefix}_scale} s,
                {{$this->shortprefix}_scale_assignments} a
                WHERE
                a.frameworkid = ?
                AND a.scaleid = s.id
        ", array($frameworkid));

        $mform->addElement('select', 'aggregationmethod', get_string('aggregationmethod', 'totara_hierarchy'), $aggregations);
        $mform->addHelpButton('aggregationmethod', 'competencyaggregationmethod', 'totara_hierarchy');
        $mform->addRule('aggregationmethod', get_string('aggregationmethod', 'totara_hierarchy'), 'required', null);

        $mform->addElement('static', 'scalename', get_string('scale'), ($scaledesc) ? format_string($scaledesc) : get_string('none'));
        $mform->addHelpButton('scalename', 'competencyscale', 'totara_hierarchy');

        $mform->addElement('hidden', 'proficiencyexpected', 1);
        $mform->setType('proficiencyexpected', PARAM_INT);
        $mform->addElement('hidden', 'evidencecount', 0);
        $mform->setType('evidencecount', PARAM_INT);
    }

    /**
     * Format additional fields shown in the competency add/edit forms, proficiencyexpected and evidencecount.
     *
     * @param $item object      The form data object to be formatted
     * @return object           The same object after formatting
     */
    public function process_additional_item_form_fields($item) {

        // Set the default proficiency expected.
        if (!isset($item->proficiencyexpected)) {
            $item->proficiencyexpected = 1;
        }

        // Set the default evidence count.
        if (!isset($item->evidencecount)) {
            $item->evidencecount = 0;
        }

        return $item;
    }

    /**
     * Returns various stats about an item, used for listed what will be deleted
     *
     * @param integer $id ID of the item to get stats for
     * @return array Associative array containing stats
     */
    public function get_item_stats($id) {
        global $DB;
        if (!$data = parent::get_item_stats($id)) {
            return false;
        }

        // should always include at least one item (itself)
        if (!$children = $this->get_item_descendants($id)) {
            return false;
        }

        $ids = array_keys($children);

        list($idssql, $idsparams) = sql_sequence('competencyid', $ids);
        // number of comp_record records
        $data['user_achievement'] = $DB->count_records_select('comp_record', $idssql, $idsparams);

        // number of comp_criteria records
        $data['evidence'] = $DB->count_records_select('comp_criteria', $idssql, $idsparams);

        // number of comp_relations records
        list($ids1sql, $ids1params) = sql_sequence('id1', $ids);
        list($ids2sql, $ids2params) = sql_sequence('id2', $ids);
        $data['related'] = $DB->count_records_select('comp_relations',
            $ids1sql . ' OR ' . $ids2sql, array_merge($ids1params, $ids2params));

        return $data;
    }

    /**
     * Given some stats about an item, return a formatted delete message
     *
     * @param array $stats Associative array of item stats
     * @return string Formatted delete message
     */
    public function output_delete_message($stats) {
        $message = parent::output_delete_message($stats);

        if ($stats['user_achievement'] > 0) {
            $message .= get_string('deleteincludexuserstatusrecords', 'totara_hierarchy', $stats['user_achievement']) . html_writer::empty_tag('br');
        }

        if ($stats['evidence'] > 0) {
            $message .= get_string('deleteincludexevidence', 'totara_hierarchy', $stats['evidence']) . html_writer::empty_tag('br');
        }

        if ($stats['related'] > 0) {
            $message .= get_string('deleteincludexrelatedcompetencies', 'totara_hierarchy', $stats['related']). html_writer::empty_tag('br');
        }

        return $message;
    }

    /**
     * Update an existing hierarchy item
     *
     * This can include moving to a new location in the hierarchy or changing some of its data values.
     * This method will not update an item's custom field data
     *
     * @param integer $itemid ID of the item to update
     * @param object $newitem An object containing details to be updated
     *                        Only a parentid is required to update the items location, other data such as
     *                        depthlevel, sortthread, path, etc will be handled internally
     * @param boolean $usetransaction If true this function will use transactions (optional, default: true)
     * @param boolean $triggerevent If true, this command will trigger a "{$prefix}_added" event handler.
     * @param boolean $removedesc If true this sets the description field to null,
     *                             descriptions should be set by post-update editor operations
     *
     * @return object|false The updated item, or false if it could not be updated
     */
    function update_hierarchy_item($itemid, $newitem, $usetransaction = true, $triggerevent = true, $removedesc = true) {
        global $DB;

        $olditem = $DB->get_record('comp', array('id' => $itemid));

        if (isset($newitem->aggregationmethod) && $olditem->aggregationmethod != $newitem->aggregationmethod) {
            $now = time();
            $sql = "UPDATE {comp_record} SET reaggregate = ? WHERE competencyid = ?";
            $params = array($now, $itemid);
            $DB->execute($sql, $params);
        }

        $updateditem = parent::update_hierarchy_item($itemid, $newitem, $usetransaction, $triggerevent, $removedesc);

        return $updateditem;
    }

}  // class
