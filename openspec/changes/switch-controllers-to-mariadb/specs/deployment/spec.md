# deployment — delta

## ADDED Requirements

## Requirement: The app requires MariaDB/MySQL at runtime
After this change, the app SHALL NOT work with a sqlite
`.env` pointing at the old file, and SHALL require
`DB_CONNECTION=mysql` with a MariaDB/MySQL database that
has been migrated. The JSON party file SHALL be ignored
after slice 6, even if it still exists on disk.

#### Scenario: Running with the JSON file deleted
- Given the switched app and a deleted `party.json`, when
  a guest sets up, enters, tears, and votes, then every
  step works from the database alone.
