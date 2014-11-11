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
 * @author Piers Harding <piers@catalyst.net.nz>
 * @author Dave Wallace <dave.wallace@kineo.co.nz>
 * @package totara
 * @subpackage totara_core
 */
M.totara_message = M.totara_message || {

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
     * Instantiates dismiss dialog for a task/alert, with optional extra buttons
     *
     * @param object    YUI instance
     * @param int       unique Id for task/alert
     * @param string    used to form unique node Id's
     * @param string    clean local URL
     * @param string    session key
     * @param string    JSON string containing extra buttons for the dialog
     */
    create_dialog: function( Y, id, selector, clean_fullme, sesskey, extrabuttonjson ){
        // dismiss dialog
        (function() {
            var url = M.cfg.wwwroot+'/totara/message/';
            var handler = new totaraDialog_handler_confirm();
            var name = selector+id;
            var buttonsObj = {};
            buttonsObj[M.util.get_string('cancel', 'moodle')] = function() { handler._cancel() };
            buttonsObj[M.util.get_string('dismiss', 'totara_message')] = function() { handler._confirm(M.cfg.wwwroot+'/totara/message/dismiss.php?id='+id+'&sesskey='+sesskey, clean_fullme) };

            // JSON parse extrabuttons if a non-empty string is received
            if (extrabuttonjson && extrabuttonjson !== '') {
                var jargs = Y.JSON.parse( extrabuttonjson );
                for (var a in jargs) {
                    if (Y.Object.owns(jargs, a)) {
                        // add extra buttons by looping through keys in jargs,
                        // the handler must be returned by a wrapper function to
                        // close over the referenced values of each jargs key
                        buttonsObj[a] = (function(action, redirect) {
                                            return function() { handler._confirm(action, redirect, true); };
                                         })(jargs[a].action, jargs[a].clean_redirect);
                    }
                }
            }

            var dialogueWidth = Y.one('body').get("winWidth") - 50;
            dialogueWidth = Math.min(dialogueWidth, 600);

            totaraDialogs[name] = new totaraDialog(
                name,
                name+'-dialog',
                {
                    buttons: buttonsObj,
                    title: '<h2>'+M.util.get_string('reviewitems', 'block_totara_alerts')+'</h2>',
                    width: dialogueWidth,
                    height: 400
                },
                url+'dismissmsg.php?id='+id+'&sesskey='+sesskey,
                handler
            );
            // Set this dialog to not bind links
            $('#' + name).dialog('option','dialogClass', $('#' + name).dialog( 'option', 'dialogClass' ) + ' dialog-nobind');
        })();
    },

    /**
     * Instantiates a dialog for an action
     *
     * @param object    YUI instance
     * @param string    action to perform
     * @param string    action string for button
     * @param string    clean local URL
     * @param string    session key
     */
    create_action_dialog: function( Y, action, action_str, clean_fullme, sesskey ){
        // dismiss dialog
        (function() {
            var url = M.cfg.wwwroot+'/totara/message/';
            var handler = new totaraDialog_handler_confirm();
            var name = action+'msg';
            var buttonsObj = {};
            buttonsObj[M.util.get_string('cancel', 'moodle')] = function() { handler._cancel() };
            buttonsObj[action_str] = function() { handler._confirm(M.cfg.wwwroot+'/totara/message/action.php?'+action+'='+action+'&sesskey='+sesskey, clean_fullme) };

            totaraDialogs[name] = new totaraDialog(
                name,
                'totara-'+action,
                {
                    buttons: buttonsObj,
                    title: '<h2>'+action_str+'</h2>',
                    width: 600,
                    height: 400
                },
                url+'actionmsg.php?'+action+'='+action+'&sesskey='+sesskey,
                handler
            );
            // overload the load function so that we add the message ids
            totaraDialogs[name].load = function(url, method, onsuccess) {
                    // Add loading animation
                    this.dialog.html('');
                    this.showLoading();
                    msgids = [];
                    $('form#totara_messages input[type="checkbox"]:checked').each(
                                function () {
                                    msgids.push($(this).attr('value'));
                                });

                    // Save url
                    this.url = url+'&msgids='+msgids.join(',');

                    // Load page
                    this._request(this.url);
            };
        })();
    },

    /**
     * Instantiates a accept/reject dialog
     *
     * @param object    YUI instance
     * @param int       unique Id for task
     * @param string    used to form unique node Id's and handler url
     * @param string    action string for button
     * @param string    string to display in dialog header
     * @param string    return URL
     * @param string    session key
     */
    create_accept_reject_dialog: function( Y, id, type, type_str, dialog_title, returnto, sesskey ){

        (function() {
            $('#'+type+'msg'+id+'-dialog').css('display','block');
            var url = M.cfg.wwwroot+'/totara/message/';
            var handler = new totaraDialog_handler_confirm();
            var name = type+'msg'+id;

            var buttonsObj = {};
            buttonsObj[M.util.get_string('cancel', 'moodle')] = function() { handler._cancel() };
            buttonsObj[type_str] = function() { handler._confirm(M.cfg.wwwroot+'/totara/message/'+type+'.php?id='+id+'&sesskey='+sesskey, returnto) };

            totaraDialogs[name] = new totaraDialog(
                name,
                name+'-dialog',
                {
                    buttons: buttonsObj,
                    title: '<h2>'+dialog_title+'</h2>',
                    width: 600,
                    height: 400
                },
                url+'acceptrejectmsg.php?id='+id+'&event=on'+type,
                handler
            );
        })();
    },

    /**
     * Toggles disabled state of dismiss element, based on input disabled state
     */
    dismiss_input_toggle: function(){
        $('#totara_messages input[type=checkbox]').bind('click', function() {
            if ($('form#totara_messages input[type=checkbox]:checked').length) {
                $('#totara-dismiss').attr('disabled', false);
            } else {
                $('#totara-dismiss').attr('disabled', true);
            }
        });
    },

    /**
     *
     */
    select_all_none_checkbox: function(){
        $('th.message_values_dismiss_link').html('<div id="totara_message_selects"><a id="all" href="#">'+M.util.get_string('all', 'moodle')+
                                        '</a>/<a id="none" href="#">'+M.util.get_string('none', 'moodle')+'</a></div>');
        function jqCheckAll(flag) {
           if (flag === false) {
              $("form#totara_messages [type='checkbox']").prop('checked', false);
              if ($('form#totara_messages input[type=checkbox]:checked').length) {
                  $('#totara-dismiss').attr('disabled', false);
              } else {
                  $('#totara-dismiss').attr('disabled', true);
              }
           } else {
              $("form#totara_messages [type='checkbox']").prop('checked', true);
              if ($('form#totara_messages input[type=checkbox]:checked').length) {
                  $('#totara-dismiss').attr('disabled', false);
              } else {
                  $('#totara-dismiss').attr('disabled', true);
              }
           }
        }
        $('#totara_message_selects #all').click(function() {jqCheckAll(true); return false;});
        $('#totara_message_selects #none').click(function() {jqCheckAll(false); return false;});
    }
};

/*****************************************************************************/
/** totaraDialog_handler_confirm **/

/**
 * define the handler for a basic continue/cancel type dialog box
 * with a jumpto URL on continue leg
 *
 */

totaraDialog_handler_confirm = function() {};
totaraDialog_handler_confirm.prototype = new totaraDialog_handler();

/**
 * Serialize confirmed messages and send to url,
 * update table with result
 *
 * @param string URL to send confirmed messages to
 * @param string returnTo to send user back to after confirm complete
 * @param bool extrabutton Indicates if this call was made from an extrabutton
 * @return void
 */
totaraDialog_handler_confirm.prototype._confirm = function(url, returnto, extrabutton) {
    var addreasonparam = false;

    // Set the returnto.
    this.setReturnTo(returnto);

    var reasonfordecision = $('#reasonfordecision', this._container);
    if (extrabutton && reasonfordecision.length > 0 && reasonfordecision.val().trim().length > 0
        && (url.indexOf("reject") >= 0 || (url.indexOf("accept") >= 0))) {
        addreasonparam = true;
    }

    // Grab message ids if available.
    msgids = [];

    $('form#totara_messages input[type="checkbox"]:checked').each(
                function () {
                    msgids.push($(this).attr('value'));
                });
    url = url+'&msgids='+msgids.join(',');

    // Send to server.
    if (addreasonparam) {
        url = url+"&reasonfordecision=" + encodeURIComponent(reasonfordecision.val());
    }
    this._dialog._request(url, {object: this, method: '_redirect'});
};

/**
 * set the returnTo location
 *
 * @return void
 */
totaraDialog_handler_confirm.prototype.setReturnTo = function(url) {
    this._returnTo = url;
    return;
};


/**
 * Handle a 'redirect' request, by just closing the dialog
 * and then jumping to the returnTo
 *
 * @return void
 */
totaraDialog_handler_confirm.prototype._redirect = function() {
    this._dialog.hide();
    if (this._returnTo == null) {
        this._returnTo = M.cfg.wwwroot;
    }
    window.location = this._returnTo;
    return;
};
