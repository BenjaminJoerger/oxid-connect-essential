<?php

namespace Makaira\OxidConnectEssential\Controller\Admin;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Doctrine\DBAL\Exception as DBALException;
use Makaira\OxidConnectEssential\Domain\Revision;
use Makaira\OxidConnectEssential\Entity\RevisionRepository;
use Makaira\OxidConnectEssential\SymfonyContainerTrait;

use function array_map;

class CategoryOrderAjax extends CategoryOrderAjax_parent
{
    use PSR12WrapperTrait;
    use SymfonyContainerTrait;

    /**
     * @var bool
     */
    private bool $isRemove = false;

    /**
     * @return void
     */
    public function remNewOrder()
    {
        $this->isRemove = true;
        parent::remNewOrder();
        $this->isRemove = false;
    }

    /**
     * @param string $categoryId
     *
     * @throws DBALDriverException
     * @throws DBALException
     */
    protected function onCategoryChange($categoryId)
    {
        $container          = $this->getSymfonyContainer();
        $db                 = $container->get(Connection::class);
        $revisionRepository = $container->get(RevisionRepository::class);

        if ($this->isRemove) {
            $revisionRepository->touchCategory($categoryId);
        }

        $categoryView = $this->callPSR12Incompatible('_getViewName', 'oxobject2category');
        $productView  = $this->callPSR12Incompatible('_getViewName', 'oxarticles');

        $query = "SELECT `o2c`.`OXOBJECTID`, `a`.`OXPARENTID`
            FROM `{$categoryView}` `o2c`
            LEFT JOIN `{$productView}` `a` ON `a`.`OXID` = `o2c`.`OXOBJECTID`
            WHERE `o2c`.`OXCATNID` = ?";

        $changedProducts = $db->executeQuery($query, [$categoryId])->fetchAllAssociative();

        $revisionRepository->storeRevisions(
            array_map(
                static fn ($changedProduct) => new Revision(
                    $changedProduct['OXPARENTID'] ? Revision::TYPE_VARIANT : Revision::TYPE_PRODUCT,
                    $changedProduct['OXOBJECTID']
                ),
                $changedProducts
            )
        );
    }
}
