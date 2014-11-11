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
 * @author Andrew Hancox <andrewdchancox@googlemail.com> on behalf of Synergy Learning
 * @package totara
 * @subpackage enrol_totara_facetoface
 */

/**
 * Face-to-Face Direct enrolment plugin settings and presets.
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    // General settings.
    $settings->add(new admin_setting_heading('enrol_totara_facetoface_settings', '', get_string('pluginname_desc', 'enrol_totara_facetoface')));

    // Note: let's reuse the ext sync constants and strings here, internally it is very similar,
    //       it describes what should happend when users are not supposed to be enerolled any more.
    $options = array(
        ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
        ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
        ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
    );
    $settings->add(
        new admin_setting_configselect(
            'enrol_totara_facetoface/expiredaction',
            get_string('expiredaction', 'enrol_totara_facetoface'),
            get_string('expiredaction_help', 'enrol_totara_facetoface'),
            ENROL_EXT_REMOVED_KEEP,
            $options
        )
    );

    $options = array();
    for ($i=0; $i<24; $i++) {
        $options[$i] = $i;
    }
    $settings->add(
        new admin_setting_configselect(
            'enrol_totara_facetoface/expirynotifyhour',
            get_string('expirynotifyhour', 'core_enrol'),
            '',
            6,
            $options
        )
    );

    // Enrol instance defaults.
    $settings->add(new admin_setting_heading('enrol_totara_facetoface_defaults',
        get_string('enrolinstancedefaults', 'admin'), get_string('enrolinstancedefaults_desc', 'admin')));

    $settings->add(new admin_setting_configcheckbox('enrol_totara_facetoface/defaultenrol',
        get_string('defaultenrol', 'enrol'), get_string('defaultenrol_desc', 'enrol'), 1));

    $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                     ENROL_INSTANCE_DISABLED => get_string('no'));
    $settings->add(new admin_setting_configselect('enrol_totara_facetoface/status',
        get_string('status', 'enrol_totara_facetoface'), get_string('status_desc', 'enrol_totara_facetoface'), ENROL_INSTANCE_DISABLED, $options));

    $options = array(1  => get_string('yes'), 0 => get_string('no'));
    $settings->add(new admin_setting_configselect('enrol_totara_facetoface/newenrols',
        get_string('newenrols', 'enrol_totara_facetoface'), get_string('newenrols_desc', 'enrol_totara_facetoface'), 1, $options));

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(
            new admin_setting_configselect(
                'enrol_totara_facetoface/roleid',
                get_string('defaultrole', 'enrol_totara_facetoface'),
                get_string('defaultrole_desc', 'enrol_totara_facetoface'),
                $student->id,
                $options
            )
        );
    }

    $settings->add(new admin_setting_configduration('enrol_totara_facetoface/enrolperiod',
        get_string('enrolperiod', 'enrol_totara_facetoface'), get_string('enrolperiod_desc', 'enrol_totara_facetoface'), 0));

    $options = array(
        0 => get_string('no'),
        1 => get_string('expirynotifyenroller', 'core_enrol'),
        2 => get_string('expirynotifyall', 'core_enrol')
    );
    $settings->add(new admin_setting_configselect('enrol_totara_facetoface/expirynotify',
        get_string('expirynotify', 'core_enrol'), get_string('expirynotify_help', 'core_enrol'), 0, $options));

    $settings->add(new admin_setting_configduration('enrol_totara_facetoface/expirythreshold',
        get_string('expirythreshold', 'core_enrol'), get_string('expirythreshold_help', 'core_enrol'), 86400, 86400));

    $options = array(0 => get_string('never'),
                     1800 * DAYSECS => get_string('numdays', '', 1800),
                     1000 * DAYSECS => get_string('numdays', '', 1000),
                     365 * DAYSECS => get_string('numdays', '', 365),
                     180 * DAYSECS => get_string('numdays', '', 180),
                     150 * DAYSECS => get_string('numdays', '', 150),
                     120 * DAYSECS => get_string('numdays', '', 120),
                     90 * DAYSECS => get_string('numdays', '', 90),
                     60 * DAYSECS => get_string('numdays', '', 60),
                     30 * DAYSECS => get_string('numdays', '', 30),
                     21 * DAYSECS => get_string('numdays', '', 21),
                     14 * DAYSECS => get_string('numdays', '', 14),
                     7 * DAYSECS => get_string('numdays', '', 7));
    $settings->add(new admin_setting_configselect('enrol_totara_facetoface/longtimenosee',
        get_string('longtimenosee', 'enrol_totara_facetoface'), get_string('longtimenosee_help', 'enrol_totara_facetoface'), 0, $options));

    $settings->add(new admin_setting_configtext('enrol_totara_facetoface/maxenrolled',
        get_string('maxenrolled', 'enrol_totara_facetoface'), get_string('maxenrolled_help', 'enrol_totara_facetoface'), 0, PARAM_INT));

    $optyesno = array(1 => get_string('yes'), 0 => get_string('no'));
    $settings->add(new admin_setting_configselect('enrol_totara_facetoface/unenrolwhenremoved',
        get_string('unenrolwhenremoved', 'enrol_totara_facetoface'), '', 0, $optyesno));

    $settings->add(
        new admin_setting_configcheckbox(
            'enrol_totara_facetoface/sendcoursewelcomemessage',
            get_string('sendcoursewelcomemessage', 'enrol_totara_facetoface'),
            get_string('sendcoursewelcomemessage_help', 'enrol_totara_facetoface'),
            1
        )
    );
}