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
 * @author Jon Sharp <jonathans@catalyst-eu.net>
 * @package totara
 * @subpackage certification
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

/**
 * Standard HTML output renderer for totara_core module
 */
class totara_certification_renderer extends plugin_renderer_base {

    /**
     * Print a description of a program, suitable for browsing in a list.
     * (This is the counterpart to print_course in /course/lib.php)
     *
     * @param object $data all info required by renderer
     * @return HTML fragment
     */
    public function print_certification($data) {

        if ($data->accessible) {
            if ($data->visible) {
                $linkcss = '';
            } else {
                $linkcss = 'dimmed';
            }
        } else {
            if ($data->visible) {
                $linkcss = 'inaccessible';
            } else {
                $linkcss = 'dimmed inaccessible';
            }
        }

        $out = '';
        $out .= html_writer::start_tag('div', array('class' => 'coursebox programbox clearfix'));
        $out .= html_writer::start_tag('div', array('class' => 'info'));
        $out .= html_writer::start_tag('div', array('class' => 'name'));
        $out .= html_writer::empty_tag('img', array('src' => totara_get_icon($data->pid, TOTARA_ICON_TYPE_PROGRAM),
            'class' => 'course_icon', 'alt' => ''));
        $url = new moodle_url('/totara/program/view.php', array('id' => $data->progid));
        $attributes = array('title' => get_string('viewprogram', 'totara_program'), 'class' => $linkcss);
        $linktext = highlight($data->highlightterms, format_string($data->fullname));
        $out .= html_writer::link($url, $linktext, $attributes);
        $out .= html_writer::end_tag('div'); // At /name .
        $out .= html_writer::end_tag('div'); // At /info .

        $out .= html_writer::start_tag('div', array('class' => 'learningcomptype'));
        $out .= html_writer::start_tag('div', array('class' => 'name'));
        $out .= $data->learningcomptypestr;
        $out .= html_writer::end_tag('div');
        $out .= html_writer::end_tag('div');

        $out .= html_writer::start_tag('div', array('class' => 'summary'));
        $options = new stdClass();
        $options->noclean = true;
        $options->para = false;
        $options->context = context_program::instance($data->progid);
        $out .= highlight($data->highlightterms, format_text($data->summary, FORMAT_MOODLE, $options));
        $out .= html_writer::end_tag('div');
        $out .= html_writer::end_tag('div');
        return $out;
    }


    /**
     * Generates HTML for a cancel button which is displayed on
     * management edit screens
     *
     * @param str $url
     * @return str HTML fragment
     */
    public function get_cancel_button($params=null, $url='') {
        if (empty($url)) {
            $url = "/totara/program/edit.php"; // Back to program edit.
        }
        $link = new moodle_url($url, $params);
        $output = $this->output->action_link($link, get_string('cancelcertificationmanagement', 'totara_certification'),
                         null, array('id' => 'cancelcertificationedits'));
        $output .= html_writer::empty_tag('br');
        return $output;
    }

}
