<?php
namespace WE\SpreadsheetImport\Domain\Model;

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

/**
 * @Flow\Entity
 */
class SpreadsheetImport {

	const IMPORTING_STATUS_DRAFT = 0;
	const IMPORTING_STATUS_IN_QUEUE = 1;
	const IMPORTING_STATUS_IN_PROGRESS = 2;
	const IMPORTING_STATUS_COMPLETED = 3;
	const IMPORTING_STATUS_FAILED = 4;

	/**
	 * @var string
	 * @Flow\Validate(type="NotEmpty")
	 */
	protected $context;

	/**
	 * @ORM\OneToOne(cascade={"persist", "remove"})
	 * @var \TYPO3\Flow\Resource\Resource
	 */
	protected $file;

	/**
	 * @var \DateTime
	 */
	protected $scheduleDate;

	/**
	 * @var string
	 * @ORM\Column(type="text")
	 */
	protected $mapping = '';

	/**
	 * @var string
	 * @ORM\Column(type="text")
	 */
	protected $arguments = '';

	/**
	 * @var boolean
	 */
	protected $inserting = FALSE;

	/**
	 * @var boolean
	 */
	protected $updating = FALSE;

	/**
	 * @var boolean
	 */
	protected $deleting = FALSE;

	/**
	 * @var int
	 * @ORM\Column(options={"default": 0})
	 */
	protected $importingStatus = self::IMPORTING_STATUS_DRAFT;

	/**
	 * @var int
	 */
	protected $totalInserted = 0;

	/**
	 * @var int
	 */
	protected $totalUpdated = 0;

	/**
	 * @var int
	 */
	protected $totalDeleted = 0;

	/**
	 * @var int
	 */
	protected $totalSkipped = 0;

	/**
	 * SpreadsheetImport constructor.
	 */
	public function __construct() {
		$this->scheduleDate = new \DateTime();
	}

	/**
	 * @return string
	 */
	public function getContext() {
		return $this->context;
	}

	/**
	 * @param string $context
	 */
	public function setContext($context) {
		$this->context = $context;
	}

	/**
	 * @return \TYPO3\Flow\Resource\Resource
	 */
	public function getFile() {
		return $this->file;
	}

	/**
	 * @param \TYPO3\Flow\Resource\Resource $file
	 */
	public function setFile($file) {
		$this->file = $file;
	}

	/**
	 * @return \DateTime
	 */
	public function getScheduleDate() {
		return $this->scheduleDate;
	}

	/**
	 * @param \DateTime $scheduleDate
	 */
	public function setScheduleDate($scheduleDate) {
		$this->scheduleDate = $scheduleDate;
	}

	/**
	 * @return array
	 */
	public function getMapping() {
		$mapping = unserialize($this->mapping);
		if (! is_array($mapping)) {
			$mapping = array();
		}
		return $mapping;
	}

	/**
	 * @param array $mapping
	 */
	public function setMapping($mapping) {
		$this->mapping = serialize($mapping);
	}

	/**
	 * @return array
	 */
	public function getArguments() {
		$arguments = unserialize($this->arguments);
		if (! is_array($arguments)) {
			$arguments = array();
		}
		return $arguments;
	}

	/**
	 * @param string|array $arguments
	 */
	public function setArguments($arguments) {
		if (is_array($arguments)) {
			$this->arguments = serialize($arguments);
		} else {
			$this->arguments = $arguments;
		}
	}

	/**
	 * @return boolean
	 */
	public function isInserting() {
		return $this->inserting;
	}

	/**
	 * @param boolean $inserting
	 */
	public function setInserting($inserting) {
		$this->inserting = $inserting;
	}

	/**
	 * @return boolean
	 */
	public function isUpdating() {
		return $this->updating;
	}

	/**
	 * @param boolean $updating
	 */
	public function setUpdating($updating) {
		$this->updating = $updating;
	}

	/**
	 * @return boolean
	 */
	public function isDeleting() {
		return $this->deleting;
	}

	/**
	 * @param boolean $deleting
	 */
	public function setDeleting($deleting) {
		$this->deleting = $deleting;
	}

	/**
	 * @return int
	 */
	public function getImportingStatus() {
		return $this->importingStatus;
	}

	/**
	 * @param int $importingStatus
	 */
	public function setImportingStatus($importingStatus) {
		$this->importingStatus = $importingStatus;
	}

	/**
	 * @return int
	 */
	public function getTotalInserted() {
		return $this->totalInserted;
	}

	/**
	 * @param int $totalInserted
	 */
	public function setTotalInserted($totalInserted) {
		$this->totalInserted = $totalInserted;
	}

	/**
	 * @return int
	 */
	public function getTotalUpdated() {
		return $this->totalUpdated;
	}

	/**
	 * @param int $totalUpdated
	 */
	public function setTotalUpdated($totalUpdated) {
		$this->totalUpdated = $totalUpdated;
	}

	/**
	 * @return int
	 */
	public function getTotalDeleted() {
		return $this->totalDeleted;
	}

	/**
	 * @param int $totalDeleted
	 */
	public function setTotalDeleted($totalDeleted) {
		$this->totalDeleted = $totalDeleted;
	}

	/**
	 * @return int
	 */
	public function getTotalSkipped() {
		return $this->totalSkipped;
	}

	/**
	 * @param int $totalSkipped
	 */
	public function setTotalSkipped($totalSkipped) {
		$this->totalSkipped = $totalSkipped;
	}

}
