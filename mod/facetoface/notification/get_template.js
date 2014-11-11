/**
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

M.totara_f2f_notification_template = M.totara_f2f_notification_template || {

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
            throw new Error('M.totara_f2f_notification_template.init()-> jQuery dependency required for this module to function.');
        }

        var templates = M.totara_f2f_notification_template.config.templates;

        $(function() {
            // Attach event to drop down
            $('select#id_template').change(function() {
                var select = $(this);

                // Get current value
                var current = select.val();

                // Overwrite form data.
                if (current !== '0') {
                    $('input#id_title').val(templates[current].title);
                    $('textarea#id_body_editor').val(templates[current].body);
                    $('textarea#id_managerprefix_editor').val(templates[current].managerprefix);
                    tinyMCE.get('id_body_editor').setContent(templates[current].body);
                    if (templates[current].managerprefix) {
                        tinyMCE.get('id_managerprefix_editor').setContent(templates[current].managerprefix);
                    } else {
                        tinyMCE.get('id_managerprefix_editor').setContent('');
                    }
                } else {
                    $('input#id_title').val('');
                    $('textarea#id_body_editor').val('');
                    $('textarea#id_managerprefix_editor').val('');
                    tinyMCE.get('id_body_editor').setContent('');
                    tinyMCE.get('id_managerprefix_editor').setContent('');
                }
            });

        });
    }
}
