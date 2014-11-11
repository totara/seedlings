<?php
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
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @package totara
 * @subpackage totara_core
 */

require_once '../../config.php';
require_once $CFG->dirroot.'/totara/core/js/lib/setup.php';

$courseid = optional_param('id', 0, PARAM_INT);
$ok_string = get_string('ok');
$cancel_string = get_string('cancel');

?>

// Bind functionality to page on load
$(function() {

    /// Find course dependencies
    ///
    (function() {
        var url = '<?php echo $CFG->wwwroot ?>/course/';

        var handler = new totaraDialog_handler_preRequisite();
        handler.baseurl = url;

        totaraDialogs['evidence'] = new totaraDialog(
            'coursedependency',
            'id_add_criteria_course',
            {
                 buttons: {
                    '<?php echo $cancel_string ?>': function() { handler._cancel() },
                    '<?php echo $ok_string ?>': function() { handler._save() }
                 },
                title: '<?php
                    echo '<h2>';
                    echo get_string('addcoursedependency', 'completion');
                    echo dialog_display_currently_selected(get_string('selected', 'hierarchy'), 'coursedependency');
                    echo '</h2>';
                ?>'
            },
            url+'completion_dependency.php?id=<?php echo $courseid;?>',
            handler
        );
    })();

});

// Create handler for the dialog
totaraDialog_handler_preRequisite = function() {
    // Base url
    var baseurl = '';
}

totaraDialog_handler_preRequisite.prototype = new totaraDialog_handler_treeview_singleselect();

totaraDialog_handler_preRequisite.prototype._save = function() {

    var id = $('#treeview_selected_val_coursedependency').val();
    var course = $('#treeview_selected_text_coursedependency').text();

    // Check if something was actually selected
    if (id == 0) {
        this._dialog.hide();
        return;
    }

    // Get button fitem
    var button_fitem = $('#id_add_criteria_course').parent().parent();

    // Delete no prerequisites warning, if exists
    var statics = $('#coursedependencies span.nocoursesselected');
    if (statics) {
        $(statics).parent().parent().remove();
    }

    var html = '<div class="fitem"><div class="fitemtitle"><label for="id_dynprereq_'+id+'">'+course+'</label></div>';
    var html = html + '<div class="felement fcheckbox"><span>';
    var html = html + '<input id="id_dyncprereq_'+id+'" type="checkbox" name="criteria_course['+id+']" checked="checked" value="1" />';
    var html = html + '</span></div></div>';

    $(button_fitem).before(html);

    this._dialog.hide();
}
