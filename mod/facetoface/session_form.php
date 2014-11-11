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
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @author Francois Marier <francois@catalyst.net.nz>
 * @package modules
 * @subpackage facetoface
 */
defined('MOODLE_INTERNAL') || die();


require_once("{$CFG->libdir}/formslib.php");
require_once("{$CFG->dirroot}/mod/facetoface/lib.php");


class mod_facetoface_session_form extends moodleform {

    function definition() {
        global $CFG, $DB;

        $mform =& $this->_form;
        $context = context_course::instance($this->_customdata['course']->id);

        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->addElement('hidden', 'f', $this->_customdata['f']);
        $mform->addElement('hidden', 's', $this->_customdata['s']);
        $mform->addElement('hidden', 'c', $this->_customdata['c']);
        $mform->setType('id', PARAM_INT);
        $mform->setType('f', PARAM_INT);
        $mform->setType('s', PARAM_INT);
        $mform->setType('c', PARAM_INT);

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $editoroptions = $this->_customdata['editoroptions'];

        // Show all custom fields
        $customfields = $this->_customdata['customfields'];
        facetoface_add_customfields_to_form($mform, $customfields);

        $displaytimezones = get_config(null, 'facetoface_displaysessiontimezones');

        $formarray  = array();
        $formarray[] = $mform->createElement('selectyesno', 'datetimeknown', get_string('sessiondatetimeknown', 'facetoface'));
        $formarray[] = $mform->createElement('static', 'datetimeknownhint', '', html_writer::tag('span', get_string('datetimeknownhinttext','facetoface'), array('class' => 'hint-text')));
        $mform->addGroup($formarray,'datetimeknown_group', get_string('sessiondatetimeknown','facetoface'), array(' '),false);
        $mform->addGroupRule('datetimeknown_group', null, 'required', null, 'client');
        $mform->setDefault('datetimeknown', false);
        $mform->addHelpButton('datetimeknown_group', 'sessiondatetimeknown', 'facetoface');

        $repeatarray = array();
        $timezones = totara_get_clean_timezone_list(true);
        $timezones[get_string('sessiontimezoneunknown', 'facetoface')] = get_string('sessiontimezoneunknown', 'facetoface');
        $repeatarray[] = &$mform->createElement('hidden', 'sessiondateid', 0);
        if (!empty($displaytimezones)) {
            $repeatarray[] = $mform->createElement('select', 'sessiontimezone', get_string('sessiontimezone', 'facetoface'), $timezones);
        } else {
            $repeatarray[] = $mform->createElement('hidden', 'sessiontimezone', $this->_customdata['defaulttimezone']);
        }
        $repeatarray[] = &$mform->createElement('date_time_selector', 'timestart', get_string('timestart', 'facetoface'));
        $repeatarray[] = &$mform->createElement('date_time_selector', 'timefinish', get_string('timefinish', 'facetoface'));
        $checkboxelement = &$mform->createElement('checkbox', 'datedelete', '', get_string('dateremove', 'facetoface'));
        unset($checkboxelement->_attributes['id']); // necessary until MDL-20441 is fixed
        $repeatarray[] = $checkboxelement;
        $repeatarray[] = &$mform->createElement('html', html_writer::empty_tag('br')); // spacer

        $repeatcount = $this->_customdata['nbdays'];

        $repeatoptions = array();
        $repeatoptions['timestart']['disabledif'] = array('datetimeknown', 'eq', 0);
        $repeatoptions['timefinish']['disabledif'] = array('datetimeknown', 'eq', 0);
        if (!empty($displaytimezones)) {
            $repeatoptions['sessiontimezone']['disabledif'] = array('datetimeknown', 'eq', 0);
            $repeatoptions['sessiontimezone']['default'] = $this->_customdata['defaulttimezone'];
        }
        $mform->setType('sessiontimezone', PARAM_TEXT);
        $mform->setType('timestart', PARAM_INT);
        $mform->setType('timefinish', PARAM_INT);
        $mform->setType('sessiondateid', PARAM_INT);
        $this->repeat_elements($repeatarray, $repeatcount, $repeatoptions, 'date_repeats', 'date_add_fields',
                               1, get_string('dateadd', 'facetoface'), true);

        // Rooms form
        $pdroom = '';
        $roomnote = '';
        if (!empty($this->_customdata['s'])) {
            $sql = "SELECT r.*
                FROM {facetoface_sessions} s
                INNER JOIN {facetoface_room} r ON s.roomid = r.id
                WHERE s.id = ? AND r.custom = 0";
            $params = array($this->_customdata['s']);
            if ($room = $DB->get_record_sql($sql, $params)) {
                $pdroom = $room->name.', '.$room->building.', '.$room->address.', '.$room->description." (".get_string('capacity', 'facetoface').": ".$room->capacity.")";
                $pdroom = format_string($pdroom);
                if ($room->type == 'external') {
                    $roomnote = '<br><em>'.get_string('roommustbebookedtoexternalcalendar', 'facetoface').'</em>';
                }
            }
        }
        $mform->addElement('static', 'predefinedroom', get_string('room', 'facetoface'),
            '<span id="pdroom">'.$pdroom.'</span><span id="roomnote">'.$roomnote.'</span>');
        $mform->addElement('static', 'addpdroom', '', '<input type="button" value="'.get_string('choosepredefinedroom', 'facetoface').'" name="show-addpdroom-dialog" id="show-addpdroom-dialog" />');
        $mform->addElement('hidden', 'pdroomid', 0);
        $mform->setType('pdroomid', PARAM_INT);
        $mform->addElement('hidden', 'pdroomcapacity', 0);
        $mform->setType('pdroomcapacity', PARAM_INT);

        $mform->addElement('checkbox', 'customroom', '', get_string('otherroom', 'facetoface'));
        $mform->setType('customroom', PARAM_INT);
        $mform->disabledIf('customroom', 'datetimeknown', 'eq', 0);

        $mform->addElement('html', '<div class="fitem"><div class="fitemtitle"></div><table><tr><td>');
        $mform->addElement('static', '', '', get_string('roomname', 'facetoface'));
        $mform->addElement('html', '</td><td>');
        $mform->addElement('static', '', '', get_string('building', 'facetoface'));
        $mform->addElement('html', '</td><td>');
        $mform->addElement('static', '', '', get_string('address', 'facetoface'));
        $mform->addElement('html', '</td><td>');
        $mform->addElement('static', '', '', get_string('capacity', 'facetoface'));
        $mform->addElement('html', '</td></tr><td>');
        $mform->addElement('text', 'croomname', array(), array('class' => 'cellwidth', 'maxlength' => '90'));
        $mform->setType('croomname', PARAM_TEXT);
        $mform->disabledIf('croomname', 'customroom', 'notchecked');
        $mform->addElement('html', '</td><td>');
        $mform->addElement('text', 'croombuilding', array(), array('class' => 'cellwidth', 'maxlength' => '90'));
        $mform->setType('croombuilding', PARAM_TEXT);
        $mform->disabledIf('croombuilding', 'customroom', 'notchecked');
        $mform->addElement('html', '</td><td>');
        $mform->addElement('text', 'croomaddress', array(), array('class' => 'cellwidth', 'maxlength' => '230'));
        $mform->setType('croomaddress', PARAM_TEXT);
        $mform->disabledIf('croomaddress', 'customroom', 'notchecked');
        $mform->addElement('html', '</td><td>');
        $mform->addElement('text', 'croomcapacity', array(), array('class' => 'cellwidth', 'maxlength' => '10'));
        $mform->disabledIf('croomcapacity', 'customroom', 'notchecked');
        $mform->setType('croomcapacity', PARAM_INT);
        $mform->addElement('html', '</td><tr></table></div>');

        $mform->addElement('text', 'capacity', get_string('capacity', 'facetoface'), 'size="5"');
        $mform->addRule('capacity', null, 'required', null, 'client');
        $mform->setType('capacity', PARAM_INT);
        $mform->setDefault('capacity', 10);
        $mform->addRule('capacity', null, 'numeric', null, 'client');
        $mform->addHelpButton('capacity', 'capacity', 'facetoface');

        $mform->addElement('checkbox', 'allowoverbook', get_string('allowoverbook', 'facetoface'));
        $mform->addHelpButton('allowoverbook', 'allowoverbook', 'facetoface');

        if (has_capability('mod/facetoface:configurecancellation', $context)) {
            $mform->addElement('advcheckbox', 'allowcancellations', get_string('allowcancellations', 'facetoface'));
            $mform->setDefault('allowcancellations', $this->_customdata['facetoface']->allowcancellationsdefault);
            $mform->addHelpButton('allowcancellations', 'allowcancellations', 'facetoface');
        }

        $facetoface_allowwaitlisteveryone = get_config(null, 'facetoface_allowwaitlisteveryone');
        if ($facetoface_allowwaitlisteveryone) {
            $mform->addElement('checkbox', 'waitlisteveryone', get_string('waitlisteveryone', 'facetoface'));
            $mform->addHelpButton('waitlisteveryone', 'waitlisteveryone', 'facetoface');
            $mform->disabledIf('waitlisteveryone', 'allowoverbook');
        }

        // Show minimum capacity and cut-off (for when this should be reached).
        $mform->addElement('checkbox', 'enablemincapacity', get_string('enablemincapacity', 'facetoface'));
        $mform->setDefault('enablemincapacity', 0);
        $mform->disabledIf('enablemincapacity', 'datetimeknown', 'eq', 0);

        $mform->addElement('text', 'mincapacity', get_string('mincapacity', 'facetoface'), 'size="5"');
        $mform->disabledIf('mincapacity', 'enablemincapacity', 'notchecked');
        $mform->disabledIf('mincapacity', 'datetimeknown', 'eq', 0);
        $mform->setType('mincapacity', PARAM_INT);
        $mform->setDefault('mincapacity', 0);
        $mform->addRule('mincapacity', null, 'numeric', null, 'client');
        $mform->addHelpButton('mincapacity', 'mincapacity', 'facetoface');

        $mform->addElement('duration', 'cutoff', get_string('cutoff', 'facetoface'), array('defaultunit' => HOURSECS, 'optional' => false));
        $mform->setType('cutoff', PARAM_INT);
        $mform->setDefault('cutoff', DAYSECS);
        $mform->disabledIf('cutoff', 'enablemincapacity', 'notchecked');
        $mform->disabledIf('cutoff', 'datetimeknown', 'eq', 0);
        $mform->addHelpButton('cutoff', 'cutoff', 'facetoface');

        $mform->addElement('text', 'duration', get_string('duration', 'facetoface'), 'size="5"');
        $mform->setType('duration', PARAM_TEXT);
        $mform->addHelpButton('duration', 'duration', 'facetoface');

        if (!get_config(NULL, 'facetoface_hidecost')) {
            $formarray  = array();
            $formarray[] = $mform->createElement('text', 'normalcost', get_string('normalcost', 'facetoface'), 'size="5"');
            $formarray[] = $mform->createElement('static', 'normalcosthint', '', html_writer::tag('span', get_string('normalcosthinttext','facetoface'), array('class' => 'hint-text')));
            $mform->addGroup($formarray,'normalcost_group', get_string('normalcost','facetoface'), array(' '),false);
            $mform->setType('normalcost', PARAM_TEXT);
            $mform->addHelpButton('normalcost_group', 'normalcost', 'facetoface');

            if (!get_config(NULL, 'facetoface_hidediscount')) {
                $formarray  = array();
                $formarray[] = $mform->createElement('text', 'discountcost', get_string('discountcost', 'facetoface'), 'size="5"');
                $formarray[] = $mform->createElement('static', 'discountcosthint', '', html_writer::tag('span', get_string('discountcosthinttext','facetoface'), array('class' => 'hint-text')));
                $mform->addGroup($formarray,'discountcost_group', get_string('discountcost','facetoface'), array(' '),false);
                $mform->setType('discountcost', PARAM_TEXT);
                $mform->addHelpButton('discountcost_group', 'discountcost', 'facetoface');
            }
        }

        $mform->addElement('checkbox', 'availablesignupnote', get_string('availablesignupnote', 'facetoface'));
        $mform->addHelpButton('availablesignupnote', 'availablesignupnote', 'facetoface');
        $mform->setDefault('availablesignupnote', $this->_customdata['facetoface']->allowsignupnotedefault);

        $mform->addElement('checkbox', 'selfapproval', get_string('selfapproval', 'facetoface'));
        $mform->addHelpButton('selfapproval', 'selfapproval', 'facetoface');
        if (!$this->_customdata['facetoface']->approvalreqd) {
            $mform->hardFreeze('selfapproval');
        }

        $mform->addElement('editor', 'details_editor', get_string('details', 'facetoface'), null, $editoroptions);
        $mform->setType('details_editor', PARAM_RAW);
        $mform->addHelpButton('details_editor', 'details', 'facetoface');

        // Choose users for trainer roles
        $roles = facetoface_get_trainer_roles($context);

        if ($roles) {
            // Get current trainers
            $current_trainers = facetoface_get_trainers($this->_customdata['s']);
            // Get course context and roles
            $rolenames = role_get_names($context);
            // Loop through all selected roles
            $header_shown = false;
            foreach ($roles as $role) {
                $rolename = format_string($rolenames[$role->id]->localname);

                // Attempt to load users with this role in this context.
                $usernamefields = get_all_user_name_fields(true, 'u');
                $rs = get_role_users($role->id, $context, true, "u.id, {$usernamefields}", 'u.id ASC');

                if (!$rs) {
                    continue;
                }

                $choices = array();
                foreach ($rs as $roleuser) {
                    $choices[$roleuser->id] = fullname($roleuser);
                }

                // Show header (if haven't already)
                if ($choices && !$header_shown) {
                    $mform->addElement('header', 'trainerroles', get_string('sessionroles', 'facetoface'));
                    $header_shown = true;
                }

                // If only a few, use checkboxes
                if (count($choices) < 4) {
                    $role_shown = false;
                    foreach ($choices as $cid => $choice) {
                        // Only display the role title for the first checkbox for each role
                        if (!$role_shown) {
                            $roledisplay = $rolename;
                            $role_shown = true;
                        } else {
                            $roledisplay = '';
                        }

                        $mform->addElement('advcheckbox', 'trainerrole['.$role->id.']['.$cid.']', $roledisplay, $choice, null, array('', $cid));
                        $mform->setType('trainerrole['.$role->id.']['.$cid.']', PARAM_INT);
                    }
                } else {
                    $mform->addElement('select', 'trainerrole['.$role->id.']', $rolename, $choices, array('multiple' => 'multiple'));
                    $mform->setType('trainerrole['.$role->id.']', PARAM_SEQUENCE);
                }

                // Select current trainers
                if ($current_trainers) {
                    foreach ($current_trainers as $roleid => $trainers) {
                        $t = array();
                        foreach ($trainers as $trainer) {
                            $t[] = $trainer->id;
                            $mform->setDefault('trainerrole['.$roleid.']['.$trainer->id.']', $trainer->id);
                        }

                        $mform->setDefault('trainerrole['.$roleid.']', implode(',', $t));
                    }
                }
            }
        }

        // If conflicts are disabled
        if (!empty($CFG->facetoface_allowschedulingconflicts)) {
            $mform->addElement('selectyesno', 'allowconflicts', get_string('allowschedulingconflicts', 'facetoface'));
            $mform->setDefault('allowconflicts', 0); // defaults to 'no'
            $mform->addHelpButton('allowconflicts', 'allowschedulingconflicts', 'facetoface');
            $mform->setType('allowconflicts', PARAM_BOOL);
        }

        $this->add_action_buttons();
    }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $dates = array();
        $dateids = $data['sessiondateid'];
        $datecount = count($dateids);
        for ($i=0; $i < $datecount; $i++) {
            //only check timezones if datetimeknown is set
            if (!empty($data['datetimeknown'])) {
                $timezone = $data["sessiontimezone"][$i];
                if ($timezone == get_string('sessiontimezoneunknown', 'facetoface')) {
                    $errors['sessiontimezone['.$i.']'] =  get_string('error:mustspecifytimezone', 'facetoface');
                }
            }
            $starttime = $data["timestart[$i]"];
            $endtime = $data["timefinish[$i]"];
            $removecheckbox = empty($data["datedelete"]) ? array() : $data["datedelete"];
            if ($starttime > $endtime && !isset($removecheckbox[$i])) {
                $errstr = get_string('error:sessionstartafterend', 'facetoface');
                $errors['timestart['.$i.']'] = $errstr;
                $errors['timefinish['.$i.']'] = $errstr;
                unset($errstr);
                continue;
            }
            //check this date does not overlap with any previous dates - time overlap logic from a Stack Overflow post
            if (!empty($dates)) {
                foreach ($dates as $existing) {
                    if (($endtime > $existing->timestart) && ($existing->timefinish > $starttime) ||
                        ($endtime == $existing->timefinish) || ($starttime == $existing->timestart)) {
                        // This date clashes with an existing date - either they overlap or
                        // one of them is zero minutes and they start at the same time or end at the same time.
                        $errors['timestart['.$i.']'] = get_string('error:sessiondatesconflict', 'facetoface');
                    }
                }
            }
            // If valid date, add to array
            $date = new object();
            $date->timestart = $starttime;
            $date->timefinish = $endtime;
            $dates[] = $date;
        }

        $datefound = false;
        if (!empty($data['datetimeknown'])) {
            for ($i = 0; $i < $data['date_repeats']; $i++) {
                if (empty($data['datedelete'][$i])) {
                    $datefound = true;
                    break;
                }
            }

            if (!$datefound) {
                $errors['datedelete[0]'] = get_string('validation:needatleastonedate', 'facetoface');
            }
        }

        // Check the availabilty of trainers if scheduling not allowed
        $trainerdata = !empty($data['trainerrole']) ? $data['trainerrole'] : array();
        $allowconflicts = !empty($data['allowconflicts']);

        if ($datefound && !$allowconflicts && is_array($trainerdata)) {
            $wheresql = '';
            $whereparams = array();
            if (!empty($this->_customdata['s'])) {
                $wheresql = ' AND s.id != ?';
                $whereparams[] = $this->_customdata['s'];
            }

            // Loop through roles
            $hasconflicts = 0;
            $context = context_course::instance($this->_customdata['course']->id);
            foreach ($trainerdata as $roleid => $trainers) {
                // Attempt to load users with this role in this context.
                $trainerlist = get_role_users($roleid, $context, true, 'u.id, u.firstname, u.lastname', 'u.id ASC');
                // Initialize error variable.
                $trainererrors = '';
                // Loop through trainers in this role.
                foreach ($trainers as $trainer) {

                    if (!$trainer) {
                        continue;
                    }

                    // Check their availability.
                    $availability = facetoface_get_sessions_within($dates, $trainer, $wheresql, $whereparams);
                    if (!empty($availability)) {
                        // Verify if trainers come in form of checkboxes or dropdown list to properly place the errors.
                        if (isset($this->_form->_types["trainerrole[{$roleid}][{$trainer}]"])) {
                            $errors["trainerrole[{$roleid}][{$trainer}]"] = facetoface_get_session_involvement($trainerlist[$trainer], $availability);
                        } else if (isset($this->_form->_types["trainerrole[{$roleid}]"])) {
                            $trainererrors .= html_writer::tag('div', facetoface_get_session_involvement($trainerlist[$trainer], $availability));
                        }
                        ++$hasconflicts;
                    }
                }

                if (isset($this->_form->_types["trainerrole[{$roleid}]"]) && $trainererrors != '') {
                    $errors["trainerrole[{$roleid}]"] = $trainererrors;
                }
            }

            // If there are conflicts, add a help message to checkbox
            if ($hasconflicts) {
                if ($hasconflicts > 1) {
                    $errors['allowconflicts'] = get_string('error:therearexconflicts', 'facetoface', $hasconflicts);
                } else {
                    $errors['allowconflicts'] = get_string('error:thereisaconflict', 'facetoface');
                }
            }
        }

        //check capcity is a number
        if (empty($data['capacity'])) {
            $errors['capacity'] = get_string('error:capacityzero', 'facetoface');
        } else {
            $capacity = $data['capacity'];
            if (!(is_numeric($capacity) && (intval($capacity) == $capacity) && $capacity > 0)) {
                $errors['capacity'] = get_string('error:capacitynotnumeric', 'facetoface');
            }
        }

        // Check the minimum capacity and cut-off.
        if (!empty($data['enablemincapacity'])) {
            if (empty($data['mincapacity'])) {
                $errors['mincapacity'] = get_string('error:mincapacityzero', 'facetoface');
            } else {
                $mincapacity = $data['mincapacity'];
                if (!is_numeric($mincapacity) || (intval($mincapacity) != $mincapacity)) {
                    $errors['mincapacity'] = get_string('error:mincapacitynotnumeric', 'facetoface');
                } else if ($mincapacity > $data['capacity']) {
                    $errors['mincapacity'] = get_string('error:mincapacitytoolarge', 'facetoface');
                }
            }

            // Check the cut-off is at least the day before the earliest start time.
            $cutoff = $data['cutoff'];
            if ($cutoff < DAYSECS) {
                $errors['cutoff'] = get_string('error:cutofftoolate', 'facetoface');
            }

            foreach ($dates as $dateid => $date) {
                // If the date is being deleted then we don't need to test against it.
                if (!empty($data['datedelete'][$dateid])) {
                    continue;
                }

                $cutofftimestamp = $date->timestart - $cutoff;
                if ($cutofftimestamp < time()) {
                    $errors['cutoff'] = get_string('error:cutofftoolate', 'facetoface');
                    break;
                }
            }
        }

        return $errors;
    }

    function set_data($values) {
        $mform =& $this->_form;
        foreach ($values as $key => $val) {
            if (strpos($key, 'sessiontimezone') !== false) {
                $idx1 = strpos($key, '[');
                $idx2 = strpos($key, ']');
                $idx = substr($key, $idx1 + 1, ($idx2 - $idx1) - 1);
                $tz = ($val == 'UTC') ? 0 : $val;
                $el = $mform->getElement('timestart['.$idx. ']');
                $el->set_option('timezone', $tz);
                $el = $mform->getElement('timefinish['.$idx. ']');
                $el->set_option('timezone', $tz);
            }
        }
        parent::set_data($values);
    }
}
