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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @author Dave Wallace <dave.wallace@kineo.co.nz>
 * @package totara
 * @subpackage program
 */
M.totara_programview = M.totara_programview || {

    Y: null,
    // optional php params and defaults defined here, args passed to init method
    // below will override these values
    config: {
        userid:'',
        user_fullname:''
    },

    totaraDialog_extension: null,

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
            throw new Error('M.totara_programview.init()-> Required config \'id\' not available.');
        }

        // check jQuery dependency and continue with setup
        if (typeof $ === 'undefined') {
            throw new Error('M.totara_programview.init()-> jQuery dependency required for this module to function.');
        }


        // define the dialog handler
        totaraDialog_extension_handler = function() {};

        totaraDialog_extension_handler.prototype = new totaraDialog_handler();

        totaraDialog_extension_handler.prototype.first_load = function() {
            M.totara_core.build_datepicker(Y, 'input[name="extensiontime"]', M.util.get_string('datepickerlongyeardisplayformat', 'totara_core'));
            $('#ui-datepicker-div').css('z-index',1600);
        }

        totaraDialog_extension_handler.prototype.every_load = function() {
            // rebind placeholder for date picker
            $('input[placeholder], textarea[placeholder]').placeholder();
        }

        // Adapt the handler's save function
        totaraDialog_extension_handler.prototype._save = function() {

            var success = false;

            var extensiontime = $('.extensiontime', this._container).val();
            var extensionreason = $('.extensionreason', this._container).val();

            var dateformat = new RegExp(M.util.get_string('datepickerlongyearregexjs', 'totara_core'));

            if (dateformat.test(extensiontime) == false) {
                alert(M.util.get_string('pleaseentervaliddate', 'totara_program', M.util.get_string('datepickerlongyearplaceholder', 'totara_core')));
            } else if (extensionreason=='') {
                alert(M.util.get_string('pleaseentervalidreason', 'totara_program'));
            } else {
                success = true;
            }

            if (success) {
                var data = {
                    id: module.config.id,
                    userid: module.config.userid,
                    extrequest: "1",
                    extdate: extensiontime,
                    extreason: extensionreason
                };

                $.ajax({
                    type: 'POST',
                    url: M.cfg.wwwroot + '/totara/program/extension.php',
                    data: data,
                    success: module.totara_program_extension_update,
                    error: module.totara_program_extension_error
                });
                this._dialog.hide();
            }
        }

        // Define the extension request dialog
        this.totaraDialog_extension = function() {

            this.url = M.cfg.wwwroot + '/totara/program/view/set_extension.php?id='+module.config.id+'&amp;userid='+module.config.userid;

            // Setup the handler
            var handler = new totaraDialog_extension_handler();

            // Store reference to this
            var self = this;

            var buttonsObj = {};
            buttonsObj[M.util.get_string('cancel', 'totara_program')] = function() { handler._cancel(); };
            buttonsObj[M.util.get_string('ok', 'totara_program')] = function() { handler._save(); };

            // Call the parent dialog object and link us
            totaraDialog.call(
            this,
            'extension-dialog',
            'unused', // buttonid unused
            {
                buttons: buttonsObj,
                title: '<h2>'+M.util.get_string('extensionrequest', 'totara_program', module.config.user_fullname)+'</h2>'
            },
            this.url,
            handler
            );

            this.old_open = this.open;
            this.open = function() {
            this.old_open();
            this.dialog.height(150);
            }

        }

        totaraDialogs['extension'] = new this.totaraDialog_extension();

        // Bind the extension request dialog to the 'Request an extension' link
        $('a#extrequestlink').click(function() {
            totaraDialogs['extension'].open();
            return false;
        });
    },

    /**
     * Update extension text and notify user of success
     */
    totara_program_extension_update: function(response) {
        // Get existing text
        var extensiontext = $('a#extrequestlink');

        if (response) {
            var new_text = response;

            if (extensiontext.size()) {
                //If text found replace
                extensiontext.replaceWith(new_text);
            }

            $('div#totara-header-notifications').html(M.totara_programview.config.notify_html);
        } else {
            $('div#totara-header-notifications').html(M.totara_programview.config.notify_html_fail);
        }
    },

    /**
     * If validation error has occured then an error is returned print a
     * notification with the error message
     */
    totara_program_extension_error: function(response) {
        if (response) {
            var notify_text = response.responseText;

            var notify_html = '<div class="notifyproblem" style="text-align:center">' + notify_text + '</div>';

            $('div#totara-header-notifications').html(notify_html);
        }
    }
};

