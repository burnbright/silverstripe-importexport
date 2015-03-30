<?php

/**
 * Array Bulk Loader Source
 * Useful for testing bulk loader. The output is the same as input.
 */
class ArrayBulkLoaderSource extends BulkLoaderSource{

	protected $data;

	public function __construct($data) {
		$this->data = $data;
	}

	public function getIterator() {
		return $this->data;
	}

	public function setData($data){
		$this->data = $data;

		return $this;
	}

	public function getData(){
		return $this->data;
	}

}
