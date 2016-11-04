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
	 * @var \WE\SpreadsheetImport\Log\SpreadsheetImportLoggerInterface
	 */
	protected $logger;

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
			$message = 'Spreadsheet import started.';
			$this->log($spreadsheetImport, $message, LOG_INFO);
			// mark importing status as "Progressing" before continuing the importing
			$spreadsheetImport->setImportingStatus(SpreadsheetImport::IMPORTING_STATUS_IN_PROGRESS);
			$this->spreadsheetImportRepository->update($spreadsheetImport);
			$this->persistenceManager->persistAll();

			// do importing and mark its status as "Completed/Failed"
			$this->spreadsheetImportService->init($spreadsheetImport);
			try {
				$this->spreadsheetImportService->import();
				$spreadsheetImport->setImportingStatus(SpreadsheetImport::IMPORTING_STATUS_COMPLETED);
				$args = array($spreadsheetImport->getTotalInserted(), $spreadsheetImport->getTotalUpdated(), $spreadsheetImport->getTotalDeleted(), $spreadsheetImport->getTotalSkipped());
				$message = vsprintf('Spreadsheet import complete: %d inserted, %d updated, %d deleted, %d skipped', $args);
				$this->log($spreadsheetImport, $message, LOG_INFO);
			} catch (\Exception $e) {
				$spreadsheetImport->setImportingStatus(SpreadsheetImport::IMPORTING_STATUS_FAILED);
				$message = 'Spreadsheet import failed.';
				$this->log($spreadsheetImport, $message, LOG_ERR, $e->getMessage());
			}
			try {
				$this->spreadsheetImportRepository->update($spreadsheetImport);
			} catch (\Exception $e) {
				$message = 'Spreadsheet import status update error. It remains in progress until cleanup.';
				$this->log($spreadsheetImport, $message, LOG_ERR, $e->getMessage());
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
			$message = 'Spreadsheet import removed.';
			$this->log($spreadsheetImport, $message, LOG_INFO, NULL);
		}
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
			$message = 'Spreadsheet import exceeded max execution threashold. Status set to failed.';
			$this->log($spreadsheetImport, $message, LOG_NOTICE);
		}
	}

	/**
	 * @param SpreadsheetImport $spreadsheetImport
	 * @param string $message
	 * @param int $severity
	 * @param null $additionalData
	 */
	private function log(SpreadsheetImport $spreadsheetImport, $message, $severity = LOG_INFO, $additionalData = NULL) {
		$name = ucfirst($spreadsheetImport->getContext());
		$message = vsprintf('[%s] ' . $message, array($name));
		$this->logger->log($message, $severity, $additionalData, 'SpreadsheetImport');
		$this->outputLine($message);
	}
}
