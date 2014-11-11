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
 * @author Jon Sharp <jonathans@catalyst-eu.net>
 * @package totara
 * @subpackage certification
 */

require_once("{$CFG->libdir}/formslib.php");

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

// Define a form class to edit the program messages.
class edit_certification_form extends moodleform {

    public function definition() {
        global $CFG, $USER;

        $mform =& $this->_form;

        $timeallowance = $this->_customdata['timeallowance'];

        $certification = $this->_customdata['certification'];
        if (empty($certification->activeperiod)) {
            $active = array('', 'day');
        } else {
            $active = explode(' ', $certification->activeperiod);
        }

        if (empty($certification->windowperiod)) {
            $window = array('', 'day');
        } else {
            $window = explode(' ', $certification->windowperiod);
        }

        if (empty($certification->recertifydatetype)) {
            $recertifydatetype = CERTIFRECERT_EXPIRY;
        } else {
            $recertifydatetype = $certification->recertifydatetype;
        }

        $mform->addElement('header', 'editdetailshdr', get_string('editdetailshdr', 'totara_certification'));
        $mform->addElement('html', html_writer::start_tag('p', array('class' => 'instructions')) .
                             get_string('editdetailsdesc', 'totara_certification') . html_writer::end_tag('p'));

        // Active period num.
        $mform->addElement('html', html_writer::start_tag('p', array('class' => 'subheader')) .
                             get_string('editdetailsactivep', 'totara_certification') . html_writer::end_tag('p'));
        $mform->addElement('html', html_writer::start_tag('p', array('class' => 'instructions')) .
                             get_string('editdetailsvalid', 'totara_certification') . html_writer::end_tag('p'));
        $activegrp = array();
        $activegrp[] =  $mform->createElement('text', 'activenum', '', array('size' => 4, 'maxlength' => 3));
        $mform->setType('activenum', PARAM_INT);
        $mform->setdefault('activenum', $active[0]);

        // Active period timeselect.
        $dateperiodoptions = array(
            'day' => get_string('days', 'totara_certification'),
            'week' => get_string('weeks', 'totara_certification'),
            'month' => get_string('months', 'totara_certification'),
            'year' => get_string('years', 'totara_certification'),
        );
        $activegrp[] = $mform->createElement('select', 'activeperiod', '', $dateperiodoptions);
        $mform->setDefault('activeperiod', $active[1]);
        $mform->addGroup($activegrp, 'activegrp', get_string('editdetailsactive', 'totara_certification'), ' ', false);
        $mform->addHelpButton('activegrp', 'editdetailsactive', 'totara_certification');

        $mform->registerRule('activeperiod_validation', 'function', 'activeperiod_validation');
        $mform->addRule('activegrp',
                get_string('error:minimumactiveperiod', 'totara_certification'),
                'activeperiod_validation',
                $mform);

        // Recert window period num.
        $mform->addElement('html', html_writer::start_tag('p', array('class' => 'subheader')) .
                             get_string('editdetailsrcwin', 'totara_certification') . html_writer::end_tag('p'));
        $windowgrp = array();
        $windowgrp[] = $mform->createElement('text', 'windownum', '', array('size' => 4, 'maxlength' => 3));
        $mform->setType('windownum', PARAM_INT);
        $mform->setDefault('windownum', $window[0]);

        // Recert window period timeselect.
        $windowgrp[] = $mform->createElement('select', 'windowperiod', '', $dateperiodoptions);
        $mform->setDefault('windowperiod', $window[1]);
        $mform->addGroup($windowgrp, 'windowgrp', get_string('editdetailswindow', 'totara_certification'), ' ', false);
        $mform->addHelpButton('windowgrp', 'editdetailswindow', 'totara_certification');

        $mform->registerRule('windowperiod_validation', 'function', 'windowperiod_validation');
        $mform->addRule('windowgrp',
                get_string('error:minimumwindowperiod', 'totara_certification', $timeallowance->timestring),
                'windowperiod_validation',
                $timeallowance->seconds);

        if ($timeallowance->seconds > 0) {
            $mform->addElement('html', html_writer::tag('p',
                    get_string('timeallowance', 'totara_certification', $timeallowance),
                    array('class' => 'timeallowance')));
        }

        // Recert datetype.
        $recertoptions = array(
            CERTIFRECERT_COMPLETION => get_string('editdetailsrccmpl', 'totara_certification'),
            CERTIFRECERT_EXPIRY => get_string('editdetailsrcexp', 'totara_certification')
        );
        $mform->addElement('select', 'recertifydatetype', get_string('editdetailsrcopt', 'totara_certification'), $recertoptions);
        $mform->setDefault('recertifydatetype', $recertifydatetype);
        $mform->addHelpButton('recertifydatetype', 'editdetailsrcopt', 'totara_certification');

        // Buttons.
        $buttonarray = array();
        $buttonarray[] = $mform->createElement('submit', 'savechanges', get_string('savechanges'), 'class="certification-add"');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }


    /**
     * Carries out validation of submitted form values
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        foreach ($data as $elementname => $elementvalue) {
            // Check for negative integer issues.
            if ($elementname == 'activenum' || $elementname == 'windownum') {
                if ($elementvalue < 0) {
                    $errors[$elementname] = get_string('error:mustbepositive', 'totara_certification');
                }
            }
        }
        return $errors;
    }

}

/**
 * Validates that the window period is greater than or equal to the required time for recertification
 *
 * @param string $element Element name
 * @param array $value Value of windowgrp
 * @param int $timeallowance time allowance in seconds
 * @return boolean
 */
function windowperiod_validation($element, $value, $timeallowance) {
    $timewindowperiod = strtotime($value['windownum'] . ' ' . $value['windowperiod'], 0);
    return ($timewindowperiod && ($timewindowperiod >= $timeallowance));
}

/**
 * Validates that the active period is greater than or equal to the recertification window period
 *
 * @param string $element Element name
 * @param array $value Value of windowgrp
 * @param object $mform
 * @return boolean
 */
function activeperiod_validation($element, $value, $mform) {
    $timeactiveperiod = strtotime($value['activenum'] . ' ' . $value['activeperiod'], 0);
    $windowgrp = $mform->getElementValue('windowgrp');
    $timewindowperiod = strtotime($windowgrp['windownum'] . ' ' . $windowgrp['windowperiod'][0], 0);
    return ($timewindowperiod && $timeactiveperiod && ($timeactiveperiod >= $timewindowperiod));
}
