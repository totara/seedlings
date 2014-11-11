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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

/*
 * Page for viewing, creating and deleting activity groups
 */

    require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
    require_once($CFG->libdir . '/adminlib.php');
    require_once($CFG->libdir . '/ddllib.php');
    require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');
    require_once($CFG->dirroot . '/totara/reportbuilder/groupslib.php');
    require_once($CFG->dirroot . '/totara/reportbuilder/groups_forms.php');

    define('REPORT_BUILDER_GROUPS_CONFIRM_DELETE', 1);
    define('REPORT_BUILDER_GROUPS_FAILED_DELETE', 2);
    define('REPORT_BUILDER_GROUPS_FAILED_CREATE_GROUP', 3);
    define('REPORT_BUILDER_GROUPS_NO_PREPROCESSOR', 4);
    define('REPORT_BUILDER_GROUPS_FAILED_INIT_TABLES', 5);
    define('REPORT_BUILDER_GROUPS_REPORTS_EXIST', 6);

    $id = optional_param('id', null, PARAM_INT); // id for delete group
    $d = optional_param('d', false, PARAM_BOOL); // delete group?
    $confirm = optional_param('confirm', false, PARAM_BOOL); // confirm delete

    $returnurl = $CFG->wwwroot . '/totara/reportbuilder/groups.php';

    admin_externalpage_setup('rbactivitygroups');

    $output = $PAGE->get_renderer('totara_reportbuilder');

    if ($d && $confirm) {
        // delete an existing group
        if (!confirm_sesskey()) {
            totara_set_notification(get_string('error:bad_sesskey', 'totara_reportbuilder'), $returnurl);
        }
        if (delete_group($id)) {
            totara_set_notification(get_string('groupdeleted', 'totara_reportbuilder'), $returnurl, array('class' => 'notifysuccess'));
        } else {
            totara_set_notification(get_string('error:groupnotdeleted', 'totara_reportbuilder'), $returnurl);
        }
    } else if ($d) {
        $likesql = $DB->sql_like('source', '?');
        $likeparam = '%' . $DB->sql_like_escape("grp_{$id}");
        $reports = $DB->get_records_select('report_builder', $likesql, array($likeparam));
        if ($reports) {
            // can't delete group when reports are using it
            totara_set_notification(get_string('error:grouphasreports', 'totara_reportbuilder'), $returnurl);
            die;
        } else {
            // prompt to delete
            echo $output->header();
            echo $output->heading(get_string('activitygroups', 'totara_reportbuilder'));
            echo $output->confirm(get_string('groupconfirmdelete', 'totara_reportbuilder'),
                new moodle_url('/totara/reportbuilder/groups.php',
                    array('id' => $id, 'd' => '1', 'confirm' => '1', 'sesskey' => $USER->sesskey)),
                $returnurl);

            echo $output->footer();
        }
        die;
    }

    // form definition
    $mform = new report_builder_new_group_form();

    // form results check
    if ($mform->is_cancelled()) {
        redirect($returnurl);
    }
    if ($fromform = $mform->get_data()) {

        if (empty($fromform->submitbutton)) {
            totara_set_notification(get_string('error:unknownbuttonclicked', 'totara_reportbuilder'), $returnurl);
        }

        $errorcode = 'error:groupnotcreated';
        if ($newid = create_group($fromform, $errorcode)) {
            redirect($CFG->wwwroot . '/totara/reportbuilder/groupsettings.php?id=' .
                $newid);
            die;
        } else {
            totara_set_notification(get_string($errorcode, 'totara_reportbuilder'), $returnurl);
            die;
        }

    }

    echo $output->header();

    echo $output->heading(get_string('activitygroups', 'totara_reportbuilder'));

    echo html_writer::tag('p', get_string('activitygroupdesc', 'totara_reportbuilder'));

    $feedbackmoduleid = $DB->get_field('modules', 'id', array('name' => 'feedback'));
    if ($feedbackmoduleid) {

        $position_sql = $DB->sql_position("'grp_'", 'source');
        $substr_sql = $DB->sql_substr('source', "{$position_sql} + 4");
        $likesql = $DB->sql_like('source', '?');
        $likeparam = '%' . $DB->sql_like_escape('_grp_').'%';

        $sql = "
            SELECT g.id, g.*, assign.numitems, reports.numreports,
            f.name AS feedbackname, f.id AS feedbackid,
            c.fullname AS coursename, c.id AS courseid,
            cm.id AS cmid, tag.name as tagname
            FROM {report_builder_group} g
            LEFT JOIN {feedback} f
            ON f.id = " . $DB->sql_cast_char2int('g.baseitem') . "
            LEFT JOIN {course} c ON f.course = c.id
            LEFT JOIN {course_modules} cm ON cm.course = c.id
            AND cm.instance = f.id AND cm.module = ?
            LEFT JOIN (
                SELECT groupid, COUNT(id) as numitems
                FROM {report_builder_group_assign}
                GROUP BY groupid
            ) assign ON assign.groupid = g.id
            LEFT JOIN (
                SELECT id, name
                FROM {tag}
                WHERE tagtype = ?
            ) tag ON g.assigntype = ? AND g.assignvalue = tag.id
            LEFT JOIN (
                SELECT $substr_sql as groupid,
                count(id) as numreports
                FROM {report_builder}
                WHERE $likesql
                GROUP BY $substr_sql
            ) reports ON " . $DB->sql_cast_char2int('reports.groupid') . " = g.id ORDER BY g.name";
        $params = array($feedbackmoduleid, 'official', 'tag', $likeparam);
        $groups = $DB->get_records_sql($sql, $params);
    } else {
        $groups = false;
    }

    echo $output->activity_groups_table($groups);

    $mform->display();
    echo $output->footer();



// page specific functions

/**
 * Deletes a group
 *
 * @param integer $id ID of the group to delete
 *
 * @return boolean True if successful
 */
function delete_group($id) {
    global $DB;
    if (!$id) {
        return false;
    }

    $preproc = $DB->get_field('report_builder_group', 'preproc', array('id' => $id));
    if (!$preproc) {
        return false;
    }
    $pp = reportbuilder::get_preproc_object($preproc, $id);
    if (!$pp) {
        return false;
    }
    // try to drop group's tables
    if (!$pp->drop_group_tables()) {
        return false;
    }

    // now get rid of any records about this group
    $transaction = $DB->start_delegated_transaction();

    // delete the group
    $DB->delete_records('report_builder_group', array('id' => $id));
    // delete the group assignments
    $DB->delete_records('report_builder_group_assign', array('groupid' => $id));
    // delete any tracking records
    $DB->delete_records('report_builder_preproc_track', array('groupid' => $id));

    $transaction->allow_commit();
    return true;
}

/**
 * Creates a group
 *
 * @param object $fromform Formslib data object to base group on
 * @param integer &$errorcode Error code to return on failure (passed by ref)
 *
 * @return mixed ID of new group if successful, or false
 */
function create_group($fromform, &$errorcode) {
    global $DB;

    // create new record here
    $todb = new stdClass();
    $todb->name = $fromform->name;
    $todb->baseitem = $fromform->baseitem;
    $todb->preproc = $fromform->preproc;
    $todb->assigntype = $fromform->assigntype;
    $todb->assignvalue = $fromform->assignvalue;

    $transaction = $DB->start_delegated_transaction();

    // first create the group
    $newid = $DB->insert_record('report_builder_group', $todb);
    if (!$newid) {
        $errorcode = 'error:groupnotcreated';
        return false;
    }
    // group's preprocessor must exist
    $pp = reportbuilder::get_preproc_object($fromform->preproc, $newid);
    if (!$pp) {
        $errorcode = 'error:groupnotcreatedpreproc';
        return false;
    }
    // initialize any tables required by the group's preprocessor
    if (!$pp->is_initialized()) {
        $status = $pp->initialize_group($fromform->baseitem);
        if (!$status) {
            $errorcode = 'error:groupnotcreatedinitfail';
            return false;
        }
    }
    // find any activities that use this tag and add them to the group
    // TODO should make use of transaction too but update_tag_grouping()
    // also uses transactions
    update_tag_grouping($newid);
    $transaction->allow_commit();

    return $newid;
}
