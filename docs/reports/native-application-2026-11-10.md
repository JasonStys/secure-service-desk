# Native application validation — 2026-11-10

The 1.1.0 candidate runs the actual Laravel/PHP/Blade/SQLite service in a sandboxed native Electron
window. It does not replace domain logic with canned HTTP responses or reset work at startup.
These are actual local results from a synthetic owned profile, not a future hosted/publication claim.

| Gate | Observed result |
| --- | --- |
| Complete original + native PHP suite | 35 tests passed, 172 assertions; in-memory SQLite under explicit public test settings |
| PHP formatter / syntax | Passed for new native PHP, actual seeder/test and repository policy; no syntax warnings accepted |
| Original frontend unit tests | All four passed |
| Independent native profile/file/CGI/argv tests | All 14 passed, including actual profile restart/preservation, unknown-folder refusal, real hardlinks/bytes, malformed/ambiguous headers and outside redirects |
| Original desktop/mobile browser workflows | All 22 passed, zero retries, actual PHP 8.5.12 and Chromium; includes CSRF, stale/foreign ownership, pagination, no-JS forms and scoped axe/overflow checks |
| Existing browser isolation/format guards | Passed: bad port, absent private fixture, occupied-port refusal and preservation of the owned listener |
| Complete Composer metadata/audit | Strict metadata valid; zero advisories/abandoned/filter entries in the actual locked query |
| Complete npm graph audit | Zero known vulnerabilities after the narrow tested minimatch 11.0.1 override; no force downgrade or advisory suppression |
| Actual SDK preparation recipe | Fixed official PHP/Composer downloads SHA256-verified before extraction; selected app-local CRT signatures valid; no installation/global setting change |
| Actual extracted portable app | Complete ZIP and SHA256 created, extracted to a new directory; two native EXE launches passed real requester-create/agent-assign/comment/lifecycle/search/role/restart assertions under a system-only PATH |

The actual bundled PHP subprocess reported **8.5.12** at startup; the native acceptance checks this
runtime observation against the package record, not only a metadata label. Native PE author/file
version were **Jason Stys / 1.1.0**. The actual UI showed the native version/help and an authored ticket
icon was embedded. The test created a requester-owned fictional calibration ticket, observed initial
version 1, assigned version 2 and confirmed transition version 3. Canceling the native confirmation
left version 2 unchanged. A stale assignment remained refused at version 2. Missing CSRF returned419,
a direct PHP-script URL returned404, a CGI-option-shaped query returned400, and an unauthenticated
local request returned403. A malformed Latin-1 nonce also returned403 instead of causing a timing-safe
comparison length error. Requester management controls stayed absent and another tenant could not
read that ticket. Search found it; restart retained the exact comment and version3. Both actual parent
processes exited0 and both old local HTTP listeners were gone. No outside application request was
observed. These are specific synthetic assertions, not 100% coverage of all desktop/platform paths.

The accepted local ZIP SHA256 was `69c5469fe3a9c512c0178a288cb90186500a73a3d0dfea1b0f546de7b01c92e1`.
It is an honestly dirty local candidate based on the prior source; final clean-commit hosted acceptance
remains required. The launched application read and hashed its own two ASAR launcher modules;
acceptance compared those bytes with the package record and current checkout. Reusing an earlier
prototype lacking those bindings correctly failed before launch. This guards test/source mix-ups;
it is not attestation against a malicious OS owner.

Actual testing found and fixed an Electron ESM readiness deadlock, an omitted Blade-view packaging
directory and a form-policy conflict. The original `no-referrer` policy produced the standard's
`Origin:null` on navigation POST; the app now uses `same-origin`, retaining strict Origin/nonce/CSRF
checks and valid same-origin Laravel back navigation. Test assumptions about wrapped select labels,
automatic dialog dismissal and requester ownership were corrected to match the real existing UI;
no assertion, role rule or deadline was relaxed. A native icon build required a 256-pixel ICO rather
than the initial 64-pixel prototype. Earlier failed candidates and diagnostics remain separate from
accepted results. Routine native CI retains only small synthetic receipts/screenshots, not private
profiles or large packages.

Limits: unsigned Windows portable candidate, not a signed installer, clean-machine certification,
normal-profile installation transaction or populated-user migration. External OS input/desktop-icon
clicks were not automated. Only new marked synthetic profiles were accessed. Actor choice remains a
single-OS-owner reference flow, not authenticated workforce identity. Database/history is plaintext;
the local key encrypts Laravel cookies, not the whole database. Outbox/SLA workers remain documented
reference CLI features, not automatic external delivery. Existing PostgreSQL/container and semantic
JavaScript CodeQL/full hosted gates remain pending at this local report; Docker on the PC was not
used or modified. Historical Word guides retain their earlier source baseline until a separately
reviewed illustrated edition is produced. Native composition is not included in an invented PHP
whole-application coverage denominator.

## First hosted source review

The initial committed source passed the original PHP/PostgreSQL, asset, container, repository,
browser and actual Windows extracted-app jobs. Its processed 201-rule JavaScript analysis reported
one `js/file-system-race` result at the marker-creation path; the aggregate correctly failed.
Marker creation already used exclusive `wx`, but the redundant earlier marker `lstat` was removed.
Initialization now inventories the directory, creates a new marker exclusively only for an empty
directory, and reads an existing identified profile through the bounded descriptor reader. A real
regression verifies generated identity, exact marker/database preservation on reopen, refusal of an
unknown nonempty folder and invalid identity without replacing stored data. A rare failed close
also sets a failure exit status. No query was disabled and no alert was dismissed; the corrected
source still requires its fresh processed analysis and native jobs before acceptance.
