<?php

class CsvBulkLoaderSourceTest extends SapphireTest{

	public function testConfiguration(){
		$source = new CsvBulkLoaderSource();
		$source->setFilePath("asdf.csv")
			->setFieldDelimiter("|")
			->setFieldEnclosure(":")
			->setHasHeader(false);
		$this->assertEquals("asdf.csv",$source->getFilePath());
		$this->assertEquals("|",$source->getFieldDelimiter());
		$this->assertEquals(":",$source->getFieldEnclosure());
		$this->assertEquals(false,$source->getHasHeader());
	}

	public function testNoHeaderFile() {
		$source = new CsvBulkLoaderSource();
		$source->setFilePath(dirname(__FILE__)."/fixtures/Players.csv")
			->setHasHeader(false);

		$rowassertions = array(
			array("John", "He's a good guy", "ignored", "31/01/1988", "1"),
			array("Jane", "She is awesome.\\nSo awesome that she gets multiple rows and \\\"escaped\\\" strings in her biography", "ignored", "31/01/1982", "0"),
			array("Jamie","Pretty old\, with an escaped comma","ignored","31/01/1882","1"),
			array("Järg","Unicode FTW","ignored","31/06/1982","1"),
			//empty rows are skipped by default
			array("","nobio missing data","ignored")
		);

		$iterator = $source->getIterator();
		$count = 0;
		foreach($iterator as $record){
			$this->assertEquals(
				$rowassertions[$count],
				$record,
				"Row $count is valid"
			);
			$count++;
		}
	}

	/**
	 * @group testme
	 */
	public function testWithHeaderFile() {
		$source = new CsvBulkLoaderSource();
		$source->setFilePath(dirname(__FILE__)."/fixtures/Players_WithHeader.csv")
			->setHasHeader(true);

		$rowassertions = array(
			array("FirstName"=>"John", "Biography"=>"He's a good guy", "Ignore"=>"ignored", "Birthday"=>"31/01/1988", "IsRegistered"=>"1"),
			array("FirstName"=>"Jane", "Biography"=>"She is awesome.\\nSo awesome that she gets multiple rows and \\\"escaped\\\" strings in her biography", "Ignore"=>"ignored", "Birthday"=>"31/01/1982", "IsRegistered"=>"0"),
			array("FirstName"=>"Jamie","Biography"=>"Pretty old\, with an escaped comma","Ignore"=>"ignored","Birthday"=>"31/01/1882","IsRegistered"=>"1"),
			array("FirstName"=>"Järg","Biography"=>"Unicode FTW","Ignore"=>"ignored","Birthday"=>"31/06/1982","IsRegistered"=>"1"),
			//empty rows are skipped by default
			array("FirstName"=>"","Biography"=>"nobio missing data","Ignore"=>"ignored")
		);

		$iterator = $source->getIterator();
		$count = 0;
		foreach($iterator as $record){
			$this->assertEquals(
				$rowassertions[$count],
				$record,
				"Row $count is valid"
			);
			$count++;
		}
		
		//assert header is correct
		$this->assertEquals(
			$source->getFirstRow(),
			array("FirstName","Biography","Ignore","Birthday","IsRegistered")
		);
	}
	
}
