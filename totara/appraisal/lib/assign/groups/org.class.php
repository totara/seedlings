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
 * @subpackage appraisal
 */

/**
 * base org grouping assignment class
 * will mostly be extended by child classes in each totara module, but is generic and functional
 * enough to still be useful for simple assignment cases
 */
global $CFG;
require_once($CFG->dirroot.'/totara/core/lib/assign/lib.php');
require_once($CFG->dirroot.'/totara/core/lib/assign/groups/org.class.php');
require_once($CFG->dirroot.'/totara/hierarchy/prefix/position/lib.php');

class totara_assign_appraisal_grouptype_org extends totara_assign_core_grouptype_org {

}
