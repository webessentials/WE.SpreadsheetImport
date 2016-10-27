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
use TYPO3\Flow\Persistence\Repository;

/**
 * @Flow\Scope("singleton")
 */
class SpreadsheetImportRepository extends Repository {

	/**
	 * @var array
	 */
	protected $defaultOrderings = array('scheduleDate' => \TYPO3\Flow\Persistence\QueryInterface::ORDER_ASCENDING);

	/**
	 * @param \DateTime $dateTime
	 *
	 * @return \TYPO3\Flow\Persistence\QueryResultInterface
	 */
	public function findPreviousImportsBySpecificDate(\DateTime $dateTime) {
		$query = $this->createQuery();
		$constraint = $query->lessThanOrEqual('scheduleDate', $dateTime);
		return $query->matching($constraint)->execute();
	}

}
