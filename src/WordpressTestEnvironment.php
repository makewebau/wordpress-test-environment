<?php

namespace MakeWeb\WordpressTestEnvironment;

use Dotenv\Dotenv;

class WordpressTestEnvironment
{
    protected $envPath;

    protected $http;

    public function __construct()
    {
        $this->http = new Http;
    }

    public function boot()
    {
        $wordpress = new Wordpress;

        $this->defineHttpGlobalFunctions();

        $this->loadEnv();

        // if ($this->isNotSetup()) {
            $this->setup();
        // }

        // if ($this->wordpressIsNotInstalled()) {
            $this->installWordpress();
        // }
        $this->includeFiles();

        return $this;
    }

    public function isNotSetup()
    {
        return !file_exists($this->basePath('wp-config.php')) || $this->getEnvHash() !== file_get_contents($this->envPath('.env-hash'));
    }

    public function basePath($relativePath = null)
    {
        return $this->appendRelativePath(__DIR__.'/../../../wordpress/wordpress', $relativePath);
    }

    public function withEnvPath($path)
    {
        $this->envPath = realpath($path);

        return $this;
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

    protected function installWordpress()
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
        $wpdb->query( 'SET autocommit = 0;' );
        $wpdb->query( 'START TRANSACTION;' );
        add_filter( 'query', array( $this, 'createTemporaryTables' ) );
        add_filter( 'query', array( $this, 'dropTemporaryTables' ) );

        wp_install('Wordpress Test Environment', 'admin', 'admin@domain.com', true, '', wp_slash('password'), $loaded_language);
    }

    protected function includeFiles()
    {
        require_once $this->basePath('wp-load.php');
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
        $basePath = realpath($originalPath);

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
        $this->setGlobalFunctionCallback('wp_redirect', function(...$args) {
            $this->http->handleRedirect(...$args);
        });
    }

    protected function setGlobalFunctionCallback($functionName, $callback)
    {
        global $global_function_callbacks;

        $global_function_callbacks[$functionName] = $callback;

        eval("function $functionName(...\$args) {
            global \$global_function_callbacks;
            return \$global_function_callbacks['$functionName'](...\$args);
        }");
    }

    protected function wordpressIsNotInstalled()
    {
        return false;
    }

	public function createTemporaryTables( $query ) {
		if ( 'CREATE TABLE' === substr( trim( $query ), 0, 12 ) )
			return substr_replace( trim( $query ), 'CREATE TEMPORARY TABLE', 0, 12 );
		return $query;
	}
    
    public function dropTemporaryTables( $query ) {
		if ( 'DROP TABLE' === substr( trim( $query ), 0, 10 ) )
			return substr_replace( trim( $query ), 'DROP TEMPORARY TABLE', 0, 10 );
		return $query;
	}
}
