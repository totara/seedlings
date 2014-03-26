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
 * @package totara
 * @subpackage totara_sync
 */

M.totara_syncdatabaseconnect = M.totara_syncdatabaseconnect || {

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
            throw new Error('M.totara_syncdatabaseconnect.init()-> jQuery dependency required for this module to function.');
        }

        $('#id_database_dbtest').click(function(event) {
            var dbtype = $('#id_database_dbtype').val();
            var dbname = $('#id_database_dbname').val();
            var dbhost = $('#id_database_dbhost').val();
            var dbuser = $('#id_database_dbuser').val();
            var dbpass = $('#id_database_dbpass').val();

            // Assemble url
            var url = M.cfg.wwwroot + '/admin/tool/totara_sync/sources/databasecheck.php' +
                                '?dbtype=' + dbtype +
                                '&dbname=' + dbname +
                                '&dbhost=' + dbhost +
                                '&dbuser=' + dbuser +
                                '&dbpass=' + dbpass;

            // Run script to check DB connectivity and display success or failure message
            $.getJSON(url, function(data) {
                // Make sure dbname and dbuser are not blank. This is to get around an issue
                // with MySQL where success is reported when passing no params to connect
                // function of database layer
                if (dbname != '' && dbuser != '') {
                    if (data == true) {
                        if ($('.db_connect_message').length > 0) {
                            $('.db_connect_message').replaceWith('<p class="db_connect_message">' + M.util.get_string('dbtestconnectsuccess', 'tool_totara_sync') + '</p>');
                        } else {
                            $('<p class="db_connect_message">' + M.util.get_string('dbtestconnectsuccess', 'tool_totara_sync') + '</p>').insertAfter('#id_database_dbtest');
                        }
                    } else {
                        if ($('.db_connect_message').length > 0) {
                            $('.db_connect_message').replaceWith('<p class="db_connect_message">' + M.util.get_string('dbtestconnectfail', 'tool_totara_sync') + '</p>');
                        } else {
                            $('<p class="db_connect_message">' + M.util.get_string('dbtestconnectfail', 'tool_totara_sync') + '</p>').insertAfter('#id_database_dbtest');
                        }
                    }
                } else {
                    if ($('.db_connect_message').length > 0) {
                        $('.db_connect_message').replaceWith('<p class="db_connect_message">' + M.util.get_string('dbtestconnectfail', 'tool_totara_sync') + '</p>');
                    } else {
                        $('<p class="db_connect_message">' + M.util.get_string('dbtestconnectfail', 'tool_totara_sync') + '</p>').insertAfter('#id_database_dbtest');
                    }
                }
            })
        });
    },
};
