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
 * @subpackage theme
 */

/**
 * Overriding core rendering functions for kiwifruitresponsive
 */
class theme_kiwifruitresponsive_core_renderer extends theme_standardtotararesponsive_core_renderer {
    public function kiwifruit_header() {
        global $OUTPUT, $PAGE, $CFG, $SITE;
        $output = '';
        $output .= html_writer::start_tag('header');
        $output .= html_writer::tag('div', $OUTPUT->login_info(), array('id' => 'login-info'));

        $output .= html_writer::start_tag('div', array('id' => 'main-menu'));

        // Small responsive button.
        $output .= $this->responsive_button();

        // Find the logo.
        if (!empty($PAGE->theme->settings->frontpagelogo)) {
            $logourl = $PAGE->theme->setting_file_url('frontpagelogo', 'frontpagelogo');
            $logoalt = get_string('logoalt', 'theme_kiwifruitresponsive', $SITE->fullname);
        } else if (!empty($PAGE->theme->settings->logo)) {
            $logourl = $PAGE->theme->setting_file_url('logo', 'logo');
            $logoalt = get_string('logoalt', 'theme_kiwifruitresponsive', $SITE->fullname);
        } else {
            $logourl = $OUTPUT->pix_url('logo', 'theme');
            $logoalt = get_string('totaralogo', 'theme_standardtotararesponsive');
        }

        if (!empty($PAGE->theme->settings->alttext)) {
            $logoalt = format_string($PAGE->theme->settings->alttext);
        }

        if ($logourl) {
            $logo = html_writer::empty_tag('img', array('src' => $logourl, 'alt' => $logoalt));
            $output .= html_writer::tag('a', $logo, array('href' => $CFG->wwwroot, 'class' => 'logo'));
        }

        // The menu.
        $output .= html_writer::start_tag('div', array('id' => 'totaramenu', 'class' => 'nav-collapse'));
        if (empty($PAGE->layout_options['nocustommenu'])) {
            $custommenu = $OUTPUT->custom_menu();
            if ($custommenu) {
                $output .= $custommenu;
            } else {
                $menudata = totara_build_menu();
                $totara_core_renderer = $PAGE->get_renderer('totara_core');
                $totaramenu = $totara_core_renderer->print_totara_menu($menudata);
                $output .= $totaramenu;
            }
        }

        // Language Menu.
        $haslangmenu = (!isset($PAGE->layout_options['langmenu']) || $PAGE->layout_options['langmenu'] );
        if ($haslangmenu) {
            $output .= $OUTPUT->lang_menu();
        }
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('header');
        return $output;
    }

    public function responsive_button() {
        $attrs = array(
            'class' => 'btn btn-navbar',
            'data-toggle' => 'collapse',
            'data-target' => '.nav-collapse, .langmenu'
        );
        $output = html_writer::start_tag('a', $attrs);
        $output .= html_writer::tag('span', '', array('class' => 'icon-bar')); // Chrome doesn't like self closing spans.
        $output .= html_writer::tag('span', '', array('class' => 'icon-bar'));
        $output .= html_writer::tag('span', '', array('class' => 'icon-bar'));
        $output .= html_writer::end_tag('a');

        return $output;
    }

    /**
     * Return the standard string that says whether you are logged in (and switched
     * roles/logged in as another user).
     *
     * This overrides the core version, only making two minor changes - removing
     * the brackets from the Login/Logout string and adding the 'link-as-button'
     * class so it appears like a button
     *
     * @param bool $withlinks Not used by this version, added for compatibility with core_renderer::login_info()
     * @return string HTML fragment.
     */
    public function login_info($withlinks = null) {
        global $USER, $CFG, $DB, $SESSION, $PAGE;

        if (during_initial_install()) {
            return '';
        }

        $loginapge = ((string)$PAGE->url === get_login_url());
        $course = $PAGE->course;

        if (\core\session\manager::is_loggedinas()) {
            $realuser = \core\session\manager::get_realuser();
            $fullname = fullname($realuser, true);
            $realuserinfo = " [<a href=\"$CFG->wwwroot/course/loginas.php?id=$course->id&amp;sesskey=".sesskey()."\">$fullname</a>] ";
        } else {
            $realuserinfo = '';
        }

        $loginurl = get_login_url();

        if (empty($course->id)) {
            // $course->id is not defined during installation
            return '';
        } else if (isloggedin()) {
            $context = context_course::instance($course->id);

            $fullname = fullname($USER, true);

            // Since Moodle 2.0 this link always goes to the public profile page (not the course profile page)
            $username = "<a class=\"username\" href=\"$CFG->wwwroot/user/profile.php?id=$USER->id\">$fullname</a>";
            if (is_mnet_remote_user($USER) and $idprovider = $DB->get_record('mnet_host', array('id' => $USER->mnethostid))) {
                $username .= " from <a href=\"{$idprovider->wwwroot}\">{$idprovider->name}</a>";
            }
            if (isguestuser()) {
                $loggedinas = $realuserinfo.get_string('loggedinasguest');
                if (!$loginapge) {
                    $loggedinas .= " <a class=\"loginstatus\" href=\"$loginurl\">".get_string('login').'</a>';
                }
            } else if (is_role_switched($course->id)) { // Has switched roles
                $rolename = '';
                if ($role = $DB->get_record('role', array('id' => $USER->access['rsw'][$context->path]))) {
                    $rolename = ': '.format_string($role->name);
                }
                $loggedinas = get_string('loggedinas', '', $username).$rolename.
                    " <a class=\"loginstatus\" href=\"$CFG->wwwroot/course/view.php?id=$course->id&amp;switchrole=0&amp;sesskey="
                    .sesskey()."\">".get_string('switchrolereturn').'</a>';
            } else {
                $loggedinas = $realuserinfo.get_string('loggedinas', '', $username).
                          " <a class=\"loginstatus\" href=\"$CFG->wwwroot/login/logout.php?sesskey=".sesskey()."\">".get_string('logout').'</a>';
            }
        } else {
            $loggedinas = get_string('loggedinnot').
                " <a class=\"loginstatus\" href=\"$loginurl\">".get_string('login').'</a>';
        }

        $loggedinas = '<div class="logininfo">'.$loggedinas.'</div>';

        if (isset($SESSION->justloggedin)) {
            unset($SESSION->justloggedin);
            if (!empty($CFG->displayloginfailures)) {
                if (!isguestuser()) {
                    $loggedinas = $realuserinfo.get_string('loggedinasguest');
                    if ($count = count_login_failures($CFG->displayloginfailures, $USER->username, $USER->lastlogin)) {
                        $loggedinas .= '&nbsp;<div class="loginfailures">';
                        if (empty($count->accounts)) {
                            $loggedinas .= get_string('failedloginattempts', '', $count);
                        } else {
                            $loggedinas .= get_string('failedloginattemptsall', '', $count);
                        }
                        if (file_exists("$CFG->dirroot/report/log/index.php") and has_capability('report/log:view', get_context_instance(CONTEXT_SYSTEM))) {
                            $loggedinas .= ' <a href="'.$CFG->wwwroot.'/report/log/index.php'.
                                                 '?chooselog=1&amp;id=1&amp;modid=site_errors">'.get_string('logs').'</a>';
                        }
                        $loggedinas .= '</div>';
                    }
                }
            }
        }

        return $loggedinas;
    }

    /**
     * Gets HTML for the page heading.
     *
     * @since Moodle 2.5.1 2.6
     * @param string $tag The tag to encase the heading in. h1 by default.
     * @return string HTML.
     */
    public function page_heading($tag = 'h1') {
        return html_writer::tag($tag, $this->page->heading, array('id' => 'pageheading'));
    }
}
