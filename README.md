# HTTP Emitter

[![License](https://poser.pugx.org/httpsoft/http-emitter/license)](https://packagist.org/packages/httpsoft/http-emitter)
[![Latest Stable Version](https://poser.pugx.org/httpsoft/http-emitter/v)](https://packagist.org/packages/httpsoft/http-emitter)
[![Total Downloads](https://poser.pugx.org/httpsoft/http-emitter/downloads)](https://packagist.org/packages/httpsoft/http-emitter)
[![GitHub Build Status](https://github.com/httpsoft/http-emitter/workflows/build/badge.svg)](https://github.com/httpsoft/http-emitter/actions)
[![GitHub Static Analysis Status](https://github.com/httpsoft/http-emitter/workflows/static/badge.svg)](https://github.com/httpsoft/http-emitter/actions)
[![Scrutinizer Code Coverage](https://scrutinizer-ci.com/g/httpsoft/http-emitter/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/httpsoft/http-emitter/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/httpsoft/http-emitter/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/httpsoft/http-emitter/?branch=master)

This package emitting implementations of [Psr\Http\Message\ResponseInterface](https://github.com/php-fig/http-message/blob/master/src/ResponseInterface.php) from [PSR-7 HTTP Message](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message.md).

## Documentation

* [In English language](https://httpsoft.org/docs/emitter).
* [In Russian language](https://httpsoft.org/ru/docs/emitter).

## Installation

This package requires PHP version 7.4 or later.

```
composer require httpsoft/http-emitter
```

## Usage SapiEmitter

```php
use HttpSoft\Emitter\SapiEmitter;
use Psr\Http\Message\ResponseInterface;

/** @var ResponseInterface $response */
$response->getBody()->write('Content');

$emitter = new SapiEmitter();
$emitter->emit($response);
// Output result: 'Content'
```

By default, the entire content of the response is emitted. To emit the content in parts, it is necessary to specify a maximum buffer length:

```php
$emitter = new SapiEmitter(8192);
$emitter->emit($response);
// Output result: 'Content'
```

Emitting only part of the content using the `Content-Range` header:

```php
$emitter = new SapiEmitter(8192);
$emitter->emit($response->withHeader('Content-Range', 'bytes 0-3/7'));
// Output result: 'Cont'
```

To emitting only the status line and headers without a body, it is necessary to specify `true` as the second parameter:

```php
$emitter = new SapiEmitter(8192);
$emitter->emit($response, true);
// Output result: ''
```
