<?php

namespace MakeWeb\WordpressTestEnvironment;

use Dotenv\Dotenv;

class WordpressTestEnvironment
{
    protected $envPath;

    public function boot()
    {
        $this->loadEnv();

        if ($this->isNotSetup()) {
            $this->setup();
        }

        // if (!is_wordpress_installed()) {
        //     $this->installWordpress();
        // }

        $this->includeFiles();
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
}
