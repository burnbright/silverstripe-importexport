<?php

class ArrayBulkLoaderSourceTest extends SapphireTest{
	
	public function testIterator(){

		$data = array(
			array("First" => 1),
			array("First" => 2)
		);
		$source = new ArrayBulkLoaderSource($data);
		$iterator = $source->getIterator();
		$this->assertEquals(2, count($iterator));
	}

}