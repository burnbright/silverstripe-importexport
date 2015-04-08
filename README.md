# SilverStripe Import/Export Module

[![Build Status](https://travis-ci.org/burnbright/silverstripe-importexport.svg?branch=master)](https://travis-ci.org/burnbright/silverstripe-importexport)

Import and export data from SilverStripe in various forms, including CSV. This module serves as a replacement/overhaul of BulkLoader functionality found in framework.

## The loading process

1. Raw data is retrieved from a source (`BulkLoaerSource`).
2. Data is provided as iterable rows (each row is heading->value mapped array).
3. Rows are mapped to a standardised format, based on a user/developer provided mapping.
4. Data is set/linked/tranformed on new object.
5. New object is validated.
6. New object is saved.

## User-defined column mapping

Users can choose which columns map to DataObject fields. This removes any need to define headings, or headings according to a given schema.

Users can state if the first line of data is infact a heading row.

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

```php
$source = new CsvBulkLoaderSource();
$source->setFilePath("files/myfile.csv")
    ->setHasHeader(true)
    ->setFieldDelimiter(",")
    ->setFieldEnclosure("'");

foreach($source->getIterator() as $record){
    //do stuff
}
```

### (Better)BulkLoader

Saves data from a particular source and persists it to database via the ORM.
Determines which fields can be mapped to, either scaffoleded from the model, provided by configuration, or both.

Detects existing records, and either skips or updates them, based on criteria.
Maps the source data to new/existing dataobjects, based on a given mapping.
Finds, creates, and connects relation objects to objects.
Can clear all records prior to processing.

```php
$source = new CsvBulkLoaderSource();
$source->setFilePath("files/myfile.csv");

$loader = new BetterBulkLoader("Product");
$loader->setSource($source);

$result = $loader->load();
```

### ListBulkLoader

Often you'll want to confine bulk loading to a specific DataList. The ListBulkLoader is a varaition of BulkLoader that adds and removes records from a given DataList. Of course DataList iself doesn't have an add method implemented, so you'll probably find it more useful for a `HasManyList`.

```php
$category = ProductCategory::get()->first();

$source = new CsvBulkLoaderSource();
$source->setFilePath("productlist.csv");

$loader = new ListBulkLoader($category->Products());
$loader->setSource($source);

$result = $loader->load();
```

## Transform incoming record data

You may want to perform some transformations to incoming record data. This can be done by specifying a callback against the record field name.

```php
$this->loader->transforms = array(
    'Code' => array(
        'callback' => function($field, $record) {
            //capitalize course codes
            return strtoupper($record[$field]));
        }
    )
);
```

## Customise how relationships are handled

The bulk loader can handle linking and creating `has_one` relationship objects, by either providing a callback, or using the `Relation.FieldName` style "dot notation". Relationship handling is also performed in the `transformations` array.

Here are some configuration examples:

```php
$this->loader->transforms = array(
    //link and create courses
    'Course.Title' = array(
        'link' => true,
        'create' => true
    ),
    //only link to existing tutors
    'Tutor.Name' => array(
        'link' => true,
        'create' => false
    ),
    //custom way to find parent courses
    'Parent' => array(
        'callback' => function($value, $placeholder) use ($self){
            return Course::get()
                ->filter("Title", $value)
                ->first();
        }
    )
);
```

Note that `$placeholder` in the above example refers to a dummy DataObject that is populated in order to then be saved, or checked against for duplicates. You should not call `$placeholder->write()` in your callback.

## Contributions

Please do contribute whatever you can to this module. Check out the [issues](https://github.com/burnbright/silverstripe-importexport/issues) and [milestones](https://github.com/burnbright/silverstripe-importexport/milestones) to see what has needs to be done.

## License

MIT

## Author

Jeremy Shipman (http://jeremyshipman.com)
