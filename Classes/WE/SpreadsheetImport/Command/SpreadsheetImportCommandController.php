<?php
namespace WE\SpreadsheetImport\Command;

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
use TYPO3\Flow\Cli\CommandController;
use TYPO3\Flow\Exception;
use WE\SpreadsheetImport\Domain\Model\SpreadsheetImport;

/**
 * @Flow\Scope("singleton")
 */
class SpreadsheetImportCommandController extends CommandController {

	/**
	 * @Flow\Inject
	 * @var \WE\SpreadsheetImport\Domain\Repository\SpreadsheetImportRepository
	 */
	protected $spreadsheetImportRepository;

	/**
	 * @Flow\Inject
	 * @var \WE\SpreadsheetImport\SpreadsheetImportService
	 */
	protected $spreadsheetImportService;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Persistence\PersistenceManagerInterface
	 */
	protected $persistenceManager;

	/**
	 * @Flow\InjectConfiguration
	 * @var array
	 */
	protected $settings;

	/**
	 * Import next queued spreadsheets into domain objects asynchronously.
	 */
	public function importCommand() {
		$currentImportingCount = $this->spreadsheetImportRepository->countByImportingStatus(SpreadsheetImport::IMPORTING_STATUS_IN_PROGRESS);
		if ($currentImportingCount > 0) {
			$this->outputLine('Previous spreadsheet import is still in progress.');
			$this->quit();
		}
		/** @var SpreadsheetImport $spreadsheetImport */
		$spreadsheetImport = $this->spreadsheetImportRepository->findNextInQueue();
		if ($spreadsheetImport instanceof SpreadsheetImport) {
			// mark importing status as "Progressing" before continuing the importing
			$spreadsheetImport->setImportingStatus(SpreadsheetImport::IMPORTING_STATUS_IN_PROGRESS);
			$this->spreadsheetImportRepository->update($spreadsheetImport);
			$this->persistenceManager->persistAll();

			// do importing and mark its status as "Completed/Failed"
			$this->spreadsheetImportService->init($spreadsheetImport);
			try {
				$this->spreadsheetImportService->import();
				$spreadsheetImport->setImportingStatus(SpreadsheetImport::IMPORTING_STATUS_COMPLETED);
				$this->outputLine('Spreadsheet has been imported. %d inserted, %d updated, %d deleted, %d skipped',
					array($spreadsheetImport->getTotalInserted(), $spreadsheetImport->getTotalUpdated(), $spreadsheetImport->getTotalDeleted(), $spreadsheetImport->getTotalSkipped()));
			} catch (\Exception $e) {
				$spreadsheetImport->setImportingStatus(SpreadsheetImport::IMPORTING_STATUS_FAILED);
				$this->outputLine('Spreadsheet import failed.');
			}
			try {
				$this->spreadsheetImportRepository->update($spreadsheetImport);
			} catch (\Exception $e) {
				$this->outputLine('Spreadsheet import status update error. It remains in progress until cleanup.');
			}
		} else {
			$this->outputFormatted('No spreadsheet import in queue.');
		}
	}

	/**
	 * Cleanup past and stalled imports.
	 *
	 * @param int $keepPastImportsThreasholdDays Overwrites the setting value
	 * @param int $maxExecutionThreasholdMinutes Overwrites the setting value
	 */
	public function cleanupCommand($keepPastImportsThreasholdDays = -1, $maxExecutionThreasholdMinutes = -1) {
		$keepPastImportsThreasholdDays = ($keepPastImportsThreasholdDays >= 0) ? $keepPastImportsThreasholdDays : intval($this->settings['keepPastImportsThreasholdDays']);
		$maxExecutionThreasholdMinutes = ($maxExecutionThreasholdMinutes >= 0) ? $maxExecutionThreasholdMinutes : intval($this->settings['maxExecutionThreasholdMinutes']);
		$this->cleanupPastImports($keepPastImportsThreasholdDays);
		$this->cleanupStalledImports($maxExecutionThreasholdMinutes);
	}

	/**
	 * Delete past imports
	 *
	 * @param int $keepPastImportsThreasholdDays
	 */
	private function cleanupPastImports($keepPastImportsThreasholdDays) {
		$cleanupFromDateTime = new \DateTime();
		$cleanupFromDateTime->sub(new \DateInterval('P' . $keepPastImportsThreasholdDays . 'D'));
		$spreadsheetImports = $this->spreadsheetImportRepository->findBySpecificDateTimeAndImportingStatus($cleanupFromDateTime);
		/** @var SpreadsheetImport $spreadsheetImport */
		foreach ($spreadsheetImports as $spreadsheetImport) {
			$this->spreadsheetImportRepository->remove($spreadsheetImport);
		}
		$this->outputLine('%d spreadsheet imports removed.', array($spreadsheetImports->count()));
	}

	/**
	 * Set stalled imports to failed
	 *
	 * @param int $maxExecutionThreasholdMinutes
	 */
	private function cleanupStalledImports($maxExecutionThreasholdMinutes) {
		$cleanupFromDateTime = new \DateTime();
		$cleanupFromDateTime->sub(new \DateInterval('PT' . $maxExecutionThreasholdMinutes . 'M'));
		$spreadsheetImports = $this->spreadsheetImportRepository->findBySpecificDateTimeAndImportingStatus($cleanupFromDateTime, SpreadsheetImport::IMPORTING_STATUS_IN_PROGRESS);
		/** @var SpreadsheetImport $spreadsheetImport */
		foreach ($spreadsheetImports as $spreadsheetImport) {
			$spreadsheetImport->setImportingStatus(SpreadsheetImport::IMPORTING_STATUS_FAILED);
			$this->spreadsheetImportRepository->update($spreadsheetImport);
		}
		$this->outputLine('%d spreadsheet imports set to failed.', array($spreadsheetImports->count()));
	}
}
