# Drop and Pair on WordPress

A plugin (`plugins/drop-and-pair`) and a block theme (`themes/drop-and-pair`), developed against a local WordPress Studio site.

## Local site

- Path: `~/Studio/drop-and-pair` (name "Drop and Pair"), URL http://localhost:8886
- Studio follows symlinks, so the site's `wp-content/plugins/drop-and-pair` and `wp-content/themes/drop-and-pair` link straight to the folders in this repo:

```sh
ln -s "$PWD/wordpress/plugins/drop-and-pair" ~/Studio/drop-and-pair/wp-content/plugins/drop-and-pair
ln -s "$PWD/wordpress/themes/drop-and-pair" ~/Studio/drop-and-pair/wp-content/themes/drop-and-pair
```

- Run WP-CLI from the site directory: `cd ~/Studio/drop-and-pair && studio wp plugin activate drop-and-pair`

## Tests

PHPUnit 11, no WordPress loaded.

```sh
cd wordpress/plugins/drop-and-pair
composer install
vendor/bin/phpunit
```

## Parity check

`bin/parity.mjs` reads every park, ride, pairing and the About page back through the REST API (`context=edit`, so drafts are included) and compares them with `import/content.json`: titles, park links, stats, intensity, roughness, ride type, vibes, further reading, each pairing link's target, blurb and strength, and body text with tags and entities stripped. It prints each mismatch and exits 1 if there are any. It only reads.

Coming-soon mode blocks anonymous REST, so it needs an application password, passed through the environment (never commit one):

```sh
node scripts/export-content.js
cd ~/Studio/drop-and-pair
studio wp user application-password create admin parity --porcelain   # prints the password
cd -
DP_USER=admin DP_APP_PASSWORD='the password' node wordpress/bin/parity.mjs http://localhost:8886
studio wp user application-password delete admin <uuid>              # uuid from `... list admin`
```

Expected: `0 mismatches (65 items checked against http://localhost:8886)`.
