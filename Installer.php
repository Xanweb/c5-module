<?php

namespace Xanweb\Module;

use Concrete\Core\Attribute\Category\CategoryInterface as AttributeCategoryInterface;
use Concrete\Core\Attribute\Category\CategoryService;
use Concrete\Core\Attribute\SetFactory;
use Concrete\Core\Attribute\TypeFactory;
use Concrete\Core\Block\BlockType\BlockType;
use Concrete\Core\Block\BlockType\Set as BlockTypeSet;
use Concrete\Core\Entity\Attribute\Category as AttributeCategoryEntity;
use Concrete\Core\Entity\Attribute\Key\Key as AttributeKeyEntity;
use Concrete\Core\Entity\Attribute\Set as AttributeSetEntity;
use Concrete\Core\Entity\Attribute\Type as AttributeTypeEntity;
use Concrete\Core\Entity\Package as PackageEntity;
use Concrete\Core\Entity\Page\Template;
use Concrete\Core\Error\ErrorList\ErrorList;
use Concrete\Core\Job\Job;
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
     * @param string $pTemplateHandle
     * @param string $pTemplateName
     * @param string $pTemplateIcon
     * @param bool $pTemplateIsInternal
     * @return Template|null
     */
    public function installPageTemplate(string $pTemplateHandle, string $pTemplateName, string $pTemplateIcon = FILENAME_PAGE_TEMPLATE_DEFAULT_ICON, bool $pTemplateIsInternal = false): ?Template
    {
        if (!is_object($template = PageTemplate::getByHandle($pTemplateHandle))) {
            $template = PageTemplate::add($pTemplateHandle, $pTemplateName, $pTemplateIcon, $this->pkg, $pTemplateIsInternal);
        }

        return $template;
    }

    /**
     * Install Page Type if not Exist.
     *
     * @param string $pTemplateHandle
     * @param string $pTypeHandle
     * @param string $pTypeName
     * @return PageType|null
     */
    public function installPageType(string $pTemplateHandle, string $pTypeHandle, string $pTypeName): ?PageType
    {
        if (!is_object($type = PageType::getByHandle($pTypeHandle))) {
            $pTPL = PageTemplate::getByHandle($pTemplateHandle);
            if (!is_object($pTPL)) {
                throw new \RuntimeException(t('Page Template with handle `%s` not found.', $pTemplateHandle));
            }

            $type = PageType::add([
                'handle' => $pTypeHandle,
                'name' => $pTypeName,
                'defaultTemplate' => $pTPL,
                'ptIsFrequentlyAdded' => 1,
                'ptLaunchInComposer' => 1,
            ], $this->pkg);
        }

        return $type;
    }

    /**
     * Install single pages.
     *
     * @param array<string, string, array> ...$paths list of paths and names
     *                     Example:
     *                     <pre>
     *                     ['pagePath', 'pageName', optionalArrayOfAttributeKeysAndValues],
     *                     </pre>
     */
    public function installSinglePages(array ...$paths): void
    {
        foreach ($paths as $path) {
            $this->installSinglePage($path[0], $path[1], $path[2] ?? []);
        }
    }

    /**
     * Install Single Page if not exists.
     *
     * @param string $path
     * @param string $name
     * @param array $options
     *
     * @return Page return installed single page
     */
    public function installSinglePage(string $path, string $name, array $options = []): Page
    {
        $sp = Page::getByPath($path);
        if (!is_object($sp) || COLLECTION_NOT_FOUND === $sp->getError()) {
            $sp = SinglePage::add($path, $this->pkg);

            $sp->update(['cName' => $name]);

            foreach ($options as $key => $value) {
                $sp->setAttribute($key, $value);
            }
        }

        return $sp;
    }

    /**
     * Install BlockType Sets.
     *
     * @param array<string, string> ...$sets array of handles and names
     * Example:
     *                     <pre>
     *                     ['btSetHandle', 'btSetName'], ['btSetHandle', 'btSetName'],
     *                     </pre>
     */
    public function installBlockTypeSets(array ...$sets): void
    {
        foreach ($sets as $set) {
            $this->installBlockTypeSet($set[0], $set[1]);
        }
    }

    /**
     * Install BlockTypeSet if Exists.
     *
     * @param string $handle
     * @param string $name
     *
     * @return BlockTypeSet return installed BlockTypeSet
     */
    public function installBlockTypeSet(string $handle, string $name): BlockTypeSet
    {
        if (!is_object($bts = BlockTypeSet::getByHandle($handle))) {
            $bts = BlockTypeSet::add($handle, $name, $this->pkg);
        }

        return $bts;
    }

    /**
     * Install Jobs
     *
     * @param string ...$handles
     *
     * @return Job[] return installed Jobs
     */
    public function installJobs(string ...$handles): array
    {
        $jobs = [];
        foreach ($handles as $handle) {
            $jobs[] = $this->installJob($handle);
        }

        return $jobs;
    }

    /**
     * Install Job if it doesn't exist.
     *
     * @param string $handle
     *
     * @return Job return installed Job
     */
    public function installJob(string $handle): Job
    {
        if (!is_object($job = Job::getByHandle($handle))) {
            $job = Job::installByPackage($handle, $this->pkg);
        }

        return $job;
    }

    /**
     * Install BlockTypes.
     *
     * @param mixed ...$handles list of handles.
     * Example:
     *                     <code>
     *                     'btHandle1', ['btHandle2', 'btSetObj'], 'btHandle3',
     *                     </code>
     */
    public function installBlockTypes(...$handles): void
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
     * Install BlockType if Exists.
     *
     * @param string $handle
     * @param BlockTypeSet|string $bts Block Type Set object or handle
     *
     * @return \Concrete\Core\Entity\Block\BlockType\BlockType return installed BlockType
     */
    public function installBlockType(string $handle, $bts = null): \Concrete\Core\Entity\Block\BlockType\BlockType
    {
        if (!is_object($bt = BlockType::getByHandle($handle))) {
            $bt = BlockType::installBlockType($handle, $this->pkg);

            if (is_string($bts)) {
                $bts = BlockTypeSet::getByHandle($bts);
            }

            if ($bts instanceof BlockTypeSet) {
                $bts->addBlockType($bt);
            }
        }

        return $bt;
    }

    /**
     * Install AttributeKeyCategory.
     *
     * @param string $handle The handle string for the category
     * @param int $allowSets This should be an attribute AttributeKeyCategory::ASET_ALLOW_* constant
     * @param array $associatedAttrTypes array of attribute type handles to be associated with
     *
     * @return AttributeCategoryInterface
     */
    public function installAttributeKeyCategory(string $handle, int $allowSets = 0, array $associatedAttrTypes = []): AttributeCategoryInterface
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
     * Install AttributeTypes.
     *
     * @param array<string, string, AttributeCategoryEntity> ...$handles list of handles and names
     */
    public function installAttributeTypes(array ...$handles): void
    {
        foreach ($handles as $handle) {
            $this->installAttributeType($handle[0], $handle[1], $handle[2] ?? null);
        }
    }

    /**
     * Install AttributeType if Exists.
     *
     * @param string $handle
     * @param string $name
     * @param AttributeCategoryEntity|null $akc
     *
     * @return AttributeTypeEntity return installed attribute type
     */
    public function installAttributeType(string $handle, string $name, ?AttributeCategoryEntity $akc = null): AttributeTypeEntity
    {
        $atFactory = $this->app->make(TypeFactory::class);
        $at = $atFactory->getByHandle($handle);
        if (!is_object($at)) {
            $at = $atFactory->add($handle, $name, $this->pkg);

            if ($akc !== null) {
                $akc->getController()->associateAttributeKeyType($at);
            }
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
     * @return AttributeKeyEntity[] return installed AttrKeys
     */
    public function installSiteAttributeKeys(array $data): array
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
     * @return AttributeKeyEntity[] return installed AttrKeys
     */
    public function installPageAttributeKeys(array $data): array
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
     * @return AttributeKeyEntity[] return installed AttrKeys
     */
    public function installUserAttributeKeys(array $data): array
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
     * @return AttributeKeyEntity[] return installed AttrKeys
     */
    public function installFileAttributeKeys(array $data): array
    {
        return $this->installAttributeKeys('file', $data);
    }

    /**
     * Install AttributeKeys.
     *
     * @param AttributeCategoryEntity|string $akCateg AttributeKeyCategory object or handle
     * @param array $data array of handles and names
     *
     * @return AttributeKeyEntity[] return installed AttrKeys
     */
    public function installAttributeKeys($akCateg, array $data): array
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
     * @param AttributeCategoryEntity|string $akCateg AttributeKeyCategory object or handle
     * @param AttributeTypeEntity|string $type
     * @param array{akHandle: string, akName: string, settings: array} $data
     *
     * @return AttributeKeyEntity return installed attribute key
     */
    public function installAttributeKey($akCateg, $type, array $data): AttributeKeyEntity
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
     * @return AttributeSetEntity[]
     */
    public function installSiteAttributeSets(array $data): array
    {
        return $this->installAttributeSets('site', $data);
    }

    /**
     * Install PageAttributeSets.
     *
     * @param array $data array of handles and names
     *
     * @return AttributeSetEntity[]
     */
    public function installPageAttributeSets(array $data): array
    {
        return $this->installAttributeSets('collection', $data);
    }

    /**
     * Install UserAttributeSets.
     *
     * @param array $data array of handles and names
     *
     * @return AttributeSetEntity[]
     */
    public function installUserAttributeSets(array $data): array
    {
        return $this->installAttributeSets('user', $data);
    }

    /**
     * Install FileAttributeSets.
     *
     * @param array $data array of handles and names
     *
     * @return AttributeSetEntity[]
     */
    public function installFileAttributeSets(array $data): array
    {
        return $this->installAttributeSets('file', $data);
    }

    /**
     * Install AttributeSets.
     *
     * @param AttributeCategoryEntity|string $akCateg AttributeKeyCategory object or handle
     * @param array $data array of handles and names
     *
     * @return AttributeSetEntity[]
     */
    public function installAttributeSets($akCateg, array $data): array
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
     * @return AttributeSetEntity
     */
    public function installPageAttributeSet(string $handle, string $name, array $associatedAttrs = []): AttributeSetEntity
    {
        return $this->installAttributeSet('collection', $handle, $name, $associatedAttrs);
    }

    /**
     * @param AttributeCategoryEntity|string $akCateg
     * @param string $handle
     * @param string $name
     * @param array $associatedAttrs
     *
     * @return AttributeSetEntity
     */
    public function installAttributeSet($akCateg, string $handle, string $name, array $associatedAttrs = []): AttributeSetEntity
    {
        if (is_string($akCateg)) {
            $akCateg = $this->app->make(CategoryService::class)->getByHandle($akCateg);
        }

        $akCategController = $akCateg->getController();
        $manager = $akCategController->getSetManager();

        $set = $this->app->make(SetFactory::class)->getByHandle($handle);
        if (!is_object($set)) {
            $set = $manager->addSet($handle, $name, $this->pkg);

            foreach ($associatedAttrs as $ak) {
                if (is_string($ak)) {
                    $ak = $akCategController->getAttributeKeyByHandle($ak);
                }

                if ($ak !== null) {
                    $manager->addKey($set, $ak);
                }
            }
        }

        return $set;
    }

    /**
     * Associate Attribute Keys To Set.
     *
     * @param AttributeKeyEntity[] $aks Array of attribute keys
     * @param AttributeSetEntity|string $akSetHandleOrObj AttributeSet handle or object
     *
     * @throws \RuntimeException
     */
    public function associateAttributeKeysToSet(array $aks, $akSetHandleOrObj): void
    {
        if (is_string($akSetHandleOrObj)) {
            $akSetObj = $this->app->make(SetFactory::class)->getByHandle($akSetHandleOrObj);
            if (!is_object($akSetObj)) {
                throw new \RuntimeException(__METHOD__ . ': ' . t('The Attribute Set "%s" is not installed.', $akSetHandleOrObj));
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
