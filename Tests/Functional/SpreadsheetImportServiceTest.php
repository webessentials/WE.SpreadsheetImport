<?php
namespace WE\SpreadsheetImport\Tests\Functional;

/*                                                                        *
 * This script belongs to the Flow package "SpreadsheetImport".           *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Reflection\ReflectionService;
use TYPO3\Flow\Resource\ResourceManager;
use TYPO3\Flow\Tests\FunctionalTestCase;
use WE\SpreadsheetImport\Annotations\Mapping;
use WE\SpreadsheetImport\Domain\Model\SpreadsheetImport;
use WE\SpreadsheetImport\SpreadsheetImportService;
use WE\SpreadsheetImport\Tests\Functional\Fixtures\ImportTarget;

class SpreadsheetImportServiceTest extends FunctionalTestCase {

	/**
	 * @var SpreadsheetImportService
	 */
	protected $spreadsheetImportService;

	/**
	 * @var \TYPO3\Flow\Resource\ResourceManager
	 */
	protected $resourceManager;

	/**
	 * @return void
	 */
	public function setUp() {
		parent::setUp();
		$this->spreadsheetImportService = $this->objectManager->get(SpreadsheetImportService::class);
		$this->resourceManager = $this->objectManager->get(ResourceManager::class);

		$reflectionService = $this->objectManager->get(ReflectionService::class);
		$this->inject($this->spreadsheetImportService, 'reflectionService', $reflectionService);

		$this->initializeSpreadsheetMock();
	}

	/**
	 * @return void
	 */
	public function tearDown() {
		$persistenceManager = self::$bootstrap->getObjectManager()->get('TYPO3\Flow\Persistence\PersistenceManagerInterface');
		if (is_callable(array($persistenceManager, 'tearDown'))) {
			$persistenceManager->tearDown();
		}
		self::$bootstrap->getObjectManager()->forgetInstance('TYPO3\Flow\Persistence\PersistenceManagerInterface');
		parent::tearDown();
	}

	private function initializeSpreadsheetMock() {
		$spreadsheetImport = new SpreadsheetImport();
		$spreadsheetImport->setContext('testing');
		$resource = $this->resourceManager->importResource(__DIR__ . '/Fixtures/Resources/sample.xlsx');
		$spreadsheetImport->setFile($resource);
		$idMapping = array('column' => 'C', 'mapping' => new Mapping());
		$nameMapping = array('column' => 'A', 'mapping' => new Mapping());
		$spreadsheetImport->setMapping(array('id' => $idMapping, 'name' => $nameMapping));
		$this->spreadsheetImportService->init($spreadsheetImport);
	}

	/**
	 * @test
	 */
	public function getMappingPropertiesReturnsPropertiesWithMappingAnnotation() {
		$properties = $this->spreadsheetImportService->getAnnotationMappingProperties();
		$this->assertArrayHasKey('id', $properties);
		$this->assertArrayHasKey('name', $properties);
		/** @var Mapping $id */
		$id = $properties['id'];
		/** @var Mapping $name */
		$name = $properties['name'];
		$this->assertSame(TRUE, $id->identifier);
		$this->assertSame(FALSE, $name->identifier);
	}

	/**
	 * @test
	 */
	public function getSpreadsheetColumnsReturnsColumnsWithHeadings() {
		$columns = $this->spreadsheetImportService->getSpreadsheetColumns();
		$this->assertArrayHasKey('A', $columns);
		$this->assertArrayHasKey('B', $columns);
		$this->assertArrayHasKey('C', $columns);
		$this->assertContains('name', $columns);
		$this->assertContains('lastname', $columns);
		$this->assertContains('id', $columns);
	}

	/**
	 * @test
	 */
	public function getObjectByRowOneReturnsImportTargetWithSetProperties() {
		/** @var ImportTarget $object */
		$object = $this->spreadsheetImportService->getObjectByRow(1);
		$this->assertEquals('00001', $object->getId());
		$this->assertEquals('Hans', $object->getName());
	}
}
