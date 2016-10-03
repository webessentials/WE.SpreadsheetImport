<?php
namespace WE\SpreadsheetImport;

/*                                                                        *
 * This script belongs to the Flow package "SpreadsheetImport".           *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use WE\SpreadsheetImport\Annotations\Mapping;
use WE\SpreadsheetImport\Domain\Model\SpreadsheetImport;

/**
 * @Flow\Scope("singleton")
 */
class SpreadsheetImportService {
	/**
	 * @var SpreadsheetImport
	 */
	protected $spreadsheetImport;

	/**
	 * @var array
	 */
	protected $context;

	/**
	 * @Flow\InjectConfiguration
	 * @var array
	 */
	protected $settings;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Reflection\ReflectionService
	 */
	protected $reflectionService;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Resource\ResourceManager
	 */
	protected $resourceManager;

	/**
	 * @param \WE\SpreadsheetImport\Domain\Model\SpreadsheetImport $spreadsheetImport
	 *
	 * @return $this
	 */
	public function init(SpreadsheetImport $spreadsheetImport) {
		$this->spreadsheetImport = $spreadsheetImport;
		$this->context = $this->settings[$spreadsheetImport->getContext()];
		return $this;
	}

	/**
	 * @return array
	 */
	public function getDomainMappingProperties() {
		$domainMappingProperties = array();
		$properties = $this->reflectionService->getPropertyNamesByAnnotation($this->context['domain'], Mapping::class);
		foreach ($properties as $property) {
			$domainMappingProperties[$property] = $this->reflectionService->getPropertyAnnotation($this->context['domain'], $property, Mapping::class);
		}
		return $domainMappingProperties;
	}

	/**
	 * @return array
	 */
	public function getSpreadsheetColumns() {
		$columns = array();
		$file = $this->spreadsheetImport->getFile()->createTemporaryLocalCopy();
		$reader = \PHPExcel_IOFactory::load($file);
		$sheet = $reader->getActiveSheet();
		$highestColumn = $sheet->getHighestDataColumn();
		$row = $sheet->getRowIterator(1, 1)->current();
		$cellIterator = $row->getCellIterator();
		$cellIterator->setIterateOnlyExistingCells(FALSE);
		/** @var \PHPExcel_Cell $cell */
		foreach ($cellIterator as $cell) {
			$columns[$cell->getColumn()] = $cell->getValue();
			if ($cell->getColumn() === $highestColumn) {
				break;
			}
		}
		return $columns;
	}

	/**
	 * @param int $number
	 *
	 * @return object
	 */
	public function getObjectByRow($number) {
		$domain = $this->context['domain'];
		$newObject = new $domain;
		// Plus one to skip the headings
		$file = $this->spreadsheetImport->getFile()->createTemporaryLocalCopy();
		$reader = \PHPExcel_IOFactory::load($file);
		$row = $reader->getActiveSheet()->getRowIterator($number + 1, $number + 1)->current();
		$this->setObjectPropertiesByRow($newObject, $row);
		return $newObject;
	}

	/**
	 * @return array
	 */
	public function import() {
		// TODO: This simply creates the objects for now without update or delete
		$objects = array();
		$domain = $this->context['domain'];
		$file = $this->spreadsheetImport->getFile()->createTemporaryLocalCopy();
		$reader = \PHPExcel_IOFactory::load($file);
		/** @var \PHPExcel_Worksheet_Row $row */
		foreach ($reader->getActiveSheet()->getRowIterator(2) as $row) {
			$newObject = new $domain;
			$this->setObjectPropertiesByRow($newObject, $row);
			$objects[] = $newObject;
		}
		return $objects;
	}

	/**
	 * @param object $newObject
	 * @param \PHPExcel_Worksheet_Row $row
	 */
	private function setObjectPropertiesByRow($newObject, $row) {
		// TODO: Cache $domainMappingProperties and $mappings
		$domainMappingProperties = $this->getDomainMappingProperties();
		$mappings = $this->spreadsheetImport->getMapping();
		/** @var \PHPExcel_Cell $cell */
		foreach ($row->getCellIterator() as $cell) {
			$column = $cell->getColumn();
			if (array_key_exists($column, $mappings)) {
				$property = $mappings[$column];
				/** @var Mapping $mapping */
				$mapping = $domainMappingProperties[$property];
				$setter = empty($mapping->setter) ? 'set' . ucfirst($property) : $mapping->setter;
				$newObject->$setter($cell->getValue());
			}
		}
	}
}
