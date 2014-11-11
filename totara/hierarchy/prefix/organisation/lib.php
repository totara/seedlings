<?php // $Id$
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
 * organisation/lib.php
 *
 * Library to construct organisation hierarchies
 */
require_once("{$CFG->dirroot}/totara/hierarchy/lib.php");
require_once("{$CFG->dirroot}/totara/core/utils.php");

/**
 * Oject that holds methods and attributes for organisation operations.
 * @abstract
 */
class organisation extends hierarchy {

    /**
     * The base table prefix for the class
     */
    var $prefix = 'organisation';
    var $shortprefix = 'org';
    protected $extrafields = null;

    /**
     * Run any code before printing header
     * @param $page string Unique identifier for page
     * @return void
     */
    function hierarchy_page_setup($page = '', $item) {
        global $CFG, $PAGE;

        if ($page !== 'item/view') {
            return;
        }

        $id = optional_param('id', 0, PARAM_INT);
        $frameworkid = optional_param('frameworkid', 0, PARAM_INT);

        // Setup custom javascript
        require_once($CFG->dirroot.'/totara/core/js/lib/setup.php');

        // Setup lightbox
        local_js(array(
            TOTARA_JS_DIALOG,
            TOTARA_JS_TREEVIEW
        ));

        $PAGE->requires->strings_for_js(array('assigncompetencies', 'assigngoals'), 'totara_hierarchy');

        $args = array('args'=>'{"id":' . $id . ','
                             . '"frameworkid":' . $frameworkid . ','
                             . '"sesskey":"' . sesskey() .'"}');

        $jsmodule = array(
            'name' => 'totara_organisationitem',
            'fullpath' => '/totara/core/js/organisation.item.js',
            'requires' => array('json'));
        $PAGE->requires->js_init_call('M.totara_organisationitem.init',
            $args, false, $jsmodule);
    }


    /**
     * Delete all data associated with the organisations
     *
     * This method is protected because it deletes the organisations, but doesn't use transactions
     *
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


        // nullify all references to these organisations in comp_record table
        $prefix = hierarchy::get_short_prefix('competency');

        list($in_sql, $params) = $DB->get_in_or_equal($items);

        $sql = "UPDATE {{$prefix}_record}
            SET organisationid = NULL
            WHERE organisationid $in_sql";
        $DB->execute($sql, $params);

        // nullify all references to these organisations in course_completions table
        $sql = "UPDATE {course_completions}
            SET organisationid = NULL
            WHERE organisationid $in_sql";
        $DB->execute($sql, $params);

        // nullify all references to these organisations in pos_assignment table
        $prefix = hierarchy::get_short_prefix('position');
        $sql = "UPDATE {{$prefix}_assignment}
            SET organisationid = NULL
            WHERE organisationid $in_sql";
        $DB->execute($sql, $params);

        // nullify all references to these organisations in pos_assignment_history table
        $sql = "UPDATE {{$prefix}_assignment_history}
            SET organisationid = NULL
            WHERE organisationid $in_sql";
        $DB->execute($sql, $params);

        return true;
    }


    /**
     * Print any extra markup to display on the hierarchy view item page
     * @param $item object Organisation being viewed
     * @return void
     */
    function display_extra_view_info($item, $frameworkid=0) {
        global $CFG, $OUTPUT, $PAGE;

        require_once($CFG->dirroot . '/totara/hierarchy/prefix/goal/lib.php');

        $sitecontext = context_system::instance();
        $can_edit = has_capability('totara/hierarchy:updateorganisation', $sitecontext);
        $comptype = optional_param('comptype', 'competencies', PARAM_TEXT);

        // Spacing.
        echo html_writer::empty_tag('br');

        echo html_writer::start_tag('div', array('class' => "list-assignedcompetencies"));
        echo $OUTPUT->heading(get_string('assignedcompetencies', 'totara_hierarchy'));

        echo $this->print_comp_framework_picker($item->id, $frameworkid);

        if ($comptype == 'competencies') {
            // Display assigned competencies.
            $items = $this->get_assigned_competencies($item, $frameworkid);
            $addurl = new moodle_url('/totara/hierarchy/prefix/organisation/assigncompetency/find.php', array('assignto' => $item->id));
            $displaytitle = 'assignedcompetencies';
        } else if ($comptype == 'comptemplates') {
            // Display assigned competencies.
            $items = $this->get_assigned_competency_templates($item, $frameworkid);
            $addurl = new moodle_url('/totara/hierarchy/prefix/organisation/assigncompetencytemplate/find.php', array('assignto' => $item->id));
            $displaytitle = 'assignedcompetencytemplates';
        }
        $renderer = $PAGE->get_renderer('totara_hierarchy');
        echo $renderer->print_hierarchy_items($frameworkid, $this->prefix, $this->shortprefix, $displaytitle, $addurl, $item->id, $items, $can_edit);
        echo html_writer::end_tag('div');

        // Spacing.
        echo html_writer::empty_tag('br');

        // Display all goals assigned to this item.
        if (totara_feature_visible('goals') && !is_ajax_request($_SERVER)) {
            $addgoalparam = array('assignto' => $item->id, 'assigntype' => GOAL_ASSIGNMENT_ORGANISATION, 'sesskey' => sesskey());
            $addgoalurl = new moodle_url('/totara/hierarchy/prefix/goal/assign/find.php', $addgoalparam);
            echo html_writer::start_tag('div', array('class' => 'list-assigned-goals'));
            echo $OUTPUT->heading(get_string('goalsassigned', 'totara_hierarchy'));
            echo $renderer->print_assigned_goals($this->prefix, $this->shortprefix, $addgoalurl, $item->id);
            echo html_writer::end_tag('div');
        }
    }

    /**
     * Returns an array of assigned competencies that are assigned to the organisation
     * @param $item object|int Organisation being viewed
     * @param $frameworkid int If set only return competencies for this framework
     * @param $excluded_ids array an optional set of ids of competencies to exclude
     * @return array List of assigned competencies
     */
    function get_assigned_competencies($item, $frameworkid=0, $excluded_ids=false) {
        global $DB;
        if (is_object($item)) {
            $itemid = $item->id;
        } else if (is_numeric($item)) {
            $itemid = $item;
        } else {
            return false;
        }

        $sql = "SELECT
                    c.*,
                    cf.id AS fid,
                    cf.fullname AS framework,
                    ct.fullname AS type,
                    oc.id AS aid,
                    oc.linktype as linktype
                FROM
                    {org_competencies} oc
                INNER JOIN
                    {comp} c
                 ON oc.competencyid = c.id
                INNER JOIN
                    {comp_framework} cf
                 ON c.frameworkid = cf.id
                LEFT JOIN
                    {comp_type} ct
                 ON c.typeid = ct.id
                WHERE
                    oc.templateid IS NULL
                AND oc.organisationid = ?
            ";
        $params = array($itemid);

        if (!empty($frameworkid)) {
            $sql .= " AND c.frameworkid = ?";
            $params[] = $frameworkid;
        }
        if (is_array($excluded_ids) && !empty($excluded_ids)) {
            $ids = implode(',', $excluded_ids);
            list($in_sql, $in_params) = $DB->get_in_or_equal($excluded_ids, SQL_PARAMS_QM, 'param', false);
            $sql .= " AND c.id $in_sql";
            $params = array_merge($params, $in_params);
        }

        $sql .= " ORDER BY c.fullname";

        return $DB->get_records_sql($sql, $params);
    }

   /**
    * Gets assigned competency templates
    *
    * @param int|object $item the item id
    * @param int $frameworkid default 0 the framework id
    * @return array
    */
    function get_assigned_competency_templates($item, $frameworkid=0) {
        global $DB;

        if (is_object($item)) {
            $itemid = $item->id;
        } else if (is_numeric($item)) {
            $itemid = $item;
        }

        $sql = "SELECT
                    c.*,
                    cf.id AS fid,
                    cf.fullname AS framework,
                    oc.id AS aid
                FROM
                    {org_competencies} oc
                INNER JOIN
                    {comp_template} c
                 ON oc.templateid = c.id
                INNER JOIN
                    {comp_framework} cf
                 ON c.frameworkid = cf.id
                WHERE
                    oc.competencyid IS NULL
                AND oc.organisationid = ?
            ";

        if (!empty($frameworkid)) {
            $sql .= " AND c.frameworkid = ?";
        }

        return $DB->get_records_sql($sql, array($itemid, $frameworkid));
    }

   /**
    * prints competency framework pickler
    *
    * @param int $organisationid
    * @param int $currentfw
    */
    function print_comp_framework_picker($organisationid, $currentfw) {
        global $CFG, $DB, $OUTPUT;

        require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/lib.php');

        $edit = optional_param('edit', 'off', PARAM_TEXT);

        $competency = new competency();
        $frameworks = $competency->get_frameworks();

        $assignedcounts = $DB->get_records_sql_menu("SELECT comp.frameworkid, COUNT(*)
            FROM {org_competencies} orgcomp
            INNER JOIN {comp} comp ON orgcomp.competencyid=comp.id
            WHERE orgcomp.organisationid= ? GROUP BY comp.frameworkid", array($organisationid));

        $out = '';

        $out .= html_writer::start_tag('div', array('class' => "frameworkpicker"));
        if (!empty($frameworks)) {
            $fwoptions = array();
            foreach ($frameworks as $fw) {
                $count = isset($assignedcounts[$fw->id]) ? $assignedcounts[$fw->id] : 0;
                $fwoptions[$fw->id] = $fw->fullname . " ({$count})";
            }
            $fwoptions = count($fwoptions) > 1 ? array(0 => get_string('all')) + $fwoptions : $fwoptions;
            $out .= html_writer::start_tag('div', array('class' => "hierarchyframeworkpicker"));

            $out .= get_string('filterframework', 'totara_hierarchy') . $OUTPUT->single_select(
                new moodle_url('/totara/hierarchy/item/view.php', array('id' => $organisationid, 'edit' => $edit, 'prefix' => 'organisation')),
                'framework',
                $fwoptions,
                $currentfw,
                null,
                'switchframework');

            $out .= html_writer::end_tag('div');
        } else {
            $out .= get_string('competencynoframeworks', 'totara_hierarchy');
        }
        $out .= html_writer::end_tag('div');

        return $out;
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

        list($idssql, $idsparams) = sql_sequence('organisationid', $ids);
        // number of organisation assignment records
        $data['org_assignment'] = $DB->count_records_select('pos_assignment', $idssql, $idsparams);

        // number of assigned competencies
        $data['assigned_comps'] = $DB->count_records_select('org_competencies', $idssql, $idsparams);

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

        if ($stats['org_assignment'] > 0) {
            $message .= get_string('organisationdeleteincludexposassignments', 'totara_hierarchy', $stats['org_assignment']) . html_writer::empty_tag('br');
        }

        if ($stats['assigned_comps'] > 0) {
            $message .= get_string('organisationdeleteincludexlinkedcompetencies', 'totara_hierarchy', $stats['assigned_comps']). html_writer::empty_tag('br');
        }

        return $message;
    }

}
