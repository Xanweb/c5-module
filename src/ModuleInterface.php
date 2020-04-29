<?php
namespace Xanweb\Module;

use Concrete\Core\Entity\Package;

interface ModuleInterface
{
    /**
     * Get current package handle.
     *
     * @return string
     */
    public static function pkgHandle();

    /**
     * Get current package object.
     *
     * @return Package
     */
    public static function pkg(): Package;

    /**
     * Basic Boot for Module.
     */
    public static function boot();
}
