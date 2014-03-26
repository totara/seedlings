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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage facetoface
 */

M.totara_f2f_room = M.totara_f2f_room || {

    Y: null,
    // Optional php params and defaults defined here, args passed to init method
    // below will override these values
    config: {},
    // Public handler reference for the dialog
    totaraDialog_handler_preRequisite: null,

    /**
     * module initialisation method called by php js_init_call()
     *
     * @param object    YUI instance
     * @param string    args supplied in JSON format
     */
    init: function(Y, args){
        var module = this;

        // Save a reference to the Y instance (all of its dependencies included)
        this.Y = Y;

        // If defined, parse args into this module's config object
        if (args) {
            var jargs = Y.JSON.parse(args);
            for (var a in jargs) {
                if (Y.Object.owns(jargs, a)) {
                    this.config[a] = jargs[a];
                }
            }
        }

        // Check jQuery dependency is available
        if (typeof $ === 'undefined') {
            throw new Error('M.totara_f2f_room.init()-> jQuery dependency required for this module to function.');
        }

        var url = M.cfg.wwwroot+'/mod/facetoface/room/ajax/';

        var handler = new totaraDialog_handler_addpdroom();
        handler.setup_delete();

        var buttonsObj = {};
        buttonsObj[M.util.get_string('cancel','moodle')] = function() { handler._cancel(); };
        buttonsObj[M.util.get_string('ok','moodle')] = function() { handler._save(); };

        totaraDialogs['addpdroom'] = new totaraDialog(
                'addpdroom-dialog',
                'show-addpdroom-dialog',
                {
                    buttons: buttonsObj,
                    title: '<h2>' + M.util.get_string('chooseroom', 'facetoface') + M.totara_f2f_room.config.display_selected_item + '</h2>'
                },
                url + 'sessionrooms.php?sessionid=' + M.totara_f2f_room.config.sessionid,
                handler
        );

        // Disable pre-defined room button if session datetimes aren't know
        if ($('select[name="datetimeknown"]').val() == 0) {
            $('input[name="show-addpdroom-dialog"]').attr('disabled', 'disabled');
        }
        $('select[name="datetimeknown"]').change(function() {
            var addroombtn = $('input[name="show-addpdroom-dialog"]');
            if ($(this).val() == 1) {
                addroombtn.removeAttr('disabled');
            } else {
                addroombtn.attr('disabled', 'disabled');
                clean_pdroom_data();
            }
        });

        // Clear pre-defined room selection if session start/finish datetimes change
        $('select[name^="timestart["]').change(function() {
            clean_pdroom_data();
        });
        $('select[name^="timefinish["]').change(function() {
            clean_pdroom_data();
        });

        // Clear pre-defined room selection and set room capacity if custom room is selected
        $('input[name="customroom"]').click(function() {
            if ($(this).is(':checked')) {
                clean_pdroom_data();
                $('#warn').remove();
                $('input[name="pdroomcapacity"]').val(0);
                $('input[name="croomcapacity"]').val($('input[name="capacity"]').val());
            }
        });

        clean_pdroom_data = function() {
            $('input[name="pdroomid"]').val(0);
            $('span#pdroom').html('');
            $('span#roomnote').html('');
        }

        is_pdroom_exceeded = function() {
            var pdroomcapacity = parseInt($('input[name="pdroomcapacity"]').val(), 10);
            var sessioncapacity = parseInt($('input[name="capacity"]').val(), 10);

            if ((sessioncapacity > pdroomcapacity) && (pdroomcapacity > 0) && ($.isNumeric($('input[name="capacity"]').val()))) {
                $('<div id=warn class="notice">' + M.util.get_string('pdroomcapacityexceeded', 'facetoface') + '</div>').insertBefore('input[name="capacity"]');
            }
        }

        // If pre-defined room capacity is exceeded by room capacity, a warning message will be shown
        $('input[name="capacity"]').bind('keyup blur', function() {
            $('#warn').remove();
            is_pdroom_exceeded();
        });
        // Show the warning message is the session was saved with pre-defined room capacity exceeded by room capacity
        $().ready(function() {
            is_pdroom_exceeded();
        });

        // Update session capacity if room capacity changes
        $('input[name="croomcapacity"]').bind('keyup cut paste', function() {
            var capacity = $(this);
            setTimeout(function() {
                $('input[name="capacity"]').val(capacity.val());
            });
        });
        // Update room capacity if session capacity changes
        $('input[name="capacity"]').bind('keyup cut paste', function() {
            if ($('input[name="customroom"]').is(':checked')) {
                var capacity = $(this);
                setTimeout(function() {
                    $('input[name="croomcapacity"]').val(capacity.val());
                });
            }
        });
    }
}

// Create handler for the addpdroom dialog
totaraDialog_handler_addpdroom = function() {};
totaraDialog_handler_addpdroom.prototype = new totaraDialog_handler_treeview_singleselect('pdroomid', 'pdroom');

totaraDialog_handler_addpdroom.prototype.setup_delete = function() {

    this.deletable = true;
    var textel = $('#'+this.text_element_id);
    var idel = $('input[name='+this.value_element_name+']');
    var deletebutton = $('<span class="dialog-singleselect-deletable">'+M.util.get_string('delete', 'totara_core')+'</span>');
    var handler = this;

    // Setup handler
    deletebutton.click(function() {
        idel.val('');
        textel.removeClass('nonempty');
        textel.empty();
        $('input[name="pdroomid"]').val(0);
        $('span#roomnote').html('');
        $('input[name="capacity"]').val('10');
        $('input[name="pdroomcapacity"]').val(0);
        $('#warn').remove();
        handler.setup_delete();
    });

    if (textel.text().length) {
        textel.append(deletebutton);
    }
}

totaraDialog_handler_addpdroom.prototype._open = function(alternative_url) {
    var datecount = 0;
    var timeslots = new Array();
    $('input[name^="datedelete["]').each(function() {
        if (!$(this).is(':checked')) {
            var timestart = new Date(
                $('.fdate_time_selector select[name="timestart['+datecount+'][year]"]').val(),
                $('.fdate_time_selector select[name="timestart['+datecount+'][month]"]').val()-1,
                $('.fdate_time_selector select[name="timestart['+datecount+'][day]"]').val(),
                $('.fdate_time_selector select[name="timestart['+datecount+'][hour]"]').val(),
                $('.fdate_time_selector select[name="timestart['+datecount+'][minute]"]').val()
                ).getTime() / 1000;
            var timefinish = new Date(
                $('.fdate_time_selector select[name="timefinish['+datecount+'][year]"]').val(),
                $('.fdate_time_selector select[name="timefinish['+datecount+'][month]"]').val()-1,
                $('.fdate_time_selector select[name="timefinish['+datecount+'][day]"]').val(),
                $('.fdate_time_selector select[name="timefinish['+datecount+'][hour]"]').val(),
                $('.fdate_time_selector select[name="timefinish['+datecount+'][minute]"]').val()
                ).getTime() / 1000;
            timeslots.push([timestart, timefinish]);
        }
        datecount += 1;
    });
    // Update the url to include the timestamps
    timeslots = JSON.stringify(timeslots);
    this._dialog.default_url = M.cfg.wwwroot+'/mod/facetoface/room/ajax/sessionrooms.php?sessionid='+M.totara_f2f_room.config.sessionid+'&timeslots='+timeslots;
}

totaraDialog_handler_addpdroom.prototype.first_load = function() {
    // Call parent function
    totaraDialog_handler_treeview_singleselect.prototype.first_load.call(this);
}

totaraDialog_handler_addpdroom.prototype.every_load = function() {
    // Call parent function
    totaraDialog_handler_treeview_singleselect.prototype.every_load.call(this);

    var selected_val = $('#treeview_selected_val_'+this._title).val();

    // Add footnote flag to all unclickable items, except the currently selected one
    $('span.unclickable').not('#item_'+selected_val).has('a').not('.hasfootnoteflag').each(function() {
        $(this).addClass('hasfootnoteflag');
    });
}

// Called as part of _save
totaraDialog_handler_addpdroom.prototype.external_function = function() {
    // Set the chosen room capacity
    $.ajax({
        url: M.cfg.wwwroot+'/mod/facetoface/room/ajax/roomcap.php?id='+$('input[name="pdroomid"]').val(),
    type: 'GET',
    success: function(o) {
        if (o.length) {
            $('#warn').remove();
            $('input[name="capacity"]').val(o);
            $('input[name="pdroomcapacity"]').val(o);
        }
    }
    });
    // Set room note
    $.ajax({
        url: M.cfg.wwwroot+'/mod/facetoface/room/ajax/roomnote.php?id='+$('input[name="pdroomid"]').val(),
    type: 'GET',
    success: function(o) {
        if (o.length) {
            o = '<br>' + o;
        }
        $('span#roomnote').html(o);
    },
    error: function(o) {
        $('span#roomnote').html('');  // remove all notes
    }
    });

    // Clear custom room
    $('input[name="customroom"]').prop("checked", false);
    // Disable custom room if pre-defined room is selected
    $('input[name="croomname"], input[name="croombuilding"], input[name="croomaddress"], input[name="croomcapacity"]').prop('disabled', true);
}
