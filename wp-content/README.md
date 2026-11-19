# Drop and Pair on WordPress

A plugin (`plugins/drop-and-pair`) and a block theme (`themes/drop-and-pair`), developed against a local WordPress Studio site.

## Local site

- Path: `~/Studio/drop-and-pair` (name "Drop and Pair"), URL http://localhost:8886
- Studio follows symlinks, so the site's `wp-content/plugins/drop-and-pair` and `wp-content/themes/drop-and-pair` link straight to the folders in this repo:

```sh
ln -s "$PWD/wp-content/plugins/drop-and-pair" ~/Studio/drop-and-pair/wp-content/plugins/drop-and-pair
ln -s "$PWD/wp-content/themes/drop-and-pair" ~/Studio/drop-and-pair/wp-content/themes/drop-and-pair
```

- Run WP-CLI from the site directory: `cd ~/Studio/drop-and-pair && studio wp plugin activate drop-and-pair`

## Tests

PHPUnit 11, no WordPress loaded. The tests, Composer and PHPUnit files live at the repo root rather than in the plugin folder, so the deploy never picks them up. Run them from the root:

```sh
composer install
vendor/bin/phpunit
node tests/js/ride-sidebar-save.cjs
```

The smoke scripts in `tests/smoke/` are WP-CLI checks for the local site, for example `cd ~/Studio/drop-and-pair && studio wp eval-file ~/Source/Personal/dropandpair/tests/smoke/ride-page.php`.

## Deploying from GitHub

Pressable deploys the plugin and theme from github.com/mikachan/dropandpair on every push to `main`. It's set up in MyPressable > the site > Advanced > GitHub Integration, with branch `main` and these branch settings:

- Sync plugins and themes. Leave mu-plugins unticked.
- Leave deleting files turned off, so the other plugins and themes on the site stay put.

This is Pressable's original GitHub integration, which syncs the `plugins`, `mu-plugins` and `themes` folders inside the `wp-content/` folder at the repo root. Nothing else in `wp-content/` (this README, `bin/`, `dist/`, `import/`) is deployed.

The deploy copies files as they are, with no build step, which is fine because neither the plugin nor the theme has one. Production doesn't need Composer either: the plugin uses its own small autoloader (`DropAndPair\Foo` is `src/Foo.php`) when there's no `vendor/autoload.php` in its folder.

Removing a file from the repo doesn't remove it from the server, since deleting is off. Delete it over SSH or SFTP as well.

Each push shows up in the integration's Git History. After a deploy that changes URLs, flush the rewrite rules once (Settings > Permalinks > Save, or `wp rewrite flush`). The old `/moment/`, `/vibe/` and `/ride-type/` archives redirect to the filtered ride list either way.

## Pressable

drop-pair.mystagingwebsite.com runs on Pressable. SSH in with the site's own SSH username (MyPressable > the site > Collaborators) at `ssh.pressable.com`, using a key attached to that site. WP-CLI works from `/srv/htdocs`.

Never run the smoke scripts in `tests/smoke/` there: they create and delete posts.
