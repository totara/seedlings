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
 * @package totara
 * @subpackage reportbuilder
 */

/**
 * Page containing column display options, displayed inside show/hide popup dialog
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');

require_login();
$PAGE->set_context(context_system::instance());

$id = required_param('id', PARAM_INT);

$report = new reportbuilder($id);
echo $OUTPUT->container_start(null, 'column-checkboxes');
$count = 0;
foreach ($report->columns as $column) {
    // skip empty headings
    if ($column->heading == '') {
        continue;
    }
    $ident = "{$column->type}_{$column->value}";
    echo html_writer::empty_tag('input', array('type' => 'checkbox', 'id' => $ident, 'name' => $ident));
    echo html_writer::tag('label', $report->format_column_heading($column, false), array('for' => $ident));
    echo html_writer::empty_tag('br');
    $count++;
}
echo $OUTPUT->container_end();

?>
<script type="text/javascript">
// set checkbox state based on current column visibility
$('#column-checkboxes input').each(function() {
    var sel = '#' + shortname + ' .' + $(this).attr('name');
    var state = $(sel).css('display');
    var check = (state == 'none') ? false : true;
    $(this).prop('checked', check);
});
// when clicked, toggle visibility of columns
$('#column-checkboxes input').click(function() {
    var selheader = '#' + shortname + ' th.' + $(this).attr('name');
    var sel = '#' + shortname + ' td.' + $(this).attr('name');
    var value = $(this).is(':checked') ? 1 : 0;

    $(selheader).toggle();
    $(sel).toggle();

    $.ajax({
        url: '<?php print $CFG->wwwroot; ?>/totara/reportbuilder/showhide_save.php',
        data: {'shortname' : shortname,
               'column' : $(this).attr('name'),
               'value' : value,
               'sesskey' : M.cfg.sesskey
        }
    });

});
</script>
