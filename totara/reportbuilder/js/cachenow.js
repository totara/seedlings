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
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage totara_reportbuilder
 */
M.totara_reportbuilder_cachenow = M.totara_reportbuilder_cachenow || {

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
            throw new Error('M.totara_reportbuilder_cachenow.init()-> jQuery dependency required for this module to function.');
        }

        (function() {
            handler = new totaraDialog_handler();
            buttonObj = {};
            $('.show-cachenow-dialog').css('display','inline');

            $('.show-cachenow-dialog').each(function(ind,inst) {
                id = $(inst).data('id');
                url = M.cfg.wwwroot + '/totara/reportbuilder/ajax/cachenow.php?reportid='+id;
                name = 'cachenow';

                buttonObj[M.util.get_string('ok', 'moodle')] = function() {
                        handler._cancel();
                };

                totaraDialogs[name] = new totaraDialog(
                    name,
                    $(inst).attr('id'),
                    {
                        buttons: buttonObj,
                        title: '<h2>' + M.util.get_string('cachenow_title', 'totara_reportbuilder') + '</h2>',
                        width: '500',
                        height: '200',
                        dialogClass: 'totara-dialog notifynotice'
                    },
                    url,
                    handler
                );
            });
            $('#show-cachenow-dialog-'+id).bind('click', function() {$('#cachenotice_'+id).css('display', 'none')});
        })();
    }
};