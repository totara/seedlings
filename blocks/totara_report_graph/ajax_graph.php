<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2014 onwards Totara Learning Solutions LTD
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
 * @author Petr Skoda <petr.skoda@totaralms.com>
 * @package block_totara_report_graph
 */

// Disable debug messages and any errors in output,
// comment out when debugging or better look into error log!
define('NO_DEBUG_DISPLAY', true);

define('REPORT_BUILDER_IGNORE_PAGE_PARAMETERS', true); // No source params here.

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');

$blockid = required_param('blockid', PARAM_INT);
$type = required_param('type', PARAM_ALPHA);

$blockcontext = context_block::instance($blockid, MUST_EXIST);
list($context, $course, $cm) = get_context_info_array($blockcontext->id);

if ($CFG->forcelogin) {
    require_login($course, false, $cm, false, true);
} else {
    require_course_login($course, false, $cm, false, true);
}

require_capability('moodle/block:view', $blockcontext);

// NOTE: no need to require sesskey here, this is not JSON.

// Release session lock - most of the access control is over
// and we want to mess with session data and improve perf.
\core\session\manager::write_close();

$block = $DB->get_record('block_instances', array('id' => $blockid, 'blockname' => 'totara_report_graph'), '*', MUST_EXIST);

if (empty($block->configdata)) {
    error_log($blockid . ': no config yet');
    die;
}

$config = unserialize(base64_decode($block->configdata));

$svgdata = \block_totara_report_graph\util::get_svg_data($blockid, $config);

if ($type === 'svg') {
    \block_totara_report_graph\util::send_svg($svgdata);

} else {
    \block_totara_report_graph\util::send_pdf($svgdata);
}
