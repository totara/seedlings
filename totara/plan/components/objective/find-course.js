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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @author Dave Wallace <dave.wallace@kineo.co.nz>
 * @package totara
 * @subpackage totara_core
 */
M.totara_plan_objective_find_course = M.totara_plan_objective_find_course || {

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

        // check jQuery dependency is available
        if (typeof $ === 'undefined') {
            throw new Error('M.totara_positionitem.init()-> jQuery dependency required for this module to function.');
        }
        // Create handler for the dialog
        totaraDialog_handler_preRequisite = function() {
            // Base url
            var baseurl = '';
        }

        totaraDialog_handler_preRequisite.prototype = new totaraDialog_handler_treeview_multiselect();

        /**
         * Add a row to a table on the calling page
         * Also hides the dialog and any no item notice
         *
         * @param string    HTML response
         * @return void
         */
        totaraDialog_handler_preRequisite.prototype._update = function(response) {

            // Hide dialog
            this._dialog.hide();

            // Remove no item warning (if exists)
            $('.noitems-'+this._title).remove();

            // Grab table
            var table = $('table.dp-plan-component-items');
            var new_table = $(response).find('table.dp-plan-component-items');
            // If table found
            if (table.size()) {
                table.replaceWith(new_table);
            }
            else {
                // Add new table
                $('div#dp-objective-courses-container').prepend(new_table);
            }

            // Grab remove button
            $('input#remove-selected-course').show();
        };
        var url = M.cfg.wwwroot + '/totara/plan/components/objective/';
        var saveurl = url + 'update-course.php?planid='+this.config.plan_id+'&objectiveid='+this.config.objective_id+'&update=';

        var handler = new totaraDialog_handler_preRequisite();
        handler.baseurl = url;
        var buttonsObj = {};
        buttonsObj[M.util.get_string('cancel','moodle')] = function() { handler._cancel(); }
        buttonsObj[M.util.get_string('save','totara_core')] = function() { handler._save(saveurl); }

        totaraDialogs['evidence'] = new totaraDialog(
            'assigncourses',
            'show-course-dialog',
            {
                buttons: buttonsObj,
                title: '<h2>' + M.util.get_string('addlinkedcourses', 'totara_plan') + '</h2>'
            },
            url+'find-course.php?planid='+this.config.plan_id+'&objectiveid='+this.config.objective_id,
            handler
        );

    }

};
