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
 * @author Oleg Demeshev <oleg.demeshev@totaralms.com>
 * @package modules
 * @subpackage facetoface
 */

require_once "$CFG->dirroot/lib/formslib.php";

class attendee_note_form extends moodleform {

    public function definition() {

        $mform = & $this->_form;
        $attendee = $this->_customdata['attendee'];

        $mform->addElement('header', 'usernoteheader', get_string('usernoteheading', 'facetoface', $attendee->fullname));

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 's');
        $mform->setType('s', PARAM_INT);

        $mform->addElement('html', html_writer::tag('p', '&nbsp;', array('id' => 'attendee_note_err', 'class' => 'error')));

        $mform->addElement('text', 'usernote', get_string('attendeenote', 'facetoface'), 'maxlength="255" size="50"');
        $mform->setType('usernote', PARAM_TEXT);

        $submittitle = get_string('savenote', 'facetoface');
        $this->add_action_buttons(true, $submittitle);
        $this->set_data($attendee);
    }
}

class attendee_note {

    protected $id = 0;
    /**
     * Attendee id
     *
     * @var int
     */
    protected $userid = 0;

    /**
     * Facetoface session id
     *
     * @var type
     */
    protected $sessionid = 0;

    /**
     * Attendee note
     *
     * @var string
     */
    protected $usernote = '';

    /**
     * Attendee fullname
     *
     * @var string
     */
    protected $fullname = '';

    /**
     * Create instance of attendee
     *
     * @param int $id
     */
    public function __construct($userid = 0, $sessionid = 0) {
        if ($userid && $sessionid) {
            $this->userid = $userid;
            $this->sessionid = $sessionid;
            $this->load();
        }
    }

    /**
     * Get stdClass with attendee properties
     *
     * @return stdClass
     */
    public function get() {
        $obj = new stdClass();
        $obj->userid = $this->userid;
        $obj->sessionid = $this->sessionid;
        $obj->usernote = $this->usernote;
        $obj->note = $this->usernote;
        $obj->fullname = $this->fullname;
        return $obj;
    }

    /**
     * Set attendee properties
     *
     * @param stdClass $todb
     * @return $this
     */
    public function set(stdClass $todb) {
        if (isset($todb->id)) {
            $this->userid = $todb->id;
        }
        if (isset($todb->s)) {
            $this->sessionid = $todb->s;
        }
        if (isset($todb->usernote)) {
            $this->usernote = clean_param($todb->usernote, PARAM_TEXT);
        }
        return $this;
    }

    /**
     * Saves current attendee properties
     *
     * @return $this
     */
    public function save() {
        global $DB;

        $todb = $this->get();
        $todb->id = $this->id;

        if(!$DB->update_record('facetoface_signups_status', $todb)) {
            throw new exception('Cannot update facetoface attendee', 21);
        }

        // Refresh data.
        $this->load();
        return $this;
    }

    /**
     * load attendee properties from DB
     *
     * @return $this
     */
    public function load() {
        $attendee = facetoface_get_attendee($this->sessionid, $this->userid);
        if (!$attendee) {
            throw new exception('Cannot load facetoface attendee', 22);
        }
        $this->id = $attendee->statusid;
        $this->usernote = $attendee->usernote;
        $this->fullname = fullname($attendee);
        return $this;
    }
}