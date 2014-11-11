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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

/**
 * Moodle Formslib templates for scheduled reports settings forms
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/calendar/lib.php');

/**
 * Formslib template for the new report form
 */
class scheduled_reports_new_form extends moodleform {
    function definition() {

        $mform =& $this->_form;
        $id = $this->_customdata['id'];
        $frequency = $this->_customdata['frequency'];
        $schedule = $this->_customdata['schedule'];
        $report = $this->_customdata['report'];
        $savedsearches = $this->_customdata['savedsearches'];
        $exporttofilesystem = $this->_customdata['exporttofilesystem'];

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'reportid', $report->_id);
        $mform->setType('reportid', PARAM_INT);

        // Export type options.
        $exportformatselect = reportbuilder_get_export_options();

        $exporttofilesystemenabled = false;
        if (get_config('reportbuilder', 'exporttofilesystem') == 1) {
            $exporttofilesystemenabled = true;
        }

        $mform->addElement('header', 'general', get_string('scheduledreportsettings', 'totara_reportbuilder'));

        $mform->addElement('static', 'report', get_string('report', 'totara_reportbuilder'), $report->fullname);
        if (empty($savedsearches)) {
            $mform->addElement('static', '', get_string('data', 'totara_reportbuilder'),
                    html_writer::div(get_string('scheduleneedssavedfilters', 'totara_reportbuilder', $report->report_url()),
                            'notifyproblem'));
        } else {
            $mform->addElement('select', 'savedsearchid', get_string('data', 'totara_reportbuilder'), $savedsearches);
        }
        $mform->addElement('select', 'format', get_string('export', 'totara_reportbuilder'), $exportformatselect);

        if ($exporttofilesystemenabled) {
            $exporttosystemarray = array();
            $exporttosystemarray[] =& $mform->createElement('radio', 'emailsaveorboth', '',
                    get_string('exporttoemail', 'totara_reportbuilder'), REPORT_BUILDER_EXPORT_EMAIL);
            $exporttosystemarray[] =& $mform->createElement('radio', 'emailsaveorboth', '',
                    get_string('exporttoemailandsave', 'totara_reportbuilder'), REPORT_BUILDER_EXPORT_EMAIL_AND_SAVE);
            $exporttosystemarray[] =& $mform->createElement('radio', 'emailsaveorboth', '',
                    get_string('exporttosave', 'totara_reportbuilder'), REPORT_BUILDER_EXPORT_SAVE);
            $mform->setDefault('emailsaveorboth', $exporttofilesystem);
            $mform->addGroup($exporttosystemarray, 'exporttosystemarray',
                    get_string('exportfilesystemoptions', 'totara_reportbuilder'), array('<br />'), false);
        } else {
            $mform->addElement('hidden', 'emailsaveorboth', REPORT_BUILDER_EXPORT_EMAIL);
            $mform->setType('emailsaveorboth', PARAM_TEXT);
        }

        $mform->addElement('scheduler', 'schedulegroup', get_string('schedule', 'totara_reportbuilder'),
                           array('frequency' => $frequency, 'schedule' => $schedule));
        if (!empty($savedsearches)) {
            $this->add_action_buttons();
        }
    }
}


class scheduled_reports_add_form extends moodleform {
    function definition() {

        $mform =& $this->_form;

        //Report type options
        $reports = reportbuilder_get_reports();
        $reportselect = array();
        foreach ($reports as $report) {
            $reportobject = new reportbuilder($report->id);
            if ($reportobject->src->scheduleable) {
                $reportselect[$report->id] = $report->fullname;
            }
        }

        if (!empty($reportselect)) {
            $mform->addElement('select', 'reportid', get_string('addnewscheduled', 'totara_reportbuilder'), $reportselect);
            $mform->addElement('submit', 'submitbutton', get_string('addscheduledreport', 'totara_reportbuilder'));

            $renderer =& $mform->defaultRenderer();
            $elementtemplate = '<span>{element}</span>';
            $renderer->setElementTemplate($elementtemplate, 'submitbutton');
            $renderer->setElementTemplate('<label for="{id}" class="accesshide">{label}</label><span>{element}</span>', 'reportid');
        }
    }
}
