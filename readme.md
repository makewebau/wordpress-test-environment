# Wordpress Test Environment

Easily pull Wordpress in to your plugin, library or package as a composer development dependency and test your code in an actual wordpress environment.

This package aims to support developers who wish to test code which depends on Wordpress in two key ways:

1) Boot wordpress to allow usage of wordpress database and global functions

2) Simulate actual HTTP requests to the wordpress application

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
SITE_URL=example.test
```

3) Initialise Wordpress in your parent test case class

Pass the relative path to the directory which contains your .env file into `loadEnvFrom()`

```
use MakeWeb\WordpressTestEnvironment\Wordpress;
use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase;
{
    public function setUp()
    {
        $this->wordpress = (new Wordpress)
            ->loadEnvFrom(__DIR__.'/..');
    } 
}
```

4) Usage in your tests

### Boot only

If you simply wish to boot wordpress to allow access to the database and Wordpress global functions. Add the following lines to the start of
your test.

#### In your test file
```
class MyPluginTest extends TestCase
{
    public function the_main_plugin_class_can_use_wordpress_global_functions()
    {
        $this->wordpress->boot();

        $this->assertEquals('http://example.test', (new MyPluginClass)->url());
    }
}
```

#### In your main plugin file or source code

Here we are just calling a native Wordpress function `get_site_url()`. If everything is set up correctly the test above will
pass with the code below because `get_site_url()` has been defined by wordpress.

> Note: Make sure you are including the plugin file in your test bootstrap file manually or with an autoloader like composer

```
class MyPluginClass
{
    public function url()
    {
        return get_site_url();
    }
}
```

#### Test HTTP requests to your wordpress application

Sometimes your theme, plugin or library might actually handle HTTP requests (for example, if you are creating an API), or
perhaps you just want to test the way your code affects the actual html output of the application. The `Wordpress` class
exposes a get method which simulates an actual HTTP GET request to the given url. Wordpress, and your dependent code
will be booted and executed as it would be in production. We don't need to call `$this->wordpress->boot()` when using the HTTP
methods because it is done for us automatically.

#### In your test file
```
class MyPluginTest extends TestCase
{
    public function the_main_plugin_class_can_use_wordpress_global_functions()
    {
        // To boot the plugin which we are testing when booting wordpress, pass the relative path
        // to the main plugin file into the withPlugin() method. Installation and activation hooks
        // will not be called.
        $this->wordpress->withPlugin(__DIR__.'/../edd-sl-deployer.php');

        // Request the front page of the wordpress site and capture the result
        $response = $this->wordpress->get('/');

        // Assert that the response returned a 200 http status code
        $response->assertSuccessful();

        // Assert that our text was output in the html
        $response->assertSee('Hello World');
    }
}
```

#### In your plugin file or source code
```
/**
 * Plugin Name: Hello World Outputter
 * Plugin Description: An enterprise ready solution to output the text 'Hello World' on your website
 * Plugin URI: https://hello-world-outputter.com
 * Version: 1.0.0
 * Author: Andrew Feeney
**/

class MyPluginClass
{
    public function boot()
    {
        echo('Hello World');
    }
}

(new MyPluginClass)->boot();
```



