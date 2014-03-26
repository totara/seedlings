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
 * @author Russell England <russell.england@catalyst-eu.net>
 * @package totara
 * @subpackage totara_certifications
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version  = 2013092000;       // The current module version (Date: YYYYMMDDXX).
$plugin->requires = 2011120501;       // Requires this Moodle version.
$plugin->cron = 0;                    // Period for cron to check this module (secs).
$plugin->component = 'block_totara_certifications'; // To check on upgrade, that module sits in correct place.
