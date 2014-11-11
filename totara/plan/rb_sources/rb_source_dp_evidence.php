<?php
/*
 *
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
 * @author Simon Coggins <simonc@catalyst.net.nz>
 * @author Russell England <russell.england@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/totara/plan/lib.php');
require_once($CFG->dirroot . '/totara/plan/record/evidence/lib.php');

/**
 * A report builder source for DP Evidence
 */
class rb_source_dp_evidence extends rb_base_source {

    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns, $sourcetitle;

    private $dp_plans = array();

    /**
     * Constructor
     * @global object $CFG
     */
    public function __construct() {
        $sql="
            (SELECT
                e.id,
                e.name,
                e.description,
                e.userid,
                e.evidencelink,
                e.institution,
                e.datecompleted,
                e.readonly,
                CASE
                    WHEN e.datecompleted > 0 THEN 'completed'
                    ELSE 'active'
                END AS rolstatus,
                e.evidencetypeid,
                et.name AS evidencetypename,
                CASE
                    WHEN linkedevidence.count IS NULL THEN 0
                    ELSE linkedevidence.count
                END AS evidenceinuse
            FROM {dp_plan_evidence} e
            LEFT JOIN {dp_evidence_type} et ON et.id = e.evidencetypeid
            LEFT JOIN
                (SELECT er.evidenceid,
                        COUNT(*) AS count
                FROM {dp_plan_evidence_relation} er
                GROUP BY er.evidenceid) linkedevidence ON linkedevidence.evidenceid = e.id)";

        $this->base = $sql;
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = array();
        $this->requiredcolumns = array();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_dp_evidence');
        parent::__construct();
    }

    /**
     * Creates the array of rb_join objects required for this->joinlist
     *
     * @global object $CFG
     * @return array
     */
    private function define_joinlist() {
        global $CFG;

        $joinlist = array();

        // to get access to position type constants
        require_once($CFG->dirroot . '/totara/reportbuilder/classes/rb_join.php');

        $this->add_user_table_to_joinlist($joinlist, 'base', 'userid');
        $this->add_position_tables_to_joinlist($joinlist, 'base', 'userid');
        $this->add_manager_tables_to_joinlist($joinlist, 'position_assignment', 'reportstoid');
        $this->add_cohort_user_tables_to_joinlist($joinlist, 'base', 'userid');

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
                'evidence',
                'name',
                get_string('name', 'rb_source_dp_evidence'),
                'base.name',
                array(
                    'dbdatatype' => 'char',
                    'outputformat' => 'text')
        );

        $columnoptions[] = new rb_column_option(
                'evidence',
                'namelink',
                get_string('namelink', 'rb_source_dp_evidence'),
                'base.name',
                array(
                    'defaultheading' => get_string('name'),
                    'displayfunc' => 'evidenceview',
                    'extrafields' => array(
                        'evidence_id' => 'base.id',
                    ),
                )
        );

        $columnoptions[] = new rb_column_option(
                'evidence',
                'description',
                get_string('description'),
                'base.description',
                array(
                    'displayfunc' => 'description',
                    'nosort' => true,
                    'dbdatatype' => 'text',
                    'outputformat' => 'text',
                    'extrafields' => array(
                        'evidence_id' => 'base.id',
                    ),
                )
        );

        $columnoptions[] = new rb_column_option(
                'evidence',
                'attachmentlink',
                get_string('attachment', 'rb_source_dp_evidence'),
                'base.id',
                array(
                    'displayfunc' => 'attachmentlink',
                    'extrafields' => array(
                        'userid' => 'base.userid',
                        'evidenceid' => 'base.id',
                    ),
                    'nosort' => true,
                )
        );

        $columnoptions[] = new rb_column_option(
            'evidence',
            'evidencelink',
            get_string('evidencelink', 'rb_source_dp_evidence'),
            'base.evidencelink',
            array(
                'displayfunc' => 'evidencelink',
            )
        );

        $columnoptions[] = new rb_column_option(
            'evidence',
            'institution',
            get_string('institution', 'rb_source_dp_evidence'),
            'base.institution',
            array('dbdatatype' => 'char',
                  'outputformat' => 'text')
        );

        $columnoptions[] = new rb_column_option(
            'evidence',
            'datecompleted',
            get_string('datecompleted', 'rb_source_dp_evidence'),
            'base.datecompleted',
            array('displayfunc' => 'nice_date', 'dbdatatype' => 'timestamp')
        );

        $columnoptions[] = new rb_column_option(
            'evidence',
            'evidencetypeid',
            get_string('evidencetype', 'rb_source_dp_evidence'),
            'base.evidencetypeid',
            array(
                'hidden' => true,
                'selectable' => false,
            )
        );

        $columnoptions[] = new rb_column_option(
            'evidence',
            'evidencetypename',
            get_string('evidencetype', 'rb_source_dp_evidence'),
            'base.evidencetypename',
            array('dbdatatype' => 'char',
                  'outputformat' => 'text')
        );

        $columnoptions[] = new rb_column_option(
            'evidence',
            'evidenceinuse',
            get_string('evidenceinuse', 'rb_source_dp_evidence'),
            'base.evidenceinuse',
            array('displayfunc' => 'evidenceinuse')
        );

        $columnoptions[] = new rb_column_option(
            'evidence',
            'actionlinks',
            get_string('actionlinks', 'rb_source_dp_evidence'),
            'base.id',
            array(
                'displayfunc' => 'actionlinks',
                'extrafields' => array(
                    'userid' => 'base.userid',
                    'readonly' => 'base.readonly',
                'noexport' => true,
                'nosort' => true),
            )
        );

        $this->add_user_fields_to_columns($columnoptions);
        $this->add_position_fields_to_columns($columnoptions);
        $this->add_manager_fields_to_columns($columnoptions);
        $this->add_cohort_user_fields_to_columns($columnoptions);

        return $columnoptions;
    }

    /**
     * Creates the array of rb_filter_option objects required for $this->filteroptions
     * @return array
     */
    protected function define_filteroptions() {
        $filteroptions = array();

        $filteroptions[] = new rb_filter_option(
                'evidence',
                'name',
                get_string('evidencename', 'rb_source_dp_evidence'),
                'text'
        );

        $filteroptions[] = new rb_filter_option(
                'evidence',
                'description',
                get_string('evidencedescription', 'rb_source_dp_evidence'),
                'textarea'
        );

        $filteroptions[] = new rb_filter_option(
                'evidence',
                'evidencetypeid',
                get_string('evidencetype', 'rb_source_dp_evidence'),
                'select',
                array(
                    'selectfunc' => 'evidencetypes',
                )
        );

        $this->add_user_fields_to_filters($filteroptions);
        $this->add_position_fields_to_filters($filteroptions);
        $this->add_manager_fields_to_filters($filteroptions);
        $this->add_cohort_user_fields_to_filters($filteroptions);

        return $filteroptions;
    }

    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'user',
                'value' => 'fullname',
            ),
            array(
                'type' => 'evidence',
                'value' => 'namelink',
            ),
            array(
                'type' => 'evidence',
                'value' => 'description',
            ),
            array(
                'type' => 'evidence',
                'value' => 'datecompleted',
            ),
        );
        return $defaultcolumns;
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

        // Include the rb_user_content content options for this report
        $contentoptions[] = new rb_content_option(
            'user',
            get_string('users'),
            array(
                'userid' => 'base.userid',
                'managerid' => 'position_assignment.managerid',
                'managerpath' => 'position_assignment.managerpath',
                'postype' => 'position_assignment.type',
            ),
            'position_assignment'
        );
        return $contentoptions;
    }

    protected function define_paramoptions() {
        global $CFG;
        require_once($CFG->dirroot . '/totara/plan/lib.php');
        $paramoptions = array();

        $paramoptions[] = new rb_param_option(
                'userid',
                'base.userid',
                'base'
        );
        $paramoptions[] = new rb_param_option(
                'rolstatus',
                'base.rolstatus'
        );

        return $paramoptions;
    }

    /**
     * Generate the evidence name with a link to the evidence details page
     * @global object $CFG
     * @param string $evidence evidence name
     * @param object $row Object containing other fields
     * @return string
     */
    public function rb_display_evidenceview($evidence, $row) {
        $url = new moodle_url('/totara/plan/record/evidence/view.php', array('id' => $row->evidence_id ));
        return html_writer::link($url, $evidence);
    }

    public function rb_display_evidencelink($evidencelink, $row) {
        global $OUTPUT;
        return $OUTPUT->action_link(new moodle_url($evidencelink), $evidencelink);
    }

    public function rb_display_attachmentlink($attachment, $row) {
        return evidence_display_attachment($row->userid, $row->evidenceid);
    }

    public function rb_display_actionlinks($evidenceid, $row) {
        global $USER, $OUTPUT;

        $out = '';

        // Check user's permissions to edit this item
        $usercontext = context_user::instance($row->userid);
        $canaccess = has_capability('totara/plan:accessanyplan', $usercontext);
        $canedit = has_capability('totara/plan:editsiteevidence', $usercontext);
        if ($row->readonly && !($canaccess || $canedit)) {
            $out .= get_string('evidence_readonly', 'totara_plan');
        } else if ($USER->id == $row->userid ||
                totara_is_manager($row->userid) ||
                $canaccess || $canedit) {

            $out .= $OUTPUT->action_icon(
                        new moodle_url('/totara/plan/record/evidence/edit.php',
                                array('id' => $evidenceid, 'userid' => $row->userid)),
                        new pix_icon('t/edit', get_string('edit')));

            $out .= $OUTPUT->spacer(array('width' => 11, 'height' => 11, 'class' => 'iconsmall action-icon'));

            $out .= $OUTPUT->action_icon(
                        new moodle_url('/totara/plan/record/evidence/edit.php',
                                array('id' => $evidenceid, 'userid' => $row->userid, 'd' => '1')),
                        new pix_icon('t/delete', get_string('delete')));
        }

        return $out;
    }

    public function rb_display_evidenceinuse($evidenceinuse, $row) {
        return (empty($evidenceinuse)) ? get_string('no') : get_string('yes');
    }

    public function rb_display_description($description, $row) {
        $description = file_rewrite_pluginfile_urls($description, 'pluginfile.php',
                context_system::instance()->id, 'totara_plan', 'dp_plan_evidence', $row->evidence_id );
        return(format_text($description, FORMAT_HTML));
    }

    public function rb_filter_evidencetypes() {
        global $DB;

        $types = $DB->get_records('dp_evidence_type', null, 'sortorder', 'id, name');
        $list = array();
        foreach ($types as $type) {
            $list[$type->id] = $type->name;
        }
        return $list;
    }
}
