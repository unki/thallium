/**
 * This file is part of Thallium.
 *
 * Thallium, a PHP-based framework for web applications.
 * Copyright (C) <2015> <Andreas Unterkircher>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.

 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 */

$(document).ready(function () {
    try {
        mbus = new ThalliumMessageBus;
    } catch (e) {
        throw new Error('Failed to load ThalliumMessageBus! '+ e);
        return false;
    }

    /* RPC handlers */
    $("table tr td a.delete").click(function () {
        delete_object($(this));
    })
    $("form.ui.form.add").on('submit', function () {
        rpc_object_update($(this));
    });
    $('.inline.editable.edit.link').click(function () {
        inlineobj = new ThalliumInlineEditable($(this));
        inlineobj.toggle();
    });
});

function show_modal(settings, do_function, modalclass)
{
    if (!modalclass) {
        modalclass = '.ui.basic.modal';
    }

    var modal_settings = {};

    if (settings.header) {
        $(modalclass + ' .header').html(settings.header);
    }

    if (settings.icon) {
        $(modalclass + ' .image.content i.icon').removeClass().addClass(settings.icon);
    } else {
        settings.icon = 'icon';
    }

    if (settings.iconHtml) {
        $(modalclass + ' .image.content i.' + settings.icon).html(settings.iconHtml);
    } else {
        $(modalclass + ' .image.content i.' + settings.icon).html('');
    }

    if (settings.content) {
        $(modalclass + ' .image.content .description p').html(settings.content);
    }

    if (typeof settings.closeable === 'undefined') {
        settings.closeable = true;
    }

    if (!settings.closeable) {
        $(modalclass + ' i.close.icon').detach();
    } else {
        $(modalclass + ' i.close.icon').appendTo('.ui.basic.modal');
    }

    if (typeof settings.hasActions === 'undefined') {
        settings.hasActions = true;
    }

    if (typeof settings.blurring === 'undefined') {
        settings.blurring = true;
    }

    if (!settings.hasActions) {
        $(modalclass + ' .actions').detach();
    } else {
        $(modalclass + ' .actions').appendTo('.ui.basic.modal');
    }

    if (!settings.onDeny) {
        settings.onDeny = function () {
            return true;
        };
    }

    if (!settings.onApprove) {
        settings.onApprove = function () {
            return true;
        };
    }

    if (!do_function) {
        do_function = function () {
            return true;
        };
    }

    modal = $(modalclass)
        .modal({
            closable  : settings.closeable,
            onDeny    : settings.onDeny,
            onApprove : settings.onApprove,
            blurring  : settings.blurring
        })
        .modal('show')
        .on('click.modal', do_function);

        return modal;
}

function safe_string(input)
{
    return input.replace(/[!"#$%&'()*+,.\/:;<=>?@[\\\]^`{|}~]/g, "\\$&");
}

function delete_object(element)
{
    var del_id = element.attr("id");

    if (typeof del_id === 'undefined' || del_id == "") {
        alert('no attribute "id" found!');
        return false;
    }

    del_id = safe_string(del_id);

    // for single objects
    if (!del_id.match(/-flush$/)) {
        return rpc_object_delete(element, del_id);
    }

    // for all objects
    show_modal({
        closeable : false,
        header : 'Flush Queue',
        icon : 'wait icon',
        content : 'This will delete all items from Queue! Are you sure?\nThere is NO undo',
        onDeny : function () {
            return true;
        },
        onApprove : function () {
            return rpc_object_delete(element, del_id);
        }
    });
}

// vim: set filetype=javascript expandtab softtabstop=4 tabstop=4 shiftwidth=4:
