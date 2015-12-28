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

var ThalliumMessageBus = function (id) {
    this.element = id;
    this.messages = new Array;
    this.recvMessages = new Array;
    this.subscribers = new Object;
    this.pollerId;
    this.rpcEnabled = true;

    if (!(this.pollerId = setInterval("mbus.poll()", 1000))) {
        throw 'Failed to start ThalliumMessageBus.poll()!';
        return false;
    }

    $(document).on('Thallium:notifySubscribers', function (event) {
        this.notifySubscribers();
    }.bind(this));

    return true;
};

ThalliumMessageBus.prototype.add = function (message) {
    if (!message) {
        throw 'No message to add provided!';
        return false;
    }

    if (typeof(message) != 'object') {
        throw 'parameter is not an object!';
        return false;
    }

    this.messages.push(message);
    return true;
}

ThalliumMessageBus.prototype.fetchMessages = function () {
    var fetched_messages = new Array;
    var message;

    while ((message = this.messages.shift())) {
        fetched_messages.push(message);
    }

    return fetched_messages;
}

ThalliumMessageBus.prototype.getMessagesCount = function () {
    return this.messages.length;
}

ThalliumMessageBus.prototype.getReceivedMessages = function () {
    var _messages = new Array;

    while (message = this.recvMessages.shift()) {
        _messages.push(message);
    }
    return _messages;
}

ThalliumMessageBus.prototype.getReceivedMessagesCount = function () {
    return this.recvMessages.length;
}

ThalliumMessageBus.prototype.send = function (messages) {
    // will not send an empty message
    if (!this.getMessagesCount()) {
        return true;
    }

    var messages;

    if ((messages = this.fetchMessages()) === undefined) {
        throw "fetchMessages() failed!";
        return false;
    }

    try {
        json_str = JSON.stringify(messages);
    } catch (e) {
        throw 'Failed to convert messages to JSON string! '+ e;
        return false;
    }

    if (!(md = forge.md.sha1.create())) {
        throw 'Failed to initialize forge SHA1 message digest!';
        return false;
    }

    if (!md.update(json_str)) {
        throw 'forge SHA1 failed on json input!';
        return false;
    }

    var json = new Object;
    json.count = messages.length;
    json.size = json_str.length;
    json.hash = md.digest().toHex();
    json.json = json_str;

    try {
        var submitmsg = JSON.stringify(json);
    } catch (e) {
        throw 'Failed to convert messages to JSON string! '+ e;
        return false;
    }

    if (!submitmsg) {
        throw 'No message to send provided!';
        return false;
    }

    if (typeof(submitmsg) != 'string') {
        throw 'parameter is not a string!';
        return false;
    }

    $.ajax({
        context: this,
        global: false,
        type: 'POST',
        url: 'rpc.html',
        retries: 0,
        data: ({
            type : 'rpc',
            action : 'submit-messages',
            messages : submitmsg
        }),
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            if (textStatus == 'timeout') {
                this.retries++;
                if (this.retries <= 3) {
                    $.ajax(this);
                    return;
                }
            }
            throw 'Failed to contact server! ' + textStatus;
            return false;
        },
        success: function (data) {
            if (data != "ok") {
                throw 'Failed to submit messages! ' + data;
                return false;
            }
        }.bind(this)
    });

    return true;
}

ThalliumMessageBus.prototype.poll = function () {
    $.ajax({
        context: this,
        global: false,
        type: 'POST',
        url: 'rpc.html',
        retries: 0,
        data: ({
            type : 'rpc',
            action : 'retrieve-messages',
        }),
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            if (textStatus == 'timeout') {
                this.retries++;
                if (this.retries <= 3) {
                    $.ajax(this);
                    return;
                }
            }
            throw 'Failed to contact server! ' + textStatus;
        },
        success: function (data) {
            this.parseResponse(data);
        }.bind(this)
    });

    return true;
}

ThalliumMessageBus.prototype.parseResponse = function (data) {
    if (!data) {
        throw 'Requires data to be set!';
        return false;
    }

    try {
        json = JSON.parse(data);
    } catch (e) {
        console.log(data);
        throw 'Failed to parse response! ' + e;
        return false;
    }

    if (
        json.hash == undefined ||
        json.size == undefined ||
        json.json == undefined ||
        json.count == undefined
    ) {
        throw 'Response is invalid!';
        return false;
    }

    if (json.json.length != json.size) {
        throw 'Response size does not match!';
        return false;
    }

    if (!(md = forge.md.sha1.create())) {
        throw 'Failed to initialize forge SHA1 message digest!';
        return false;
    }

    if (!md.update(json.json)) {
        throw 'forge SHA1 failed on json input!';
        return false;
    }

    if (json.hash != md.digest().toHex()) {
        throw 'Hash does not match!';
        return false;
    }

    // no messages included? then we are done.
    if (json.count == 0) {
        return true;
    }

    try {
        messages = JSON.parse(json.json);
    } catch (e) {
        console.log(data);
        throw 'Failed to parse JSON field!' + e;
        return false;
    }

    if (messages.length != json.count) {
        throw 'Response meta data stat '+ json.count +' message(s) but only found '+ messages.length +'!';
        return false;
    }

    for (var message in messages) {
        this.recvMessages.push(messages[message]);
    }

    $(document).trigger("Thallium:notifySubscribers");
    return true;
};

ThalliumMessageBus.prototype.subscribe = function (name, category, handler) {
    if (!name) {
        throw 'No name provided!';
        return false;
    }

    if (!category) {
        throw 'No category provided!';
        return false;
    }

    if (!handler) {
        throw 'No handler provided!';
        return false;
    }

    if (this.subscribers[name]) {
        throw 'A subscriber named '+ name +' has already been registered. It has been unsubscribed now!';
        this.unsubscribe(name);
    }

    this.subscribers[name] = new Object;
    this.subscribers[name].category = category;
    this.subscribers[name].handler = handler;
    return true;
}

ThalliumMessageBus.prototype.unsubscribe = function (name) {
    if (!this.subscribers[name]) {
        return true;
    }

    delete this.subscribers[name];
    return true;
}

ThalliumMessageBus.prototype.getSubscribers = function (category) {
    if (!category) {
        return this.subscribers;
    }

    subscribers = new Array;
    for (var subname in this.subscribers) {
        if (this.subscribers[subname].category != category) {
            continue;
        }
        subscribers.push(this.subscribers[subname]);
    }

    return subscribers;
}

ThalliumMessageBus.prototype.notifySubscribers = function () {
    var subscribers;
    var messages;

    // if there are no messages pending, we do not bother our
    // subscribers.
    if (!(cnt = this.getReceivedMessagesCount())) {
        return true;
    }

    if (!(messages = this.getReceivedMessages())) {
        throw 'Failed to query received messages!';
        return false;
    }

    for (var msgid in messages) {
        if (!(subscribers = this.getSubscribers(messages[msgid].command))) {
            throw 'Failed to retrieve subscribers list!';
            return false;
        }

        for (var subid in subscribers) {
            if (!subscribers[subid].handler(messages[msgid])) {
                throw 'Subscriber "'+ subid +'" returned false!';
                return false;
            }
        }
    }
    return true;
}

// vim: set filetype=javascript expandtab softtabstop=4 tabstop=4 shiftwidth=4:
