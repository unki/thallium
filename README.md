# Thallium Framework - a PHP-based framework for web applications

## Introduction

Thallium is a PHP 7 compatible framework helping to develop web applications.
It is provided open source (see License section below).
And yes - it is yet another PHP framework.

## Design

Thallium is designed in a [model-view-controller architecture](https://en.wikipedia.org/wiki/Model–view–controller) and basically consists of:

* Controllers
 * Database communication
 * Error reporting
 * Session management
 * Cache management
 * Data paging
 * HTTP routing
 * Background jobs controller
* Models
  * Models with fields
  * Models with items (= one or more child models)
* Viewѕ
  * Listing
  * Editing
  * Inline editing.
  * Smarty3 templates

### Controllers

Controllers basically provide the logic of the framework.

Thallium already comes with multiple controllers like a **DatabaseController** that takes care of communicating with a SQL-compatible database or a **MessageBusController** which allows asynchronous communication with a client (e.g. a browser). Also a **PagingController** is on-board that helps to display a bigger amount of data.

Additional controllers can easily be added which can rely on methods that are provided by a **DefaultController**.

### Models

Models are the representation of a data object. Thallium right now comes with two types of models:

  * Models with fields
  * Models with items

#### Field Model

In Thallium, Models are basically PHP object orientated classes. One of the differences is that instead of storing model data directly into class properties, Thallium uses an additional layer called **fields** to store the data. Fields can be declared with different types like **integers** (with a min and max value), **strings** (with a maximum length), **timestamps** (unix timestamps), ...

#### Items Model

Items Models are sharing the same code-base with Field Models. The only difference is that Items Models do not have fields - but they are having items instead. An Item is nothing else than a Field Model. So an Items Model actually does have zero or more Field Models that it is going to represent and allows to handle multiple Field Models at once (updating, deleting, filtering, etc.).

### Views

Views are managing the frameworks output. They provide different way to display and edit a single Field Model or listing items of an Items Model. To keep HTML-code out of Views as much as possible, Views interact with [Smarty3 templates](http://smarty.net).

### Client-side Integration

On client-side (e.g. browsers) Thallium provides its own Javascript-based libraries to interact with the framework. In the background it utilizes [jQuery](http://jquery.com). Right now these client-side libraries provide:

* **ThalliumStore** - a common interface that is used by the other Thallium libraries to store data on the client-side (inpersistent right now).
* **ThalliumInlineEditable** - allowing to inline-edit a Field Model without the need of a traditional overall-form-concept (but in fact it still uses HTML-forms).
* **ThalliumMessageBus** - send/receive messages to/from the (server-side) Thallium framework allowing asynchronous communication.
* **Remote Procedure Calls** - traditional RPC-support.

## License

This software is licensed under **GNU Affero General Public License 3**.
See the LICENSE file or [gnu.org](http://www.gnu.org/licenses/agpl-3.0.de.html) for more.

## Side Note

Right now development of Thallium is a one-man show. Please also consider more proven frameworks like [Zend Framework](https://framework.zend.com) or [Symfony](https://symfony.com). Thallium is my approach to have a slim framework that I can use as a common code-base for my own projects allowing me some kind of rapid development :)

By automated-testing and using code-analyzers I am trying to keep it secure. But well - this is not guaranteed and only best effort. Nothing else.

## Links

* [Thallium API documentation](https://github.com/unki/thallium-docs)
* [Thallium automated-testing suite](https://github.com/unki/thallium-tests)

## Copyright

(c) 2015-2016 Andreas Unterkircher <unki@netshadow.net>
