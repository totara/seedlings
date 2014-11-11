<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010 onwards Totara Learning Solutions LTD
 * Copyright (C) 1999 onwards Martin Dougiamas
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
 * @package totara
 * @subpackage reportbuilder
 */

defined('MOODLE_INTERNAL') || die();

class rb_source_org extends rb_base_source {
    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns, $sourcetitle;

    function __construct() {
        $this->base = '{org}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->requiredcolumns = $this->define_requiredcolumns();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_org');

        parent::__construct();
    }

    //
    //
    // Methods for defining contents of source
    //
    //

    protected function define_joinlist() {

        $joinlist = array(
            new rb_join(
                'framework',
                'INNER',
                '{org_framework}',
                'base.frameworkid = framework.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'parent',
                'LEFT',
                '{org}',
                'base.parentid = parent.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'comps',
                'LEFT',
                '(SELECT oc.organisationid, ' .
                sql_group_concat(sql_cast2char('c.fullname'), '<br>', true) .
                " AS list FROM {org_competencies} oc LEFT JOIN {comp} c ON oc.competencyid = c.id GROUP BY oc.organisationid)",
                'comps.organisationid = base.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'orgtype',
                'LEFT',
                '{org_type}',
                'base.typeid = orgtype.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            // This join is required to keep the joining of org custom fields happy :D
            new rb_join(
                'organisation',
                'INNER',
                '{org}',
                'base.id = organisation.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
        );

        return $joinlist;
    }

    protected function define_columnoptions() {
        $columnoptions = array(
        new rb_column_option(
                'org',
                'idnumber',
                get_string('idnumber', 'rb_source_org'),
                "base.idnumber",
                array('dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'org',
                'fullname',
                get_string('name', 'rb_source_org'),
                "base.fullname",
                array('displayfunc' => 'orgnamelink',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'org',
                'shortname',
                get_string('shortname', 'rb_source_org'),
                "base.shortname",
                array('dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'org',
                'description',
                get_string('description', 'rb_source_org'),
                "base.description",
                array('dbdatatype' => 'text',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'org',
                'orgtypeid',
                get_string('type', 'rb_source_org'),
                'orgtype.id',
                array(
                    'joins' => 'orgtype',
                    'hidden' => true,
                    'selectable' => false
                )
            ),
            new rb_column_option(
                'org',
                'orgtype',
                get_string('type', 'rb_source_org'),
                'orgtype.fullname',
                array('joins' => 'orgtype',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'org',
                'framework',
                get_string('framework', 'rb_source_org'),
                "framework.fullname",
                array('joins' => 'framework',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'org',
                'visible',
                get_string('visible', 'rb_source_org'),
                'base.visible',
                array('displayfunc' => 'yes_no')
            ),
            new rb_column_option(
                'org',
                'parentidnumber',
                get_string('parentidnumber', 'rb_source_org'),
                'parent.idnumber',
                array('joins' => 'parent',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'org',
                'parentfullname',
                get_string('parentfullname', 'rb_source_org'),
                'parent.fullname',
                array('joins' => 'parent',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'org',
                'comps',
                get_string('competencies', 'rb_source_org'),
                'comps.list',
                array('joins' => 'comps',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'org',
                'timecreated',
                get_string('timecreated', 'rb_source_org'),
                'base.timecreated',
                array('displayfunc' => 'nice_date', 'dbdatatype' => 'timestamp')
            ),
            new rb_column_option(
                'org',
                'timemodified',
                get_string('timemodified', 'rb_source_org'),
                'base.timemodified',
                array('displayfunc' => 'nice_date', 'dbdatatype' => 'timestamp')
            ),
        );

        return $columnoptions;
    }

    protected function define_filteroptions() {
        $filteroptions = array(
            new rb_filter_option(
                'org',              // type
                'idnumber',         // value
                get_string('idnumber', 'rb_source_org'), // label
                'text'              // filtertype
            ),
            new rb_filter_option(
                'org',              // type
                'fullname',         // value
                get_string('name', 'rb_source_org'), // label
                'text'              // filtertype
            ),
            new rb_filter_option(
                'org',              // type
                'shortname',        // value
                get_string('shortname', 'rb_source_org'), // label
                'text'              // filtertype
            ),
            new rb_filter_option(
                'org',              // type
                'description',      // value
                get_string('description', 'rb_source_org'), // label
                'text'              // filtertype
            ),
            new rb_filter_option(
                'org',              // type
                'parentidnumber',   // value
                get_string('parentidnumber', 'rb_source_org'), // label
                'text'              // filtertype
            ),
            new rb_filter_option(
                'org',              // type
                'parentfullname',   // value
                get_string('parentfullname', 'rb_source_org'), // label
                'text'              // filtertype
            ),
            new rb_filter_option(
                'org',              // type
                'timecreated',      // value
                get_string('timecreated', 'rb_source_org'), // label
                'date'              // filtertype
            ),
            new rb_filter_option(
                'org',              // type
                'timemodified',     // value
                get_string('timemodified', 'rb_source_org'), // label
                'date'              // filtertype
            ),
            new rb_filter_option(
                'org',              // type
                'orgtypeid',        // value
                get_string('type', 'rb_source_org'), // label
                'select',           // filtertype
                array(
                    'selectfunc' => 'orgtypes',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
            ),
            new rb_filter_option(
                'org',              // type
                'visible',          // value
                get_string('visible', 'rb_source_org'), // label
                'select',           // filtertype
                array(
                    'selectfunc' => 'org_yesno',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
            ),
        );

        return $filteroptions;
    }

    protected function define_contentoptions() {
        $contentoptions = array();

        return $contentoptions;
    }

    protected function define_paramoptions() {
        $paramoptions = array();

        return $paramoptions;
    }

    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'org',
                'value' => 'idnumber',
            ),
            array(
                'type' => 'org',
                'value' => 'fullname',
            ),
            array(
                'type' => 'org',
                'value' => 'framework',
            ),
            array(
                'type' => 'org',
                'value' => 'parentidnumber',
            ),
            array(
                'type' => 'org',
                'value' => 'comps',
            ),
        );
        return $defaultcolumns;
    }

    protected function define_defaultfilters() {
        $defaultfilters = array(
            array(
                'type' => 'org',
                'value' => 'fullname',
                'advanced' => 0,
            ),
            array(
                'type' => 'org',
                'value' => 'idnumber',
                'advanced' => 0,
            ),
            array(
                'type' => 'org',
                'value' => 'parentidnumber',
                'advanced' => 0,
            ),
        );

        return $defaultfilters;
    }

    protected function define_requiredcolumns() {
        $requiredcolumns = array(
            /*
            // array of rb_column objects, e.g:
            new rb_column(
                '',         // type
                '',         // value
                '',         // heading
                '',         // field
                array()     // options
            )
            */
        );
        return $requiredcolumns;
    }


    //
    //
    // Source specific column display methods
    //
    //
    function rb_display_orgnamelink($orgname, $row) {
        global $CFG;

        return html_writer::link("{$CFG->wwwroot}/totara/hierarchy/item/view.php?prefix=organisation&id={$row->id}", $orgname);
    }


    //
    //
    // Source specific filter display methods
    //
    //
    function rb_filter_org_yesno() {
        return array(
            1 => get_string('yes'),
            0 => get_string('no')
        );
    }

    function rb_filter_orgtypes() {
        global $DB;

        $types = $DB->get_records('org_type', null, 'fullname', 'id, fullname');
        $list = array();
        foreach ($types as $type) {
            $list[$type->id] = $type->fullname;
        }
        return $list;
    }

} // end of rb_source_org class
