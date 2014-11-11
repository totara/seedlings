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
 * @package totara
 * @subpackage program
 */
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('lib.php');

$id = required_param('id', PARAM_INT);
// Delete confirmation hash
$delete = optional_param('delete', '', PARAM_ALPHANUM);
$category = optional_param('category', '', PARAM_INT);

if (!$program = new program($id)) {
    print_error('error:programid', 'totara_program');
}

if (!has_capability('totara/program:deleteprogram', $program->get_context())) {
    print_error('error:nopermissions', 'local_program');
}

// Check if programs or certifications are enabled.
if ($program->certifid) {
    check_certification_enabled();
} else {
    check_program_enabled();
}

admin_externalpage_setup('programmgmt', '', array('id' => $id, 'delete' => $delete), $CFG->wwwroot.'/totara/program/delete.php');

$returnurl = "{$CFG->wwwroot}/totara/program/edit.php?id={$program->id}";
$deleteurl = "{$CFG->wwwroot}/totara/program/delete.php?id={$program->id}&amp;sesskey={$USER->sesskey}&amp;category={$category}&amp;delete=".md5($program->timemodified);

if (!$delete) {
    echo $OUTPUT->header();
    $strdelete = get_string('checkprogramdelete', 'totara_program');
    $strdelete .= html_writer::empty_tag('br') . html_writer::empty_tag('br') . format_string($program->fullname);
    $sql = "SELECT COUNT(DISTINCT pc.userid)
        FROM {user} AS u
        JOIN {prog_completion} AS pc ON u.id = pc.userid
        JOIN {prog_user_assignment} AS pua ON pua.programid = pc.programid AND pua.userid = pc.userid
        WHERE pc.programid = ?
        AND pc.coursesetid = ?
        AND pc.status = ?";
    $incomplete_program_learners = $DB->count_records_sql($sql, array($program->id, 0, STATUS_PROGRAM_INCOMPLETE));

    if ($incomplete_program_learners && $incomplete_program_learners > 0) {
        $strdelete .= html_writer::empty_tag('br') . html_writer::empty_tag('br') . get_string('xlearnerscurrentlyenrolled', 'totara_program', $incomplete_program_learners);
    }

    echo $OUTPUT->confirm($strdelete, $deleteurl, $returnurl);

    echo $OUTPUT->footer();
    exit;
}

if ($delete != md5($program->timemodified)) {
    print_error('error:badcheckvariable', 'totara_program');
}

if (!confirm_sesskey()) {
    print_error('confirmsesskeybad', 'error');
}


$transaction = $DB->start_delegated_transaction();
if ($program->delete()) {
    if (prog_fix_program_sortorder($program->category)) {
        $transaction->allow_commit();
    } else {
        throw new Exception(get_string('error:failfixprogsortorder', 'totara_program'));
    }
    $viewtype = ($program->certifid ? 'certification' : 'program');
    $notification_url = "{$CFG->wwwroot}/totara/program/index.php?categoryid={$category}&amp;viewtype={$viewtype}";
    totara_set_notification(get_string('programdeletesuccess', 'totara_program', $program->fullname), $notification_url, array('class' => 'notifysuccess'));
}
