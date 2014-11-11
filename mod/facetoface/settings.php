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

$ADMIN->add('modsettings', new admin_category('modfacetofacefolder', new lang_string('pluginname', 'mod_facetoface'), $module->is_enabled() === false));

$settings = new admin_settingpage($section, get_string('generalsettings', 'mod_facetoface'), 'moodle/site:config', $module->is_enabled() === false);

if ($ADMIN->fulltree) { // Improve performance.

    $settings->add(new admin_setting_configtext('facetoface_fromaddress', new lang_string('setting:fromaddress_caption', 'facetoface'),new lang_string('setting:fromaddress', 'facetoface'), new lang_string('setting:fromaddressdefault', 'facetoface'), "/^((?:[\w\.\-])+\@(?:(?:[a-zA-Z\d\-])+\.)+(?:[a-zA-Z\d]{2,4}))$/",30));

    $settings->add(new admin_setting_pickroles('facetoface_session_roles', new lang_string('setting:sessionroles_caption', 'facetoface'), new lang_string('setting:sessionroles', 'facetoface'), array()));

    $settings->add(new admin_setting_configcheckbox('facetoface_allowschedulingconflicts', new lang_string('setting:allowschedulingconflicts_caption', 'facetoface'), new lang_string('setting:allowschedulingconflicts', 'facetoface'), 0));

    $settings->add(new admin_setting_configcheckbox('facetoface_notificationdisable', new lang_string('setting:notificationdisable_caption', 'facetoface'), new lang_string('setting:notificationdisable', 'facetoface'), 0));

    $setting = new admin_setting_configcheckbox('facetoface_displaysessiontimezones', new lang_string('setting:displaysessiontimezones_caption', 'facetoface'), new lang_string('setting:displaysessiontimezones', 'facetoface'), 1);
    $setting->set_updatedcallback('facetoface_displaysessiontimezones_updated');
    $settings->add($setting);

    $settings->add(
        new admin_setting_configcheckbox(
            'facetoface_selectpositiononsignupglobal',
            new lang_string('setting:selectpositiononsignupglobal', 'facetoface'),
            new lang_string('setting:selectpositiononsignupglobal_caption', 'facetoface'),
            0
        )
    );

    $settings->add(new admin_setting_configcheckbox('facetoface_allowwaitlisteveryone',
        new lang_string('setting:allowwaitlisteveryone_caption', 'facetoface'),
        new lang_string('setting:allowwaitlisteveryone', 'facetoface'), 0));

    $settings->add( new admin_setting_configcheckbox('facetoface_lotteryenabled',
        new lang_string('setting:lotteryenabled_caption', 'facetoface'),
        new lang_string('setting:lotteryenabled', 'facetoface'), 0));

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

    $options = array();
    $options['bulkaddsourceidnumber'] = new lang_string('bulkaddsourceidnumber', 'facetoface');
    $options['bulkaddsourceuserid']   = new lang_string('bulkaddsourceuserid', 'facetoface');
    $options['bulkaddsourceusername'] = new lang_string('bulkaddsourceusername', 'facetoface');

    $settings->add(new admin_setting_configselect('facetoface_bulkaddsource',
        new lang_string('setting:bulkaddsource_caption', 'facetoface'),
        new lang_string('setting:bulkaddsource', 'facetoface'), 'bulkaddsourceidnumber', $options));

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

} // End of if ($ADMIN->fulltree).

$ADMIN->add('modfacetofacefolder', $settings);
// Tell core we already added the settings structure.
$settings = null;

$ADMIN->add('modfacetofacefolder', new admin_externalpage('modfacetofacecustomfields', new lang_string('customfieldsheading','facetoface'), "$CFG->wwwroot/mod/facetoface/customfields.php"));
$ADMIN->add('modfacetofacefolder', new admin_externalpage('modfacetofacerooms', new lang_string('rooms','facetoface'), "$CFG->wwwroot/mod/facetoface/room/manage.php"));
$ADMIN->add('modfacetofacefolder', new admin_externalpage('modfacetofacetemplates', new lang_string('notificationtemplates','facetoface'), "$CFG->wwwroot/mod/facetoface/notification/template/index.php"));
$ADMIN->add('modfacetofacefolder', new admin_externalpage('modfacetofacesitenotices', new lang_string('sitenoticesheading','facetoface'), "$CFG->wwwroot/mod/facetoface/sitenotices.php"));
