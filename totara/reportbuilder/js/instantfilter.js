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
 * @author Nathan Lewis <nathan.lewis@totaralms.com>
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

/**
 * Javascript file containing JQuery bindings for processing changes to instant filters
 */

M.totara_reportbuilder_instantfilter = M.totara_reportbuilder_instantfilter || {

    Y: null,

    /**
     * module initialisation method called by php js_init_call()
     *
     * @param object    YUI instance
     * @param string    args supplied in JSON format
     */
    init: function(Y, args) {
        // save a reference to the Y instance (all of its dependencies included)
        this.Y = Y;
        var xhr = null;

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
            throw new Error('M.totara_reportbuilder_instantfilter.init()-> jQuery dependency required for this module to function.');
        }

        $('.rb-sidebar input, .rb-sidebar select').change(function (event) {
            // Abort any call to instantreport that is already active.
            if (xhr) {
                xhr.abort();
            }
            // Add the wait icon if it is not already attached to the clicked item.
            if ($(event.target).siblings('.instantfilterwait').length === 0) {
                loadingimg = '<img src="' + M.util.image_url('wait', 'totara_reportbuilder') +
                        '" class="iconsmall instantfilterwait" />';
                $(loadingimg).insertAfter($(event.target).parent().children().last());
            }
            xhr = M.totara_reportbuilder_instantfilter.refresh_results();
        });
    },

    /**
     * refresh results panel, used by change handler on sidebar search
     * as well as callbacks from reportbuilder sources that change
     * report entries
     *
     * @param callback    function to run once data is refreshed
     */
    refresh_results: function (callback) {
        // Make the ajax call.
        return $.post(
            M.cfg.wwwroot + '/totara/reportbuilder/ajax/instantreport.php',
            $('.rb-sidebar').serialize()
        ).done( function (data) {
                // Clear all waiting icons.
                $('.instantfilterwait').remove();
                // Replace contents.
                $('.rb-display-table-container').replaceWith($(data).find('.rb-display-table-container'));
                $('.rb-record-count').replaceWith($(data).find('.rb-record-count'));
                // Update sidebar filter counts.
                $(data).find('.rb-sidebar.mform label').each(function (ind, elem) {
                    var $elem = $(elem);
                    if ($elem.attr('for')) {
                        $('label[for="'+$elem.attr('for')+'"]').html($elem.html());
                    }
                });
                if (callback) {
                    callback();
                }
            });
    }
};