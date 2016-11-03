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
use WE\SpreadsheetImport\Tests\Functional\Fixtures\Domain\Model\ImportTarget;
use WE\SpreadsheetImport\Tests\Functional\Fixtures\Domain\Model\ImportTargetCategory;

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
	}

	/**
	 * @return void
	 */
	public function tearDown() {
		$persistenceManager = $this->objectManager->get('TYPO3\Flow\Persistence\PersistenceManagerInterface');
		if (is_callable(array($persistenceManager, 'tearDown'))) {
			$persistenceManager->tearDown();
		}
		$this->objectManager->forgetInstance('TYPO3\Flow\Persistence\PersistenceManagerInterface');
		parent::tearDown();
	}

	private function initializeSpreadsheetMock() {
		$spreadsheetImport = new SpreadsheetImport();
		$spreadsheetImport->setContext('testing');
		$resource = $this->resourceManager->importResource(__DIR__ . '/Fixtures/Resources/sample.xlsx');
		$spreadsheetImport->setFile($resource);
		$this->spreadsheetImportService->init($spreadsheetImport);

		return $spreadsheetImport;
	}

	private function initializeConfiguredSpreadsheetMock() {
		$spreadsheetImport = $this->initializeSpreadsheetMock();
		$annotationMappings = $this->spreadsheetImportService->getAnnotationMappingProperties();
		$spreadsheetImport->setMapping(array(
			'id' => array('column' => 'C', 'mapping' => $annotationMappings['id']),
			'firstName' => array('column' => 'A', 'mapping' => $annotationMappings['firstName']),
			'lastName' => array('column' => 'B', 'mapping' => $annotationMappings['lastName']),
			'account' => array('column' => 'C', 'mapping' => $annotationMappings['account'])));
		$spreadsheetImport->setArguments(array(
			'category' => new ImportTargetCategory(), // Could also simply assign the UUID
			'comment' => 'Sample import'
		));
	}

	/**
	 * @test
	 */
	public function getAnnotationMappingPropertiesReturnsArrayMappingAnnotation() {
		$this->initializeSpreadsheetMock();
		$properties = $this->spreadsheetImportService->getAnnotationMappingProperties();
		$this->assertArrayHasKey('id', $properties);
		$this->assertArrayHasKey('firstName', $properties);
		/** @var Mapping $id */
		$id = $properties['id'];
		/** @var Mapping $firstName */
		$firstName = $properties['firstName'];
		$this->assertSame(TRUE, $id->identifier);
		$this->assertSame(FALSE, $firstName->identifier);
	}

	/**
	 * @test
	 */
	public function getTotalRecordsRecordsNumberOfObjectsToImport() {
		$this->initializeSpreadsheetMock();
		$this->assertSame(2, $this->spreadsheetImportService->getTotalRecords());
	}

	/**
	 * @test
	 */
	public function getSpreadsheetColumnsReturnsColumnsWithHeadings() {
		$this->initializeSpreadsheetMock();
		$columns = $this->spreadsheetImportService->getSpreadsheetColumns();
		$this->assertArrayHasKey('A', $columns);
		$this->assertArrayHasKey('B', $columns);
		$this->assertArrayHasKey('C', $columns);
		$this->assertSame('firstname', $columns['A']);
		$this->assertSame('name', $columns['B']);
		$this->assertSame('id', $columns['C']);
	}

	/**
	 * @test
	 */
	public function getObjectByRowOneReturnsImportTargetWithSetProperties() {
		$this->initializeConfiguredSpreadsheetMock();
		/** @var ImportTarget $object */
		$object = $this->spreadsheetImportService->getObjectByRow(1);
		$this->assertSame('00001', $object->getId());
		$this->assertSame('Hans', $object->getFirstName());
		$this->assertSame('Muster', $object->getLastName());
		$this->assertSame('001', $object->getAccount());
		$this->assertInstanceOf(ImportTargetCategory::class, $object->getCategory());
		$this->assertSame('Sample import', $object->getComment());
	}
}
