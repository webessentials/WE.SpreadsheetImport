A Flow package to import spreadsheet records into configurable domain objects.

## Domain Model Configuration

### Settings

The package supports the import of different domain models. Each one needs to be configured in the setting files. A specific configuration is also referred to as 'Context'. 

Minimum configuration example 'default' context:

    WE:
      SpreadsheetImport:
        default:
          domain: WE\Sample\Domain\Model\User
          arguments: ~

### Mapping by Annotations

The domain model properties to be imported are specified by the `Mapping` annotation and can then be mapped to a column in the spreadsheet.

A simple property is specified the following way:

    /**
     * @var string
     * @SpreadsheetImport\Mapping
     */
    $firstName

`Mapping` supports properties to specify further configurations such as modified getters/setters, label, and the definition for identifiers. See the `WE\SpreadsheetImport\Annotations\Mapping` class for your reference.

### Argument Values

Besides the values that are imported from the spreadsheet, fix values are specified by parameter or statically in the settings. Example:

    arguments:
    - { name: category, domain: 'WE\Sample\Domain\Model\UserCategory', identifier: TRUE }
    - { name: comment, static: 'Sample import' }

The argument 'category' expects a `UserCategory` object passed as parameter. It will be set to the object by `setCategory(..)`. Further, this value is used as identifier in the same way as the identifier property in the Mapping annotation.
The argument 'comment' has a static value and will simply call the `UserCategory::setComment('Sample import')`.


## Usage

### Technical
Per import, a specific `\WE\SpreadsheetImport\Domain\Model\SpreadsheetImport` object needs to be created, which then can be processed by the `SpreadsheetImportService::import()` function.
The object contains the column mapping between domain model and spreadsheet columns.

How the `SpreadsheetImport` object is created and progressed by the import function my vary per implementation and can easily be modified. The process implemented within this package works as a sample scenario but can be used out of the box with the simple configuration documented above.

### Sample Implementation

1. Call the `newAction` of the `SpreadsheetImportController` to create new `SpreadsheetImport` object.
2. On the next step of the dialog, the mapping between the configured domain model mapping properties and the spreadsheet columns needs to be done.
3. An object mapping preview shows the applied mapping and has to be confirmed.
4. After confirmation, the import is scheduled to import.
4. The function `./flow spreadsheetimport:import` has to be called to process the import.

To use the sample implementation, the controller might be extended and the `context` property overwritten.

