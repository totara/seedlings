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
 * Block for displaying user-defined links
 *
 * @package   totara
 * @author    Eugene Venter <eugene@catalyst.net.nz>
 * @author    Alastair Munro <alastair.munro@totaralms.com>
 */
class block_totara_quicklinks extends block_base {

    function init() {
        $this->title = get_string('pluginname', 'block_totara_quicklinks');
        $this->version = 2010111000;
    }

    function preferred_width() {
        return 210;
    }

    function specialization() {
        // After the block has been loaded we customize the block's title display
        if (!empty($this->config) && !empty($this->config->title)) {
            // There is a customized block title, display it
            $this->title = format_string($this->config->title);
        } else {
            // No customized block title, use localized remote news feed string
            $this->title = get_string('quicklinks', 'block_totara_quicklinks');
        }
    }

    function get_content() {
        global $DB, $OUTPUT;

        // Check if content is cached
        if($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text   = '';
        $this->content->footer = '';

        if (empty($this->instance)) {
            // We're being asked for content without an associated instance
            return $this->content;
        }

        if (empty($this->instance->pinned)) {
            $context = context_block::instance($this->instance->id);
        } else {
            $context = context_system::instance(); // pinned blocks do not have own context
        }

        // Get links to display
        $links = $DB->get_records('block_quicklinks', array('block_instance_id' => $this->instance->id), 'displaypos', 'id, url, title');
        if (count($links) == 0) {
            $this->content->text = get_string('noquicklinks', 'block_totara_quicklinks');
            return $this->content;
        }

        $linksoutput = html_writer::start_tag('ul');
        $odd = true;
        foreach ($links as $link) {
            $classes = "";
            if ($odd) {
                $classes = 'odd';
            }

            $linksoutput .= html_writer::start_tag('li', array('class' => $classes));
            $linksoutput .= html_writer::link(format_string($link->url), format_string($link->title));
            $linksoutput .= html_writer::end_tag('li');
            $odd = !$odd;
        }

        $linksoutput .= html_writer::end_tag('ul');
        $this->content->text = $linksoutput;
        return $this->content;
    }

    function instance_allow_multiple() {
        return true;
    }

    function instance_create() {
        global $CFG, $USER, $DB;

        // Add some default quicklinks
        $links = array(
            get_string('home',    'block_totara_quicklinks')    => "{$CFG->wwwroot}/index.php",
            get_string('reports', 'block_totara_quicklinks')    => "{$CFG->wwwroot}/my/reports.php",
            get_string('courses', 'block_totara_quicklinks')    => "{$CFG->wwwroot}/course/find.php"
        );

        $poscount = 0;
        foreach ($links as $title=>$url) {
            $link = new stdClass;
            $link->block_instance_id = $this->instance->id;
            $link->title = $title;
            $link->url = $url;
            $link->displaypos = $poscount;
            $link->userid = $USER->id;
            $DB->insert_record('block_quicklinks', $link);
            $poscount++;
        }

        return true;

    }

    function instance_delete() {
        global $DB;
        // Do some additional cleanup
        $DB->delete_records('block_quicklinks', array('block_instance_id' => $this->instance->id));
        return true;
    }
}

?>
