YUI.add('moodle-mod_facetoface-signupform', function (Y, NAME) {

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
 * @author Andrew Hancox <andrewdchancox@googlemail.com>
 * @package totara
 * @subpackage facetoface
 */

M.mod_facetoface = M.mod_facetoface || {};
M.mod_facetoface.signupform = {

    /**
     * module initialisation method called by php js_init_call()
     *
     * @param object    YUI instance
     * @param string    args supplied in JSON format
     */
    init: function(){

        /**
         *  Attaches mouse events to the loaded content.
         */
        this.attachCustomClickEvents = function() {
            // Add handler to edit position button.
            Y.all('a.ajax-action').each(function(node) {
                var href = node.getAttribute('href');
                node.on('click', function(e){
                    Y.io(node.getAttribute('href'), {
                        on: {success: M.mod_facetoface.signupform.loadConfirmForm}
                    });
                    e.preventDefault();
                });
            });
        };

        this.attachCustomClickEvents();

        /**
         * Modal popup for confirmation form. Requires the existence of standard mform with a button #id_confirm
         * @param href The desired contents of the panel
         */
        this.loadConfirmForm = function(id, o) {
            bodyContent = o.responseText;
            var config = {
                headerContent : null,
                bodyContent : bodyContent,
                draggable : true,
                modal : true,
                closeButton : false,
                width : '600px'
            };
            dialog = new M.core.dialogue(config);
            Y.one('#' + dialog.get('id')).one('#id_confirm').on('click', function(e) {
                dialog.destroy(true);
                e.preventDefault();
            });
            dialog.show();
        };
    }
};

}, '@VERSION@', {
    "requires": [
        "base",
        "node",
        "io-base",
        "moodle-core-notification-dialogue",
        "moodle-core-notification-alert"
    ]
});
