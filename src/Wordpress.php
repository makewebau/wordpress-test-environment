<?php

namespace MakeWeb\WordpressTestEnvironment;

use Dotenv\Dotenv;
use MakeWeb\WordpressTestEnvironment\Http\Response;
use MakeWeb\WordpressTestEnvironment\Http\RedirectHandler;
use MakeWeb\WordpressTestEnvironment\Database\Database;
use MakeWeb\WordpressTestEnvironment\Exceptions\WPDieException;
use Illuminate\Support\Collection;

class Wordpress
{
    protected $envPath;

    protected $basePath;

    protected $http;

    protected $plugins = [];


    protected $useTransactions = false;

    public function __construct()
    {
        define('WORDPRESS_TEST_ENVIRONMENT', true);

        $this->redirectHandler = new RedirectHandler;
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

        add_filter('wp_die_handler', function () {
            $this->wpDieHandler();
        });

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
        return file_exists($this->basePath('wp-config.php'));
    }

    public function get($uri = '/')
    {
        $this->installPlugins();

        // Set up the WordPress query.
        wp();

        define('WP_USE_THEMES', true);

        ob_start();

        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Load the theme template.
        try {
            require_once(ABSPATH.WPINC.'/template-loader.php');
        } catch (WPDieException $e) {

        }

        return new Response(ob_get_clean(), 200);
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

    public function wpDieHandler($message = null)
    {
		throw new WPDieException($message);
	}
}
