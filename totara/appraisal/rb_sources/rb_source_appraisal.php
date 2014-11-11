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
 * @author Nathan Lewis <nathan.lewis@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

defined('MOODLE_INTERNAL') || die();

class rb_source_appraisal extends rb_base_source {
    public $base, $joinlist, $columnoptions, $filteroptions, $paramoptions;
    public $contentoptions, $defaultcolumns, $defaultfilters, $embeddedparams;
    public $sourcetitle, $shortname;

    public function __construct() {
        $this->base = '{appraisal_user_assignment}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->embeddedparams = $this->define_embeddedparams();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_appraisal');
        $this->shortname = 'appraisal_status';

        parent::__construct();
    }

    protected function define_joinlist() {
        $joinlist = array(
            new rb_join(
                'appraisal',
                'LEFT',
                '{appraisal}',
                'appraisal.id = base.appraisalid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'activestage',
                'LEFT',
                '{appraisal_stage}',
                'activestage.id = base.activestageid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'previousstage',
                'LEFT',
                '(SELECT aua.id AS appraisaluserassignmentid, MAX(asd.timecompleted) AS timecompleted
                    FROM {appraisal_stage_data} asd
                    JOIN {appraisal_role_assignment} ara
                      ON asd.appraisalroleassignmentid = ara.id
                    JOIN {appraisal_user_assignment} aua
                      ON ara.appraisaluserassignmentid = aua.id
                   WHERE asd.appraisalstageid != aua.activestageid
                   GROUP BY aua.id)',
                'previousstage.appraisaluserassignmentid = base.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'aralearner',
                'LEFT',
                '(SELECT * FROM {appraisal_role_assignment} WHERE appraisalrole = 1)',
                'aralearner.appraisaluserassignmentid = base.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'aramanager',
                'LEFT',
                '(SELECT * FROM {appraisal_role_assignment} WHERE appraisalrole = 2)',
                'aramanager.appraisaluserassignmentid = base.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'arateamlead',
                'LEFT',
                '(SELECT * FROM {appraisal_role_assignment} WHERE appraisalrole = 4)',
                'arateamlead.appraisaluserassignmentid = base.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'araappraiser',
                'LEFT',
                '(SELECT * FROM {appraisal_role_assignment} WHERE appraisalrole = 8)',
                'araappraiser.appraisaluserassignmentid = base.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            )
        );

        $this->add_user_table_to_joinlist($joinlist, 'base', 'userid');
        $this->add_position_tables_to_joinlist($joinlist, 'base', 'userid');
        $this->add_manager_tables_to_joinlist($joinlist, 'position_assignment', 'reportstoid');

        return $joinlist;
    }

    protected function define_columnoptions() {
        global $CFG;
        require_once($CFG->dirroot . '/totara/appraisal/lib.php');

        $columnoptions = array(
            new rb_column_option(
                'userappraisal',
                'activestageid',
                '',
                'base.activestageid',
                array('selectable' => false)
            ),
            new rb_column_option(
                'userappraisal',
                'timecompleted',
                get_string('userappraisaltimecompletedcolumn', 'rb_source_appraisal'),
                'base.timecompleted',
                array('displayfunc' => 'nice_date',
                      'dbdatatype' => 'timestamp',
                      'defaultheading' => get_string('userappraisaltimecompletedheading', 'rb_source_appraisal'))
            ),
            new rb_column_option(
                'userappraisal',
                'previousstagetimecompleted',
                get_string('userappraisalpreviousstagetimecompletedcolumn', 'rb_source_appraisal'),
                'previousstage.timecompleted',
                array('joins' => array('previousstage'),
                      'displayfunc' => 'nice_date',
                      'dbdatatype' => 'timestamp',
                      'defaultheading' => get_string('userappraisalpreviousstagetimecompletedheading', 'rb_source_appraisal'))
            ),
            new rb_column_option(
                'userappraisal',
                'status',
                get_string('userappraisalstatuscolumn', 'rb_source_appraisal'),
                "CASE WHEN base.status = " . appraisal::STATUS_COMPLETED . " AND base.timecompleted IS NOT NULL THEN 'statuscomplete' " .
                     "WHEN base.status = " . appraisal::STATUS_CLOSED . " AND appraisal.status = " . appraisal::STATUS_ACTIVE . " THEN 'statuscancelled' " .
                     "WHEN base.status = " . appraisal::STATUS_CLOSED . " AND (appraisal.status = " . appraisal::STATUS_CLOSED .
                            " OR appraisal.status = " . appraisal::STATUS_COMPLETED . " ) THEN 'statusincomplete' " .
                     "WHEN base.status = " . appraisal::STATUS_ACTIVE . " AND activestage.timedue < " . time() . " THEN 'statusoverdue' " .
                     "WHEN base.status = " . appraisal::STATUS_ACTIVE . " AND activestage.timedue >= " . time() . " THEN 'statusontarget' " .
                     "ELSE 'statusdraft' " .
                "END",
                array('joins' => array('appraisal', 'activestage'),
                      'displayfunc' => 'status',
                      'defaultheading' => get_string('userappraisalstatusheading', 'rb_source_appraisal'))
            ),
            new rb_column_option(
                'userappraisal',
                'activestagename',
                get_string('userappraisalactivestagenamecolumn', 'rb_source_appraisal'),
                'activestage.name',
                array('joins' => 'activestage',
                      'defaultheading' => get_string('userappraisalactivestagenameheading', 'rb_source_appraisal'),
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'userappraisal',
                'activestagetimedue',
                get_string('userappraisalactivestagetimeduecolumn', 'rb_source_appraisal'),
                'activestage.timedue',
                array('joins' => 'activestage',
                      'displayfunc' => 'nice_date',
                      'dbdatatype' => 'timestamp',
                      'defaultheading' => get_string('userappraisalactivestagetimedueheading', 'rb_source_appraisal'))
            ),
            new rb_column_option(
                'appraisal',
                'name',
                get_string('appraisalnamecolumn', 'rb_source_appraisal'),
                'appraisal.name',
                array('joins' => 'appraisal',
                      'defaultheading' => get_string('appraisalnameheading', 'rb_source_appraisal'),
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'appraisal',
                'status',
                get_string('appraisalstatuscolumn', 'rb_source_appraisal'),
                'appraisal.status',
                array('joins' => 'appraisal',
                      'displayfunc' => 'appraisalstatus',
                      'defaultheading' => get_string('appraisalstatusheading', 'rb_source_appraisal'))
            ),
            new rb_column_option(
                'appraisal',
                'timestarted',
                get_string('appraisaltimestartedcolumn', 'rb_source_appraisal'),
                'appraisal.timestarted',
                array('joins' => 'appraisal',
                      'displayfunc' => 'nice_date',
                      'dbdatatype' => 'timestamp',
                      'defaultheading' => get_string('appraisaltimestartedheading', 'rb_source_appraisal'))
            ),
            new rb_column_option(
                'appraisal',
                'timefinished',
                get_string('appraisaltimefinishedcolumn', 'rb_source_appraisal'),
                'appraisal.timefinished',
                array('joins' => 'appraisal',
                      'displayfunc' => 'nice_date',
                      'dbdatatype' => 'timestamp',
                      'defaultheading' => get_string('appraisaltimefinishedheading', 'rb_source_appraisal'))
            )
        );

        $this->add_user_fields_to_columns($columnoptions);
        $this->add_position_fields_to_columns($columnoptions);
        $this->add_manager_fields_to_columns($columnoptions);

        return $columnoptions;
    }

    protected function define_filteroptions() {
        $filteroptions = array(
            new rb_filter_option(
                'userappraisal',
                'activestageid',
                get_string('userappraisalactivestagenamecolumn', 'rb_source_appraisal'),
                'select',
                array('selectfunc' => 'activestagename')
            ),
            new rb_filter_option(
                'userappraisal',
                'status',
                get_string('userappraisalstatuscolumn', 'rb_source_appraisal'),
                'select',
                array('selectfunc' => 'status')
            ),
            new rb_filter_option(
                'appraisal',
                'status',
                get_string('appraisalstatuscolumn', 'rb_source_appraisal'),
                'select',
                array('selectfunc' => 'appraisalstatus')
            ),
        );

        $this->add_user_fields_to_filters($filteroptions);
        $this->add_position_fields_to_filters($filteroptions);
        $this->add_manager_fields_to_filters($filteroptions);

        return $filteroptions;
    }

    protected function define_paramoptions() {
        $paramoptions = array(
            new rb_param_option('appraisalid', 'base.appraisalid'),
            new rb_param_option('filterstatus',
                "CASE WHEN base.status = " . appraisal::STATUS_COMPLETED . " AND base.timecompleted IS NOT NULL THEN 'statuscomplete' " .
                     "WHEN base.status = " . appraisal::STATUS_CLOSED . " AND appraisal.status = " . appraisal::STATUS_ACTIVE . " THEN 'statuscancelled' " .
                     "WHEN base.status = " . appraisal::STATUS_CLOSED . " AND (appraisal.status = " . appraisal::STATUS_CLOSED .
                            " OR appraisal.status = " . appraisal::STATUS_COMPLETED . " ) THEN 'statusincomplete' " .
                     "WHEN base.status = " . appraisal::STATUS_ACTIVE . " AND activestage.timedue < " . time() . " THEN 'statusoverdue' " .
                     "WHEN base.status = " . appraisal::STATUS_ACTIVE . " AND activestage.timedue >= " . time() . " THEN 'statusontarget' " .
                     "ELSE 'statusdraft' " .
                "END", array('appraisal', 'activestage'), 'string')
        );

        return $paramoptions;
    }


    protected function define_contentoptions() {
        $contentoptions = array(
            new rb_content_option(
                'current_pos',
                get_string('currentpos', 'totara_reportbuilder'),
                'position.path',
                'position'
            ),
            new rb_content_option(
                'current_org',
                get_string('currentorg', 'totara_reportbuilder'),
                'organisation.path',
                'organisation'
            ),
            new rb_content_option(
                'user',
                get_string('user', 'rb_source_appraisal'),
                array(
                    'userid' => 'base.userid',
                    'managerid' => 'position_assignment.managerid',
                    'managerpath' => 'position_assignment.managerpath',
                    'postype' => 'position_assignment.type',
                ),
                'position_assignment'
            ),
            new rb_content_option(
                'date',
                get_string('completiondate', 'rb_source_appraisal'),
                'base.timecompleted'
            ),
        );
        return $contentoptions;
    }


    /**
     * Convert status code string to human readable string.
     *
     * @param string $status status code string
     * @param object $row other fields in the record (unused)
     *
     * @return string
     */
    public function rb_display_status($status, $row) {
        return get_string($status, 'rb_source_appraisal');
    }

    /**
     * Convert appraisal status code string to human readable string.
     * @param string $status status code string
     * @param object $row other fields in the record (unused)
     *
     * @return string
     */
    public function rb_display_appraisalstatus($status, $row) {
        global $CFG;
        require_once($CFG->dirroot.'/totara/appraisal/lib.php');

        return appraisal::display_status($status);
    }

    /**
     * Filter current stage.
     *
     * @param reportbuilder $report
     * @return array
     */
    public function rb_filter_activestagename($report) {
        global $CFG;
        require_once($CFG->dirroot . "/totara/appraisal/lib.php");

        $stagenames = array();

        $appraisalid = $report->get_param_value('appraisalid');
        if ($appraisalid) {
            $appraisal = new appraisal($appraisalid);
            $stages = appraisal_stage::get_stages($appraisalid);
            foreach ($stages as $stage) {
                $stagenames[$stage->id] = $appraisal->name . ': ' . $stage->name;
            }
        } else {
            $stages = appraisal_stage::get_all_stages();
            foreach ($stages as $stage) {
                $stagenames[$stage->id] = $stage->appraisalname . ': ' . $stage->stagename;
            }
        }

        return $stagenames;
    }

    /**
     * Filter current stage.
     *
     * @return array
     */
    public function rb_filter_status() {
        global $CFG;
        require_once($CFG->dirroot . "/totara/appraisal/lib.php");

        $statuses = array();

        $statuses['statuscomplete'] = get_string('statuscomplete', 'rb_source_appraisal');
        $statuses['statuscancelled'] = get_string('statuscancelled', 'rb_source_appraisal');
        $statuses['statusincomplete'] = get_string('statusincomplete', 'rb_source_appraisal');
        $statuses['statusoverdue'] = get_string('statusoverdue', 'rb_source_appraisal');
        $statuses['statusontarget'] = get_string('statusontarget', 'rb_source_appraisal');

        return $statuses;
    }

    public function rb_filter_appraisalstatus() {
        global $CFG;
        require_once($CFG->dirroot . "/totara/appraisal/lib.php");

        $statuses = array();
        $statuses[appraisal::STATUS_DRAFT] = appraisal::display_status(appraisal::STATUS_DRAFT);
        $statuses[appraisal::STATUS_ACTIVE] = appraisal::display_status(appraisal::STATUS_ACTIVE);
        $statuses[appraisal::STATUS_CLOSED] = appraisal::display_status(appraisal::STATUS_CLOSED);
        $statuses[appraisal::STATUS_COMPLETED] = appraisal::display_status(appraisal::STATUS_COMPLETED);

        return $statuses;
    }

    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'user',
                'value' => 'namelink',
            ),
            array(
                'type' => 'appraisal',
                'value' => 'name',
            ),
            array(
                'type' => 'userappraisal',
                'value' => 'activestagename',
            ),
            array(
                'type' => 'userappraisal',
                'value' => 'activestagetimedue',
            ),
            array(
                'type' => 'userappraisal',
                'value' => 'timecompleted',
            ),
            array(
                'type' => 'userappraisal',
                'value' => 'status',
            )
        );

        return $defaultcolumns;
    }

    protected function define_defaultfilters() {
        $defaultfilters = array(
            array(
                'type' => 'userappraisal',
                'value' => 'activestageid',
            ),
            array(
                'type' => 'userappraisal',
                'value' => 'status',
            )
        );

        return $defaultfilters;
    }

    protected function define_embeddedparams() {
        $embeddedparams = array();

        return $embeddedparams;
    }

}
