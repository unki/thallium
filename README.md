# Thallium Framework - a PHP-based framework for web applications

## Introduction

Thallium is an PHP 7 compatible framework for developing web applications.
It is provided open source (see License section) - and yes, its yet another PHP framework.

## Design

Thallium is using a [MVC-approach](https://en.wikipedia.org/wiki/Model–view–controller) for its design.

Basically it consists of:

* Controllers
* Models
  * Models with fields
  * Models with items (= one or more child models)
* Viewѕ
  * Smarty3 templates

### Controllers

Controllers basically provide the logic of the framework. Thallium already provides multiple controllers like a **DatabaseController** that takes care of communicating with a SQL-compatible database or a **MessageBusController** which allows to asynchronous communicate with a client (e.g. a browser). Also a **PagingController** is on-board that helps you to display a bigger amount of data.

### Models

Models are the representation of a data object. Thallium right now comes with two types of models:

  * Models with fields
  * Models with items

#### Field Model

Models are basically PHP object orientated classes. The only difference is that instead of storing model data directly into class properties, Thallium uses an additional layer called **fields** to store the data. Fields can be declared with different types like **integers** (with a min and max value), **strings** (with a maximum length), **timestamps** (unix timestamps), ...

#### Items Model

Items Models are sharing the same code-base as Field Models. The only difference is that Items Models do not have fields - but they are having items instead. An Item is nothing else than a Field Model. So an Items Model actually does have zero or more Field Models that it is going to represent and allows to handle multiple Field Models at once (deleting, filtering, etc.).

### Views

Views are managing the frameworks output. They provide ways to display and edit a single Field Model or listing items of an Items Model. To keep HTML-code out of Views as much as possible, Views interact with [Smarty3 templates](http://smarty.net).

### Client-side Integration

On client-side (e.g. browsers) Thallium provides its own Javascript-based libraries to interact with the framework. In the background it utilizes [jQuery](http://jquery.com). Right now these client-side libraries provide:

* **ThalliumStore** - a common interface that is used by the other Thallium libraries to store data on the client-side.
* **ThalliumInlineEditable** - allowing to inline-edit a Field Model without the need of a traditional overall-form-concept (but in fact it still uses HTML <form>s).
* **ThalliumMessageBus** - send/receive messages to/from the (server-side) framework allowing asynchronous communication.
* **Remote Procedure Calls** - traditional RPC-support.

## License

This software is licensed under **GNU Affero General Public License**.
See the LICENSE file or [gnu.org](http://www.gnu.org/licenses/agpl-3.0.de.html) for more.

## Side Note

Development of Thallium right now is a one-man show. So please also consider other frameworks like [Zend Framework](https://framework.zend.com) or [Symfony](https://symfony.com). Thallium is my own approach to have a slim framework that I can use as the code-base for my other project allowing me some kind of rapid development.

## Links

* [Thallium API documentation](https://github.com/unki/thallium-docs)
* [Thallium automated-testing suite](https://github.com/unki/thallium-tests)

## Copyright

(c) 2015-2016 Andreas Unterkircher <unki@netshadow.net> 
