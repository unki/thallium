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

function rpc_object_delete(element, del_id)
{
    if (typeof del_id === 'undefined' || del_id == '') {
        alert('invalid "del_id" parameter found!');
        return;
    }

    $.ajax({
        type: 'POST',
        url: 'rpc.html',
        data: ({
            type : 'rpc',
            action : 'delete',
            id : del_id
        }),
        beforeSend: function () {
            // change row color to red
            element.parent().parent().animate({backgroundColor: '#fbc7c7' }, 'fast');
        },
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            alert('Failed to contact server! ' + textStatus);
        },
        success: function (data) {
            if (data == 'ok') {
                // on flushing, reload the page
                if (del_id.match(/-flush$/)) {
                    location.reload();
                    return;
                }
                element.parent().parent().animate({ opacity: 'hide' }, 'fast');
                return;
            }
            // change row color back to white
            element.parent().parent().animate({backgroundColor: '#ffffff' }, 'fast');
            alert('Server returned: ' + data + ', length ' + data.length);
            return;
        }
    });

    return true;

} // rpc_object_delete()

function rpc_object_update(element)
{
    if (!(element instanceof jQuery) ) {
        throw "element is not a jQuery object!";
        return false;
    }

    var target = element.attr('data-target');

    if (typeof target === 'undefined' || target == '') {
        alert('no attribute "data-target" found!');
        return false;
    }


    if (!(input = element.find('input[name="'+target+'"]'))) {
        throw "Failed to get input element!";
        return false;
    }

    if (!(action = input.attr('data-action'))) {
        throw "Unable to locate 'data-action' attribute!";
        return false;
    }

    if (!(model = input.attr('data-model'))) {
        throw "Unable to locate 'data-model' attribute!";
        return false;
    }

    if (!(key = input.attr('data-key'))) {
        throw "Unable to locate 'data-key' attribute!";
        return false;
    }

    if (!(id = input.attr('data-id'))) {
        throw "Unable to locate 'data-id' attribute!";
        return false;
    }

    if (!(value = input.val())) {
        return false;
    }

    action = safe_string(action);
    model = safe_string(model);
    key = safe_string(key);
    id = safe_string(id);
    value = safe_string(value);

    if (
        typeof window.location.pathname !== 'undefined' &&
        window.location.pathname != '' &&
        !window.location.pathname.match(/\/$/)
    ) {
        url = window.location.pathname;
    } else {
        url = 'rpc.html';
    }

    $.ajax({
        type: 'POST',
        url: url,
        data: ({
            type   : 'rpc',
            action : action,
            model  : model,
            id     : id,
            key    : key,
            value  : value
        }),
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            alert('Failed to contact server! ' + textStatus);
        },
        success: function (data) {
            if (data != 'ok') {
                alert('Server returned: ' + data + ', length ' + data.length);
                return;
            }
            if (action == 'add') {
                location.reload();
                return;
            }
            return;
        }
    });

    return true;
}

function rpc_fetch_jobstatus()
{
    if (!mbus.poll()) {
        throw 'MessageBus.poll() returned false!';
        return false;
    }
}

function rpc_object_delete2(element)
{
    if (!(element instanceof jQuery) ) {
        throw "element is not a jQuery object!";
        return false;
    }

    if (!(id = element.attr('data-id'))) {
        alert('no attribute "data-id" found!');
        return false;
    }

    if (!(guid = element.attr('data-guid'))) {
        alert('no attribute "data-guid" found!');
        return false;
    }

    id = safe_string(id);
    guid = safe_string(guid);

    if (
        typeof window.location.pathname !== 'undefined' &&
        window.location.pathname != '' &&
        !window.location.pathname.match(/\/$/)
    ) {
        url = window.location.pathname;
    } else {
        url = 'rpc.html';
    }

    $.ajax({
        type: 'POST',
        url: url,
        data: ({
            type   : 'rpc',
            action : 'delete-document',
            id     : id,
            guid   : guid
        }),
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            alert('Failed to contact server! ' + textStatus);
        },
        success: function (data) {
            if (data != 'ok') {
                alert('Server returned: ' + data + ', length ' + data.length);
                return;
            }
            location.reload();
            return;
        }
    });

    return true;
}

// vim: set filetype=javascript expandtab softtabstop=4 tabstop=4 shiftwidth=4:
