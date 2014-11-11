<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Renderer for outputting the demo course format.
 *
 * @package format_demo
 * @copyright 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.3
 */


defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/course/format/renderer.php');

/**
 * Basic renderer for demo format.
 *
 * @copyright 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_demo_renderer extends format_section_renderer_base {

    /**
     * Generate the starting container html for a list of sections
     * @return string HTML to output.
     */
    protected function start_section_list() {
        return html_writer::start_tag('ul', array('class' => 'demosections'));
    }

    /**
     * Generate the closing container html for a list of sections
     * @return string HTML to output.
     */
    protected function end_section_list() {
        return html_writer::end_tag('ul');
    }

    /**
     * Generate the title for this section page
     * @return string the page title
     */
    protected function page_title() {
        return get_string('demooutline', 'format_demo');
    }

    /**
    * Generate the section title
    *
    * @param stdClass $section The course_section entry from DB
    * @param stdClass $course The course entry from DB
    * @return string HTML to output.
    */
    public function section_title($section, $course) {
        if ($section->section == 0) {
            $title = get_section_name($course, $section);
            $url = course_get_url($course, $section->section, array('navigation' => true));
            if ($url) {
                $title = html_writer::link($url, $title);
            }
            return $title;
        } else {
            return '';
        }
    }

    /**
     * Generate the edit controls of a section
     *
     * @param stdClass $course The course entry from DB
     * @param stdClass $section The course_section entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return array of links with edit controls
     */
    protected function section_edit_controls($course, $section, $onsectionpage = false) {
        global $PAGE;

        if (!$PAGE->user_is_editing()) {
            return array();
        }

        $coursecontext = context_course::instance($course->id);

        if ($onsectionpage) {
            $url = course_get_url($course, $section->section);
        } else {
            $url = course_get_url($course);
        }
        $url->param('sesskey', sesskey());

        $controls = array();
        if (has_capability('moodle/course:setcurrentsection', $coursecontext)) {
            if ($course->marker == $section->section) {  // Show the "light globe" on/off.
                $url->param('marker', 0);
                $controls[] = html_writer::link($url,
                                    html_writer::empty_tag('img', array('src' => $this->output->pix_url('i/marked'),
                                        'class' => 'icon ', 'alt' => get_string('markedthistopic'))),
                                    array('title' => get_string('markedthistopic'), 'class' => 'editing_highlight'));
            } else {
                $url->param('marker', $section->section);
                $controls[] = html_writer::link($url,
                                html_writer::empty_tag('img', array('src' => $this->output->pix_url('i/marker'),
                                    'class' => 'icon', 'alt' => get_string('markthistopic'))),
                                array('title' => get_string('markthistopic'), 'class' => 'editing_highlight'));
            }
        }

        return array_merge($controls, parent::section_edit_controls($course, $section, $onsectionpage));
    }

    protected function section_header($section, $course, $onsectionpage, $sectionreturn=null) {
        global $PAGE;

        $output = '';
        $currenttext = '';
        $sectionstyle = '';

        if (!$section->visible) {
            $sectionstyle = ' hidden';
        } else if (course_get_format($course)->is_section_current($section)) {
             $sectionstyle = ' current';
        }

        $output.= html_writer::start_tag('li', array('id' => 'section-'.$section->section,
                      'class' => 'section main clearfix'.$sectionstyle)
                  );

        $leftcontent = $this->section_left_content($section, $course, $onsectionpage);
        $output .= html_writer::tag('div', $leftcontent, array('class' => 'left side'));

        $rightcontent = $this->section_right_content($section, $course, $onsectionpage);
        $output .= html_writer::tag('div', $rightcontent, array('class' => 'right side'));
        $output .= html_writer::start_tag('div', array('class' => 'content'));

        $output.= $this->output->heading($this->section_title($section, $course), 3, 'sectionname');

        $output .= html_writer::start_tag('div', array('class' => 'summary'));
        $output .= $this->format_summary_text($section);

        $context = context_course::instance($course->id);
        if ($PAGE->user_is_editing() && has_capability('moodle/course:update', $context)) {
            $url = new moodle_url('/course/editsection.php', array('id'=>$section->id, 'sr'=>$sectionreturn));
            $output .= html_writer::link($url, html_writer::empty_tag('img', array('src' => $this->output->pix_url('t/edit'),
                                            'class' => 'iconsmall edit', 'alt' => get_string('edit'))),
                                            array('title' => get_string('editsummary'))
                                        );
        }
        $output .= html_writer::end_tag('div');
        $output .= $this->section_availability_message($section, has_capability('moodle/course:viewhiddensections', $context));
        return $output;
   }

}
