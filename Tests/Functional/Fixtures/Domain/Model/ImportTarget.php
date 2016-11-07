<?php
namespace WE\SpreadsheetImport\Tests\Functional\Fixtures\Domain\Model;

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
use Doctrine\ORM\Mapping as ORM;
use WE\SpreadsheetImport\Annotations as SpreadsheetImport;

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
	protected  $firstName;

	/**
	 * @var string
	 * @SpreadsheetImport\Mapping
	 */
	protected  $lastName;

	/**
	 * @var string
	 * @SpreadsheetImport\Mapping
	 */
	protected  $account;

	/**
	 * @var ImportTargetCategory
	 * @ORM\ManyToOne
	 */
	protected  $category;

	/**
	 * @var string
	 */
	protected  $comment;

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
	public function getFirstName() {
		return $this->firstName;
	}

	/**
	 * @param string $firstName
	 */
	public function setFirstName($firstName) {
		$this->firstName = $firstName;
	}

	/**
	 * @return string
	 */
	public function getLastName() {
		return $this->lastName;
	}

	/**
	 * @param string $lastName
	 */
	public function setLastName($lastName) {
		$this->lastName = $lastName;
	}

	/**
	 * @return string
	 */
	public function getAccount() {
		return $this->account;
	}

	/**
	 * @param string $account
	 */
	public function setAccount($account) {
		$this->account = $account;
	}

	/**
	 * @return \WE\SpreadsheetImport\Tests\Functional\Fixtures\Domain\Model\ImportTargetCategory
	 */
	public function getCategory() {
		return $this->category;
	}

	/**
	 * @param \WE\SpreadsheetImport\Tests\Functional\Fixtures\Domain\Model\ImportTargetCategory $category
	 */
	public function setCategory($category) {
		$this->category = $category;
	}

	/**
	 * @return string
	 */
	public function getComment() {
		return $this->comment;
	}

	/**
	 * @param string $comment
	 */
	public function setComment($comment) {
		$this->comment = $comment;
	}

}
