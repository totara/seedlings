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

require_once "$CFG->dirroot/mod/facetoface/lib.php";

if ($data = data_submitted()) {
    // Check that the setting has changed before taking action.
    $oldvalue = get_config(null, 'facetoface_displaysessiontimezones');
    if (isset($data->s__facetoface_displaysessiontimezones) && $data->s__facetoface_displaysessiontimezones != $oldvalue) {
        // Force saving of setting before doing update of events.
        set_config('facetoface_displaysessiontimezones', $data->s__facetoface_displaysessiontimezones);
        $sessions = $DB->get_records('facetoface_sessions');
        foreach ($sessions as $s) {
            $session = facetoface_get_session($s->id);
            facetoface_update_calendar_entries($session);
        }
    }
}

$settings->add(new admin_setting_configtext('facetoface_fromaddress', new lang_string('setting:fromaddress_caption', 'facetoface'),new lang_string('setting:fromaddress', 'facetoface'), new lang_string('setting:fromaddressdefault', 'facetoface'), "/^((?:[\w\.\-])+\@(?:(?:[a-zA-Z\d\-])+\.)+(?:[a-zA-Z\d]{2,4}))$/",30));

// Load roles
$systemcontext = context_system::instance();
$choices = array();
if ($roles = role_fix_names(get_all_roles(), $systemcontext)) {
    foreach ($roles as $role) {
        $choices[$role->id] = format_string($role->localname);
    }
}

$settings->add(new admin_setting_configmultiselect('facetoface_session_roles', new lang_string('setting:sessionroles_caption', 'facetoface'), new lang_string('setting:sessionroles', 'facetoface'), array(), $choices));

$settings->add(new admin_setting_configcheckbox('facetoface_allowschedulingconflicts', new lang_string('setting:allowschedulingconflicts_caption', 'facetoface'), new lang_string('setting:allowschedulingconflicts', 'facetoface'), 0));

$settings->add(new admin_setting_configcheckbox('facetoface_notificationdisable', new lang_string('setting:notificationdisable_caption', 'facetoface'), new lang_string('setting:notificationdisable', 'facetoface'), 0));

$settings->add(new admin_setting_configcheckbox('facetoface_displaysessiontimezones', new lang_string('setting:displaysessiontimezones_caption', 'facetoface'), new lang_string('setting:displaysessiontimezones', 'facetoface'), 1));

$settings->add(new admin_setting_heading('facetoface_multiplesessions_header', get_string('multiplesessionsheading', 'facetoface'), ''));

$settings->add(new admin_setting_configcheckbox('facetoface_multiplesessions', get_string('setting:multiplesessions_caption', 'facetoface'), get_string('setting:multiplesessions', 'facetoface'), 0));

$settings->add(new admin_setting_heading('facetoface_manageremail_header', new lang_string('manageremailheading', 'facetoface'), ''));

$settings->add(new admin_setting_configcheckbox('facetoface_addchangemanageremail', new lang_string('setting:addchangemanageremail_caption', 'facetoface'),new lang_string('setting:addchangemanageremail', 'facetoface'), 0));

$settings->add(new admin_setting_configtext('facetoface_manageraddressformat', new lang_string('setting:manageraddressformat_caption', 'facetoface'),new lang_string('setting:manageraddressformat', 'facetoface'), new lang_string('setting:manageraddressformatdefault', 'facetoface'), PARAM_TEXT));

$settings->add(new admin_setting_configtext('facetoface_manageraddressformatreadable', new lang_string('setting:manageraddressformatreadable_caption', 'facetoface'),new lang_string('setting:manageraddressformatreadable', 'facetoface'), new lang_string('setting:manageraddressformatreadabledefault', 'facetoface'), PARAM_NOTAGS));

$settings->add(new admin_setting_heading('facetoface/managerreserveheader',
                                         new lang_string('setting:managerreserveheader', 'mod_facetoface'), ''));

$settings->add(new admin_setting_configcheckbox('facetoface/managerreserve',
                                                new lang_string('setting:managerreserve', 'mod_facetoface'),
                                                new lang_string('setting:managerreserve_desc', 'mod_facetoface'), 0));

$settings->add(new admin_setting_configtext('facetoface/maxmanagerreserves',
                                            new lang_string('setting:maxmanagerreserves', 'mod_facetoface'),
                                            new lang_string('setting:maxmanagerreserves_desc', 'mod_facetoface'), 1, PARAM_INT));

$settings->add(new admin_setting_configtext('facetoface/reservecanceldays',
                                            new lang_string('setting:reservecanceldays', 'mod_facetoface'),
                                            new lang_string('setting:reservecanceldays_desc', 'mod_facetoface'), 1, PARAM_INT));

$settings->add(new admin_setting_configtext('facetoface/reservedays',
                                            new lang_string('setting:reservedays', 'mod_facetoface'),
                                            new lang_string('setting:reservedays_desc', 'mod_facetoface'), 2, PARAM_INT));

$settings->add(new admin_setting_heading('facetoface_cost_header', new lang_string('costheading', 'facetoface'), ''));

$settings->add(new admin_setting_configcheckbox('facetoface_hidecost', new lang_string('setting:hidecost_caption', 'facetoface'),new lang_string('setting:hidecost', 'facetoface'), 0));

$settings->add(new admin_setting_configcheckbox('facetoface_hidediscount', new lang_string('setting:hidediscount_caption', 'facetoface'),new lang_string('setting:hidediscount', 'facetoface'), 0));


$settings->add(new admin_setting_heading('facetoface_icalendar_header', new lang_string('icalendarheading', 'facetoface'), ''));

$settings->add(new admin_setting_configcheckbox('facetoface_oneemailperday', new lang_string('setting:oneemailperday_caption', 'facetoface'),new lang_string('setting:oneemailperday', 'facetoface'), 0));

$settings->add(new admin_setting_configcheckbox('facetoface_disableicalcancel', new lang_string('setting:disableicalcancel_caption', 'facetoface'),new lang_string('setting:disableicalcancel', 'facetoface'), 0));


$settings->add(new admin_setting_heading('facetoface_bulkadd_header', new lang_string('bulkaddheading', 'facetoface'), ''));

$menu['bulkaddsourceidnumber'] = new lang_string('bulkaddsourceidnumber', 'facetoface');
$menu['bulkaddsourceuserid']   = new lang_string('bulkaddsourceuserid', 'facetoface');
$menu['bulkaddsourceusername'] = new lang_string('bulkaddsourceusername', 'facetoface');

$settings->add(new admin_setting_configselect('facetoface_bulkaddsource',
        new lang_string('setting:bulkaddsource_caption', 'facetoface'),
        new lang_string('setting:bulkaddsource', 'facetoface'), 'bulkaddsourceidnumber', $menu));

// Export.
$settings->add(new admin_setting_heading('facetoface_export_header', new lang_string('exportheading', 'facetoface'), ''));
$settings->add(new admin_setting_configtext('facetoface_export_userprofilefields', new lang_string('exportuserprofilefields', 'facetoface'), new lang_string('exportuserprofilefields_desc', 'facetoface'), 'firstname,lastname,idnumber,institution,department,email', PARAM_TEXT));

$settings->add(new admin_setting_configtext('facetoface_export_customprofilefields', new lang_string('exportcustomprofilefields', 'facetoface'), new lang_string('exportcustomprofilefields_desc', 'facetoface'), '', PARAM_TEXT));

// Create array with existing custom fields (if any), empty array otherwise.
$customfields = array();
$allcustomfields = facetoface_get_session_customfields();
foreach ($allcustomfields as $fieldid => $fielname) {
    $customfields[$fieldid] = $fielname->name;
}

// List of facetoface session fields that can be selected as filters.
$settings->add(new admin_setting_heading('facetoface_calendarfilters_header', new lang_string('calendarfiltersheading', 'facetoface'), ''));
$calendarfilters = array(
    'timestart'  => get_string('startdateafter', 'facetoface'),
    'timefinish' => get_string('finishdatebefore', 'facetoface'),
    'room'       => get_string('room', 'facetoface'),
    'building'   => get_string('building', 'facetoface'),
    'address'    => get_string('address', 'facetoface'),
    'capacity'   => get_string('capacity', 'facetoface')
);
$calendarfilters = $calendarfilters + $customfields;
$settings->add(new admin_setting_configmultiselect('facetoface_calendarfilters', new lang_string('setting:calendarfilterscaption', 'facetoface'), new lang_string('setting:calendarfilters', 'facetoface'), array('room', 'building', 'address'), $calendarfilters));

// List of existing custom fields.
$html = facetoface_list_of_customfields();
$html .= html_writer::start_tag('p');
$url = new moodle_url('/mod/facetoface/customfield.php', array('id' => 0));
$html .= html_writer::link($url, new lang_string('addnewfieldlink', 'facetoface'));
$html .= html_writer::end_tag('p');

$settings->add(new admin_setting_heading('facetoface_customfields_header', new lang_string('customfieldsheading', 'facetoface'), $html));

// List of existing site notices.
$html = html_writer::start_tag('p');
$url = html_writer::link(new moodle_url('/blocks/facetoface/calendar.php'), new lang_string('setting:sitenoticeshere', 'facetoface'));
$html .= new lang_string('setting:sitenotices', 'facetoface', $url);
$html .= html_writer::end_tag('p');
$html .= facetoface_list_of_sitenotices();
$html .= html_writer::start_tag('p');
$url = new moodle_url('/mod/facetoface/sitenotice.php', array('id' => 0));
$html .= html_writer::link($url, new lang_string('addnewnoticelink', 'facetoface'));
$html .= html_writer::end_tag('p');

$settings->add(new admin_setting_heading('facetoface_sitenotices_header', new lang_string('sitenoticesheading', 'facetoface'), $html));

// Link to notification templates.
$html = html_writer::start_tag('p');
$url = new moodle_url('/mod/facetoface/notification/template/');
$html .= html_writer::link($url, new lang_string('managenotificationtemplates', 'facetoface'));
$html .= html_writer::end_tag('p');

$settings->add(new admin_setting_heading('facetoface_notification_template', new lang_string('notificationtemplates', 'facetoface'), $html));

// Link to rooms.
$html = html_writer::start_tag('p');
$url = new moodle_url('/mod/facetoface/room/manage.php');
$html .= html_writer::link($url, new lang_string('managerooms', 'facetoface'));
$html .= html_writer::end_tag('p');

$settings->add(new admin_setting_heading('facetoface_rooms', new lang_string('rooms', 'facetoface'), $html));
