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
 * @subpackage totara_core
 */
$(function() {
    // Handle icon change preview
    $('#id_icon').change(function() {
        var selected = $(this);
        var src = $('#icon_preview').attr('src');
        src = src.replace(/image=(.*?)icons%2F(.*?)(&.*?){0,1}$/, 'image=$1'+'icons%2F'+selected.val()+'$3');
        $('#icon_preview').attr('src', src);
    });
});
