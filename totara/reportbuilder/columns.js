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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @author Dave Wallace <dave.wallace@kineo.co.nz>
 * @package totara
 * @subpackage reportbuilder
 */
M.totara_reportbuildercolumns = M.totara_reportbuildercolumns || {

    Y: null,
    // optional php params and defaults defined here, args passed to init method
    // below will override these values
    config: {},
    loadingimg: '<img src="'+M.util.image_url('i/ajaxloader', 'moodle')+'" alt="'+M.util.get_string('saving', 'totara_reportbuilder')+'" class="iconsmall" />',
    advoptionshtml : '',

    /**
     * module initialisation method called by php js_init_call()
     *
     * @param object    YUI instance
     * @param object    configuration data
     */
    init: function(Y, config){
        // save a reference to the Y instance (all of its dependencies included)
        this.Y = Y;

        // store config info
        this.config = config;
        this.config.user_sesskey = M.cfg.sesskey;

        // check jQuery dependency is available
        if (typeof $ === 'undefined') {
            throw new Error('M.totara_reportbuildercolumns.init()-> jQuery dependency required for this module to function.');
        }

        // store all options for adv selector for later
        this.advoptionshtml = $('select.new_advanced_selector').html();

        // do setup
        this.rb_init_col_rows();
    },

    /**
     * Tweak row elements.
     * @param column_selector
     */
    rb_update_col_row: function(column_selector) {
        var module = this;

        var colName = $(column_selector).val();
        var newHeading = module.config.rb_column_headings[colName];

        var advancedSelector = $('select.advanced_selector', $(column_selector).parents('tr:first'));
        var headingElement = $('input.column_heading_text', $(column_selector).parents('tr:first'));
        var customHeadingCheckbox = $('input.column_custom_heading_checkbox', $(column_selector).parents('tr:first'));

        if (colName == '0') {
            advancedSelector.hide();
            customHeadingCheckbox.hide();
            headingElement.hide();

        } else if ($.inArray(colName, module.config.rb_grouped_columns) == -1) {
            advancedSelector.show();
            customHeadingCheckbox.show();
            headingElement.show();

            var advancedSelectorVal = advancedSelector.val();
            if (advancedSelectorVal != '') {
                if ($.inArray(advancedSelectorVal, module.config.rb_allowed_advanced[colName]) == -1) {
                    advancedSelector.val('');
                    advancedSelectorVal = '';
                } else {
                    var strName = advancedSelectorVal.replace('transform_', 'transformtype').replace('aggregate_', 'aggregatetype') + '_heading';
                    newHeading = M.util.get_string(strName, 'totara_reportbuilder', newHeading);
                }
            }
            // Alter advanced selector to show only possible options.
            advancedSelector.html(module.advoptionshtml);
            advancedSelector.val(advancedSelectorVal);
            advancedSelector.find('option').each(function () {
                var option = $(this);
                if ($.inArray(option.val(), module.config.rb_allowed_advanced[colName]) == -1) {
                    option.remove();
                }
            });
            advancedSelector.children().each(function () {
                var group = $(this);
                if (group.children().size() == 0) {
                    group.remove();
                }
            });

        } else {
            advancedSelector.hide();
            customHeadingCheckbox.show();
            headingElement.show();

            advancedSelector.val('');
        }

        if (customHeadingCheckbox.is(':checked')) {
            headingElement.prop('disabled', false);
        } else {
            headingElement.prop('disabled', true);
            headingElement.val(newHeading);
        }
    },

    /**
     *
     */
    rb_init_col_rows: function(){

        var module = this;

        // disable the new column heading field on page load
        $('#id_newheading').prop('disabled', true);
        $('#id_newcustomheading').prop('disabled', true);

        // handle changes to the column pulldowns
        $('select.column_selector').unbind('change');
        $('select.column_selector').bind('change', function() {
            window.onbeforeunload = null;
            module.rb_update_col_row(this);
        });

        // handle changes to the advanced pulldowns
        $('select.advanced_selector').unbind('change');
        $('select.advanced_selector').bind('change', function() {
            window.onbeforeunload = null;
            var column_selector = $('select.column_selector', $(this).parents('tr:first'));
            module.rb_update_col_row(column_selector);
        });

        // handle changes to the customise checkbox
        // use click instead of change event for IE
        $('input.column_custom_heading_checkbox').unbind('click');
        $('input.column_custom_heading_checkbox').bind('click', function() {
            window.onbeforeunload = null;
            var column_selector = $('select.column_selector', $(this).parents('tr:first'));
            module.rb_update_col_row(column_selector);
        });

        // special case for the 'Add another column...' selector
        $('select.new_column_selector').bind('change', function() {
            window.onbeforeunload = null;
            var newHeadingBox = $('input.column_heading_text', $(this).parents('tr:first'));
            var newCheckBox = $('input.column_custom_heading_checkbox', $(this).parents('tr:first'));
            var addbutton = module.rb_init_addbutton($(this));
            if ($(this).val() == 0) {
                // empty and disable the new heading box if no column chosen
                newHeadingBox.val('');
                newHeadingBox.prop('disabled', true);
                addbutton.remove();
                newCheckBox.removeAttr('checked');
                newCheckBox.prop('disabled', true);
            } else {
                // reenable it (binding above will fill the value)
                newCheckBox.prop('disabled', false);
            }
        });

        // init display of advanced column for existing fields
        $('select.column_selector').each(function() {
            module.rb_update_col_row(this);
        });

        // Set up delete button events
        this.rb_init_deletebuttons();

        // Set up hide button events
        this.rb_init_hidebuttons();

        // Set up show button events
        this.rb_init_showbuttons();

        // Set up 'move' button events
        this.rb_init_movedown_btns();
        this.rb_init_moveup_btns();
    },

    /**
     *
     */
    rb_init_addbutton: function(colselector){

        var module = this;
        var newAdvancedBox = $('select.advanced_selector', colselector.parents('tr:first'));
        var newHeadingCheckbox = $('input.column_custom_heading_checkbox', colselector.parents('tr:first'));
        var newHeadingBox = $('input.column_heading_text', colselector.parents('tr:first'));

        var optionsbox = $('td:last', newHeadingBox.parents('tr:first'));
        var newcolinput = colselector.closest('tr').clone();  // clone of current 'Add new col...' tr
        var addbutton = optionsbox.find('.addcolbtn');
        if (addbutton.length == 0) {
            addbutton = this.rb_get_btn_add(module.config.rb_reportid);
        } else {
            // Button already initialised
            return addbutton;
        }

        // Add save button to options
        optionsbox.prepend(addbutton);
        addbutton.unbind('click');
        addbutton.bind('click', function(e) {
            var data = {
                action: 'add',
                sesskey: module.config.user_sesskey,
                id: module.config.rb_reportid,
                col: colselector.val(),
                heading: newHeadingBox.val(),
                advanced: newAdvancedBox.val(),
                customheading : (newHeadingCheckbox.is(':checked') ? 1 : 0)
            }
            var oldAddButtonHtml = addbutton.html();

            e.preventDefault();
            $.ajax({
                url: M.cfg.wwwroot + '/totara/reportbuilder/ajax/column.php',
                type: "POST",
                data: data,
                beforeSend: function() {
                    addbutton.html(module.loadingimg);
                },
                success: function(o) {
                    if (o.length == 0) {
                        alert(M.util.get_string('error', 'moodle'));
                        // Reload the broken page.
                        location.reload();
                    } else if (o.error) {
                        alert(o.error);
                        if (o.noreload) {
                            addbutton.html(oldAddButtonHtml);
                            return;
                        } else {
                            location.reload();
                        }
                    } else {
                        // Add action buttons to row
                        var colid = parseInt(o.result);
                        var hidebutton = module.rb_get_btn_hide(module.config.rb_reportid, colid);
                        var deletebutton = module.rb_get_btn_delete(module.config.rb_reportid, colid);

                        var upbutton = '';
                        var uppersibling = colselector.closest('tr').prev('tr');
                        if (uppersibling.find('select.column_selector').length > 0) {
                            // Create an up button for the newly added col, to be added below
                            var upbutton = module.rb_get_btn_up(module.config.rb_reportid, colid);
                        }

                        addbutton.remove();
                        optionsbox.prepend(hidebutton, deletebutton, upbutton);

                        // Set row atts
                        var columnbox = $('td:first', optionsbox.parents('tr:first'));
                        var columnSelector = $('select.column_selector', columnbox);
                        var newCustomHeading = $('input.column_custom_heading_checkbox', optionsbox.parents('tr:first'));
                        columnSelector.removeClass('new_column_selector');
                        columnSelector.attr('name', 'column'+colid);
                        columnSelector.attr('id', 'id_column'+colid);
                        columnbox.find('select optgroup[label=New]').remove();

                        newAdvancedBox.removeClass('new_advanced_selector');
                        newAdvancedBox.attr('name', 'advanced'+colid);
                        newAdvancedBox.attr('id', 'id_advanced'+colid);

                        newCustomHeading.attr('name', 'customheading'+colid);
                        newCustomHeading.removeAttr('id');

                        newHeadingBox.attr('name', 'heading'+colid);
                        newHeadingBox.attr('id', 'id_heading'+colid);
                        newHeadingBox.closest('tr').attr('colid', colid);

                        // Append a new col select box
                        newcolinput.find('input[name=newheading]').val('');
                        columnbox.closest('table').append(newcolinput);

                        module.rb_reload_option_btns(uppersibling);

                        var coltype = colselector.val().split('-')[0];
                        var colval = colselector.val().split('-')[1];

                        // Remove added col from the new col selector
                        $('.new_column_selector optgroup option[value='+coltype+'-'+colval+']').remove();

                        // Add added col to 'default sort column' selector
                        $('select[name=defaultsortcolumn]').append('<option value="'+coltype+'_'+colval+'">'+module.config.rb_column_headings[coltype+'-'+colval]+'</option>');

                        module.rb_init_col_rows();

                    }
                },
                error: function(h, t, e) {
                    alert(M.util.get_string('error', 'moodle'));
                    // Reload the broken page
                    location.reload();
                }
            }); // ajax
        }); // click event

        return addbutton;
    },

    /**
     *
     */
    rb_init_deletebuttons: function() {
        var module = this;
        $('.reportbuilderform table .deletecolbtn').unbind('click');
        $('.reportbuilderform table .deletecolbtn').bind('click', function(e) {
            e.preventDefault();
            var clickedbtn = $(this);

            confirmed = confirm(M.util.get_string('confirmcoldelete', 'totara_reportbuilder'));

            if (!confirmed) {
                return;
            }

            var colrow = $(this).closest('tr');
            $.ajax({
                url: M.cfg.wwwroot + '/totara/reportbuilder/ajax/column.php',
                type: "POST",
                data: ({action: 'delete', sesskey: module.config.user_sesskey, id: module.config.rb_reportid, cid: colrow.attr('colid')}),
                beforeSend: function() {
                    clickedbtn.replaceWith(module.loadingimg);
                },
                success: function(o) {
                    if (o.success) {
                        var uppersibling = colrow.prev('tr');
                        var lowersibling = colrow.next('tr');

                        // Remove column row
                        colrow.remove();

                        var delcol = o.result;

                        // Fix sibling buttons
                        if (uppersibling.find('select.column_selector').length > 0) {
                            module.rb_reload_option_btns(uppersibling);
                        }
                        if (lowersibling.find('select.column_selector:not(.new_column_selector)').length > 0) {
                            module.rb_reload_option_btns(lowersibling);
                        }

                        // Add deleted col to new col selector
                        var nlabel = rb_ucwords(delcol.optgroup_label);
                        var optgroup = $(".new_column_selector optgroup[label='"+nlabel+"']");
                        if (optgroup.length == 0) {
                            // Create optgroup and append to select
                            optgroup = $('<optgroup label="'+nlabel+'"></optgroup>');
                            $('.new_column_selector').append(optgroup);
                        }

                        if (optgroup.find('option[value='+delcol.type+'-'+delcol.value+']').length == 0) {
                            optgroup.append('<option value="'+delcol.type+'-'+delcol.value+'">'+module.config.rb_column_headings[delcol.type+'-'+delcol.value]+'</option>');
                        }

                        // Remove deleted col from 'default sort column' selector
                        $('select[name=defaultsortcolumn] option[value='+delcol.type+'_'+delcol.value+']').remove();

                        module.rb_init_col_rows();
                    } else {
                        alert(M.util.get_string('error', 'moodle'));
                        location.reload();
                    }
                },
                error: function(h, t, e) {
                    alert(M.util.get_string('error', 'moodle'));
                    // Reload the broken page
                    location.reload();
                }
            }); // ajax

        });

        function rb_ucwords (str) {
            return (str + '').replace(/^([a-z])|\s+([a-z])/g, function($1) {
                return $1.toUpperCase();
            });
        }
    },

    /**
     *
     */
    rb_init_hidebuttons: function() {
        var module = this;
        $('.reportbuilderform table .hidecolbtn').unbind('click');
        $('.reportbuilderform table .hidecolbtn').bind('click', function(e) {
            e.preventDefault();
            var clickedbtn = $(this);

            var colrow = $(this).closest('tr');
            $.ajax({
                url: M.cfg.wwwroot + '/totara/reportbuilder/ajax/column.php',
                type: "POST",
                data: ({action: 'hide', sesskey: module.config.user_sesskey, id: module.config.rb_reportid, cid: colrow.attr('colid')}),
                beforeSend: function() {
                    clickedbtn.find('img').replaceWith(module.loadingimg);
                },
                success: function(o) {
                    if (o.success) {
                        var colid = colrow.attr('colid');

                        var showbtn = module.rb_get_btn_show(module.config.rb_reportid, colid);
                        clickedbtn.replaceWith(showbtn);

                        module.rb_init_col_rows();

                    } else {
                        alert(M.util.get_string('error', 'moodle'));
                        // Reload the broken page
                        location.reload();
                    }

                },
                error: function(h, t, e) {
                    alert(M.util.get_string('error', 'moodle'));
                    // Reload the broken page
                    location.reload();
                }
            }); // ajax

        });
    },

    /**
     *
     */
    rb_init_showbuttons: function() {
        var module = this;
        $('.reportbuilderform table .showcolbtn').unbind('click');
        $('.reportbuilderform table .showcolbtn').bind('click', function(e) {
            e.preventDefault();
            var clickedbtn = $(this);

            var colrow = $(this).closest('tr');
            $.ajax({
                url: M.cfg.wwwroot + '/totara/reportbuilder/ajax/column.php',
                type: "POST",
                data: ({action: 'show', sesskey: module.config.user_sesskey, id: module.config.rb_reportid, cid: colrow.attr('colid')}),
                beforeSend: function() {
                    clickedbtn.find('img').replaceWith(module.loadingimg);
                },
                success: function(o) {
                    if (o.success) {
                        var colid = colrow.attr('colid');

                        var showbtn = module.rb_get_btn_hide(module.config.rb_reportid, colid);
                        clickedbtn.replaceWith(showbtn);

                        module.rb_init_col_rows();

                    } else {
                        alert(M.util.get_string('error', 'moodle'));
                        // Reload the broken page
                        location.reload();
                    }

                },
                error: function(h, t, e) {
                    alert(M.util.get_string('error', 'moodle'));
                    // Reload the broken page
                    location.reload();
                }
            }); // ajax

        });

    },

    /**
     *
     */
    rb_init_movedown_btns: function() {
        var module = this;
        $('.reportbuilderform table .movecoldownbtn').unbind('click');
        $('.reportbuilderform table .movecoldownbtn').bind('click', function(e) {
            e.preventDefault();
            var clickedbtn = $(this);

            var colrow = $(this).closest('tr');

            var colrowclone = colrow.clone();
            // Set the selected option, cause for some reason this don't clone so well...
            colrowclone.find('select.column_selector option[value='+colrow.find('select.column_selector').val()+']').attr('selected', 'selected');
            colrowclone.find('select.advanced_selector option[value='+colrow.find('select.advanced_selector').val()+']').attr('selected', 'selected');

            var lowersibling = colrow.next('tr');

            var lowersiblingclone = lowersibling.clone();
            // Set the selected option, cause for some reason this don't clone so well...
            lowersiblingclone.find('select.column_selector option[value='+lowersibling.find('select.column_selector').val()+']').attr('selected', 'selected');
            lowersiblingclone.find('select.advanced_selector option[value='+lowersibling.find('select.advanced_selector').val()+']').attr('selected', 'selected');

            $.ajax({
                url: M.cfg.wwwroot + '/totara/reportbuilder/ajax/column.php',
                type: "POST",
                data: ({action: 'movedown', sesskey: module.config.user_sesskey, id: module.config.rb_reportid, cid: colrow.attr('colid')}),
                beforeSend: function() {
                    lowersibling.html(module.loadingimg);
                    colrow.html(module.loadingimg);
                    colrowclone.find('td *').hide();
                    lowersiblingclone.find('td *').hide();
                },
                success: function(o) {
                    if (o.success) {
                        // Switch!
                        colrow.replaceWith(lowersiblingclone);
                        lowersibling.replaceWith(colrowclone);

                        colrowclone.find('td *').fadeIn();
                        lowersiblingclone.find('td *').fadeIn();

                        // Fix option buttons
                        module.rb_reload_option_btns(colrowclone);
                        module.rb_reload_option_btns(lowersiblingclone);

                        module.rb_init_col_rows();

                    } else {
                        alert(M.util.get_string('error', 'moodle'));
                        // Reload the broken page
                        location.reload();
                    }

                },
                error: function(h, t, e) {
                    alert(M.util.get_string('error', 'moodle'));
                    // Reload the broken page
                    location.reload();
                }
            }); // ajax

        });
    },

    /**
     *
     */
    rb_init_moveup_btns: function() {
        var module = this;
        $('.reportbuilderform table .movecolupbtn').unbind('click');
        $('.reportbuilderform table .movecolupbtn').bind('click', function(e) {
            e.preventDefault();
            var clickedbtn = $(this);

            var colrow = $(this).closest('tr');

            var colrowclone = colrow.clone();
            // Set the selected option, cause for some reason this don't clone so well...
            colrowclone.find('select.column_selector option[value='+colrow.find('select.column_selector').val()+']').attr('selected', 'selected');
            colrowclone.find('select.advanced_selector option[value='+colrow.find('select.advanced_selector').val()+']').attr('selected', 'selected');

            var uppersibling = colrow.prev('tr');

            var uppersiblingclone = uppersibling.clone();
            // Set the selected option, cause for some reason this don't clone so well...
            uppersiblingclone.find('select.column_selector option[value='+uppersibling.find('select.column_selector').val()+']').attr('selected', 'selected');
            uppersiblingclone.find('select.advanced_selector option[value='+uppersibling.find('select.advanced_selector').val()+']').attr('selected', 'selected');

            $.ajax({
                url: M.cfg.wwwroot + '/totara/reportbuilder/ajax/column.php',
                type: "POST",
                data: ({action: 'moveup', sesskey: module.config.user_sesskey, id: module.config.rb_reportid, cid: colrow.attr('colid')}),
                beforeSend: function() {
                    uppersibling.html(module.loadingimg);
                    colrow.html(module.loadingimg);

                    colrowclone.find('td *').hide();
                    uppersiblingclone.find('td *').hide();
                },
                success: function(o) {
                    if (o.success) {
                        // Switch!
                        colrow.replaceWith(uppersiblingclone);
                        uppersibling.replaceWith(colrowclone);

                        colrowclone.find('td *').fadeIn();
                        uppersiblingclone.find('td *').fadeIn();

                        // Fix option buttons
                        module.rb_reload_option_btns(colrowclone);
                        module.rb_reload_option_btns(uppersiblingclone);

                        module.rb_init_col_rows();

                    } else {
                        alert(M.util.get_string('error', 'moodle'));
                        // Reload the broken page
                        location.reload();
                    }

                },
                error: function(h, t, e) {
                    alert(M.util.get_string('error', 'moodle'));
                    // Reload the broken page
                    location.reload();
                }
            }); // ajax

        });
    },

    /**
     *
     */
    rb_reload_option_btns: function(colrow) {
        var optionbox = colrow.children('td').filter(':last');
        var hideshowbtn = optionbox.find('.hidecolbtn');
        if (hideshowbtn.length == 0) {
            hideshowbtn = optionbox.find('.showcolbtn');
        }
        hideshowbtn = hideshowbtn.closest('a');

        // Remove all option buttons
        //optionbox.find('a:not(.hidecolbtn):not(.showcolbtn)').remove();
        optionbox.find('a').remove();
        optionbox.find('img').remove();

        // Replace with btns with updated ones
        var colid = colrow.attr('colid');
        var deletebtn = this.rb_get_btn_delete(this.config.rb_reportid, colid);
        var upbtn = '<img src="' + M.util.image_url('spacer', 'moodle') +'" alt="" class="spacer" width="11px" height="11px"/>';
        if (colrow.prev('tr').find('select.column_selector').length > 0) {
            upbtn = this.rb_get_btn_up(this.config.rb_reportid, colid);
        }
        var downbtn = '<img src="' + M.util.image_url('spacer', 'moodle') +'" alt="" class="spacer" width="11px" height="11px"/>';
        if (colrow.next('tr').next('tr').find('select.column_selector').length > 0) {
            downbtn = this.rb_get_btn_down(this.config.rb_reportid, colid);
        }

        optionbox.append(hideshowbtn, deletebtn, upbtn, downbtn);
    },

    /**
     *
     */
    rb_get_btn_hide: function(reportid, colid) {
        return $('<a href="' + M.cfg.wwwroot + '/totara/reportbuilder/columns.php?id=' + reportid + '&cid='+colid+'&h=1" class="hidecolbtn action-icon"><img src="' + M.util.image_url('t/hide', 'moodle') +'" alt="' + M.util.get_string('hide', 'totara_reportbuilder') + '" class="iconsmall" /></a>');
    },

    rb_get_btn_show: function(reportid, colid) {
        return $('<a href="' + M.cfg.wwwroot + '/totara/reportbuilder/columns.php?id=' + reportid + '&cid='+colid+'&h=0" class="showcolbtn action-icon"><img src="' + M.util.image_url('t/show', 'moodle') +'" alt="' + M.util.get_string('show', 'totara_reportbuilder') + '" class="iconsmall" /></a>');
    },

    rb_get_btn_delete: function(reportid, colid) {
        return $('<a href="' + M.cfg.wwwroot + '/totara/reportbuilder/columns.php?id=' + reportid + '&cid='+colid+'&d=1" class="deletecolbtn action-icon"><img src="' + M.util.image_url('t/delete', 'moodle') +'" alt="' + M.util.get_string('delete', 'totara_reportbuilder') + '" class="iconsmall" /></a>');
    },

    rb_get_btn_up: function(reportid, colid) {
        return $('<a href="' + M.cfg.wwwroot + '/totara/reportbuilder/columns.php?id=' + reportid + '&cid='+colid+'&m=up" class="movecolupbtn action-icon"><img src="' + M.util.image_url('t/up', 'moodle') +'" alt="' + M.util.get_string('moveup', 'totara_reportbuilder') + '" class="iconsmall" /></a>');
    },

    rb_get_btn_down: function(reportid, colid) {
        return $('<a href="' + M.cfg.wwwroot + '/totara/reportbuilder/columns.php?id=' + reportid + '&cid='+colid+'&m=down" class="movecoldownbtn action-icon"><img src="' + M.util.image_url('t/down', 'moodle') +'" alt="' + M.util.get_string('movedown', 'totara_reportbuilder') + '" class="iconsmall" /></a>');
    },

    rb_get_btn_add: function(reportid) {
        return $('<a href="' + M.cfg.wwwroot + '/totara/reportbuilder/columns.php?id=' + reportid + '" class="addcolbtn"><input type="button" value="' + M.util.get_string('add', 'totara_reportbuilder') + '" /></a>');
    }
};
