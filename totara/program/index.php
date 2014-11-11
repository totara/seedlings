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
 * @author Yuliya Bozhko <yuliya.bozhko@totaralms.com>
 * @package totara
 * @subpackage program
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/totara/program/lib.php');
require_once($CFG->libdir. '/coursecatlib.php');

$categoryid = optional_param('categoryid', 0, PARAM_INT); // Category id
$viewtype = optional_param('viewtype', 'program', PARAM_TEXT); // Type of a page, program or certification.

if (!empty($CFG->enhancedcatalog) && !$categoryid) {
    if ($viewtype == 'program') {
        redirect(new moodle_url('/totara/coursecatalog/programs.php'));
    } else {
        redirect(new moodle_url('/totara/coursecatalog/certifications.php'));
    }
}

// Check if programs or certifications are enabled.
if ($viewtype == 'program') {
    check_program_enabled();
} else {
    check_certification_enabled();
}

$site = get_site();

if ($categoryid) {
    $url = new moodle_url('/totara/program/index.php', array('categoryid' => $categoryid, 'viewtype' => $viewtype));
    $PAGE->set_category_by_id($categoryid);
    $PAGE->set_url($url);
    $PAGE->set_pagetype('course-index-category');
    $category = $PAGE->category;
    // Add program breadcrumbs.
    $navname = $viewtype == 'program' ? get_string('programs', 'totara_program') : get_string('certifications', 'totara_certification');
    $PAGE->navbar->add($navname, $url);
    $category_breadcrumbs = prog_get_category_breadcrumbs($categoryid, $viewtype);
    foreach ($category_breadcrumbs as $crumb) {
        $PAGE->navbar->add($crumb['name'], $crumb['link']);
    }
} else {
    $categoryid = 0;
    $PAGE->set_url('/totara/program/index.php', array('viewtype' => $viewtype));
    $PAGE->set_context(context_system::instance());
}

$PAGE->set_pagelayout('coursecategory');
$programrenderer = $PAGE->get_renderer('totara_program');

if ($CFG->forcelogin) {
    require_login();
}

if ($categoryid && !$category->visible && !has_capability('moodle/category:viewhiddencategories', $PAGE->context)) {
    throw new moodle_exception('unknowncategory');
}

$PAGE->set_totara_menu_selected('findlearning');
$PAGE->set_heading(format_string($site->fullname));
$content = $programrenderer->program_category($categoryid, $viewtype);

echo $OUTPUT->header();
echo $OUTPUT->skip_link_target();
echo $content;

echo $OUTPUT->footer();