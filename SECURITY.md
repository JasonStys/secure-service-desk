# Security policy

## Supported version

Security updates are applied to the latest commit on `main`.

## Reporting a vulnerability

Please use GitHub's private vulnerability reporting for this repository. Do not open a public issue containing exploit steps, secrets, personal information, or tenant data. Include the affected commit, impact, reproduction conditions, and a minimal proof of concept. Expect an acknowledgement within five business days.

## Important boundary

`X-Demo-Actor` is a local portfolio adapter, not an authentication scheme. A deployment must replace `ResolveDemoActor` with verified identity middleware, set a high-entropy `APP_KEY` and `WEBHOOK_SECRET`, force HTTPS, use managed secrets, and review the controls in [docs/security.md](docs/security.md).
