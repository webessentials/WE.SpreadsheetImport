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
use TYPO3\Flow\Mvc\ActionRequest;
use WE\SpreadsheetImport\Annotations\Mapping;

/**
 * Service class of basic FE mapping functionality for simple usage on separate implementations.
 *
 * @Flow\Scope("singleton")
 */
class FrontendMappingService {

	/**
	 * @Flow\Inject
	 * @var \WE\SpreadsheetImport\SpreadsheetImportService
	 */
	protected $spreadsheetImportService;

	/**
	 * @Flow\InjectConfiguration
	 * @var array
	 */
	protected $settings;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Persistence\PersistenceManagerInterface
	 */
	protected $persistenceManager;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Property\PropertyMapper
	 */
	protected $propertyMapper;

	/**
	 * @param string $context
	 * @param \TYPO3\Flow\Mvc\ActionRequest $request
	 *
	 * @return array
	 */
	public function getContextArgumentsForRequest($context, ActionRequest $request) {
		$arguments = array();
		$contextArguments = $this->settings[$context]['arguments'];
		if (is_array($contextArguments)) {
			foreach ($contextArguments as $contextArgument) {
				$name = $contextArgument['name'];
				if (isset($contextArgument['static'])) {
					$arguments[$name] = $contextArgument['static'];
				} elseif ($request->hasArgument($name)) {
					$value = $request->getArgument($name);
					if (isset($contextArgument['domain'])) {
						$object = $this->propertyMapper->convert($value, $contextArgument['domain']);
						$arguments[$name] = $this->persistenceManager->getIdentifierByObject($object);
					} else {
						$arguments[$name] = $value;
					}
				} else {
					$arguments[$name] = NULL;
				}
			}
		}
		return $arguments;
	}

	/**
	 * @param array $mappingProperties
	 * @param array $columns
	 *
	 * @return array
	 */
	public function getSpreadsheetImportMapping($mappingProperties, $columns = array()) {
		$mappings = array();
		foreach ($mappingProperties as $property => $mapping) {
			$column = isset($columns[$property]) ? $columns[$property] : '';
			$columnMapping = array('column' => $column, 'mapping' => $mapping);
			$mappings[$property] = $columnMapping;
		}
		return $mappings;
	}

	/**
	 * @param array $mapping
	 * @param int $record
	 *
	 * @return array
	 */
	public function getMappingPreview($mapping, $record) {
		$previewObject = $this->spreadsheetImportService->getObjectByRow($record);
		$preview = array();
		foreach ($mapping as $property => $columnMapping) {
			/** @var Mapping $mapping */
			$mapping = $columnMapping['mapping'];
			$getter = empty($mapping->getter) ? 'get' . ucfirst($property) : $mapping->getter;
			$preview[$property] = $previewObject->$getter();
		}
		return $preview;
	}
}
