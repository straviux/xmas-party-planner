# 07 — Deployment (SCAFFOLD — needs full review)

Status: scaffold only. Rules carried over from the current app.
Arc deploys himself; this spec describes what his host must
support, it is not a deployment runbook for Muse.

## 1. Problem / user story

Arc can deploy the rebuilt app to his own website host with no
help from Muse, and a guest opening the deployed URL gets the
guest home, not a setup screen or an error.

## 2. Scope

- In scope: server requirements, build steps, environment,
  first-run setup, what is and is not in the repo.
- Out of scope: choosing Arc's host, migrating old party data
  (see constitution rule 20 — that would be its own spec).

## 3. Requirements (current app, to be confirmed for rebuild)

- Document root must be `public/`.
- PHP 8.3+ with mbstring, xml, curl, zip, gd (gd with AVIF
  support if images/AVIF are kept — see `04`).
- `composer install --no-dev --optimize-autoloader`.
- `.env` from `.env.example` with a real `APP_KEY`
  (`php artisan key:generate`), `APP_URL` = deployed origin,
  `APP_ENV=production`, `APP_DEBUG=false`,
  `SESSION_DRIVER=file`.
- `storage/` and `bootstrap/cache/` writable.
- `npm run build` for frontend assets.
- `php artisan migrate --seed` (or the rebuild's equivalent
  first-run step), then change the seeded host password
  immediately via the host dashboard.

## 4. Excluded from the repo (current app)

`vendor/`, `node_modules/`, `.env`, `public/build/`,
`database/database.sqlite`, storage session/compiled files,
agent files (`AGENTS.md`, `CLAUDE.md`).

## 5. Local run (current app)

`npm run startlocal` → Vite 5139 + `php artisan serve`
on port 8005; open http://localhost:8005 (not the Vite URL,
not 8000/5173). Phone testing: serve on 0.0.0.0:8005.

## 6. Acceptance criteria

[TODO — review pass: fresh checkout → build → first sign-in
→ party setup → guest entry on a phone, all from the spec
steps alone, with no extra instructions from Muse.]

## 7. Edge cases

[TODO — review pass: host without composer, host without
`public/` as docroot, storage not writable, missing APP_KEY.]

## 8. Open decisions

- `[DECISION]` Does Arc's host support the Laravel deployment
  above, or is it drop-files-only? This was never answered for
  the current app and it decides the rebuild's deployment spec.
- `[DECISION]` JSON file store vs database — see `plan.md`.
  The deployment spec must match whichever is chosen.
