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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Xanweb\Module\Asset\Provider;

/**
 * @method static string getPackagePath() @see \Concrete\Core\Package\Package::getPackagePath()
 * @method static string getRelativePath() @see \Concrete\Core\Package\Package::getRelativePath()
 */
abstract class Module implements ModuleInterface
{
    /**
     * The resolved controller instances.
     *
     * @var array
     */
    private static array $resolvedPackController = [];

    /**
     * Class to be used Statically.
     */
    private function __construct()
    {
    }

    /**
     * Handle dynamic, static calls to the controller.
     *
     * @param string $method
     * @param array $args
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
     * @see ModuleInterface::pkg()
     */
    public static function pkg(): Package
    {
        return self::controller()->getPackageEntity();
    }

    /**
     * {@inheritdoc}
     *
     * @see ModuleInterface::boot()
     */
    public static function boot(): void
    {
        // Register Class Aliases
        if (is_array($aliases = static::getClassAliases()) && $aliases !== []) {
            ClassAliasList::getInstance()->registerMultiple($aliases);
        }

        $app = self::app();
        // Register Service Providers
        if (($providers = static::getServiceProviders()) !== []) {
            $app->make(ProviderList::class)->registerProviders($providers);
        }

        // Register Route Lists
        if (($routeListClasses = static::getRoutesClasses()) !== []) {
            $router = Route::getFacadeRoot();
            foreach ($routeListClasses as $routeListClass) {
                if (is_subclass_of($routeListClass, RouteListInterface::class)) {
                    $router->loadRouteList($app->build($routeListClass));
                } else {
                    self::throwInvalidClassRuntimeException('getRoutesClasses', $routeListClass, RouteListInterface::class);
                }
            }
        }

        // Register Asset Providers
        $assetProviders = static::getAssetProviders();
        foreach ($assetProviders as $assetProviderClass) {
            if (is_subclass_of($assetProviderClass, Provider::class)) {
                $app->build($assetProviderClass, ['package' => static::pkg()])->register();
            } else {
                self::throwInvalidClassRuntimeException('getAssetProviders', $assetProviderClass, Provider::class);
            }
        }

        // Register Event Subscribers
        if (($evtSubscriberClasses = static::getEventSubscribers()) !== []) {
            $director = $app->make('director');
            foreach ($evtSubscriberClasses as $evtSubscriberClass) {
                if (is_subclass_of($evtSubscriberClass, EventSubscriberInterface::class)) {
                    $director->addSubscriber($app->build($evtSubscriberClass));
                } else {
                    self::throwInvalidClassRuntimeException('getEventSubscribers', $evtSubscriberClass, EventSubscriberInterface::class);
                }
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
     * Get Database Config.
     *
     * @param string|null $key
     * @param mixed $default
     *
     * @return \Concrete\Core\Config\Repository\Liaison|mixed
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
     * Classes to be registered as aliases in \Concrete\Core\Foundation\ClassAliasList.
     *
     * @return array
     */
    protected static function getClassAliases(): array
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
    protected static function getServiceProviders(): array
    {
        return [];
    }

    /**
     * Get Classes names for RouteList, must be an instance of \Concrete\Core\Routing\RouteListInterface.
     *
     * @return string[]
     */
    protected static function getRoutesClasses(): array
    {
        return [];
    }

    /**
     * AssetProviders should be an instance of \Xanweb\Module\Asset\Provider.
     *
     * @return string[]
     */
    protected static function getAssetProviders(): array
    {
        return [];
    }

    /**
     * Event Subscribers should be an instance of \Symfony\Component\EventDispatcher\EventSubscriberInterface.
     *
     * @return string[]
     */
    protected static function getEventSubscribers(): array
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

    private static function throwInvalidClassRuntimeException(string $relatedMethod, $targetClass, string $requiredClass): void
    {
        throw new \RuntimeException(t('%s:%s - `%s` should be an instance of `%s`', static::class, $relatedMethod, (string) $targetClass, $requiredClass));
    }

    private static function controller(): PackageController
    {
        $pkgHandle = static::pkgHandle();

        return self::$resolvedPackController[$pkgHandle] ??
            self::$resolvedPackController[$pkgHandle] = self::app(PackageService::class)->getClass($pkgHandle);
    }
}
