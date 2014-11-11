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
 * @author Dave Wallace <dave.wallace@kineo.co.nz>
 * @package totara
 * @subpackage program
 */
M.totara_programassignment = M.totara_programassignment || {

    Y: null,
    // optional php params and defaults defined here, args passed to init method
    // below will override these values
    config: {},

    // reference to dialogs
    totaraDialog_program_cat: null,

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

        // check if required param id is available
        if (!this.config.id) {
            throw new Error('M.totara_programassignment.init()-> Required config \'id\' not available.');
        }

        // check jQuery dependency and continue with setup
        if (typeof $ === 'undefined') {
            throw new Error('M.totara_programassignment.init()-> jQuery dependency required for this module to function.');
        }

        // attach a function to the page to prevent unsaved changes from being lost
        // when navigating away
        window.onbeforeunload = function(e) {

            var modified = module.isFormModified();

            if (modified === true) {
                var str = M.util.get_string('youhaveunsavedchanges', 'totara_program');
                var e = e || window.event;
                // For IE and Firefox (before version 4)
                if (e) {
                    e.returnValue = str;
                }
                // For Safari
                return str;
            }
        };


        /**
         * Dialog and dialog handler definitions, totaraDialog_program_cat is
         * saved on the module instance as it is referenced in the code
         * following this module definition
         */
        this.totaraDialog_program_cat = function(category, catid, name, find_url, title) {

            // Store some bits
            this.category = category;
            this.catid = catid;
            this.name = name;
            this.url = M.cfg.wwwroot+'/totara/program/assignment/';
            this.ajax_url = this.url + 'get_item.php?cat=' + this.name;

            // Setup the handler
            var handler = new totaraDialog_handler_treeview_multiselect();

            var default_url = this.url + find_url;

            var self = this;
            var buttonsObj = {};
            buttonsObj[M.util.get_string('cancel','totara_program')] = function() { self.hide(); };
            buttonsObj[M.util.get_string('ok','totara_program')] = function() { self.category.save(); };

            // Call the parent dialog object and link us
            totaraDialog.call(
            this,
            'add-assignment-dialog-' + catid,
            'add-assignment-' + catid,
            {
                buttons: buttonsObj,
                title: '<h2>' + title +'</h2>'
            },
            default_url,
            handler);

            // Modify the open function to dynamically get values from the category
            this.old_open = this.open;
            this.open = function() {
                var selected = this.category.get_itemids();
                selected = selected.join(",");
                this.default_url += '&selected=' + selected;
                this.old_open();
            };

            // Add a handler for any click events for the complete column

        };


        // The completion dialog handler
        var totaraDialog_completion_handler = function() {};

        totaraDialog_completion_handler.prototype = new totaraDialog_handler();

        totaraDialog_completion_handler.prototype.first_load = function() {
            M.totara_core.build_datepicker(module.Y, 'input[name="completiontime"]', M.util.get_string('datepickerlongyeardisplayformat', 'totara_core'));
            $('#ui-datepicker-div').css('z-index',1600);
        };

        totaraDialog_completion_handler.prototype.every_load = function() {
            var completiontime = this._dialog.item.get_completion_time();
            var completionevent = this._dialog.item.get_completion_event();
            var completioninstance = this._dialog.item.get_completion_instance();
            // TODO SCANMSG: check datatype usage of 'COMPLETION_EVENT_NONE' and similar args stored in config
            if (typeof completionevent === 'undefined' ||
                completionevent == module.config.COMPLETION_EVENT_NONE) {
                if (typeof completiontime === 'undefined' ||
                    completiontime != module.config.COMPLETION_TIME_NOT_SET) {
                    $('.completiontime', this._container).val(completiontime);
                }
            }
            else {
                var parts = completiontime.split(" ");
                $('#timeamount', this._container).val(parts[0]);
                $('#timeperiod', this._container).val(parts[1]);
                $('#eventtype', this._container).val(completionevent);
                $('#instance').val(completioninstance);
                $('#instancetitle').text(this._dialog.item.completioneventname);
            }
            // rebind placeholder for date picker
            $('input[placeholder], textarea[placeholder]').placeholder();
        };

        // The completion dialog
        var totaraDialog_completion = function() {

            //this.item = item;
            //this.url = url + 'choose_completion.php';

            // Setup the handler
            var handler = new totaraDialog_completion_handler();

            // Store reference to this
            var self = this;
            var buttonsObj = {};
            buttonsObj[M.util.get_string('cancel','totara_program')] = function() { handler._cancel() };

            // Call the parent dialog object and link us
            totaraDialog.call(
            this,
            'completion-dialog',
            'unused', // buttonid unused
            {
                buttons: buttonsObj,
                title: '<h2>'+ M.util.get_string('completioncriteria', 'totara_program') +'</h2>'
            },
            this.url,
            handler
            );

            this.old_open = this.open;
            this.open = function() {
                this.old_open();
                this.dialog.height(150);
            };

            $(document).on('click', '.fixeddate', function(event) {
                var completiontime = $('.completiontime', self.handler._container).val();
                var completionevent = module.config.COMPLETION_EVENT_NONE;
                var completioninstance = 0;

                var dateformat = new RegExp(M.util.get_string('datepickerlongyearregexjs', 'totara_core'));
                if (dateformat.test(completiontime) == false) {
                    alert(M.util.get_string('pleaseentervaliddate', 'totara_program', M.util.get_string('datepickerlongyearplaceholder', 'totara_core')));
                }
                else {
                    self.item.update_completiontime(completiontime, completionevent, completioninstance);
                    self.hide();
                }
            });

            $(document).on('click', '.relativeeventtime', function(event) {

                var timeunit = $('#timeamount', self.handler._container).val();
                var timeperiod = $('#timeperiod option:selected', self.handler._container).val();
                var completiontime = timeunit + " " + timeperiod;

                var completionevent = $('#eventtype option:selected', self.handler._container).val();
                var completioninstance = $('#instance', self.handler._container).val() ? $('#instance', self.handler._container).val() : self.item.completioninstance.val();
                var unitformat = /^\d{1,3}$/;
                if (unitformat.test(timeunit) === false) {
                    alert(M.util.get_string('pleaseentervalidunit', 'totara_program'));
                } else if (completioninstance == 0 && completionevent != module.config.COMPLETION_EVENT_FIRST_LOGIN &&
                    completionevent != module.config.COMPLETION_EVENT_ENROLLMENT_DATE) {
                    alert(M.util.get_string('pleasepickaninstance', 'totara_program'));
                } else {
                    self.item.update_completiontime(completiontime, completionevent, completioninstance);
                    self.hide();
                }
            });
        };

        // The save changes confirmation dialog
        var totaraDialog_savechanges = function() {

            // Setup the handler
            var handler = new totaraDialog_handler();

            // Store reference to this
            var self = this;
            var buttonsObj = {};
            buttonsObj[M.util.get_string('editassignments','totara_program')] = function() { handler._cancel() };
            buttonsObj[M.util.get_string('saveallchanges','totara_program')] = function() { self.save() };

            // Call the parent dialog object and link us
            totaraDialog.call(
            this,
            'savechanges-dialog',
            'unused', // buttonid unused
            {
                buttons: buttonsObj,
                title: '<h2>'+M.util.get_string('confirmassignmentchanges', 'totara_program')+'</h2>'
            },
            'unused', // default_url unused
            handler
            );

            this.old_open = this.open;
            this.open = function(html, table, rows) {
                // Do the default open first to get everything ready
                this.old_open();

                this.dialog.height(270);

                // Now load the custom html content
                this.dialog.html(html);

                this.table = table;
                this.rows = rows;
            };

            // Don't load anything
            this.load = function(url, method) {
            }

        };

        // The completion event dialog
        totaraDialog_completion_event = function() {

            // Setup the handler
            var handler = new totaraDialog_handler_treeview_singleselect('instance', 'instancetitle');

            // Store reference to this
            var self = this;
            var buttonsObj = {};
            buttonsObj[M.util.get_string('cancel','totara_program')] = function() { handler._cancel() };
            buttonsObj[M.util.get_string('ok','totara_program')] = function() { self.save() };

            // Call the parent dialog object and link us
            totaraDialog.call(
            this,
            'completion-event-dialog',
            'unused2', // buttonid unused
            {
                buttons: buttonsObj,
                title: '<h2>'+M.util.get_string('chooseitem', 'totara_program') + module.config.display_selected_completion_event +'</h2>'
            },
            'unused2', // default_url unused
            handler
            );

            this.save = function() {
                var selected_val = $('#treeview_selected_val_'+this.handler._title).val();
                var selected_text = $('#treeview_selected_text_'+this.handler._title).text();

                $('#instance').val(selected_val);
                $('#instancetitle').text(selected_text);

                this.hide();
            }

            this.clear = function() {
                $('#instance').val(0);
                $('#instancetitle').text('');
            }

            $(document).on('change', '#eventtype', function() {
                if ($('#eventtype').val() != module.config.COMPLETION_EVENT_FIRST_LOGIN &&
                    $('#eventtype').val() != module.config.COMPLETION_EVENT_ENROLLMENT_DATE) {
                    $('#instance').val(0);
                    $('#instancetitle').text(M.util.get_string('none', 'moodle'));
                }
            });
        };


        /**
         * Event bindings, Dialog instantiation and setup
         */
        // remove the 'unsaved changes' confirmation when submitting the form
        $('form[name="form_prog_assignments"]').submit(function(){
            window.onbeforeunload = null;
        });

        // Remove the 'unsaved changes' confirmation when clicking the 'Cancel program management' link
        $('#cancelprogramedits').click(function(){
            window.onbeforeunload = null;
            return true;
        });

        $('#category_select button').click(function() {
            var url = M.cfg.wwwroot+'/totara/program/assignment/get_items.php?progid='+module.config.id;

            var select = $("#category_select select");
            var option = $("option:selected", select);

            // Do nothing if default option is selected
            if (option.val() === '') {
                return false;
            }

            // Ajax call to get the html for the new category box
            $.getJSON(url + '&catid=' + option.val(), function(data) {

                // Need to check that it doesn't already exist before we add it
                if ($("#category-" + option.val()).length > 0) {
                    return;
                }

                // Add the category to the bottom of the list of categories
                $("#assignment_categories").append(data['html']);

                // Remove the option from the drop down
                option.remove();

                // Remove the dropdown box if no options are left
                if ($("option",select).length === 1) {
                    // Remove the category select if there's only the default option left
                    $('#category_select').remove();
                } else {
                    $('#category_select option:eq(1)').attr('selected', 'selected');
                }
            });

            return false;
        });

        // Add a function to launch the save changes dialog
        $('input[name="savechanges"]').click(function(event) {
            return module.handleSaveChanges(event);
        });

        totaraDialogs['completion'] = new totaraDialog_completion();
        totaraDialogs['savechanges'] = new totaraDialog_savechanges();
        totaraDialogs['completionevent'] = new totaraDialog_completion_event();

        // call assignment setup, in window scope
        program_assignment.setup();

        //
        this.storeInitialFormValues();
    },

    /**
     * Called by AJAX callback data inserted into DOM
     */
    add_category: function(id, name, find_url, title){
        program_assignment.add_category(new category(id, name, find_url, title));
    },

    /**
     *
     */
    handleSaveChanges: function(event){

        // no need to display the confirmation dialog if there are no changes to save
        if (!this.isFormModified()) {
            window.onbeforeunload = null;
            return true;
        }

        var dialog = totaraDialogs['savechanges'];

        if (dialog.savechanges === true) {
            window.onbeforeunload = null;
            return true;
        }

        // Load a html template in, and switch
        var templateJSON = this.config.confirmation_template;
        var template = $(templateJSON['html']);

        var totalAdded = 0;
        var totalRemoved = 0;
        if (program_assignment.categories.length > 0) {
            for (var x in program_assignment.categories) {
                var category = program_assignment.categories[x];

                totalAdded += category.num_added_users;
                totalRemoved += category.num_removed_users;

                $('.added_' + category.id, template).html(category.num_added_users);
                $('.removed_' + category.id, template).html(category.num_removed_users);
            }
        }

        $('.total_added', template).html(totalAdded);
        $('.total_removed', template).html(totalRemoved);

        var html = template.html();

        // don't prompt if no assignments have been added or removed
        // the user may still have changed the completion criteria but
        // this doesn't require a prompt
        if (totalAdded === 0 && totalRemoved === 0) {
            return true;
        }

        totaraDialogs['savechanges'].open(html);
        totaraDialogs['savechanges'].save = function() {
            totaraDialogs['savechanges'].savechanges = true;
            this.hide();
            $('input[name="savechanges"]').trigger('click');
        };
        event.preventDefault();
    },

    /**
     * Stores the initial values of the form when the page is loaded
     */
    storeInitialFormValues: function(){
        var form = $('form[name="form_prog_assignments"]');

        $('input[type="text"], textarea, select', form).each(function() {
            $(this).attr('initialValue', $(this).val());
        });

        $('input[type="checkbox"]', form).each(function() {
            var checked = $(this).is(':checked') ? 1 : 0;
            $(this).attr('initialValue', checked);
        });
    },

    /**
     * Checks if the form is modified by comparing the initial and current values
     */
    isFormModified: function(){
        var form = $('form[name="form_prog_assignments"]');
        var isModified = false;

        // Check if text inputs or selects have been changed
        $('input[type="text"], select', form).each(function() {
            if ($(this).attr('initialValue') != $(this).val()) {
                isModified = true;
            }
        });

        // Check if check boxes have changed
        $('input[type="checkbox"]', form).each(function() {
            var checked = $(this).is(':checked') ? 1 : 0;
            if ($(this).attr('initialValue') != checked) {
                isModified = true;
            }
        });

        // Check if textareas have been changed
        $('textarea', form).each(function() {
            // See if there's a tiny MCE instance for this text area
            var instance = undefined;
            if (typeof(tinyMCE) != 'undefined') {
                instance = tinyMCE.getInstanceById($(this).attr('id'));
            }
            if (instance !== undefined && typeof instance.isDirty == 'function') {
                if (instance.isDirty()) {
                    isModified = true;
                }
            } else {
                // normal textarea (not tinyMCE)
                if ($(this).attr('initialValue') != $(this).val()) {
                    isModified = true;
                }
            }
        });

        // Check if assignments have been added or removed
        if (program_assignment.num_added_items > 0 || program_assignment.num_deleted_items > 0) {
            isModified = true;
        }

        // Check if assignments have been added or removed
        if (program_assignment.is_modified === true) {
            isModified = true;
        }

        return isModified;
    }
};

/***
 *** Define and create the object which acts as the main program assignment
 ***/
program_assignment = new function() {
    this.categories = [];
    this.num_deleted_items = 0;
    this.num_added_items = 0;
    this.total_count = 0;
    this.is_setup = false;
    this.is_modified = false;

    this.add_category = function(category) {
        category.main = this;
        this.categories.push(category);
        category.setup();
    };

    this.all_items_have_completion_times = function() {
        var result = true;
        $.each(this.categories, function(index, category) {
            $.each(category.items, function(index, item) {
                if (item.completiontime.length == 0 || item.completiontime.val() === M.program_assignment.config.COMPLETION_TIME_NOT_SET) {
                    result = false;
                }
            });
        });
        return result;
    };

    this.update_total_user_count = function() {
        var count = 0;
        $.each(this.categories, function(index, category) {
            count += category.user_count;
        });
        this.total_count = count;

        if (this.is_setup) {
            $('.overall_total span.total').html(this.total_count);
        }
    };

    this.setup = function() {
        this.is_setup = true;
        this.update_total_user_count();
    };
};

/***
 *** Define the category object for re-use, but don't actually instantiate any!
 ***/
function category(id, name, find_url, title) {
    this.id = id;
    this.name = name;
    this.items = [];
    this.url = M.cfg.wwwroot + '/totara/program/assignment/';
    this.title = title;
    this.ajax_url = this.url + 'get_item.php?cat=' + this.name;

    // Jquery objects for the element and the table inside
    this.element = $('#category-' + this.id);
    this.table = $('table', this.element);

    // Instaniate the dialog used to add new items
    this.dialog_additem = new M.totara_programassignment.totaraDialog_program_cat(this, id, name, find_url, title);
    this.num_initial_users = 0;
    this.num_added_users = 0;
    this.num_removed_users = 0;

    // Add a span for printing the total number
    this.user_count = 0;
    this.user_count_label = $('.user_count',this.element);

    /**
     ** Adds an item
     **/
    this.add_item = function(element,isexistingitem) {
        var newitem = new item(this, element, isexistingitem);

        this.items.push(newitem);

        if (!isexistingitem) {
            this.main.num_added_items++;
            this.num_added_users += newitem.users;
        }
        else {
            this.initial_user_count += newitem.users;
        }

        this.check_table_hidden_status();
    };

    /**
     ** Creates an element and then adds it
     **/
    this.create_item = function(html) {
        var element = $(html);

        // Add the item element to the table
        this.table.append(element);

        this.add_item(element,false);
    };

    this.remove_item = function(item) {
        // Remove the element from the table
        item.element.remove();

        // Remove the item from the array of items
        this.items = $.grep(this.items, function (element, x) {
            if (element == item) {
            return false;
            }
            return true;
        });

        if (item.isexistingitem) {
            this.main.num_deleted_items++;
            this.num_removed_users += item.users;
        }
        else {
            this.main.num_added_items--;
            this.num_added_users -= item.users;
        }

        this.check_table_hidden_status();
    };

    /**
     ** Checks if the item id exists in this category
     **/
    this.item_exists = function(itemid) {
        for (var x in this.items) {
            if (this.items[x].itemid == itemid) {
             return true;
            }
        }
        return false;
    };

    /**
     ** Gets a list of item ids in this category
     **/
    this.get_itemids = function() {
        var itemids = [];
        for (var x in this.items) {
            itemids.push(this.items[x].itemid);
        }
        return itemids;
    };

    this.check_table_hidden_status = function() {
        if (this.items.length === 0) {
            $('th',this.table).hide();
        }
        else {
            $('th',this.table).show();
        }


        this.update_user_count();
    };

    this.update_user_count = function() {
        this.user_count = 0;
        for (var x in this.items) {
            this.user_count += this.items[x].users;

        }
        $(this.user_count_label).text(this.user_count);

        this.main.update_total_user_count();
    };

    var self = this;

    /**
     ** Saves the users selected, called when the user tries to save and exit from the add dialog
     **/
    this.save = function() {
        var elements = $('.selected > div > span', this._container);
        var newids = [];

        // Loop through the selected elements
        elements.each(function() {

            // Get id
            var itemid = $(this).attr('id').split('_');
            itemid = itemid[itemid.length-1];  // The last item is the actual id
            itemid = parseInt(itemid);

            if (!self.item_exists(itemid)) {
                newids.push(itemid);
            }
        });

        if (newids.length > 0) {
            this.dialog_additem.showLoading();

            $.getJSON(this.ajax_url + '&itemid=' + newids.join(','), function(data) {

                $.each(data['rows'], function(index, html) {
                    self.create_item(html);
                });

                self.dialog_additem.hide();
            });
        } else {
            this.dialog_additem.hide();
        }
    };

    this.setup = function() {
        // Go through existing rows and add them
        // NB: when no items are set in this category html_writer::table adds a blank row
        // with one colspanned TD for strict XHTML compatability - we want to ignore that row
        $('tbody tr',this.element).each(function() {
            if ($(this)[0].children.length != 1) {
                self.add_item($(this), true);
            }
        });

        // Check if we should hide the table header
        this.check_table_hidden_status();
    };
}

/***
 *** Defines an item in a category
 ***/
function item(category, element, isexistingitem) {
    var self = this;
    // Create jQuery element
    this.element = element;
    this.category = category;
    this.isexistingitem = isexistingitem;
    // Retreive and store item id
    this.itemid = this.element.find('input[name^="item"]').attr('name');
    this.itemid = this.itemid.replace('item['+ this.category.id +'][','');
    this.itemid = parseInt(this.itemid.replace(']',''));

    if (this.category.name == "individuals") {
        // Hard code individuals to have 1 user
        this.users = 1;
    }
    else {
        // Retreive and store the number of affected users
        this.usersElement = this.element.find('td:last');
        this.users = parseInt(this.usersElement.html());
    }
    this.initial_users = this.users;

    this.removeurl = this.category.url + 'remove_item.php?cat=' + this.category.name + '&itemid=' + this.itemid;

    // Hidden values
    this.completiontime = this.element.find('input[name^="completiontime"]');
    this.completionevent = this.element.find('input[name^="completionevent"]');
    this.completioninstance = this.element.find('input[name^="completioninstance"]');
    this.includechildren = this.element.find('[name^="includechildren"]');

    // Label
    this.completionlink = this.element.find('.completionlink');
    this.update_completiontime = function(completiontime, completionevent, completioninstance) {
    // Update the hidden inputs

        var url = this.category.url + 'completion/get_completion_string.php' +
                  '?completiontime=' + completiontime +
                  '&completionevent=' + completionevent +
                  '&completioninstance=' + completioninstance;

        var original = this.completionlink.html();
        this.completionlink.html('Loading..');

        $.get(url, function(data) {
            if (data == 'error') {
                // Put back to the original
                self.completionlink.html(original);
            } else {
                var deletecompletiondatelink = self.completionlink.parent().find('.deletecompletiondatelink');
                if (data == M.util.get_string('setcompletion', 'totara_program')) {
                    //remove deletedate link if it exists
                    if (deletecompletiondatelink.length > 0) {
                        deletecompletiondatelink.remove();
                    }
                } else {
                    //add deletedate link if it does not exist
                    if (deletecompletiondatelink.length == 0) {
                        var newlink = $('<a href="#" class="deletecompletiondatelink"><img class="smallicon" src="'+M.util.image_url('t/delete', 'moodle')+'" alt="'+M.util.get_string('removecompletiondate', 'totara_program')+'" title="'+M.util.get_string('removecompletiondate', 'totara_program')+'" /></a>');
                        self.completionlink.parent().append(newlink);
                    }
                }
                self.completionlink.html(data);
                if (typeof completiontime === 'undefined' || completiontime == M.totara_programassignment.config.COMPLETION_TIME_NOT_SET) {
                    // Completion time no longer set. Remove hidden element if it exists.
                    if (self.completiontime.length > 0) {
                        self.completiontime.remove();
                    }
                } else {
                    if (self.completiontime.length > 0) {
                        // Hidden form element already exists - update value.
                        self.completiontime.val(completiontime);
                    } else {
                        // Append new hidden element.
                        $('<input>').attr({
                                type: 'hidden',
                                name: 'completiontime['+self.category.id+']['+self.itemid+']',
                                value: completiontime
                        }).insertBefore(self.completionlink);
                    }
                }

                if (typeof completionevent === 'undefined' || completionevent == M.totara_programassignment.config.COMPLETION_EVENT_NONE) {
                    // Completion event no longer set. Remove hidden element if it exists.
                    if (self.completionevent.length > 0) {
                        self.completionevent.remove();
                    }
                } else {
                    if (self.completionevent.length > 0) {
                        // Hidden form element already exists - update value.
                        self.completionevent.val(completionevent);
                    } else {
                        // Append new hidden element.
                        $('<input>').attr({
                                type: 'hidden',
                                name: 'completionevent['+self.category.id+']['+self.itemid+']',
                                value: completionevent
                        }).insertBefore(self.completionlink);
                    }
                }

                if (typeof completioninstance === 'undefined' || completioninstance == 0) {
                    // Completion instance no longer set. Remove hidden element if it exists.
                    if (self.completioninstance.length > 0) {
                        self.completioninstance.remove();
                    }
                } else {
                    if (self.completioninstance.length > 0) {
                        // Hidden form element already exists - update value.
                        self.completioninstance.val(completioninstance);
                    } else {
                        // Append new hidden element.
                        $('<input>').attr({
                                type: 'hidden',
                                name: 'completioninstance['+self.category.id+']['+self.itemid+']',
                                value: completioninstance
                        }).insertBefore(self.completionlink);
                    }
                }

                // Update stored values in case multiple changes are
                // made without reloading page.
                self.completiontime = self.element.find('input[name^="completiontime"]');
                self.completionevent = self.element.find('input[name^="completionevent"]');
                self.completioninstance = self.element.find('input[name^="completioninstance"]');

                // set a flag to indicate that the program assignments has been modified but not saved
                self.category.main.is_modified = true;
            }
        });
    };

    this.get_completion_time = function() { return this.completiontime.val(); };
    this.get_completion_event = function() { return this.completionevent.val(); };
    this.get_completion_instance = function() { return this.completioninstance.val(); };
    this.get_completion_link = function() { return this.completionlink.html(); };

    // Update the user count, and notifies the parent category
    this.update_user_count = function(count) {

        // Determine if we were at base number
        if (this.users == this.initial_users) {
            if (count > this.users) { // See if we have increased our user count
                // If so, increase the parent count
                this.category.num_added_users += (count - this.users);
            }
            else if (count < this.users) { // See if we have decreased our user count
                this.category.num_removed_users += (this.users - count);
            }
        } else {
            // We are not at our initial count, so its likely we need to decrease the added/removed count
            if (count > this.users) { // See if we have increased our user count
                // If so, increase the parent count
                this.category.num_removed_users -= (count - this.users);
            }
            else if (count < this.users) { // See if we have decreased our user count
                this.category.num_added_users -= (this.users - count);
            }
        }

        this.users = count;
        this.usersElement.html(this.users);
        this.category.update_user_count();

    };

    // Do an ajax request to get an updated count
    // includechildren is true or false
    this.get_user_count = function(includechildren) {

        var url = this.category.url + 'get_item_count.php?cat=' + this.category.name + '&itemid=' + this.itemid + '&include=' + includechildren;

        this.set_loading();

        $.getJSON(url, function(data) {
            var count = parseInt(data['count']);
            self.update_user_count(count);
        });
    };

    this.set_loading = function() {
        var loadingImg = '<img src="'+M.util.image_url('i/loading_small', 'moodle')+'" alt="'+M.util.get_string('loading', 'admin')+'"/>';
        this.usersElement.html(loadingImg);
    };

    // Add handler to remove this element
    this.element.on('click', '.deletelink', function(event){
        event.preventDefault();
        self.category.remove_item(self);
    });

    // Add handler to remove completion dates
    this.element.on('click', '.deletecompletiondatelink', function(event){
        event.preventDefault();
        self.update_completiontime(M.totara_programassignment.config.COMPLETION_TIME_NOT_SET, 0, 0);
    });

    // Set the current completion name (contained between the single quotes).
    this.get_completioneventname = function(completioneventname) {
        this.completioneventname = completioneventname;
    }

    // Add handler to add completion dates.
    this.element.on('click', '.completionlink', function(event){
        var i, completioneventname = '';
        var completionname = $(this).text(); // Get the completion name currently selected.

        // Check if the completion name contains single quotes.
        if (completionname.indexOf("'") != -1) {
            // Get the name contained between the single quotes.
            completionname = completionname.substring(completionname.indexOf("'"))
            for (i=1; i<completionname.length-1; i++) {
                completioneventname = completioneventname + completionname[i];
            }
        }
        self.get_completioneventname(completioneventname);
        event.preventDefault();
        totaraDialogs['completion'].item = self;
        totaraDialogs['completion'].default_url = self.category.url + 'set_completion.php';
        totaraDialogs['completion'].open();
    });

    // Add handler for the include children being toggled
    $(this.includechildren).change(function(event) {
        if (this.tagName.toLowerCase() == 'input') {
            var value = $(this).is(':checked') ? 1 : 0;
            self.get_user_count(value);
        }
        else if (this.tagName.toLowerCase() == 'select') {
            self.get_user_count($(this).val());
        }
    });

}
