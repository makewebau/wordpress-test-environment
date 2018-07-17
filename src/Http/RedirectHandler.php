<?php

namespace MakeWeb\WordpressTestEnvironment\Http;

use MakeWeb\WordpressTestEnvironment\Exceptions\WPRedirectException;

class RedirectHandler
{
    public function handle(...$args)
    {
        if ($args[0] == 'http:/wp-admin/install.php') {
            throw new \Exception('Wordpress is not installed');
        }

        throw new WPRedirectException($args[0], $args[1]);
    }
}
