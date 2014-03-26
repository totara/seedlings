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
 * @author David Curry <david.curry@totaralms.com>
 * @package totara
 * @subpackage totara_core
 */
M.totara_cohort = M.totara_cohort || {

    Y: null,
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

        var module = this;

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
            throw new Error('M.totara_cohort.init()-> jQuery dependency required for this module to function.');
        }

        //
        // Goals dialog.
        // Note: assigntype=2 is referring to the constant GOAL_ASSIGNMENT_AUDIENCE.
        //
        (function() {
            var url = M.cfg.wwwroot+ '/totara/hierarchy/prefix/goal/assign/';
            var saveurl = url + 'assign.php';
            var findurl = url + 'find.php';

            // Dialog & handler for hierarchy picker.
            var thandler = new totaraDialog_handler_assigngoaltreeview();
            var tbuttons = {};
            tbuttons[M.util.get_string('cancel','moodle')] = function() { thandler._cancel(); }
            tbuttons[M.util.get_string('save','totara_core')] = function() { thandler._save(); }
            var tdialog = new totaraDialog(
                'assigngoaltreeviewdialog',
                'nobutton',
                {
                    buttons: tbuttons,
                    title: '<h2>' + M.util.get_string('assigngoals', 'totara_hierarchy') + '</h2>'
                },
                findurl,
                thandler
            );
            tdialog.assigngoal_base_url = url;
            totaraDialogs['assigngoaltreeview'] = tdialog;

            // Bind open event to group_selector menu(s)
            // Also set their default value
            $(document).on('click', '#show-assignedgoals-dialog', function(event) {
                // Stop any default event occuring
                event.preventDefault();

                // Open default url
                var select = $(this);
                var id = M.totara_cohort.config.id;
                var sesskey = M.totara_cohort.config.sesskey;

                var dialog = totaraDialogs['assigngoaltreeview'];
                var url = dialog.assigngoal_base_url;
                var handler = dialog.handler;

                handler.responsetype = 'newgoal';
                handler.responsegoeshere = $('#print_assigned_goals');

                dialog.default_url = findurl + '?assigntype=2&sesskey=' + sesskey + '&assignto=' + id;
                dialog.saveurl = saveurl + '?assigntype=2&sesskey=' + sesskey + '&assignto=' + id + '&add=1';
                dialog.open();

            });
        })();

        ///
        /// Position dialog
        ///
        (function() {
            var url = M.cfg.wwwroot+'/totara/hierarchy/prefix/position/assign/';

            totaraSingleSelectDialog(
                'position',
                M.util.get_string('chooseposition', 'totara_hierarchy') +
                module.config.display_selected_position,
                url+'position.php?',
                'positionid',
                'positiontitle'
            );
        })();


        ///
        /// Organisation dialog
        ///
        (function() {
            var url = M.cfg.wwwroot+'/totara/hierarchy/prefix/organisation/assign/';

            totaraSingleSelectDialog(
                'organisation',
                M.util.get_string('chooseorganisation', 'totara_hierarchy') +
                module.config.display_selected_organisation,
                url+'find.php?',
                'organisationid',
                'organisationtitle'
            );
        })();


        ///
        /// Confirmation dialog
        ///
        (function() {
            var submitAllowed = false;
            $('#mform1').submit(function () {
                return submitAllowed;
            });

            var handler = new totaraDialog_handler();
            var name = 'confirm';
            var buttonsObj = {};
            buttonsObj[M.util.get_string('cancel', 'moodle')] = function() { handler._cancel() };
            buttonsObj[M.util.get_string('continue', 'moodle')] = function() { submitAllowed = true; $('#mform1').submit(); };
            var dialog = new totaraDialog(
                name,
                'id_submitbutton',
                {
                    buttons: buttonsObj,
                    title: '<h2>'+ M.util.get_string('confirmdynamiccohortcreation', 'totara_cohort') +'</h2>'
                },
                '',
                handler
            );

            // Override the open function to get the dialog url dynamically
            dialog.originalOpen = dialog.open;
            dialog.open = function() {

                var positionincludechildren = 0;
                if ($('input[name="positionincludechildren"]').is(':checked')) {
                    positionincludechildren = 1;
                }

                var orgincludechildren = 0;
                if ($('input[name="orgincludechildren"]').is(':checked')) {
                    orgincludechildren = 1;
                }

                dialog.default_url = M.cfg.wwwroot+'/cohort/criteria.php' +
                '?cohortname=' + $('input[name="cohortname"]').val() +
                '&profilefield=' + $('select[name="profilefield"]').val() +
                '&profilefieldvalues=' + $('input[name="profilefieldvalues"]').val() +
                '&positionid=' + $('input[name="positionid"]').val() +
                '&positionincludechildren=' + positionincludechildren +
                '&organisationid=' + $('input[name="organisationid"]').val() +
                '&orgincludechildren=' + orgincludechildren;

                dialog.originalOpen();
            }

            totaraDialogs[name] = dialog;
        })();

    }
};

// A function to handle the responses generated by handlers
var assigngoal_handler_responsefunc = function(response) {
    if (response.substr(0,4) == 'DONE') {
        // Get all root elements in response
        var els = $(response.substr(4));

        // Update the assignments table.
        this.responsegoeshere.replaceWith(els);
        els.effect('pulsate', { times: 3 }, 2000);

        $('#assigngoal_action_box').show();

        // Close dialog
        this._dialog.hide();
    } else {
        this._dialog.render(response);
    }
}
totaraDialog_handler_assigngoaltreeview = function() {};
totaraDialog_handler_assigngoaltreeview.prototype = new totaraDialog_handler_treeview_multiselect();

/**
 * Serialize dropped items and send to url,
 * update table with result
 *
 * @return void
 */
totaraDialog_handler_assigngoaltreeview.prototype._save = function() {
    // Serialize data
    var elements = $('.selected > div > span', this._container);
    var selected = this._get_ids(elements);
    var extrafields = $('.assigngrouptreeviewsubmitfield');

    // If they're trying to create a new rule but haven't selected anything, just exit.
    // (If they are updating an existing rule, we'll want to delete the selected ones.)
    if (!selected.length) {
        if (this.responsetype == 'new') {
            this._cancel();
            return;
        } else if (this.responsetype == 'update') {
            // Trigger the "delete" link, closing this dialog if it's successful
            $('a.group-delete', this.responsegoeshere).trigger('click', {object: this, method: '_cancel'});
            return;
        }
    }

    // Check for any validation functions
    var success = true;
    extrafields.each(
        function(intIndex) {
            if (typeof(this.assigngoal_validation_func) == 'function') {
                success = success && this.assigngoal_validation_func(this);
            }
        }
    );
    if (!success) {
        return;
    }
    $('#assigngoal_action_box').show();

    var selected_str = selected.join(',');

    // Add to url
    var url = this._dialog.saveurl + '&selected=' + selected_str;
    extrafields.each(
        function(intIndex) {
            if ($(this).val() != null) {
                url = url + '&' + $(this).attr('name') + '=' + $(this).val();
            }
        }
    );

    // Send to server
    this._dialog._request(url, {object: this, method: '_update'});
}

// TODO: T-11233 need to figure out a better way to share this common code between this and the formpicker.
totaraDialog_handler_assigngoaltreeview.prototype._update = assigngoal_handler_responsefunc;
