<?php

/**
 * CSV file bulk loading source
 */
class CsvBulkLoaderSource extends BulkLoaderSource{

	protected $filepath;

	protected $delimiter = ',';

	protected $enclosure = '"';

	protected $hasheader = true;

	protected $columnMap;

	public function setFilePath($path) {
		$this->filepath = $path;

		return $this;
	}

	public function getFilePath() {
		return $this->filepath;
	}

	public function setFieldDelimiter($delimiter) {
		$this->delimiter = $delimiter;

		return $this;
	}

	public function getFieldDelimiter() {
		return $this->delimiter;
	}

	public function setFieldEnclosure($enclosure) {
		$this->enclosure = $enclosure;

		return $this;
	}

	public function getFieldEnclosure() {
		return $this->enclosure;
	}

	public function setHasHeader($hasheader) {
		$this->hasheader = $hasheader;

		return $this;
	}

	public function getHasHeader() {
		return $this->hasheader;
	}

	public function setColumnMap($map) {
		$this->columnMap = $map;

		return $this;
	}

	public function getColumnMap() {
		return $this->columnMap;
	}

	/**
	 * Get a new CSVParser using defined settings.
	 * @return Iterator
	 */
	public function getIterator(){
		if(!file_exists($this->filepath)){
			//TODO: throw exception instead?
			return null;
		}

		$parser = new CSVParser(
			$this->filepath, 
			$this->delimiter, 
			$this->enclosure
		);

		if($this->columnMap){
			if($this->hasheader) {
				$parser->mapColumns($this->columnMap);
			} else {
				$parser->provideHeaderRow($this->columnMap);
			}
		}

		return $parser;
	}

}
