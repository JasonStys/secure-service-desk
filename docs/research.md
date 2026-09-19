# Technology research

Research was refreshed on 2026-09-18 from primary documentation.

## Runtime selection

- [PHP supported versions](https://www.php.net/supported-versions.php) shows PHP 8.5 in active support, making it preferable to a preview branch for a new portfolio application.
- [PHP downloads](https://www.php.net/downloads.php) identifies the current stable 8.5 release used for local validation.
- [Laravel release notes](https://laravel.com/docs/13.x/releases) document Laravel 13's PHP requirement and support policy. The project uses the current stable major and records exact dependency versions in `composer.lock`.
- [Composer download documentation](https://getcomposer.org/download/) provides the signed installer/checksum workflow used to bootstrap Composer locally.
- [PostgreSQL full-text search](https://www.postgresql.org/docs/current/textsearch.html) supports indexed, language-aware search without concatenating untrusted SQL.

## Security and reliability guidance

- [Laravel CSRF documentation](https://laravel.com/docs/13.x/csrf) supports token verification on the browser route group.
- [Laravel validation documentation](https://laravel.com/docs/13.x/validation) informs bounded request rules and enum validation.
- [Laravel rate limiting documentation](https://laravel.com/docs/13.x/rate-limiting) informs per-actor API throttling.
- [OWASP Authorization Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html) recommends deny-by-default and validating permissions on every request; object checks therefore live below the controller.
- [OWASP Cross Site Scripting Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html) supports context-aware output encoding; Blade escaped output is used and regression-tested.
- [OWASP Webhook Security Guidelines](https://cheatsheetseries.owasp.org/cheatsheets/Webhook_Security_Guidelines.html) informs HMAC verification, replay-safe event identifiers, and input limits.

## Design implications

PHP/Laravel is appropriate here because request validation, database transactions, server rendering, queues, scheduling, and CSRF controls are cohesive framework capabilities. PostgreSQL is the production target for relational constraints, concurrent row locking, and indexed full-text search. Vanilla JavaScript keeps the accessibility and state surface small because the primary workflow does not need a client application framework.

All third-party version ranges remain constrained by lockfiles, dependency audits, automated update proposals, and CI. A dependency update is accepted only after the full test and build gate passes.
