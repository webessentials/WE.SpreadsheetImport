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
			$this->outputFormatted('Previous spreadsheet import is still in progress.');
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
				$this->outputFormatted('Spreadsheet has been imported. %d inserted, %d updated, %d deleted, %d skipped',
					array($spreadsheetImport->getTotalInserted(), $spreadsheetImport->getTotalUpdated(), $spreadsheetImport->getTotalDeleted(), $spreadsheetImport->getTotalSkipped()));
			} catch (Exception $e) {
				$spreadsheetImport->setImportingStatus(SpreadsheetImport::IMPORTING_STATUS_FAILED);
				$this->outputFormatted('Spreadsheet import failed.');
			}
			$this->spreadsheetImportRepository->update($spreadsheetImport);
		} else {
			$this->outputFormatted('No spreadsheet import in queue.');
		}
	}

	/**
	 * Cleanup previous spreadsheet imports. Threashold defined in settings.
	 */
	public function cleanupCommand() {
		$cleanupImportsThreasholdDays = intval($this->settings['cleanupImportsThreasholdDays']);
		$cleanupFromDate = new \DateTime();
		$cleanupFromDate->sub(new \DateInterval('P' . $cleanupImportsThreasholdDays . 'D'));
		$oldSpreadsheetImports = $this->spreadsheetImportRepository->findPreviousImportsBySpecificDate($cleanupFromDate);
		if ($oldSpreadsheetImports->count() > 0) {
			/** @var SpreadsheetImport $oldSpreadsheetImport */
			foreach ($oldSpreadsheetImports as $oldSpreadsheetImport) {
				$this->spreadsheetImportRepository->remove($oldSpreadsheetImport);
			}
			$this->outputLine('%d spreadsheet imports were removed.', array($oldSpreadsheetImports->count()));
		} else {
			$this->outputLine('There is no spreadsheet import in queue to remove.');
		}
	}

}
