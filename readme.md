# Wordpress Test Environment

Easily pull Wordpress in to your plugin, library or package as a composer development dependency and test your code in an actual wordpress environment.

## Installation

From command line:

    composer require --dev makeweb/wordpress-test-environment

## Basic Usage

1) Once installed, Create a new database in your local MySQL installation.

2) Create a `.env` file in the base directory of the codebase you are testing and add the following to it (updating the credentials to match your newly created database and local environment):

```
DATABASE_NAME=database_name_here
DATABASE_USER=database_user_here
DATABASE_PASSWORD=database_password_here
DATABASE_HOST=127.0.0.1
```

3) Add the following lines to the `setUp()` method of your test case class or to your tests bootstrap file to boot the wordpress environment, being sure to pass in the path to the directory of your .env file to the `withEnvPath()` method.
```
(new MakeWeb\WordpressTestEnvironment\WordpressTestEnvironment)
    ->withEnvPath(__DIR__.'/..')
    ->boot();
```

