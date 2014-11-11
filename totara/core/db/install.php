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
 * @author Ciaran Irvine <ciaran.irvine@totaralms.com>
 * @package totara
 * @subpackage totara_core
 */

function xmldb_totara_core_install() {
    global $CFG, $DB, $SITE;

    // switch to new default theme in totara 2.6
    set_config('theme', 'standardtotararesponsive');

    $dbman = $DB->get_manager(); // loads ddl manager and xmldb classes
    $systemcontext = context_system::instance();
    // add coursetype and icon fields to course table

    $table = new xmldb_table('course');

    $field = new xmldb_field('coursetype');
    if (!$dbman->field_exists($table, $field)) {
        $field->set_attributes(XMLDB_TYPE_INTEGER, '4', null, null, null, null, null);
        $dbman->add_field($table, $field);
    }

    $field = new xmldb_field('icon');
    if (!$dbman->field_exists($table, $field)) {
        $field->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $dbman->add_field($table, $field);
    }

    // Create totara roles.
    $staffmanagerrole    = create_role('', 'staffmanager', '', 'staffmanager');
    $assessorrole        = create_role('', 'assessor', '', 'assessor');
    $regionalmanagerrole = create_role('', 'regionalmanager', '');
    $regionaltrainerrole = create_role('', 'regionaltrainer', '');

    $newroles = array($staffmanagerrole, $assessorrole, $regionalmanagerrole, $regionaltrainerrole);

    foreach ($DB->get_records('role') as $role) {
        // Add allow* defaults related to all new roles.
        foreach (array('assign', 'override', 'switch') as $type) {
            $function = 'allow_'.$type;
            $allows = get_default_role_archetype_allows($type, $role->archetype);
            foreach ($allows as $allowid) {
                if (!in_array($allowid, $newroles) and !in_array($role->id, $newroles)) {
                    // Add only entries related to new roles!
                    continue;
                }
                $function($role->id, $allowid);
            }
        }

        if (in_array($role->id, $newroles)) {
            // Set context levels for all new roles.
            set_role_contextlevels($role->id, get_default_contextlevels($role->shortname));

            // Reset existing permissions for all new roles.
            $defaultcaps = get_default_capabilities($role->archetype);
            foreach($defaultcaps as $cap => $permission) {
                assign_capability($cap, $permission, $role->id, $systemcontext->id);
            }
        }
    }

    // Reset legacy custom roles names for standard roles,
    // we want to use the lang strings from now on.
    $resetnames = array('manager', 'coursecreator', 'editingteacher', 'teacher', 'student', 'guest', 'user');
    foreach ($resetnames as $shortname) {
        if ($old_role = $DB->get_record('role', array('shortname' => $shortname))) {
            $new_role = new stdClass();
            $new_role->id = $old_role->id;
            $new_role->name = '';
            $new_role->description = '';

            $DB->update_record('role', $new_role);
        }
    }

    // Extra capability tweaks for new roles without archetypes,
    // these cannot be reset to defaults because they do not have archetype.
    assign_capability('moodle/user:editownprofile', CAP_ALLOW, $regionalmanagerrole, $systemcontext->id);
    assign_capability('moodle/user:editownprofile', CAP_ALLOW, $regionaltrainerrole, $systemcontext->id);

    $systemcontext->mark_dirty();

    // Set up blocks.
    totara_reset_mymoodle_blocks();

    // Set up frontpage.
    set_config('frontpage', '');
    set_config('frontpageloggedin', '');

    // Turn completion on in Totara by default.
    require_once($CFG->dirroot . '/lib/completionlib.php');
    if(!completion_info::is_enabled_for_site()){
        set_config('totaracoreinstallation', 1);
        set_config('enablecompletion', 1);
        set_config('enablecompletion', 1, 'moodlecourse');
        set_config('completionstartonenrol', 1, 'moodlecourse');
    }
    // Add completionstartonenrol column to course table.
    $table = new xmldb_table('course');

    // Define field completionstartonenrol to be added to course.
    $field = new xmldb_field('completionstartonenrol', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');

    // Conditionally launch add field completionstartonenrol.
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    // Add RPL column to course_completions table
    $table = new xmldb_table('course_completions');

    // Define field rpl to be added to course_completions
    $field = new xmldb_field('rpl', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'reaggregate');

    // Conditionally launch add field rpl
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    // Define field rplgrade to be added to course_completions
    $field = new xmldb_field('rplgrade', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, null, 'rpl');

    // Conditionally launch add field rpl
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    // Add RPL column to course_completion_crit_compl table
    $table = new xmldb_table('course_completion_crit_compl');

    // Define field rpl to be added to course_completion_crit_compl
    $field = new xmldb_field('rpl', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'unenroled');

    // Conditionally launch add field rpl
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    // Define fields status and renewalstatus to be added to course_completions.
    $table = new xmldb_table('course_completions');
    $field = new xmldb_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');

    // Conditionally launch add field status.
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    $field = new xmldb_field('renewalstatus', XMLDB_TYPE_INTEGER, '2', null, null, null, '0');

    // Conditionally launch add field renewalstatus.
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    rebuild_course_cache($SITE->id, true);

    // readd totara specific course completion changes for anyone
    // upgrading from moodle 2.2.2+
    require_once($CFG->dirroot . '/totara/core/db/utils.php');
    totara_readd_course_completion_changes();

    // remove any references to "complete on unenrolment" critiera type
    // these could exist in an upgrade from moodle 2.2 but the criteria
    // was never implemented and is no longer in totara
    $DB->delete_records('course_completion_criteria', array('criteriatype' => 3));

    //disable autoupdate notifications from Moodle
    set_config('disableupdatenotifications', '1');
    set_config('disableupdateautodeploy', '1');
    set_config('updateautodeploy', false);
    set_config('updateautocheck', false);
    set_config('updatenotifybuilds', false);
    set_config('updateminmaturity', MATURITY_STABLE);

    // Disable editing execpaths by default for security.
    set_config('preventexecpath', '1');
    // Then provide default values to prevent them appearing on the upgradesettings page.
    set_config('geoipfile', $CFG->dataroot . 'geoip/GeoLiteCity.dat');
    set_config('location', '', 'enrol_flatfile');
    set_config('filter_tex_pathlatex', '/usr/bin/latex');
    set_config('filter_tex_pathdvips', '/usr/bin/dvips');
    set_config('filter_tex_pathconvert', '/usr/bin/convert');
    set_config('pathtodu', '');
    set_config('pathtoclam', '');
    set_config('aspellpath', '');
    set_config('pathtodot', '');
    set_config('quarantinedir', '');
    set_config('backup_auto_destination', '', 'backup');
    set_config('gspath', '/usr/bin/gs', 'assignfeedback_editpdf');
    set_config('exporttofilesystempath', '', 'reportbuilder');
    set_config('pathlatex', '/usr/bin/latex', 'filter_tex');
    set_config('pathdvips', '/usr/bin/dvips', 'filter_tex');
    set_config('pathconvert', '/usr/bin/convert', 'filter_tex');
    set_config('pathmimetex', '', 'filter_tex');

    // Adding some totara upgrade code from lib/db/upgrade.php to
    // avoid conflicts every time we upgrade moodle.
    // This can be removed once we reach the verion of Moodle that
    // includes this functionality. E.g. 2.5 for badges, 2.6? for
    // course completion.

    // Add openbadges tables.

    // Define table 'badge' to be created
    $table = new xmldb_table('badge');

    // Adding fields to table 'badge'
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
    $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'id');
    $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null, 'name');
    $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'description');
    $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated');
    $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'timemodified');
    $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'usercreated');
    $table->add_field('issuername', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'usermodified');
    $table->add_field('issuerurl', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'issuername');
    $table->add_field('issuercontact', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'issuerurl');
    $table->add_field('expiredate', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'issuercontact');
    $table->add_field('expireperiod', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'expiredate');
    $table->add_field('type', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'expireperiod');
    $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'type');
    $table->add_field('message', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'courseid');
    $table->add_field('messagesubject', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'message');
    $table->add_field('attachment', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'messagesubject');
    $table->add_field('notification', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'attachment');
    $table->add_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'notification');
    $table->add_field('nextcron', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'status');

    // Adding keys to table 'badge'
    $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
    $table->add_key('fk_courseid', XMLDB_KEY_FOREIGN, array('courseid'), 'course', array('id'));
    $table->add_key('fk_usermodified', XMLDB_KEY_FOREIGN, array('usermodified'), 'user', array('id'));
    $table->add_key('fk_usercreated', XMLDB_KEY_FOREIGN, array('usercreated'), 'user', array('id'));

    // Adding indexes to table 'badge'
    $table->add_index('type', XMLDB_INDEX_NOTUNIQUE, array('type'));

    // Set the comment for the table 'badge'.
    $table->setComment('Defines badge');

    // Conditionally launch create table for 'badge'
    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
    }

    // Define table 'badge_criteria' to be created
    $table = new xmldb_table('badge_criteria');

    // Adding fields to table 'badge_criteria'
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
    $table->add_field('badgeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'id');
    $table->add_field('criteriatype', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'badgeid');
    $table->add_field('method', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'criteriatype');

    // Adding keys to table 'badge_criteria'
    $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
    $table->add_key('fk_badgeid', XMLDB_KEY_FOREIGN, array('badgeid'), 'badge', array('id'));

    // Adding indexes to table 'badge_criteria'
    $table->add_index('criteriatype', XMLDB_INDEX_NOTUNIQUE, array('criteriatype'));
    $table->add_index('badgecriteriatype', XMLDB_INDEX_UNIQUE, array('badgeid', 'criteriatype'));

    // Set the comment for the table 'badge_criteria'.
    $table->setComment('Defines criteria for issuing badges');

    // Conditionally launch create table for 'badge_criteria'
    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
    }

    // Define table 'badge_criteria_param' to be created
    $table = new xmldb_table('badge_criteria_param');

    // Adding fields to table 'badge_criteria_param'
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
    $table->add_field('critid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
    $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'critid');
    $table->add_field('value', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'name');

    // Adding keys to table 'badge_criteria_param'
    $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
    $table->add_key('fk_critid', XMLDB_KEY_FOREIGN, array('critid'), 'badge_criteria', array('id'));

    // Set the comment for the table 'badge_criteria_param'.
    $table->setComment('Defines parameters for badges criteria');

    // Conditionally launch create table for 'badge_criteria_param'
    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
    }

    // Define table 'badge_issued' to be created
    $table = new xmldb_table('badge_issued');

    // Adding fields to table 'badge_issued'
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
    $table->add_field('badgeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'id');
    $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'badgeid');
    $table->add_field('uniquehash', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'userid');
    $table->add_field('dateissued', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'uniquehash');
    $table->add_field('dateexpire', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'dateissued');
    $table->add_field('visible', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'dateexpire');
    $table->add_field('issuernotified', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'visible');

    // Adding keys to table 'badge_issued'
    $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
    $table->add_key('fk_badgeid', XMLDB_KEY_FOREIGN, array('badgeid'), 'badge', array('id'));
    $table->add_key('fk_userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));

    // Adding indexes to table 'badge_issued'
    $table->add_index('badgeuser', XMLDB_INDEX_UNIQUE, array('badgeid', 'userid'));

    // Set the comment for the table 'badge_issued'.
    $table->setComment('Defines issued badges');

    // Conditionally launch create table for 'badge_issued'
    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
    }

    // Define table 'badge_criteria_met' to be created
    $table = new xmldb_table('badge_criteria_met');

    // Adding fields to table 'badge_criteria_met'
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
    $table->add_field('issuedid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'id');
    $table->add_field('critid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'issuedid');
    $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'critid');
    $table->add_field('datemet', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'userid');

    // Adding keys to table 'badge_criteria_met'
    $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
    $table->add_key('fk_critid', XMLDB_KEY_FOREIGN, array('critid'), 'badge_criteria', array('id'));
    $table->add_key('fk_userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
    $table->add_key('fk_issuedid', XMLDB_KEY_FOREIGN, array('issuedid'), 'badge_issued', array('id'));

    // Set the comment for the table 'badge_criteria_met'.
    $table->setComment('Defines criteria that were met for an issued badge');

    // Conditionally launch create table for 'badge_criteria_met'
    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
    }

    // Define table 'badge_manual_award' to be created
    $table = new xmldb_table('badge_manual_award');

    // Adding fields to table 'badge_manual_award'
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
    $table->add_field('badgeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
    $table->add_field('recipientid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'badgeid');
    $table->add_field('issuerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'recipientid');
    $table->add_field('issuerrole', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'issuerid');
    $table->add_field('datemet', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'issuerrole');

    // Adding keys to table 'badge_manual_award'
    $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
    $table->add_key('fk_badgeid', XMLDB_KEY_FOREIGN, array('badgeid'), 'badge', array('id'));
    $table->add_key('fk_recipientid', XMLDB_KEY_FOREIGN, array('recipientid'), 'user', array('id'));
    $table->add_key('fk_issuerid', XMLDB_KEY_FOREIGN, array('issuerid'), 'user', array('id'));
    $table->add_key('fk_issuerrole', XMLDB_KEY_FOREIGN, array('issuerrole'), 'role', array('id'));

    // Set the comment for the table 'badge_manual_award'.
    $table->setComment('Track manual award criteria for badges');

    // Conditionally launch create table for 'badge_manual_award'
    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
    }

    // Define table 'badge_backpack' to be created
    $table = new xmldb_table('badge_backpack');

    // Adding fields to table 'badge_backpack'
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
    $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'id');
    $table->add_field('email', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, 'userid');
    $table->add_field('backpackurl', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'email');
    $table->add_field('backpackuid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'backpackurl');
    $table->add_field('backpackgid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'backpackuid');
    $table->add_field('autosync', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'backpackgid');
    $table->add_field('password', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'autosync');

    // Adding keys to table 'badge_backpack'
    $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
    $table->add_key('fk_userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));

    // Set the comment for the table 'badge_backpack'.
    $table->setComment('Defines settings for connecting external backpack');

    // Conditionally launch create table for 'badge_backpack'
    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
    }

    // Create a new 'badge_external' table first.
    // Define table 'badge_external' to be created.
    $table = new xmldb_table('badge_external');

    // Adding fields to table 'badge_external'.
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
    $table->add_field('backpackid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
    $table->add_field('collectionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'backpackid');

    // Adding keys to table 'badge_external'.
    $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
    $table->add_key('fk_backpackid', XMLDB_KEY_FOREIGN, array('backpackid'), 'badge_backpack', array('id'));

    // Set the comment for the table 'badge_external'.
    $table->setComment('Setting for external badges display');

    // Conditionally launch create table for 'badge_external'.
    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
    }

    // Define field backpackgid to be dropped from 'badge_backpack'.
    $table = new xmldb_table('badge_backpack');
    $field = new xmldb_field('backpackgid');

    if ($dbman->field_exists($table, $field)) {
        // Perform user data migration.
        $usercollections = $DB->get_records('badge_backpack');
        foreach ($usercollections as $usercollection) {
            $collection = new stdClass();
            $collection->backpackid = $usercollection->id;
            $collection->collectionid = $usercollection->backpackgid;
            $DB->insert_record('badge_external', $collection);
        }

        // Launch drop field backpackgid.
        $dbman->drop_field($table, $field);
    }

    // Create missing badgeid foreign key on badge_manual_award.
    $table = new xmldb_table('badge_manual_award');
    $key = new xmldb_key('fk_badgeid', XMLDB_KEY_FOREIGN, array('id'), 'badge', array('id'));

    $dbman->drop_key($table, $key);
    $key->set_attributes(XMLDB_KEY_FOREIGN, array('badgeid'), 'badge', array('id'));
    $dbman->add_key($table, $key);

    // Drop unused badge image field.
    $table = new xmldb_table('badge');
    $field = new xmldb_field('image', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'description');

    // Conditionally launch drop field eventtype.
    if ($dbman->field_exists($table, $field)) {
        $dbman->drop_field($table, $field);
    }

    // Define field completionprogressonview to be added to course.
    $table = new xmldb_table('course');
    $field = new xmldb_field('completionprogressonview', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0, 'enablecompletion');

    // Conditionally launch add field completionprogressonview.
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    $field = new xmldb_field('audiencevisible', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, 2);

    // Conditionally launch add field audiencevisible to course table.
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    // Define field invalidatecache to be added to course_completions.
    $table = new xmldb_table('course_completions');
    $field = new xmldb_field('invalidatecache', XMLDB_TYPE_INTEGER, '1', null, null, null, '0');

    // Conditionally launch add field invalidatecache.
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    // Backporting MDL-41914 to add new webservice core_user_add_user_device.
    $table = new xmldb_table('user_devices');

    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
    $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'id');
    $table->add_field('appid', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null, 'userid');
    $table->add_field('name', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null, 'appid');
    $table->add_field('model', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null, 'name');
    $table->add_field('platform', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null, 'model');
    $table->add_field('version', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null, 'platform');
    $table->add_field('pushid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'version');
    $table->add_field('uuid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'pushid');
    $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'uuid');
    $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'timecreated');

    $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
    $table->add_key('pushid-userid', XMLDB_KEY_UNIQUE, array('pushid', 'userid'));
    $table->add_key('pushid-platform', XMLDB_KEY_UNIQUE, array('pushid', 'platform'));
    $table->add_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));

    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
    }

    // Add timecompleted for module completion.
    $table = new xmldb_table('course_modules_completion');
    $field = new xmldb_field('timecompleted', XMLDB_TYPE_INTEGER, '10');

    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    return true;
}
