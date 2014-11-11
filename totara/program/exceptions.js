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
 * @package totara
 * @subpackage program
 */
M.totara_programexceptions = M.totara_programexceptions || {

    Y: null,
    // optional php params and defaults defined here, args passed to init method
    // below will override these values
    config: {
        id:null, // required param
        search_term:'' // optional param
    },

    items: null,

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
            throw new Error('M.totara_programexceptions.init()-> Required config \'id\' not available.');
        }

        // Create our Items object
        this.createItems();

        // Loop through the exceptions and create item objects
        $('#exceptions tr').each(function(i,val) {
            if (i === 0) { return; }

            var typeId = parseInt($(this).find('span.type').html());
            var selectionId = 0;
            if (typeId === module.config.EXCEPTIONTYPE_TIME_ALLOWANCE) {
                selectionId = module.config.SELECTIONTYPE_TIME_ALLOWANCE;
            }
            else if (typeId === module.config.EXCEPTIONTYPE_ALREADY_ASSIGNED) {
                selectionId = module.config.SELECTIONTYPE_ALREADY_ASSIGNED;
            }
            else if (typeId === module.config.EXCEPTIONTYPE_COMPLETION_TIME_UNKNOWN) {
                selectionId = module.config.SELECTIONTYPE_COMPLETION_TIME_UNKNOWN;
            }
            else if (typeId === module.config.EXCEPTIONTYPE_DUPLICATE_COURSE) {
                selectionId = module.config.SELECTIONTYPE_DUPLICATE_COURSE;
            }
            module.items.addItem(module.createItem(selectionId,typeId,this));
        });

        // Initially select only the actions that are available to the current selection
        if (typeof(this.items.handledActions) != 'undefined') {
            this.items.limitActions(this.items.handledActions);
        }
        // Hook onto the type selection dropdown
        $('#selectiontype').change(function() {

            var url = M.cfg.wwwroot+'/totara/program/exception/updateselections.php?id='+module.config.id,
                searchterm = module.config.search_term,
                selectedId = parseInt($(this).find('option:selected').val());

            $.getJSON(url + '&action=selectmultiple&selectiontype=' + selectedId + '&search=' + searchterm, function(data) {

                var totalselected = data['selectedcount'];
                var selectiontype = data['selectiontype'];
                var handledactions = data['handledactions'];

                if (selectiontype == module.config.SELECTIONTYPE_NONE) {
                    module.items.unselectAll();
                } else if (selectiontype == module.config.SELECTIONTYPE_ALL) {
                    module.items.selectAll(totalselected);
                } else {
                    module.items.selectOnly(selectedId, totalselected);
                }

                module.items.limitActions(handledactions);
            });
        });

        // Create a dialog to handle stuff
        var handler = new totaraDialog_handler();
        var buttonsObj = {};
            buttonsObj[M.util.get_string('cancel', 'moodle')] = function() { handler._cancel(); };
            buttonsObj[M.util.get_string('ok', 'moodle')] = function() { dialog.save(M.cfg.wwwroot+'/totara/program/exception/resolve.php?id='+module.config.id); };
        var dialog = new totaraDialog(
                'applyaction',
                'applyactionbutton',
                {
                    buttons: buttonsObj,
                    title: '<h2>'+M.util.get_string('confirmresolution', 'totara_program')+'</h2>',
                            height: '250'
                },
                M.cfg.wwwroot+'/totara/program/exception/confirm_resolution.php',
                handler
            );

        // Modify it a little bit to check
        dialog.base_url = dialog.default_url;
        dialog.old_open = dialog.open;
        dialog.open = function() {

            var action = $('#selectionaction option:selected').val(),
                selectedexceptionscount = module.items.number;

            if (action === 0 || selectedexceptionscount === 0) {
                return;
            }

            this.default_url = this.base_url;
            this.default_url += '?action=' + action;
            this.default_url += '&selectedexceptioncount=' + selectedexceptionscount;

            this.old_open();
        };
        dialog.save = function(url) {
            var searchterm = module.config.search_term;
            url += '&action=' + $('#selectionaction option:selected').val();
            url += '&search=' + searchterm;
            this._request(url, {object: dialog, method: '_update'});
        };
        dialog._update = function(response) {
            this.hide();
            window.location.href = M.cfg.wwwroot+'/totara/program/exceptions.php?id='+module.config.id+'&search='+module.config.search_term;
        };

        totaraDialogs['applyaction'] = dialog;
    },

    /**
     * wrapper for Totara 1.9 globally declared Items() function, when called sets an
     * items property in this module equivalent to the previous global property created.
     */
    createItems: function(){
        var module = this;
        function Items() {
            this.list = [];

            this.number = module.config.selected_exceptions_count;
            this.numberLabel = $('#numselectedexceptions');

            this.actions = $('#selectionaction');

            this.handledActions = module.config.handled_actions;

            this.addItem = function(item) {
                this.list.push(item);
                item.parent = this;
            }
            this.selectAll = function(totalselected) {
                $.each(this.list, function(index, item) {
                    item.select();
                });
                this.updateNumber(totalselected);
            }
            this.unselectAll = function() {
                $.each(this.list, function(index, item) {
                    item.unselect();
                });
                this.updateNumber(0);
            }
            this.selectOnly = function(selectionId, totalselected) {
                var i = 0;
                $.each(this.list, function(index, item) {
                    if (item.selectionId === selectionId) {
                        i++;
                        item.select();
                    }
                    else {
                        item.unselect();
                    }
                });
                this.updateNumber(totalselected);
            };
            this.increaseCount = function() {
                this.updateNumber(this.number + 1);
            };
            this.decreaseCount = function() {
                this.updateNumber(this.number - 1);
            };
            this.updateNumber = function(number) {
                this.number = number;
                this.numberLabel.html(this.number);
            };
            this.getCount = function() {
                return this.number;
            };
            this.limitActions = function(handledActions) {

                var selectionactions = this.actions;

                // Enable all actions  by default
                $('option', selectionactions).show();

                // Loop through the actions
                $.each(handledActions, function(action, isAllowed) {

                    // If this action is not allowed for this type, then hide the option for the entire selection
                    if (!isAllowed) {
                        $('option[value="'+action+'"]', selectionactions).hide();
                    }
                });

                // Make sure the 'Action' option is always visible'
                $('option[value="0"]', selectionactions).show();
            };
        };
        this.items = new Items(); // store an instance of Items() in the module
    },

    /**
     * wrapper for Totara 1.9 globally declared Item() function
     * @param  String selectionId
     * @param  String typeId
     * @param  Object exceptions table row
     * @return Object instance of Item()
     */
    createItem: function(selectionId,typeId,object){
        var module = this;
        function Item() {
            this.selectionId = selectionId;
            this.typeId = typeId;
            this.object = object;

            this.parent = null; // Reference to Items

            this.checkbox = $(this.object).find('input[type="checkbox"]');

            this.exceptionId = $(this.checkbox).val();

            this.select = function() {
                this.checkbox.attr('checked','checked');
            }
            this.unselect = function() {
                this.checkbox.removeAttr('checked');
            }

            this.isSelected = function() {
                return this.checkbox.is(':checked');
            }

            // Add a hook for when the checkboxs are updated manually by the user
            var self = this;
            $(this.checkbox).click(function() {

                var url = M.cfg.wwwroot+'/totara/program/exception/updateselections.php?id='+module.config.id;
                    searchterm = module.config.search_term,
                    checked = $(this).is(":checked");

                $.getJSON(url + '&action=selectsingle' + '&checked=' + checked + '&exceptionid=' + self.exceptionId + '&search=' + searchterm, function(data) {
                    if (data['error'] == true) {
                        alert('An error occurred');
                    } else {
                        var totalselected = data['selectedcount'],
                            handledactions = data['handledactions'];

                        module.items.limitActions(handledactions)
                        module.items.updateNumber(totalselected)
                    }
                });

            });
        };
        return new Item();
    }
};
