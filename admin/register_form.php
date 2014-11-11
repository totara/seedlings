<?php // $Id$

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once $CFG->libdir.'/formslib.php';

class register_form extends moodleform {
    function definition (){
        global $CFG, $USER;

        $mform =& $this->_form;

        $mform->addElement('header', 'settingsheader', get_string('totararegistration', 'totara_core'));
        $radioarray=array();
        $radioarray[] = $mform->createElement('radio', 'registrationenabled', '', get_string('registrationenabled', 'admin'), 1);
        $radioarray[] = $mform->createElement('radio', 'registrationenabled', '', get_string('registrationdisabled', 'admin'), 0);
        $mform->addGroup($radioarray, 'registrationenabled', '', array(' '), false);
        $mform->setDefault('registrationenabled', 1);
        $this->add_action_buttons(false, get_string('save', 'admin'));

        $mform->addElement('header', 'registrationinfo', get_string('registrationinformation', 'admin'));
        $data = get_registration_data();
        foreach ($data as $key => $value) {
            $module = (strpos($key, 'totara') === 0 || ($key == 'debugstatus') || ($key == 'edition')) ? 'totara_core' : 'admin';
            $mform->addElement('static', $key, get_string($key, $module));
        }
    }
}

