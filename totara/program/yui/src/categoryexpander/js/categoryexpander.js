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
 * @subpackage program
 */

var CSS = {
        CONTENTNODE: 'content',
        COLLAPSEALL: 'collapse-all',
        DISABLED: 'disabled',
        LOADED: 'loaded',
        NOTLOADED: 'notloaded',
        SECTIONCOLLAPSED: 'collapsed',
        HASCHILDREN: 'with_children'
    },
    SELECTORS = {
        LOADEDTREES: '.with_children.loaded',
        CONTENTNODE: '.content',
        CATEGORYLISTENLINK: '.category .info .categoryname',
        CATEGORYSPINNERLOCATION: '.categoryname',
        CATEGORYWITHCOLLAPSEDLOADEDCHILDREN: '.category.with_children.loaded.collapsed',
        CATEGORYWITHMAXIMISEDLOADEDCHILDREN: '.category.with_children.loaded:not(.collapsed)',
        COLLAPSEEXPAND: '.collapseexpand',
        PROGRAMBOX: '.coursebox',
        PROGRAMBOXLISTENLINK: '.coursebox .moreinfo',
        PROGRAMBOXSPINNERLOCATION: '.name a',
        PROGRAMCATEGORYTREE: '.course_category_tree',
        PARENTWITHCHILDREN: '.category'
    },
    TYPE_CATEGORY = 'category',
    URL = M.cfg.wwwroot + '/totara/program/category.ajax.php';

M.program = M.program || {};
var NS = M.program.categoryexpander = M.program.categoryexpander || {};

/**
 * Set up the category expander.
 *
 * No arguments are required.
 *
 * @method init
 */
NS.init = function() {
    var doc = Y.one(Y.config.doc);
    doc.delegate('click', this.toggle_category_expansion, SELECTORS.CATEGORYLISTENLINK, this);
    doc.delegate('click', this.toggle_programbox_expansion, SELECTORS.PROGRAMBOXLISTENLINK, this);
    doc.delegate('click', this.collapse_expand_all, SELECTORS.COLLAPSEEXPAND, this);

    // Only set up they keyboard listeners when tab is first pressed - it
    // may never happen and modifying the DOM on a large number of nodes
    // can be very expensive.
    doc.once('key', this.setup_keyboard_listeners, 'tab', this);
};

/**
 * Set up keyboard expansion for program content.
 *
 * This includes setting up the delegation but also adding the nodes to the
 * tabflow.
 *
 * @method setup_keyboard_listeners
 */
NS.setup_keyboard_listeners = function() {
    var doc = Y.one(Y.config.doc);

    Y.log('Setting the tabindex for all expandable program nodes', 'info', 'moodle-program-categoryexpander');
    doc.all(SELECTORS.CATEGORYLISTENLINK, SELECTORS.PROGRAMBOXLISTENLINK, SELECTORS.COLLAPSEEXPAND).setAttribute('tabindex', '0');


    Y.one(Y.config.doc).delegate('key', this.toggle_category_expansion, 'enter', SELECTORS.CATEGORYLISTENLINK, this);
    Y.one(Y.config.doc).delegate('key', this.toggle_programbox_expansion, 'enter', SELECTORS.PROGRAMBOXLISTENLINK, this);
    Y.one(Y.config.doc).delegate('key', this.collapse_expand_all, 'enter', SELECTORS.COLLAPSEEXPAND, this);
};

/**
 * Toggle the animation of the clicked category node.
 *
 * @method toggle_category_expansion
 * @private
 * @param {EventFacade} e
 */
NS.toggle_category_expansion = function(e) {
    // Load the actual dependencies now that we've been called.
    Y.use('io-base', 'json-parse', 'moodle-core-notification', 'anim-node-plugin', function() {
        // Overload the toggle_category_expansion with the _toggle_category_expansion function to ensure that
        // this function isn't called in the future, and call it for the first time.
        NS.toggle_category_expansion = NS._toggle_category_expansion;
        NS.toggle_category_expansion(e);
    });
};

/**
 * Toggle the animation of the clicked programbox node.
 *
 * @method toggle_programbox_expansion
 * @private
 * @param {EventFacade} e
 */
NS.toggle_programbox_expansion = function(e) {
    // Load the actual dependencies now that we've been called.
    Y.use('io-base', 'json-parse', 'moodle-core-notification', 'anim-node-plugin', function() {
        // Overload the toggle_programbox_expansion with the _toggle_programbox_expansion function to ensure that
        // this function isn't called in the future, and call it for the first time.
        NS.toggle_programbox_expansion = NS._toggle_programbox_expansion;
        NS.toggle_programbox_expansion(e);
    });

    e.preventDefault();
};

NS._toggle_programbox_expansion = function(e) {
    var programboxnode;

    // Grab the parent category container - this is where the new content will be added.
    programboxnode = e.target.ancestor(SELECTORS.PROGRAMBOX, true);
    e.preventDefault();

    if (programboxnode.hasClass(CSS.LOADED)) {
        // We've already loaded this content so we just need to toggle the view of it.
        this.run_expansion(programboxnode);
        return;
    }

    YUI().use('querystring-parse', function(Y) {
        query = Y.QueryString.parse(window.location.search.substr(1));
        if (!query.viewtype) {
            query.viewtype = 'program';
        }
        NS._toggle_generic_expansion({
            parentnode: programboxnode,
            childnode: programboxnode.one(SELECTORS.CONTENTNODE),
            spinnerhandle: SELECTORS.PROGRAMBOXSPINNERLOCATION,
            data: {
                id: programboxnode.getData('programid'),
                categorytype: query.viewtype,
                type: 'summary'
            }
        });
    });
};

NS._toggle_category_expansion = function(e) {
    var categorynode,
        categoryid;

    if (e.target.test('a') || e.target.test('img')) {
        // Return early if either an anchor or an image were clicked.
        return;
    }

    // Grab the parent category container - this is where the new content will be added.
    categorynode = e.target.ancestor(SELECTORS.PARENTWITHCHILDREN, true);

    if (!categorynode.hasClass(CSS.HASCHILDREN)) {
        // Nothing to do here - this category has no children.
        return;
    }

    if (categorynode.hasClass(CSS.LOADED)) {
        // We've already loaded this content so we just need to toggle the view of it.
        this.run_expansion(categorynode);
        return;
    }

    // We use Data attributes to store the category.
    categoryid = categorynode.getData('categoryid');

    YUI().use('querystring-parse', function(Y) {
        query = Y.QueryString.parse(window.location.search.substr(1));
        if (!query.viewtype) {
            query.viewtype = 'program';
        }
        NS._toggle_generic_expansion({
            parentnode: categorynode,
            childnode: categorynode.one(SELECTORS.CONTENTNODE),
            spinnerhandle: SELECTORS.CATEGORYSPINNERLOCATION,
            data: {
                id: categoryid,
                type: TYPE_CATEGORY,
                categorytype: query.viewtype
            }
        });
    });
};

/**
 * Wrapper function to handle toggling of generic types.
 *
 * @method _toggle_generic_expansion
 * @private
 * @param {Object} config
 */
NS._toggle_generic_expansion = function(config) {
    if (config.spinnerhandle) {
      // Add a spinner to give some feedback to the user.
      spinner = M.util.add_spinner(Y, config.parentnode.one(config.spinnerhandle)).show();
    }

    config.data.sesskey = M.cfg.sesskey;

    // Fetch the data.
    Y.io(URL, {
        method: 'POST',
        context: this,
        on: {
            complete: this.process_results
        },
        data: config.data,
        "arguments": {
            parentnode: config.parentnode,
            childnode: config.childnode,
            spinner: spinner
        }
    });
};

/**
 * Apply the animation on the supplied node.
 *
 * @method run_expansion
 * @private
 * @param {Node} categorynode The node to apply the animation to
 */
NS.run_expansion = function(categorynode) {
    var categorychildren = categorynode.one(SELECTORS.CONTENTNODE),
        self = this,
        ancestor = categorynode.ancestor(SELECTORS.PROGRAMCATEGORYTREE);

    // Add our animation to the categorychildren.
    this.add_animation(categorychildren);


    // If we already have the class, remove it before showing otherwise we perform the
    // animation whilst the node is hidden.
    if (categorynode.hasClass(CSS.SECTIONCOLLAPSED)) {
        // To avoid a jump effect, we need to set the height of the children to 0 here before removing the SECTIONCOLLAPSED class.
        categorychildren.setStyle('height', '0');
        categorynode.removeClass(CSS.SECTIONCOLLAPSED);
        categorynode.setAttribute('aria-expanded', 'true');
        categorychildren.fx.set('reverse', false);
    } else {
        categorychildren.fx.set('reverse', true);
        categorychildren.fx.once('end', function(e, categorynode) {
            categorynode.addClass(CSS.SECTIONCOLLAPSED);
            categorynode.setAttribute('aria-expanded', 'false');
        }, this, categorynode);
    }

    categorychildren.fx.once('end', function(e, categorychildren) {
        // Remove the styles that the animation has set.
        categorychildren.setStyles({
            height: '',
            opacity: ''
        });

        // To avoid memory gobbling, remove the animation. It will be added back if called again.
        this.destroy();
        self.update_collapsible_actions(ancestor);
    }, categorychildren.fx, categorychildren);

    // Now that everything has been set up, run the animation.
    categorychildren.fx.run();
};

/**
 * Toggle collapsing of all nodes.
 *
 * @method collapse_expand_all
 * @private
 * @param {EventFacade} e
 */
NS.collapse_expand_all = function(e) {
    // Load the actual dependencies now that we've been called.
    Y.use('io-base', 'json-parse', 'moodle-core-notification', 'anim-node-plugin', function() {
        // Overload the collapse_expand_all with the _collapse_expand_all function to ensure that
        // this function isn't called in the future, and call it for the first time.
        NS.collapse_expand_all = NS._collapse_expand_all;
        NS.collapse_expand_all(e);
    });

    e.preventDefault();
};

NS._collapse_expand_all = function(e) {
    // The collapse/expand button has no actual target but we need to prevent it's default
    // action to ensure we don't make the page reload/jump.
    e.preventDefault();

    if (e.currentTarget.hasClass(CSS.DISABLED)) {
        // The collapse/expand is currently disabled.
        return;
    }

    var ancestor = e.currentTarget.ancestor(SELECTORS.PROGRAMCATEGORYTREE);
    if (!ancestor) {
        return;
    }

    var collapseall = ancestor.one(SELECTORS.COLLAPSEEXPAND);
    if (collapseall.hasClass(CSS.COLLAPSEALL)) {
        this.collapse_all(ancestor);
    } else {
        this.expand_all(ancestor);
    }
    this.update_collapsible_actions(ancestor);
};

NS.expand_all = function(ancestor) {
    var finalexpansions = [];

    ancestor.all(SELECTORS.CATEGORYWITHCOLLAPSEDLOADEDCHILDREN)
        .each(function(c) {
        if (c.ancestor(SELECTORS.CATEGORYWITHCOLLAPSEDLOADEDCHILDREN)) {
            // Expand the hidden children first without animation.
            c.removeClass(CSS.SECTIONCOLLAPSED);
            c.all(SELECTORS.LOADEDTREES).removeClass(CSS.SECTIONCOLLAPSED);
        } else {
            finalexpansions.push(c);
        }
    }, this);

    // Run the final expansion with animation on the visible items.
    Y.all(finalexpansions).each(function(c) {
        this.run_expansion(c);
    }, this);

};

NS.collapse_all = function(ancestor) {
    var finalcollapses = [];

    ancestor.all(SELECTORS.CATEGORYWITHMAXIMISEDLOADEDCHILDREN)
        .each(function(c) {
        if (c.ancestor(SELECTORS.CATEGORYWITHMAXIMISEDLOADEDCHILDREN)) {
            finalcollapses.push(c);
        } else {
            // Collapse the visible items first
            this.run_expansion(c);
        }
    }, this);

    // Run the final collapses now that the these are hidden hidden.
    Y.all(finalcollapses).each(function(c) {
        c.addClass(CSS.SECTIONCOLLAPSED);
        c.all(SELECTORS.LOADEDTREES).addClass(CSS.SECTIONCOLLAPSED);
    }, this);
};

NS.update_collapsible_actions = function(ancestor) {
    var foundmaximisedchildren = false,
        // Grab the anchor for the collapseexpand all link.
        togglelink = ancestor.one(SELECTORS.COLLAPSEEXPAND);

    if (!togglelink) {
        // We should always have a togglelink but ensure.
        return;
    }

    // Search for any visibly expanded children.
    ancestor.all(SELECTORS.CATEGORYWITHMAXIMISEDLOADEDCHILDREN).each(function(n) {
        // If we can find any collapsed ancestors, skip.
        if (n.ancestor(SELECTORS.CATEGORYWITHCOLLAPSEDLOADEDCHILDREN)) {
            return false;
        }
        foundmaximisedchildren = true;
        return true;
    });

    if (foundmaximisedchildren) {
        // At least one maximised child found. Show the collapseall.
        togglelink.setHTML(M.util.get_string('collapseall', 'moodle'))
            .addClass(CSS.COLLAPSEALL)
            .removeClass(CSS.DISABLED);
    } else {
        // No maximised children found but there are collapsed children. Show the expandall.
        togglelink.setHTML(M.util.get_string('expandall', 'moodle'))
            .removeClass(CSS.COLLAPSEALL)
            .removeClass(CSS.DISABLED);
    }
};

/**
 * Process the data returned by Y.io.
 * This includes appending it to the relevant part of the DOM, and applying our animations.
 *
 * @method process_results
 * @private
 * @param {String} tid The Transaction ID
 * @param {Object} response The Reponse returned by Y.IO
 * @param {Object} ioargs The additional arguments provided by Y.IO
 */
NS.process_results = function(tid, response, args) {
    var newnode,
        data;
    try {
        data = Y.JSON.parse(response.responseText);
        if (data.error) {
            return new M.core.ajaxException(data);
        }
    } catch (e) {
        return new M.core.exception(e);
    }

    // Insert the returned data into a new Node.
    newnode = Y.Node.create(data);

    // Append to the existing child location.
    args.childnode.appendChild(newnode);

    // Now that we have content, we can swap the classes on the toggled container.
    args.parentnode
        .addClass(CSS.LOADED)
        .removeClass(CSS.NOTLOADED);

    // Toggle the open/close status of the node now that it's content has been loaded.
    this.run_expansion(args.parentnode);

    // Remove the spinner now that we've started to show the content.
    if (args.spinner) {
        args.spinner.hide().destroy();
    }
};

/**
 * Add our animation to the Node.
 *
 * @method add_animation
 * @private
 * @param {Node} childnode
 */
NS.add_animation = function(childnode) {
    if (typeof childnode.fx !== "undefined") {
        // The animation has already been plugged to this node.
        return childnode;
    }

    childnode.plug(Y.Plugin.NodeFX, {
        from: {
            height: 0,
            opacity: 0
        },
        to: {
            // This sets a dynamic height in case the node content changes.
            height: function(node) {
                // Get expanded height (offsetHeight may be zero).
                return node.get('scrollHeight');
            },
            opacity: 1
        },
        duration: 0.2
    });

    return childnode;
};
