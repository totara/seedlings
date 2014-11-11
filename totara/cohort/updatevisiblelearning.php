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
 * @subpackage cohort
 */
/**
 * This class is an ajax back-end for updating audience learning visibility
 */
define('AJAX_SCRIPT', true);
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot.'/cohort/lib.php');

$id = required_param('id', PARAM_INT);
$type = required_param('type', PARAM_INT);
$value = required_param('value', PARAM_INT);

require_login();
require_sesskey();

$result = totara_cohort_update_audience_visibility($type, $id, $value);

if ($type == COHORT_ASSN_ITEMTYPE_COURSE) {
    echo json_encode(array('update' => 'course', 'id' => $id, 'value' => $value, 'result' => $result));
} else if ($type == COHORT_ASSN_ITEMTYPE_PROGRAM || $type == COHORT_ASSN_ITEMTYPE_CERTIF) {
    echo json_encode(array('update' => 'prog', 'id' => $id, 'value' => $value, 'result' => $result));
}

exit();
