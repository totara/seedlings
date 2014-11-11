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
 * @author Jon Sharp <jonathans@catalyst-eu.net>
 * @package totara
 * @subpackage certification
 */
M.totara_certificationconfirm = M.totara_certificationconfirm  || {

    Y: null,
    // optional php params and defaults defined here, args passed to init method
    // below will override these values
    config: {},

    //
    totaraDialog_savechanges: null,

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

        // check if required param id is available
        if (!this.config.id) {
            throw new Error('M.totara_certificationconfirm init()-> Required config \'id\' not available.');
        }

        // check jQuery dependency and continue with setup
        if (typeof $ === 'undefined') {
            throw new Error('M.totara_certificationconfirm.init()-> jQuery dependency required for this module to function.');
        }


        // The save changes confirmation dialog
        this.totaraDialog_savechanges = function() {

            // Setup the handler
            var handler = new totaraDialog_handler();

            // Store reference to this
            var self = this;

            var buttonsObj = {};
            buttonsObj[M.util.get_string('editcertif', 'totara_certification')] = function() { handler._cancel() };
            buttonsObj[M.util.get_string('saveallchanges', 'totara_certification')] = function() { self.save() };

            // Call the parent dialog object and link us
            totaraDialog.call(
            this,
            'savechanges-dialog',
            'unused', // buttonid unused
            {
                buttons: buttonsObj,
                title: '<h2>'+ M.util.get_string('confirmchanges', 'totara_certification') +'</h2>'
            },
            'unused', // default_url unused
            handler
            );

            this.old_open = this.open;
            this.open = function(html, table, rows) {
                // Do the default open first to get everything ready
                this.old_open();

                this.dialog.height(150);

                // Now load the custom html content
                this.dialog.html(html);

                this.table = table;
                this.rows = rows;
            }

            // Don't load anything
            this.load = function(url, method) {
            }
        }

        // attach a function to the page to prevent unsaved changes from being lost
        // when navigating away
        window.onbeforeunload = function(e) {

            var modified = module.isFormModified();
            var str = M.util.get_string('youhaveunsavedchanges', 'totara_certification');
            var e = e || window.event;

            if (modified == true) {
                // For IE and Firefox (before version 4)
                if (e) {
                    e.returnValue = str;
                }
                // For Safari
                return str;
            }
        };

        // remove the 'unsaved changes' confirmation when submitting the form
        $('form[name="form_certif_details"]').submit(function(){
            window.onbeforeunload = null;
        });

        // Remove the 'unsaved changes' confirmation when clicking th 'Cancel program management' link
        $('#cancelprogramedits').click(function(){
            window.onbeforeunload = null;
            return true;
        });

        // Add a function to launch the save changes dialog
        $('input[name="savechanges"]').click(function() {
            return module.handleSaveChanges();
        });

        totaraDialogs['savechanges'] = new this.totaraDialog_savechanges();


        // Set up the display of messages
        $('input[name=cancel]').css('display', 'none');
        $('input[name=update]').css('display', 'none');

        this.storeInitialFormValues();
    },

    /**
     *
     */
    handleSaveChanges: function(){

        // no need to display the confirmation dialog if there are no changes to save
        if (!this.isFormModified()) {
            window.onbeforeunload = null;
            return true;
        }

        var dialog = totaraDialogs['savechanges'];

        if (dialog.savechanges == true) {
            window.onbeforeunload = null;
            return true;
        }

        dialog.open(M.util.get_string('tosaveall', 'totara_certification'));
        dialog.save = function() {
            dialog.savechanges = true;
            this.hide();
            $('input[name="savechanges"]').trigger('click');
        }

        return false;
    },

    /**
     * Stores the initial values of the form when the page is loaded
     */
    storeInitialFormValues: function(){
        var form = $('form[name="form_certif_details"]');

        $('input[type="text"], textarea, select', form).each(function() {
            $(this).attr('initialValue', $(this).val());
        });
    },

    /**
     * Checks if the form is modified by comparing the initial and current values
     */
    isFormModified: function(){
        var form = $('form[name="form_certif_details"]');
        var isModified = false;

        // Check if text inputs or selects have been changed
        $('input[type="text"], select', form).each(function() {
            //console.log('this text init='+$(this).attr('initialValue')+' new='+$(this).val());
            if ($(this).attr('initialValue') != $(this).val()) {
                isModified = true;
            }
        });

        return isModified;
    }
};

