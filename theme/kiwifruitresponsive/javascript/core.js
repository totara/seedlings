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
 * @author Brian Barnes <brian.barnes@totaralms.com>
 * @package totara
 * @subpackage theme
 */

/**
 * Causes the dock to be attached to the page and scroll with it, rather than being stuck to the side of the browser.
 */
YUI().use('event', function (Y) {
    var body = Y.one('html');
    Y.on('scroll', dockscroll);

    function dockscroll(e) {
        var dock = Y.one('#dock');
        if (dock) {
            dock.removeClass('dock-fixed');
            if (window.scrollY > dock.get('offsetTop')) {
                dock.addClass('dock-fixed');
            }
        }
    }
});
