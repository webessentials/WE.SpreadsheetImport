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
 * Annotation to define the column mapping for a property
 *
 * @Annotation
 * @Target({"PROPERTY"})
 */
final class Mapping {

	/**
	 * Label id for the property mapping
	 *
	 * @var string
	 */
	public $labelId = '';

	/**
	 * Flag if property is handled as an identifier for updates
	 *
	 * @var boolean
	 */
	public $identifier = FALSE;

	/**
	 * Overwrite the default getter for previews
	 *
	 * @var string
	 */
	public $getter;

	/**
	 * Overwrite the default setter
	 *
	 * @var string
	 */
	public $setter;

	/**
	 * Overwrite the default query property name used for identifiers with overwritten getter
	 *
	 * @var string
	 */
	public $queryPropertyName;

}
