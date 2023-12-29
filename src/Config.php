<?php

namespace Durlecode\EJSParser;

class Config
{
	/**
     * @var string Prefix for CSS classes
     */
    private $prefix = "prs";

    /**
     * @var string EditorJS version
     */
    private $version = "2.28.2";

    public function getPrefix(): string
	{
		return $this->prefix;
	}

	/**
     * @param string $prefix
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    /**
     * @param string $version
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @param string $version
     */
    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

}
