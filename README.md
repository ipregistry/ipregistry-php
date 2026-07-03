[<img src="https://cdn.ipregistry.co/icons/favicon-96x96.png" alt="Ipregistry" width="64"/>](https://ipregistry.co/)
# Ipregistry PHP Client Library

[![License](http://img.shields.io/:license-apache-blue.svg)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/ipregistry/ipregistry-php)](https://packagist.org/packages/ipregistry/ipregistry-php)
[![CI](https://github.com/ipregistry/ipregistry-php/actions/workflows/ci.yml/badge.svg)](https://github.com/ipregistry/ipregistry-php/actions/workflows/ci.yml)
[![Lint](https://github.com/ipregistry/ipregistry-php/actions/workflows/lint.yml/badge.svg)](https://github.com/ipregistry/ipregistry-php/actions/workflows/lint.yml)

This is the official PHP client library for the [Ipregistry](https://ipregistry.co) IP geolocation and threat data
API, allowing you to look up your own IP address or specified ones. Responses return multiple data points including
carrier, company, currency, location, time zone, threat information, and more. The library can also parse raw
User-Agent strings.

The library builds on the PHP standard library and the cURL extension, with PSR-16 (caching) and optional PSR-18
(HTTP client) interoperability.

## Getting Started

You'll need an Ipregistry API key, which you can get along with 100,000 free lookups by signing up for a free account
at [https://ipregistry.co](https://ipregistry.co).

### Installation

```bash
composer require ipregistry/ipregistry-php
```

Requires PHP 8.2 or later with the `curl` and `json` extensions.

### Quick start

#### Single IP lookup

```php
<?php

use Ipregistry\Exception\IpregistryException;
use Ipregistry\IpregistryClient;

$client = new IpregistryClient('YOUR_API_KEY');

// Look up data for a given IPv4 or IPv6 address.
// On the server side, retrieve the client IP from the request headers.
try {
    $info = $client->lookup('54.85.132.205');
    echo $info->location->country->name;
} catch (IpregistryException $e) {
    // Handle API or network failures (see "Errors" below).
}
```

#### Origin IP lookup

To look up the IP address the request is sent from — no argument needed — use `lookupOrigin()`. It returns a
`RequesterIpInfo`, which additionally carries parsed User-Agent data:

```php
$origin = $client->lookupOrigin();
echo $origin->ip, ' ', $origin->location->country->name;
```

#### Batch IP lookup

`lookupBatch()` resolves many IP addresses in a single request. Each entry may independently succeed or fail (for
example on an invalid address), so results are inspected element by element. Iterate the list, or index into it with
`at()`, which returns the data or throws the entry's error:

```php
$list = $client->lookupBatch(['73.2.2.2', '8.8.8.8', '2001:67c:2e8:22::c100:68b']);

foreach ($list as $result) {
    if ($result->error !== null) {
        // Handle a per-entry error (e.g. an invalid IP address).
        echo 'entry failed: ', $result->error->getMessage(), PHP_EOL;
        continue;
    }
    echo $result->info->location->country->name, PHP_EOL;
}
```

The Ipregistry API accepts up to 1024 IP addresses per request. `lookupBatch()` transparently splits larger arrays
into several requests and reassembles the results in input order — so you can pass an arbitrarily long array without
hitting `TOO_MANY_IPS`. Tune the chunk size when needed:

```php
$client = new IpregistryClient('YOUR_API_KEY', maxBatchSize: 256);
```

Only cache misses are sent to the API; if a whole sub-request fails (network or API error), `lookupBatch()` throws,
whereas an individual bad address surfaces as a per-entry error as shown above.

## Lookup options

Lookups accept named arguments that map to Ipregistry query parameters:

```php
$info = $client->lookup('8.8.8.8',
    hostname: true,                              // resolve reverse-DNS hostname
    fields: 'location.country.name,security',    // select only these fields
);
```

| Argument            | Description                                                                                                                 |
|---------------------|-----------------------------------------------------------------------------------------------------------------------------|
| `hostname: bool`    | Enable reverse-DNS hostname resolution (disabled by default).                                                              |
| `fields: string`    | Restrict the response to the given [fields](https://ipregistry.co/docs/filtering-selecting-fields), reducing payload size. |
| `params: array`     | Arbitrary query parameters not covered by a dedicated argument.                                                             |

## Caching

The client accepts any [PSR-16](https://www.php-fig.org/psr/psr-16/) cache. Caching is **disabled by default** to
ensure data freshness.

The library ships with an in-process `InMemoryCache` (LRU eviction plus TTL), which is a good fit for long-running
processes such as queue workers or Swoole/FrankenPHP/RoadRunner servers:

```php
use Ipregistry\Cache\InMemoryCache;
use Ipregistry\IpregistryClient;

$client = new IpregistryClient('YOUR_API_KEY',
    cache: new InMemoryCache(maxSize: 8192, defaultTtl: 600),
);
```

In classic per-request PHP-FPM deployments, plug in a shared PSR-16 cache instead (APCu, Redis, Memcached, ...), for
example with `symfony/cache`:

```php
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Psr16Cache;

$cache = new Psr16Cache(new RedisAdapter($redisConnection));
$client = new IpregistryClient('YOUR_API_KEY', cache: $cache, cacheTtl: 600);
```

Origin (requester) lookups are never cached, because the requester IP is only known from the response. Batch lookups
transparently serve already-cached entries and only request the remainder from the API.

## Retries

Failed requests are automatically retried with an exponential backoff. By default, up to 3 retries are performed on
transient network errors and 5xx server responses.

Because Ipregistry does not rate limit by default (rate limiting is opt-in per API key), retries on
_429 Too Many Requests_ responses are **disabled by default**. Enable them if your API key is configured with a rate
limit and you want the client to wait and retry (honoring the `Retry-After` header when present):

```php
$client = new IpregistryClient('YOUR_API_KEY',
    maxRetries: 3,                  // 0 disables retries entirely
    retryInterval: 1.0,             // base backoff in seconds (interval * 2^attempt)
    retryOnServerError: true,       // retry on 5xx (default: true)
    retryOnTooManyRequests: true,   // retry on 429 (default: false)
);
```

## Timeouts and custom HTTP transports

By default the client sends requests through the cURL extension with a 15-second timeout:

```php
$client = new IpregistryClient('YOUR_API_KEY', timeout: 5.0);
```

For full control over connection pooling, proxying, TLS, or instrumentation, route requests through any
[PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client:

```php
use Ipregistry\Http\Psr18Transport;

$client = new IpregistryClient('YOUR_API_KEY',
    transport: new Psr18Transport($psr18Client, $requestFactory, $streamFactory),
);
```

When you supply a PSR-18 transport, you own the HTTP client's configuration: the `timeout` argument is ignored in
favor of your client's own settings. You can also implement `Ipregistry\Http\TransportInterface` directly.

## Errors

The library throws two exception kinds, both extending `Ipregistry\Exception\IpregistryException`:

- **`ApiException`** — the API reported a failure (e.g. insufficient credits, throttling, invalid input). It carries
  the raw code (`rawCode`), a typed `errorCode` enum (null when the raw code is not recognized), a `resolution`, and
  the HTTP `status`.
- **`ClientException`** — a client-side failure (network error, invalid argument, response decoding). The underlying
  cause is available via `getPrevious()`.

```php
use Ipregistry\Enum\ErrorCode;
use Ipregistry\Exception\ApiException;
use Ipregistry\Exception\ClientException;

try {
    $info = $client->lookup('8.8.8.8');
} catch (ApiException $e) {
    if ($e->errorCode === ErrorCode::InsufficientCredits) {
        // handle exhausted credits
    } elseif ($e->errorCode === ErrorCode::TooManyRequests) {
        // handle rate limiting
    }
} catch (ClientException $e) {
    // handle network / decoding error
}
```

The full list of error codes is documented at [ipregistry.co/docs/errors](https://ipregistry.co/docs/errors).

## Parsing User-Agents

Parse one or more raw User-Agent strings (such as the `User-Agent` header of an incoming request) into structured
data:

```php
$list = $client->parseUserAgents('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/120.0');

$userAgent = $list->at(0);
echo $userAgent->name, ' on ', $userAgent->operatingSystem->name;
```

## Filtering bots

You might want to prevent Ipregistry API calls for crawlers or bots browsing your pages. To help identify bots from
the User-Agent, the library includes a lightweight helper:

```php
use Ipregistry\UserAgents;

if (!UserAgents::isBot($_SERVER['HTTP_USER_AGENT'] ?? '')) {
    $info = $client->lookup($clientIp);
    // ...
}
```

## Examples

Runnable examples live in the [`examples/`](examples) directory. Set your key and run one:

```bash
IPREGISTRY_API_KEY=YOUR_API_KEY php examples/single.php
```

## Testing

The library ships with two tiers of tests:

- **Unit / behavior tests** run offline — partly against PHP's built-in development server — so no API key or network
  is required. This is the default `vendor/bin/phpunit` run and what CI executes on every push.
- **System tests** exercise the live Ipregistry API. They live in their own PHPUnit test suite and are skipped unless
  `IPREGISTRY_API_KEY` is set (each successful lookup consumes credits):

  ```bash
  IPREGISTRY_API_KEY=YOUR_API_KEY vendor/bin/phpunit --testsuite system
  ```

Common tasks are wired through the [`Makefile`](Makefile): `make test`, `make cover`, `make stan`, `make cs`,
`make system`, and `make all`.

## Other Libraries

There are official Ipregistry client libraries available for many languages including
[Java](https://github.com/ipregistry/ipregistry-java),
[Javascript](https://github.com/ipregistry/ipregistry-javascript),
[Python](https://github.com/ipregistry/ipregistry-python),
[Go](https://github.com/ipregistry/ipregistry-go) and more.

Are you looking for an official client with a programming language or framework we do not support yet?
[Let us know](mailto:support@ipregistry.co).

## License

This library is released under the [Apache 2.0 license](LICENSE).
