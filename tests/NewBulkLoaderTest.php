<?php

class NewBulkLoaderTest extends SapphireTest{

	protected static $fixture_file = 'importexport/tests/fixtures/NewBulkLoaderTest.yaml';

	protected $extraDataObjects = array(
		'NewBulkLoaderTest_Course',
		'NewBulkLoaderTest_CourseSelection'
	);
	
	//test the default loading behaviour
	public function testRelationDuplicatesDefaultBehaviour(){
		$data = array(
			array("Course.Title" => "Math 101"), //existing record
			array("Course.Title" => "Geometry 722") //does not exist
		);
		$source = new ArrayBulkLoaderSource($data);

		$loader = new BetterBulkLoader("NewBulkLoaderTest_CourseSelection");
		$loader->setSource($source);
		$results = $loader->load();

		$this->assertEquals($results->CreatedCount(), 2);
		$this->assertEquals($results->UpdatedCount(), 0);
		$this->assertEquals($results->DeletedCount(), 0);
		$this->assertEquals($results->SkippedCount(), 0);
		$this->assertEquals($results->Count(), 2);
	}

	//TODO: configurations matrix
	//always create, only unique, or don't create new records
	

}

class NewBulkLoaderTest_Course extends DataObject implements TestOnly{
	
	private static $db = array(
		"Title" => "Varchar"
	);
}

class NewBulkLoaderTest_CourseSelection extends DataObject implements TestOnly{

	private static $has_one = array(
		"Course" => "NewBulkLoaderTest_Course"
	);

}
