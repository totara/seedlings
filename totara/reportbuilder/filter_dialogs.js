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
 * Javascript file containing JQuery bindings for hierarchy dialog filters.
 */

M.totara_reportbuilder_filterdialogs = M.totara_reportbuilder_filterdialogs || {

    Y: null,
    // Optional php params and defaults defined here, args passed to init method
    // below will override these values.
    config: {},

    /**
     * Module initialisation method called by php js_init_call().
     *
     * @param object    YUI instance
     * @param string    args supplied in JSON format
     */
    init: function(Y, args) {
        // Save a reference to the Y instance (all of its dependencies included).
        this.Y = Y;

        // If defined, parse args into this module's config object.
        if (args) {
            var jargs = Y.JSON.parse(args);
            for (var a in jargs) {
                if (Y.Object.owns(jargs, a)) {
                    this.config[a] = jargs[a];
                }
            }
        }

        // Check jQuery dependency is available.
        if (typeof $ === 'undefined') {
            throw new Error('M.totara_reportbuilder_filterdialogs.init()-> jQuery dependency required for this module to function.');
        }

        // Do setup.
        this.rb_init_filter_dialogs();
    },

    rb_init_filter_dialogs: function() {
        config = this.config;

        switch (config.filter_to_load) {
            case "hierarchy":
                this.rb_load_hierarchy_filters();
                break;
            case "badge":
                this.rb_load_badge_filters();
                break;
            case "hierarchy_multi":
                this.rb_load_hierarchy_multi_filters();
                break;
            case "cohort":
                this.rb_load_cohort_filters();
                break;
            case "category":
                this.rb_load_category_filters();
                break;
        }

        // Activate the 'delete' option next to any selected items in filters.
        $(document).on('click', '.multiselect-selected-item a', function(event) {
            event.preventDefault();

            var container = $(this).parents('div.multiselect-selected-item');
            var filtername = container.data('filtername');
            var id = container.data('id');
            var hiddenfield = $('input[name='+filtername+']');

            // Take this element's ID out of the hidden form field.
            var ids = hiddenfield.val();
            var id_array = ids.split(',');
            var new_id_array = $.grep(id_array, function(n, i) { return n != id });
            var new_ids = new_id_array.join(',');
            hiddenfield.val(new_ids);

            // Remove this element from the DOM.
            container.remove();
        });
    },

    rb_load_hierarchy_filters: function() {

        switch (config.hierarchytype) {
            case 'org':
                $('input.rb-filter-choose-org').each(function(i, el) {
                    var id = $(this).attr('id');
                    // Remove 'show-' and '-dialog' from ID.
                    id = id.substr(5, id.length - 12);

                    ///
                    /// Organisation dialog.
                    ///
                    (function() {
                        var url = M.cfg.wwwroot + '/totara/hierarchy/prefix/organisation/assign/';

                        totaraSingleSelectDialog(
                            id,
                            M.util.get_string('chooseorganisation', 'totara_hierarchy') + config[id + '-currentlyselected'],
                            url + 'find.php?',
                            id,
                            id + 'title'
                        );

                        // Disable popup buttons if first pulldown is set to 'any value'.
                        if ($('select[name=' + id + '_op]').val() == 0) {
                            $('input[name=' + id + '_rec]').prop('disabled', true);
                            $('#show-' + id + '-dialog').prop('disabled', true);
                        }
                    })();
                });

                break;

            case 'pos':
                $('input.rb-filter-choose-pos').each(function(i, el) {
                    var id = $(this).attr('id');
                    // Remove 'show-' and '-dialog' from ID.
                    id = id.substr(5, id.length - 12);

                    ///
                    /// Position dialog.
                    ///
                    (function() {
                        var url = M.cfg.wwwroot + '/totara/hierarchy/prefix/position/assign/';

                        totaraSingleSelectDialog(
                            id,
                            M.util.get_string('chooseposition', 'totara_hierarchy') + config[id + '-currentlyselected'],
                            url + 'position.php?',
                            id,
                            id + 'title'
                        );

                        // Disable popup buttons if first pulldown is set to 'any value'.
                        if ($('select[name=' + id + '_op]').val() == 0) {
                            $('input[name=' + id + '_rec]').prop('disabled',true);
                            $('#show-' + id + '-dialog').prop('disabled',true);
                        }
                    })();
                });

                break;

            case 'comp':
                $('input.rb-filter-choose-comp').each(function(i, el) {
                    var id = $(this).attr('id');
                    // Remove 'show-' and '-dialog' from ID.
                    id = id.substr(5, id.length - 12);

                    ///
                    /// Competency dialog.
                    ///
                    (function() {
                        var url = M.cfg.wwwroot + '/totara/hierarchy/prefix/competency/assign/';

                        totaraSingleSelectDialog(
                            id,
                            M.util.get_string('selectcompetency', 'totara_hierarchy')  + config[id + '-currentlyselected'],
                            url + 'find.php?',
                            id,
                            id + 'title'
                        );

                        // Disable popup buttons if first pulldown is set to 'any value'.
                        if ($('select[name=' + id + '_op]').val() == 0) {
                            $('input[name=' + id + '_rec]').prop('disabled',true);
                            $('#show-' + id + '-dialog').prop('disabled',true);
                        }
                    })();
                });

                break;
        }

    },

    rb_load_hierarchy_multi_filters: function() {

        // Bind multi-organisation report filter.
        $('div.rb-org-add-link a').each(function(i, el) {
            var id = $(this).attr('id');
            // Remove 'show-' and '-dialog' from ID.
            id = id.substr(5, id.length - 12);

            (function() {
                var url = M.cfg.wwwroot + '/totara/hierarchy/prefix/organisation/assignfilter/';

                totaraMultiSelectDialogRbFilter(
                    id,
                    M.util.get_string('chooseorgplural', 'totara_reportbuilder'),
                    url + 'find.php?',
                    url + 'save.php?filtername=' + id + '&ids='
                );
            })();
        });

        // Bind multi-position report filter.
        $('div.rb-pos-add-link a').each(function(i, el) {
            var id = $(this).attr('id');
            // Remove 'show-' and '-dialog' from ID.
            id = id.substr(5, id.length - 12);

            (function() {
                var url = M.cfg.wwwroot + '/totara/hierarchy/prefix/position/assignfilter/';

                totaraMultiSelectDialogRbFilter(
                    id,
                    M.util.get_string('chooseposplural', 'totara_reportbuilder'),
                    url + 'find.php?',
                    url + 'save.php?filtername=' + id + '&ids='
                );
            })();
        });


        // Bind multi-competency report filter.
        $('div.rb-comp-add-link a').each(function(i, el) {
            var id = $(this).attr('id');
            // Remove 'show-' and '-dialog' from ID.
            id = id.substr(5, id.length - 12);

            (function() {
                var url = M.cfg.wwwroot + '/totara/hierarchy/prefix/competency/assignfilter/';

                totaraMultiSelectDialogRbFilter(
                    id,
                    M.util.get_string('choosecompplural', 'totara_reportbuilder'),
                    url + 'find.php?',
                    url + 'save.php?filtername=' + id + '&ids='
                );
            })();
        });
    },

    rb_load_cohort_filters: function() {
        // Loop through every 'add cohort' link binding to a dialog.
        $('div.rb-cohort-add-link a').each(function(i, el) {
            var id = $(this).attr('id');
            // Remove 'show-' and '-dialog' from ID.
            id = id.substr(5, id.length - 12);

            (function() {
                var url = M.cfg.wwwroot + '/totara/reportbuilder/ajax/';

                totaraMultiSelectDialogRbFilter(
                    id,
                    M.util.get_string('choosecohorts', 'totara_cohort'),
                    url + 'find_cohort.php',
                    url + 'save_cohort.php?filtername=' + id + '&ids='
                );
            })();
        });
    },

    rb_load_badge_filters: function() {
        // Loop through every 'add badge' link binding to a dialog.
        $('div.rb-badge-add-link a').each(function(i, el) {
            var id = $(this).attr('id');
            // Remove 'show-' and '-dialog' from ID.
            id = id.substr(5, id.length - 12);

            (function() {
                var url = M.cfg.wwwroot + '/totara/reportbuilder/ajax/';

                totaraMultiSelectDialogRbFilter(
                    id,
                    M.util.get_string('choosebadges', 'badges'),
                    url + 'find_badge.php?reportid=' + config.reportid + '&sesskey=' + M.cfg.sesskey,
                    url + 'save_badge.php?filtername=' + id + '&sesskey=' + M.cfg.sesskey + '&ids='
                );
            })();
        });
    },

    rb_load_category_filters: function() {
        $(document).on('change', '#id_course_category-path_op', function(event) {
            event.preventDefault();
            var name = $(this).attr('name');
            name = name.substr(0, name.length - 3);// Remove _op.

            if ($(this).val() == 0) {
                $('input[name='+name+'_rec]').prop('disabled', true);
                $('#show-'+name+'-dialog').prop('disabled', true);
            } else {
                $('input[name='+name+'_rec]').prop('disabled', false);
                $('#show-'+name+'-dialog').prop('disabled', false);
            }
        });

        $('input.rb-filter-choose-category').each(function(i, el) {
            var id = $(this).attr('id');
            // Remove 'show-' and '-dialog' from ID.
            id = id.substr(5, id.length - 12);

            (function() {
                var url = M.cfg.wwwroot + '/totara/reportbuilder/ajax/filter/category/';
                totaraMultiSelectDialogRbFilter(
                    id,
                    M.util.get_string('choosecatplural', 'totara_reportbuilder'),
                    url + 'find.php?sesskey=' + M.cfg.sesskey,
                    url + 'save.php?filtername=' + id + '&sesskey=' + M.cfg.sesskey +'&ids='
                );
            })();

            // Disable popup buttons if first pulldown is set to 'any value'.
            if ($('select[name='+id+'_op]').val() == 0) {
                $('input[name='+id+'_rec]').prop('disabled',true);
                $('#show-'+id+'-dialog').prop('disabled',true);
            }
        });
    }
}
