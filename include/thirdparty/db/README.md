
https://github.com/catfan/Medoo/tags    - MIT - License
----------------------------------------------------------------

The lightweight PHP database framework to accelerate development

## Features

* **Lightweight** - Portable with only one file.

* **Easy** - Easy to learn and use, friendly construction.

* **Powerful** - Supports various common and complex SQL queries, data mapping and prevents SQL injection.

* **Compatible** - Supports MySQL, MSSQL, SQLite, MariaDB, PostgreSQL, Sybase, Oracle, and more.

* **Friendly** - Works well with every PHP framework, like Laravel, Codeigniter, Yii, Slim, and framework that are supporting singleton extension or composer.

* **Free** - Under the MIT license, you can use it anywhere, whatever you want.

## Requirement

PHP 7.3+ and installed PDO extension.

## Get Started

### Install via composer

Add Medoo to composer.json configuration file.
```
$ composer require catfan/medoo
```

And update the composer
```
$ composer update
```

```php
// Require Composer's autoloader.
require 'vendor/autoload.php';

// Using Medoo namespace.
use Medoo\Medoo;

// Connect the database.
$database = new Medoo([
    'type' => 'mysql',
    'host' => 'localhost',
    'database' => 'name',
    'username' => 'your_username',
    'password' => 'your_password'
]);

// Enjoy
$database->insert('account', [
    'user_name' => 'foo',
    'email' => 'foo@bar.com'
]);

$data = $database->select('account', [
    'user_name',
    'email'
], [
    'user_id' => 50
]);

echo json_encode($data);

// [{
//    "user_name" : "foo",
//    "email" : "foo@bar.com",
// }]
```

## Contribution Guides

For starting a new pull request, please make sure it's compatible with other databases and write a unit test as possible.

Run `phpunit tests` for unit testing and `php-cs-fixer fix` for fixing code style.

Each commit is started with `[fix]`, `[feature]` or `[update]` tag to indicate the change.

Please keep it simple and keep it clear.

## License

Medoo is under the MIT license.

## Links

* Official website: [https://medoo.in](https://medoo.in)

* Documentation: [https://medoo.in/doc](https://medoo.in/doc)

* -----------------------------------------------------------------

For SQLite
*File Database

$database = new Medoo([
	'type' => 'sqlite',
	'database' => 'my/database/path/database.db'
]);

*Memory database

$database = new Medoo([
	'type' => 'sqlite',
	'database' => ':memory:'
]);

*Temporary database

Temporary database will be deleted when the connection is closed.

$database = new Medoo([
	'type' => 'sqlite',
	'database' => ''
]);

// Or simply with no database option.
$database = new Medoo([
	'type' => 'sqlite'
]);