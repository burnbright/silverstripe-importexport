# 0.1.0

## New Features

* Users can define column mappings via the CSVFieldMapper class.
* CSV files can be previewed via the CSVPreviewer class.
* Records can be skipped during import.
* Introduced BulkLoaderSource as a way of abstracting CSV / other source functionality away from the BulkLoader class.

## Bug Fixes

* Validation failing on DataObject->write() will cause a record to be skipped, rather than halting the whole process.


## Upgrading Notes

You'll need to seperately define a BulkLoaderSource when configuring your BulkLoader. 