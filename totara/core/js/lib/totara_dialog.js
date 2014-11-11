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
 * @author Dave Wallace <dave.wallace@kineo.co.nz>
 * @package totara
 * @subpackage totara_core
 */
M.totara_dialog = M.totara_dialog || {

    Y: null,
    // optional php params and defaults defined here, args passed to init method
    // below will override these values
    config: {},

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

        // check jQuery dependency and continue with setup
        if (typeof $ === 'undefined') {
            throw new Error('M.totara_dialog.init()-> jQuery dependency required for this module to function.');
        }
    }
};

if (navigator.userAgent.match(/mozilla/i)) {
    $('body').addClass('mozilla');
}

// Setup
var totaraDialogs = {};

// Dialog object
function totaraDialog(title, buttonid, config, default_url, handler) {

    /**
     * ID of dialog
     */
    this.title = title;

    /**
     * ID of open button
     */
    this.buttonid = buttonid;

    /**
     * Dialog widget instance
     */
    this.dialog;

    /**
     * Default URL
     */
    this.default_url = default_url;

    /**
     * Currently loaded URL
     */
    this.url = '';

    /**
     * Custom configuration
     */
    this.config = config;

    /**
     * Handler class
     */
    this.handler = handler;

    /**
     * Setup this dialog
     * @return  void
     */
    this.setup = function() {
        // Set up obj for closure
        var obj = this;

        var height = $(window).height() * 0.8;
        var width = $(window).width() * 0.8;
        width = Math.max(width, $(window).width() - 50);
        width = Math.min(width, 800);

        var default_config = {
            autoOpen: false,
            closeOnEscape: true,
            draggable: false,
            height: height,
            width: width + 'px',
            modal: true,
            resizable: false,
            zIndex: 1500,
            dialogClass: 'totara-dialog',
            open: function() {
                $(this).parent().find("span.ui-dialog-title").html("<span class='title'>" + obj.config.title + "</span>");
            }
        };

        // Instantiate the Dialog
        $('<div class="totara-dialog" style="display: none;"><div id="'+this.title+'"></div></div>').appendTo($('body'));

        this.dialog = $('#'+this.title).dialog(
            $.extend(default_config, this.config)
        );

        // Setup handler
        if (this.handler != undefined) {
            this.handler._setup(this);
        }

        // Bind hide() event to closing dialog
        // Need this to make sure hide() is called on ESCAPE and dialog closure with [X] button
        $('#'+this.title).on( "dialogclose", function(event) {
            event.preventDefault();
            obj.hide();
        });

        // Bind open event to button
        $(document).on('click', '#'+this.buttonid, function(event) {

            // Stop any default event occuring
            event.preventDefault();

            // Open default url
            obj.open();
        });

    }


    /**
     * Open dialog and load external page
     * @return  void
     */
    this.open = function() {
        // Open default url in dialog
        var method = 'GET';

        this.dialog.html('');
        this.dialog.dialog('open');

        // Get dialog parent
        var par = this.dialog.parent();

        // Set dialog body height (the 20px is the margins above and below the content)
        var height = par.height() - $('div.ui-dialog-titlebar', par).height() - $('div.ui-dialog-buttonpane', par).height() - 36;
        this.dialog.height(height);

        // Run dialog open hook
        if (this.handler._open != undefined) {
            this.handler._open();
        }

        this.load(this.default_url);
    }


    /**
     * Load an external page in the dialog
     * @param   string      Url of page
     * @param   string      Type of request ('GET', 'POST') (optional, GET is default)
     * @param   string      GET or POST query string style data, also accepts an object (optional)
     * @return  void
     */
    this.load = function(url, type, query_data) {
        // Add loading animation
        this.dialog.html('');
        this.showLoading();

        // Save url
        this.url = url;

        // Load page
        this._request(this.url, {}, type, query_data);
    }


    /**
     * Display error information
     * @param   object  dialog object
     * @param   string  ajax response
     * @param   string  url
     * @return  void
     */
    this.error = function(dialog, response, url) {
        // Hide loading animation
        dialog.hideLoading();

        var html_message = '';
        if (response) {
            html_message = response;
        } else {
            // Print a generic error message
            html_message = '<div class="box errorbox errorboxcontent">An error has occured</div>';
        }
        dialog.dialog.html(html_message);
    }


    /**
     * Render dialog and contents
     * @param string o             asyncRequest response
     * @param object outputelement (optional) element in which output should be generated
     * @return void
     */
    this.render = function(o, outputelement) {
        // Hide loading animation
        this.hideLoading();

        if (outputelement) {
            // Render the output in the specified element
            outputelement.html(o);
        } else {
            // Just reload the whole dialog
            this.dialog.html(o);
        }

        this.bindLinks();
        this.bindForms();

        // Run setup function
        this.handler._load();

        // Run partial update function
        if (outputelement && this.handler._partial_load != undefined) {
            this.handler._partial_load(outputelement);
        }
    }


    /**
     * Bind this.navigate to any links in the dialog
     * @return void
     */
    this.bindLinks = function() {

        var dialog = this;

        // Bind dialog.load to any links in the dialog
        $('a', this.dialog).each(function() {

            // Don't bind links if any parent has dialog-nobind class
            if ($(this).parents('.dialog-nobind').length != 0) {
                return;
            }
            // Check this is not a help popup link
            if ($(this).parent().is('span.helplink')) {
                return;
            }

            $(this).bind('click', function(e) {
                var url = $(this).attr('href');
                // if the link is inside an element with the
                // dialog-load-within class set, load the results in
                // that element instead of reloading the whole dialog
                //
                // if there is more than one parent with the class set,
                // loads in the most specific one
                var target = $(this).parents('.dialog-load-within').slice(0,1);
                if(target.length != 0) {
                    dialog.showLoading();
                    dialog._request(url, {outputelement: target});
                } else {
                    // otherwise, load in the whole dialog
                    dialog.load(url);
                }

                // Stop any default event occuring
                e.preventDefault();

                return false;
            });
        });
    }


    /**
     * Bind this.navigate to any form submissions in the dialog
     * @return void
     */
    this.bindForms = function() {

        var dialog = this;

        // Bind dialog.load to any links in the dialog
        $('form', this.dialog).each(function() {

            $(this).bind('submit', function(e) {
                var url = $(this).attr('action');
                var method = $(this).attr('method');
                var data = $(this).serialize();

                // if the form is inside an element with the
                // 'dialog-load-within' class set, load the results in(improved version)

                //
                // if there is more than one parent with the class set,
                // loads in the most specific one
                var target = $(this).parents('.dialog-load-within').slice(0, 1);
                if (target.length != 0) {
                    dialog.showLoading();
                    dialog._request(url, {outputelement: target}, method, data);
                } else {
                    // if no target set, reload whole dialog
                    dialog.load(url, method, data);
                }

                // Stop any default event occuring
                e.preventDefault();

                return false;
            });
        });
    }


    /**
     * Show loading animation
     * @return void
     */
    this.showLoading = function() {
        $('div#'+this.title).addClass('yui-isloading');
    }


    /**
     * Hide loading animation
     * @return void
     */
    this.hideLoading = function() {
        $('div#'+this.title).removeClass('yui-isloading');
    }


    /**
     * Hide dialog
     * @return void
     */
    this.hide = function() {
        this.handler._loaded = false;
        this.dialog.html('');

        // Check if dialog is still open to avoid exceeding call stack size
        if (this.dialog.dialog("isOpen")) {
            this.dialog.dialog('close');
        }
    }


    /**
     * Make an HTTP request (improved version)
     *
     * The success parameter is an optional object containing:
     *  .object:
     *  .method:
     *    Object variable and method name to be called on success
     *    This method is passed the HTML response, and optionally the data variable.
     *
     *  .data:
     *    data to be passed to success method (optional)
     *
     *  .outputelement:
     *    element in which request output should be outputted (optional)
     *
     * If no object/method name are passed, or they return 'true' - the dialog.render
     * method is called on success.
     *
     * @param   string      Request url
     * @param   object      What to do on success (optional)
     * @param   string      Type of request ('GET', 'POST') (optional, GET is default)
     * @param   string      GET or POST query string style data, also accepts an object (optional)
     */
    this._request = function(url, success, type, query_data) {
        var dialog = this;

        if (type == undefined) {
            type = 'GET';
        }

        $.ajax({
            url: url,
            type: type,
            data: query_data,

            success: function(o) {

                var result = true;

                // Check the result of onsuccess
                // If false, do not run the render method
                if (success != undefined && success.object != undefined) {
                    result = success.object[success.method](o, success.data);
                }

                if (result) {
                    if (success != undefined && success.outputelement != undefined) {
                        dialog.render(o, success.outputelement);
                    } else {
                        dialog.render(o);
                    }

                }
            },
            error: function(o) {
                dialog.error(dialog, o.responseText, url);
            }
        });
    }


    // Setup object
    this.setup();

}

/*****************************************************************************/
/** totaraDialog_handler **/

function totaraDialog_handler() {

    // Reference to the totaraDialog object
    var _dialog;

    // Dialog title/name
    var _title;

    // Dialog container
    var _container;

    // Has the dialog loaded its first page?
    var _loaded = false;
}

/**
 * Setup the dialog handler
 * Run when the dialog is constructed
 *
 * @param yuiDialog dialog object
 * @return void
 */
totaraDialog_handler.prototype._setup = function(dialog) {
    this._dialog = dialog;
    this._title = dialog.title;
}

/**
 * Run on page load
 * Calls this.first_load() on first page load
 *
 * @param yuiDialog dialog object
 * @return void
 */
totaraDialog_handler.prototype._load = function(dialog) {

    // First page load
    if (!this._loaded) {

        // Setup container
        this._container = $('#'+this._title);

        // Run decendant method
        if (this.first_load != undefined) {
            this.first_load();
        }

        var containerHeight = this._container.height();
        var h3MarginBottom = parseFloat($('.select h3', this._container).css('margin-bottom'), 10) || 0;
        var tabsMarginTop = parseFloat($('#dialog-tabs', this._container).css('margin-bottom'), 10) || 0;
        var browseHeight = containerHeight
                - $('.select h3', this._container).outerHeight(true)
                - $('.select .tabs', this._container).outerHeight(true)
                - ($('#dialog-tabs').outerHeight(true) - $('#dialog-tabs').height())
                + Math.min(h3MarginBottom, tabsMarginTop);

        $('#browse-tab').outerHeight(browseHeight);
        $('#search-tab').outerHeight(browseHeight);
        $('.selected.dialog-nobind', this._container).outerHeight(this._container.height());

        this._loaded = true;
    }

    // Run decendant method
    if (this.every_load != undefined) {
        this.every_load();
    }

    return true;
}

/**
 * Add a row to a table on the calling page
 * Also hides the dialog and any no item notice
 *
 * @param string    HTML response
 * @return void
 */
totaraDialog_handler.prototype._update = function(response) {

    // Remove no item warning (if exists)
    $('.noitems-'+this._title).remove();

    // Hide dialog
    this._dialog.hide();

    // Sometimes we want to have two dialogs changing the same table,
    // so here we support tagging tables by id, or class
    var content = $('div.list-'+this._title);

    // Add replace div with updated data
    content.replaceWith(response);

    // Hide noscript objects
    $('.totara-noscript', $('div.list-'+this._title)).hide();

}


/**
 * Utility function for getting ids from
 * a list of elements
 *
 * @param jQuery jquery element list
 * @param string ID prefix string
 * @return array
 */
totaraDialog_handler.prototype._get_ids = function(elements, prefix) {

    var ids = [];

    // Loop through elements
    elements.each(
        function (intIndex) {

            // Get id attr
            var id = $(this).attr('id').split('_');
            if (id[id.length-2] == 'personal') {
              id = 'p' + id[id.length-1]; // If the item is a personal goal, we add a prefix
            } else {
              id = id[id.length-1];  // The last item is the actual id
            }
            // Append to list
            ids.push(id);
        }
    );

    return ids;
}

/**
 * Serialize dropped items and send to url,
 * update table with result
 *
 * @param string URL to send dropped items to
 * @return void
 */
totaraDialog_handler.prototype._save = function(url) {

    // Serialize data
    var elements = $('.selected > div > span', this._container);
    var selected_str = this._get_ids(elements).join(',');

    // Add to url
    url = url + selected_str;

    // Send to server
    this._dialog._request(url, {object: this, method: '_update'});
}

/**
 * Handle a 'cancel' request, by just closing the dialog
 *
 * @return void
 */
totaraDialog_handler.prototype._cancel = function() {
    this._dialog.hide();
    return;
}


/**
 * Change framework
 *
 * @return void
 */
totaraDialog_handler.prototype._set_framework = function() {

    // Get currently selected option
    var selected = $('.simpleframeworkpicker option:selected', this._container).val();

    // Update URL
    var url = this._dialog.url;

    // See if framework specific
    if (url.indexOf('&frameworkid=') == -1 || url.indexOf('?frameworkid=') == -1) {
        // Only return tree html
        url = url + '&frameworkid=' + selected + '&treeonly=1&switchframework=1';
    } else {
        // Get start of frameworkid
        var start = url.indexOf('frameworkid=') + 12;

        // Find how many characters long the value is
        var end = url.indexOf('&', start);

        // If no following &, it is the end of the url
        if (end == -1) {
            url = url.substring(0, start) + selected;
        // Just replace the value
        } else {
            url = url.substring(0, start) + selected + url.substring(end);
        }
    }

    this._dialog.showLoading();  // Show loading icon and then perform request
    this._dialog._request(url, {outputelement: $('#browse-tab .treeview-wrapper', this._container)});
}

/**
 * Change plan
 *
 * @return void
 */
totaraDialog_handler.prototype._set_plan = function() {

    // Get currently selected option
    var selected = $('.planselector option:selected', this._container).val();

    // Update URL
    var url = this._dialog.url;

    url = url + '&planid=' + selected + '&treeonly=1&switchplan=1';

    this._dialog.showLoading();  // Show loading icon and then perform request
    this._dialog._request(url, {outputelement: $('#browse-tab .treeview-wrapper', this._container)});
}


/*****************************************************************************/
/** totaraDialog_handler_treeview **/

totaraDialog_handler_treeview = function() {};

totaraDialog_handler_treeview.prototype = new totaraDialog_handler();


/**
 * Setup tabs
 *
 * Sets heights of treeviews, sets focus
 *
 * @return void
 */
totaraDialog_handler_treeview.prototype.setup_tabs = function(e, ui) {
    var selcontainer = $('.select', this._container);

    // If showing search tab, focus search box
    if (ui && $(ui.newTab).attr('aria-controls') === 'search-tab') {
        $('#id_query', this._container).focus();
    }
}


/**
 * Setup tab, treeview infrastructure on first load
 *
 * @return void
 */
totaraDialog_handler_treeview.prototype.first_load = function() {

    var handler = this;

    // Setup treeview
    $('.treeview', this._container).treeview({
        prerendered: true
    });

    // Setup tabs
    $('#dialog-tabs').tabs(
        {
            selected: 0,
            show: handler.setup_tabs,
            activate: handler.setup_tabs
        }
    );

    // Set heights of treeviews
    this.setup_tabs();

    // Setup framework picker
    $('.simpleframeworkpicker', this._container).unbind('change');  // Unbind any previous events
    $('.simpleframeworkpicker', this._container).change(function() {
        handler._set_framework();
    });

    // Setup plan picker
    $('.planselector', this._container).unbind('change');  // Unbind any previous events
    $('.planselector', this._container).change(function() {
        handler._set_plan();
    });

    // Setup hierarchy
    this._make_hierarchy($('.treeview', this._container));

    // Disable selected item's anchors
    $('.selected > div > span a', this._container).unbind('click')
    .click(function(e) {
        e.preventDefault();
    });
}


/**
 * Setup treeview infrastructure on partial page loads
 *
 * @return void
 */
totaraDialog_handler_treeview.prototype._partial_load = function(parent_element) {

    // Set heights of treeviews
    this.setup_tabs();

    // Render treeview
    if (parent_element.hasClass('treeview')) {
        var treeview = parent_element;
    } else {
        var treeview = $('.treeview', parent_element);
    }

    if (treeview.size()) {
        treeview.treeview({
            prerendered: true
        });
    }

    // Setup hierarchy
    this._make_hierarchy(parent_element);//$('.treeview', parent_element));

    // Disable selected item's anchors
    $('.selected > div > span a', parent_element).unbind('click')
    .click(function(e) {
        e.preventDefault();
    });

    // Setup selectables and deletables
    if (this.every_load != undefined) {
        this.every_load();
    }

    return true;
}

/**
 * Setup hierarchy click handlers
 *
 * @return void
 */
totaraDialog_handler_treeview.prototype._make_hierarchy = function(parent_element) {
    var handler = this;

    // Load children on parent click
   // $('span.folder, div.hitarea', parent_element).unbind('click');
    $('span.folder, div.hitarea', parent_element).bind('click', function() {
        // Get parent
        var par = $(this).parent();

        // Check this category doesn't have any children already
        if ($('> ul > li', par).size()) {
            return false;
        }

        // Id in format item_list_XX
        var id = par.attr('id').substr(10);

        var url = handler._dialog.url+'&parentid='+id;
        handler._dialog._request(url, {object: handler, method: '_update_hierarchy', data: id});

        return false;
    });

    // Make any unclickable items truely unclickable
    $('span.unclickable', parent_element).each(function() {
        handler._toggle_items($(this).attr('id'), false);
    });

    // Make currently selected items unclickable
    $('.selected > div > span', this._container).each(function() {
        // If item in hierarchy, make unclickable
        var id = $(this).attr('id');
        handler._toggle_items(id, false);
    });
}

/**
 * Add items to existing treeview
 *
 * @param string    HTML response
 * @param int       Parent id
 * @return void
 */
totaraDialog_handler_treeview.prototype._update_hierarchy = function(response, parent_id) {
    var items = response;
    var list = $('#browse-tab .treeview li#item_list_'+parent_id+' ul:first', this._container);

    // Remove all existing children
    $('li', list).remove();

    // Add items
    var treeview = $('#browse-tab .treeview', this._container);
    treeview.treeview({add: list.append($(items))});

    // Setup new items
    this._make_hierarchy(list);

    if (this._handle_update_hierarchy != undefined) {
        this._handle_update_hierarchy(list);
    }
}

/**
 * Toggle selectability of treeview items
 *
 * @param id
 * @param bool  True for clickable, false for unclickable
 * @return void
 */
totaraDialog_handler_treeview.prototype._toggle_items = function(elid, type) {

    var handler = this;

    // Get elements from treeviews
    var selectable_spans = $('.treeview', this._container).find('span#'+elid);

    if (type) {
        selectable_spans.removeClass('unclickable');
        selectable_spans.addClass('clickable');
        if (handler._make_selectable != undefined) {
            selectable_spans.each(function(i, element) {
                handler._make_selectable($('.treeview', handler._container));
            });
        }
    }
    else {
        selectable_spans.removeClass('clickable');
        selectable_spans.addClass('unclickable');

        // Disable the anchor
        $('a', selectable_spans).unbind('click');
        $('a', selectable_spans).click(function(e) {
            e.preventDefault();
        });
    }
}

/**
 * Bind click event to elements, i.e to make them deletable
 *
 * @parent element
 * @return void
 */
totaraDialog_handler_treeview.prototype._make_deletable = function(parent_element) {
    var deletables = $('.deletebutton', parent_element);
    var handler = this;

    // Bind event to delete button
    deletables.unbind('click');
    deletables.each(function() {
        // Get the span element, containing the clicked button
        var span_element = $(this).parent();

        // If unremovable, do not add click handler
        if (span_element.hasClass('unremovable')) {
            return;
        }

        $(this).click(function() {
            // Get the span element, containing the clicked button
            var span_element = $(this).parent();

            // Make sure removed element is now selectable in treeview
            handler._toggle_items(span_element.attr('id'), true);

            // Finally, remove the span element from the selected pane
            span_element.remove();

            return false;
        });
    });
}

/**
 * @param object element the element to append
 * @return void
 */
totaraDialog_handler_treeview.prototype._append_to_selected = function(element) {
    var clone = element.closest('span').clone();  // Make a clone of the list item
    var selected_area = $('.selected', this._container)

    // Check if an element with the same ID already exists
    if ($('#'+clone.attr('id'), selected_area).size() < 1) {

        // Wrap item in a div
        var wrapped = $('<div></div>').append(clone);

        // Append item clone to selected items
        selected_area.append(wrapped);

        // Disable anchor
        $('a', wrapped).click(function(e) {
            e.preventDefault();
        });

        // Scroll to show newly added item
        selected_area.scrollTop(selected_area.children().slice(-1).position().top);

        // Make all selected items deletable
        this._make_deletable(selected_area);
    }
}



/*****************************************************************************/
/** totaraDialog_handler_treeview_multiselect **/

totaraDialog_handler_treeview_multiselect = function() {};
totaraDialog_handler_treeview_multiselect.prototype = new totaraDialog_handler_treeview();

/**
 * Setup treeview and drag/drop infrastructure
 *
 * @return void
 */
totaraDialog_handler_treeview_multiselect.prototype.every_load = function() {

    // Make decending spans assignable
    this._make_selectable($('.treeview', this._container));

    // Make spans in selected pane deletable
    this._make_deletable($('.selected', this._container));
}

/**
 * Bind hover/click event to elements, to make them selectable
 *
 * @parent element
 * @return void
 */
totaraDialog_handler_treeview_multiselect.prototype._make_selectable = function(parent_element) {

    // Get assignable/clickable elements
    var selectable_items = $('span.clickable', parent_element);
    var handler = this;

    // Unbind anchors
    var anchors = $('span.clickable a', parent_element).unbind();

    // Bind click handler to selectable items
    selectable_items.unbind('click');
    selectable_items.bind('click', function() {

        var clicked = $(this);
        handler._append_to_selected(clicked);

        // Make selected element unselectable
        handler._toggle_items(clicked.attr('id'), false);

        return false;
    });

}

/**
 * Hierarchy update handler
 *
 * @param element
 * @return void
 */
totaraDialog_handler_treeview_multiselect.prototype._handle_update_hierarchy = function(parent_element) {
    this._make_selectable(parent_element);
}


/*****************************************************************************/
/** totaraDialog_handler_treeview_multiselect_rb_filter **/

totaraDialog_handler_treeview_multiselect_rb_filter = function() {};
totaraDialog_handler_treeview_multiselect_rb_filter.prototype = new totaraDialog_handler_treeview_multiselect();

/**
 * Setup treeview and drag/drop infrastructure
 *
 * @return void
 */
totaraDialog_handler_treeview_multiselect_rb_filter.prototype.first_load = function() {
    var id = this._title;
    var linkcontainer = $('.list-'+id);
    var handler = this;
    // find all the currently selected items (by traversing the DOM on the
    // underlying page), then add them to the 'selected' panel without the
    // 'clickable' class (so they are hidden)
    // This ensures they can't be selected again from the 'choose' panel
    var preselected = '';
    $('.multiselect-selected-item', linkcontainer).each(function(i, el) {
        var item_id = $(this).data('id');
        var item_name = $(this).text();
        preselected += '<div class="treeview-selected-item"><span id="item_'+item_id+'"><a href="#">'+item_name+'</a><span class="deletebutton">'
                    + M.util.get_string('delete', 'totara_core')
                    +'</span></span></div>';
        handler._toggle_items('item_'+item_id, false);
    });
    var selected_area = $('.selected', this._container)
    selected_area.append(preselected);

    // call the original function as well
    totaraDialog_handler_treeview_multiselect.prototype.first_load.call(this);
};

totaraDialog_handler_treeview_multiselect_rb_filter.prototype._update = function(response) {
    var id = this._title;
    // update the hidden field
    var hiddenfield = $('input[name='+id+']');
    var ids = hiddenfield.val();
    var id_array = (ids) ? ids.split(',') : [];

    // pull out selected IDs from selected column
    $('#'+id+' .selected .clickable').each(function(i, el){
        id_array.push($(this).attr('id').split('_')[1]);
    });
    var combined_ids = id_array.join(',');
    hiddenfield.val(combined_ids);

    // Hide dialog
    this._dialog.hide();

    // Sometimes we want to have two dialogs changing the same table,
    // so here we support tagging tables by id, or class
    var content = $('div.list-'+this._title);

    // Replace div with updated data
    content.replaceWith(response);

    // Hide noscript objects
    $('.totara-noscript', $('div.list-'+this._title)).hide();
};



/*****************************************************************************/
/** totaraDialog_handler_treeview_singleselect **/

totaraDialog_handler_treeview_singleselect = function(value_element_name, text_element_id, dualpane) {

    // Can the value be deleted
    var deletable;

    // Can hold an externally assigned function
    var external_function;

    this.value_element_name = value_element_name;
    this.text_element_id = text_element_id;

    // Use 2 panes in the dialog for getting to the selection items
    if (dualpane != 'undefined') {
        this.dualpane = dualpane
    } else {
        this.dualpane=false;
    }
};

totaraDialog_handler_treeview_singleselect.prototype = new totaraDialog_handler_treeview();

/**
 * Hierarchy update handler
 *
 * @param element
 * @return void
 */
totaraDialog_handler_treeview_singleselect.prototype._handle_update_hierarchy = function(parent_element) {
    this._make_selectable(parent_element);
}

/**
 * Setup delete buttons
 *
 * @return  void
 */
totaraDialog_handler_treeview_singleselect.prototype.setup_delete = function() {
    this.deletable = true;

    var textel = $('#'+this.text_element_id);
    var idel = $('input[name='+this.value_element_name+']');
    var deletebutton = $('<a href="#" class="dialog-singleselect-deletable">'+M.util.get_string('delete', 'totara_core')+'</a>');
    var handler = this;

    // Setup handler
    deletebutton.click(function(e) {
        e.preventDefault()
        idel.val('');
        textel.removeClass('nonempty');
        textel.empty();
        handler.setup_delete();
    });

    if (textel.hasClass('nonempty') && textel.text().length > 0) {
        textel.append(deletebutton);
    }

}


/**
 * Setup run this on first load
 *
 * @return void
 */
totaraDialog_handler_treeview_singleselect.prototype.first_load = function() {

    // Setup dialog
    totaraDialog_handler_treeview.prototype.first_load.call(this);

    this._set_current_selected();
}

/**
 * Setup treeview and click infrastructure
 *
 * @return void
 */
totaraDialog_handler_treeview_singleselect.prototype.every_load = function() {

    this._make_selectable($('.treeview', this._container));
}

totaraDialog_handler_treeview_singleselect.prototype._set_current_selected = function() {
    var current_val = $('input[name='+this.value_element_name+']').val();
    var current_text = $('#'+this.text_element_id).clone();

    // Strip delete button from current text
    $('span', current_text).remove();
    current_text = current_text.text();

    var max_title_length = 60;
    if (!(current_val && current_text)) {
        current_val = 0;
        current_text = 'None';
    }

    label_length = $('#treeview_currently_selected_span_'+this._title+' label').html().length;
    if (current_text.length+label_length > max_title_length) {
        current_text = current_text.substring(0, max_title_length-label_length)+'...';
    }

    $('#treeview_selected_text_'+this._title).text(current_text);
    $('#treeview_selected_val_'+this._title).val(current_val);

    if (current_val != 0) {
        $('#treeview_currently_selected_span_'+this._title).css('display', 'inline');
        this._toggle_items('item_'+current_val, false);
    }
}

/**
 * Take clicked/selected item and
 * either update specified element(s)
 *
 * @param string element name to update value
 * @param string element id to update text (optional)
 * @return void
 */
totaraDialog_handler_treeview_singleselect.prototype._save = function() {
    dialog = this;

    // Get selected id
    var selected_val = $('#treeview_selected_val_'+this._title).val();

    if (selected_val === "0") {
        alert(M.util.get_string('error:'+this._title+'notselected', 'totara_core'));
        return false;
    }

    // Get selected text
    var selected_text = $('.treeview span.unclickable#item_'+selected_val+' a', dialog._container).html();

    // Update value element
    if (this.value_element_name) {
        $('input[name='+this.value_element_name+']').val(selected_val);
    }

    // Update text element
    if (this.text_element_id) {

        if (selected_text) {
            $('#'+this.text_element_id).text(selected_text);
            $('#'+this.text_element_id).addClass('nonempty');
        } else {
            $('#'+this.text_element_id).removeClass('nonempty');
        }

        if (this.deletable) {
            this.setup_delete();
        }
    }

    if (this.external_function) {
        // Execute the extra function
        this.external_function();
    }

    this._dialog.hide();
}


/**
 * Make elements run the clickhandler when clicked
 *
 * @parent element
 * @return void
 */
totaraDialog_handler_treeview_singleselect.prototype._make_selectable = function(parent_element) {

    // Get selectable/clickable elements
    var selectables = $('span.clickable', parent_element);
    var dialog = this;

    // Unbind anchors
    var anchors = $('span.clickable a', parent_element).unbind();

    // Stop parents expanding when clicking the title
    selectables.unbind('click');

    if (this.dualpane) {
        selectables.click(function() {

            var clicked = $(this);

            // Get current selection
            var current_val = $('#treeview_selected_val_'+dialog._title).val();

            // Enable current (old) selection
            dialog._toggle_items('item_'+current_val, true);

            // Disable new selection
            dialog._toggle_items($(this).attr('id'), false);

            var clicked_id = clicked.attr('id').split('_');
            clicked_id = clicked_id[clicked_id.length-1];  // The last item is the actual id
            clicked.attr('id', clicked_id);

            // Check for new-style clickhandlers
            if (dialog.handle_click != undefined) {
                dialog.handle_click($(this));
            }

            return false;
        });

        return;
    }

    // Bind click handler to selectables
    selectables.click(function() {

        var item = $(this);
        var clone = item.clone();
        var max_title_length = 60;

        // Get current selection
        var current_val = $('#treeview_selected_val_'+dialog._title).val();

        // Disable new selection
        dialog._toggle_items($(this).attr('id'), false);

        label_length = $('#treeview_currently_selected_span_'+dialog._title+' label').html().length;
        if ($('a', clone).html().length+label_length > max_title_length) {
            selected_title = $('a', clone).html().substring(0, max_title_length-label_length)+'...';
        } else {
            selected_title = $('a', clone).html();
        }

        $('#treeview_selected_text_'+dialog._title).html(selected_title);
        var selected_id = clone.attr('id').split('_')[1];
        $('#treeview_selected_val_'+dialog._title).val(selected_id);

        // Make sure the info is displayed
        $('#treeview_currently_selected_span_'+dialog._title).css('display', 'inline');

        // Enable current (old) selection
        dialog._toggle_items('item_'+current_val, true);

        // Re-bind to right elements
        dialog._make_selectable(parent_element);

        return false;
    });

    // Make currently selected item unclickable
    dialog._toggle_items('item_' + $('#treeview_selected_val_'+dialog._title).val(), false);
}

/*****************************************************************************/
/** totaraDialog_handler_skeletalTreeview **/

totaraDialog_handler_skeletalTreeview = function() {};
totaraDialog_handler_skeletalTreeview.prototype = new totaraDialog_handler_treeview();

/**
 * Setup a treeview infrastructure
 *
 * @return void
 */
totaraDialog_handler_skeletalTreeview.prototype.every_load = function() {

    // Setup treeview
    $('.treeview', this._container).treeview({
        prerendered: true
    });

    var handler = this;

    // Setup framework picker if one exists
    $('.simpleframeworkpicker', this._container).unbind('change');  // Unbind any previous events
    $('.simpleframeworkpicker', this._container).change(function() {
        handler._set_framework();
    });

    // Setup plan picker if one exists
    $('.planselector', this._container).unbind('change');  // Unbind any previous events
    $('.planselector', this._container).change(function() {
        handler._set_plan();
    });

    // Setup hierarchy
    this._make_hierarchy($('.treeview', this._container));

    // Make spans in selected pane deletable
    this._make_deletable($('.selected', this._container));
}

/**
 * Setup hierarchy click handlers
 *
 * @return void
 */
totaraDialog_handler_skeletalTreeview.prototype._make_hierarchy = function(parent_element) {
    var handler = this;

    // Load courses on parent click
    $('span.folder, div.hitarea', parent_element).click(function() {

        // Get parent
        var par = $(this).parent();

        // If we have just collapsed this branch, don't reload stuff
        if ($('li:visible', $(par)).size() == 0) {
            return false;
        }

        // Check to see if the loading placeholder exists
        if ($('> ul > li.loading', par).size() == 0) {
            return false;
        }

        // Id in format item_list_XX
        var id = par.attr('id').substr(10);

        // To be overridden in child classes
        handler._handle_hierarchy_expand(id);

        return false;
    });
}

/**
 * Update the hierarchy
 *
 * @param string    HTML response
 * @param int       Parent id
 * @return void
 */
totaraDialog_handler_skeletalTreeview.prototype._update_hierarchy = function(response, parent_id) {

    var items = response;
    var list = $('.treeview li#item_list_'+parent_id+' ul:first', this._container);

    // Remove placeholder child
    $('> li.loading', list).remove();

    // Add items
    $('.treeview', this._container).treeview({add: list.append($(items))});

    var handler = this;

    handler._make_selectable(list, false);
}

/**
* @param object element to make selectable
* @return void
*/
totaraDialog_handler_skeletalTreeview.prototype._make_selectable = function(elements, addclickable) {
    var handler = this;

    if (addclickable) {
        addclickable.addClass('clickable');
    }

    if (handler._handle_course_click != undefined) {
        // Bind clickable function to course
        $('span.clickable', elements).click(function() {
            var par = $(this).parent();

            // Get the id in format course_XX
            var id = par.attr('id').substr(7);

            // To be overridden in child classes
            handler._handle_course_click(id);
        });
    } else {
        // Bind hover handlers to clickable items
        $('span.clickable', elements).parent().mouseenter(function() {
            $('.addbutton', this._container).css("display", "none");
            $(this).find('.addbutton').css('display', 'inline');
        });
        $('span.clickable', elements).parent().mouseleave(function() {
            $(this).find('.addbutton').css('display', 'none');
        });

        // Bind addbutton
        $('span.clickable', elements).find('.list-item-action').click(function() {
            // Assign id attribute to
            handler._append_to_selected($(this));

            // Make selected element unselectable; remove addbutton
            $(this).parents('span:first').attr('class', 'unclickable');
            $(this).html('');
        });
    }

}

/*****************************************************************************/
/** totaraDialog_handler_form **/

totaraDialog_handler_form = function() {};
totaraDialog_handler_form.prototype = new totaraDialog_handler();


/**
 * Add custom submit handler to forms in dialog
 */
totaraDialog_handler_form.prototype.every_load = function() {
    var handler = this;

    var forms = $('form', this._container);
    forms.unbind('submit');

    forms.bind('submit', function(e) {
        e.preventDefault();

        handler._dialog.showLoading();

        var url = $(this).attr('action');
        var method = $(this).attr('method');
        var data = $(this).serialize();

        handler._dialog._request(
            url,
            {
                object:     handler,
                method:     '_updatePage' // Update page and close dialog on success
            },
            method,
            data
        );
    });
};


/**
 * Submit first form in dialog
 */
totaraDialog_handler_form.prototype.submit = function() {
    var form = $('form', this._container).first();
    form.submit();
};


/**
 * Update page with forms results
 *
 * @param   string  HTML response
 * @return  void
 */
totaraDialog_handler_form.prototype._updatePage = function(response) {

    // Get all root elements in response
    var els = $(response);

    // Replace any items on the main page with their content (if IDs match)
    els.each(function() {
        var id = $(this).attr('id');

        if (id) {
            $('body #'+id).replaceWith($(this));
        }
    });

    // Close dialog
    this._dialog.hide();
};

/*****************************************************************************/
/** totaraDialog_handler_selectable **/

totaraDialog_handler_selectable = function(selected) { this.selected = selected; };
totaraDialog_handler_selectable.prototype = new totaraDialog_handler();

totaraDialog_handler_selectable.prototype.first_load = function() {
    // Setup selectable
    $("#icon-selectable").selectable({
        filter: "li",
        multiple: false
    });

    //Set selection
    this._set_selected();
};

totaraDialog_handler_selectable.prototype.every_load = function() {
    totaraDialog_handler_selectable.prototype.first_load.call(this);
};

totaraDialog_handler_selectable.prototype._updatePage = function(response) {
    var suffix = (typeof this._dialog.suffix === 'undefined') ? '' : this._dialog.suffix;

    // Replace any items on the main page with their content (if IDs match).
    if (suffix === '') {
        // Ordinary course or program icons.
        $('input[name=icon]').val(response.id);
    } else {
        // Multi-select icons.
        $('#icon' + suffix).val(response.id);
    }
    $('#icon_preview' + suffix).attr('src', response.src);
    $('#icon_preview' + suffix).attr('title', (response.id).replace(/-|_/g, " ").toTitleCase());
    this._dialog.hide();
};

totaraDialog_handler_selectable.prototype._set_selected = function() {
    $('#' + this.selected).addClass("ui-selected");
    $("input[name=icon]").attr('initialvalue', this.selected);
};

String.prototype.toTitleCase = function() {
    return this.replace(/\w\S*/g, function(text) { return text.charAt(0).toUpperCase() + text.substr(1).toLowerCase(); });
};

/*****************************************************************************/
/** Factory methods **/

/**
 * Setup single-select treeview dialog that calls a handler on click
 *
 * @param string dialog name
 * @param string dialog title
 * @param string find page url
 * @param string value_element bound to this dialog (value will be updated after dialog selection)
 * @param string text_element bound to this dialog (text will be updated after dialog selection)
 * @param function handler_extra extra code to be executed with handler
 * @param boolean deletable Should the value be delelable?
 * @return void
 */
totaraSingleSelectDialog = function(name, title, find_url, value_element, text_element, handler_extra, deletable) {

    var handler = new totaraDialog_handler_treeview_singleselect(value_element, text_element);
    var buttonObj = {};
    if (deletable) {
        handler.setup_delete();
    }
    handler.external_function = handler_extra;

    buttonObj[M.util.get_string('ok', 'moodle')] = function() { handler._save() };
    buttonObj[M.util.get_string('cancel', 'moodle')] = function() { handler._cancel() };

    totaraDialogs[name] = new totaraDialog(
        name,
        'show-'+name+'-dialog',
        {
            buttons: buttonObj,
            title: '<h2>'+title+'</h2>'
        },
        find_url,
        handler
    );
}

/**
 * Setup multi-select treeview dialog that calls a save page, and
 * prints the html response to an underlying table
 *
 * @param string dialog name
 * @param string dialog title
 * @param string find page url
 * @param string save page url
 * @return void
 */
totaraMultiSelectDialog = function(name, title, find_url, save_url) {

    var handler = new totaraDialog_handler_treeview_multiselect();

    var buttonObj = {};
    buttonObj[M.util.get_string('save', 'totara_core')] = function() { handler._save(save_url) };
    buttonObj[M.util.get_string('cancel', 'moodle')] = function() { handler._cancel() };

    totaraDialogs[name] = new totaraDialog(
        name,
        'show-'+name+'-dialog',
        {
            buttons: buttonObj,
            title: '<h2>'+title+'</h2>'
        },
        find_url,
        handler
    );
}

/**
 * Setup multi-select treeview dialog for use in a report builder filter
 *
 * This is a special case of the multiselect dialog, for cases where it is
 * being used for a report builder filter. This version updates a hidden form
 * field in the underlying page (the hidden input name = dialog name) with a
 * comma-separated list of selected IDs, and also prints the selected items in a
 * specific format into a div with a class of 'list-'+name
 *
 * @param string dialog name
 * @param string dialog title
 * @param string find page url
 * @param string save page url
 * @return void
 */
totaraMultiSelectDialogRbFilter = function(name, title, find_url, save_url) {

    var handler = new totaraDialog_handler_treeview_multiselect_rb_filter();

    var buttonObj = {};
    buttonObj[M.util.get_string('save', 'totara_core')] = function() { handler._save(save_url) };
    buttonObj[M.util.get_string('cancel', 'moodle')] = function() { handler._cancel() };

    totaraDialogs[name] = new totaraDialog(
        name,
        'show-'+name+'-dialog',
        {
            buttons: buttonObj,
            title: '<h2>'+title+'</h2>'
        },
        find_url,
        handler
    );


    // activate the 'delete' option next to any selected items in filters
    // (for this dialog only)
    $(document).on('click', '.multiselect-selected-item[data-filtername='+name+'] a', function(event) {
        event.preventDefault();

        var container = $(this).parents('div.multiselect-selected-item');
        var filtername = container.data('filtername');
        var id = container.data('id');
        var hiddenfield = $('input[name='+filtername+']');

        // take this element's ID out of the hidden form field
        var ids = hiddenfield.val();
        var id_array = ids.split(',');
        var new_id_array = $.grep(id_array, function(n, i) { return n != id });
        var new_ids = new_id_array.join(',');
        hiddenfield.val(new_ids);

        // remove this element from the DOM
        container.remove();

    });

}
