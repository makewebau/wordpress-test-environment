<?php

namespace MakeWeb\WordpressTestEnvironment\Http;

use MakeWeb\WordpressTestEnvironment\Wordpress;
use MakeWeb\WordpressTestEnvironment\Exceptions\WPDieException;
use Illuminate\Support\Str;

class RequestHandler
{
    protected $wordpress;

    public function __construct(Wordpress $wordpress)
    {
        $this->wordpress = $wordpress;
    }

    public function post($uri = '/', $postData = [])
    {
        return $this->call('POST', $uri, $postData);
    }
    
    public function get($uri = '/', $queryParameters = [])
    {
        return $this->call('GET', $this->buildUri($uri, $queryParameters));
    }

    public function buildUri($uri, $queryParameters = [])
    {
        $queryParameters = array_merge($this->extractQueryParameters($uri), $queryParameters);

        return $this->stripQueryString($uri).(count($queryParameters) ? '?' : '').(http_build_query($queryParameters));
    }

    public function call($method, $uri = '/', $postData = [])
    {
        // Make sure the uri starts with a forward slash
        if (empty($uri) || $uri[0] != '/') {
            $uri = '/'.$uri;
        }

        $this->wordpress->installPlugins();

        // Set up the WordPress query.
        wp();

        if (!defined('WP_USE_THEMES')) {
            define('WP_USE_THEMES', true);
        }

        ob_start();

        $_SERVER['REQUEST_URI'] = $uri.(count($queryParameters) ? '?' : '').($queryString = http_build_query($queryParameters));
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['QUERY_STRING'] = $queryString;
        $_SERVER['HTTP_HOST'] = get_site_url();
        $_GET = $queryParameters;
		$_GET['noheader'] = true;

        // Load the theme template.
        try {

            global $wp_db_version, $pagenow, $menu, $submenu, $_wp_menu_nopriv, $_wp_submenu_nopriv, $plugin_page, $_registered_pages;

            require_once $this->determineScriptFileFromUri($uri);
        } catch (WPDieException $e) {
            // Prevent execution from being halted
        }

        return new Response(ob_get_clean(), 200);
    }

    public function determineScriptFileFromUri($uri)
    {
        if (Str::contains($uri, 'wp-admin/admin.php')) {
            return ABSPATH.'/wp-admin/admin.php';
            return ABSPATH.'/wp-admin/includes/admin.php';
        }
        return ABSPATH.WPINC.'/template-loader.php';
    }

    protected function setUpAdmin()
    {
        if ( ! defined( 'WP_ADMIN' ) ) {
            define( 'WP_ADMIN', true );
        }
        
        if ( ! defined('WP_NETWORK_ADMIN') )
            define('WP_NETWORK_ADMIN', false);
        
        if ( ! defined('WP_USER_ADMIN') )
            define('WP_USER_ADMIN', false);
        
        if ( ! WP_NETWORK_ADMIN && ! WP_USER_ADMIN ) {
            define('WP_BLOG_ADMIN', true);
        }
        
        if (isset($_GET['import']) && !defined('WP_LOAD_IMPORTERS')) {
            define('WP_LOAD_IMPORTERS', true);
        }
    
        nocache_headers();
    }
}