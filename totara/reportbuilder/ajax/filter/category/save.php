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
 * @author Maria Torres <maria.torres@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->dirroot.'/totara/core/utils.php');
require_once($CFG->dirroot.'/totara/reportbuilder/filters/lib.php');
require_once($CFG->dirroot.'/totara/reportbuilder/filters/category.php');
require_once($CFG->dirroot.'/lib/coursecatlib.php');

$ids = required_param('ids', PARAM_SEQUENCE);
$ids = explode(',', $ids);
$filtername = required_param('filtername', PARAM_ALPHANUMEXT);

// Permissions checks.
require_login();
require_sesskey();

// Send headers.
send_headers('text/html; charset=utf-8', false);

$PAGE->set_context(context_system::instance());

echo $OUTPUT->container_start('rb-filter-content-list list-' . $filtername);
if (!empty($ids)) {
    list($in_sql, $in_params) = $DB->get_in_or_equal($ids);
    $items = $DB->get_records_select('course_categories', "id {$in_sql}", $in_params);
    $names = coursecat::make_categories_list();
    foreach ($items as $item) {
        echo display_selected_category_item($names, $item, $filtername);
    }
}
echo $OUTPUT->container_end();
