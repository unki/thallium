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
    var id = element.attr("data-id");

    if (typeof id === 'undefined' || id == "") {
        throw new Error('no attribute "data-id" found!');
        return;
    }

    id = safe_string(id);

    if (id == 'selected') {
        id = new Array;
        $('.checkbox.item.select[id!="select_all"]').each(function () {
            if (!($(this).checkbox('is checked'))) {
                return true;
            }
            var item = $(this).attr('id')
            if (typeof item === 'undefined' || !item || item == '') {
                return false;
            }
            item = item.match(/^select_(\d+)$/);
            if (typeof item === 'undefined' || !item || !item[1] || item[1] == '') {
                return false;
            }
            var item_id = item[1];
            id.push(item_id);
        });
        if (id.length == 0) {
            return true;
        }
    }

    var title = element.attr("data-modal-title");

    if (typeof title === 'undefined' || title === "") {
        throw 'No attribute "data-modal-title" found!';
        return false;
    }

    var text = element.attr("data-modal-text");

    if (typeof text === 'undefined' || text === "") {
        if (id instanceof String && !id.match(/-all$/)) {
            text = "Do you really want to delete this item?";
        } else {
            text = "Do you really want to delete all items?";
        }
    }

    var elements = new Array;
    if (id instanceof Array) {
        id.forEach(function (value) {
            elements.push($('#delete_link_'+value));
        });
    } else {
        elements.push(element);
    }

    show_modal('confirm', {
        header : title,
        icon : 'red remove icon',
        content : text,
        onDeny : function () {
            return true;
        },
        onApprove : function () {
            $(this).modal('hide');
            return rpc_object_delete(elements, function () {
                if (typeof elements === 'undefined') {
                    return true;
                }
                if (typeof id !== 'undefined' && id == 'all') {
                    $('table#datatable tbody tr').each(function () {
                        $(this).hide(400, function () {
                            $(value).remove();
                        });
                    });
                    return true;
                }
                elements.forEach(function (value) {
                    $(value).closest('tr').hide(400, function () {
                        $(value).remove();
                    });
                });
                return true;
            });
        },
    });

    return true;
}

// vim: set filetype=javascript expandtab softtabstop=4 tabstop=4 shiftwidth=4:
