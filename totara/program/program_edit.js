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
 * @subpackage program
 */
M.totara_programedit = M.totara_programedit || {

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

        // check if required param id is available
        if (!this.config.id) {
            throw new Error('M.totara_programedit.init()-> Required config \'id\' not available.');
        }

        // check jQuery dependency and continue with setup
        if (typeof $ === 'undefined') {
            throw new Error('M.totara_programedit.init()-> jQuery dependency required for this module to function.');
        }

        // Handle program icon change preview
        $('#id_icon').change(function() {
            var selected = $(this);
            var src = $('#program_icon_preview').attr('src');

            src = src.replace(/icon=[^&]*/, 'icon='+selected.val());

            $('#program_icon_preview').attr('src', src);
        });


        // attach a function to the page to prevent unsaved changes from being lost
        // when navigating away
        window.onbeforeunload = function(e) {

            var modified = module.isFormModified();
            var str = M.util.get_string('youhaveunsavedchanges', 'totara_program');
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
        $('form[name="form_prog_details"]').submit(function(){
            window.onbeforeunload = null;
        });

        // Remove the 'unsaved changes' confirmation when clicking th 'Cancel program management' link
        $('#cancelprogramedits').click(function(){
            window.onbeforeunload = null;
            return true;
        });


        this.storeInitialFormValues();
    },

    /**
     * Stores the initial values of the form when the page is loaded
     */
    storeInitialFormValues: function() {
        var form = $('form[name="form_prog_details"]');
        $('input[type="text"], textarea, select', form).each(function() {
            $(this).attr('initialValue', $(this).val());
        });

        $('input[type="checkbox"]', form).each(function() {
            var checked = $(this).is(':checked') ? 1 : 0;
            $(this).attr('initialValue', checked);
        });
    },

    /**
     * Checks if the form is modified by comparing the initial and current values
     */
    isFormModified: function() {
        var form = $('form[name="form_prog_details"]');
        var isModified = false;

        // Check if text inputs or selects have been changed
        $('input[type="text"], select', form).each(function() {
            if ($(this).attr('initialValue') != $(this).val()) {
                isModified = true;
            }
        });

        // Check if check boxes have changed
        $('input[type="checkbox"]', form).each(function() {
            var checked = $(this).is(':checked') ? 1 : 0;
            if ($(this).attr('initialValue') != checked) {
                isModified = true;
            }
        });

        // Check if check boxes have changed
        if (($('input[name=icon]').attr('initialvalue') != undefined) && ($('input[name=icon]').attr('initialvalue') != $('input[name=icon]').val())) {
            isModified = true;
        }

        // Check if textareas have been changed
        $('textarea', form).each(function() {
            // See if there's a tiny MCE instance for this text area
            var instance = undefined;
            if (typeof tinyMCE != 'undefined') {
                instance = tinyMCE.getInstanceById($(this).attr('id'));
            }
            if (instance != undefined  && typeof instance.isDirty == 'function') {
                if (instance.isDirty()) {
                    isModified = true;
                }
            } else {
                // normal textarea (not tinyMCE)
                if ($(this).attr('initialValue') != $(this).val()) {
                    isModified = true;
                }
            }
        });

        return isModified;
    }
};
