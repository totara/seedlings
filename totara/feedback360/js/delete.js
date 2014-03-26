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
 * @subpackage totara_feedback360
 */
$(document).ready(function() {
    var elements = document.getElementsByClassName('system_record_del');
    var index;
    for (index = 0; index < elements.length; ++index) {
        elements[index].hidden = false;
    }
});

$(document).on('click', '.system_record_del', function (event) {
    event.preventDefault();
    var userid = this.id;
    var sysnew = $('input[name="systemnew"]');
    var user_record = document.getElementById('system_user_' + userid);

    // Remove the user from displaying on the screen.
    user_record.parentNode.removeChild(user_record);

    // Remove the user from the hidden div used to add them.
    var sysval = sysnew.val().split(',');

    var newval = [];
    for (var i = 0; i < sysval.length; i++) {
        if (sysval[i] != userid) {
            newval.push(sysval[i]);
        }
    }

    sysnew.val(newval.join(','));
});

$(document).on('click', '.external_record_del', function (event) {
    event.preventDefault();

    var email = this.id;

    // Remove the email assignment from the display.
    var external_record = document.getElementById('external_user_' + email);
    external_record.parentNode.removeChild(external_record);

    // Add the email to #cancelledemails (commaseperated);
    var cancelled = $('input[name="emailcancel"]');
    if (cancelled.val()) {
        var newval = cancelled.val().split(',');
    } else {
        var newval = [];
    }
    newval.push(email);
    cancelled.val(newval.join(','));
});
