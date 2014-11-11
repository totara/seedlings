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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage totara_plan
 */
M.totara_plan_competency_find = M.totara_plan_competency_find || {

    Y: null,
    // optional php params and defaults defined here, args passed to init method
    // below will override these values
    config: {},
    // public handler reference for the dialog
    totaraDialog_handler_lpCompetency: null,

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
            throw new Error('M.totara_plan_competency_find.init()-> jQuery dependency required for this module to function.');
        }

        // Create handler for the dialog
        this.totaraDialog_handler_lpCompetency = function() {
            // Base url
            var baseurl = '';
        }

        this.totaraDialog_handler_lpCompetency.prototype = new M.totara_plan_component.totaraDialog_handler_preRequisite();


        /**
         * Reset buttons on dialog open
         *
         * @return  void
         */
        this.totaraDialog_handler_lpCompetency.prototype._open = function() {

            // Check if user has allow permissions for updating compentencies
            if (comp_update_allowed) {
                var buttons = this.continue_buttons;
            } else {
                var buttons = this.standard_buttons;
            }

            // Reset buttons
            this._dialog.dialog.dialog('option', 'buttons', buttons);
        }


        /**
         * Load intermediate page for selecting courses
         *
         * @param   string  url
         * @return  void
         */
        this.totaraDialog_handler_lpCompetency.prototype._continue = function(url) {

            // Serialize data
            var elements = $('.selected > div > span', this._container);
            var selected_str = this._get_ids(elements).join(',');

            // Add to url
            url = url + selected_str;

            // Load url in dialog
            this._dialog._request(url, {object: this, method: '_continueRender'});
        }


        /**
         * Check result, if special string, redirect. Else, render;
         *
         * If rendering, update dialog buttons to be ok/cancel
         *
         * @param   object  asyncRequest response
         * @return  void
         */
        this.totaraDialog_handler_lpCompetency.prototype._continueRender = function(response) {

            // Check result
            if (response.substr(0, 9) == 'NOCOURSES') {

                // Generate url
                var url = this.continueskipurl + response.substr(10);

                // Send to server
                this._dialog._request(url, {object: this, method: '_update'});

                // Do not render
                return false;
            }

            // Update buttons
            this._dialog.dialog.dialog('option', 'buttons', this.continuesave_buttons);

            // Render result
            return true;
        }


        /**
         * Serialize linked courses and send to url,
         * update table with result
         *
         * @param string URL to send dropped items to
         * @return void
         */
        totaraDialog_handler.prototype._continueSave = function(url) {

            // Serialize form data
            var data_str = $('form', this._container).serialize();

            // Add to url
            url = url + data_str;

            // Send to server
            this._dialog._request(url, {object: this, method: '_update'});
        }

        var url = M.cfg.wwwroot + '/totara/plan/components/competency/';
        var continueurl = url + 'confirm.php?id='+this.config.plan_id+'&update=';
        var saveurl = url + 'update.php?id='+this.config.plan_id+'&update=';
        var continueskipurl = saveurl + 'id='+this.config.plan_id+'&update=';
        var continuesaveurl = url + 'update.php?';

        var handler = new this.totaraDialog_handler_lpCompetency();
        handler.baseurl = url;
        handler.continueskipurl = continueskipurl;

        handler.standard_buttons = {};
        handler.standard_buttons[M.util.get_string('cancel', 'moodle')] = function() { handler._cancel() }
        handler.standard_buttons[M.util.get_string('save', 'totara_core')] = function() { handler._save(saveurl) }

        handler.continue_buttons = {};
        handler.continue_buttons[M.util.get_string('cancel', 'moodle')] = function() { handler._cancel() }
        handler.continue_buttons[M.util.get_string('continue', 'moodle')] = function() { handler._continue(continueurl) }

        handler.continuesave_buttons = {};
        handler.continuesave_buttons[M.util.get_string('cancel', 'moodle')] = function() { handler._cancel() }
        handler.continuesave_buttons[M.util.get_string('save', 'totara_core')] = function() { handler._continueSave(continuesaveurl) }

        totaraDialogs['evidence'] = new totaraDialog(
            'assigncompetencies',
            'show-competency-dialog',
            {
                buttons: {},
                title: '<h2>' + M.util.get_string('addcompetencys', 'totara_plan') + '</h2>'
            },
            url+'find.php?id='+this.config.plan_id,
            handler
        );
    }
};
