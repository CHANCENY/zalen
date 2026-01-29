## 1) Install deps
composer require drupal/migrate_plus drupal/migrate_tools drupal/migrate_upgrade


drush en migrate_plus migrate_tools migrate_drupal migrate_upgrade -y


## 2) Add D7 source DB in settings.php
# Example (adjust credentials):
$databases['migrate']['default'] = [
'driver' => 'mysql',
'database' => 'd7_db',
'username' => 'd7_user',
'password' => 'd7_pass',
'host' => '127.0.0.1',
'port' => '3306',
'prefix' => '',
];


## 3) Filesystem
# Set public/private directories to point to a copy of the D7 file trees so URIs
# like public:// and private:// resolve during migration.
# Example:
# $settings['file_public_path'] = 'sites/default/files';
# Copy D7 files to web/sites/default/files before running.


## 4) Enable module & check status
drush en zalen_migrate -y
drush ms # list migrations


## 5) Run in order
drush mim d7_user__zalen
drush mim d7_file__zalen
drush mim d7_media_image__zalen
drush mim d7_taxonomy__occasions # repeat per vocabulary
drush mim d7_node_bedrijf__zalen
drush mim d7_node_zaal__zalen
drush mim d7_url_alias__zalen


# Use `drush mr <id>` to rollback if needed.