# WIP: SilverStripe Import/Export Module

This module serves as a replacement for all SilverStripe importing and exporting.

## User-defined column mapping

Users can choose which columns map to DataObject fields. This removes any need to define headings, or headings according to a given schema.

Users can state if the first line of data is infact a heading row.

## Feature ideas



### Importing

 * Column mapper
 * Choose mandatory columns
 * Optionally use DataObject 'defaults' values for empty cells
 * Require specific fields
 * Cache selected column mapping for future uploads
 * Validate incoming data, and flag issues
 * 

### Exporting

 * Choose fields to export
 * Choose order of fields to export
 * Display a preview of export data


## Future

Use an established / feature rich csv composer package:
https://github.com/thephpleague/csv (best, but requires PHP 5.4)
https://github.com/goodby/csv
