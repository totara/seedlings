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
 * @author Aaron Wells <aaronw@catalyst.net.nz>
 * @package totara
 * @subpackage cohort
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    // It must be included from a Moodle page
}

global $CFG;
require_once($CFG->dirroot.'/cohort/lib.php');

/**
 * A report builder source for a cohort's "learning items", which includes "enrolled items", i.e. courses & programs that
 * the cohort's members should be enrolled in
 */
class rb_source_cohort_associations extends rb_base_source {

    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns, $sourcetitle;

    /**
     * Constructor
     * @global object $CFG
     */
    public function __construct() {
        $this->base = "(SELECT e.id, e.customint1 AS cohortid, e.courseid AS instanceid,
                c.fullname AS name, c.icon, " . COHORT_ASSN_ITEMTYPE_COURSE . " AS instancetype
            FROM {enrol} e
            JOIN {course} c ON e.courseid = c.id
            WHERE e.enrol = 'cohort'
            UNION ALL
            SELECT pa.id, pa.assignmenttypeid AS cohortid, p.id AS instanceid,
                p.fullname AS name, p.icon, " . COHORT_ASSN_ITEMTYPE_PROGRAM . " AS instancetype
            FROM {prog_assignment} pa
            JOIN {prog} p ON pa.programid = p.id
            WHERE pa.assignmenttype = " . ASSIGNTYPE_COHORT . ")";
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->requiredcolumns = array();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_cohort_associations');
        parent::__construct();
    }

    //
    //
    // Methods for defining contents of source
    //
    //

    /**
     * Creates the array of rb_join objects required for this->joinlist
     *
     * @global object $CFG
     * @return array
     */
    private function define_joinlist() {
        global $CFG;

        $joinlist = array();

        $joinlist[] = new rb_join(
            'cohort',
            'INNER',
            '{cohort}',
            'base.cohortid = cohort.id',
            REPORT_BUILDER_RELATION_MANY_TO_ONE
        );

        $joinlist[] = new rb_join(
            'associations',
            'LEFT',
            '{cohort_visibility}',
            'base.cohortid = associations.cohortid',
            REPORT_BUILDER_RELATION_MANY_TO_ONE
        );

        return $joinlist;
    }

    /**
     * Creates the array of rb_column_option objects required for
     * $this->columnoptions
     *
     * @return array
     */
    protected function define_columnoptions() {
        $columnoptions = array();

        $columnoptions[] = new rb_column_option(
            'associations',
            'name',
            get_string('associationname', 'totara_cohort'),
            'base.name',
            array('dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'associations',
            'type',
            get_string('associationtype', 'totara_cohort'),
            'base.instancetype',
            array('displayfunc'=>'associationtype')
        );
        $columnoptions[] = new rb_column_option(
            'associations',
            'nameiconlink',
            get_string('associationnameiconlink', 'totara_cohort'),
            'base.name',
            array(
                'displayfunc'=>'associationnameiconlink',
                'extrafields'=>array(
                    'insid'=> 'base.instanceid',
                    'icon' => 'base.icon',
                    'type' => 'base.instancetype'
                )
            )
        );
        $columnoptions[] = new rb_column_option(
            'associations',
            'actionsenrolled',
            get_string('associationactionsenrolled', 'totara_cohort'),
            'base.id',
            array(
                'displayfunc' => 'associationactionsenrolled',
                'extrafields' => array('cohortid' => 'base.cohortid', 'type' => 'base.instancetype'),
                'nosort' => true
            )
        );
        $columnoptions[] = new rb_column_option(
            'cohort',
            'name',
            get_string('name', 'totara_cohort'),
            'cohort.name',
            array('joins' => 'cohort',
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'cohort',
            'idnumber',
            get_string('idnumber', 'totara_cohort'),
            'cohort.idnumber',
            array('joins' => 'cohort',
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'associations',
            'programcompletionlink',
            get_string('associationprogramcompletionlink', 'totara_cohort'),
            'base.instanceid',
            array(
                'displayfunc' => 'programcompletionlink',
                'extrafields' => array(
                    'type' => 'base.instancetype',
                    'cohortid' => 'base.cohortid'
                )
            )
        );

        return $columnoptions;
    }


    /**
     * Creates the array of rb_filter_option objects required for $this->filteroptions
     * @return array
     */
    protected function define_filteroptions() {
        global $CFG;
        $filteroptions = array();
        $filteroptions[] = new rb_filter_option(
            'associations',
            'name',
            get_string('associationname', 'totara_cohort'),
            'text'
        );
        $filteroptions[] = new rb_filter_option(
            'associations',
            'type',
            get_string('associationtype', 'totara_cohort'),
            'select',
            array(
                'selectchoices' => array(
                    COHORT_ASSN_ITEMTYPE_COURSE => get_string('associationcoursesonly', 'totara_cohort'),
                    COHORT_ASSN_ITEMTYPE_PROGRAM  => get_string('associationprogramsonly', 'totara_cohort'),
                ),
                'simplemode' => true,
            )
        );
        $filteroptions[] = new rb_filter_option(
            'cohort',
            'name',
            get_string('name', 'totara_cohort'),
            'text'
        );
        $filteroptions[] = new rb_filter_option(
            'cohort',
            'idnumber',
            get_string('idnumber', 'totara_cohort'),
            'text'
        );
        return $filteroptions;
    }


    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'associations',
                'value' => 'name',
            ),
            array(
                'type' => 'associations',
                'value' => 'type',
            )
        );
        return $defaultcolumns;
    }

    protected function define_defaultfilters() {
        $defaultfilters = array();
        $defaultfilters[] = array(
            'type' => 'associations',
            'value' => 'name',
            'advanced' => 0,
        );
        $defaultfilters[] = array(
            'type' => 'associations',
            'value' => 'type',
            'advanced' => 0,
        );

        return $defaultfilters;
    }
    /**
     * Creates the array of rb_content_option object required for $this->contentoptions
     * @return array
     */
    protected function define_contentoptions() {
        $contentoptions = array();
        return $contentoptions;
    }

    protected function define_paramoptions() {
        $paramoptions = array();
        $paramoptions[] = new rb_param_option(
            'cohortid',
            'base.cohortid'
        );
        $paramoptions[] = new rb_param_option(
            'type',
            'base.instancetype'
        );
        return $paramoptions;
    }

    /**
     * Helper function to display a string describing the learning item's type
     * @param int $instancetype
     * @param object $row
     * @return str
     */
    public function rb_display_associationtype($instancetype, $row) {
        switch ($instancetype) {
            case COHORT_ASSN_ITEMTYPE_COURSE:
                $ret = get_string('course');
                break;
            case COHORT_ASSN_ITEMTYPE_PROGRAM:
                $ret = get_string('program', 'totara_program');
                break;
            default:
                $ret = '';
        }
        return $ret;
    }

    /**
     * Helper function to display the learning item's name, with its icon and a link to it
     * @param str $instancename
     * @param object $row
     * @return str
     */
    public function rb_display_associationnameiconlink($instancename, $row) {

        if ($row->type == COHORT_ASSN_ITEMTYPE_COURSE) {
            $url = new moodle_url('/course/view.php', array('id' => $row->insid));
        } else {
            $url = new moodle_url('/totara/program/view.php', array('id' => $row->insid));
        }
        return html_writer::link($url, s($instancename));
    }

    private function cohort_association_delete_link($associationid, $row) {
        global $OUTPUT;

        static $strdelete = false;
        if ($strdelete === false) {
            $strdelete = get_string('deletelearningitem', 'totara_cohort');
        }
        $delurl = new moodle_url('/totara/cohort/dialog/updatelearning.php',
            array('cohortid' => $row->cohortid,
            'type' => $row->type,
            'd' => $associationid,
            'sesskey' => sesskey()));
        return html_writer::link($delurl, $OUTPUT->pix_icon('t/delete', $strdelete), array('title' => $strdelete, 'class' => 'learning-delete'));
    }

    /**
     * Helper function to display the action links for the "enrolled learning" page
     * @param int $associationid
     * @param object $row
     * @return str
     */
    public function rb_display_associationactionsenrolled($associationid, $row) {
        static $canedit = null;
        if ($canedit === null) {
            $canedit = has_capability('moodle/cohort:manage', context_system::instance());
        }

        if ($canedit) {
            return $this->cohort_association_delete_link($associationid, $row);
        }
        return '';
    }

    /**
     * Helper function to display the "Set completion date" link for a program (should only be used with enrolled items)
     * @param $instanceid
     * @param $row
     */
    public function rb_display_programcompletionlink($instanceid, $row) {
        global $DB;

        static $canedit = null;
        if ($canedit === null) {
            $canedit = has_capability('moodle/cohort:manage', context_system::instance());
        }

        if ($canedit && $row->type == COHORT_ASSN_ITEMTYPE_PROGRAM) {
            return totara_cohort_program_completion_link($row->cohortid, $instanceid);
        }
        return get_string('na', 'totara_cohort');
    }
}
