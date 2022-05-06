<?php

namespace Makaira\OxidConnectEssential\Test\Unit\RevisionHandler\Extractor;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;
use Makaira\OxidConnectEssential\Domain\Revision;
use Makaira\OxidConnectEssential\RevisionHandler\Extractor\ArticleCategory;
use OxidEsales\Eshop\Application\Model\Manufacturer as OxidManufacturer;
use OxidEsales\Eshop\Application\Model\Object2Category;
use OxidEsales\Eshop\Core\TableViewNameGenerator;
use OxidEsales\TestingLibrary\UnitTestCase;

class ArticleCategoryTest extends UnitTestCase
{
    public function testItSupportsObject2CategoryModel()
    {
        $dataExtractor = new ArticleCategory(
            $this->createMock(Connection::class),
            $this->createMock(TableViewNameGenerator::class)
        );

        $actual = $dataExtractor->supports(new Object2Category());
        $this->assertTrue($actual);
    }

    public function testItDoesNotSupportManufacturerModel()
    {
        $dataExtractor = new ArticleCategory(
            $this->createMock(Connection::class),
            $this->createMock(TableViewNameGenerator::class)
        );

        $actual = $dataExtractor->supports(new OxidManufacturer());
        $this->assertFalse($actual);
    }

    /**
     * @param string $parentId
     * @param string $expectedType
     *
     * @return void
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     * @dataProvider provideTestData
     */
    public function testReturnsRevisionObject(string $parentId, string $expectedType)
    {
        $statementMock = $this->createMock(Statement::class);
        $statementMock
            ->expects($this->once())
            ->method('execute')
            ->with(['phpunit42']);

        $statementMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn($parentId);

        $sql = "SELECT `OXPARENTID` FROM `phpunit_oxarticles_de` WHERE `OXID` = ?";

        $db = $this->createMock(Connection::class);
        $db->expects($this->once())
            ->method('prepare')
            ->with($sql)
            ->willReturn($statementMock);

        $viewNameGenerator = $this->createMock(TableViewNameGenerator::class);
        $viewNameGenerator
            ->expects($this->once())
            ->method('getViewName')
            ->with('oxarticles')
            ->willReturn('phpunit_oxarticles_de');

        $model = $this->createMock(Object2Category::class);
        $model->method('getProductId')->willReturn('phpunit42');

        $articleExtractor = new ArticleCategory($db, $viewNameGenerator);
        $actual = $articleExtractor->extract($model);

        $changed = new DateTimeImmutable();

        foreach ($actual as $revision) {
            $revision->changed = $changed;
        }

        $expected = [
            $expectedType . '-phpunit42' => new Revision($expectedType, 'phpunit42', $changed)
        ];
        $this->assertEqualsCanonicalizing($expected, $actual);
    }

    public function provideTestData()
    {
        return [
            'Testing product' => ['', Revision::TYPE_PRODUCT],
            'Testing variant' => ['phpunit21', Revision::TYPE_VARIANT]
        ];
    }
}
