<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2009 Catalyst IT LTD
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
 * @author Francois Marier <francois@catalyst.net.nz>
 * @package blocks
 * @subpackage facetoface
 */

class block_facetoface extends block_base {
    function init() {
        $this->title = get_string('formaltitle', 'block_facetoface');
        $this->version = 2012071900;
    }

    function specialization() {
    }

    function applicable_formats() {
        return array('all' => true);
    }

    function get_content() {

        if ($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->footer = '';

        $timenow = time();
        $startyear  = strftime('%Y', $timenow);
        $startmonth = strftime('%m', $timenow);
        $startday   = strftime('%d', $timenow);

        $this->content->text = '';
        $this->content->text .= html_writer::start_tag('ul');
        $this->content->text .= html_writer::tag('li', html_writer::link(new moodle_url('/blocks/facetoface/mysignups.php'), get_string('mybookings', 'block_facetoface')));
        $this->content->text .= html_writer::tag('li', html_writer::link(new moodle_url('/blocks/facetoface/mysessions.php'), get_string('upcomingsessions', 'block_facetoface')));
        $this->content->text .= html_writer::tag('li', html_writer::link(new moodle_url('/blocks/facetoface/mysessions.php', array('startday' => $startday, 'startmonth' => $startmonth, 'startyear' => $startyear, 'endday' => 1, 'endmonth' => 1, 'endyear' => '2020')), get_string('allfuturesessions', 'block_facetoface')));

        $this->content->text .= html_writer::end_tag('ul');

        return $this->content;
    }
}
