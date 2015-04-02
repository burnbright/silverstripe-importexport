<?php

class BulkLoaderRelationTest extends SapphireTest{

	protected static $fixture_file = 'importexport/tests/fixtures/BulkLoaderRelationTest.yaml';

	protected $extraDataObjects = array(
		'BulkLoaderRelationTest_Course',
		'BulkLoaderRelationTest_CourseSelection'
	);

	protected $loader;
	
	//use the same source for all tests
	public function setUp(){
		parent::setUp();
		$data = array(
			array("Course.Title" => "Math 101"),
			array("Course.Title" => "Tech 102"), //existing record
			array("Course.Title" => "Geometry 722") //relation does not exist
		);
		$this->loader = new BetterBulkLoader("BulkLoaderRelationTest_CourseSelection");
		$this->loader->setSource(
			new ArrayBulkLoaderSource($data)
		);
	}

	//this is the default behaviour
	public function testCreateAllRelations() {
		$results = $this->loader->load();
		$this->assertEquals($results->CreatedCount(), 3);
		$this->assertEquals($results->UpdatedCount(), 0);
		$this->assertEquals($results->DeletedCount(), 0);
		$this->assertEquals($results->SkippedCount(), 0);
		$this->assertEquals($results->Count(), 3);
	}

	public function testLinkAndCreateRelations() {
		$this->loader->transforms['Course.Title'] = array(
			'link' => true,
			'create' => true
		);
		$this->loader->duplicateChecks['Course.Title'] = 'Course.Title';
		$results = $this->loader->load();
		$this->assertEquals($results->CreatedCount(), 1);
		$this->assertEquals($results->UpdatedCount(), 2);
		$this->assertEquals($results->DeletedCount(), 0);
		$this->assertEquals($results->SkippedCount(), 0);
		$this->assertEquals($results->Count(), 3);
	}

	public function testOnlyLinkRelations() {
		$this->loader->transforms['Course.Title'] = array(
			'link' => true,
			'create' => false
		);
		$this->loader->duplicateChecks['Course.Title'] = 'Course.Title';
		$results = $this->loader->load();
		$this->assertEquals($results->CreatedCount(), 0);
		$this->assertEquals($results->UpdatedCount(), 2);
		$this->assertEquals($results->DeletedCount(), 0);
		$this->assertEquals($results->SkippedCount(), 0);
		$this->assertEquals($results->Count(), 2);
	}

	public function testOnlyCreateUniqueRelations() {
		$this->loader->transforms['Course.Title'] = array(
			'link' => false,
			'create' => true
		);
		$this->loader->duplicateChecks['Course.Title'] = 'Course.Title';
		$results = $this->loader->load();
		$this->assertEquals($results->CreatedCount(), 1);
		$this->assertEquals($results->UpdatedCount(), 0);
		$this->assertEquals($results->DeletedCount(), 0);
		$this->assertEquals($results->SkippedCount(), 0);
		$this->assertEquals($results->Count(), 1);
	}

}

class BulkLoaderRelationTest_Course extends DataObject implements TestOnly{
	
	private static $db = array(
		"Title" => "Varchar"
	);
}

class BulkLoaderRelationTest_CourseSelection extends DataObject implements TestOnly{

	private static $has_one = array(
		"Course" => "BulkLoaderRelationTest_Course"
	);

}
