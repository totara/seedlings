<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2014 onwards Totara Learning Solutions LTD
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
 * @author Petr Skoda <petr.skoda@totaralms.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');

admin_externalpage_setup('roledefaults');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('roledefaults', 'totara_core'));

$allroles = role_get_names(null, ROLENAME_ORIGINALANDSHORT, false);
$systemcontext = context_system::instance();

$allpermissions = array(
    CAP_INHERIT => get_string('none'),
    CAP_ALLOW => get_string('allow', 'core_role'),
    CAP_PREVENT => get_string('prevent', 'core_role'),
    CAP_PROHIBIT => get_string('prohibit', 'core_role'),
);

$roleprinted = false;

foreach ($allroles as $role) {
    if (empty($role->archetype)) {
        // We have capability defaults only for roles with archetypes.
        continue;
    }
    $diff = array();

    $currentcaps = $DB->get_records_menu('role_capabilities', array('roleid' => $role->id, 'contextid' => $systemcontext->id), '', 'capability, permission');
    $defaultcaps = $defaultcaps = get_default_capabilities($role->archetype);

    foreach ($defaultcaps as $capability => $permission) {
        $currentpermission = isset($currentcaps[$capability]) ? $currentcaps[$capability] : CAP_INHERIT;
        if ($permission != $currentpermission) {
            $diff[] = array($capability, $allpermissions[$permission], $allpermissions[$currentpermission]);
        }
        unset($currentcaps[$capability]);
    }
    foreach ($currentcaps as $capability => $currentpermission) {
        $permission = CAP_INHERIT;
        if ($permission != $currentpermission) {
            $diff[] = array($capability, $allpermissions[$permission], $allpermissions[$currentpermission]);
        }
    }

    if (!$diff) {
        continue;
    }
    $roleprinted = true;

    echo $OUTPUT->heading($role->localname, 3);
    $table = new html_table();
    $table->colclasses = array('leftalign', 'leftalign', 'leftalign');
    $table->id = 'roledefaults';
    $table->attributes['class'] = 'admintable generaltable';
    $table->head = array(
        get_string('capability', 'role'),
        get_string('default'),
        get_string('permission', 'role'),
    );
    $table->data = $diff;
    echo html_writer::table($table);
}

if (!$roleprinted) {
    echo get_string('roledefaultsnochanges', 'totara_core');
}

echo $OUTPUT->footer();
