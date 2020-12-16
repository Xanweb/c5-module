<?php

namespace Xanweb\Module;

use Concrete\Core\Entity\Package;
use Concrete\Core\Foundation\ClassAliasList;
use Concrete\Core\Foundation\Service\ProviderList;
use Concrete\Core\Package\Package as PackageController;
use Concrete\Core\Package\PackageService;
use Concrete\Core\Routing\RouteListInterface;
use Concrete\Core\Support\Facade\Application;
use Concrete\Core\Support\Facade\Route;
use Illuminate\Support\Str;
use Xanweb\Module\Asset\Provider;

/**
 * @method static string getPackagePath() @see \Concrete\Core\Package\Package::getPackagePath()
 * @method static string getRelativePath() @see \Concrete\Core\Package\Package::getRelativePath()
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
     * The resolved controller instances.
     *
     * @var array
     */
    private static $resolvedPackController;

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
        return self::controller()->$method(...$args);
    }

    /**
     * {@inheritdoc}
     *
     * @see Module::pkg()
     */
    public static function pkg(): Package
    {
        $pkgHandle = static::pkgHandle();

        return static::$resolvedPackInstance[$pkgHandle] ?? static::$resolvedPackInstance[$pkgHandle] = self::app(PackageService::class)->getByHandle($pkgHandle);
    }

    /**
     * {@inheritdoc}
     *
     * @see Module::boot()
     */
    public static function boot()
    {
        if (!empty($aliases = static::getClassAliases())) {
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
                if (is_subclass_of($routeListClass, RouteListInterface::class)) {
                    $router->loadRouteList($app->build($routeListClass));
                } else {
                    throw new \Exception(t(static::class . ':getRoutesClass: RoutesClass should be instanceof Concrete\Core\Routing\RouteListInterface'));
                }
            }
        }

        $assetProviders = static::getAssetProviders();
        foreach ($assetProviders as $assetProviderClass) {
            if (is_subclass_of($assetProviderClass, Provider::class)) {
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
        return Str::studly(static::pkgHandle());
    }

    /**
     * Get Service Providers Class Names.
     *
     * @return string[]
     */
    protected static function getServiceProviders()
    {
        return [];
    }

    /**
     * Get Classes names for RouteList, must be instance of \Concrete\Core\Routing\RouteListInterface.
     *
     * @return string[]
     */
    protected static function getRoutesClasses()
    {
        return [];
    }

    /**
     * AssetProviders should be instance of \Xanweb\Module\Asset\Provider.
     *
     * @return string[]
     */
    protected static function getAssetProviders(): array
    {
        return [];
    }

    /**
     * Get Database Config.
     *
     * @param string|null $key
     * @param mixed $default
     *
     * @return \Concrete\Core\Config\Repository\Liaison|mixed
     * @noinspection PhpMissingReturnTypeInspection
     */
    final public static function getConfig(?string $key = null, $default = null)
    {
        $config = self::controller()->getDatabaseConfig();
        if ($key !== null) {
            return $config->get($key, $default);
        }

        return $config;
    }

    /**
     * Get File Config.
     *
     * @param string|null $key
     * @param mixed $default
     *
     * @return \Concrete\Core\Config\Repository\Liaison|mixed
     * @noinspection PhpMissingReturnTypeInspection
     */
    final public static function getFileConfig(?string $key = null, $default = null)
    {
        $config = self::controller()->getFileConfig();
        if ($key !== null) {
            return $config->get($key, $default);
        }

        return $config;
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

    private static function controller(): PackageController
    {
        $pkgHandle = static::pkgHandle();

        return self::$resolvedPackController[$pkgHandle] ?? self::$resolvedPackController[$pkgHandle] = static::pkg()->getController();
    }
}
