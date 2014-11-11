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
 * @author Jonathan Newman <jonathan.newman@catalyst.net.nz>
 * @author Ciaran Irvine <ciaran.irvine@totaralms.com>
 * @package totara
 * @subpackage totara_core
 */

/**
 * Local db upgrades for Totara Core
 */

require_once($CFG->dirroot.'/totara/core/db/utils.php');


/**
 * Local database upgrade script
 *
 * @param   integer $oldversion Current (pre-upgrade) local db version timestamp
 * @return  boolean $result
 */
function xmldb_totara_core_upgrade($oldversion) {
    global $CFG, $DB, $OUTPUT;

    $dbman = $DB->get_manager(); // loads ddl manager and xmldb classes

    if ($oldversion < 2012052802) {
        // add the archetype field to the staff manager role
        $sql = 'UPDATE {role} SET archetype = ? WHERE shortname = ?';
        $DB->execute($sql, array('staffmanager', 'staffmanager'));

        // rename the moodle 'manager' fullname to "Site Manager" to make it
        // distinct from the totara "Staff Manager"
        if ($managerroleid = $DB->get_field('role', 'id', array('shortname' => 'manager', 'name' => get_string('manager', 'role')))) {
            $todb = new stdClass();
            $todb->id = $managerroleid;
            $todb->name = get_string('sitemanager', 'totara_core');
            $DB->update_record('role', $todb);
        }

        totara_upgrade_mod_savepoint(true, 2012052802, 'totara_core');
    }

    if ($oldversion < 2012061200) {
        // Add RPL column to course_completions table
        $table = new xmldb_table('course_completions');

        // Define field rpl to be added to course_completions
        $field = new xmldb_field('rpl', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'reaggregate');

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

        totara_upgrade_mod_savepoint(true, 2012061200, 'totara_core');
    }

    /*
     * Move Totara 1.1 dashlets to Totara 2.x mymoodle
     */
    if ($oldversion < 2012062900) {
        // get the id of the default mylearning and myteam quicklinks block instances
        $quicklinks_defaultinstances = $DB->get_fieldset_sql("
            SELECT bi.id
            FROM {dashb_instance_dashlet} did
            INNER JOIN {dashb_instance} di ON did.dashb_instance_id = di.id
            INNER JOIN {dashb} d on d.id = di.dashb_id
            INNER JOIN {block_instances} bi on did.block_instance_id = bi.id
            WHERE di.userid = 0
                AND d.shortname IN ('mylearning', 'myteam')
                AND bi.blockname = 'totara_quicklinks'
        ");
        // first get all default quicklinks
        if (!empty($quicklinks_defaultinstances)) {
            list($insql, $inparams) = $DB->get_in_or_equal($quicklinks_defaultinstances);
            $alllinks = $DB->get_records_select('block_quicklinks', "block_instance_id $insql", $inparams, 'displaypos ASC');
        } else {
            $alllinks = array();
        }
        // now loop through and remove duplicates with same url and title
        $links = array();
        foreach ($alllinks as $l) {
            $key = $l->url . '-' . $l->title;
            $links[$key] = $l;
        }

        // Change default my_pages for My Moodle
        if ($mypageid = $DB->get_field_sql('SELECT id FROM {my_pages} WHERE userid IS null AND private = 1')) {

            $blockinstance = new stdClass;
            $blockinstance->parentcontextid = SYSCONTEXTID;
            $blockinstance->showinsubcontexts = 0;
            $blockinstance->pagetypepattern = 'my-index';
            $blockinstance->subpagepattern = $mypageid;
            $blockinstance->configdata = '';
            $blockinstance->defaultweight = 0;

            // List of Totara blocks for default pages
            $defaultblocks = array('totara_quicklinks', 'totara_tasks', 'totara_alerts', 'totara_stats');

            // Install new Totara blocks to default mymoodle page
            foreach ($defaultblocks as $block) {
                // put tasks and alerts in the middle, others on the side
                if ($block == 'totara_tasks' || $block == 'totara_alerts' || $block == 'totara_recent_learning') {
                    $blockinstance->defaultregion = 'content';
                } else {
                    $blockinstance->defaultregion = 'side-post';
                }
                $blockinstance->blockname = $block;
                $blockinstance->id = $DB->insert_record('block_instances', $blockinstance);

                // Add default links to each quicklinks instance
                if ($block == 'totara_quicklinks') {
                    // Add default content for quicklinks block
                    $pos = 0;
                    foreach ($links as $ql) {
                        $ql->userid = 0;
                        $ql->block_instance_id = $blockinstance->id;
                        $ql->displaypos = $pos;
                        $DB->update_record('block_quicklinks', $ql);
                        $pos++;
                    }
                }
            }

        }

        // delete old references in block_instances that refer to old default dashboard blocks
        $old_defaultinstance_ids = $DB->get_fieldset_sql("
            SELECT bi.id
            FROM {dashb_instance_dashlet} did
            INNER JOIN {dashb_instance} di ON did.dashb_instance_id = di.id
            INNER JOIN {dashb} d on d.id = di.dashb_id
            INNER JOIN {block_instances} bi on did.block_instance_id = bi.id
            WHERE di.userid = 0
        ");
        foreach ($old_defaultinstance_ids as $instanceid) {
            $DB->delete_records('block_instances', array('id' => $instanceid));
        }

        // delete the old default quicklink block instances to avoid more duplicates
        foreach ($quicklinks_defaultinstances as $instanceid) {
            $DB->delete_records('block_quicklinks', array('block_instance_id' => $instanceid));
        }

        // get the new default quicklinks, for user pages
        $defaultquicklinks = $DB->get_records('block_quicklinks', array('userid' => 0));

        // get the default page for mymoodle
        $systempage = $DB->get_record('my_pages', array('userid' => null, 'private' => 1));

        // get system context
        $systemcontext = context_system::instance();

        // get default block instances
        $blockinstances = $DB->get_records('block_instances', array('parentcontextid' => $systemcontext->id,
                    'pagetypepattern' => 'my-index',
                    'subpagepattern' => "$systempage->id"));

        // get all totara dashboard users (except deleted users)
        $sql = 'SELECT DISTINCT userid from {dashb_instance} dbi JOIN {user} u ON dbi.userid = u.id WHERE u.deleted = 0';
        $dashusers = $DB->get_records_sql($sql);

        // set up per-user mymoodle pages
        foreach ($dashusers as $user) {
            // Clone the default mymoodle page
            $page = clone($systempage);
            unset($page->id);
            $page->userid = $user->userid;

            // Add a mymoodle page for each dashboard user
            if (!($DB->record_exists('my_pages', array('userid' => $user->userid)))) {
                $page->id = $DB->insert_record('my_pages', $page);

                $usercontext = context_user::instance($user->userid);

                // Get dashboard block instances
                $sql = "SELECT bi.id,bi.blockname
                    FROM {dashb_instance_dashlet} did
                    INNER JOIN {block_instances} bi
                    ON did.block_instance_id = bi.id
                    INNER JOIN {dashb_instance} di
                    ON di.id = did.dashb_instance_id
                    WHERE di.userid = ?";

                $dashletinstances = $DB->get_records_sql($sql, array($user->userid));
                $userblocks = array();

                // Move per-user dashlets to mymoodle blocks
                foreach ($dashletinstances as $instance) {
                    $instance->parentcontextid = $usercontext->id;
                    $instance->subpagepattern =  $page->id;
                    $instance->pagetypepattern = 'my-index';

                    // put tasks and alerts in the middle, others on the side
                    if ($instance->blockname == 'totara_alerts' || $instance->blockname == 'totara_tasks' || $instance->blockname == 'totara_recent_learning') {
                        $instance->defaultregion = 'content';
                    } else {
                        $instance->defaultregion = 'side-post';
                    }

                    // check if user already has this block
                    if (!(in_array($instance->blockname, $userblocks))) {
                        // if not migrate it across
                        $DB->update_record('block_instances', $instance);
                    } else {
                        // delete any duplicates to avoid leaving stray records in block instance table
                        $DB->delete_records('block_instances', array('id' => $instance->id));
                        if ($instance->blockname == 'totara_quicklinks') {
                            $DB->delete_records('block_quicklinks', array('block_instance_id' => $instance->id));
                        }
                    }
                    $userblocks[] = $instance->blockname;
                }

                // Add default blocks to each users page.
                foreach ($blockinstances as $instance) {
                    // check if user already has this block
                    if (!(in_array($instance->blockname, $userblocks))) {
                        unset($instance->id);
                        $instance->parentcontextid = $usercontext->id;
                        $instance->subpagepattern = $page->id;
                        // put tasks and alerts in the middle, others on the side
                        if ($instance->blockname == 'totara_alerts' || $instance->blockname == 'totara_tasks' || $instance->blockname == 'totara_recent_learning') {
                            $instance->defaultregion = 'content';
                        } else {
                            $instance->defaultregion = 'side-post';
                        }
                        $instance->id = $DB->insert_record('block_instances', $instance);

                        // Add default links to each quicklinks instance
                        if ($instance->blockname == 'totara_quicklinks') {
                            // Add default content for quicklinks block
                            foreach ($defaultquicklinks as $ql) {
                                unset($ql->id);
                                $ql->block_instance_id = $instance->id;
                                $ql->userid = $user->userid;
                                $DB->insert_record('block_quicklinks', $ql);
                            }
                        }
                    }
                }
            }
        }

        // Clean up - delete the obsolete dashboard tables
        $dbman = $DB->get_manager();

        $tables = array('dashb', 'dashb_instance', 'dashb_instance_dashlet');
        foreach ($tables as $tablename) {
            $table = new xmldb_table($tablename);
            if ($dbman->table_exists($table)) {
                $dbman->drop_table($table);
            }
        }

        // delete old default dashboard blocks
        $DB->delete_records('block_instances', array('pagetypepattern' => 'totara_dashboard'));

        totara_upgrade_mod_savepoint(true, 2012062900, 'totara_core');
    }


    if ($oldversion < 2012080100) {
        // readd totara specific course completion changes for anyone
        // who has already upgraded from moodle 2.2.2+
        totara_readd_course_completion_changes();
        totara_upgrade_mod_savepoint(true, 2012080100, 'totara_core');
    }

    if ($oldversion < 2012080101) {
        // remove OAuth plugin
        // Google fusion export will use repository/gdrive integration instead
        uninstall_plugin('totara', 'oauth');
        totara_upgrade_mod_savepoint(true, 2012080101, 'totara_core');
    }

    if ($oldversion < 2012081300) {
        //turn off forceunique for any filepicker totara customfields
        $tables = array('course', 'pos_type', 'org_type', 'comp_type');
        foreach ($tables as $table) {
            $DB->execute("UPDATE {{$table}_info_field} SET forceunique = ? WHERE datatype = ?", array(0, 'file'));
        }
        totara_upgrade_mod_savepoint(true, 2012081300, 'totara_core');
    }

    if ($oldversion < 2012090500) {
        // backport of SCORM directview patch MDL-33755 from 2.3
        // we removed the directview column in upgrade_pre20 but we may have orphaned data that needs fixed
        $DB->execute("UPDATE {scorm} SET popup = ?, skipview = ? WHERE popup = ?", array(1, 2, 2));
        totara_upgrade_mod_savepoint(true, 2012090500, 'totara_core');
    }

    if ($oldversion < 2012102400) {
        //fix broken stats for course completions
        require_once($CFG->dirroot.'/completion/completion_completion.php');
        $completions = $DB->get_recordset('course_completions', array('status' => COMPLETION_STATUS_COMPLETE));
        foreach ($completions as $completion) {
            $data = array();
            $data['userid'] = $completion->userid;
            $data['eventtype'] = STATS_EVENT_COURSE_COMPLETE;
            $data['data2'] = $completion->course;
            if (!$DB->record_exists('block_totara_stats', $data)) {
                totara_stats_add_event($completion->timecompleted, $data['userid'], STATS_EVENT_COURSE_COMPLETE, '', $data['data2']);
            }
        }
        $completions->close();
        totara_upgrade_mod_savepoint(true, 2012102400, 'totara_core');
    }

    if ($oldversion < 2012121200) {
        // remove hardcoded names and descriptions for totara core roles
        $roles_to_fix = array('staffmanager', 'assessor', 'regionalmanager', 'regionaltrainer', 'editingtrainer', 'trainer', 'student');
        foreach ($roles_to_fix as $shortname) {
            if ($roleid = $DB->get_field('role', 'id', array('shortname' => $shortname))) {
                $todb = new stdClass();
                $todb->id = $roleid;
                $todb->name = '';
                $todb->description = '';
                $DB->update_record('role', $todb);
            }
        }
        totara_upgrade_mod_savepoint(true, 2012121200, 'totara_core');
    }

    if ($oldversion < 2013041000) {
        //fix the sort order for any legacy (1.0.x) custom fields
        //that are still ordered by now non-existent custom field categories

        $countsql = "SELECT COUNT(*) as count
                     FROM {course_info_field}
                     WHERE categoryid IS NOT NULL";
        $count = $DB->count_records_sql($countsql);

        if ($count != 0) {
            $sql = "SELECT id, sortorder, categoryid
                    FROM {course_info_field}
                    ORDER BY categoryid, sortorder";
            $neworder = $DB->get_records_sql($sql);
            $sortorder = 1;
            $transaction = $DB->start_delegated_transaction();

            foreach ($neworder as $item) {
                $item->sortorder = $sortorder++;
                $item->categoryid = null;
                $DB->update_record('course_info_field', $item);
            }

            $transaction->allow_commit();
        }

        totara_upgrade_mod_savepoint(true, 2013041000, 'totara_core');
    }

    if ($oldversion < 2013041500) {
        //need to get any currently-used languages installed as a langpack in moodledata/lang
        require_once($CFG->libdir.'/adminlib.php');
        require_once($CFG->libdir.'/filelib.php');
        require_once($CFG->libdir.'/componentlib.class.php');

        set_time_limit(0);
        $notice_ok = array();
        $notice_error = array();
        $installedlangs = array();
        $neededlangs = array();
        //get available and already-installed (via langimport tool) languages
        $installer = new lang_installer();
        if (!$availablelangs = $installer->get_remote_list_of_languages()) {
            $notice_error[] = get_string('cannotdownloadtotaralanguageupdatelist', 'totara_core');
        } else {
            if (!isset($CFG->langotherroot)) {
                $CFG->langotherroot = $CFG->dataroot.'/lang';
            }
            $langdirs = get_list_of_plugins('', '', $CFG->langotherroot);
            foreach ($langdirs as $lang) {
                if (strstr($lang, '_local') !== false) {
                    continue;
                }
                if (strstr($lang, '_utf8') !== false) {
                    continue;
                }
                $string = get_string_manager()->load_component_strings('langconfig', $lang);
                //if this installed lang is a properly configured language that also exists on the Totara lang site, add it to the update list
                if (!empty($string['thislanguage']) && in_array($lang, $availablelangs)) {
                    $neededlangs[] = $lang;
                }
                unset($string);
            }
            make_temp_directory('');
            make_upload_directory('lang');

            // install all used language packs to moodledata/lang
            $installer->set_queue($neededlangs);
            $results = $installer->run();
            $updated = false;    // any packs updated?
            foreach ($results as $langcode => $langstatus) {
                switch ($langstatus) {
                case lang_installer::RESULT_DOWNLOADERROR:
                    $a       = new stdClass();
                    $a->url  = $installer->lang_pack_url($langcode);
                    $a->dest = $CFG->dataroot.'/lang';
                    $notice_error[] = get_string('remotedownloaderror', 'error', $a);
                    break;
                case lang_installer::RESULT_INSTALLED:
                    $updated = true;
                    $notice_ok[] = get_string('langpackinstalled', 'tool_langimport', $langcode);
                    break;
                case lang_installer::RESULT_UPTODATE:
                    $notice_ok[] = get_string('langpackuptodate', 'tool_langimport', $langcode);
                    break;
                }
            }

           if ($updated) {
                $notice_ok[] = get_string('langupdatecomplete', 'tool_langimport');
            } else {
                $notice_ok[] = get_string('nolangupdateneeded', 'tool_langimport');
            }
        }
        unset($installer);
        get_string_manager()->reset_caches();
        //display notifications
        if (!empty($notice_ok)) {
            $info = implode(html_writer::empty_tag('br'), $notice_ok);
            echo $OUTPUT->notification($info, 'notifysuccess');
        }

        if (!empty($notice_error)) {
            $info = implode(html_writer::empty_tag('br'), $notice_error);
            echo $OUTPUT->notification($info, 'notifyproblem');
        }

        totara_upgrade_mod_savepoint(true, 2013041500, 'totara_core');
    }

    if ($oldversion < 2013042300) {
        //disable autoupdate notifications from Moodle
        set_config('disableupdatenotifications', '1');
        set_config('disableupdateautodeploy', '1');
        set_config('updateautodeploy', false);
        set_config('updateautocheck', false);
        set_config('updatenotifybuilds', false);
        set_config('updateminmaturity', MATURITY_STABLE);
        set_config('updatenotifybuilds', 0);
        totara_upgrade_mod_savepoint(true, 2013042300, 'totara_core');
    }

    if ($oldversion < 2013042600) {
        $systemcontext = context_system::instance();
        $roles = get_all_roles();
        foreach($roles as $id => $role) {
            switch ($role->shortname) {
                case 'assessor':
                    $DB->update_record('role', array('id' => $id, 'archetype' => 'assessor'));
                    assign_capability('moodle/user:editownprofile', CAP_ALLOW, $id, $systemcontext->id, true);
                    break;
                case 'regionalmanager':
                case 'regionaltrainer':
                    assign_capability('moodle/user:editownprofile', CAP_ALLOW, $id, $systemcontext->id, true);
                    break;
            }
        }

        // Add totara block instances to context
        $bisql = "SELECT id FROM {block_instances}
                  WHERE blockname IN ('totara_stats', 'totara_alerts', 'totara_tasks')";
        $totarablocks = $DB->get_records_sql($bisql);
        foreach ($totarablocks as $block) {
            context_block::instance($block->id);
        }

        totara_upgrade_mod_savepoint(true, 2013042600, 'totara_core');
    }

    if ($oldversion < 2013061800) {
        // Clean up course completion records for deleted roles
        $sql= "SELECT cc.id, cc.role, r.shortname FROM {course_completion_criteria} cc
      LEFT OUTER JOIN {role} r on cc.role=r.id
                WHERE cc.role IS NOT NULL
                  AND r.shortname IS NULL";
        $roles = $DB->get_records_sql($sql, array());
        foreach ($roles as $role) {
            $DB->delete_records('course_completion_criteria', array('role' => $role->role));
        }
        totara_upgrade_mod_savepoint(true, 2013061800, 'totara_core');
    }

    if ($oldversion < 2013070800) {
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

        // Main savepoint reached.
        totara_upgrade_mod_savepoint(true, 2013070800, 'totara_core');
    }

    if ($oldversion < 2013070801) {
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

        // Main savepoint reached.
        totara_upgrade_mod_savepoint(true, 2013070801, 'totara_core');
    }

    if ($oldversion < 2013070802) {
        // Create missing badgeid foreign key on badge_manual_award.
        $table = new xmldb_table('badge_manual_award');
        $key = new xmldb_key('fk_badgeid', XMLDB_KEY_FOREIGN, array('id'), 'badge', array('id'));

        $dbman->drop_key($table, $key);
        $key->set_attributes(XMLDB_KEY_FOREIGN, array('badgeid'), 'badge', array('id'));
        $dbman->add_key($table, $key);

        totara_upgrade_mod_savepoint(true, 2013070802, 'totara_core');
    }

    if ($oldversion < 2013070803) {
        // Drop unused badge image field.
        $table = new xmldb_table('badge');
        $field = new xmldb_field('image', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'description');

        // Conditionally launch drop field eventtype.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        totara_upgrade_mod_savepoint(true, 2013070803, 'totara_core');
    }

    // Add status column to course_completions table.
    if ($oldversion < 2013070804) {
        // Define field completionprogressonview to be added to course.
        $table = new xmldb_table('course');
        $field = new xmldb_field('completionprogressonview', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0, 'enablecompletion');

        // Conditionally launch add field completionprogressonview.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        totara_upgrade_mod_savepoint(true, 2013070804, 'totara_core');
    }

    // Add audiencevisible column to courses.
    if ($oldversion < 2013091500) {
        $table = new xmldb_table('course');
        $field = new xmldb_field('audiencevisible', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, 2);

        // Conditionally launch add field audiencevisible to course table.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        totara_upgrade_mod_savepoint(true, 2013091500, 'totara_core');
    }

    if ($oldversion < 2013092000) {
        // Define table temporary_manager to be created.
        $table = new xmldb_table('temporary_manager');

        // Adding fields to table temporary_manager.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('tempmanagerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('expirytime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table temporary_manager.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
        $table->add_key('tempmanagerid', XMLDB_KEY_FOREIGN, array('tempmanagerid'), 'user', array('id'));
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, array('usermodified'), 'user', array('id'));

        // Conditionally launch create table for temporary_manager.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Core savepoint reached.
        upgrade_plugin_savepoint(true, 2013092000, 'totara', 'core');
    }

    if ($oldversion < 2013092100) {
        // Add RPL and renewalstatus columns to course_completions table.
        $table = new xmldb_table('course_completions');

        // Define field rpl to be added to course_completions.
        $field = new xmldb_field('rplgrade', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, null, 'rpl');

        // Conditionally launch add field rpl.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('renewalstatus', XMLDB_TYPE_INTEGER, '2', null, null, null, '0', 'status');

        // Conditionally launch add field renewalstatus.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        totara_upgrade_mod_savepoint(true, 2013092100, 'totara_core');
    }

    // Backporting MDL-41914 to add new webservice core_user_add_user_device.
    if ($oldversion < 2013101100) {
        // Define table user_devices to be created.
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

        // Main savepoint reached.
        totara_upgrade_mod_savepoint(true, 2013101100, 'totara_core');
    }

    // Remove duplicate 1.1 legacy event handlers.
    if ($oldversion < 2013101800) {
        $handlers = array(
            'local_cohort#organisation_deleted',
            'local_cohort#organisation_updated',
            'local_cohort#position_deleted',
            'local_cohort#position_updated',
            'local_cohort#profilefield_deleted',
            'local_program#program_assigned',
            'local_program#program_completed',
            'local_program#program_courseset_completed',
            'local_program#program_unassigned',
            'local_program#user_firstaccess',
            'local#role_assigned',
            'local#user_deleted',
        );

        // Delete the outdated handlers.
        foreach ($handlers as $handler) {
            $hinfo = explode('#', $handler);
            $DB->delete_records('events_handlers', array('component' => $hinfo[0], 'eventname' => $hinfo[1]));
        }

        totara_upgrade_mod_savepoint(true, 2013101800, 'totara_core');
    }

    if ($oldversion < 2013102300) {
        // Define field invalidatecache to be added to course_completions.
        $table = new xmldb_table('course_completions');
        $field = new xmldb_field('invalidatecache', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'status');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        totara_upgrade_mod_savepoint(true, 2013102300, 'totara_core');
    }

    if ($oldversion < 2013102900) {
        // Add timecompleted for module completion.
        $table = new xmldb_table('course_modules_completion');
        $field = new xmldb_field('timecompleted', XMLDB_TYPE_INTEGER, '10');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        totara_upgrade_mod_savepoint(true, 2013102900, 'totara_core');
    }

    if ($oldversion < 2013120300) {
        // Add timecompleted for module completion.
        $table = new xmldb_table('oldpassword');
        $field = new xmldb_field('hash', XMLDB_TYPE_CHAR, '255');

        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }

        totara_upgrade_mod_savepoint(true, 2013120300, 'totara_core');
    }

    if ($oldversion < 2014010700) {
        set_config('enablegoals', '1');
        set_config('enableappraisals', '1');
        set_config('enablefeedback360', '1');
        set_config('enablelearningplans', '1');
        totara_upgrade_mod_savepoint(true, 2014010700, 'totara_core');
    }

    if ($oldversion < 2014030800) {
        $table = new xmldb_table('course_info_data_param');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
        $table->add_field('dataid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('value', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('dataid', XMLDB_KEY_FOREIGN, array('dataid'), 'course_info_data', array('id'));
        $table->add_index('value', null, array('value'));

        // Set the comment for the table 'course_info_data_param'.
        $table->setComment('Custom course fields data parameters');

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        totara_upgrade_mod_savepoint(true, 2014030800, 'totara_core');
    }

    if ($oldversion < 2014031901) {
        // Fix defaults on executable config settings disabled in 2.5.7.
        if (!get_config('core', 'geoipfile')) {set_config('geoipfile', $CFG->dataroot . 'geoip/GeoLiteCity.dat');}
        if (!get_config('enrol_flatfile', 'location')) {set_config('location', '', 'enrol_flatfile');}
        if (!get_config('core', 'filter_tex_pathlatex')) {set_config('filter_tex_pathlatex', ' /usr/bin/latex');}
        if (!get_config('core', 'filter_tex_pathdvips')) {set_config('filter_tex_pathdvips', ' /usr/bin/dvips');}
        if (!get_config('core', 'filter_tex_pathconvert')) {set_config('filter_tex_pathconvert', '/usr/bin/convert');}
        if (!get_config('core', 'pathtodu')) {set_config('pathtodu', '');}
        if (!get_config('core', 'pathtoclam')) {set_config('pathtoclam', '');}
        if (!get_config('core', 'aspellpath')) {set_config('aspellpath', '');}
        if (!get_config('core', 'pathtodot')) {set_config('pathtodot', '');}
        if (!get_config('core', 'quarantinedir')) {set_config('quarantinedir', '');}
        if (!get_config('backup', 'backup_auto_destination')) {set_config('backup_auto_destination', '', 'backup');}
        if (!get_config('assignfeedback_editpdf', 'gspath')) {set_config('gspath', '/usr/bin/gs', 'assignfeedback_editpdf');}
        if (!get_config('reportbuilder', 'exporttofilesystempath')) {set_config('exporttofilesystempath', '', 'reportbuilder');}
        totara_upgrade_mod_savepoint(true, 2014031901, 'totara_core');
    }

    if ($oldversion < 2014032000) {
        global $CFG;
        require_once($CFG->dirroot . '/totara/core/totara.php');

        // Set features as they were before (enable/disable).
        $featureslist = totara_advanced_features_list();
        foreach ($featureslist as $feature) {
            $cfgsetting = "enable{$feature}";
            if (!isset($CFG->$cfgsetting)) {
                set_config($cfgsetting, TOTARA_SHOWFEATURE);
            } else if ($CFG->$cfgsetting == 0) {
                set_config($cfgsetting, TOTARA_HIDEFEATURE);
            }
        }

        totara_upgrade_mod_savepoint(true, 2014032000, 'totara_core');
    }

    if ($oldversion < 2014041500) {
        // Fix incorrect timezone information for Indianapolis.
        $sql = "UPDATE {user} SET timezone = ? WHERE timezone = ?";
        $DB->execute($sql, array('America/Indiana/Indianapolis', 'America/Indianapolis'));
        $sql = "UPDATE {facetoface_sessions_dates} SET sessiontimezone = ? WHERE sessiontimezone = ?";
        $DB->execute($sql, array('America/Indiana/Indianapolis', 'America/Indianapolis'));
        totara_upgrade_mod_savepoint(true, 2014041500, 'totara_core');
    }

    if ($oldversion < 2014051200) {
        // Re-aggregate all course completion criteria due to T-12280. This may be slow for large sites.

        // Don't run again if this upgrade has occurred before.
        $hasrun = get_config('totara_core', 'completion_reaggregation_fix_has_run');
        if (empty($hasrun)) {

            $previousversion = get_config('totara_core', 'previous_version');
            if (empty($previousversion)) {
                $previousversionknown = false;
            } else {
                $previousversionknown = true;

                $affected25site =
                    (version_compare($previousversion, '2.5.10', '>=') &&
                    version_compare($previousversion, '2.5.13', '<'));

                $affected26site =
                    (version_compare($previousversion, '2.6.0', '>=') &&
                    version_compare($previousversion, '2.6.1', '<'));
            }

            // Only run if previous version was affected.
            // If the previous version isn't known it won't be affected as $CFG->previous_version
            // is set in all affected versions.
            if ($previousversionknown && ($affected25site || $affected26site)) {
                // Set time to unlimited as this could take a while.
                set_time_limit(0);

                $countsql = 'SELECT COUNT(crc.id) ';
                $selectsql = 'SELECT crc.* ';
                $fromsql = '
                    FROM
                        {course_completions} crc
                    INNER JOIN
                        {course} c
                     ON crc.course = c.id
                    WHERE
                        c.enablecompletion = 1
                ';

                $count = $DB->count_records_sql($countsql . $fromsql);

                if ($count) {
                    $pbar = new progress_bar('reaggregatecompletions', 500, true);
                    $rs = $DB->get_recordset_sql($selectsql . $fromsql);

                    $i = 0;
                    // Grab records for current user/course.
                    foreach ($rs as $record) {
                        $i++;
                        // Load completion object (without hitting db again).
                        $completion = new completion_completion((array) $record, false);

                        // Recalculate course's criteria.
                        completion_handle_criteria_recalc($completion->course, $completion->userid);

                        // Aggregate the criteria and complete if necessary.
                        $completion->aggregate();
                        $pbar->update($i, $count, "Reaggregating completion data - record $i/$count.");
                    }
                    $pbar->update($count, $count, "Reaggregating completion data - done!");

                    $rs->close();
                }

            }

            // Record that we've run this upgrade now.
            set_config('completion_reaggregation_fix_has_run', 1, 'totara_core');
        }

        totara_upgrade_mod_savepoint(true, 2014051200, 'totara_core');
    }

    if ($oldversion < 2014081200) {
        // Get course that have other courses as criteria for completion.
        $sqlaffectedusers = "SELECT cc.id
                             FROM {course_completions} cc
                             INNER JOIN {course_completion_criteria} ccc
                               ON ccc.criteriatype = ".COMPLETION_CRITERIA_TYPE_COURSE." AND ccc.courseinstance = cc.course
                             WHERE ccc.courseinstance IS NOT NULL
                               AND cc.reaggregate = 0
                               AND cc.timecompleted IS NULL";

        $completions = $DB->get_fieldset_sql($sqlaffectedusers);
        if ($completions) {
            list($insql, $inparams) = $DB->get_in_or_equal($completions);

            // Set reaggregate to 1 for courses that have dependencies or other courses so they can be evaluated again
            // when the cron for completion runs.
            $sql = "UPDATE
                        {course_completions}
                    SET
                        reaggregate = 1
                    WHERE
                        id $insql";
            $DB->execute($sql, $inparams);
        }

        totara_upgrade_mod_savepoint(true, 2014081200, 'totara_core');
    }

    if ($oldversion < 2014082700) {
        require_once(dirname(__FILE__) . '/upgradelib.php');
        totara_core_fix_old_upgraded_mssql();
        upgrade_plugin_savepoint(true, 2014082700, 'totara', 'core');
    }

    // This is Totara 2.7 upgrade line.

    if ($oldversion < 2014100700) {
        global $CFG;
        if (get_config('moodle', 'tempmanagerrestrictselection') !== false) {
            set_config('tempmanagerrestrictselection', (int) $CFG->tempmanagerrestrictselection);
        }
        totara_upgrade_mod_savepoint(true, 2014100700, 'totara_core');
    }

    if ($oldversion < 2014100701) {
        // Define field categoryid to be dropped from course_info_field.
        $table = new xmldb_table('course_info_field');
        $field = new xmldb_field('categoryid');

        // Conditionally launch drop field categoryid.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Core savepoint reached.
        totara_upgrade_mod_savepoint(true, 2014100701, 'totara_core');
    }

    if ($oldversion < 2014100800) {
        if (!get_config('filter_tex', 'pathmimetex')) {
            set_config('pathmimetex', '',  'filter_tex');
        }
        totara_upgrade_mod_savepoint(true, 2014100800, 'totara_core');
    }

    if ($oldversion < 2014100902) {
        // Removing the themes from core and old Totara.
        $themes = array('standard', 'standardold', 'clean', 'more', 'customtotara', 'kiwifruit',
                        'standardtotara', 'mymobiletotara', 'canvas');

        foreach ($themes as $theme) {
            if (file_exists("{$CFG->dirroot}/theme/{$theme}/config.php")) {
                // Do not alter reintroduced themes.
                continue;
            }

            $replacement = 'standardtotararesponsive';
            if (file_exists("{$CFG->dirroot}/theme/{$theme}responsive/config.php")) {
                $replacement = "{$theme}responsive";
            }

            // Replace the theme configs.
            $types = array('theme', 'thememobile', 'themelegacy', 'themetablet');
            foreach ($types as $type) {
                if (get_config('core', $type) === $theme) {
                    set_config($type, $replacement);
                    // TODO: there should be some attempt to migrate theme settings, it is tricky because all themes may be already configured.
                }
            }

            // Hacky emulation of plugin uninstallation.
            unset_all_config_for_plugin('theme_'.$theme);
        }

        totara_upgrade_mod_savepoint(true, 2014100902, 'totara_core');
    }

    if ($oldversion < 2014100903) {
        if (file_exists($CFG->dataroot.'/environment/environment.xml')) {
            // Totara cannot use Moodle environment files and there is no update mechanism.
            unlink($CFG->dataroot.'/environment/environment.xml');
        }
        totara_upgrade_mod_savepoint(true, 2014100903, 'totara_core');
    }

    if ($oldversion < 2014101400) {
        // Make sure the lockout is enabled before removing this setting.
        if (!empty($CFG->recaptchaloginform)) {
            if (empty($CFG->lockoutthreshold) or $CFG->lockoutthreshold < 20) {
                set_config('lockoutthreshold', 20);
            }
        }
        // Make sure password reset does not show any hints if recaptcha previously enabled.
        if (!empty($CFG->recaptchaforgotform)) {
            set_config('protectusernames', 1);
        }

        unset_config('recaptchaloginform');
        unset_config('recaptchaforgotform');

        totara_upgrade_mod_savepoint(true, 2014101400, 'totara_core');
    }

    if ($oldversion < 2014101401) {
        // Remove settings for cron watcher superseded by new cron tasks from upstream Moodle.
        unset_config('cron_max_time');
        unset_config('cron_max_time_mail_notify');
        unset_config('cron_max_time_kill');

        totara_upgrade_mod_savepoint(true, 2014101401, 'totara_core');
    }

    if ($oldversion < 2014101402) {
        // Disable filters temporarily.
        $filterall = (!empty($CFG->filterall)) ? $CFG->filterall : 0;
        $CFG->filterall = 0;
        // Remove the hardcoded defaultfor and defaultinfofor strings in question_categories
        $categories = $DB->get_recordset_select('question_categories', '', array());
        $todelete = array();
        foreach ($categories as $category) {
            if ($context = context::instance_by_id($category->contextid, IGNORE_MISSING)) {
                $name = $context->get_context_name(false);
                $category->name = $name;
                $category->info = $name;
                $DB->update_record('question_categories', $category);
            } else {
                // This can happen because when a quiz is deleted, it does not check and clean up entries in question_categories.
                $todelete[] = $category->id;
            }
        }
        if (!empty($todelete)) {
            $DB->delete_records_list('question_categories', 'id', $todelete);
        }
        // Re-enable filters if necessary.
        $CFG->filterall = $filterall;
        totara_upgrade_mod_savepoint(true, 2014101402, 'totara_core');
    }

    if ($oldversion < 2014102000) {

        $table = new xmldb_table('totara_navigation');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
        $table->add_field('parentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('title', XMLDB_TYPE_CHAR, '1024', null, XMLDB_NOTNULL, null, null);
        $table->add_field('url', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('classname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('depth', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('path', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('custom', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('customtitle', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('visibility', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('targetattr', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        $table->add_index('parentid', XMLDB_INDEX_NOTUNIQUE, array('parentid'));
        $table->add_index('sortorder', XMLDB_INDEX_NOTUNIQUE, array('sortorder'));

        $table->setComment('Totara navigation menu');

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        totara_upgrade_mod_savepoint(true, 2014102000, 'totara_core');
    }

    return true;
}
