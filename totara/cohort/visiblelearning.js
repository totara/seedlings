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
 * @subpackage totara_cohort
 */
M.totara_cohortvisiblelearning = M.totara_cohortvisiblelearning || {

    Y: null,
    // optional php params and defaults defined here, args passed to init method
    // below will override these values
    config: {},
    // public handler reference for the dialog
    totaraDialog_handler_preRequisite: null,

    /**
     * module initialisation method called by php js_init_call()
     *
     * @param object    YUI instance
     * @param string    args supplied in JSON format
     */
    init: function(Y, args) {
        // save a reference to the Y instance (all of its dependencies included)
        var module = this;
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

        // Check jQuery dependency is available.
        if (typeof $ === 'undefined') {
            throw new Error('M.totara_positionuser.init()-> jQuery dependency required for this module to function.');
        }

        // Add hooks to visibility of learning content.
        // Update when visibility drop-down list change.
        $(document).on('change', 'table#cohort_associations_visible form select', function() {
            var learningcontent = $(this).attr('id').split('_');
            var learningcontenttype = learningcontent[1];
            var learningcontentid = learningcontent[2];
            var learningvisibilityvalue = $(this).val();

            if (module.config.cohort_visibility[learningvisibilityvalue]) {
                $.ajax({
                    type: "POST",
                    url: M.cfg.wwwroot + '/totara/cohort/updatevisiblelearning.php',
                    data: ({
                        id: learningcontentid,
                        type: learningcontenttype,
                        value: learningvisibilityvalue,
                        sesskey: M.cfg.sesskey
                    })
                });
            } else {
                alert(M.util.get_string('invalidentry', 'error'));
            }
        });
    }
};
