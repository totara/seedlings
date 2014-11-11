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
 * @author Russell England <russell.england@catalyst-eu.net>
 * @package    totara
 * @subpackage completionimport
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/totara/completionimport/lib.php');

/**
 * A report builder source for Certifications
 */
class rb_source_completionimport_certification extends rb_base_source {

    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns, $sourcetitle;

    /**
     * Constructor
     */
    public function __construct() {
        $this->base = '{totara_compl_import_cert}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = array();
        $this->requiredcolumns = array();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_completionimport_certification');
        parent::__construct();
    }

    //
    //
    // Methods for defining contents of source.
    //
    //

    /**
     * Creates the array of rb_join objects required for this->joinlist
     *
     * @global object $DB
     * @return array
     */
    protected function define_joinlist() {
        global $DB;

        $joinlist = array();

        $joinlist[] = new rb_join(
                'importuser',
                'LEFT',
                '(SELECT id, username, ' . $DB->sql_fullname() . ' AS userfullname FROM {user})',
                'importuser.id = base.importuserid',
                REPORT_BUILDER_RELATION_MANY_TO_ONE,
                array('base')
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
                'base',
                'id',
                get_string('columnbaseid', 'rb_source_completionimport_certification'),
                'base.id'
        );

        $columnoptions[] = new rb_column_option(
                'base',
                'rownumber',
                get_string('columnbaserownumber', 'rb_source_completionimport_certification'),
                'base.rownumber',
                array('dbdatatype' => 'integer')
        );

        $columnoptions[] = new rb_column_option(
                'base',
                'importerrormsg',
                get_string('columnbaseimporterrormsg', 'rb_source_completionimport_certification'),
                'base.importerrormsg',
                array(
                    'displayfunc' => 'importerrormsg',
                )
        );

        $columnoptions[] = new rb_column_option(
                'base',
                'importevidence',
                get_string('columnbaseimportevidence', 'rb_source_completionimport_certification'),
                'base.importevidence',
                array(
                    'displayfunc' => 'yes_no',
                )
        );

        $columnoptions[] = new rb_column_option(
                'importuser',
                'userfullname',
                get_string('columnbaseimportuserfullname', 'rb_source_completionimport_certification'),
                'importuser.userfullname',
                array('joins' => 'importuser',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
        );

        $columnoptions[] = new rb_column_option(
                'importuser',
                'username',
                get_string('columnbaseimportusername', 'rb_source_completionimport_certification'),
                'importuser.username',
                array('joins' => 'importuser',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
        );

        $columnoptions[] = new rb_column_option(
                'base',
                'importuserid',
                get_string('columnbaseimportuserid', 'rb_source_completionimport_course'),
                'base.importuserid'
        );

        $columnoptions[] = new rb_column_option(
                'base',
                'timecreated',
                get_string('columnbasetimecreated', 'rb_source_completionimport_certification'),
                'base.timecreated',
                array(
                    'displayfunc' => 'nice_datetime',
                    'dbdatatype' => 'timestamp'
                )
        );

        $columnoptions[] = new rb_column_option(
                'base',
                'username',
                get_string('columnbaseusername', 'rb_source_completionimport_certification'),
                'base.username',
                array('dbdatatype' => 'char',
                      'outputformat' => 'text')
        );

        $columnoptions[] = new rb_column_option(
                'base',
                'certificationshortname',
                get_string('columnbasecertificationshortname', 'rb_source_completionimport_certification'),
                'base.certificationshortname',
                array('dbdatatype' => 'char',
                      'outputformat' => 'text')
        );

        $columnoptions[] = new rb_column_option(
                'base',
                'certificationidnumber',
                get_string('columnbasecertificationidnumber', 'rb_source_completionimport_certification'),
                'base.certificationidnumber',
                array('dbdatatype' => 'char',
                      'outputformat' => 'text')
        );

        $columnoptions[] = new rb_column_option(
                'base',
                'completiondate',
                get_string('columnbasecompletiondate', 'rb_source_completionimport_certification'),
                'base.completiondate',
                array('dbdatatype' => 'timestamp')
        );

        return $columnoptions;
    }

    /**
     * Creates the array of rb_filter_option objects required for $this->filteroptions
     * @return array
     */
    protected function define_filteroptions() {
        $filteroptions = array();

        $filteroptions[] = new rb_filter_option(
                'base',
                'id',
                get_string('columnbaseid', 'rb_source_completionimport_certification'),
                'int'
        );

        $filteroptions[] = new rb_filter_option(
                'base',
                'rownumber',
                get_string('columnbaserownumber', 'rb_source_completionimport_certification'),
                'int'
        );

        $filteroptions[] = new rb_filter_option(
                'importuser',
                'userfullname',
                 get_string('columnbaseimportuserfullname', 'rb_source_completionimport_certification'),
                'text'
        );

        $filteroptions[] = new rb_filter_option(
                'importuser',
                'username',
                 get_string('columnbaseimportusername', 'rb_source_completionimport_certification'),
                'text'
        );

        $filteroptions[] = new rb_filter_option(
                'base',
                'importuserid',
                get_string('columnbaseimportuserid', 'rb_source_completionimport_certification'),
                'int'
        );

        $filteroptions[] = new rb_filter_option(
                'base',
                'timecreated',
                get_string('columnbasetimecreated', 'rb_source_completionimport_certification'),
                'select',
                array(
                    'selectfunc' => 'timecreated',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
        );

        $filteroptions[] = new rb_filter_option(
                'base',
                'username',
                get_string('columnbaseusername', 'rb_source_completionimport_certification'),
                'text'
        );

        $filteroptions[] = new rb_filter_option(
                'base',
                'certificationshortname',
                get_string('columnbasecertificationshortname', 'rb_source_completionimport_certification'),
                'text'
        );

        $filteroptions[] = new rb_filter_option(
                'base',
                'certificationidnumber',
                get_string('columnbasecertificationidnumber', 'rb_source_completionimport_certification'),
                'text'
        );

        $filteroptions[] = new rb_filter_option(
                'base',
                'completiondate',
                get_string('columnbasecompletiondate', 'rb_source_completionimport_certification'),
                'text'
        );

        return $filteroptions;
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
                'timecreated',
                'base.timecreated'
        );
        $paramoptions[] = new rb_param_option(
                'importuserid',
                'base.importuserid'
        );
        return $paramoptions;
    }

    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'base',
                'value' => 'id',
            ),
            array(
                'type' => 'base',
                'value' => 'rownumber',
            ),
            array(
                'type' => 'base',
                'value' => 'importerrormsg',
            ),
            array(
                'type' => 'base',
                'value' => 'importevidence',
            ),
            array(
                'type' => 'importuser',
                'value' => 'username',
            ),
            array(
                'type' => 'base',
                'value' => 'timecreated',
            ),
            array(
                'type' => 'base',
                'value' => 'username',
            ),
            array(
                'type' => 'base',
                'value' => 'certificationshortname',
            ),
            array(
                'type' => 'base',
                'value' => 'certificationidnumber',
            ),
            array(
                'type' => 'base',
                'value' => 'completiondate',
            ),
        );
        return $defaultcolumns;
    }

    protected function define_defaultfilters() {
        $defaultfilters = array(
            array(
                'type' => 'base',
                'value' => 'id',
                'advanced' => 1,
            ),
            array(
                'type' => 'base',
                'value' => 'rownumber',
                'advanced' => 0,
            ),
            array(
                'type' => 'importuser',
                'value' => 'username',
                'advanced' => 1,
            ),
            array(
                'type' => 'base',
                'value' => 'timecreated',
                'advanced' => 0,
            ),
            array(
                'type' => 'base',
                'value' => 'username',
                'advanced' => 1,
            ),
            array(
                'type' => 'base',
                'value' => 'certificationshortname',
                'advanced' => 1,
            ),
            array(
                'type' => 'base',
                'value' => 'certificationidnumber',
                'advanced' => 1,
            ),
            array(
                'type' => 'base',
                'value' => 'completiondate',
                'advanced' => 1,
            ),
        );
        return $defaultfilters;
    }


    public function rb_display_importerrormsg($importerrormsg, $row) {
        $errors = array();
        $errorcodes = explode(';', $importerrormsg);
        foreach ($errorcodes as $errorcode) {
            if (!empty($errorcode)) {
                $errors[] = get_string($errorcode, 'totara_completionimport');
            }
        }

        return html_writer::alist($errors);
    }

    public function rb_filter_timecreated() {
        global $DB;

        $out = array();
        $sql = "SELECT DISTINCT timecreated
                FROM {totara_compl_import_cert}
                WHERE importerror = :importerror OR importevidence = :importevidence
                ORDER BY timecreated DESC";
        $times = $DB->get_records_sql($sql, array('importerror' => 1, 'importevidence' => 1));
        foreach ($times as $time) {
            $out[$time->timecreated] = userdate($time->timecreated, get_string('strftimedatetimeshort', 'langconfig'));
        }
        return $out;
    }
}
