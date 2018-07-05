<?php

namespace MakeWeb\WordpressTestEnvironment;

class Http
{
    public function handleRedirect(...$args)
    {
        if ($args[0] == 'http:/wp-admin/install.php') {
            throw new \Exception('Wordpress is not installed');
        }
        var_dump($args);
    }
}