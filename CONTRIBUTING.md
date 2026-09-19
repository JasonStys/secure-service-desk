# Contributing

1. Create a focused branch from `main`.
2. Keep controllers thin and enforce tenant/object authorization in the service layer.
3. Add tests for every lifecycle, authorization, reliability, or schema change.
4. Run `bash scripts/verify.sh`.
5. Regenerate `docs/code-index.md` with `php tools/validate_repo.php` after source changes.
6. Update relevant design and evidence documents.
7. Open a pull request describing behavior, risk, validation, and rollback.

Never commit `.env`, tokens, keys, production data, generated dependencies, or copied customer content. Synthetic fixtures must use reserved `.example` addresses.
