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

## Deploying from GitHub

Pressable deploys the plugin and theme from github.com/mikachan/dropandpair on every push to `main`. It's set up in MyPressable > the site > Advanced > GitHub Integration:

- Repository: `mikachan/dropandpair`, branch `main`
- Repository Subdirectory: `wordpress`
- Destination Path: `wp-content`

The deploy copies files as they are, with no build step, which is fine because neither the plugin nor the theme has one. Production doesn't need Composer either: `vendor/` isn't in the repo, and the plugin falls back to its own small autoloader (`DropAndPair\Foo` is `src/Foo.php`) when `vendor/autoload.php` isn't there.

It also deletes anything at the destination that isn't in the repo. `.deployignore` at the repo root stops that: it keeps other plugins, themes and the rest of `wp-content` on the server, and keeps this README, `tests/` and the Composer and PHPUnit files off it. Add a line there before installing anything on the server that lives outside the two `drop-and-pair` folders.

Each push shows up in the integration's Git History. After a deploy that changes URLs, flush the rewrite rules once (Settings > Permalinks > Save, or `wp rewrite flush`). The old `/moment/`, `/vibe/` and `/ride-type/` archives redirect to the filtered ride list either way.

## Pressable

drop-pair.mystagingwebsite.com runs on Pressable. SSH in with the site's own SSH username (MyPressable > the site > Collaborators) at `ssh.pressable.com`, using a key attached to that site. WP-CLI works from `/srv/htdocs`.

Never run the smoke scripts in `tests/smoke/` there: they create and delete posts.
