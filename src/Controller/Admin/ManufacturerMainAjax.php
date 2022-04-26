<?php

namespace Makaira\OxidConnectEssential\Controller\Admin;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\ParameterType;
use Makaira\OxidConnectEssential\Domain\Revision;
use Makaira\OxidConnectEssential\Entity\RevisionRepository;
use Makaira\OxidConnectEssential\SymfonyContainerTrait;
use OxidEsales\Eshop\Core\Registry;

use function array_map;
use function implode;

class ManufacturerMainAjax extends ManufacturerMainAjax_parent
{
    use PSR12WrapperTrait;
    use SymfonyContainerTrait;

    /**
     * @return void
     * @throws ConnectionException
     * @throws DBALDriverException
     * @throws DBALException
     */
    public function addManufacturer()
    {
        $productIds     = $this->callPSR12Incompatible('_getActionIds', 'oxarticles.oxid');
        $manufacturerId = Registry::getRequest()->getRequestParameter('synchoxid');

        if (Registry::getRequest()->getRequestParameter('all')) {
            $productView = $this->callPSR12Incompatible('_getViewName', 'oxarticles');
            $changedIds  = $this->callPSR12Incompatible(
                '_getAll',
                $this->callPSR12Incompatible(
                    '_addFilter',
                    "SELECT {$productView}.OXID, {$productView}.OXPARENTID " . $this->callPSR12Incompatible('_getQuery')
                )
            );
        } else {
            $changedIds = $this->addParentIds($productIds);
        }

        parent::addManufacturer();

        $this->executeTouches($changedIds, $manufacturerId);
    }

    /**
     * @param array $productIds
     *
     * @return array<string>
     * @throws DBALDriverException
     * @throws DBALException
     */
    private function addParentIds(array $productIds): array
    {
        $db          = $this->getSymfonyContainer()->get(Connection::class);
        $productView = $this->callPSR12Incompatible('_getViewName', 'oxarticles');

        $sqlProductIds = implode(
            ',',
            array_map(static fn($id) => $db->quote($id, ParameterType::STRING), $productIds)
        );

        $query = "SELECT a.OXID, a.OXPARENTID FROM {$productView} a WHERE a.OXID IN ($sqlProductIds)";

        return $db->executeQuery($query)->fetchAllAssociative();
    }

    /**
     * @param array  $productIds
     * @param string $manufacturerId
     *
     * @return void
     * @throws ConnectionException
     */
    private function executeTouches(array $productIds, string $manufacturerId): void
    {
        $container          = $this->getSymfonyContainer();
        $revisionRepository = $container->get(RevisionRepository::class);

        if (!empty($productIds)) {
            $revisionRepository->storeRevisions(
                array_map(
                    static fn($changedProduct) => new Revision(
                        $changedProduct['OXPARENTID'] ? Revision::TYPE_VARIANT : Revision::TYPE_PRODUCT,
                        $changedProduct['OXID']
                    ),
                    $productIds
                )
            );
        }

        $revisionRepository->touchManufacturer($manufacturerId);
    }

    /**
     * @return void
     * @throws ConnectionException
     * @throws DBALDriverException
     * @throws DBALException
     */
    public function removeManufacturer()
    {
        $productIds     = $this->callPSR12Incompatible('_getActionIds', 'oxarticles.oxid');
        $manufacturerId = Registry::getRequest()->getRequestParameter('oxid');
        $db             = $this->getSymfonyContainer()->get(Connection::class);
        $productView    = $this->callPSR12Incompatible('_getViewName', 'oxarticles');

        if (Registry::getRequest()->getRequestParameter('all')) {
            $oxidQuery  = $this->callPSR12Incompatible('_getQuery');
            $query      = "SELECT {$productView}.OXID, {$productView}.OXPARENTID {$oxidQuery}";
            $changedIds = $db->executeQuery($query)->fetchAllAssociative();
        } else {
            $changedIds = $this->addParentIds($productIds);
        }

        parent::removeManufacturer();

        $this->executeTouches($changedIds, $manufacturerId);
    }
}
