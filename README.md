# WIP: SilverStripe Import/Export Module

This module serves as a replacement for all SilverStripe importing and exporting.

## User-defined column mapping

Users can choose which columns map to DataObject fields. This removes any need to define headings, or headings according to a given schema.

Users can state if the first line of data is infact a heading row.

## Usage

### Grid Field Importer

```php
    $importer = new GridFieldImporter('before');
    $gridConfig->addComponent($importer);
```

## Future feature ideas

### Importing

 * Choose mandatory columns
 * Auto-map columns that match loader fields
 * Optionally use DataObject 'defaults' values for empty cells
 * Cache selected column mapping for future uploads
 * Validate incoming data, and flag any issues

### Exporting

 * Choose fields to export
 * Choose order of fields to export
 * Display a preview of export data
