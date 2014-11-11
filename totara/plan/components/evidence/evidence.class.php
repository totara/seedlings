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
 * @author Russell England <russell.england@totaralms.com>
 * @package totara
 * @subpackage plan
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    //  It must be included from a Moodle page
}

class dp_evidence_relation {

    protected $planid;
    protected $componentname;
    protected $itemid;

    /**
     * Constructor
     *
     * @access  public
     * @param int    $planid Plan ID
     * @param string $componentname Name of component - competency, objective, program, course
     * @param int    $itemid ID of the component
     * @return  void
     */
    public function __construct($planid, $componentname, $itemid) {
        $this->planid = $planid;
        $this->componentname = $componentname;
        $this->itemid = $itemid;
    }

    /**
     * Displays all linked evidence for this component
     *
     * Used by view.php for each component
     *
     * @param object $currenturl
     * @param bool $canupdate
     * @param bool $plancompleted
     * @todo Display the remove selected button after adding evidence
     * @return type $out - output for display
     */
    public function display_linked_evidence($currenturl, $canupdate, $plancompleted) {
        global $OUTPUT;

        $evidencename = get_string('evidence', 'totara_plan');
        $out = '';

        $out .= html_writer::empty_tag('br');
        $out .= $OUTPUT->heading(get_string('linkedx', 'totara_plan', $evidencename), 3, null, 'dp-component-evidence-header');

        if ($canupdate && !$plancompleted) {
            // Do not alter the $currenturl parameter!
            $url = new moodle_url($currenturl, array('action' => 'removelinkedevidence', 'sesskey' => sesskey()));
            $out .=  html_writer::start_tag('form', array('id' => 'dp-component-evidence-update',
                'action' => $url->out(false), 'method' => 'POST'));
        }

        $out .= $OUTPUT->container_start('', 'dp-component-evidence-container');

        if ($this->linked_evidence_exists()) {
            $out .=  $this->list_linked_evidence($canupdate && !$plancompleted);
        } else {
            $out .=  html_writer::tag('p', get_string('nolinkedx', 'totara_plan', mb_strtolower($evidencename, "UTF-8")),
                    array('class' => 'noitems-assignevidence'));
        }

        if ($canupdate && !$plancompleted) {
            $class = 'plan-remove-selected';
            if (!$this->linked_evidence_exists()) {
                $class = 'plan-remove-selected-hidden';
            }
            $out .=  html_writer::empty_tag('input', array(
                'type' => 'submit',
                'value' => get_string('removeselected', 'totara_plan'),
                'class' => $class,
                'id' => 'remove-selected-evidence'));
            $out .=  html_writer::end_tag('form');
        }

        $out .=  $OUTPUT->container_end();

        if (!$plancompleted) {
            $out .= $OUTPUT->container_start('buttons');
            $out .= $OUTPUT->container_start('singlebutton dp-plan-assign-button');
            $out .= $OUTPUT->container_start();
            $out .= html_writer::script('var item_id = ' . $this->itemid . ';' .
                    'var plan_id = ' . $this->planid . ';' . 'var component_name = "' . $this->componentname . '";');
            $out .= $OUTPUT->single_submit(get_string('addlinkedevidence', 'totara_plan'),
                    array('id' => 'show-evidence-dialog'));

            $out .= $OUTPUT->container_end();
            $out .= $OUTPUT->container_end();
            $out .= $OUTPUT->container_end();
        }

        return $out;
    }

    /**
     * Remove selected evidence from this component
     *
     * Used by view.php for each component
     *
     * @param array $selectedids - evidence ids to remove
     * @param object $currenturl - url to redirect to
     */
    public function remove_linked_evidence($selectedids, $currenturl) {
        global $DB;

        if (!empty($selectedids)) {
            // See which ones have been marked for deletion
            $deleteids = array();
            foreach ($selectedids as $evidenceid => $delete) {
                if ($delete) {
                    $deleteids[] = $evidenceid;
                }
            }

            if (!empty($deleteids)) {
                // $deletethese = implode(',', $deleteids);
                list($insql, $inparams) = $DB->get_in_or_equal($deleteids);
                $sql = "
                    SELECT evidenceid
                    FROM {dp_plan_evidence_relation}
                    WHERE planid = ?
                    AND component = ?
                    AND itemid = ?
                    AND NOT id $insql";
                $params = array($this->planid, $this->componentname, $this->itemid);
                $params = array_merge($params, $inparams);
                // Get an array of ids to keep
                $keepids = array();
                if ($keeps = $DB->get_records_sql($sql, $params)) {
                    foreach ($keeps as $keep) {
                        $keepids[] = $keep->evidenceid;
                    }
                }

                // Remove them
                $this->update_linked_evidence($keepids);

                totara_set_notification(get_string('selectedlinkedevidenceremovedfrom'.$this->componentname, 'totara_plan'),
                        $currenturl, array('class' => 'notifysuccess'));
            }
        }

        redirect($currenturl);
    }

    /**
     * List of linked evidence for this component
     *
     * Used by $this->display_linked_evidence() and update-evidence.php
     *
     * @return  false|string  $out  the table to display
     */
    public function list_linked_evidence($canremove) {
        global $DB;

        $selectsql = "SELECT er.id AS id, e.name AS fullname";
        $countsql = "SELECT COUNT('x')";
        $wheresql = "
            FROM {dp_plan_evidence_relation} er
            JOIN {dp_plan_evidence} e ON e.id = er.evidenceid
            WHERE er.planid = ?
            AND er.component = ?
            AND er.itemid = ?";
        $ordersql = " ORDER BY e.name";

        $params = array($this->planid, $this->componentname, $this->itemid);
        if (!$items = $DB->get_recordset_sql($selectsql . $wheresql . $ordersql, $params)) {
            return false;
        }
        $numberrows = $DB->count_records_sql($countsql . $wheresql, $params);
        $rownumber = 0;

        $tableheaders = array(get_string('evidencename', 'totara_plan'));

        $tablecolumns = array('fullname');

        if ($canremove) {
            $tableheaders[] = get_string('remove', 'totara_plan', get_string('evidence', 'totara_plan'));
            $tablecolumns[] = 'remove';
        }

        // Start output buffering to bypass echo statements in $table->add_data()
        ob_start();
        $table = new flexible_table('linkedevidencelist');
        $table->define_columns($tablecolumns);
        $table->define_headers($tableheaders);
        $url = new moodle_url('/totara/plan/component.php', array('id' => $this->planid, 'c' => $this->componentname));
        $table->define_baseurl($url);
        $table->set_attribute('class', 'logtable generalbox dp-plan-evidence-items');
        $table->setup();

        foreach ($items as $item) {
            $row = array();
            $row[] = $this->display_item_name($item);

            if ($canremove) {
                $row[] = html_writer::checkbox('delete_linked_evidence['.$item->id.']', '1', false);
            }

            if (++$rownumber >= $numberrows) {
                $table->add_data($row, 'last');
            } else {
                $table->add_data($row);
            }
        }

        $items->close();

        // return instead of outputing table contents
        $table->finish_html();
        $out = ob_get_contents();
        ob_end_clean();

        return $out;
    }

    /**
     * Print details about an evidence relation
     *
     * Used by evidence/view.php
     *
     * @global object $CFG
     * @global object $DB
     * @param int $evidenceid
     * @prama object $component
     * @return void
     */
    static public function display_linked_evidence_detail($linkedid, $delete = false) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/totara/plan/record/evidence/lib.php');

        if (!$item = $DB->get_record('dp_plan_evidence_relation', array('id' => $linkedid), 'evidenceid')) {
            return false;
        }

        return display_evidence_detail($item->evidenceid, $delete);;
    }

    /**
     * Check if evidence is linked to this item
     *
     * @global type $DB
     * @return type bool - True if evidence is linked to this item
     */
    public function linked_evidence_exists() {
        global $DB;
        $params = array('planid' => $this->planid, 'component' => $this->componentname, 'itemid' => $this->itemid);
        return($DB->record_exists('dp_plan_evidence_relation', $params));
    }

    /**
     * Add/Delete selected evidence
     *
     * Used by $this->remove_linked_evidence() and update-evidence.php
     *
     * @param array $evidenceids array of evidence IDs that are selected / to be kept
     *
     * @return void
     */
    public function update_linked_evidence($evidenceids) {
        global $DB;

        // find all matching evidence for this plan and component
        $sql = "SELECT id, evidenceid
                FROM {dp_plan_evidence_relation}
                WHERE planid = ?
                AND component = ?
                AND itemid = ?";
        $params = array($this->planid, $this->componentname, $this->itemid);
        $items = $DB->get_records_sql($sql, $params);

        // Delete current links that are not in the selected ids
        // or where they are already selected
        foreach ($items as $item) {
            $position = array_search($item->evidenceid, $evidenceids);
            if (empty($position)) {
                // Deselected
                $DB->delete_records('dp_plan_evidence_relation', array('id' => $item->id));
            } else {
                // Already exists
                unset($evidenceids[$position]);
            }
        }

        // Add any remaining selected ids
        if (!empty($evidenceids)) {
            foreach ($evidenceids as $evidenceid) {
                $relation = new stdClass();
                $relation->id = 0;
                $relation->evidenceid = $evidenceid;
                $relation->planid = $this->planid;
                $relation->component = $this->componentname;
                $relation->itemid = $this->itemid;
                $DB->insert_record('dp_plan_evidence_relation', $relation);
            }
        }

    }

    /**
     * Display a warning if evidence is attached to a component
     *
     * Used by post_header_hook() in each learning plan component class
     *
     * @global type $OUTPUT
     * @global type $DB
     * @return string html output warning
     */
    public function display_delete_warning() {
        global $OUTPUT, $DB;
        $out = '';

        $sql = "SELECT e.name
                FROM {dp_plan_evidence} e
                JOIN {dp_plan_evidence_relation} er ON er.evidenceid = e.id
                WHERE er.planid = ?
                AND er.component = ?
                AND er.itemid = ?
                ORDER BY e.name";

        if ($items = $DB->get_fieldset_sql($sql, array($this->planid, $this->componentname, $this->itemid))) {
            $out = $OUTPUT->box_start('generalbox', 'notice');
            $out .= $OUTPUT->heading(get_string('deletelinkedevidenceheader', 'totara_plan'));
            $out .= html_writer::tag('p', get_string('deletelinkedevidencemessage', 'totara_plan'));
            $out .= html_writer::alist($items);
            $out .= $OUTPUT->box_end();
        }
        return $out;
    }


    /**
     * Display item's name
     *
     * Used by $this->list_linked_evidence()
     *
     * @access  public
     * @param   object  $item Evidence item record object
     * @return  string html text to display
     */
    public function display_item_name($item) {
        global $OUTPUT;
        $icon = 'evidence-regular';
        $img = $OUTPUT->pix_icon('/msgicons/' . $icon,
                format_string($item->fullname),
                'totara_core',
                array('class' => 'evidence-state-icon'));
        $url = new moodle_url('/totara/plan/components/evidence/view.php',
                array('id' => $item->id));
        $link = $OUTPUT->action_link($url, format_string($item->fullname));
        return $img . $link;
    }
}
