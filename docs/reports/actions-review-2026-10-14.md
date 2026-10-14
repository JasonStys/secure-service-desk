# Actions maintenance review — 2026-10-14

Reviewed by Jason Stys. Run 38318776413 failed two legitimate supply-chain gates:
Composer found CommonMark raw-HTML and table-parser advisories, while npm found
the source-map-js indexed-source-map denial-of-service advisory. These are
dependency problems, not runner outages. CommonMark is upgraded to 2.10.3, its
required polyfill is resolved by Composer, and source-map-js is now 1.2.2.
No audit ignore list or weaker severity threshold was added.

Local validation: strict Composer metadata and locked audit passed; npm audit
reported zero known vulnerabilities; all four frontend tests and the Vite
production build passed. PostgreSQL schema/seed, backend coverage and container
builds are validated on disposable Actions runners, not by changing local Docker.
Final native run links will be appended after verification. Historical logs and
artifact downloads stay in the private PC archive.

Advisories: [CommonMark raw HTML](https://github.com/advisories/GHSA-97jj-33gv-5xf9),
[CommonMark tables](https://github.com/advisories/GHSA-3q6v-r5mr-hxv8),
[source-map-js](https://github.com/advisories/GHSA-68fv-2mgg-jv7q).
