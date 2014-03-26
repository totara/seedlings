<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Cohort related management functions, this file needs to be included manually.
 *
 * @package    core_cohort
 * @copyright  2010 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

class cohort_edit_form extends moodleform {

    /**
     * Define the cohort edit form
     */
    public function definition() {
        global $CFG, $DB, $COHORT_ALERT;

        $mform = $this->_form;
        $editoroptions = $this->_customdata['editoroptions'];
        $cohort = $this->_customdata['data'];
        $placeholder = get_string('datepickerlongyearplaceholder', 'totara_core');
        $hint = get_string('dateformatlongyearhint', 'totara_cohort', $placeholder);

        $mform->addElement('text', 'name', get_string('name', 'cohort'), 'maxlength="254" size="50"');
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->setType('name', PARAM_TEXT);

        $options = $this->get_category_options($cohort->contextid);
        $mform->addElement('select', 'contextid', get_string('context', 'role'), $options);

        $mform->addElement('text', 'idnumber', get_string('idnumber', 'cohort'), 'maxlength="254" size="50"');
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->setDefault('idnumber', totara_cohort_next_automatic_id());

        if (!$cohort->id) {
            $mform->addElement('select', 'cohorttype', get_string('type', 'totara_cohort'), cohort::getCohortTypes());
            $mform->addHelpButton('cohorttype', 'type', 'totara_cohort');
        }

        $mform->addElement('editor', 'description_editor', get_string('description', 'cohort'), null, $editoroptions);
        $mform->setType('description_editor', PARAM_RAW);

        // startdate
        $group = array();
        $group[] = $mform->createElement('text', 'startdate', '', array('name' => get_string('startdate', 'totara_cohort'),
            'placeholder' => $placeholder));
        $group[] = $mform->createElement('static', 'startdate_hint', '', $hint);
        $mform->addGroup($group, 'startdate_group', get_string('startdate', 'totara_cohort'), array(' '), false);
        $mform->setType('startdate', PARAM_TEXT);
        $mform->setDefault('startdate', get_string('datepickerlongyeardisplayformat', 'totara_core'));
        $mform->addHelpButton('startdate_group', 'startdate', 'totara_cohort');

         // enddate
        $group = array();
        $group[] = $mform->createElement('text', 'enddate', '', array('name' => get_string('enddate', 'totara_cohort'),
            'placeholder' => $placeholder));
        $group[] = $mform->createElement('static', 'enddate_hint', '', $hint);
        $mform->addGroup($group, 'enddate_group', get_string('enddate', 'totara_cohort'), array(' '), false);
        $mform->setType('enddate', PARAM_TEXT);
        $mform->setDefault('enddate', get_string('datepickerlongyeardisplayformat', 'totara_core'));
        $mform->addHelpButton('enddate_group', 'enddate', 'totara_cohort');

        $rule1['startdate'][] = array(get_string('entervaliddate', 'totara_cohort'), 'regex' , get_string('datepickerlongyearregexphp', 'totara_core'));
        $mform->addGroupRule('startdate_group', $rule1);
        $rule2['enddate'][] = array(get_string('entervaliddate', 'totara_cohort'), 'regex' , get_string('datepickerlongyearregexphp', 'totara_core'));
        $mform->addGroupRule('enddate_group', $rule2);

        // alert options
        $alertoptions = get_config('cohort', 'alertoptions');
        if ($alertoptions == '') {
            $alertoptions = array();
        } else {
           $alertoptions = explode(',', $alertoptions);
           $alertoptions = array_combine($alertoptions, $alertoptions);
        }
        foreach ($COHORT_ALERT as $ocode => $oval) {
            if (in_array($ocode, $alertoptions)) {
                $alertoptions[$ocode] = $oval;
            }
        }
        if (!empty($alertoptions)) {
            $mform->addElement(
                'select',
                'alertmembers',
                get_string('alertmembers', 'totara_cohort'),
                $alertoptions
            );
            $mform->addHelpButton('alertmembers', 'alertmembers', 'totara_cohort');
        } else {
            $mform->addElement('hidden', 'alertmembers', COHORT_ALERT_NONE);
        }
        unset($alertoptions);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Display offical Cohort Tags
        if (!empty($CFG->usetags) && $DB->count_records('tag', array('tagtype' => 'official'))) {
            $mform->addElement('header', 'tagshdr', get_string('tags', 'tag'));

            $namefield = empty($CFG->keeptagnamecase) ? 'name' : 'rawname';
            $sql = "SELECT id, {$namefield} FROM {tag} WHERE tagtype = ? ORDER by name ASC";
            $params = array('official');
            if ($otags = $DB->get_records_sql_menu($sql, $params)) {
                $otagsselEl =& $mform->addElement('select', 'otags', get_string('otags', 'tag'), $otags, 'size="5"');
                $otagsselEl->setMultiple(true);
                $mform->addHelpButton('otags', 'otags', 'tag');
            }
        }

        $this->add_action_buttons();

        $this->set_data($cohort);
    }

    function definition_after_data() {
        $mform =& $this->_form;

        // Fix odd date values
        // Check if form is frozen
        if ($mform->elementExists('startdate_group')) {

             $groupstart = $mform->getElement('startdate_group');
             $date = $groupstart->getValue();
             $startdateint = (int)$date["startdate"];

            if (!$startdateint) {
                 $mform->setDefault('startdate', '');
            } else {
                 $mform->setDefault('startdate', userdate($startdateint, get_string('datepickerlongyearphpuserdate', 'totara_core')));
            }
        }

        if ($mform->elementExists('enddate_group')) {

             $groupend = $mform->getElement('enddate_group');
             $date2 = $groupend->getValue();
             $enddateint = (int)$date2["enddate"];

            if (!$enddateint) {
                 $mform->setDefault('enddate', '');
            } else {
                 $mform->setDefault('enddate', userdate($enddateint, get_string('datepickerlongyearphpuserdate', 'totara_core')));
            }
        }
    }

    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        $idnumber = trim($data['idnumber']);
        if ($idnumber === '') {
            // Fine, empty is ok.

        } else if ($data['id']) {
            $current = $DB->get_record('cohort', array('id'=>$data['id']), '*', MUST_EXIST);
            if ($current->idnumber !== $idnumber) {
                if ($DB->record_exists('cohort', array('idnumber'=>$idnumber))) {
                    $errors['idnumber'] = get_string('duplicateidnumber', 'cohort');
                }
            }

        } else {
            if ($DB->record_exists('cohort', array('idnumber'=>$idnumber))) {
                $errors['idnumber'] = get_string('duplicateidnumber', 'cohort');
            }
        }
        // Check that startdate and enddate are empty or valid dates, and that
        // startdate is before enddate if both are provided
        $startdatestr = isset($data['startdate'])?$data['startdate']:'';
        $startdate = totara_date_parse_from_format(get_string('datepickerlongyearparseformat', 'totara_core'), $startdatestr );
        $enddatestr = isset($data['enddate'])?$data['enddate']:'';
        $enddate = totara_date_parse_from_format(get_string('datepickerlongyearparseformat', 'totara_core'), $enddatestr );

        // Enforce valid dates
        if (false === $startdate && $startdatestr !== get_string('datepickerlongyeardisplayformat', 'totara_core') && $startdatestr !== '') {
            $errors['startdate'] = get_string('error:dateformat', 'totara_cohort', get_string('datepickerlongyearplaceholder', 'totara_core'));
        }
        if (false === $enddate && $enddatestr !== get_string('datepickerlongyeardisplayformat', 'totara_core') && $enddatestr !== '') {
            $errors['enddate'] = get_string('error:dateformat', 'totara_cohort', get_string('datepickerlongyearplaceholder', 'totara_core'));
        }

        // Enforce start date before finish date
        if ($startdate > $enddate && $enddate) {
            $errstr = get_string('error:startafterfinish','totara_cohort');
            $errors['startdate_group'] = $errstr;
            $errors['enddate_group'] = $errstr;
            unset($errstr);
        }
        return $errors;
    }

    protected function get_category_options($currentcontextid) {
        global $CFG;
        require_once($CFG->libdir. '/coursecatlib.php');
        $displaylist = coursecat::make_categories_list('moodle/cohort:manage');
        $options = array();
        $syscontext = context_system::instance();
        if (has_capability('moodle/cohort:manage', $syscontext)) {
            $options[$syscontext->id] = $syscontext->get_context_name();
        }
        foreach ($displaylist as $cid=>$name) {
            $context = context_coursecat::instance($cid);
            $options[$context->id] = $name;
        }
        // Always add current - this is not likely, but if the logic gets changed it might be a problem.
        if (!isset($options[$currentcontextid])) {
            $context = context::instance_by_id($currentcontextid, MUST_EXIST);
            $options[$context->id] = $syscontext->get_context_name();
        }
        return $options;
    }
}

