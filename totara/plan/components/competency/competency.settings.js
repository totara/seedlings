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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage totara_plan
 */

$(document).ready(function() {
    // disable checkboxes on page load
    dp_competency_disable_dependencies();
    // check each time the checkboxes are changed
    $('#mform1 input[name=autoassignpos], #mform1 input[name=autoassignorg]').change(function() {
        dp_competency_disable_dependencies();

    });
});

/**
 * Disable 'include completed competencies' and 'include linked courses',
 * unless either 'auto assign by positions' or 'auto assign by organisations' are enabled
 */
function dp_competency_disable_dependencies() {
    if ($('#mform1 input[name=autoassignpos]').is(':checked') ||
       $('#mform1 input[name=autoassignorg]').is(':checked')) {
        $('#mform1 input[name=includecompleted]').removeAttr('disabled');
        $('#mform1 input[name=autoassigncourses]').removeAttr('disabled');
    } else {
        $('#mform1 input[name=includecompleted]').attr('disabled', 'disabled');
        $('#mform1 input[name=autoassigncourses]').attr('disabled', 'disabled');
    }
}
