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
use TYPO3\Flow\Persistence\QueryInterface;
use TYPO3\Flow\Persistence\RepositoryInterface;
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
	 * @var string
	 */
	protected $domain;

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
	 * @var \TYPO3\Flow\Persistence\PersistenceManagerInterface
	 */
	protected $persistenceManager;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Object\ObjectManagerInterface
	 */
	protected $objectManager;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Property\PropertyMapper
	 */
	protected $propertyMapper;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Validation\ValidatorResolver
	 */
	protected $validatorResolver;

	/**
	 * Inverse array of SpreadsheetDomain mapping array property. Always use the getter function, which will process the
	 * property in case it is not set.
	 *
	 * @var array
	 */
	private $inverseSpreadsheetImportMapping;

	/**
	 * Identifier properties of SpreadsheetDomain mapping array. Always use the getter function, which will process the
	 * property in case it is not set.
	 *
	 * @var array
	 */
	private $mappingIdentifierProperties;

	/**
	 * @param \WE\SpreadsheetImport\Domain\Model\SpreadsheetImport $spreadsheetImport
	 *
	 * @return $this
	 */
	public function init(SpreadsheetImport $spreadsheetImport) {
		$this->inverseSpreadsheetImportMapping = array();
		$this->mappingIdentifierProperties = array();
		$this->spreadsheetImport = $spreadsheetImport;
		$this->domain = $this->settings[$spreadsheetImport->getContext()]['domain'];

		return $this;
	}

	/**
	 * @return array
	 */
	public function getAnnotationMappingProperties() {
		$mappingPropertyAnnotations = array();
		$properties = $this->reflectionService->getPropertyNamesByAnnotation($this->domain, Mapping::class);
		foreach ($properties as $property) {
			$mappingPropertyAnnotations[$property] = $this->reflectionService->getPropertyAnnotation($this->domain, $property, Mapping::class);
		}
		return $mappingPropertyAnnotations;
	}

	/**
	 * @return array
	 */
	public function getTotalRecords() {
		$sheet = $this->getFileActiveSheet();
		$highestColumn = $sheet->getHighestDataRow();

		return $highestColumn - 1;
	}

	/**
	 * @return array
	 */
	public function getSpreadsheetColumns() {
		$columns = array();
		$sheet = $this->getFileActiveSheet();
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
		$domain = $this->domain;
		$newObject = new $domain;
		$sheet = $this->getFileActiveSheet();
		// Plus one to skip the headings
		$row = $sheet->getRowIterator($number + 1, $number + 1)->current();
		$this->setObjectPropertiesByRow($newObject, $row);
		return $newObject;
	}

	/**
	 * @return void
	 */
	public function import() {
		$totalInserted = 0;
		$totalUpdated = 0;
		$totalDeleted = 0;
		$processedObjectIds = array();
		$objectRepository = $this->getDomainRepository();
		$objectValidator = $this->validatorResolver->getBaseValidatorConjunction($this->domain);
		$sheet = $this->getFileActiveSheet();
		$persistRecordsChunkSize = intval($this->settings['persistRecordsChunkSize']);
		$totalCount = 0;
		/** @var \PHPExcel_Worksheet_Row $row */
		foreach ($sheet->getRowIterator(2) as $row) {
			$totalCount++;
			$object = $this->findObjectByIdentifierPropertiesPerRow($row);
			if (is_object($object)) {
				$processedObjectIds[] = $this->persistenceManager->getIdentifierByObject($object);
				if ($this->spreadsheetImport->isUpdating()) {
					$this->setObjectPropertiesByRow($object, $row);
					$validationResult = $objectValidator->validate($object);
					if ($validationResult->hasErrors()) {
						continue;
					}
					$objectRepository->update($object);
					$totalUpdated++;
				} else {
					continue;
				}
			} elseif ($this->spreadsheetImport->isInserting()) {
				$newObject = new $this->domain;
				$this->setObjectPropertiesByRow($newObject, $row);
				$validationResult = $objectValidator->validate($newObject);
				if ($validationResult->hasErrors()) {
					continue;
				}
				$objectRepository->add($newObject);
				$processedObjectIds[] = $this->persistenceManager->getIdentifierByObject($newObject);
				$totalInserted++;
			} else {
				continue;
			}
			if ($totalCount % $persistRecordsChunkSize === 0) {
				$this->persistenceManager->persistAll();
			}
		}
		$deleteCount = 0;
		if ($this->spreadsheetImport->isDeleting()) {
			$notExistingObjects = $this->findObjectsByExcludedIds($processedObjectIds);
			foreach ($notExistingObjects as $object) {
				$objectRepository->remove($object);
				if (++$deleteCount % $persistRecordsChunkSize === 0) {
					$this->persistenceManager->persistAll();
				}
			}
		}
		$this->persistenceManager->persistAll();
		$this->spreadsheetImport->setTotalInserted($totalInserted);
		$this->spreadsheetImport->setTotalUpdated($totalUpdated);
		$this->spreadsheetImport->setTotalSkipped($totalCount - $totalInserted - $totalUpdated);
		$this->spreadsheetImport->setTotalDeleted($totalDeleted);
	}

	/**
	 * @return \PHPExcel_Worksheet
	 */
	private function getFileActiveSheet() {
		$file = $this->spreadsheetImport->getFile()->createTemporaryLocalCopy();
		$reader = \PHPExcel_IOFactory::load($file);
		$sheet = $reader->getActiveSheet();
		return $sheet;
	}

	/**
	 * @return \TYPO3\Flow\Persistence\RepositoryInterface
	 */
	private function getDomainRepository() {
		$repositoryClassName = preg_replace(array('/\\\Model\\\/', '/$/'), array('\\Repository\\', 'Repository'), $this->domain);
		/** @var RepositoryInterface $repository */
		$repository = $this->objectManager->get($repositoryClassName);
		return $repository;
	}

	/**
	 * @return array
	 */
	private function getMappingIdentifierProperties() {
		if (empty($this->mappingIdentifierProperties)) {
			foreach ($this->spreadsheetImport->getMapping() as $property => $columnMapping) {
				/** @var Mapping $mapping */
				$mapping = $columnMapping['mapping'];
				if ($mapping->identifier) {
					$this->mappingIdentifierProperties[$property] = $columnMapping;
				}
			}
		}
		return $this->mappingIdentifierProperties;
	}

	/**
	 * @param \PHPExcel_Worksheet_Row $row
	 *
	 * @return null|object
	 */
	private function findObjectByIdentifierPropertiesPerRow(\PHPExcel_Worksheet_Row $row) {
		$query = $this->getDomainRepository()->createQuery();
		$constraints = array();
		$identifierProperties = $this->getMappingIdentifierProperties();
		foreach ($identifierProperties as $property => $columnMapping) {
			$column = $columnMapping['column'];
			/** @var Mapping $mapping */
			$mapping = $columnMapping['mapping'];
			$propertyName = $mapping->queryPropertyName ?: $property;
			/** @var \PHPExcel_Worksheet_RowCellIterator $cellIterator */
			$cellIterator = $row->getCellIterator($column, $column);
			$value = $cellIterator->current()->getValue();
			$constraints[] = $query->equals($propertyName, $value);
		}
		$this->mergeQueryConstraintsWithArgumentIdentifiers($query, $constraints);
		if (!empty($constraints)) {
			return $query->matching($query->logicalAnd($constraints))->execute()->getFirst();
		} else {
			return NULL;
		}
	}

	/**
	 * @param \TYPO3\Flow\Persistence\QueryInterface $query
	 * @param array $constraints
	 */
	private function mergeQueryConstraintsWithArgumentIdentifiers(QueryInterface $query, &$constraints) {
		$contextArguments = $this->settings[$this->spreadsheetImport->getContext()]['arguments'];
		if (is_array($contextArguments)) {
			foreach ($contextArguments as $contextArgument) {
				if (isset($contextArgument['identifier']) && $contextArgument['identifier'] == TRUE) {
					$name = $contextArgument['name'];
					$arguments = $this->spreadsheetImport->getArguments();
					if (array_key_exists($name, $arguments)) {
						$value = $arguments[$name];
						$constraints[] = $query->equals($name, $value);
					}
				}
			}
		}
	}

	/**
	 * @param array $identifiers
	 *
	 * @return \TYPO3\Flow\Persistence\QueryResultInterface
	 */
	private function findObjectsByExcludedIds(array $identifiers) {
		$query = $this->getDomainRepository()->createQuery();
		$constraints[] = $query->logicalNot($query->in('Persistence_Object_Identifier', $identifiers));
		$this->mergeQueryConstraintsWithArguments($query, $constraints);
		return $query->matching($query->logicalAnd($constraints))->execute();
	}

	/**
	 * @param \TYPO3\Flow\Persistence\QueryInterface $query
	 * @param array $constraints
	 */
	private function mergeQueryConstraintsWithArguments(QueryInterface $query, &$constraints) {
		$contextArguments = $this->settings[$this->spreadsheetImport->getContext()]['arguments'];
		if (is_array($contextArguments)) {
			foreach ($contextArguments as $contextArgument) {
				$name = $contextArgument['name'];
				$arguments = $this->spreadsheetImport->getArguments();
				if (array_key_exists($name, $arguments)) {
					$value = $arguments[$name];
					$constraints[] = $query->equals($name, $value);
				}
			}
		}
	}

	/**
	 * @param object $object
	 * @param \PHPExcel_Worksheet_Row $row
	 */
	private function setObjectPropertiesByRow($object, $row) {
		/* Set the argument properties before the mapping properties, as mapping property setters might be dependent on
		argument property values */
		$this->setObjectArgumentProperties($object);
		$this->setObjectSpreadsheetImportMappingProperties($object, $row);
	}

	/**
	 * @param $object
	 */
	private function setObjectArgumentProperties($object) {
		$contextArguments = $this->settings[$this->spreadsheetImport->getContext()]['arguments'];
		if (is_array($contextArguments)) {
			$arguments = $this->spreadsheetImport->getArguments();
			foreach ($contextArguments as $contextArgument) {
				$name = $contextArgument['name'];
				if (array_key_exists($name, $arguments)) {
					if (array_key_exists('domain', $contextArgument)) {
						$value = $this->propertyMapper->convert($arguments[$name], $contextArgument['domain']);
					} else {
						$value = $arguments[$name];
					}
					$setter = 'set' . ucfirst($name);
					$object->$setter($value);
				}
			}
		}
	}

	/**
	 * @param object $object
	 * @param \PHPExcel_Worksheet_Row $row
	 */
	private function setObjectSpreadsheetImportMappingProperties($object, $row) {
		$inverseSpreadsheetImportMapping = $this->getInverseSpreadsheetImportMapping();
		/** @var \PHPExcel_Cell $cell */
		foreach ($row->getCellIterator() as $cell) {
			$column = $cell->getColumn();
			if (array_key_exists($column, $inverseSpreadsheetImportMapping)) {
				$properties = $inverseSpreadsheetImportMapping[$column];
				foreach ($properties as $propertyMapping) {
					$property = $propertyMapping['property'];
					/** @var Mapping $mapping */
					$mapping = $propertyMapping['mapping'];
					$setter = empty($mapping->setter) ? 'set' . ucfirst($property) : $mapping->setter;
					$object->$setter($cell->getValue());
				}
			}
		}
	}

	/**
	 * Return an inverse SpreadsheetImport mapping array. It flips the property and column attribute and returns it as a
	 * 3-dim array instead of a 2-dim array. This is done for the case when the same column is assigned to multiple
	 * properties.
	 */
	private function getInverseSpreadsheetImportMapping() {
		if (empty($this->inverseSpreadsheetImportMapping)) {
			$this->inverseSpreadsheetImportMapping = array();
			foreach ($this->spreadsheetImport->getMapping() as $property => $columnMapping) {
				$column = $columnMapping['column'];
				$propertyMapping = array('property' => $property, 'mapping' => $columnMapping['mapping']);
				$this->inverseSpreadsheetImportMapping[$column][] = $propertyMapping;
			}
		}
		return $this->inverseSpreadsheetImportMapping;
	}
}
