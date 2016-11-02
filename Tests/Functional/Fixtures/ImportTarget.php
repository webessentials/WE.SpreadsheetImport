<?php
namespace WE\SpreadsheetImport\Tests\Functional\Fixtures;

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
use WE\SpreadsheetImport\Annotations as SpreadsheetImport;
use WE\SpreadsheetImport\Domain\Model\SpreadsheetImportTargetInterface;

/**
 * @Flow\Entity
 */
class ImportTarget {

	/**
	 * @var string
	 * @SpreadsheetImport\Mapping(identifier=true, setter="setRawId")
	 */
	protected  $id;

	/**
	 * @var string
	 * @SpreadsheetImport\Mapping
	 */
	protected  $name;

	/**
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @param string $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * @param string $id
	 */
	public function setRawId($id) {
		$this->id = sprintf('%05d', $id);
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @param string $name
	 */
	public function setName($name) {
		$this->name = $name;
	}
}
