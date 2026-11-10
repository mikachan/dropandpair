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
