<?php

namespace Xanweb\Module;

use Concrete\Core\Attribute\Category\CategoryService;
use Concrete\Core\Attribute\SetFactory;
use Concrete\Core\Attribute\TypeFactory;
use Concrete\Core\Block\BlockType\BlockType;
use Concrete\Core\Block\BlockType\Set as BlockTypeSet;
use Concrete\Core\Entity\Package as PackageEntity;
use Concrete\Core\Error\ErrorList\ErrorList;
use Concrete\Core\Package\Package;
use Concrete\Core\Page\Page;
use Concrete\Core\Page\Single as SinglePage;
use Concrete\Core\Page\Template as PageTemplate;
use Concrete\Core\Page\Type\Type as PageType;
use Concrete\Core\Support\Facade\Application;

class Installer
{
    /**
     * @var \Concrete\Core\Application\Application
     */
    protected $app;

    /**
     * @param PackageEntity $pkg
     */
    protected $pkg;

    /**
     * Installer constructor.
     *
     * @param Package|PackageEntity $pkg
     */
    public function __construct($pkg)
    {
        $this->pkg = ($pkg instanceof Package) ? $pkg->getPackageEntity() : $pkg;
        $this->app = Application::getFacadeApplication();
    }

    /**
     * install Page Template if not Exist.
     *
     * @param $pTemplateHandle
     * @param $pTemplateName
     * @param $pTemplateIcon
     */
    public function installPageTemplate($pTemplateHandle, $pTemplateName, $pTemplateIcon = FILENAME_PAGE_TEMPLATE_DEFAULT_ICON)
    {
        if (!PageTemplate::getByHandle($pTemplateHandle)) {
            PageTemplate::add($pTemplateHandle, $pTemplateName, $pTemplateIcon, $this->pkg);
        }
    }

    /**
     * Install Page Type if not Exist.
     *
     * @param $pTemplateHandle
     * @param $pTypeHandle
     * @param $pTypeName
     */
    public function installPageType($pTemplateHandle, $pTypeHandle, $pTypeName)
    {
        $pTPL = PageTemplate::getByHandle($pTemplateHandle);
        if (is_object($pTPL) && !PageType::getByHandle($pTypeHandle)) {
            PageType::add([
                'handle' => $pTypeHandle,
                'name' => $pTypeName,
                'defaultTemplate' => $pTPL,
                'ptIsFrequentlyAdded' => 1,
                'ptLaunchInComposer' => 1,
            ], $this->pkg);
        }
    }

    /**
     * Install Or Update single pages.
     *
     * @param array $paths array of paths and names
     *                     Example:
     *                     <pre>
     *                     [
     *                     ['pagePath', 'pageName', optionalArrayofAttributeKeysAndValues],
     *                     ]
     *                     </pre>
     */
    public function installSinglePages(array $paths)
    {
        foreach ($paths as $path) {
            $this->installSinglePage($path[0], $path[1], $path[2] ?? []);
        }
    }

    /**
     * Install Or Update Single Page if Exists.
     *
     * @param string $path
     * @param string $name
     * @param array $options
     *
     * @return SinglePage return installed single page
     */
    public function installSinglePage($path, $name, $options = [])
    {
        $sp = Page::getByPath($path);
        if (!is_object($sp) || COLLECTION_NOT_FOUND === $sp->getError()) {
            $sp = SinglePage::add($path, $this->pkg);
        }

        $sp->update(['cName' => $name]);

        foreach ($options as $key => $value) {
            $sp->setAttribute($key, $value);
        }

        return $sp;
    }

    /**
     * Install Or Update BlockTypeSets.
     *
     * @param array $handles array of handles and names
     */
    public function installBlockTypeSets(array $handles)
    {
        foreach ($handles as $handle) {
            $this->installBlockTypeSet($handle[0], $handle[1]);
        }
    }

    /**
     * Install Or Update BlockTypeSet if Exists.
     *
     * @param string $handle
     * @param string $name
     *
     * @return BlockTypeSet return installed BlockTypeSet
     */
    public function installBlockTypeSet($handle, $name)
    {
        $bts = BlockTypeSet::getByHandle($handle);

        if (!is_object($bts)) {
            $bts = BlockTypeSet::add($handle, $name, $this->pkg);
        }

        return $bts;
    }

    /**
     * Install Or Update BlockTypes.
     *
     * @param array $handles array of handles. You can also include Blocktype sets and
     *                       use an array ['bt_handle', $btSetObj] instead of simple handle
     */
    public function installBlockTypes(array $handles)
    {
        foreach ($handles as $handle) {
            $blockTypeSet = null;
            $btHandle = $handle;
            if (is_array($handle)) {
                $btHandle = $handle[0];
                $blockTypeSet = $handle[1] ?? null;
            }

            $this->installBlockType($btHandle, $blockTypeSet);
        }
    }

    /**
     * Install Or Update BlockType if Exists.
     *
     * @param string $handle
     * @param BlockTypeSet|string $bts Block Type Set object or handle
     *
     * @return \Concrete\Core\Entity\Block\BlockType\BlockType return installed BlockType
     */
    public function installBlockType(string $handle, $bts = null)
    {
        $bt = BlockType::getByHandle($handle);
        if (!is_object($bt)) {
            $bt = BlockType::installBlockType($handle, $this->pkg);
        }

        if (is_string($bts)) {
            $bts = BlockTypeSet::getByHandle($bts);
        }

        if (is_object($bts) && $bts instanceof BlockTypeSet) {
            $bts->addBlockType($bt);
        }

        return $bt;
    }

    /**
     * Install Or Update AttributeKeyCategory.
     *
     * @param string $handle The handle string for the category
     * @param int $allowSets This should be an attribute AttributeKeyCategory::ASET_ALLOW_* constant
     * @param array $associatedAttrTypes array of attribute type handles to be associated with
     *
     * @return \Concrete\Core\Attribute\Category\CategoryInterface
     */
    public function installAttributeKeyCategory($handle, $allowSets = 0, array $associatedAttrTypes = [])
    {
        $akCategSvc = $this->app->make(CategoryService::class);
        $akCateg = $akCategSvc->getByHandle($handle);
        if (!is_object($akCateg)) {
            $akCateg = $akCategSvc->add($handle, $allowSets, $this->pkg);
        } else {
            $akCateg = $akCateg->getController();
        }

        $atFactory = $this->app->make(TypeFactory::class);
        foreach ($associatedAttrTypes as $atHandle) {
            $akCateg->associateAttributeKeyType($atFactory->getByHandle($atHandle));
        }

        return $akCateg;
    }

    /**
     * Install Or Update AttributeTypes.
     *
     * @param array $handles array of handles and names
     */
    public function installAttributeTypes(array $handles)
    {
        foreach ($handles as $handle) {
            $this->installAttributeType($handle[0], $handle[1], $handle[2] ?? null);
        }
    }

    /**
     * Install Or Update AttributeType if Exists.
     *
     * @param string $handle
     * @param string $name
     * @param \Concrete\Core\Entity\Attribute\Category $akc
     *
     * @return \Concrete\Core\Entity\Attribute\Type return installed attribute type
     */
    public function installAttributeType($handle, $name, $akc = null)
    {
        $atFactory = $this->app->make(TypeFactory::class);

        $at = $atFactory->getByHandle($handle);
        if (!is_object($at)) {
            $at = $atFactory->add($handle, $name, $this->pkg);
        }

        if (is_object($akc)) {
            $akc->getController()->associateAttributeKeyType($at);
        }

        return $at;
    }

    /**
     * Install SiteAttributeKeys.
     * Example of $data:
     * <pre>
     * [
     *    'at_type_handle' => [
     *       ['akHandle' => 'ak_handle', 'akName' => 'AttributeKey Name']
     *    ]
     * ]
     * </pre>.
     *
     * @param array $data array of handles and names
     *
     * @return \Concrete\Core\Entity\Attribute\Key\Key[] return installed AttrKeys
     */
    public function installSiteAttributeKeys(array $data)
    {
        return $this->installAttributeKeys('site', $data);
    }

    /**
     * Install PageAttributeKeys.
     * Example of $data:
     * <pre>
     * [
     *    'at_type_handle' => [
     *       ['akHandle' => 'ak_handle', 'akName' => 'AttributeKey Name']
     *    ]
     * ]
     * </pre>.
     *
     * @param array $data array of handles and names
     *
     * @return \Concrete\Core\Entity\Attribute\Key\Key[] return installed AttrKeys
     */
    public function installPageAttributeKeys(array $data)
    {
        return $this->installAttributeKeys('collection', $data);
    }

    /**
     * Install UserAttributeKeys.
     * Example of $data:
     * <pre>
     * [
     *    'at_type_handle' => [
     *       ['akHandle' => 'ak_handle', 'akName' => 'AttributeKey Name']
     *    ]
     * ]
     * </pre>.
     *
     * @param array $data array of handles and names
     *
     * @return \Concrete\Core\Entity\Attribute\Key\Key[] return installed AttrKeys
     */
    public function installUserAttributeKeys(array $data)
    {
        return $this->installAttributeKeys('user', $data);
    }

    /**
     * Install FileAttributeKeys.
     * Example of $data:
     * <pre>
     * [
     *    'at_type_handle' => [
     *       ['akHandle' => 'ak_handle', 'akName' => 'AttributeKey Name']
     *    ]
     * ]
     * </pre>.
     *
     * @param array $data array of handles and names
     *
     * @return \Concrete\Core\Entity\Attribute\Key\Key[] return installed AttrKeys
     */
    public function installFileAttributeKeys(array $data)
    {
        return $this->installAttributeKeys('file', $data);
    }

    /**
     * Install AttributeKeys.
     *
     * @param \Concrete\Core\Entity\Attribute\Category|string $akCateg AttributeKeyCategory object or handle
     * @param array $data array of handles and names
     *
     * @return \Concrete\Core\Entity\Attribute\Key\Key[] return installed AttrKeys
     */
    public function installAttributeKeys($akCateg, array $data)
    {
        if (is_string($akCateg)) {
            $akCateg = $this->app->make(CategoryService::class)->getByHandle($akCateg);
        }

        $installedAks = [];
        $atFactory = $this->app->make(TypeFactory::class);
        foreach ($data as $atHandle => $attrs) {
            $at = $atFactory->getByHandle($atHandle);
            foreach ($attrs as $params) {
                $ak = $this->installAttributeKey($akCateg, $at, $params);
                if (is_object($ak)) {
                    $installedAks[$ak->getAttributeKeyHandle()] = $ak;
                }
            }
        }

        return $installedAks;
    }

    /**
     * Install AttributeKey if not Exists.
     *
     * @param \Concrete\Core\Entity\Attribute\Category|string $akCateg AttributeKeyCategory object or handle
     * @param \Concrete\Core\Entity\Attribute\Type|string $type
     * @param array $data
     *
     * @return \Concrete\Core\Entity\Attribute\Key\Key return installed attribute key
     */
    public function installAttributeKey($akCateg, $type, $data)
    {
        if (is_string($akCateg)) {
            $akCateg = $this->app->make(CategoryService::class)->getByHandle($akCateg);
        }

        if (is_string($type)) {
            $type = $this->app->make(TypeFactory::class)->getByHandle($type);
        }

        $akCategController = $akCateg->getController();
        $cak = $akCategController->getAttributeKeyByHandle($data['akHandle']);
        if (!is_object($cak)) {
            $key = $akCategController->createAttributeKey();
            $key->setAttributeKeyHandle($data['akHandle']);
            $key->setAttributeKeyName($data['akName']);

            $akSettings = null;
            if (isset($data['settings'])) {
                if (isset($data['settings']['akIsSearchableIndexed'])) {
                    $key->setIsAttributeKeyContentIndexed((bool) $data['settings']['akIsSearchableIndexed']);
                }

                if (isset($data['settings']['akIsSearchable'])) {
                    $key->setIsAttributeKeySearchable((bool) $data['settings']['akIsSearchable']);
                }

                $akSettings = $type->getController()->saveKey($data['settings']);
            }

            return $akCategController->add($type, $key, $akSettings, $this->pkg);
        }

        return $cak;
    }

    /**
     * Install SiteAttributeSets.
     *
     * @param array $data array of handles and names
     *
     * @return \Concrete\Core\Entity\Attribute\Set[]
     */
    public function installSiteAttributeSets(array $data)
    {
        return $this->installAttributeSets('site', $data);
    }

    /**
     * Install PageAttributeSets.
     *
     * @param array $data array of handles and names
     *
     * @return \Concrete\Core\Entity\Attribute\Set[]
     */
    public function installPageAttributeSets(array $data)
    {
        return $this->installAttributeSets('collection', $data);
    }

    /**
     * Install UserAttributeSets.
     *
     * @param array $data array of handles and names
     *
     * @return \Concrete\Core\Entity\Attribute\Set[]
     */
    public function installUserAttributeSets(array $data)
    {
        return $this->installAttributeSets('user', $data);
    }

    /**
     * Install FileAttributeSets.
     *
     * @param array $data array of handles and names
     *
     * @return \Concrete\Core\Entity\Attribute\Set[]
     */
    public function installFileAttributeSets(array $data)
    {
        return $this->installAttributeSets('file', $data);
    }

    /**
     * Install AttributeSets.
     *
     * @param \Concrete\Core\Entity\Attribute\Category|string $akCateg AttributeKeyCategory object or handle
     * @param array $data array of handles and names
     *
     * @return \Concrete\Core\Entity\Attribute\Set[]
     */
    public function installAttributeSets($akCateg, array $data)
    {
        if (is_string($akCateg)) {
            $akCateg = $this->app->make(CategoryService::class)->getByHandle($akCateg);
        }

        $installedAttrSets = [];
        foreach ($data as $params) {
            $atSet = $this->installAttributeSet($akCateg, $params[0], $params[1], $params[2] ?? []);
            if (is_object($atSet)) {
                $installedAttrSets[$atSet->getAttributeSetHandle()] = $atSet;
            }
        }

        return $installedAttrSets;
    }

    /**
     * @param string $handle
     * @param string $name
     * @param array $associatedAttrs
     *
     * @return \Concrete\Core\Entity\Attribute\Set
     */
    public function installPageAttributeSet($handle, $name, array $associatedAttrs = [])
    {
        return $this->installAttributeSet('collection', $handle, $name, $associatedAttrs);
    }

    /**
     * @param \Concrete\Core\Entity\Attribute\Category|string $akCateg
     * @param string $handle
     * @param string $name
     * @param array $associatedAttrs
     *
     * @return \Concrete\Core\Entity\Attribute\Set
     */
    public function installAttributeSet($akCateg, $handle, $name, array $associatedAttrs = [])
    {
        if (is_string($akCateg)) {
            $akCateg = $this->app->make(CategoryService::class)->getByHandle($akCateg);
        }

        $akCategController = $akCateg->getController();
        $manager = $akCategController->getSetManager();

        $set = $this->app->make(SetFactory::class)->getByHandle($handle);
        if (!is_object($set)) {
            $set = $manager->addSet($handle, $name, $this->pkg);
        }

        foreach ($associatedAttrs as $ak) {
            if (is_string($ak)) {
                $ak = $akCategController->getAttributeKeyByHandle($ak);
            }

            if (is_object($ak)) {
                $manager->addKey($set, $ak);
            }
        }

        return $set;
    }

    /**
     * Associate Attribute Keys To Set.
     *
     * @param \Concrete\Core\Entity\Attribute\Key\Key[] $aks Array of attribute keys
     * @param \Concrete\Core\Entity\Attribute\Set|string $akSetHandleOrObj AttributeSet handle or object
     *
     * @throws \Exception
     */
    public function associateAttributeKeysToSet(array $aks, $akSetHandleOrObj)
    {
        if (is_string($akSetHandleOrObj)) {
            $akSetObj = $this->app->make(SetFactory::class)->getByHandle($akSetHandleOrObj);
            if (!is_object($akSetObj)) {
                throw new \Exception(__METHOD__ . ': ' . t('The Attribute Set "%s" is not installed.', $akSetHandleOrObj));
            }
        } else {
            $akSetObj = $akSetHandleOrObj;
        }

        $aSetAttrKeys = $akSetObj->getAttributeKeys();
        foreach ($aks as $ak) {
            if (!in_array($ak, $aSetAttrKeys)) {
                $akSetObj->addKey($ak);
            }
        }
    }

    /**
     * Override Blocks By Package.
     *
     * @param array $blocks
     * @param PackageEntity|int|null $pkgObjOrId
     *
     * @return ErrorList
     */
    public function overrideBlocks(array $blocks, $pkgObjOrId = null): ErrorList
    {
        $pkgID = $this->pkg->getPackageID();
        if ($pkgObjOrId) {
            if (is_object($pkgObjOrId)) {
                $pkgID = $pkgObjOrId->getPackageID();
            } elseif (is_int($pkgObjOrId) && $pkgObjOrId > 0) {
                $pkgID = (int) $pkgObjOrId;
            } else {
                throw new \RuntimeException(__METHOD__ . ': ' . t('Invalid given package or package id.'));
            }
        }

        $e = new ErrorList();
        foreach ($blocks as $btHandle) {
            $block = BlockType::getByHandle($btHandle);
            if ($block !== null) {
                $block->setPackageID($pkgID);
                $block->refresh();
            } else {
                $e->add(__METHOD__ . ': ' . t('Block Type with handle `%s` not found.', $btHandle));
            }
        }

        return $e;
    }

    /**
     * Assign Blocks to Core.
     *
     * @param array $blocks
     *
     * @return ErrorList
     */
    public function assignBlocksToCore(array $blocks): ErrorList
    {
        $e = new ErrorList();
        foreach ($blocks as $btHandle) {
            $block = BlockType::getByHandle($btHandle);
            if ($block !== null) {
                $block->setPackageID(0);
                $block->refresh();
            } else {
                $e->add(__METHOD__ . ': ' . t('Block Type with handle `%s` not found.', $btHandle));
            }
        }

        return $e;
    }
}
