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
	 * Import one pending queued spreadsheet into Domain data, and it will import the next one if it is done
	 */
	public function importCommand() {
		$currentImportingCount = $this->spreadsheetImportRepository->countByImportingStatus(SpreadsheetImport::IMPORTING_STATUS_IN_PROGRESS);
		if ($currentImportingCount > 0) {
			$this->outputFormatted('There is a progressing importing spreadsheet.');
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
			$this->spreadsheetImportService->import();

			// mark importing status as "Completed"
			$spreadsheetImport->setImportingStatus(SpreadsheetImport::IMPORTING_STATUS_COMPLETED);
			$this->spreadsheetImportRepository->update($spreadsheetImport);

			$this->outputFormatted('Spreadsheet has been imported. (totalInserted: %d, totalUpdated: %d, totalDeleted: %d, totalSkipped: %d)',
				array($spreadsheetImport->getTotalInserted(), $spreadsheetImport->getTotalUpdated(), $spreadsheetImport->getTotalDeleted(), $spreadsheetImport->getTotalSkipped()));
		} else {
			$this->outputFormatted('There is no spreadsheet importing in queue.');
		}
	}

	/**
	 * Cleanup previous spreadsheet imports by specific time (defined by settings)
	 */
	public function cleanupCommand() {
		$cleanupImportFromPreviousDay = $this->settings['cleanupImportFromPreviousDay'];
		$cleanupFromDate = new \DateTime();
		$cleanupFromDate->sub(new \DateInterval('P' . $cleanupImportFromPreviousDay . 'D'));
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
