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
 * @author Brian Barnes <brian.barnes@totaralms.com>
 * @package totara
 * @subpackage blocks_totara_certifications
 */
class block_totara_certifications_renderer extends plugin_renderer_base {
    /**
     * Displays the certifications block.
     *
     * @param array $certifications the list of certification.
     *
     * @returns the rendered results.
     */
    public function display_certifications($certifications) {
        if (count($certifications) <= 0) {
            return get_string('nocertifications', 'block_totara_certifications');
        }

        $output = html_writer::tag('p', get_string('intro', 'block_totara_certifications'), array('class' => 'intro'));
        $output .= html_writer::start_tag('ul');
        $output .= html_writer::start_tag('li', array('class' => 'certification row-fluid'));
        $output .= html_writer::tag('div', html_writer::tag('h3', get_string('certification', 'totara_certification')), array('class' => 'span8 name'));
        $output .= html_writer::tag('div', html_writer::tag('h3', get_string('duedate', 'totara_program')), array('class' => 'span4 due'));
        $output .= html_writer::end_tag('li');

        foreach ($certifications as $certification) {
            $output .= $this->display_certification($certification);
        }
        $output .= html_writer::end_tag('ul');
        return $output;
    }

    /**
     * Displays a single certification result.
     *
     * @param $certification The certification to display
     *
     * @returns the rendered certification
     */
    public function display_certification($certification) {
        $output = html_writer::start_tag('li', array('class' => 'certification row-fluid'));
        $output .= html_writer::tag('div', $certification->description, array('class' => 'span8 name'));
        $output .= html_writer::tag('div', $certification->date, array('class' => 'span4 due ' . ($certification->due ? 'timedue' : 'timewindowopens')));
        $output .= html_writer::end_tag('li');
        return $output;
    }
}
