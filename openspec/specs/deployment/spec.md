# deployment

## Requirement: Document root is public
The deployed document root SHALL be `public/`.

#### Scenario: Deployed URL
- Given the app deployed with `public/` as docroot, when a
  guest opens the deployed URL, then they reach guest home,
  not a setup screen or an error.

## Requirement: MariaDB/MySQL database (DECIDED)
The rebuild SHALL use a MariaDB or MySQL database via
Laravel's `mysql` driver, with MariaDB-compatible migrations
(standard column types only, utf8mb4, utf8mb4_unicode_ci).
The host SHALL provide the database, and `.env` SHALL carry
`DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT` (3306 default),
`DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`. `.env`
SHALL never be committed.

#### Scenario: Fresh deploy
- Given a fresh checkout, when Arc runs composer install,
  sets `.env` with a real `APP_KEY`, runs migrations, and
  builds frontend assets, then host sign-in and party setup
  work on the deployed URL.

## Requirement: Server requirements
The server SHALL provide PHP 8.3+ with mbstring, xml, curl,
zip, gd, and `pdo_mysql`; writable `storage/` and
`bootstrap/cache/`; `APP_ENV=production`,
`APP_DEBUG=false`, `SESSION_DRIVER=file`, and
`APP_URL` equal to the deployed origin.

#### Scenario: Missing APP_KEY
- Given a deploy without a real `APP_KEY`, when the app
  starts, then it fails loudly at setup, not silently with
  broken sessions. (Exact failure behaviour to be confirmed
  in the deployment change proposal.)

## Requirement: Local development needs a database instance
Local development SHALL run against a MariaDB/MySQL instance
(e.g. a local MariaDB install, XAMPP, or a container — the
rebuild's local setup change SHALL name one concrete option
for Arc's Windows machine), then Vite 5139 + `php artisan
serve` on port 8005, opened at http://localhost:8005.

#### Scenario: Local run
- Given a local MariaDB instance running and `.env` pointing
  at it, when Arc runs the local start command, then guest
  home opens at http://localhost:8005.

## Requirement: Host support is an open decision
[DECISION] Does Arc's host support the Laravel deployment
above, including a MariaDB/MySQL database, or is it
drop-files-only? This was never answered for the current app
and SHALL be answered before the deployment slice is built.
Old party data SHALL NOT migrate automatically.
