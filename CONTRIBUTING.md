# Contributing

Thanks for your interest in improving the official Ipregistry PHP client.

## Development

The library targets PHP 8.2+ and keeps its runtime dependencies minimal: the `curl` and `json` extensions plus the
`psr/simple-cache` interfaces — please keep it that way.

Common tasks are wired through the [`Makefile`](Makefile):

```bash
make install     # composer install
make test        # run the offline test suite
make cover       # run tests with coverage (requires pcov or xdebug)
make stan        # PHPStan static analysis (level max)
make cs          # fail if any file violates the coding style
make cs-fix      # fix coding style issues
make validate    # composer validate --strict
make all         # validate + cs + stan + test
```

Before opening a pull request, please make sure `make all` passes.

## Guidelines

- **Public API stability.** This package is on 1.x; avoid breaking changes to the public API. If a breaking change is
  unavoidable, it must go through a new major version.
- **Naming.** Follow PSR-12 and the existing conventions: `Ip` prefix (not `IP`), readonly models, string-backed
  enums for closed value sets.
- **Errors.** Surface API failures as `ApiException` and client-side failures as `ClientException`; keep underlying
  causes reachable through `getPrevious()`.
- **Tests.** Add or update tests for any behavior change. Offline behavior is tested against a scripted transport and
  PHP's built-in development server; no live API key is required. Live system tests live in the `system` PHPUnit test
  suite and run against the real API when `IPREGISTRY_API_KEY` is set (`make system`); they consume credits, so keep
  them minimal.
- **Docs.** Keep the `README.md`, docblocks, and runnable examples in `examples/` in sync with the code.
- **Changelog.** Record notable changes in `CHANGELOG.md` under `[Unreleased]`.

## Reporting issues

For bugs or feature requests, please open a GitHub issue. For account or API questions, contact
[support@ipregistry.co](mailto:support@ipregistry.co).
