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
 * @package totara
 * @subpackage plan
 */

/**
 * Determine whether a evidence type is in use or not.
 *
 * "in use" means that items are assigned any of the evidence type's values.
 *
 * @param int $evidencetypeid The evidence type to check
 * @return boolean
 */
function dp_evidence_type_is_used($evidencetypeid) {
    global $DB;

    return $DB->record_exists('dp_plan_evidence', array('evidencetypeid' => $evidencetypeid));
}
