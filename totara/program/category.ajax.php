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
 * @subpackage program
 */

define('AJAX_SCRIPT', true);

require_once(dirname(__dir__) . '/../config.php');

require_sesskey();
if ($CFG->forcelogin) {
    require_login();
}

$PAGE->set_context(context_system::instance());
$programrenderer = $PAGE->get_renderer('totara_program');

$type = required_param('type', PARAM_ALPHA);
$id = required_param('id', PARAM_INT);
$categorytype = required_param('categorytype', PARAM_ALPHA);

$programrenderer->header();

switch ($type) {
    case 'summary':
        require_once('program.class.php');
        if ($categorytype === 'program' || $categorytype === 'certification') {
            $program = new program($id);
            echo json_encode($programrenderer->program_description_ajax($program));
        }
        break;
    case 'category':
        require_once('lib.php');
        $category = coursecat::get($id);
        $categorytype = required_param('categorytype', PARAM_ALPHA);
        echo json_encode($programrenderer->program_category($category, $categorytype, true));
        break;
}
