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
 * @author Maria Torres <maria.torres@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

/**
 * Javascript file containing JQuery bindings for show saved searches popup dialog box
 */

M.totara_reportbuilder_savedsearches = M.totara_reportbuilder_savedsearches || {

    Y: null,

    /**
     * module initialisation method called by php js_init_call()
     *
     * @param object    YUI instance
     * @param string    args supplied in JSON format
     */
    init: function(Y, args) {
        // Save a reference to the Y instance (all of its dependencies included).
        this.Y = Y;

        // Check jQuery dependency is available.
        if (typeof $ === 'undefined') {
            throw new Error('M.totara_reportbuilder_savevedsearch.init()-> jQuery dependency required for this module to function.');
        }

        var totaraDialog_saved_search_handler = function() {};

        totaraDialog_saved_search_handler.prototype = new totaraDialog_handler();

        totaraDialog_saved_search_handler.prototype.every_load = function() {
            // We are in confirmation dialog to delete a saved search.
            $('input[value="Continue"]', this._container).click(function() {
                var idsearch = $(this).siblings(":hidden[name=sid]").val();
                var action = $(this).siblings(":hidden[name=action]").val();
                // Deleting is confirmed, so remove the search from the drop-down list.
                if (action == 'delete') {
                    $('select[name=sid] option[value=' + idsearch + ']').remove();
                    // Ask for the elements of the select sid. If none. remove the view saved search option.
                    // And the manage button.
                    if ($('select[name=sid] option').length == 1) { // 1 because of the Choose option.
                        $('#rb-search-menu').remove();
                        $('#manage-saved-search-button').remove();
                    }
                }
            });

            // We are editing, then update the saved search.
            $('form').submit(function() {
                var form = this;
                var action = $(":hidden[name=action]", form).val();

                // Deleting is confirmed, so remove the search from the drop-down list.
                if (action == 'edit') {
                    var idsearch = $(":hidden[name=sid]", form).val();
                    var searchname = $("#id_name", form).val();
                    $('select[name=sid] option[value=' + idsearch + ']').text(searchname);
                }
            });
        }

        var managebutton = $('input[name=rb_manage_search]');

        if (typeof managebutton.attr('id') != 'undefined') {
            var path = M.cfg.wwwroot + '/totara/reportbuilder/';
            var handler = new totaraDialog_saved_search_handler();
            var name = 'searchlist';
            var id = managebutton.attr('id').substr('show-searchlist-dialog-'.length);
            var buttons = {};
            buttons[M.util.get_string('close', 'form')] = function() { handler._cancel() };

            totaraDialogs[name] = new totaraDialog(
                name,
                'show-' + name + '-dialog-' + id,
                {
                    buttons: buttons,
                    title: '<h2>' + M.util.get_string('managesavedsearches', 'totara_reportbuilder') + '</h2>'
                },
                path + 'savedsearches.php?id=' + id.toString(),
                handler
            );
        }
    }
}
