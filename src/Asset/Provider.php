<?php

namespace Xanweb\Module\Asset;

use Concrete\Core\Application\ApplicationAwareInterface;
use Concrete\Core\Application\ApplicationAwareTrait;
use Concrete\Core\Asset\AssetList;
use Concrete\Core\Entity\Package;

abstract class Provider implements ApplicationAwareInterface
{
    use ApplicationAwareTrait;

    /**
     * @var Package
     */
    protected $pkg;

    /**
     * @var AssetList
     */
    protected $assetList;

    public function __construct(Package $package)
    {
        $this->pkg = $package;

        $this->assetList = AssetList::getInstance();
    }

    /**
     * The Assets array will be passed to \Concrete\Core\Asset\AssetList::registerMultiple().
     *
     * <code>
     * [
     *     'asset/handle' => [
     *         ['javascript', 'js/my-asset.js', ['minify' => false], $this->pkg],
     *     ],
     * ]
     * </code>
     *
     * @see \Concrete\Core\Asset\AssetList::registerMultiple()
     */
    abstract public function getAssets(): array;

    /**
     * The Asset Groups array will be passed to \Concrete\Core\Asset\AssetList::registerGroupMultiple().
     *
     * <code>
     * [
     *     'asset-group/handle' => [
     *         [
     *              ['javascript', 'jquery/ui'],
     *              ['javascript-localized', 'jquery/ui'],
     *              ['css', 'jquery/ui'],
     *         ],
     *     ],
     * ]
     * </code>
     *
     * @see \Concrete\Core\Asset\AssetList::registerGroupMultiple()
     */
    public function getAssetGroups(): array
    {
        return [];
    }

    /**
     * Register Assets.
     */
    public function register(): void
    {
        $this->assetList->registerMultiple($this->getAssets());
        $this->assetList->registerGroupMultiple($this->getAssetGroups());
    }
}
