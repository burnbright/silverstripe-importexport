# SilverStripe Import/Export Module

This module serves as a replacement for all SilverStripe importing and exporting.

## User-defined column mapping

Users can choose which columns map to DataObject fields. This removes any need to define headings, or headings according to a given schema.

Users can state if the first line of data is infact a heading row.

Mapping process is:
**User's CSV file** *-maps-to->* **columnMap** *-maps-to->* **DataObject**

## Usage

### Grid Field Importer

This is a grid field component for users to selecting a CSV file and map it's columns to data fields.

```php
    $importer = new GridFieldImporter('before');
    $gridConfig->addComponent($importer);
```

The importer makes use of the `CSVFieldMapper`, which displays the beginning content of a CSV.

### BulkLoaderSource

A `BulkLoaderSource` provides an iterator to get record data from. Data could come from anywhere such as a CSV file, a web API, etc.

It can be used independently from the BulkLoader to obtain data.

### BulkLoader

Saves data from a particular source and persists it to database via the ORM.
Determines which fields can be mapped to, either scaffoleded from the model, provided by configuration, or both.

Detects existing records, and either skips or updates them, based on criteria.
Maps the source data to new/existing dataobjects, based on a given mapping.
Finds, creates, and connects relation objects to objects.
Can clear all records prior to processing.
