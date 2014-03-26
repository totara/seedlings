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
 * @author Nathan Lewis <nathan.lewis@totaralms.com>
 * @package totara
 * @subpackage totara_reportbuilder
 */

var SVGNS = 'http://www.w3.org/2000/svg', XLINKNS = 'http://www.w3.org/1999/xlink';

M.totara_reportbuilder_verticaltableheadings = M.totara_reportbuilder_verticaltableheadings || {
    Y: null,

    config: {},

    /**
     * Module initialisation method called by php js_init_call()
     *
     * @param object    YUI instance
     */
    init: function(Y) {
        // Save a reference to the Y instance (all of its dependencies included).
        this.Y = Y;

        YUI().use('yui2-dom', function(Y) {
            var elements = Y.YUI2.util.Dom.getElementsByClassName('verticaltableheading', 'th');
            // The anchor is where we will render the text to determine its size. We use the first column heading.
            var headerrow = Y.YUI2.util.Dom.getElementsByClassName('c0', 'th')[0];
            if (headerrow) {
                var anchor = headerrow.firstChild;
                for (var i = 0; i < elements.length; i++)
                {
                    var el = elements[i];
                    el.style.verticalAlign = 'bottom';
                    onCreateElementNsReady(make_svg(el, anchor));
                }
            }
        });

        function onCreateElementNsReady(func) {
            if (document.createElementNS == undefined) {
                setTimeout(function() { onCreateElementNsReady(func); }, 100);
            } else {
                func;
            }
        }

        function make_svg (thEl, anchor) {
            var aEl = thEl.firstChild;
            var anonTextEl = aEl.firstChild;
            var string = anonTextEl.nodeValue;

            // Add absolute-positioned string (to measure length).
            var abs = document.createElement('div');
            abs.appendChild(document.createTextNode(string));
            abs.style.position = 'absolute';
            anchor.appendChild(abs);
            var textWidth = abs.offsetWidth;
            var textHeight = abs.offsetHeight;
            anchor.removeChild(abs);

            // Create SVG.
            var svg = document.createElementNS(SVGNS, 'svg');
            svg.setAttribute('version', '1.1');
            var width = (textHeight * 10) / 8;
            svg.setAttribute('width', width);
            svg.setAttribute('height', textWidth + 20);

            // Add text.
            var text = document.createElementNS(SVGNS, 'text');
            svg.appendChild(text);
            if (right_to_left()) {
                text.setAttribute('x', 0);
                text.setAttribute('y', - 2 * textHeight / 4);
                text.setAttribute('text-anchor', 'end');
                text.setAttribute('transform', 'rotate(90)');
            } else {
                text.setAttribute('x', 0);
                text.setAttribute('y', 4 * textHeight / 4);
                text.setAttribute('text-anchor', 'end');
                text.setAttribute('transform', 'rotate(270)');
            }
            // Copy style from <a> element. More properties can be found using "console.log(window.getComputedStyle(aEl, null));".
            text.setAttributeNS(null,"font-family", getStyleProp(aEl, 'font-family'));
            text.setAttributeNS(null,"font-style", getStyleProp(aEl, 'font-style'));
            text.setAttributeNS(null,"font-variant", getStyleProp(aEl, 'font-variant'));
            text.setAttributeNS(null,"font-weight", getStyleProp(aEl, 'font-weight'));
            text.setAttributeNS(null,"font-size", getStyleProp(aEl, 'font-size'));
            text.setAttributeNS(null,"fill", getStyleProp(aEl, 'color'));
            text.appendChild(document.createTextNode(string));

            // Is there an icon near the text?
            var iconEl = aEl.nextSibling.nextSibling;
            if (iconEl.nodeName.toLowerCase() === 'img') {
                thEl.removeChild(iconEl);
                var image = document.createElementNS(SVGNS, 'image');
                var iconx = aEl.offsetHeight / 4;
                if (iconx > width - 16)
                    iconx = width - 16;
                if (right_to_left()) {
                    image.setAttribute('x', iconx + 4);
                } else {
                    image.setAttribute('x', iconx - 4);
                }
                image.setAttribute('y', textWidth + 8);
                image.setAttribute('width', 12);
                image.setAttribute('height', 12);
                image.setAttributeNS(XLINKNS, 'href', iconEl.src);
                svg.appendChild(image);
            }

            // Replace original content with this new SVG.
            aEl.removeChild(anonTextEl);
            aEl.appendChild(svg);
        }

        // Get the current computed style of an element.
        function getStyleProp(elem, prop){
            if(window.getComputedStyle)
                return window.getComputedStyle(elem, null).getPropertyValue(prop);
            else if(elem.currentStyle)
                return elem.currentStyle[prop]; // IE.
        }

    }

}
