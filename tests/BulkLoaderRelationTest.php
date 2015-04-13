<?php

class BulkLoaderRelationTest extends SapphireTest{

	protected static $fixture_file = 'importexport/tests/fixtures/BulkLoaderRelationTest.yaml';

	protected $extraDataObjects = array(
		'BulkLoaderRelationTest_CourseSelection',
		'BulkLoaderRelationTest_Course'
	);

	protected $loader;
	
	//use the same source for all tests
	public function setUp(){
		parent::setUp();
		$data = array(
			 //unlinked relation, no record
			array("Course.Title" => "Math 101", "Term" => 1),
			 //existing relation and record
			array("Course.Title" => "Tech 102", "Term" => 1),
			 //relation does not exist, no record
			array("Course.Title" => "Geometry 722", "Term" => 1)
		);
		$this->loader = new BetterBulkLoader("BulkLoaderRelationTest_CourseSelection");
		$this->loader->setSource(
			new ArrayBulkLoaderSource($data)
		);
	}

	/**
	 * This default behaviour should act the same as
	 * testLinkAndCreateRelations
	 */
	public function testEmptyBehaviour() {
		$results = $this->loader->load();
		$this->assertEquals(3, $results->CreatedCount(),
			"objs have been created from all records");
		$this->assertEquals(4, BulkLoaderRelationTest_Course::get()->count(),
			"New Geometry 722 course created");
		$this->assertEquals(4, BulkLoaderRelationTest_CourseSelection::get()
					->filter("CourseID:GreaterThan", 0)->count(),
				"we have gone from 1 to 4 linked records");
	}

	public function testLinkAndCreateRelations() {
		$this->loader->transforms['Course.Title'] = array(
			'link' => true,
			'create' => true
		);
		$results = $this->loader->load();
		$this->assertEquals(3, $results->CreatedCount(),
			"objs have been created from all records");
		$this->assertEquals(4, BulkLoaderRelationTest_Course::get()->count(),
			"New Geometry 722 course created");
		$this->assertEquals(4, BulkLoaderRelationTest_CourseSelection::get()
					->filter("CourseID:GreaterThan", 0)->count(),
				"we have gone from 1 to 4 linked records");
	}

	public function testNoRelations() {
		$this->loader->transforms['Course.Title'] = array(
			'link' => false,
			'create' => false
		);
		$results = $this->loader->load();
		$this->assertEquals(3, $results->CreatedCount(),
			"objs have been created from all records");
		$this->assertEquals(3, BulkLoaderRelationTest_Course::get()->count(),
			"No extra courses created");
		$this->assertEquals(1, BulkLoaderRelationTest_CourseSelection::get()
					->filter("CourseID:GreaterThan", 0)->count(),
			"No records have been linked");
	}

	public function testOnlyLinkRelations() {
		$this->loader->transforms['Course.Title'] = array(
			'link' => true,
			'create' => false
		);
		$results = $this->loader->load();
		$this->assertEquals(3, $results->CreatedCount(),
			"objs have been created from all records");
		$this->assertEquals(3, BulkLoaderRelationTest_Course::get()->count(),
			"number of courses remains the same");
		//asserting 3 and not 2 because we have no duplicate checks
		$this->assertEquals(3, BulkLoaderRelationTest_CourseSelection::get()
					->filter("CourseID:GreaterThan", 0)->count(),
				"we have gone from 1 to 3 linked records");
	}

	public function testOnlyCreateUniqueRelations() {
		$this->loader->transforms['Course.Title'] = array(
			'link' => false,
			'create' => true
		);
		$results = $this->loader->load();
		$this->assertEquals(3, $results->CreatedCount(),
			"objs have been created from all records");
		$this->assertEquals(4, BulkLoaderRelationTest_Course::get()->count(),
			"New Geometry 722 course created");
		$this->assertEquals(2, BulkLoaderRelationTest_CourseSelection::get()
					->filter("CourseID:GreaterThan", 0)->count(),
				"Only the created object is linked");
	}

	public function testRelationDuplicateCheck() {
		$this->loader->transforms['Course.Title'] = array(
			'link' => true,
			'create' => true
		);
		$this->loader->duplicateChecks = array(
			"Course.Title"
		);
		$results = $this->loader->load();
		$this->assertEquals(2, $results->CreatedCount(), "2 created");
		$this->assertEquals(0, $results->SkippedCount(), "0 skipped");
		$this->assertEquals(1, $results->UpdatedCount(), "1 updated");

		$this->markTestIncomplete("test using {RelationName}ID and {RelationName}");
	}

	public function testRelationList() {
		$list = new ArrayList();
		$this->loader->transforms['Course.Title'] = array(
			'create' => true,
			'link' => true,
			'list' => $list
		);
		$results = $this->loader->load();
		$this->assertEquals(3, $results->CreatedCount(), "3 records created");
		$this->assertEquals(3, $list->count(), "3 relations created");

		//make sure re-run doesn't change relation list
		$results = $this->loader->load();
		$this->assertEquals(3, $results->CreatedCount(), "3 more records created");
		$this->assertEquals(3, $list->count(), "relation list count remains the same");
	}

	public function testRequiredRelation() {
		$this->markTestIncomplete("Required relations should be checked");
	}

}

//primary object we are loading records into
class BulkLoaderRelationTest_CourseSelection extends DataObject implements TestOnly{

	private static $db = array(
		"Term" => "Int"
	);

	private static $has_one = array(
		"Course" => "BulkLoaderRelationTest_Course"
	);

}

//related object
class BulkLoaderRelationTest_Course extends DataObject implements TestOnly{
	
	private static $db = array(
		"Title" => "Varchar"
	);
}
