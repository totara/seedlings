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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package totara
 * @subpackage reportbuilder
 */

/**
 * Javascript file containing JQuery bindings for hierarchy dialog filters
 */

M.totara_reportbuilder_filterdialogs = M.totara_reportbuilder_filterdialogs || {

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
    init: function(Y, args) {
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
            throw new Error('M.totara_reportbuilder_filterdialogs.init()-> jQuery dependency required for this module to function.');
        }

        // do setup
        this.rb_init_filter_dialogs();
    },

    rb_init_filter_dialogs: function() {
        config = this.config;

        $('input.rb-filter-choose-pos').each(function(i, el) {
            var id = $(this).attr('id');
            // remove 'show-' and '-dialog' from ID
            id = id.substr(5, id.length - 12);

            ///
            /// Position dialog
            ///
            (function() {
                var url = M.cfg.wwwroot + '/totara/hierarchy/prefix/position/assign/';

                totaraSingleSelectDialog(
                    id,
                    M.util.get_string('chooseposition', 'totara_hierarchy') + config[id + '-currentlyselected'],
                    url+'position.php?',
                    id,
                    id+'title'
                );

                // disable popup buttons if first pulldown is set to
                // 'any value'
                if ($('select[name='+id+'_op]').val() == 0) {
                    $('input[name='+id+'_rec]').prop('disabled',true);
                    $('#show-'+id+'-dialog').prop('disabled',true);
                }
            })();

        });

        $('input.rb-filter-choose-org').each(function(i, el) {
            var id = $(this).attr('id');
            // remove 'show-' and '-dialog' from ID
            id = id.substr(5, id.length - 12);

            ///
            /// Organisation dialog
            ///
            (function() {
                var url = M.cfg.wwwroot + '/totara/hierarchy/prefix/organisation/assign/';

                totaraSingleSelectDialog(
                    id,
                    M.util.get_string('chooseorganisation', 'totara_hierarchy') + config[id + '-currentlyselected'],
                    url+'find.php?',
                    id,
                    id + 'title'
                );

                // disable popup buttons if first pulldown is set to
                // 'any value'
                if ($('select[name='+id+'_op]').val() == 0) {
                    $('input[name='+id+'_rec]').prop('disabled',true);
                    $('#show-'+id+'-dialog').prop('disabled',true);
                }
            })();

        });

        $('input.rb-filter-choose-comp').each(function(i, el) {
            var id = $(this).attr('id');
            // remove 'show-' and '-dialog' from ID
            id = id.substr(5, id.length - 12);

            ///
            /// Competency dialog
            ///
            (function() {
                var url = M.cfg.wwwroot + '/totara/hierarchy/prefix/competency/assign/';

                totaraSingleSelectDialog(
                    id,
                    M.util.get_string('selectcompetency', 'totara_hierarchy')  + config[id + '-currentlyselected'],
                    url+'find.php?',
                    id,
                    id+'title'
                );

                // disable popup buttons if first pulldown is set to
                // 'any value'
                if ($('select[name='+id+'_op]').val() == 0) {
                    $('input[name='+id+'_rec]').prop('disabled',true);
                    $('#show-'+id+'-dialog').prop('disabled',true);
                }
            })();

        });



        // bind multi-organisation report filter
        $('div.rb-org-add-link a').each(function(i, el) {
            var id = $(this).attr('id');
            // remove 'show-' and '-dialog' from ID
            id = id.substr(5, id.length - 12);

            (function() {
                var url = M.cfg.wwwroot + '/totara/hierarchy/prefix/organisation/assignfilter/';

                totaraMultiSelectDialogRbFilter(
                    id,
                    M.util.get_string('chooseorgplural', 'totara_reportbuilder'),
                    url+'find.php?',
                    url+'save.php?filtername='+id+'&ids='
                );

            })();

        });


        // bind multi-position report filter
        $('div.rb-pos-add-link a').each(function(i, el) {
            var id = $(this).attr('id');
            // remove 'show-' and '-dialog' from ID
            id = id.substr(5, id.length - 12);

            (function() {
                var url = M.cfg.wwwroot + '/totara/hierarchy/prefix/position/assignfilter/';

                totaraMultiSelectDialogRbFilter(
                    id,
                    M.util.get_string('chooseposplural', 'totara_reportbuilder'),
                    url+'find.php?',
                    url+'save.php?filtername='+id+'&ids='
                );

            })();

        });


        // bind multi-competency report filter
        $('div.rb-comp-add-link a').each(function(i, el) {
            var id = $(this).attr('id');
            // remove 'show-' and '-dialog' from ID
            id = id.substr(5, id.length - 12);

            (function() {
                var url = M.cfg.wwwroot + '/totara/hierarchy/prefix/competency/assignfilter/';

                totaraMultiSelectDialogRbFilter(
                    id,
                    M.util.get_string('choosecompplural', 'totara_reportbuilder'),
                    url+'find.php?',
                    url+'save.php?filtername='+id+'&ids='
                );

            })();

        });

        ///
        /// Cohorts
        ///

        // activate the 'delete' option next to any selected items in filters
        $(document).on('click', '.multiselect-selected-item a', function(event) {
            event.preventDefault();

            var container = $(this).parents('div.multiselect-selected-item');
            var filtername = container.data('filtername');
            var id = container.data('id');
            var hiddenfield = $('input[name='+filtername+']');

            // take this element's ID out of the hidden form field
            var ids = hiddenfield.val();
            var id_array = ids.split(',');
            var new_id_array = $.grep(id_array, function(n, i) { return n != id });
            var new_ids = new_id_array.join(',');
            hiddenfield.val(new_ids);

            // remove this element from the DOM
            container.remove();

        });

        // loop through every 'add cohort' link binding to a dialog
        $('div.rb-cohort-add-link a').each(function(i, el) {
            var id = $(this).attr('id');
            // remove 'show-' and '-dialog' from ID
            id = id.substr(5, id.length - 12);

            (function() {
                var url = M.cfg.wwwroot + '/totara/reportbuilder/ajax/';

                totaraMultiSelectDialogRbFilter(
                    id,
                    M.util.get_string('choosecohorts', 'totara_cohort'),
                    url + 'find_cohort.php',
                    url + 'save_cohort.php?filtername='+id+'&ids='
                );
            })();

        });

    }  // init_filter_dialogs
}
