<?php
namespace WE\SpreadsheetImport\Domain\Repository;

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
use TYPO3\Flow\Persistence\Repository;
use WE\SpreadsheetImport\Domain\Model\SpreadsheetImport;

/**
 * @Flow\Scope("singleton")
 */
class SpreadsheetImportRepository extends Repository {

	/**
	 * @var array
	 */
	protected $defaultOrderings = array('scheduleDate' => QueryInterface::ORDER_DESCENDING);

	/**
	 * @return SpreadsheetImport
	 */
	public function findNextInQueue() {
		$query = $this->createQuery();
		$constraint = $query->logicalAnd(
			$query->equals('importingStatus', SpreadsheetImport::IMPORTING_STATUS_IN_QUEUE),
			$query->lessThanOrEqual('scheduleDate', new \DateTime())
		);
		return $query->matching($constraint)
			->setOrderings(array('scheduleDate' => QueryInterface::ORDER_ASCENDING))
			->execute()->getFirst();
	}

	/**
	 * @param \DateTime $dateTime
	 * @param int $importingStatus
	 *
	 * @return \TYPO3\Flow\Persistence\QueryResultInterface
	 */
	public function findBySpecificDateTimeAndImportingStatus(\DateTime $dateTime, $importingStatus = -1) {
		$query = $this->createQuery();
		$constraint = $query->lessThanOrEqual('progressDate', $dateTime);
		if ($importingStatus >= 0) {
			$constraint = $query->logicalAnd($constraint, $query->equals('importingStatus', $importingStatus));
		}
		return $query->matching($constraint)->execute();
	}

	/**
	 * @param string $context
	 * @param array $arguments
	 *
	 * @return \TYPO3\Flow\Persistence\QueryResultInterface
	 */
	public function findByContextAndArguments($context, $arguments = array()) {
		$query = $this->createQuery();
		$constraint = $query->logicalAnd(
			$query->equals('context', $context),
			$query->equals('arguments', serialize($arguments))
		);
		return $query->matching($constraint)->execute();
	}
}
