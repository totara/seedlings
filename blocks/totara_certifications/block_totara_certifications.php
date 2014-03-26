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
 * @author Russell England <russell.england@catalyst-eu.net>
 * @package totara
 * @subpackage block_totara_certifications
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Certifications block
 *
 * Displays upcoming certifications
 */
class block_totara_certifications extends block_base {

    public function init() {
        $this->title   = get_string('pluginname', 'block_totara_certifications');
    }

    public function get_content() {
        global $USER, $DB;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';

        $sql = "SELECT p.id as pid, p.fullname, cfc.timewindowopens, cfc.certifpath
                FROM {prog} p
                JOIN {certif_completion} cfc ON (cfc.certifid = p.certifid AND cfc.userid = ?)
                WHERE p.visible = 1
                    AND (cfc.certifpath = ?
                         OR (cfc.certifpath = ? AND cfc.renewalstatus = ?))
                ORDER BY cfc.timewindowopens DESC";

        // As timewindowopens is 0 for CERTs they will come at top, in any order.

        $renewals = $DB->get_records_sql($sql, array($USER->id, CERTIFPATH_CERT, CERTIFPATH_RECERT, CERTIFRENEWALSTATUS_DUE));

        if (!$renewals) {
            $this->content->text = get_string('nocertifications', 'block_totara_certifications');
        } else {
            $intro = html_writer::tag('p', get_string('intro', 'block_totara_certifications'), array('class' => 'intro'));

            $table = new html_table();
            $table->attributes['class'] = 'certifications_block';

            foreach ($renewals as $renewal) {
                $url = new moodle_url('/totara/program/required.php', array('id' => $renewal->pid));
                $link = html_writer::link($url, $renewal->fullname, array('title' => $renewal->fullname));
                $cell1 = new html_table_cell($link);
                $cell1->attributes['class'] = 'certification';

                if ($renewal->certifpath == CERTIFPATH_CERT) {
                    $prog_completion = $DB->get_record('prog_completion',
                                    array('programid' => $renewal->pid, 'userid' => $USER->id, 'coursesetid' => 0));
                    if ($prog_completion) {
                        $duedatestr = (empty($prog_completion->timedue) || $prog_completion->timedue == COMPLETION_TIME_NOT_SET)
                            ? get_string('duedatenotset', 'totara_program')
                            : userdate($prog_completion->timedue, get_string('strftimedate', 'langconfig'));
                    } else {
                        $duedatestr =  get_string('duedatenotset', 'totara_program');
                    }

                    $cell2 = new html_table_cell($duedatestr);
                    $cell2->attributes['class'] = 'timedue';
                } else {
                    $cell2 = new html_table_cell(userdate($renewal->timewindowopens, get_string('strftimedate', 'langconfig')));
                    $cell2->attributes['class'] = 'timewindowopens';
                }

                $table->data[] = new html_table_row(array($cell1, $cell2));
            }
            $this->content->text = $intro . html_writer::table($table);

            // Display 'required' list, certifications only.
            $url = new moodle_url('/totara/program/required.php', array('userid' => $USER->id, 'filter' => 'certification'));
            $this->content->footer = html_writer::link($url, get_string('allmycertifications', 'block_totara_certifications'));
        }

        return $this->content;
    }
}
