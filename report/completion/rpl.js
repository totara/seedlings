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
 * @author Simon Coggins <simonc@catalyst.net.nz>
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @author Aaron Barnes <aaronb@catalyst.net.nz>
 * @package totara
 * @subpackage totara_core
 */
M.totara_completionrpl = M.totara_completionrpl || {

    Y: null,
    // below will override these values
    config: {
        id:0
    },

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
            throw new Error('M.totara_comlpetionrpl.init()-> jQuery dependency required for this module to function.');
        }

        ///
        /// Add related competency dialog
        ///
        var wwwroot = M.cfg.wwwroot;
        var courseid = this.config.course;
        var pix_rply = this.config.pix_rply;
        var pix_rpln = this.config.pix_rpln;
        var pix_cross = this.config.pix_cross;
        var pix_loading = this.config.pix_loading;


        var rplvisible = new Array();

        // Get RPL type of element
        var fnc_rpltype = function(el) {
            var classes = el.attr('class').split(' ');
            var type = '';

            for (var id in classes) {
                if (classes[id].substr(0, 4) == 'rpl-') {
                    type = classes[id];
                    break;
                }
            }

            return type;
        }

        // Display RPL expand if RPLs present
        var fnc_toggleexpand = function() {

            // Loop through expanders
            var expanders = $('a.rplexpand');

            expanders.each(function() {

                // Get rpl type
                var type = fnc_rpltype($(this));

                // Check for any RPLs
                var rpls = $('td.'+type+' a.rplshow');

                // If RPLs, show expander
                if (rpls.length) {
                    $(this).show();
                } else {
                    $(this).hide();
                }

                // Hide values, show expanders
                if (rpls.length) {
                    $('td.'+type+' span.rplvalue').hide();
                    $('td.'+type+' a.rplshow').show();
                }
            });
        }
        fnc_toggleexpand();


        // RPL expand functionality
        var fnc_expand = function(event) {

            event.preventDefault();

            // Trigger the save/hide for any other open input groups
            fnc_savehide();

            // Get rpl type
            var type = fnc_rpltype($(this).parent());

            // Toggle visibility
            rplvisible[type] = rplvisible[type] ? false : true;

            if (rplvisible[type]) {
                $('td.'+type+' a.rplshow').hide();
                $('td.'+type+' span.rplvalue').show();
            } else {
                $('td.'+type+' a.rplshow').show();
                $('td.'+type+' span.rplvalue').hide();
            }
        }
        $('a.rplexpand img').click(fnc_expand);


        // RPL edit textfield functionality
        var fnc_edit = function(event) {

            event.preventDefault();

            // Get table cell
            var cell = $(this).parent('td');

            // Get elements
            var value = $('span.rplvalue', cell);
            var inputgroup = $('span.rplinputgroup', cell);
            var input = $('input.rplinput', inputgroup);
            var dots = $('a.rplshow', cell);

            // Toggle text field
            if (inputgroup.length)
            {
                // If text field exists

                // Old value
                var oldvalue = value.text();

                // If a RPL was entered
                var inputvalue = input.val();
                if (inputvalue) {
                    // Change icon
                    $('a.rpledit img', cell).attr('src', pix_rply);

                    // Save value
                    value.text(inputvalue);

                    // Show value
                    if (rplvisible) {
                        value.show();
                    }

                    // Add dots if they don't exist
                    if (!dots.length) {
                        var dots = $('<a href="#" class="rplshow" title="Show RPL">...</a>');
                        dots.click(fnc_expand);

                        cell.append(dots);
                    }

                    if (rplvisible) {
                        dots.hide();
                    } else {
                        dots.show();
                    }

                // If no RPL was entered
                } else {
                    // Reset value and hide
                    value.text('').hide();

                    // Remove dots
                    dots.remove();

                    // Change icon
                    $('a.rpledit img', cell).attr('src', pix_rpln);
                }

                // Toggle expander
                fnc_toggleexpand();

                // Remove inputgroup
                inputgroup.remove();

                // If value has changed, save
                if (oldvalue != inputvalue) {
                    var user = cell.parent('tr').attr('id').substr(5);
                    fnc_saverpl(cell, user, inputvalue);
                }

            } else {
                // If no text field

                // Trigger the save/hide for any other open input groups
                fnc_savehide();

                // Create group
                var inputgroup = $('<span class="rplinputgroup"></span>');

                // Create input
                var input = $('<input class="rplinput" type="text" maxlength="255"/>');
                input.val(value.text());

                // Bind enter event to input
                input.keypress(function(event) {
                    if (event.which != 13) {
                        return;
                    }

                    // If enter key pressed, save
                    $('a.rpledit', cell).trigger('click');
                });

                // Create delete button
                var cancel = $('<a href="#" class="icon rpldelete" title="Delete this RPL"><img src="'+pix_cross+'" alt="Delete" /></a>');
                cancel.click(function(event) {

                    event.preventDefault();

                    // Remove RPL
                    input.val('');

                    // Trigger edit event
                    $('a.rpledit', cell).trigger('click');
                });

                // Add stuff to group
                inputgroup.append(input);
                inputgroup.append(cancel);

                // Hide value or dots if shown
                value.hide();
                dots.hide();

                // Insert into cell
                $('a.rpledit', cell).after(inputgroup);

                // Focus input
                input.focus();
            }

        }
        $('a.rpledit, a.rplshow').click(fnc_edit);


        // Trigger the save/hide for any other open input groups
        var fnc_savehide = function() {
            $('span.rplinputgroup').each(function() {

                // Trigger edit event
                $('a.rpledit', $(this).parent('td')).trigger('click');
            });
        }

        // Course module id.
        var cmid;

        // Get the course module id depending on which activity is clicked by the user.
        $('a.rpledit').click(function() {
            var classname = $(this).parent('td').attr('class');

            classname = classname.split('cmid-');
            if (typeof classname[1] !== 'undefined') {
                cmid = classname[1];
            } else {
                cmid = 0;
            }
        });

        // Save RPL data
        var fnc_saverpl = function(cell, user, rpl) {

            // Get rpl type
            var type = fnc_rpltype(cell).substr(4);

            // Show loading icon
            cell.append($('<img class="rplloading" src="'+pix_loading+'" />'));

            // Callback for saving RPL.
            var callback = {
                    method: 'GET',
                    data: 'type='+type+'&course='+courseid+'&user='+user+'&rpl='+rpl+'&cmid='+cmid,
                    arguments: { success : user },
                    on: {
                        success: function(id, o, args) {
                                    var user = args.success;
                                    // Hide save icon.
                                    $('#user-'+user+' .rplloading').remove();
                                },
                        failure: function(o) { }
                    }
                };
            Y.use('io-base', function(Y) {
                var uri = wwwroot+'/report/completion/save_rpl.php';
                Y.io(uri, callback);
            });
        }
    }
};
