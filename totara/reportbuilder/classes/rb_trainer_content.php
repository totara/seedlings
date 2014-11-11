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

/**
 * Restrict content by a particular trainer or group of trainers
 * Pass in an integer that represents a trainer's moodle id
 */
class rb_trainer_content extends rb_base_content {
    /**
     * Generate the SQL to apply this content restriction
     *
     * @param string $field SQL field to apply the restriction against
     * @param integer $reportid ID of the report
     *
     * @return array containing SQL snippet to be used in a WHERE clause, as well as array of SQL params
     */
    function sql_restriction($field, $reportid) {

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);
        $userid = $this->reportfor;

        $uniqueparam = rb_unique_param('ctr');
        $who = isset($settings['who']) ? $settings['who'] : null;
        if ($who == 'own') {
            // show own records
            return array("{$field} = :{$uniqueparam}", array($uniqueparam => $userid));
        } else if ($who == 'reports') {
            // show staff records
            if ($staff = totara_get_staff()) {
                list($isql, $iparams) = $DB->get_in_or_equal($staff, SQL_PARAMS_NAMED, $uniqueparam.'_');
                return array("{$field} {$isql}", $iparams);
            } else {
                // using 1=0 instead of FALSE for MSSQL support
                return array('1=0', array());
            }
        } else if ($who == 'ownandreports') {
            // show own and staff records
            if ($staff = totara_get_staff()) {
                $staff[] = $userid;
                list($isql, $iparams) = $DB->get_in_or_equal($staff, SQL_PARAMS_NAMED, $uniqueparam.'_');
                return array("{$field} {$isql}", $iparams);
            } else {
                return array("{$field} = :{$uniqueparam}", array($uniqueparam => $userid));
            }
        } else {
            // anything unexpected
            // using 1=0 instead of FALSE for MSSQL support
            return array('1=0', array());
        }
    }

    /**
     * Generate a human-readable text string describing the restriction
     *
     * @param string $title Name of the field being restricted
     * @param integer $reportid ID of the report
     *
     * @return string Human readable description of the restriction
     */
    function text_restriction($title, $reportid) {
        global $DB;

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);
        $userid = $this->reportfor;

        $user = $DB->get_record('user', array('id' => $userid));
        switch ($settings['who']) {
        case 'own':
            return $title . ' ' . get_string('is', 'totara_reportbuilder') . ' "' .
                fullname($user) . '"';
        case 'reports':
            return $title . ' ' . get_string('reportsto', 'totara_reportbuilder') . ' "' .
                fullname($user) . '"';
        case 'ownandreports':
            return $title . ' ' . get_string('is', 'totara_reportbuilder') . ' "' .
                fullname($user) . '"' . get_string('or', 'totara_reportbuilder') .
                get_string('reportsto', 'totara_reportbuilder') . ' "' . fullname($user) . '"';
        default:
            return $title . ' ' . get_string('isnotfound', 'totara_reportbuilder');
        }
    }

    /**
     * Adds form elements required for this content restriction's settings page
     *
     * @param object &$mform Moodle form object to modify (passed by reference)
     * @param integer $reportid ID of the report being adjusted
     * @param string $title Name of the field the restriction is acting on
     */
    function form_template(&$mform, $reportid, $title) {

        // get current settings
        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $enable = reportbuilder::get_setting($reportid, $type, 'enable');
        $who = reportbuilder::get_setting($reportid, $type, 'who');

        $mform->addElement('header', 'trainer_header', get_string('showbyx',
            'totara_reportbuilder', lcfirst($title)));
        $mform->setExpanded('trainer_header');
        $mform->addElement('checkbox', 'trainer_enable', '',
            get_string('showbasedonx', 'totara_reportbuilder', lcfirst($title)));
        $mform->disabledIf('trainer_enable', 'contentenabled', 'eq', 0);
        $mform->setDefault('trainer_enable', $enable);
        $radiogroup = array();
        $radiogroup[] =& $mform->createElement('radio', 'trainer_who', '',
            get_string('trainerownrecords', 'totara_reportbuilder'), 'own');
        $radiogroup[] =& $mform->createElement('radio', 'trainer_who', '',
            get_string('trainerstaffrecords', 'totara_reportbuilder'), 'reports');
        $radiogroup[] =& $mform->createElement('radio', 'trainer_who', '',
            get_string('both', 'totara_reportbuilder'), 'ownandreports');
        $mform->addGroup($radiogroup, 'trainer_who_group',
            get_string('includetrainerrecords', 'totara_reportbuilder'), html_writer::empty_tag('br'), false);
        $mform->setDefault('trainer_who', $who);
        $mform->disabledIf('trainer_who_group', 'contentenabled', 'eq', 0);
        $mform->disabledIf('trainer_who_group', 'trainer_enable', 'notchecked');
        $mform->addHelpButton('trainer_header', 'reportbuildertrainer', 'totara_reportbuilder');
    }

    /**
     * Processes the form elements created by {@link form_template()}
     *
     * @param integer $reportid ID of the report to process
     * @param object $fromform Moodle form data received via form submission
     *
     * @return boolean True if form was successfully processed
     */
    function form_process($reportid, $fromform) {
        $status = true;
        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);

        // enable checkbox option
        $enable = (isset($fromform->trainer_enable) &&
            $fromform->trainer_enable) ? 1 : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'enable', $enable);

        // who radio option
        $who = isset($fromform->trainer_who) ?
            $fromform->trainer_who : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'who', $who);

        return $status;
    }

} // end of rb_trainer_content class
