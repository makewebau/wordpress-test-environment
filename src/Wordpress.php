<?php

namespace MakeWeb\WordpressTestEnvironment;

use Dotenv\Dotenv;
use Illuminate\Support\Collection;
use MakeWeb\WordpressTestEnvironment\Database\Database;
use MakeWeb\WordpressTestEnvironment\Exceptions\WPDieException;
use MakeWeb\WordpressTestEnvironment\Http\RedirectHandler;
use MakeWeb\WordpressTestEnvironment\Http\RequestHandler;
use MakeWeb\WordpressTestEnvironment\Http\Response;
use WP_User;

class Wordpress
{
    protected $envPath;

    protected $basePath;

    protected $http;

    protected $plugins = [];

    protected $useTransactions = false;

    public function __construct()
    {
        if (!defined('WORDPRESS_TEST_ENVIRONMENT')) {
            define('WORDPRESS_TEST_ENVIRONMENT', true);
        }

        $this->redirectHandler = new RedirectHandler;
        $this->requestHandler = new RequestHandler($this);
        $this->database = new Database($this);
    }

    public function initialise()
    {
        // This sets the default basepath if none has been set
        $this->withBasePath($this->basePath);

        $this->defineHttpGlobalFunctions();

        $this->loadEnv();

        $this->pdo = $this->database->connect();

        if ($this->isNotSetup()) {
            $this->setup();
        }

        if ($this->isNotInstalled()) {
            $this->install();
        }

        return $this;
    }

    public function boot()
    {
        $this->initialise();

        $this->include('wp-load.php');

        if ($this->useTransactions) {
            $this->startTransactions();
        }

        // Allow us to terminate execution without halting phpunit
        add_filter('wp_die_handler', function (...$args) {
            return function(...$args) {
                $this->wpDieHandler(...$args);
            };
        });

        // Prevent wordpress from calling exit(), thus halting phpunit
        add_action('admin_footer', function() {
            wp_die();
        });

        // Prevent headers from being started
        add_action('admin_init', function() {
            remove_action('admin_init', 'wp_admin_headers');
        }, PHP_INT_MIN);

        return $this;
    }

    public function useTransactions()
    {
        $this->useTransactions = true;

        return $this;
    }

    public function isNotSetup()
    {
        // TODO Remove this
        return true;

        return !$this->isSetup();
    }

    public function isSetup()
    {
        return file_exists($this->basePath('wp-config.php')) && $this->getEnvHash() === file_get_contents($this->envPath('.env-hash'));
    }

    public function basePath($relativePath = null)
    {
        return $this->appendRelativePath($this->basePath, $relativePath);
    }

    public function loadEnvFrom($path)
    {
        $this->envPath = realpath($path);

        return $this;
    }

    public function withBasePath($path = __DIR__.'/../../../wordpress/wordpress')
    {
        $this->basePath = realpath($path);

        return $this;
    }

    public function withPlugin($pluginFilePath)
    {
        $this->plugins[] = realpath($pluginFilePath);

        return $this;
    }

    /**
     * Returns the url of the wordpress installation
     */
    public function url()
    {
        return get_site_url();
    }

    protected function setup()
    {
        $wpConfig = file_get_contents($this->basePath('wp-config-sample.php'));

        foreach ([
            'database_name_here' => $this->env('DATABASE_NAME'),
            'username_here' => $this->env('DATABASE_USER'),
            'password_here' => $this->env('DATABASE_PASSWORD'),
            'localhost' => $this->env('DATABASE_HOST'),
        ] as $original => $substitution) {
            $wpConfig = str_replace($original, $substitution, $wpConfig);
        }

        $wpConfig .= implode([
            "\n\n",
            "define('WP_SITEURL', '".(empty($this->env('WP_SITEURL')) ? 'http://localhost/' : $this->env('WP_SITEURL'))."');",
        ], '');

        file_put_contents($this->basePath('wp-config.php'), $wpConfig);

        // We also save a hash of the environment variables which we can use later to check if the wp-config
        // file needs to be rebuilt
        file_put_contents($this->envPath('.env-hash'), $this->getEnvHash());
    }

    protected function install()
    {
        /**
         * We are installing WordPress.
         *
         * @since 1.5.1
         * @var bool
         */
        define('WP_INSTALLING', true);

        /** Load WordPress Bootstrap */
        require_once $this->basePath('wp-load.php');

        /** Load WordPress Administration Upgrade API */
        require_once ABSPATH.'wp-admin/includes/upgrade.php';

        /** Load WordPress Translation Install API */
        require_once ABSPATH.'wp-admin/includes/translation-install.php';

        /** Load wpdb */
        require_once ABSPATH.WPINC.'/wp-db.php';

        global $wpdb;

        wp_install('Wordpress Test Environment', 'admin', 'admin@domain.com', true, '', wp_slash('password'), $loaded_language);

        $wpdb->get_results('SHOW TABLES');
    }

    protected function include($filename)
    {
        return require_once $this->basePath($filename);
    }

    protected function env($key)
    {
        return getenv($key);
    }

    protected function loadEnv()
    {
        if (is_null($this->envPath())) {
            return;
        }

        $dotenv = new Dotenv($this->envPath());
        $dotenv->load();
    }

    protected function envPath($relativePath = null)
    {
        return $this->appendRelativePath($this->envPath, $relativePath);
    }

    protected function appendRelativePath($originalPath, $relativePath = null)
    {
        // $basePath = realpath($originalPath);
        $basePath = $originalPath;

        if (!is_null($relativePath)) {
            return $basePath.'/'.$relativePath;
        }

        return $basePath;
    }

    protected function getEnvHash()
    {
        return sha1(file_get_contents($this->envPath('.env')));
    }

    protected function defineHttpGlobalFunctions()
    {
        $this->setGlobalFunctionCallback('wp_redirect', function (...$args) {
            $this->redirectHandler->handle(...$args);
        });
    }

    protected function startTransactions()
    {
        // We roll back first to make sure any open transactions are rolled back between tests
        // in the case that transactions are opened twice before rollback
        $this->rollbackTransactions();

        global $wpdb;

        $wpdb->query('SET autocommit = 0;');
        $wpdb->query('START TRANSACTION;');
    }

    public function rollbackTransactions()
    {
        global $wpdb;

        $wpdb->query('ROLLBACK');
    }

    protected function setGlobalFunctionCallback($functionName, $callback)
    {
        global $global_function_callbacks;

        if (isset($global_function_callbacks[$functionName])) {
            return;
        }

        $global_function_callbacks[$functionName] = $callback;

        eval("function $functionName(...\$args) {
            global \$global_function_callbacks;
            return \$global_function_callbacks['$functionName'](...\$args);
        }");
    }

    protected function isNotInstalled()
    {
        return !$this->isInstalled();
    }

    protected function isInstalled()
    {
        return file_exists($this->basePath('wp-config.php')) && $this->database->wordpressTablesExist();
    }

    public function get($uri = '/', $queryParameters = [])
    {
        return $this->requestHandler->get($uri, $queryParameters);
    }

    public function post($uri = '/', $postParameters = [])
    {
        return $this->requestHandler->post($uri, $postParameters);
    }

    public function installPlugins()
    {
        foreach ($this->plugins as $pluginFilePath) {
            $plugin = (new Plugin($this))->withPath($pluginFilePath);
            $plugin->symlink();
            $plugin->activate();
        }
    }

    public function activePlugins()
    {
        return (new Collection($this->getOption('active_plugins')));
    }

    public function setActivePlugins($activePlugins)
    {
        return $this->updateOption('active_plugins', $activePlugins);
    }

    public function updateOption($key, $value)
    {
        return $this->database
            ->update('options')
            ->set('option_value', serialize($value))
            ->where('option_name', '=', $key)
            ->execute();
    }

    public function getOption($key)
    {
        return unserialize($this->database
            ->select('options')
            ->where('option_name', '=', $key)
            ->first()['option_value']);
    }

    public function wpDieHandler($message = '', $title = '', $arguments = [])
    {
        throw new WPDieException($title.': '.$message, isset($arguments['response']) ? $arguments['response'] : 200);
    }

    public function actingAsAdmin()
    {
        return $this->actingAsUser($this->getAdminUser());
    }

    public function actingAsUser(WP_User $user)
    {
        $this->loginUser($user);

        return $this;
    }

    public function loginUser(WP_User $user)
    {
        // Prevent wordpress from sending the cookies and thus trying to start output
        add_filter('send_auth_cookies', function () {
            return false;
        });

        // Since we can't send the cookies in the headers of the request (PHPUnit has already sent headers),
        // We have to simulate it by directly modifying $_COOKIE superglobal
        add_action('set_logged_in_cookie', function ($cookie) {
            $_COOKIE[LOGGED_IN_COOKIE] = $cookie;
        });
        add_action('set_auth_cookie', function ($cookie) {
            $_COOKIE[AUTH_COOKIE] = $cookie;
        });

        wp_set_current_user($user);
        wp_set_auth_cookie($user->ID);
    }

    public function getAdminUser()
    {
        $adminUsers = get_users(['role' => 'administrator']);

        if (empty($adminUsers)) {
            return null;
        }

        return $adminUsers[0];
    }
}
