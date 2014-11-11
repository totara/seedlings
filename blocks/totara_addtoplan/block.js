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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 *
 * @package totara
 * @subpackage plan
 */

M.block_totara_addtoplan = M.block_totara_addtoplan || {
    Y: null,

    // optional php params and defaults defined here, args passed to init method
    // below will override these values
    config: {},

    init: function(Y, args) {
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

        // check jQuery dependency is available
        if (typeof $ === 'undefined') {
            throw new Error('M.block_totara_addtoplan.init()-> jQuery dependency required for this module to function.');
        }

        // setup form submit listener
        $(document).on('submit', '#block_totara_addtoplan_text form', function() {
            var addurl = M.cfg.wwwroot + '/totara/plan/components/course/add.php?add=' + M.block_totara_addtoplan.config.courseid + '&fromblock=1&id=';
            addurl = addurl + $('#block_totara_addtoplan_text form select').val();
            $('#block_totara_addtoplan_text').load(addurl);
            return false;
        });
    }
}
