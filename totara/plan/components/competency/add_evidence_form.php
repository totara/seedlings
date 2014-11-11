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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage plan
 */

require_once "$CFG->dirroot/lib/formslib.php";

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

class totara_competency_evidence_form extends moodleform {

    function definition() {
        global $CFG, $DB, $OUTPUT;

        $mform =& $this->_form;

        $competencyid = isset($this->_customdata['competencyid']) ? $this->_customdata['competencyid'] : 0;
        $positionid = isset($this->_customdata['positionid']) ? $this->_customdata['positionid'] : 0;
        $organisationid = isset($this->_customdata['organisationid']) ? $this->_customdata['organisationid'] : 0;
        $returnurl = isset($this->_customdata['returnurl']) ? $this->_customdata['returnurl'] : '';
        $nojs = $this->_customdata['nojs'];
        $id = $this->_customdata['id'];
        $evidenceid = $this->_customdata['evidenceid'];
        $editing = !empty($evidenceid) ? true : false;

        if ($editing) {
            // Get the evidence record
            $cr = $DB->get_record('comp_record', array('id' => $evidenceid));

            // get id and userid from competency evidence object
            $userid = $cr->userid;

            // Get position title
            $position_title = '';
            if ($cr && $cr->positionid) {
                $position_title = $DB->get_field('pos', 'fullname', array('id' => $cr->positionid));
            }
            // Get organisation title
            $organisation_title = '';
            if ($cr && $cr->organisationid) {
                $organisation_title = $DB->get_field('org', 'fullname', array('id' => $cr->organisationid));
            }

            $competency_title = ($competencyid != 0) ?
                $DB->get_field('comp', 'fullname', array('id' => $competencyid)) : '';

        } else {
            // for new record, userid must also be passed to form
            $userid = $this->_customdata['userid'];
            $id = $this->_customdata['id'];
            $position_assignment = new position_assignment(
                array(
                    'userid'    => $userid,
                    'type'      => POSITION_TYPE_PRIMARY
                )
            );

            // repopulate if set but validation failed
            if (!empty($positionid)) {
                $position_title = $DB->get_field('pos', 'fullname', array('id' => $positionid));
            } else {
                $position_title = !empty($position_assignment->fullname) ? $position_assignment->fullname : '';
            }
            if (!empty($organisationid)) {
                $organisation_title = $DB->get_field('org', 'fullname', array('id' => $organisationid));
            } else {
                $organisation_title = $DB->get_field('org', 'fullname', array('id' => $position_assignment->organisationid));
            }
            $competency_title = ($competencyid != 0) ?
                $DB->get_field('comp', 'fullname', array('id' => $competencyid)) : '';
        }

        $mform->addElement('hidden', 'evidenceid', $evidenceid);
        $mform->setType('evidenceid', PARAM_INT);

        if (!$nojs && $competencyid == 0) {
            // replace previous return url with a new url
            // submitting the form won't return the user to
            // the record of learning page if JS is ofe
            $murl = new moodle_url(qualified_me());
            $link = new action_link($murl->out(false, array('nojs' => 1)), get_string('clickfornonjsform', 'competency'));
            $mform->addElement('html', html_writer::tag('noscript', htmlwriter::tag('p', get_string('requiresjs', 'totara_core', 'form') . $link)));
        }

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('static', 'user', get_string('participant', 'totara_core'));
        $mform->addHelpButton('user', 'competencyevidenceuser', 'totara_hierarchy');
        $mform->addElement('hidden', 'userid', $userid);
        $mform->setType('userid', PARAM_INT);
        $mform->addRule('userid', null, 'required');
        $mform->addRule('userid', null, 'numeric');
        $mform->setType('userid', PARAM_INT);
        $mform->addElement('hidden', 'id', $id);
        $mform->addElement('hidden', 'evidenceid', $evidenceid);
        $mform->addElement('hidden', 'returnurl', $returnurl);
        $mform->setType('returnurl', PARAM_LOCALURL);


        if ($editing) {
            $mform->addElement('hidden', 'competencyid', $cr->competencyid);
            $mform->setType('competencyid', PARAM_INT);
            $mform->addElement('static', 'compname', get_string('competency', 'totara_hierarchy'), html_writer::tag('span', format_string($competency_title), array('id' => "competencytitle1")));
            $mform->addHelpButton('compname', 'competencyevidencecompetency', 'totara_hierarchy');
        } else {
            if ($nojs) {
                $mform->addElement('static','assigncompetency',get_string('assigncompetency', 'totara_hierarchy'), $OUTPUT->container(format_string($competency_title).
                    new action_link(new moodle_url('/totara/hierarchy/prefix/competency/assign/find.php',
                    array('nojs' => '1', 's' => sesskey(), 'returnurl' => $newreturn, 'userid' => $userid)), get_string('assigncompetency', 'totara_hierarchy')), null, "competencytitle"));
                $mform->addElement('hidden', 'competencyid');
                $mform->setType('competencyid', PARAM_INT);
                $mform->setDefault('competencyid', $competencyid);
            } else {
                // competency selector
                $mform->addElement('static', 'competencyselector', get_string('competency', 'totara_hierarchy'), html_writer::tag('span', format_string($competency_title), array('id' => "competencytitle")));
                $mform->addElement('hidden', 'competencyid');
                $mform->setType('competencyid', PARAM_INT);
                $mform->setDefault('competencyid', $competencyid);
                $mform->addHelpButton('competencyselector', 'competencyevidencecompetency', 'totara_hierarchy');
            }

        }

        $mform->addRule('competencyid',null,'required');
        $mform->addRule('competencyid',null,'numeric');

        if ($assessorroleid = $CFG->assessorroleid) {
            $sql = "SELECT DISTINCT u.id, " . $DB->sql_fullname('u.firstname','u.lastname') . " AS name
                FROM {role_assignments} ra
                JOIN {user} u ON ra.userid = u.id
                WHERE roleid = ?
                ORDER BY " . $DB->sql_fullname('u.firstname','u.lastname');
            $params = array($assessorroleid);

            $selectoptions = $DB->get_records_sql_menu($sql, $params);
        } else {
            // no assessor role
            $selectoptions = false;
        }
        if ($selectoptions) {
            $selector = array(0 => get_string('selectanassessor', 'totara_core'));
            $mform->addElement('select', 'assessorid', get_string('assessor', 'totara_core'), $selector + $selectoptions);
            $mform->setType('assessorid', PARAM_INT);
            $mform->addHelpButton('assessorid', 'competencyevidenceassessor', 'totara_hierarchy');
        } else {
            // if assessorid set but no assessor roles defined, this should pass the current value
            $mform->addElement('hidden', 'assessorid','');
            $mform->setType('assessorid', PARAM_INT);
            $mform->addElement('static', 'assessoriderror', get_string('assessor', 'totara_core'), get_string('noassessors', 'totara_core'));
            $mform->addHelpButton('assessoriderror', 'competencyevidenceassessor', 'totara_hierarchy');
        }

        $mform->addElement('text', 'assessorname', get_string('assessorname', 'totara_core'));
        $mform->setType('assessorname', PARAM_TEXT);
        $mform->addHelpButton('assessorname', 'competencyevidenceassessorname', 'totara_hierarchy');
        $mform->addElement('text', 'assessmenttype', get_string('assessmenttype', 'totara_core'));
        $mform->setType('assessmenttype', PARAM_TEXT);
        $mform->addHelpButton('assessmenttype', 'competencyevidenceassessmenttype', 'totara_hierarchy');

        if (!empty($cr) && $cr->proficiency) {
            // editing existing competency evidence item
            // get id of the scale referred to by the evidence's proficiency
            $scaleid = $DB->get_field('comp_scale_values', 'scaleid', array('id' => $cr->proficiency));
            $selectoptions = $DB->get_records_menu('comp_scale_values', array('scaleid' => $scaleid), 'sortorder');
            $mform->addElement('select', 'proficiency', get_string('status', 'totara_plan'), $selectoptions);
        } else if ($competencyid != 0) {
            // competency set but validation failed. Refill scale options
            $sql = "SELECT
                        cs.defaultid as defaultid, cs.id as scaleid
                    FROM {comp} c
                    JOIN {comp_scale_assignments} csa
                        ON c.frameworkid = csa.frameworkid
                    JOIN {comp_scale} cs
                        ON csa.scaleid = cs.id
                    WHERE c.id = ?";
            if (!$scaledetails = $DB->get_record_sql($sql, array($competencyid))) {
                print_error('error:scaledetails', 'competency');
            }
            $defaultid = $scaledetails->defaultid;
            $scaleid = $scaledetails->scaleid;
            $selectoptions = $DB->get_records_menu('comp_scale_values', array('scaleid' => $scaleid), 'sortorder');
            $mform->addElement('select', 'proficiency', get_string('status', 'totara_plan'), $selectoptions);
            $mform->setType('proficiency', PARAM_INT);
            $mform->setDefault('proficiency', $defaultid);

        } else {
            // new competency evidence item
            // create a placeholder element to be filled when competency is selected
            $mform->addElement('select', 'proficiency', get_string('status', 'totara_plan'), array(get_string('firstselectcompetency','totara_hierarchy')));
            $mform->setType('proficiency', PARAM_INT);
            $mform->disabledIf('proficiency','competencyid','eq',0);
        }
        $mform->addHelpButton('proficiency', 'competencyevidencestatus', 'totara_plan');
        $mform->addRule('proficiency',null,'required');
        $mform->addRule('proficiency',get_string('err_required','form'),'nonzero');


        if ($nojs) {
            $allpositions = $DB->get_records_menu('pos', null, 'frameworkid,sortorder', 'id,fullname');
            $mform->addElement('select','positionid', get_string('chooseposition', 'totara_hierarchy'), array(0 => get_string('chooseposition', 'totara_hierarchy')) + $allpositions);
        } else {
            // position selector
            $mform->addElement('static', 'positionselector', get_string('positionatcompletion', 'totara_core'),
                html_writer::tag('span', format_string($position_title), array('id' => 'positiontitle')) .
                $OUTPUT->single_submit(get_string('chooseposition', 'totara_hierarchy'), array('id' => "show-position-dialog", 'type' => 'button'))
                );
            $mform->addHelpButton('positionselector', 'competencyevidenceposition', 'totara_hierarchy');

            $mform->addElement('hidden', 'positionid');
            $mform->setType('positionid', PARAM_INT);
            $mform->addRule('positionid', null, 'numeric');

            // Set default pos to user's current primary position
            $mform->setDefault('positionid', !empty($position_assignment->positionid) ? $position_assignment->positionid : 0);
        }

        if ($nojs) {
            $allorgs = $DB->get_records_menu('org', null, 'frameworkid,sortorder', 'id,fullname');
            $mform->addElement('select','organisationid', get_string('chooseorganisation', 'totara_hierarchy'), array(0 => get_string('chooseorganisation', 'totara_hierarchy')) + $allorgs);
        } else {
            // organisation selector
            $mform->addElement('static', 'organisationselector', get_string('organisationatcompletion', 'totara_core'),
                html_writer::tag('span', format_string($organisation_title), array('id' => "organisationtitle")) .
                $OUTPUT->single_submit(get_string('chooseorganisation', 'totara_hierarchy'), array('id' => "show-organisation-dialog", 'type' => 'button'))
                );
            $mform->addHelpButton('organisationselector', 'competencyevidenceorganisation', 'totara_hierarchy');
            $mform->addElement('hidden', 'organisationid');
            $mform->setType('organisationid', PARAM_INT);
            $mform->setDefault('organisationid', !empty($position_assignment->organisationid) ? $position_assignment->organisationid : 0);
            $mform->addRule('organisationid', null, 'numeric');
        }

        $mform->addElement('date_selector', 'timemodified', get_string('timecompleted', 'totara_core'));
        $mform->setDefault('timemodified', 0);
        $mform->addHelpButton('timemodified', 'competencyevidencetimecompleted', 'totara_hierarchy');

        $this->add_action_buttons();
    }

}
