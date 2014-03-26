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
 * @author Russell England <russell.england@totaralms.com>
 * @package totara
 * @subpackage plan
 */

/**
 * Display attachments to an evidence
 *
 * @global object $CFG
 * @global type $OUTPUT
 * @param int $userid
 * @param int $evidenceid
 * @return string
 */
function evidence_display_attachment($userid, $evidenceid) {
    global $CFG, $OUTPUT, $FILEPICKER_OPTIONS;

    if (!$filecontext = context_user::instance($userid)) {
        return '';
    }

    $out = '';

    $context = $FILEPICKER_OPTIONS['context'];

    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'totara_plan', 'attachment', $evidenceid, null, FALSE);

    if (!empty($files)) {
        foreach ($files as $file) {
            $filename = $file->get_filename();
            $fileurl = new moodle_url(
                    '/pluginfile.php/' .
                    $file->get_contextid() .
                    '/totara_plan/attachment' .
                    $file->get_filepath() .
                    $file->get_itemid() .
                    '/' .
                    $filename);

            $mimetype = $file->get_mimetype();
            $fileicon = html_writer::empty_tag('img', array('class' => 'icon',
                'src' => $OUTPUT->pix_url(file_mimetype_icon($mimetype)),
                'alt' => $mimetype));

            $out .= html_writer::tag('a', $fileicon . s($filename), array('href' => $fileurl));
            $out .= html_writer::empty_tag('br');
        }
    }

    return $out;
}

/**
 * Returns markup to display an individual evidence relation
 *
 * @global object $USER
 * @global object $DB
 * @global object $OUTPUT
 * @param int $evidenceid - dp_plan_evidence->id
 * @param bool $delete - display a delete link
 * @return string html markup
 */
function display_evidence_detail($evidenceid, $delete = false) {
    global $USER, $DB, $OUTPUT;

    $sql ="
        SELECT
            e.id,
            e.name,
            e.description,
            e.evidencetypeid,
            e.evidencelink,
            et.name as evidencetypename,
            e.userid,
            e.institution,
            e.datecompleted
        FROM {dp_plan_evidence} e
        LEFT JOIN {dp_evidence_type} et on e.evidencetypeid = et.id
        WHERE e.id = ?";

    if (!$item = $DB->get_record_sql($sql, array($evidenceid))) {
        return get_string('error:evidencenotfound', 'totara_plan');
    }

    if (!empty($item->userid)) {
        $usercontext = context_user::instance($item->userid);
    } else {
        $usercontext = null;
    }

    $out = '';

    $icon = 'evidence-regular';
    $img = $OUTPUT->pix_icon('/msgicons/' . $icon,
            format_string($item->name),
            'totara_core',
            array('class' => 'evidence-state-icon'));

    $out .= $OUTPUT->heading($img . $item->name, 4);

    if (($USER->id == $item->userid && has_capability('totara/plan:editownsiteevidence', $usercontext) ||
            $USER->id != $item->userid && has_capability('totara/plan:editsiteevidence', $usercontext)) && !$delete) {
        // Can edit
        $buttonlabel = get_string('editdetails', 'totara_plan');
        $editurl = new moodle_url('/totara/plan/record/evidence/edit.php',
                array('id' => $evidenceid, 'userid' => $item->userid));
        $out .= html_writer::tag('div', $OUTPUT->single_button($editurl, $buttonlabel, null),
                array('class' => 'add-linked-competency'));
    }

    if (!empty($item->description)) {
        $item->description = file_rewrite_pluginfile_urls($item->description, 'pluginfile.php', context_system::instance()->id, 'totara_plan', 'dp_plan_evidence', $item->id);
        $out .= html_writer::tag('p', get_string('description') . ' : ' . format_text($item->description, FORMAT_HTML));
    }
    if (!empty($item->evidencetypename)) {
        $out .=  html_writer::tag('p', get_string('evidencetype', 'totara_plan') . ' : ' . $item->evidencetypename);
    }
    if (!empty($item->institution)) {
        $out .=  html_writer::tag('p', get_string('evidenceinstitution', 'totara_plan') . ' : ' . $item->institution);
    }
    if (!empty($item->datecompleted)) {
        $out .=  html_writer::tag('p', get_string('evidencedatecompleted', 'totara_plan') . ' : ' . userdate($item->datecompleted, get_string('datepickerlongyearphpuserdate', 'totara_core')));
    }
    if (!empty($item->evidencelink)) {
        $evidencelink = $OUTPUT->action_link(new moodle_url($item->evidencelink), $item->evidencelink);
        $out .=  html_writer::tag('p', get_string('evidencelink', 'totara_plan') . ' : ' . $evidencelink);
    }

    $attachments = evidence_display_attachment($item->userid, $evidenceid);
    if (!empty($attachments)) {
        $out .= $OUTPUT->heading(get_string('attachment', 'totara_plan'), 4);
        $out .= html_writer::start_tag('div', array('class' => 'attachments'));
        $out .= $attachments;
        $out .= html_writer::end_tag('div');
    }
    return $out;
}

/**
 * Lists all components that are linked to the evidence id
 *
 * @global type $DB
 * @global type $OUTPUT
 * @param type $evidenceid Evidence ID to list items for
 * @return type string html output
 */
function list_evidence_in_use($evidenceid) {
    global $DB, $OUTPUT;

    $out = '';

    $sql = "
        SELECT er.id, dp.name AS planname, er.component, comp.fullname AS itemname
        FROM {dp_plan_evidence_relation} AS er
        JOIN {dp_plan} AS dp ON dp.id = er.planid
        JOIN {dp_plan_competency_assign} AS c ON c.id = er.itemid
        JOIN {comp} AS comp ON comp.id = c.competencyid
        WHERE er.component = 'competency'
        AND er.evidenceid = ?
        UNION
        SELECT er.id, dp.name AS planname, er.component, course.fullname AS itemname
        FROM {dp_plan_evidence_relation} AS er
        JOIN {dp_plan} AS dp ON dp.id = er.planid
        JOIN {dp_plan_course_assign} AS c ON c.id = er.itemid
        JOIN {course} AS course ON course.id = c.courseid
        WHERE er.component = 'course'
        AND er.evidenceid = ?
        UNION
        SELECT er.id, dp.name AS planname, er.component, c.fullname AS itemname
        FROM {dp_plan_evidence_relation} AS er
        JOIN {dp_plan} AS dp ON dp.id = er.planid
        JOIN {dp_plan_objective} AS c ON c.id = er.itemid
        WHERE er.component = 'objective'
        AND er.evidenceid = ?
        UNION
        SELECT er.id, dp.name AS planname, er.component, prog.fullname AS itemname
        FROM {dp_plan_evidence_relation} AS er
        JOIN {dp_plan} AS dp ON dp.id = er.planid
        JOIN {dp_plan_program_assign} AS c ON c.id = er.itemid
        JOIN {prog} AS prog ON prog.id = c.programid
        WHERE er.component = 'program'
        AND er.evidenceid = ?
        ORDER BY planname, component, itemname";
    if ($items = $DB->get_records_sql($sql, array($evidenceid, $evidenceid, $evidenceid, $evidenceid))) {
        $out .= $OUTPUT->heading(get_string('evidenceinuseby', 'totara_plan'), 4);

        $tableheaders = array(
            get_string('planname', 'totara_plan'),
            get_string('component', 'totara_plan'),
            get_string('name', 'totara_plan'),
        );

        $tablecolumns = array(
            'planname',
            'component',
            'itemname'
        );

        // Start output buffering to bypass echo statements in $table->add_data()
        ob_start();
        $table = new flexible_table('linkedevidencelist');
        $table->define_columns($tablecolumns);
        $table->define_headers($tableheaders);
        $url = new moodle_url('/totara/plan/record/evidence/index.php');
        $table->define_baseurl($url);
        $table->set_attribute('class', 'logtable generalbox dp-plan-evidence-items');
        $table->setup();

        foreach ($items as $item) {
            $row = array();
            $row[] = $item->planname;
            $row[] = get_string($item->component, 'totara_plan');
            $row[] = $item->itemname;
            $table->add_data($row);
        }

        // return instead of outputing table contents
        $table->finish_html();
        $out .= ob_get_contents();
        ob_end_clean();
    }
    return $out;

}
