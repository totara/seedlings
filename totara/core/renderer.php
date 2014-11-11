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
 * @author Ciaran Irvine <ciaran.irvine@totaralms.com>
 * @package totara
 * @subpackage totara_core
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

/**
* Standard HTML output renderer for totara_core module
*/
class totara_core_renderer extends plugin_renderer_base {

    /**
    * Displays a count of the number of active users in the last year
    *
    * @param integer $activeusers Number of active users in the last year
    * @return string HTML to output.
    */
    public function totara_print_active_users($activeusers) {
        $output = '';
        $output .= $this->output->box_start('generalbox adminwarning');
        $output .= get_string('numberofactiveusers', 'totara_core', $activeusers);
        $output .= $this->output->box_end();
        return $output;
    }

    /**
     * Displays a link to download error log
     *
     * @param object $latesterror Object containing information about the last site error
     *
     * @return string HTML to output.
     */
    public function totara_print_errorlog_link($latesterror) {
        $output = '';
        $output .= $this->output->box_start('generalbox adminwarning');
        $output .= get_string('lasterroroccuredat', 'totara_core', userdate($latesterror->timeoccured));
        $output .= $this->output->single_button(new moodle_url('/admin/index.php', array('geterrors' => 1)), get_string('downloaderrorlog', 'totara_core'), 'post');
        $output .= $this->output->box_end();
        return $output;
    }

    /**
     * Outputs a block containing totara copyright information
     *
     * @param string $totara_release A totara release version, for inclusion in the block
     *
     * @return string HTML to output.
     */
    public function totara_print_copyright($totara_release) {
        $output = '';
        $output .= $this->output->box_start('generalbox adminwarning totara-copyright');
        $text = get_string('totaralogo', 'totara_core');
        $icon = new pix_icon('logo', $text, 'totara_core',
            array('width' => 253, 'height' => 177, 'class' => 'totaralogo'));
        $url = new moodle_url('http://www.totaralms.com');
        $output .= $this->output->action_icon($url, $icon, null, array('target' => '_blank'));
        $output .= html_writer::empty_tag('br');
        $output .= html_writer::empty_tag('br');
        $text = get_string('version') . ' ' . $totara_release;
        $url = new moodle_url('http://www.totaralms.com');
        $attributes = array('href' => $url, 'target' => '_blank');
        $output .= html_writer::tag('a', $text, $attributes);
        $output .= html_writer::empty_tag('br');
        $output .= html_writer::empty_tag('br');
        $output .= get_string('totaracopyright', 'totara_core');
        $output .= $this->output->box_end();
        return $output;
    }

    /**
    * Returns markup for displaying a progress bar for a user's course progress
    *
    * Optionally with a link to the user's profile if they have the correct permissions
    *
    * @access  public
    * @param   $userid     int
    * @param   $courseid   int
    * @param   $status     int     COMPLETION_STATUS_ constant
    * @return  string html to display
    */
    public function display_course_progress_icon($userid, $courseid, $status) {
        global $COMPLETION_STATUS;

        if (!isset($status) || !array_key_exists($status, $COMPLETION_STATUS)) {
            return '';
        }
        $statusstring = $COMPLETION_STATUS[$status];
        $status = get_string($statusstring, 'completion');
        // Display progress bar
        $content = html_writer::start_tag('span', array('class'=>'coursecompletionstatus'));
        $content .= html_writer::start_tag('span', array('class'=>'completion-' . $statusstring, 'title' => $status));
        $content .= $status;
        $content .= html_writer::end_tag('span');
        $content .= html_writer::end_tag('span');
        // Check if user has permissions to see details
        if (completion_can_view_data($userid, $courseid)) {
            $url = new moodle_url("/blocks/completionstatus/details.php?course={$courseid}&user={$userid}");
            $attributes = array('href' => $url);
            $content = html_writer::tag('a', $content, $attributes);
        }
        return $content;
    }

    /**
    * print out the Totara My Team nav section
    * @return html_writer::table
    */
    public function print_my_team_nav($numteammembers) {
        if (empty($numteammembers) || $numteammembers == 0) {
            return '';
        }

        $text = get_string('viewmyteam','totara_core');
        $icon = new pix_icon('teammembers', $text, 'totara_core');
        $url = new moodle_url('/my/teammembers.php');
        $content = $this->output->action_icon($url, $icon);
        $content .= html_writer::link($url, $text);
        $content .= html_writer::tag('span', get_string('numberofstaff', 'totara_core', $numteammembers));
        return $content;
    }

    /**
    * print out the table of visible reports
    * @param array $reports array of report objects visible to this user
    * @param bool $showsettings if this user is an admin with editing turned on
    * @return html_writer::table
    */
    public function print_report_manager($reports, $canedit) {
        $output = '';

        if (count($reports) == 0) {
            return $output;
        }

        foreach ($reports as $report) {
            // Check url property is set.
            if (!isset($report->url)) {
                debugging(get_string('error:reporturlnotset', 'totara_reportbuilder', $report->fullname), DEBUG_DEVELOPER);
                continue;
            }
            // Show reports user has permission to view, that are not hidden.
            $output .= html_writer::start_tag('li');
            $cells = array();
            $text = format_string($report->fullname);
            $icon = html_writer::empty_tag(
                    'img',
                    array('src' => $this->output->pix_url('report_icon', 'totara_reportbuilder'),
                    'alt'=> $text)
            );

            $attributes = array('href' => $report->url);
            $output .= html_writer::tag('a', $icon . $text, $attributes);
            // if admin with edit mode on show settings button too
            if ($canedit) {
                $text = get_string('settings','totara_core');
                $icon = html_writer::empty_tag('img', array('src' => $this->output->pix_url('/t/edit'),
                                                'alt'=> $text));
                $url = new moodle_url('/totara/reportbuilder/general.php?id='.$report->id);
                $attributes = array('href' => $url);
                $output .= '&nbsp;' . html_writer::tag('a', $icon, $attributes);
            }

            $output .= html_writer::end_tag('li');
        }

        // If we've generated a list of report links, wrap it.
        if ($output) {
            $output = html_writer::start_tag('ul', array('class' => 'reportmanager')) . $output . html_writer::end_tag('ul');
        }

        return $output;
    }
    /**
    * Returns markup for displaying saved scheduled reports
    *
    * Optionally without the options column and add/delete form
    * Optionally with an additional sql WHERE clause
    */
    public function print_scheduled_reports($scheduledreports, $showoptions=true) {

        $table = new html_table();
        $table->id = 'scheduled_reports';
        $table->attributes['class'] = 'scheduled-reports generaltable';
        $headers = array();
        $headers[] = get_string('reportname', 'totara_reportbuilder');
        $headers[] = get_string('savedsearch', 'totara_reportbuilder');
        $headers[] = get_string('format', 'totara_reportbuilder');
        if (get_config('reportbuilder', 'exporttofilesystem') == 1) {
            $headers[] = get_string('exportfilesystemoptions', 'totara_reportbuilder');
        }
        $headers[] = get_string('schedule', 'totara_reportbuilder');
        if ($showoptions) {
            $headers[] = get_string('options', 'totara_core');
        }
        $table->head = $headers;

        foreach ($scheduledreports as $sched) {
            $cells = array();
            $cells[] = new html_table_cell($sched->fullname);
            $cells[] = new html_table_cell($sched->data);
            $cells[] = new html_table_cell($sched->format);
            if (get_config('reportbuilder', 'exporttofilesystem') == 1) {
                $cells[] = new html_table_cell($sched->exporttofilesystem);
            }
            $cells[] = new html_table_cell($sched->schedule);
            if ($showoptions) {
                $text = get_string('edit');
                $icon = html_writer::empty_tag('img', array('src' => $this->output->pix_url('/t/edit'),
                                                        'alt' => $text, 'class' =>'iconsmall'));
                $url = new moodle_url('/totara/reportbuilder/scheduled.php', array('id' => $sched->id));
                $attributes = array('href' => $url);
                $cellcontent = html_writer::tag('a', $icon, $attributes);
                $cellcontent .= ' ';
                $text = get_string('delete');
                $icon = html_writer::empty_tag('img', array('src' => $this->output->pix_url('/t/delete'),
                                                        'alt' => $text, 'class' =>'iconsmall'));
                $url = new moodle_url('/totara/reportbuilder/deletescheduled.php', array('id' => $sched->id));
                $attributes = array('href' => $url);
                $cellcontent .= html_writer::tag('a', $icon, $attributes);
                $cell = new html_table_cell($cellcontent);
                $cell->attributes['class'] = 'options';
                $cells[] = $cell;
            }
            $row = new html_table_row($cells);
            $table->data[] = $row;
        }

        return html_writer::table($table);
    }

    public function print_my_courses($displaycourses, $userid) {
        global $COMPLETION_STATUS; //required for $this->display_course_progress_icon

        if (count($displaycourses) > 0) {
            $table = new html_table();
            $table->attributes['class'] = 'centerblock fullwidth';
            //set up table headers
            $headers = array();
            $cell = new html_table_cell(get_string('course'));
            $cell->attributes['class'] = 'course';
            $headers[] = $cell;
            $cell = new html_table_cell(get_string('status'));
            $cell->attributes['class'] = 'status';
            $headers[] = $cell;
            $cell = new html_table_cell(get_string('enrolled', 'totara_core'));
            $cell->attributes['class'] = 'enroldate';
            $headers[] = $cell;
            $cell = new html_table_cell(get_string('started','totara_core'));
            $cell->attributes['class'] = 'startdate';
            $headers[] = $cell;
            $cell = new html_table_cell(get_string('completed','totara_core'));
            $cell->attributes['class'] = 'completeddate';
            $headers[] = $cell;
            $table->head = $headers;
            foreach ($displaycourses as $course) {
                $cells = array();
                // Display deleted courses as unknown
                if ($course->name != '') {
                    $url = new moodle_url("/course/view.php?id={$course->course}");
                    $attributes = array('href' => $url, 'title' => $course->name);
                    $cellcontent = html_writer::tag('a', $course->name, $attributes);
                } else {
                    $cellcontent = get_string('deletedcourse', 'completion');
                }
                $cell = new html_table_cell($cellcontent);
                $cell->attributes['class'] = 'course';
                $cells[] = $cell;

                $completion = $this->display_course_progress_icon($userid, $course->course, $course->status);
                $cell = new html_table_cell($completion);
                $cell->attributes['class'] = 'status';
                $cells[] = $cell;
                $cell = new html_table_cell($course->enroldate);
                $cell->attributes['class'] = 'enroldate';
                $cells[] = $cell;
                $cell = new html_table_cell($course->starteddate);
                $cell->attributes['class'] = 'startdate';
                $cells[] = $cell;
                $cell = new html_table_cell($course->completeddate);
                $cell->attributes['class'] = 'completeddate';
                $cells[] = $cell;
                $row = new html_table_row($cells);
                $table->data[] = $row;
            }
            $content = html_writer::table($table);
            $content .= html_writer::start_tag('div', array('class' => 'allmycourses'));
            $url = new moodle_url('/totara/plan/record/courses.php?userid='.$userid);
            $attributes = array('href' => $url);
            $content .= html_writer::tag('a', get_string('allmycourses','totara_core'), $attributes);
            $content .= html_writer::end_tag('div');
        } else {
            $content = html_writer::start_tag('span', array('class' => 'noenrollments'));
            $content .= get_string('notenrolled','totara_core');
            $content .= html_writer::end_tag('span');
        }

        $output = html_writer::start_tag('div', array('class' => 'mycourses block'));
        $output .= html_writer::start_tag('div', array('class' => 'header'));
        $output .= html_writer::start_tag('div', array('class' => 'title'));
        $output .= html_writer::start_tag('h2');
        $output .= get_string('mycoursecompletions','totara_core');
        $output .= html_writer::end_tag('h2');
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');
        $output .= html_writer::start_tag('div', array('class' => 'content'));
        $output .= $content;
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');
        return $output;
    }

    /**
    * Render a set of toolbars (either top or bottom)
    *
    * @param string $position 'top' or 'bottom'
    * @param int $numcolumns
    * @param array $toolbar array of left and right arrays
    *              eg. $toolbar[0]['left'] = <first row left content>
    *                  $toolbar[0]['right'] = <first row right content>
    *                  $toolbar[1]['left'] = <second row left content>
    * @return boolean True if the toolbar was successfully rendered
    */
    public function print_toolbars($position='top', $numcolumns, $toolbar) {

        ksort($toolbar);
        $count = 1;
        $totalcount = count($toolbar);
        foreach ($toolbar as $index => $row) {
            // don't render empty toolbars
            // if you want to render one, add an empty content string to the toolbar
            if (empty($row['left']) && empty($row['right'])) {
                continue;
            }
            $trclass = "toolbar-{$position}";
            if ($count == 1) {
                $trclass .= ' first';
            }
            if ($count == $totalcount) {
                $trclass .= ' last';
            }
            echo html_writer::start_tag('tr', array('class' => $trclass));
            echo html_writer::start_tag('td', array('class' => 'toolbar', 'colspan' => $numcolumns));

            if (!empty($row['left'])) {
                echo html_writer::start_tag('div', array('class' => 'toolbar-left-table'));
                foreach ($row['left'] as $item) {
                    echo html_writer::tag('div', $item, array('class' => 'toolbar-cell'));
                }
                echo html_writer::end_tag('div');
            }

            if (!empty($row['right'])) {
                echo html_writer::start_tag('div', array('class' => 'toolbar-right-table'));
                foreach (array_reverse($row['right']) as $item) {
                    echo html_writer::tag('div', $item, array('class' => 'toolbar-cell'));
                }
                echo html_writer::end_tag('div');
            }
            echo html_writer::end_tag('td');
            echo html_writer::end_tag('tr');
            $count++;
        }
    }

    /**
    * Generate markup for search box
    */
    public function print_totara_search($action, $hiddenfields = null, $placeholder = '', $value = '', $formid = null, $inputid = null) {

        $attr = array(
            'action' => $action,
            'method' => 'get',
        );
        if (isset($formid)) {
            $attr['id'] = $formid;
        }
        $output = html_writer::start_tag('form', $attr);
        $output .= html_writer::start_tag('fieldset', array('class' => 'coursesearchbox invisiblefieldset'));
        if (isset($hiddenfields)) {
            foreach ($hiddenfields as $fname => $fvalue) {
                $attr = array(
                    'type' => 'hidden',
                    'name' => $fname,
                    'value' => $fvalue
                );
                $output .= html_writer::empty_tag('input', $attr);
            }
        }
        $attr = array(
            'type' => 'text',
            'class' => 'search-box',
            'name' => 'search',
            'placeholder' => $placeholder,
            'alt' => $placeholder,
        );
        if (strlen($value) != 0) {
            $attr['value'] = $value;
        }
        if (isset($inputid)) {
            $attr['id'] = $inputid;
        }
        $output .= html_writer::empty_tag('input', $attr);
        $output .= html_writer::empty_tag('input', array('type'=>'submit', 'value'=>get_string('go')));
        $output .= html_writer::end_tag('fieldset');
        $output .= html_writer::end_tag('form');
        return $output;
    }

    /**
     * Generate markup for totara menu
     */
    public function print_totara_menu($menudata, $parent=null, $selected_items=array()) {
        global $PAGE;
        $PAGE->requires->jquery();
        static $menuinited = false;
        if (!$menuinited) {
            $PAGE->requires->yui_module('moodle-totara_core-totaramenu', 'M.coremenu.setfocus.init');
            $menuinited = true;
        }

        $output = '';

        // Gets selected items, only done first time
        if (!$selected_items && $PAGE->totara_menu_selected) {
            $relationships = array();
            foreach ($menudata as $item) {
                $relationships[$item->name] = array($item->name);
                if ($item->parent) {
                    $relationships[$item->name][] = $item->parent;
                    if (!empty($relationships[$item->parent])) {
                        $relationships[$item->name] = array_merge($relationships[$item->name], $relationships[$item->parent]);
                    } elseif (!isset($relationships[$item->parent])) {
                        throw new coding_exception('Totara menu definition is incorrect');
                    }
                }
            }

            if (array_key_exists($PAGE->totara_menu_selected, $relationships)) {
                $selected_items = $relationships[$PAGE->totara_menu_selected];
            }
        }

        $currentlevel = array();
        foreach ($menudata as $menuitem) {
            if ($menuitem->parent == $parent) {
                $currentlevel[] = $menuitem;
            }
        }

        $numitems = count($currentlevel);

        $count = 0;
        if ($numitems > 0) {
            // Print out Structure
            $output .= html_writer::start_tag('ul');
            foreach ($currentlevel as $menuitem) {
                $class = 'menu-' . $menuitem->name;
                if ($count == 0) {
                    $class .= ' first';
                }

                if ($count == $numitems - 1) {
                    $class .= ' last';
                }

                if (in_array($menuitem->name, $selected_items)) {
                    $class .= ' selected';
                }

                $output .= html_writer::start_tag('li', array('class' => $class));
                $url = new moodle_url($menuitem->url);

                $output .= $this->output->action_link($url, $menuitem->linktext, null, array('target' => $menuitem->target));

                $output .= $this->print_totara_menu($menudata, $menuitem->name, $selected_items);
                $output .= html_writer::end_tag('li');

                $count++;
            }

            $output .= html_writer::end_tag('ul');
        }
        return $output;
    }

    /**
     * Displaying notices at top of page
     */
    public function print_totara_notifications() {
        $output = '';
        // Display notifications set with totara_set_notification()
        $notices = totara_get_notifications();
        foreach ($notices as $notice) {
            if (isset($notice['class'])) {
                $output .= $this->output->notification($notice['message'], $notice['class']);
            } else {
                $output .= $this->output->notification($notice['message']);
            }
        }
        return $output;
    }


    /**
     * Displays relevant progress bar
     * @param $percent int a percentage value (0-100)
     * @param $size string large, medium...
     * @param $showlabel boolean show completion text label
     * @param $tooltip string required tooltip text
     * @return $out html string
     */
    public function print_totara_progressbar($percent, $size='medium', $showlabel=false, $tooltip='DEFAULTTOOLTIP') {
        $percent = round($percent);

        if ($percent < 0 || $percent > 100) {
            return 'progress bar error- invalid value...';
        }

        // Add more sizes if as neccessary :)!
        switch ($size) {
        case 'large' :
            $bar_foreground = 'progressbar-large';
            $pixelvalue = ($percent / 100) * 121;
            $pixeloffset = round($pixelvalue - 120);
            $class = 'totara_progress_bar_large';
            break;
        case 'medium' :
        default :
            $bar_foreground = 'progressbar-medium';
            $pixelvalue = ($percent / 100) * 61;
            $pixeloffset = round($pixelvalue - 60);
            $class = 'totara_progress_bar_medium';
            break;
        }

        if ($tooltip == 'DEFAULTTOOLTIP') {
            $tooltip = get_string('xpercent', 'totara_core', $percent);
        }

        $out = '';

        $out .= $this->pix_icon($bar_foreground, $tooltip, 'totara_core', array('title' => $tooltip, 'style' => 'background-position: ' . $pixeloffset . 'px 0px;', 'class' => $class));
        if ($showlabel) {
            $out .= ' ' . get_string('xpercentcomplete', 'totara_core', $percent) . html_writer::empty_tag('br');
        }

        return $out;
    }

    /**
     * Renders a Totara-style HTML comment template to be used by the comments engine
     *
     * @return string Totara-style HTML comment template
     */
    public function comment_template() {
        $template = html_writer::tag('div', '___picture___', array('class' => 'comment-userpicture'));
        $template .= html_writer::start_tag('div', array('class' => 'comment-content'));
        $template .= html_writer::tag('span', '___name___', array('class' => 'comment-user-name'));
        $template .= '___content___';
        $template .= html_writer::tag('div', '___time___', array('class' => 'comment-datetime'));
        $template .= html_writer::end_tag('div');

        return $template;
    }
    /**
     * Print list of icons
     */
    public function print_icons_list($type = 'course') {
        global $CFG;

        $fs = get_file_storage();
        $files = $fs->get_area_files(context_system::instance()->id, 'totara_core', $type, 0, 'itemid', false);

        $out = html_writer::start_tag('ol', array('id' => 'icon-selectable'));
        // Custom icons.
        foreach ($files as $file) {
            $itemid = $file->get_itemid();
            $filename = $file->get_filename();
            $url = moodle_url::make_pluginfile_url($file->get_contextid(), 'totara_core',
                $file->get_filearea(), $itemid, $file->get_filepath(), $filename, true);
            $out .= html_writer::start_tag('li', array('id' => $file->get_pathnamehash(), 'iconname' => $filename));
            $out .= html_writer::empty_tag('img', array('src' => $url, 'class' => 'course_icon', 'alt' => ''));
            $out .= html_writer::end_tag('li');
        }
        // Totara icons.
        $path = $CFG->dirroot . '/totara/core/pix/' . $type . 'icons';
        foreach (scandir($path) as $icon) {
            if ($icon == '.' || $icon == '..') {
                continue;
            }
            $iconid = str_replace('.png', '', $icon);
            $replace = array('.png' => '', '_' => ' ', '-' => ' ');
            $iconname = ucwords(strtr($icon, $replace));
            $out .= html_writer::start_tag('li', array('id' => $iconid));
            $out .= $this->output->pix_icon('/' . $type . 'icons/' . $iconid, $iconname, 'totara_core',
                    array('class' => 'course-icon'));
            $out .= html_writer::end_tag('li');
        }
        $out .= html_writer::end_tag('ol');

        return $out;
    }

     /**
     * Render an appropriate message if registration is not complete.
     * @return string HTML to output.
     */
    public function is_registered() {
        global $CFG;

        if (!isset($CFG->registrationenabled)) {
            // Default is true
            set_config('registrationenabled', '1');
        }
        if (empty($CFG->registrationenabled)) {
            $message = get_string('registrationisdisabled', 'admin', $CFG->wwwroot . '/admin/register.php');
        } else if (empty($CFG->registered)) {
            $message = get_string('sitehasntregistered', 'admin', $CFG->wwwroot . '/admin/cron.php');
            $message = $message . '&nbsp;' . $this->help_icon('cron', 'admin');
        } else if ($CFG->registered < time() - 60 * 60 * 24 * 31) {
            $message = get_string('registrationoutofdate', 'admin');
        } else {
            $message = get_string('registrationisenabled', 'admin');
        }

        return $this->box($message, 'generalbox adminwarning');
    }

    /**
     * Render Totara information on user profile.
     *
     * @param $userid ID of a user.
     * @return string HTML to output.
     */
    public function print_totara_user_profile($userid) {
        global $USER, $CFG;

        $currentuser = ($userid == $USER->id);
        $usercontext = context_user::instance($userid);
        // Display hierarchy information.
        profile_display_hierarchy_fields($userid);
        $canviewROL = has_capability('totara/core:viewrecordoflearning', $usercontext);
        // Record of learning.
        if ($currentuser || totara_is_manager($userid) || $canviewROL) {
            $strrol = get_string('recordoflearning', 'totara_core');
            $urlrol = new moodle_url('/totara/plan/record/index.php', array('userid' => $userid));
            echo html_writer::tag('dt', $strrol);
            echo html_writer::tag('dd', html_writer::link($urlrol, $strrol));
        }

        // Learning plans.
        if (totara_feature_visible('learningplans') && dp_can_view_users_plans($userid)) {
            $strplans = get_string('learningplans', 'totara_plan');
            $urlplans = new moodle_url('/totara/plan/index.php', array('userid' => $userid));
            echo html_writer::tag('dt', $strplans);
            echo html_writer::tag('dd', html_writer::link($urlplans, $strplans));
        }
    }

    /**
     * Get a rule description.
     *
     * @param int $ruleid The rule's id.
     * @param $ruledefinition
     * @param int $ruleparamid Param id of the rule.
     * @return string Rule description of the rule.
     */
    public function get_rule_description($ruleid, $ruledefinition, $ruleparamid) {
        $ruledefinition->sqlhandler->fetch($ruleid);

        $ruledefinition->ui->setParamValues($ruledefinition->sqlhandler->paramvalues);

        return $ruledefinition->ui->getRuleDescription($ruleparamid, false);
    }

    /**
     * Render text broken rules in a HTML table.
     *
     * @return string $output HTML to output.
     */
    public function show_text_broken_rules($brokenrules = null) {
        $output = '';
        if (is_null($brokenrules)) {
            $brokenrules = totara_get_text_broken_rules();
        }

        if (!empty($brokenrules)) {
            $content = array();
            $warning = get_string('cohortbugneedfixing', 'totara_cohort');
            $output .= $this->container($warning, 'notifynotice');
            $table = new html_table();

            // Avoid duplicate rules. Display draft rules which contain the most recent changes.
            $brokenrules = array_filter($brokenrules, function ($objtofind) {
                return $objtofind->activecollectionid != $objtofind->rulecollectionid;
            });

            foreach ($brokenrules as $ruleparam) {
                $rule = cohort_rules_get_rule_definition($ruleparam->ruletype, $ruleparam->rulename);
                if (get_class($rule->ui) === 'cohort_rule_ui_text') {
                    $description = $this->get_rule_description($ruleparam->ruleid, $rule, $ruleparam->id);
                    $index = $ruleparam->cohortid . ',' . $ruleparam->cohortname;
                    if (!isset($content[$index])) {
                        $content[$index] = '';
                    }
                    $content[$index] .= html_writer::tag('div', $description);
                }
            }

            $table->head = array(get_string('cohorts', 'totara_cohort'), get_string('rules', 'totara_cohort'));
            foreach ($content as $key => $value) {
                list($id, $name) = explode(',', $key);
                $cohortlink = html_writer::link(new moodle_url('/totara/cohort/rules.php', array('id' => $id)), $name,
                    array('target' => '_blank'));
                $cells = array(new html_table_cell($cohortlink), new html_table_cell($value));
                $table->data[] = new html_table_row($cells);
            }
            $output .= $this->container(html_writer::table($table));
        }

        return $output;
    }

}
