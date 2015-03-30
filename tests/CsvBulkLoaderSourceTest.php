<?php

class CsvBulkLoaderSourceTest extends SapphireTest{
	
	public function testConfiguration(){
		$source = new CsvBulkLoaderSource();
		$source->setFilePath("asdf.csv")
			->setFieldDelimiter("|")
			->setFieldEnclosure(":")
			->setHasHeader(false)
			->setColumnMap(array("ASDF" => "asdf"));

		$this->assertEquals("asdf.csv",$source->getFilePath());
		$this->assertEquals("|",$source->getFieldDelimiter());
		$this->assertEquals(":",$source->getFieldEnclosure());
		$this->assertEquals(false,$source->getHasHeader());
		$this->assertEquals(array("ASDF" => "asdf"),$source->getColumnMap());
	}

	//TODO: test iterator
	
}