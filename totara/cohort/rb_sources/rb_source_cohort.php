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
 * @subpackage reportbuilder
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page
}
global $CFG;
require_once($CFG->dirroot.'/cohort/lib.php');
/**
 * A report builder source for the "cohorts" table.
 */
class rb_source_cohort extends rb_base_source {

    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns, $sourcetitle;

    /**
     * Constructor
     * @global object $CFG
     */
    public function __construct() {
        $this->base = '{cohort}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->requiredcolumns = array();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_cohort');

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
    protected function define_joinlist() {
        global $CFG;

        $joinlist = array(
                        new rb_join(
                            'members', // Table alias?
                            'LEFT', // Type of join.
                            '{cohort_members}',
                            'base.id = members.cohortid', // How it is joined.
                            REPORT_BUILDER_RELATION_ONE_TO_MANY
                        ),
                        new rb_join(
                            'membercount',
                            'LEFT', // Type of join.
                            '(SELECT cohortid, count(id) AS count FROM {cohort_members} GROUP BY cohortid)',
                            'base.id = membercount.cohortid', // How it is joined.
                            REPORT_BUILDER_RELATION_ONE_TO_ONE
                        ),
        );

        $this->add_user_table_to_joinlist($joinlist, 'members', 'userid');
        $this->add_position_tables_to_joinlist($joinlist, 'members', 'userid');
        $this->add_tag_tables_to_joinlist('cohort', $joinlist, 'base', 'id');

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
            'cohort',  // Which table? Type.
            'name', // Alias for the field.
            get_string('name', 'totara_cohort'), // Name for the column.
            'base.name', // Table alias and field name.
            array('dbdatatype' => 'char',
                  'outputformat' => 'text') // Options.
        );
        $columnoptions[] = new rb_column_option(
            'cohort',
            'namelink',
            get_string('namelink', 'totara_cohort'),
            'base.name',
            array(
                'displayfunc' => 'cohort_name_link',
                'extrafields' => array(
                    'cohort_id' => 'base.id'
                )
            )
        );
        $columnoptions[] = new rb_column_option(
            'cohort',
            'idnumber',
            get_string('idnumber', 'totara_cohort'),
            'base.idnumber',
            array('dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'cohort',
            'type',
            get_string('type', 'totara_cohort'),
            'base.cohorttype',
            array(
                'displayfunc' => 'cohort_type'
            )
        );
        $columnoptions[] = new rb_column_option(
            'cohort',
            'numofmembers',
            get_string('numofmembers', 'totara_cohort'),
            'CASE WHEN membercount.count IS NULL THEN 0 ELSE membercount.count END',
            array(
                'joins' => array('membercount'),
                'dbdatatype' => 'integer'
            )
        );
        $columnoptions[] = new rb_column_option(
            'cohort',
            'actions',
            get_string('actions', 'totara_cohort'),
            'base.id',
            array(
                'displayfunc' => 'cohort_actions',
                'nosort' => true
            )
        );
        $columnoptions[] = new rb_column_option(
            'cohort',
            'startdate',
            get_string('startdate', 'totara_cohort'),
            'base.startdate',
            array(
                'displayfunc' => 'nice_date',
                'dbdatatype' => 'timestamp',
            )
        );
        $columnoptions[] = new rb_column_option(
            'cohort',
            'enddate',
            get_string('enddate', 'totara_cohort'),
            'base.enddate',
            array(
                'displayfunc' => 'nice_date',
                'dbdatatype' => 'timestamp',
            )
        );
        $columnoptions[] = new rb_column_option(
            'cohort',
            'status',
            get_string('status', 'totara_cohort'),
            'base.id',
            array(
                'displayfunc' => 'cohort_status',
                'extrafields' => array(
                    'startdate'=>'base.startdate',
                    'enddate'=>'base.enddate'
                )
            )
        );

        $this->add_user_fields_to_columns($columnoptions);
        $this->add_position_fields_to_columns($columnoptions);
        $this->add_tag_fields_to_columns('cohort', $columnoptions);

        return $columnoptions;
    }

    /**
     * Creates the array of rb_filter_option objects required for $this->filteroptions
     * @return array
     */
    protected function define_filteroptions() {
        // No filter options!
        $filteroptions = array();
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
        $filteroptions[] = new rb_filter_option(
            'cohort',
            'type',
            get_string('type', 'totara_cohort'),
            'select',
            array(
                'selectchoices' => array(
                    cohort::TYPE_DYNAMIC => get_string('dynamic', 'totara_cohort'),
                    cohort::TYPE_STATIC  => get_string('set', 'totara_cohort'),
                ),
                'simplemode' => true,
            )
        );
        $this->add_user_fields_to_filters($filteroptions);
        $this->add_tag_fields_to_filters('cohort', $filteroptions);

        return $filteroptions;
    }


    protected function define_defaultcolumns() {
        $defaultcolumns = array(
                            array(
                                'type' => 'cohort',
                                'value' => 'name',
                            ),
                            array(
                                'type' => 'user',
                                'value' => 'fullname',
                            )
        );
        return $defaultcolumns;
    }

    protected function define_defaultfilters() {
        $defaultfilters = array(
                            array(
                                    'type' => 'user',
                                    'value' => 'fullname',
                                    'advanced' => 0,
                                ),
        );

        return $defaultfilters;
    }
    /**
     * Creates the array of rb_content_option object required for $this->contentoptions
     * @return array
     */
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
            )
        );

        return $contentoptions;
    }

    protected function define_paramoptions() {
        $paramoptions = array(
            new rb_param_option(
                'cohortid', // Parameter name.
                'base.id'  // Field.
            ),
            new rb_param_option(
                'contextid', // Parameter name.
                'base.contextid'  // Field.
            ),
        );
        return $paramoptions;
    }
    /**
     * RB helper function to show the name of the cohort with a link to the cohort's details page
     * @param int $cohortid
     * @param object $row
     */
    public function rb_display_cohort_name_link($cohortname, $row ) {
        return html_writer::link(new moodle_url('/cohort/view.php', array('id' => $row->cohort_id)), format_string($cohortname));
    }

    /**
     * RB helper function to show whether a cohort is dynamic or static
     * @param int $cohorttype
     * @param object $row
     */
    public function rb_display_cohort_type($cohorttype, $row) {
        global $CFG;
        require_once($CFG->dirroot.'/cohort/lib.php');

        switch( $cohorttype ) {
            case cohort::TYPE_DYNAMIC:
                $ret = get_string('dynamic', 'totara_cohort');
                break;
            case cohort::TYPE_STATIC:
                $ret = get_string('set', 'totara_cohort');
                break;
            default:
                $ret = get_string('typeunknown', 'totara_cohort', $cohorttype);
        }
        return $ret;
    }

    /**
     * RB helper function to show the "action" links for a cohort -- edit/clone/delete
     * @param int $cohortid
     * @param object $row
     * @return string|string
     */
    public function rb_display_cohort_actions( $cohortid, $row ) {
        global $OUTPUT;

        static $canedit = null;
        if ($canedit === null) {
            $canedit = has_capability('moodle/cohort:manage', context_system::instance());
        }

        if ($canedit) {
            $editurl = new moodle_url('/cohort/edit.php', array('id' => $cohortid));
            $str = html_writer::link(
                $editurl,
                $OUTPUT->pix_icon('t/edit', get_string('edit'), null, array('class' => 'iconsmall')),
                null,
                array('class' => 'action-icon')
            );
            $cloneurl = new moodle_url('/cohort/view.php', array('id' => $cohortid, 'clone' => 1, 'cancelurl' => qualified_me()));
            $str .= html_writer::link(
                $cloneurl,
                $OUTPUT->pix_icon('t/copy', get_string('copy', 'totara_cohort'), null, array('class' => 'iconsmall')),
                null,
                array('class' => 'action-icon')
            );
            $delurl = new moodle_url('/cohort/view.php', array('id'=>$cohortid, 'delete' => 1, 'cancelurl' => qualified_me()));
            $str .= html_writer::link(
                $delurl,
                $OUTPUT->pix_icon('t/delete', get_string('delete'), null, array('class' => 'iconsmall')),
                null,
                array('class' => 'action-icon')
            );
            return $str;
        }
        return '';
    }

    public function rb_display_cohort_status($cohortid, $row) {
        $now = time();
        if (totara_cohort_is_active($row, $now)) {
            return get_string('cohortdateactive', 'totara_cohort');
        }

        if ($row->startdate && $row->startdate > $now) {
            return get_string('cohortdatenotyetstarted', 'totara_cohort');
        }

        if ($row->enddate && $row->enddate < $now) {
            return get_string('cohortdatealreadyended', 'totara_cohort');
        }

        return '';
    }
}

// End of rb_source_user class
