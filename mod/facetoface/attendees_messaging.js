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
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage facetoface
 */

M.totara_f2f_attendees_messaging = M.totara_f2f_attendees_messaging || {

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
        var module = this;
        var default_url;

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
            throw new Error('M.totara_f2f_attendees.init()-> jQuery dependency required for this module to function.');
        }

        (function() {
            var handler = new totaraDialog_handler_editrecipients();
            var name = 'editrecipients';

            var buttonsObj = {};
            buttonsObj[M.util.get_string('cancel','moodle')] = function() { handler._cancel(); };
            buttonsObj[M.util.get_string('update','moodle')] = function() { handler.update_recipients(); };

            default_url = M.cfg.wwwroot + '/mod/facetoface/editrecipients.php?s=' + M.totara_f2f_attendees_messaging.config.sessionid;
            totaraDialogs[name] = new totaraDialog(
                name,
                undefined,
                {
                    buttons: buttonsObj,
                    title: '<h2>' + M.util.get_string('editmessagerecipientsindividually', 'facetoface') + '</h2>',
                    height: 500
                },
                default_url,
                handler
                );
        })();

        // Logic for messaging tab
        $('fieldset#recipientsheader').hide();

        var f2f_update_message_recipients_from_group = function() {

            var recipients = $('fieldset#recipientsheader select');
            recipients.html('');

            // Get selected
            $('fieldset#recipientgroupsheader input:checked').each(function() {
                // Get status code
                var status = $(this).attr('id').substring(('id_recipient_group_').length);
                for (user in recipient_groups[status]) {
                    user = recipient_groups[status][user];
                    recipients.append('<option value="'+user.id+'">'+user.firstname+' '+user.lastname+', '+user.email+'</select>');
                }
            });

            if ($('option', recipients).length) {
                $('fieldset#recipientsheader').show();
            } else {
                $('fieldset#recipientsheader').hide();
            }
        };

        // Update recipients list on update groups and on page load
        $('fieldset#recipientgroupsheader input').change(f2f_update_message_recipients_from_group);
        f2f_update_message_recipients_from_group();

        $('input#id_recipient_custom').click(function() {

            // Update default url to reflect currently selected users
            var selected = '';
            $('fieldset#recipientsheader select option').each(function() {
                selected += $(this).val()+',';
            });

            totaraDialogs['editrecipients'].default_url = default_url + '&recipients=' + selected;
            totaraDialogs['editrecipients'].open();
        });

        // Make recipient checkbox unclickable
        $('fieldset#recipientsheader select').change(function() { $(this).blur(); $(this).children().attr('selected', false); });
    }
}


/**
 * Edit message recipients dialog
 */
totaraDialog_handler_editrecipients = function() {};
totaraDialog_handler_editrecipients.prototype = new totaraDialog_handler();

/**
 * Update recipients form with selected recipients
 */
totaraDialog_handler_editrecipients.prototype.update_recipients = function() {
    // Reset recipients on list in background
    var recipients = $('fieldset#recipientsheader select');
    var recipients_hidden = $('input[name=recipients_selected]');
    recipients.html('');
    recipients_hidden.val('');

    // Reset recipient groups
    $('fieldset#recipientgroupsheader input').removeAttr('checked');

    $('select#removeselect option', this._container).each(function() {
        var value = $(this).val();
        var title = $(this).html();

        if (!value) {
            return;
        }

        recipients.append('<option value="'+value+'">'+title+'</option>');
        recipients_hidden.val(recipients_hidden.val()+','+value);
    });

    // Check if anything selected
    if ($('option', recipients).length) {
        $('fieldset#recipientgroupsheader').hide();
        $('fieldset#recipientsheader').show();
    } else {
        $('fieldset#recipientgroupsheader').show();
        $('fieldset#recipientsheader').hide();
    }

    this._cancel();
};
