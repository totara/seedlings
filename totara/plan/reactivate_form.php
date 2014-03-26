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
 * @subpackage plan
 */

require_once("{$CFG->libdir}/formslib.php");
require_once("{$CFG->dirroot}/totara/plan/development_plan.class.php");

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

class plan_reactivate_form extends moodleform {
    function definition() {
        global $CFG, $USER, $DB;

        $mform =& $this->_form;

        if (isset($this->_customdata['plan'])) {
            $plan = $this->_customdata['plan'];
        }

        $planid = $this->_customdata['id'];
        $plan = new development_plan($planid);

        $mform->addElement('hidden', 'id', $planid);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'reactivate', true);
        $mform->setType('reactivate', PARAM_BOOL);
        $mform->addElement('hidden', 'sesskey', sesskey());
        $mform->setType('sesskey', PARAM_ALPHA);
        $mform->addElement('hidden', 'confirm', true);
        $mform->setType('confim', PARAM_BOOL);
        $mform->addElement('hidden', 'referer', $this->_customdata['referer']);
        $mform->setType('referer', PARAM_LOCALURL);


        $sql = "SELECT * FROM {dp_plan_history} WHERE planid = ? ORDER BY timemodified DESC";
        $history = $DB->get_records_sql($sql, array($planid), 0, 1);
        $history = array_shift($history);

        $mform->addElement('static', 'reactivatecheck', null, get_string('checkplanreactivate', 'totara_plan', $plan->name));

        if ($history->reason == DP_PLAN_REASON_AUTO_COMPLETE_DATE) {
            $mform->addElement('hidden', 'validate_date', true);

            $mform->addElement('static', 'instructions', null, 'This plan was completed because the end date elapsed, please enter a new end date.');

            $mform->addElement('text', 'enddate', get_string('completiondate', 'totara_plan'));
            $mform->setType('enddate', PARAM_TEXT);
            $mform->addRule('enddate', get_string('err_required', 'form'), 'required', '', 'client', false, false);
            $mform->setDefault('enddate', userdate(time(), get_string('datepickerlongyearphpuserdate', 'totara_core'), $CFG->timezone, false));
        }

        $this->add_action_buttons(true, get_string('reactivate', 'totara_plan'));
    }

    /**
     * Carries out validation of submitted form values
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    function validation($data, $files) {
        $mform =& $this->_form;

        $result = array();
        if (!empty($data['validate_date'])) {
            // Validate date
            $datenow = time();
            $enddate = isset($data['enddate']) ? $data['enddate'] : '';

            $datepattern = get_string('datepickerlongyearregexphp', 'totara_core');
            if (preg_match($datepattern, $enddate, $matches) == 0) {
                $errstr = get_string('error:dateformat', 'totara_plan', get_string('datepickerlongyearplaceholder', 'totara_core'));
                $result['enddate'] = $errstr;
                unset($errstr);
            } else if ($datenow > totara_date_parse_from_format(get_string('datepickerlongyearparseformat', 'totara_core'), $enddate) && $enddate !== false) {
                // Enforce start date before finish date
                $errstr = get_string('error:reactivatedatebeforenow', 'totara_plan');
                $result['enddate'] = $errstr;
                unset($errstr);
            }
        }

        return $result;
    }
}
