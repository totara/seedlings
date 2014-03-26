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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage totara_core
 */

M.totara_version_tracking = M.totara_version_tracking || {

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
        var module = this;

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
            throw new Error('M.totara_version_tracking.init()-> jQuery dependency required for this module to function.');
        }

        $.ajax({
            url: this.config.protocol + '://community.totaralms.com/admin/current_release.php',
            dataType: 'jsonp',
            data: {'version': this.config.totara_version},
            jsonp: 'jsonp_callback',
            success: function(data) {
                var BRANCH_UNKNOWN = 0;
                var BRANCH_UNSUPPORTED = 1;
                var BRANCH_SUPPORTED = 2;
                var BRANCH_CURRENT = 3;
                var RELEASE_UNKNOWN = 0;
                var RELEASE_NOT_CURRENT = 1;
                var RELEASE_CURRENT = 2;

                var currentbranch = data.currentbranch;
                var currentrelease = data.currentrelease;
                var current_major_version = data.current_major_version;
                var alltypes_count = data.alltypes_count;
                var security_count = data.security_count;

                // something went wrong, we need this value to continue
                if (typeof currentbranch == 'undefined') {
                    return true;
                }

                // give the benefit of the doubt and display nothing
                if (currentbranch == BRANCH_UNKNOWN) {
                    return true;
                }

                // don't print anything if they are completely up to date
                // or they are on the current branch but an unknown release
                if (currentbranch == BRANCH_CURRENT && currentrelease != RELEASE_NOT_CURRENT) {
                    return true;
                }

                // if branch is unsupported, tell them the latest version
                if (currentbranch == BRANCH_UNSUPPORTED) {
                    var message = M.util.get_string('unsupported_branch_text', 'totara_core', module.config.major_version);
                    message = message.replace('[[CURRENT_MAJOR_VERSION]]', current_major_version);
                    module.display_message(message);
                    return true;
                }

                // release is either up to date or unknown, just let them know it's
                // not the newest major release
                if (currentbranch == BRANCH_SUPPORTED && currentrelease != RELEASE_NOT_CURRENT) {
                    var message = M.util.get_string('supported_branch_text', 'totara_core', module.config.major_version);
                    message = message.replace('[[CURRENT_MAJOR_VERSION]]', current_major_version);
                    module.display_message(message);
                    return true;
                }

                // if we reach here, the release is not current
                var message = (alltypes_count == 1) ?
                    M.util.get_string('old_release_text_singular', 'totara_core') :
                    M.util.get_string('old_release_text_plural', 'totara_core');
                message = message.replace('[[ALLTYPES_COUNT]]', alltypes_count);
                if (security_count > 0) {
                    message += (security_count == 1) ?
                        M.util.get_string('old_release_security_text_singular', 'totara_core') :
                        M.util.get_string('old_release_security_text_plural', 'totara_core');
                    message = message.replace('[[SECURITY_COUNT]]', security_count);
                }

                if (currentbranch == BRANCH_SUPPORTED) {
                    message += M.util.get_string('supported_branch_old_release_text', 'totara_core', module.config.major_version);
                    message = message.replace('[[CURRENT_MAJOR_VERSION]]', current_major_version);
                }
                module.display_message(message);
                return true;

            }
        });
    },

    display_message: function(text) {
        text += M.util.get_string('totarareleaselink', 'totara_core');
        $('div.totara-copyright p:last').append('<p>'+text+'</p>');
        return true;
    }
};

