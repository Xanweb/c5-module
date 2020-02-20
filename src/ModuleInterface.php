<?php
namespace Xanweb\Module;

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
     * @return \Concrete\Core\Entity\Package
     */
    public static function pkg();

    /**
     * Basic Boot for Module.
     */
    public static function boot();
}
