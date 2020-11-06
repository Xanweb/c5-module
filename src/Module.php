<?php

namespace Xanweb\Module;

use Concrete\Core\Entity\Package;
use Concrete\Core\Foundation\ClassAliasList;
use Concrete\Core\Foundation\Service\ProviderList;
use Concrete\Core\Support\Facade\Application;
use Concrete\Core\Support\Facade\Route;

/**
 * @method static \Concrete\Core\Config\Repository\Liaison getConfig()
 * @method static \Concrete\Core\Config\Repository\Liaison getFileConfig()
 * @method static string getPackagePath()
 * @method static string getRelativePath()
 *
 * @see \Concrete\Core\Package\Package
 */
abstract class Module implements ModuleInterface
{
    /**
     * The resolved object instances.
     *
     * @var array
     */
    protected static $resolvedPackInstance;

    /**
     * Class to be used Statically.
     */
    private function __construct()
    {
    }

    /**
     * Handle dynamic, static calls to the controller.
     *
     * @param  string  $method
     * @param  array  $args
     *
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        $pkg = static::pkg();

        return $pkg->getController()->$method(...$args);
    }

    /**
     * {@inheritdoc}
     *
     * @see Module::pkg()
     */
    public static function pkg(): Package
    {
        $pkgHandle = static::pkgHandle();
        if (!isset(static::$resolvedPackInstance[$pkgHandle])) {
            static::$resolvedPackInstance[$pkgHandle] = self::app('Concrete\Core\Package\PackageService')->getByHandle($pkgHandle);
        }

        return static::$resolvedPackInstance[$pkgHandle];
    }

    /**
     * {@inheritdoc}
     *
     * @see Module::boot()
     */
    public static function boot()
    {
        $aliases = static::getClassAliases();
        if (!empty($aliases)) {
            $aliasList = ClassAliasList::getInstance();
            $aliasList->registerMultiple($aliases);
        }

        $app = self::app();
        $providers = static::getServiceProviders();
        if (is_array($providers) && !empty($providers)) {
            $app->make(ProviderList::class)->registerProviders($providers);
        }

        $routeListClasses = static::getRoutesClasses();
        if (is_array($routeListClasses) && !empty($routeListClasses)) {
            /**
             * @var \Concrete\Core\Routing\Router $router
             */
            $router = Route::getFacadeRoot();
            foreach ($routeListClasses as $routeListClass) {
                if (is_subclass_of($routeListClass, 'Concrete\Core\Routing\RouteListInterface')) {
                    $router->loadRouteList($app->build($routeListClass));
                } else {
                    throw new \Exception(t(static::class . ':getRoutesClass: RoutesClass should be instanceof Concrete\Core\Routing\RouteListInterface'));
                }
            }
        }

        $assetProviders = static::getAssetProviders();
        foreach ($assetProviders as $assetProviderClass) {
            if (is_subclass_of($assetProviderClass, 'Xanweb\Module\Asset\Provider')) {
                $assetProvider = $app->build($assetProviderClass, [static::pkg()]);
                $assetProvider->register();
            } else {
                throw new \Exception(t(static::class . ':getAssetProviders: Asset Provider class should extend Xanweb\Module\Asset\Provider'));
            }
        }
    }

    public static function isInstalled(): bool
    {
        try {
            return static::pkg()->isPackageInstalled();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Classes to be registered as aliases in \Concrete\Core\Foundation\ClassAliasList.
     *
     * @return array
     */
    protected static function getClassAliases()
    {
        return [
            static::getPackageAlias() => static::class,
        ];
    }

    /**
     * Get Package Alias.
     *
     * @return string
     */
    protected static function getPackageAlias(): string
    {
        return camelcase(static::pkgHandle());
    }

    /**
     * Get Service Providers Class Names.
     *
     * @return array
     */
    protected static function getServiceProviders()
    {
        return [];
    }

    /**
     * Get Classes names for RouteList, must be instance of \Concrete\Core\Routing\RouteListInterface.
     *
     * @return array
     */
    protected static function getRoutesClasses()
    {
        return [];
    }

    /**
     * AssetProviders should be instance of \Xanweb\Module\Asset\Provider.
     */
    protected static function getAssetProviders(): array
    {
        return [];
    }

    /**
     * @param string $make [optional]
     *
     * @return \Concrete\Core\Application\Application|object
     */
    protected static function app($make = null)
    {
        $app = Application::getFacadeApplication();

        if ($make !== null) {
            return $app->make($make);
        }

        return $app;
    }
}
