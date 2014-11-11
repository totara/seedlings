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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Nathan Lewis <nathan.lewis@totaralms.com>
 * @package totara
 * @subpackage totara_core
 */
M.totara_review_settings = M.totara_review_settings || {

    /**
     * module initialisation method called by php js_init_call()
     *
     * @param object    YUI instance
     * @param string    args supplied in JSON format
     */
    init: function(Y, args) {
        $('#id_hasmultifield').on('click', function() {
            if ($('#id_hasmultifield').is(':checked')) {
                $('#id_multiplefields').show();
            } else {
                $('#id_multiplefields').hide();
            }
        });

        if ($('#id_hasmultifield').is(':checked')) {
            $('#id_multiplefields').show();
        } else {
            $('#id_multiplefields').hide();
        }
    }

};
