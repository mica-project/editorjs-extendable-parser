<?php

namespace MicaProject\EJSParser;

class Config
{
    public function __construct(

        /**
         * @var string Prefix for CSS classes
         */
        protected string $prefix = 'prs',

        /**
         * @var string EditorJS version
         */
        protected string $version = '2.28.2',
    )
    {
    }


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
     * @return string
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
