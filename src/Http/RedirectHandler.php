<?php

namespace MakeWeb\WordpressTestEnvironment\Http;

class RedirectHandler
{
    public function handleRedirect(...$args)
    {
        if ($args[0] == 'http:/wp-admin/install.php') {
            throw new \Exception('Wordpress is not installed');
        }
    }
}
