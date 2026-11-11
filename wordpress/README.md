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

## Deploy artefact

`bin/package.sh` builds what goes on the server: `dist/drop-and-pair-plugin.zip` and `dist/drop-and-pair-theme.zip`, each holding a `drop-and-pair/` folder. Upload them through Plugins > Add New > Upload and Themes > Add New > Upload, or unzip them into `wp-content/plugins` and `wp-content/themes`.

```sh
wordpress/bin/package.sh            # or: wordpress/bin/package.sh <output dir>
```

Production needs no Composer. `vendor/` is left out, and the plugin falls back to its own small autoloader (`DropAndPair\Foo` is `src/Foo.php`) when `vendor/autoload.php` isn't there. Also left out: `tests/`, `composer.json`, `composer.lock`, `phpunit.xml.dist` and every dotfile (`.phpunit.result.cache` included). The script fails if any of those got in, if a file WordPress needs is missing, or if any PHP file doesn't lint. `dist/` is gitignored.

After installing or updating, flush the rewrite rules once (Settings > Permalinks > Save, or `wp rewrite flush`), so URL changes such as the dropped `/moment/`, `/vibe/` and `/ride-type/` archives take effect. Those old URLs redirect to the filtered ride list either way.

## Pressable staging

drop-pair.mystagingwebsite.com runs on Pressable. SSH in with the site's own SSH username (MyPressable > the site > Collaborators) at `ssh.pressable.com`, using a key attached to that site. WP-CLI works from `/srv/htdocs`.

`scp` and SFTP drop files into the web root (`/srv/htdocs`), not the SSH home folder, which is recreated each session. Move uploads to `/tmp` before installing and delete them afterwards, so the zips and `content.json` are never served:

```sh
scp wordpress/dist/*.zip wordpress/import/content.json <user>@ssh.pressable.com:dp-deploy/
ssh <user>@ssh.pressable.com
mkdir -p /tmp/dp-deploy && mv /srv/htdocs/dp-deploy/* /tmp/dp-deploy/ && rmdir /srv/htdocs/dp-deploy
cd /srv/htdocs
wp plugin install /tmp/dp-deploy/drop-and-pair-plugin.zip --activate --force
wp theme install /tmp/dp-deploy/drop-and-pair-theme.zip --activate --force
wp rewrite flush && wp cache flush
rm -rf /tmp/dp-deploy
```

Then run `bin/coming-soon.sh https://drop-pair.mystagingwebsite.com` from your machine. Never run the smoke scripts in `tests/smoke/` on staging: they create and delete posts.

The import (`wp dropandpair import <file>`) only creates what isn't there, so re-running it never overwrites edits made on staging.
