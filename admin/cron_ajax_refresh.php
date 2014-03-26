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
 * @author Darko Miletic
 * @package totara
 * @subpackage cron
 */
require_once(dirname(__FILE__).'/../config.php');
require_once($CFG->libdir . '/pear/HTML/AJAX/JSON.php');
require_once(dirname(__FILE__).'/cron_procfile.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context, $USER->id, true, "nopermissions");

echo json_encode(cron_status());
