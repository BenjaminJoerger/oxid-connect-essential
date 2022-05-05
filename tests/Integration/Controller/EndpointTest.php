<?php

namespace Makaira\OxidConnectEssential\Test\Integration\Controller;

use Exception;
use JsonException;
use Makaira\OxidConnectEssential\Controller\Endpoint;
use Makaira\OxidConnectEssential\Repository;
use Makaira\OxidConnectEssential\Test\Integration\IntegrationTestCase;
use OxidEsales\Eshop\Application\Model\Attribute;
use OxidEsales\Eshop\Core\Model\BaseModel;
use OxidEsales\Eshop\Core\Model\MultiLanguageModel;
use ReflectionException;
use Symfony\Component\HttpFoundation\Request;

use function end;
use function json_decode;

use const JSON_THROW_ON_ERROR;

class EndpointTest extends IntegrationTestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        static::setModuleSetting('makaira_connect_secret', parent::SECRET);
    }

    /**
     * @return void
     * @throws JsonException
     */
    public function testResponsesWith403ForInvalidSignature(): void
    {
        $request  = $this->getConnectRequest(['action' => 'listLanguages'], 's3cr3t');
        $endpoint = new Endpoint();
        $response = $endpoint->handleRequest($request);

        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * @return void
     */
    public function testResponsesWith401IfHeadersAreMissing(): void
    {
        $endpoint = new Endpoint();
        $response = $endpoint->handleRequest(new Request());

        $this->assertEquals(401, $response->getStatusCode());
    }

    /**
     * @return void
     * @throws JsonException
     */
    public function testResponsesWith400IfBodyIsNotJson(): void
    {
        $request  = $this->getConnectRequest(
            '<!DOCTYPE html><html lang="en"><head><title>phpunit</title></head><body><h1>phpunit</h1></body></html>',
            static::SECRET,
            false
        );
        $endpoint = new Endpoint();
        $response = $endpoint->handleRequest($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    /**
     * @return void
     * @throws JsonException
     */
    public function testResponsesWith400IfActionIsMissing(): void
    {
        $request  = $this->getConnectRequest([]);
        $endpoint = new Endpoint();
        $response = $endpoint->handleRequest($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    /**
     * @return void
     * @throws JsonException
     */
    public function testResponsesWith404IfActionIsUnknown(): void
    {
        $request  = $this->getConnectRequest(['action' => 'UnknownAction']);
        $endpoint = new Endpoint();
        $response = $endpoint->handleRequest($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * @return void
     * @throws JsonException
     */
    public function testCanGetLanguagesFromShop(): void
    {
        $request  = $this->getConnectRequest(['action' => 'listLanguages']);
        $endpoint = new Endpoint();
        $response = $endpoint->handleRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['de', 'en'], json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * @param string $language
     *
     * @return void
     * @throws JsonException
     * @throws ReflectionException
     * @dataProvider provideLanguages
     */
    public function testFetchChangesFromShop(string $language): void
    {
        $this->prepareProducts();

        $since = 0;
        do {
            $body    = [
                'action'   => 'getUpdates',
                'since'    => $since,
                'count'    => 25,
                'language' => $language,
            ];
            $request = $this->getConnectRequest($body);

            $controller  = new Endpoint();
            $rawResponse = $controller->handleRequest($request);
            $response    = json_decode($rawResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

            if ($response['count'] > 0) {
                $this->assertSnapshot($response, null, true);
                $lastChange = end($response['changes']);
                $since      = $lastChange['sequence'];
            }
        } while ($response['count'] > 0);

        $this->assertGreaterThan(0, $since, sprintf('No changes were returned for language "%s".', $language));
    }

    /**
     * @return array<string, array<string>>
     */
    public function provideLanguages(): array
    {
        return [
            'Changes in german'  => ['de'],
            'Changes in english' => ['en'],
        ];
    }

    /**
     * @return void
     * @throws Exception
     */
    private function prepareProducts(): void
    {
        $testProductId    = '6b63f459c781fa42edeb889242304014';
        $testVariantId    = '6b6c129c62119185c7779987e7d8cd5c';
        $intAttributeId   = md5('phphunit_attribute_int');
        $floatAttributeId = md5('phphunit_attribute_float');

        $intAttribute = new Attribute();
        $intAttribute->assign(
            [
                'oxid'     => $intAttributeId,
                'oxtitle'  => 'PHPUnit integer attribute',
                'oxshopid' => 1,
            ]
        );
        $intAttribute->setLanguage(1);
        $intAttribute->save();
        $intAttribute->setLanguage(2);
        $intAttribute->save();

        $floatAttribute = new Attribute();
        $floatAttribute->assign(
            [
                'oxid'     => $floatAttributeId,
                'oxtitle'  => 'PHPUnit float attribute',
                'oxshopid' => 1,
            ]
        );
        $floatAttribute->setLanguage(1);
        $floatAttribute->save();
        $floatAttribute->setLanguage(2);
        $floatAttribute->save();

        $articleAttributeInt = new MultiLanguageModel();
        $articleAttributeInt->init('oxobject2attribute');
        $articleAttributeInt->assign(
            [
                'oxid'       => md5("{$testProductId}-{$intAttributeId}"),
                'oxobjectid' => $testProductId,
                'oxattrid'   => $intAttributeId,
                'oxvalue'    => '21',
            ]
        );
        $articleAttributeInt->setLanguage(1);
        $articleAttributeInt->save();
        $articleAttributeInt->setLanguage(2);
        $articleAttributeInt->save();

        $articleAttributeFloat = new MultiLanguageModel();
        $articleAttributeFloat->init('oxobject2attribute');
        $articleAttributeFloat->assign(
            [
                'oxid'       => md5("{$testProductId}-{$floatAttributeId}"),
                'oxobjectid' => $testProductId,
                'oxattrid'   => $floatAttributeId,
                'oxvalue'    => '2.1',
            ]
        );
        $articleAttributeFloat->setLanguage(1);
        $articleAttributeFloat->save();
        $articleAttributeFloat->setLanguage(2);
        $articleAttributeFloat->save();

        $articleAttributeInt = new MultiLanguageModel();
        $articleAttributeInt->init('oxobject2attribute');
        $articleAttributeInt->assign(
            [
                'oxid'       => md5("{$testVariantId}-{$intAttributeId}"),
                'oxobjectid' => $testVariantId,
                'oxattrid'   => $intAttributeId,
                'oxvalue'    => '42',
            ]
        );
        $articleAttributeInt->setLanguage(1);
        $articleAttributeInt->save();
        $articleAttributeInt->setLanguage(2);
        $articleAttributeInt->save();

        $articleAttributeFloat = new MultiLanguageModel();
        $articleAttributeFloat->init('oxobject2attribute');
        $articleAttributeFloat->assign(
            [
                'oxid'       => md5("{$testVariantId}-{$floatAttributeId}"),
                'oxobjectid' => $testVariantId,
                'oxattrid'   => $floatAttributeId,
                'oxvalue'    => '4.2',
            ]
        );
        $articleAttributeFloat->setLanguage(1);
        $articleAttributeFloat->save();
        $articleAttributeFloat->setLanguage(2);
        $articleAttributeFloat->save();

        self::setModuleSetting('makaira_attribute_as_int', [$intAttributeId]);
        self::setModuleSetting('makaira_attribute_as_float', [$floatAttributeId]);

        /** @var Repository $repo */
        $repo = static::getContainer()->get(Repository::class);
        $repo->touchAll();
    }
}
