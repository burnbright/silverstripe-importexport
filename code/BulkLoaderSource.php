<?php

/**
 * An abstract source to bulk load records from.
 * Provides an iterator for retrieving records from.
 * 
 * Useful for holiding source configuration state.
 */
abstract class BulkLoaderSource{

	protected $loader;

	public final function __construct(BulkLoader $loader){
		$this->loader = $loader;
	}

	/**
	 * Provide iterator for bulk loading from.
	 * Records are expected to be 1 dimensional key-value arrays.
	 * @return Iterator
	 */
	abstract public function getIterator();

}

/**
 * CSV file bulk loading source
 */
class CsvBulkLoaderSource extends BulkLoaderSource{

	protected $filepath;

	protected $delimiter = ',';

	protected $enclosure = '"';

	protected $hasheader = true;

	public function setFilePath($path) {
		$this->filepath = $path;

		return $this;
	}

	public function setFieldDelimiter($delimiter) {
		$this->delimiter = $delimiter;

		return $this;
	}

	public function setFieldEnclosure($enclosure) {
		$this->enclosure = $enclosure;

		return $this;
	}

	public function setHasHeader($hasheader) {
		$this->hasheader = $hasheader;

		return $this;
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

		// ColumnMap has two uses, depending on whether hasheader is set
		if($colmap = $this->loader->columnMap) {
			// if the map goes to a callback, use the same key value as the map
			// value, rather than function name as multiple keys may use the 
			// same callback
			$map = array();
			foreach($colmap as $k => $v) {
				$map[$k] = (strpos($v, "->") === 0) ? $k : $v;
			}
			if($this->hasheader) {
				$parser->mapColumns($map);
			} else {
				$parser->provideHeaderRow($map);
			}
		}

		return $parser;
	}

}
