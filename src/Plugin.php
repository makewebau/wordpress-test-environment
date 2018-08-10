<?php

namespace MakeWeb\WordpressTestEnvironment;

class Plugin
{
    public $path;

    public $directory;

    public $directoryName;

    protected $wordpress;

    public function __construct(Wordpress $wordpress)
    {
        $this->wordpress = $wordpress;
    }

    public function withPath($path)
    {
        $this->path = $path;
        $this->directory = $this->directory();
        $this->directoryName = $this->directoryName();
        $this->identifier = $this->identifier();

        return $this;
    }

    public function directory()
    {
        return dirname($this->path);
    }

    public function directoryName()
    {
        return basename($this->directory);
    }

    public function symlink()
    {
        $targetPath = $this->wordpress->basePath('wp-content/plugins/'.$this->directoryName);

        if (is_link($targetPath)) {
            unlink($targetPath);
        }

        symlink($this->directory, $targetPath);
    }

    public function activate()
    {
        if ($this->isActive()) {
            return;
        }
        $this->wordpress->setActivePlugins(
            $this->wordpress
                ->activePlugins()
                ->push($this->identifier)
                ->sort()
                ->toArray()
        );
    }

    public function isActive()
    {
        return $this->wordpress->activePlugins()->contains($this->identifier);
    }

    public function identifier()
    {
        return $this->directoryName.'/'.basename($this->path);
    }
}
