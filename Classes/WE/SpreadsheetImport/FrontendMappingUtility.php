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

use TYPO3\Flow\Mvc\ActionRequest;
use WE\SpreadsheetImport\Annotations\Mapping;

/**
 * Utility class of basic FE mapping functionality for simple usage on separate implementations.
 */
class FrontendMappingUtility {

	/**
	 * @param \WE\SpreadsheetImport\SpreadsheetImportService $spreadsheetImportService
	 * @param \TYPO3\Flow\Mvc\ActionRequest $request
	 *
	 * @return array
	 */
	public static function getSpreadsheetImportMappingByRequest(SpreadsheetImportService $spreadsheetImportService, ActionRequest $request) {
		$mappings = array();
		$domainMappingProperties = $spreadsheetImportService->getMappingProperties();
		foreach ($domainMappingProperties as $property => $mapping) {
			$columnMapping = array('column' => $request->getArgument($property), 'mapping' => $mapping);
			$mappings[$property] = $columnMapping;
		}
		return $mappings;
	}

	/**
	 * @param \WE\SpreadsheetImport\SpreadsheetImportService $spreadsheetImportService
	 * @param int $record
	 *
	 * @return array
	 */
	public static function getMappingPreview(SpreadsheetImportService $spreadsheetImportService, $record) {
		$domainMappingProperties = $spreadsheetImportService->getMappingProperties();
		$previewObject = $spreadsheetImportService->getObjectByRow($record);
		$preview = array();
		/** @var Mapping $mapping */
		foreach ($domainMappingProperties as $property => $mapping) {
			$getter = empty($mapping->getter) ? 'get' . ucfirst($property) : $mapping->getter;
			$preview[$property] = $previewObject->$getter();
		}
		return $preview;
	}
}
