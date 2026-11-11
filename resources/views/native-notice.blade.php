{{--
 File: Native-only persistent help and trust-boundary notice for the actual full service desk.
 Symbols: guarded native notice and expandable local usage help.
 State: environment only; version is checked against native product metadata by tests.
--}}
@if (app()->environment('native-local'))
    <aside class="native-notice" aria-label="Local application information">
        <strong>Secure Service Desk 1.1.0 · Jason Stys</strong>
        <p>Reference roles are not a sign-in system. Use fictional data; saved work stays on this PC.</p>
        <details><summary>How to use this application</summary><p>This is a single OS-owner workspace. Create as Requester, then choose Agent to assign, comment and confirm a valid lifecycle change. Saved versions prevent stale overwrites. Search narrows your visible queue; ticket history is independently paginated. Prepared Northwind/Contoso records are initialized once inside this same app. New work persists after Exit and restart. Help in the native menu explains storage/recovery. No notification is sent to an outside email, webhook or cloud service.</p></details>
    </aside>
@endif
