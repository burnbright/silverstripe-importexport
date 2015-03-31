# 0.1.0

## New Features

* Users can define column mappings via CSVFieldMapper / GridFieldImporter.
* CSV files can be previewed via the CSVPreviewer class.
* Records can be skipped during import. Skipped records are recorded in result object.
* Introduced BulkLoaderSource as a way of abstracting CSV / other source functionality away from the BulkLoader class.
* Introduced ListBulkLoader for confining record CRUD actions to a given DataList (HasManyList).
* Decoupled CSVParser from BulkLoader. Column mapping is now performed in BulkLoader on each record as it is loaded.
* Replaced CSVParser with goodby/csv library.

## Bug Fixes

* Validation failing on DataObject->write() will cause a record to be skipped, rather than halting the whole process.
* Prevented bulk loader from trying to work with relation names that don't exist. This would particularly cause issues when CSV header names contained a ".".

## Upgrading Notes

You'll need to seperately define a BulkLoaderSource when configuring your BulkLoader. 