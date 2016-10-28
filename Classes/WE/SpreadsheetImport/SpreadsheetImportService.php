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
	 * @var array
	 */
	protected $mappingProperties;

	/**
	 * @var array
	 */
	protected $columnPropertyMapping;

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
	 * @param \WE\SpreadsheetImport\Domain\Model\SpreadsheetImport $spreadsheetImport
	 *
	 * @return $this
	 */
	public function init(SpreadsheetImport $spreadsheetImport) {
		$this->spreadsheetImport = $spreadsheetImport;
		$this->domain = $this->settings[$spreadsheetImport->getContext()]['domain'];
		$this->initDomainMappingProperties();
		$this->initColumnPropertyMapping();

		return $this;
	}

	/**
	 * Initializes the properties declared by annotations.
	 */
	private function initDomainMappingProperties() {
		$this->mappingProperties = array();
		$properties = $this->reflectionService->getPropertyNamesByAnnotation($this->domain, Mapping::class);
		foreach ($properties as $property) {
			$this->mappingProperties[$property] = $this->reflectionService->getPropertyAnnotation($this->domain, $property, Mapping::class);
		}
	}

	/**
	 * Flip mapping and return it as a 2-dim array in case the same column is assigned to multiple properties
	 */
	private function initColumnPropertyMapping() {
		$this->columnPropertyMapping = array();
		foreach ($this->spreadsheetImport->getMapping() as $property => $column) {
			$this->columnPropertyMapping[$column][] = $property;
		}
	}

	/**
	 * Adds additional mapping properties to the domain mapping properties retrieved by annotations. This increases
	 * flexibility for dynamic property mapping.
	 *
	 * This was implemented for the single use case to support the Flow package Radmiraal.CouchDB
	 *
	 * Note: Those additional property configurations are not persisted and need to be added after each initialization
	 * of the service. The persisted mappings in the SpreadsheetImport object only contain the property without any
	 * configuration. Therefore, the import works but only for the default setters and without identifiers. To support
	 * all, the additional mapping properties need to be persisted together with the mappings.
	 *
	 * @param array $additionalMappingProperties
	 */
	public function addAdditionalMappingProperties(array $additionalMappingProperties) {
		$this->mappingProperties = array_merge($this->mappingProperties, $additionalMappingProperties);
	}

	/**
	 * @return array
	 */
	public function getMappingProperties() {
		return $this->mappingProperties;
	}

	/**
	 * @param string $context
	 *
	 * @return array
	 */
	public function getArgumentsByContext($context) {
		return $this->settings[$context]['arguments'];
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
		$domain = $this->domain;
		$newObject = new $domain;
		// Plus one to skip the headings
		$file = $this->spreadsheetImport->getFile()->createTemporaryLocalCopy();
		$reader = \PHPExcel_IOFactory::load($file);
		$row = $reader->getActiveSheet()->getRowIterator($number + 1, $number + 1)->current();
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
		$totalSkipped = 0;
		$objectIds = array();
		$domain = $this->domain;
		$objectRepository = $this->getDomainRepository();
		$objectValidator = $this->validatorResolver->getBaseValidatorConjunction($domain);
		$identifierProperties = $this->getDomainMappingIdentifierProperties();
		$file = $this->spreadsheetImport->getFile()->createTemporaryLocalCopy();
		$reader = \PHPExcel_IOFactory::load($file);
		$numberOfRecordsToPersist = $this->settings['numberOfRecordsToPersist'];
		$i = 0;
		/** @var \PHPExcel_Worksheet_Row $row */
		foreach ($reader->getActiveSheet()->getRowIterator(2) as $row) {
			$object = $this->findObjectByIdentifierPropertiesPerRow($identifierProperties, $row);
			if (is_object($object)) {
				$objectIds[] = $this->persistenceManager->getIdentifierByObject($object);
				if ($this->spreadsheetImport->isUpdating()) {
					$this->setObjectPropertiesByRow($object, $row);
					$validationResult = $objectValidator->validate($object);
					if ($validationResult->hasErrors()) {
						$totalSkipped++;
						continue;
					}
					$objectRepository->update($object);
					$totalUpdated++;
					$i++;
				} else {
					$totalSkipped++;
					continue;
				}
			} elseif ($this->spreadsheetImport->isInserting()) {
				$newObject = new $domain;
				$this->setObjectPropertiesByRow($newObject, $row);
				$validationResult = $objectValidator->validate($newObject);
				if ($validationResult->hasErrors()) {
					$totalSkipped++;
					continue;
				}
				$objectRepository->add($newObject);
				$objectIds[] = $this->persistenceManager->getIdentifierByObject($newObject);
				$totalInserted++;
				$i++;
			} else {
				$totalSkipped++;
				continue;
			}
			if ($i >= $numberOfRecordsToPersist) {
				$this->persistenceManager->persistAll();
				$i = 0;
			}
		}

		// remove objects which are not exist on the spreadsheet
		if ($this->spreadsheetImport->isDeleting()) {
			$notExistingObjects = $this->findObjectsByExcludedIds($objectIds);
			foreach ($notExistingObjects as $object) {
				$objectRepository->remove($object);
				$totalDeleted++;
				$i++;
				if ($i >= $numberOfRecordsToPersist) {
					$this->persistenceManager->persistAll();
					$i = 0;
				}
			}
		}

		$this->spreadsheetImport->setTotalInserted($totalInserted);
		$this->spreadsheetImport->setTotalUpdated($totalUpdated);
		$this->spreadsheetImport->setTotalDeleted($totalDeleted);
		$this->spreadsheetImport->setTotalSkipped($totalSkipped);
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
	private function getDomainMappingIdentifierProperties() {
		$domainMappingProperties = array();
		$properties = $this->reflectionService->getPropertyNamesByAnnotation($this->domain, Mapping::class);
		foreach ($properties as $property) {
			/** @var Mapping $mapping */
			$mapping = $this->reflectionService->getPropertyAnnotation($this->domain, $property, Mapping::class);
			if ($mapping->identifier) {
				$domainMappingProperties[$property] = $mapping;
			}
		}
		return $domainMappingProperties;
	}

	/**
	 * @param array $identifierProperties
	 * @param \PHPExcel_Worksheet_Row $row
	 *
	 * @return null|object
	 */
	private function findObjectByIdentifierPropertiesPerRow(array $identifierProperties, \PHPExcel_Worksheet_Row $row) {
		$query = $this->getDomainRepository()->createQuery();
		$constraints = array();
		$spreadsheetImportMapping = $this->spreadsheetImport->getMapping();
		/** @var Mapping $mapping */
		foreach ($identifierProperties as $property => $mapping) {
			$column = $spreadsheetImportMapping[$property];
			/** @var \PHPExcel_Worksheet_RowCellIterator $cellIterator */
			$cellIterator = $row->getCellIterator($column, $column);
			$value = $cellIterator->current()->getValue();
			$propertyName = $mapping->queryPropertyName ?: $property;
			$constraints[] = $query->equals($propertyName, $value);
		}
		if (!empty($constraints)) {
			return $query->matching($query->logicalAnd($constraints))->execute()->getFirst();
		} else {
			return NULL;
		}
	}

	/**
	 * @param array $identifiers
	 *
	 * @return \TYPO3\Flow\Persistence\QueryResultInterface
	 */
	private function findObjectsByExcludedIds(array $identifiers) {
		$query = $this->getDomainRepository()->createQuery();
		$constraint = $query->logicalNot($query->in('Persistence_Object_Identifier', $identifiers));
		return $query->matching($constraint)->execute();
	}

	/**
	 * @param object $object
	 * @param \PHPExcel_Worksheet_Row $row
	 */
	private function setObjectPropertiesByRow($object, $row) {
		$domainMappingProperties = $this->mappingProperties;
		/** @var \PHPExcel_Cell $cell */
		foreach ($row->getCellIterator() as $cell) {
			$column = $cell->getColumn();
			if (array_key_exists($column, $this->columnPropertyMapping)) {
				$properties = $this->columnPropertyMapping[$column];
				foreach ($properties as $property) {
					if (array_key_exists($property, $domainMappingProperties)) {
						/** @var Mapping $mapping */
						$mapping = $domainMappingProperties[$property];
						$setter = empty($mapping->setter) ? 'set' . ucfirst($property) : $mapping->setter;
					} else {
						$setter = 'set' . ucfirst($property);
					}
					$object->$setter($cell->getValue());
				}
			}
		}
		$this->setObjectArgumentProperties($object);
	}

	/**
	 * @param $object
	 */
	private function setObjectArgumentProperties($object) {
		$contextArguments = $this->getArgumentsByContext($this->spreadsheetImport->getContext());
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
				} else {
					$value = array_key_exists('default', $contextArgument) ? $contextArgument['default'] : NULL;
				}
				$setter = 'set' . ucfirst($name);
				$object->$setter($value);
			}
		}
	}
}
