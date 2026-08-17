# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Added
- New property `Connection::$isAnycast` (JSON `connection.is_anycast`): whether the IP address is announced via
  anycast, i.e. simultaneously from multiple locations.

## [1.0.0] - 2026-07-03
### Added
- Initial release of the official PHP client library for the Ipregistry API.
- `IpregistryClient::lookup()` for single IP address lookups, `lookupOrigin()` for origin (requester) lookups
  returning parsed User-Agent data, and `lookupBatch()` for resolving many IP addresses at once. Batch lookups
  transparently split arrays larger than the API's 1024-address limit into several requests (configurable via the
  `maxBatchSize` argument) and reassemble results in input order.
- `IpregistryClient::parseUserAgents()` for parsing raw User-Agent strings into structured data.
- Batch results (`IpInfoList`, `UserAgentList`) expose per-entry success or failure via `at()` and standard
  `Countable`/`IteratorAggregate` iteration.
- Configuration through named constructor arguments: base URL, timeout, retries, retry interval, retry-on-5xx,
  retry-on-429, batch size, cache, cache TTL, User-Agent, and HTTP transport.
- Lookup options as named method arguments: `fields`, `hostname`, and free-form `params`.
- Optional caching through any PSR-16 cache, plus a bundled `InMemoryCache` with LRU eviction and TTL. Caching is
  disabled by default; origin lookups are never cached and batch lookups reuse cached entries.
- Automatic retries with exponential backoff for transient network errors and 5xx responses, honoring the
  `Retry-After` header. Retries on 429 Too Many Requests are disabled by default.
- Typed errors: `ApiException` (with raw code and typed `ErrorCode` enum) and `ClientException`, both extending
  `IpregistryException`.
- Readonly, fully typed response models with `IpType`, `CompanyType`, and `ConnectionType` enums.
- Pluggable HTTP layer: a zero-dependency cURL transport by default, a PSR-18 adapter (`Psr18Transport`), and the
  `TransportInterface` extension point.
- `UserAgents::isBot()` helper to skip lookups for crawler traffic.
- Offline unit/behavior tests using PHP's built-in development server, plus opt-in live system tests in a dedicated
  PHPUnit test suite (run with `IPREGISTRY_API_KEY` set).

[Unreleased]: https://github.com/ipregistry/ipregistry-php/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/ipregistry/ipregistry-php/releases/tag/v1.0.0
