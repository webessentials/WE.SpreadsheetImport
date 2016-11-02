<?php
namespace WE\SpreadsheetImport\Controller;

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
use TYPO3\Flow\Mvc\Controller\ActionController;
use WE\SpreadsheetImport\Domain\Model\SpreadsheetImport;

/**
 * Controller for default import. This controller might be overwritten for the import of different domains.
 */
class SpreadsheetImportController extends ActionController {

	/**
	 * @var string
	 */
	protected $context = 'default';

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
	 * @var \WE\SpreadsheetImport\FrontendMappingService
	 */
	protected $frontendMappingService;

	/**
	 * Initialize create action
	 */
	protected function initializeCreateAction() {
		$this->arguments['newSpreadsheetImport']->getPropertyMappingConfiguration()->forProperty('scheduleDate')
			->setTypeConverterOption('TYPO3\Flow\Property\TypeConverter\DateTimeConverter', \TYPO3\Flow\Property\TypeConverter\DateTimeConverter::CONFIGURATION_DATE_FORMAT, 'd.m.Y');
	}

	/**
	 * New action with context set statically
	 */
	public function newAction() {
		$arguments = $this->frontendMappingService->getContextArgumentsForRequest($this->context, $this->request);
		$spreadsheetImports = $this->spreadsheetImportRepository->findByContextAndArguments($this->context, $arguments);
		$this->view->assign('spreadsheetImports', $spreadsheetImports);
		$this->view->assign('arguments', serialize($arguments));
		$this->view->assign('context', $this->context);
	}

	/**
	 * @param \WE\SpreadsheetImport\Domain\Model\SpreadsheetImport $newSpreadsheetImport
	 */
	public function createAction(SpreadsheetImport $newSpreadsheetImport) {
		$formArguments = $this->request->getArguments();
		$this->setScheduleDateTime($newSpreadsheetImport, $formArguments['scheduleTime']);
		$this->setImportAction($newSpreadsheetImport, (int)$formArguments['action']);
		$this->spreadsheetImportService->init($newSpreadsheetImport);
		$mappingProperties = $this->spreadsheetImportService->getAnnotationMappingProperties();
		$mappings = $this->frontendMappingService->getSpreadsheetImportMapping($mappingProperties);
		$newSpreadsheetImport->setMapping($mappings);
		$this->spreadsheetImportRepository->add($newSpreadsheetImport);
		$this->redirect('mapping', NULL, NULL, array('spreadsheetImport' => $newSpreadsheetImport));
	}

	/**
	 * @param \WE\SpreadsheetImport\Domain\Model\SpreadsheetImport $spreadsheetImport
	 */
	public function mappingAction(SpreadsheetImport $spreadsheetImport) {
		$this->spreadsheetImportService->init($spreadsheetImport);
		$spreadsheetColumns = $this->spreadsheetImportService->getSpreadsheetColumns();
		$this->view->assign('spreadsheetColumns', $spreadsheetColumns);
		$this->view->assign('domainMappingProperties', $spreadsheetImport->getMapping());
		$this->view->assign('spreadsheetImport', $spreadsheetImport);
	}

	/**
	 * @param \WE\SpreadsheetImport\Domain\Model\SpreadsheetImport $spreadsheetImport
	 */
	public function mapAction(SpreadsheetImport $spreadsheetImport) {
		if ($spreadsheetImport->getImportingStatus() === SpreadsheetImport::IMPORTING_STATUS_DRAFT) {
			$this->spreadsheetImportService->init($spreadsheetImport);
			$mappingProperties = $this->spreadsheetImportService->getAnnotationMappingProperties();
			$requestArguments = $this->request->getArguments();
			$mappings = $this->frontendMappingService->getSpreadsheetImportMapping($mappingProperties, $requestArguments);
			$spreadsheetImport->setMapping($mappings);
			$this->spreadsheetImportRepository->update($spreadsheetImport);
		}
		$this->redirect('preview', NULL, NULL, array('spreadsheetImport' => $spreadsheetImport));
	}

	/**
	 * @param \WE\SpreadsheetImport\Domain\Model\SpreadsheetImport $spreadsheetImport
	 * @param int $record
	 */
	public function previewAction(SpreadsheetImport $spreadsheetImport, $record = 1) {
		$this->spreadsheetImportService->init($spreadsheetImport);
		$preview = $this->frontendMappingService->getMappingPreview($spreadsheetImport, $record);
		$total = $this->spreadsheetImportService->getTotalRecords();
		$this->view->assign('preview', $preview['preview']);
		$this->view->assign('record', $record);
		$this->view->assign('previous', $record - 1);
		$this->view->assign('next', $record + 1);
		$this->view->assign('total', $total);
		$this->view->assign('hasErrors', $preview['hasErrors']);
		$this->view->assign('spreadsheetImport', $spreadsheetImport);
	}

	/**
	 * @param \WE\SpreadsheetImport\Domain\Model\SpreadsheetImport $spreadsheetImport
	 */
	public function confirmAction(SpreadsheetImport $spreadsheetImport) {
		$arguments = $spreadsheetImport->getArguments();
		if ($spreadsheetImport->getImportingStatus() === SpreadsheetImport::IMPORTING_STATUS_DRAFT) {
			$spreadsheetImport->setImportingStatus(SpreadsheetImport::IMPORTING_STATUS_IN_QUEUE);
			$this->spreadsheetImportRepository->update($spreadsheetImport);
		}
		$this->redirect('new', NULL, NULL, $arguments);
	}

	/**
	 * @param \WE\SpreadsheetImport\Domain\Model\SpreadsheetImport $spreadsheetImport
	 */
	public function cancelAction(SpreadsheetImport $spreadsheetImport) {
		$arguments = $spreadsheetImport->getArguments();
		if ($spreadsheetImport->getImportingStatus() === SpreadsheetImport::IMPORTING_STATUS_DRAFT) {
			$this->spreadsheetImportRepository->remove($spreadsheetImport);
			$this->persistenceManager->persistAll();
		}
		$this->redirect('new', NULL, NULL, $arguments);
	}

	/**
	 * @param \WE\SpreadsheetImport\Domain\Model\SpreadsheetImport $spreadsheetImport
	 */
	public function deleteAction(SpreadsheetImport $spreadsheetImport) {
		$arguments = $spreadsheetImport->getArguments();
		if ($spreadsheetImport->getImportingStatus() <= SpreadsheetImport::IMPORTING_STATUS_IN_QUEUE) {
			$this->spreadsheetImportRepository->remove($spreadsheetImport);
		}
		$this->redirect('new', NULL, NULL, $arguments);
	}

	/**
	 * @param SpreadsheetImport $spreadsheetImport
	 * @param                   $time
	 */
	private function setScheduleDateTime(SpreadsheetImport $spreadsheetImport, $time) {
		$date = $spreadsheetImport->getScheduleDate();
		$times = explode(':', $time);
		$hour = isset($times[0]) ? $times[0] : 0;
		$minute = isset($times[1]) ? $times[1] : 0;
		$date->setTime($hour, $minute);
		$spreadsheetImport->setScheduleDate($date);
	}

	/**
	 * @param \WE\SpreadsheetImport\Domain\Model\SpreadsheetImport $spreadsheetImport
	 * @param int $action
	 */
	private function setImportAction(SpreadsheetImport $spreadsheetImport, $action = 1) {
		if ($action >= 1) {
			$spreadsheetImport->setInserting(TRUE);
		}
		if ($action >= 2) {
			$spreadsheetImport->setUpdating(TRUE);
		}
		if ($action >= 3) {
			$spreadsheetImport->setDeleting(TRUE);
		}
	}

}
