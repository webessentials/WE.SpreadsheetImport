<?php
namespace WE\SpreadsheetImport\Annotations;

/*                                                                        *
 * This script belongs to the Flow package "SpreadsheetImport".           *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 * Defines the column mapping for a property
 *
 * @Annotation
 * @Target({"PROPERTY"})
 */
final class Mapping {

	/**
	 * @var string
	 */
	public $labelId = '';

	/**
	 * @var boolean
	 */
	public $identifier = FALSE;

	/**
	 * @var string
	 */
	public $setter;
}
