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
 * @author Dave Wallace <dave.wallace@kineo.co.nz>
 * @package totara
 * @subpackage totara_core
 */
M.totara_core = M.totara_core || {

    Y:null,
    // optional php params and defaults defined here, args passed to init method
    // below will override these values
    config: {},

    /**
     * module initialisation method called by php js_init_call()
     *
     * @param object    YUI instance
     * @param string    args supplied in JSON format
     */
    init: function(Y, args){
        // save a reference to the Y instance (all of its dependencies included)
        this.Y = Y;

        // if defined, parse args into this module's config object
        if (args) {
            var jargs = Y.JSON.parse(args);
            for (var a in jargs) {
                if (Y.Object.owns(jargs, a)) {
                    this.config[a] = jargs[a];
                }
            }
        }
    },

    /**
     * sets up a jQuery datepicker based on provided selector, we intentionally
     * override the isRTL option here, and float the datepicker fields left/right
     * to get the picker to appear onthe correct side
     *
     * @param object    YUI instance
     * @param string    selector to bind the datepicker instance to
     * @param string    format for date display
     * @param array     values to determine datepicker icon
     */
    build_datepicker: function(Y, selector, dateformat, button_img){
        var icon;
        if (button_img) {
            icon = button_img;
        } else {
            icon = ['t/calendar','totara_core'];
        }

        var direction = (M.util.get_string('thisdirection', 'langconfig') === 'rtl');
        $(selector).datepicker(
            {
                dateFormat: dateformat,
                showOn: 'both',
                buttonImage: M.util.image_url(icon[0], icon[1]),
                buttonImageOnly: true,
                constrainInput: true,
                isRTL: direction
            }
        );
    }
};
