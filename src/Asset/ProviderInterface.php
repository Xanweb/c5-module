<?php

namespace Xanweb\Module\Asset;


interface ProviderInterface
{
    /**
     * The Assets array will passed to \Concrete\Core\Asset\AssetList::registerMultiple()
     *
     * @see \Concrete\Core\Asset\AssetList::registerMultiple()
     */
    public function getAssets(): array;

    /**
     * The Asset Groups array will passed to \Concrete\Core\Asset\AssetList::registerGroupMultiple()
     *
     * @see \Concrete\Core\Asset\AssetList::registerGroupMultiple()
     */
    public function getAssetGroups(): array;
}
