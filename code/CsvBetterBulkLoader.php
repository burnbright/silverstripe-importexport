<?php

/**
 * Backwards copatible CsvBulkLoader - api equivelant to CSVBulkLoader
 */
class CsvBetterBulkLoader extends BetterBulkLoader{

	public $delimiter = ',';
	public $enclosure = '"';
	public $hasHeaderRow = true;

	protected function processAll($filepath, $preview = false) {

		//configre a CsvBulkLoaderSource
		$source = new CsvBulkLoaderSource($this);
		$source->setFilePath($filepath);
		$source->setHasHeader($this->hasHeaderRow);
		$this->setSource($source);

		return parent::processAll($filepath, $preview);
	}

	public function hasHeaderRow() {
		return ($this->hasHeaderRow || isset($this->columnMap));
	}

}