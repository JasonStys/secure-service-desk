# Documentation refresh — 2026-11-09

By Jason Stys.

This documentation-only edition describes source `561f1a428594d3a12fd71c9c969e8e6f6da4efe3`. It does not change application behavior, accounts, private data, installers or published releases.

## Published editions and completed review

| File | Bytes | SHA-256 | Reviewed scope |
| --- | ---: | --- | --- |
| [project-guide.docx](project-guide.docx) | 47324 | `b6296772253f1560b2ed5c9576d69ceb77787f30d063df689c0a2235e26c7ab5` | 8 pages, each viewed individually |

Word review checked plain black titles, readable pagination, whole table rows, source references, practical limits and absence of clipping or overflow. Render receipts were matched to the current DOCX digest before approval.

## Evidence and change boundary

- Functional baseline: `561f1a428594d3a12fd71c9c969e8e6f6da4efe3`.
- Workflow observation: `2026-11-10T05:25:40.628667+00:00`; exact run URLs and source identities remain in [publications.json](publications.json).
- Local document-package validation verifies exact copied bytes, Office ZIP integrity, approved page/tab identities and internal documentation links. Repository-specific documentation and source-policy commands are run separately and recorded in the integration handoff.
- Source/index metadata is regenerated using the repository’s existing tools. Existing privacy, security, coverage and workflow gates are retained.
- PR and later main workflows must pass independently. This record does not claim future or unrun integration CI success.

## Interpretation

The Word files document the cited source; dated source reports retain their original execution scope. A passing workflow is distinct from completed analysis with zero findings, and neither establishes production suitability. Checksums identify exact bytes, not a signature or publisher attestation.

Use the [handbook](../handbook.md) to choose usage, design, security and test documentation. Keep editable Markdown and reviewed editions together; do not replace historical measurements with undated claims.

## Integration-local checks

Observed at `2026-11-10T08:06:19.374333+00:00` on Windows with this repository’s existing development tools. Python commands use its isolated development environment. The documentation source baseline remains the immutable revision above; these checks validate this documentation integration locally.

| Command | Observed result |
| --- | --- |
| `php tools/validate_repo.php` | Passed, exit 0. |
| `php tools/validate_repo.php --check` | Passed, exit 0. |
| `php vendor/bin/pint --test` | Passed, exit 0. |
| `php vendor/bin/phpunit` | Passed, exit 0. |
| `node --test tests/frontend/search.test.js` | Passed, exit 0. |

The independent publication audit also matched every copied Office file to its approved SHA-256, checked ZIP package integrity and current page/tab approvals, compared immutable workflow/source metadata, and resolved every local handbook/report link within the repository. Hosted PR/main, browser, platform and container jobs remain separate required checks; they are not inferred from these local results.



All 34 existing PHP tests passed with 166 assertions using temporary in-memory SQLite test state, alongside Pint, both source/index/link/privacy gate modes and 4 JavaScript search tests. No real .env, applicant/account data, email provider, production database or installed app was modified. Real browser and PostgreSQL integration lanes remain separately required.
