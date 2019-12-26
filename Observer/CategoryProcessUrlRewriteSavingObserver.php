<?php
/**
 * Copyright © 2017 Chad A. Carino. All rights reserved.
 * See LICENSE file for license details.
 *
 * @package    Bangerkuwranger/GtidSafeUrlRewriteTables
 * @author     Chad A. Carino <artist@chadacarino.com>
 * @author     Burak Bingollu <burak.bingollu@gmail.com>
 * @copyright  2017 Chad A. Carino
 * @license    https://opensource.org/licenses/MIT  MIT License
 */

namespace Bangerkuwranger\GtidSafeUrlRewriteTables\Observer;

use Magento\Catalog\Model\Category;
use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator;
use Magento\CatalogUrlRewrite\Model\UrlRewriteBunchReplacer;
use Magento\Framework\Event\ObserverInterface;
use Magento\CatalogUrlRewrite\Observer\UrlRewriteHandler;
use Bangerkuwranger\GtidSafeUrlRewriteTables\Model\Rewrite\CatalogUrlRewrite\Map\DatabaseMapPool;
use Bangerkuwranger\GtidSafeUrlRewriteTables\Model\Rewrite\CatalogUrlRewrite\Map\DataCategoryUrlRewriteDatabaseMap;
use Bangerkuwranger\GtidSafeUrlRewriteTables\Model\Rewrite\CatalogUrlRewrite\Map\DataProductUrlRewriteDatabaseMap;

class CategoryProcessUrlRewriteSavingObserver extends \Magento\CatalogUrlRewrite\Observer\CategoryProcessUrlRewriteSavingObserver
{
    /**
     * Category UrlRewrite generator.
     *
     * @var CategoryUrlRewriteGenerator
     */
    private $categoryUrlRewriteGenerator;

    /**
     * Url Rewrite Replacer based on bunches.
     *
     * @var UrlRewriteBunchReplacer
     */
    private $urlRewriteBunchReplacer;

    /**
     * UrlRewrite handler.
     *
     * @var UrlRewriteHandler
     */
    private $urlRewriteHandler;

    /**
     * Pool for database maps.
     * @var DatabaseMapPool
     */
    private $databaseMapPool;

    /**
     * Class names of maps that hold data for url rewrites entities.
     *
     * @var string[]
     */
    private $dataUrlRewriteClassNames;

    /**
     * @param CategoryUrlRewriteGenerator $categoryUrlRewriteGenerator
     * @param UrlRewriteHandler $urlRewriteHandler
     * @param UrlRewriteBunchReplacer $urlRewriteBunchReplacer
     * @param DatabaseMapPool $databaseMapPool
     * @param string[] $dataUrlRewriteClassNames
     */
    public function __construct(
        CategoryUrlRewriteGenerator $categoryUrlRewriteGenerator,
        UrlRewriteHandler $urlRewriteHandler,
        UrlRewriteBunchReplacer $urlRewriteBunchReplacer,
        DatabaseMapPool $databaseMapPool,
        $dataUrlRewriteClassNames = [
            DataCategoryUrlRewriteDatabaseMap::class,
            DataProductUrlRewriteDatabaseMap::class
        ]
    ) {
        $this->categoryUrlRewriteGenerator = $categoryUrlRewriteGenerator;
        $this->urlRewriteHandler = $urlRewriteHandler;
        $this->urlRewriteBunchReplacer = $urlRewriteBunchReplacer;
        $this->databaseMapPool = $databaseMapPool;
        $this->dataUrlRewriteClassNames = $dataUrlRewriteClassNames;
    }

    /**
     * Generate urls for UrlRewrite and save it in storage
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var Category $category */
        $category = $observer->getEvent()->getData('category');
        if ($category->getParentId() == Category::TREE_ROOT_ID) {
            return;
        }
        if ($category->dataHasChangedFor('url_key')
            || $category->dataHasChangedFor('is_anchor')
            || $category->getIsChangedProductList()
        ) {
            $categoryUrlRewriteResult = $this->categoryUrlRewriteGenerator->generate($category);
            $this->urlRewriteBunchReplacer->doBunchReplace($categoryUrlRewriteResult);

            $productUrlRewriteResult = $this->urlRewriteHandler->generateProductUrlRewrites($category);
            $this->urlRewriteBunchReplacer->doBunchReplace($productUrlRewriteResult);

            //frees memory for maps that are self-initialized in multiple classes that were called by the generators
            $this->resetUrlRewritesDataMaps($category);
        }
    }

    /**
     * Resets used data maps to free up memory and temporary tables.
     *
     * @param Category $category
     * @return void
     */
    private function resetUrlRewritesDataMaps($category)
    {
        foreach ($this->dataUrlRewriteClassNames as $className) {
            $this->databaseMapPool->resetMap($className, $category->getEntityId());
        }

    }
}
