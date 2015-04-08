<?php

class ListBulkLoaderTest extends SapphireTest{

	protected $extraDataObjects = array(
		'ListBulkLoaderTest_Person'
	);

	public function testImport() {
		$parent = new ListBulkLoaderTest_Person(
			array("Name" => "George", "Age" => 55)
		);
		$parent->write();

		//add one existing child
		$existingchild = new ListBulkLoaderTest_Person(
			array("Name" => "Xavier", "Age" => 13)
		);
		$existingchild->write();
		$parent->Children()->add($existingchild);

		$loader = new ListBulkLoader($parent->Children());
		$loader->duplicateChecks = array(
			"Name"
		);

		$source = new ArrayBulkLoaderSource(array(
			array(), //skip record
			array("Name" => "Martha", "Age" => 1), //new record
			array("Name" => "Xavier", "Age" => 16), //update record
			array("Name" => "Joanna", "Age" => 3), //new record
			"" //skip record
		));
		$loader->setSource($source);
		$result = $loader->load();
		$this->assertEquals(2, $result->SkippedCount(), "Records skipped");
		$this->assertEquals(2, $result->CreatedCount(), "Records created");
		$this->assertEquals(1, $result->UpdatedCount(), "Record updated");
		$this->assertEquals(3, $result->Count(), "Records imported");
		$this->assertEquals(4, ListBulkLoaderTest_Person::get()->count(), "Total DataObjects is now 4");
		$this->assertEquals(3, $parent->Children()->count(), "Parent has 3 children");
	}

	public function testDeleteExisting() {

		$this->markTestIncomplete("test deletion");

		//data list should be emptied
		//should not delete unrelated records

	}

}

class ListBulkLoaderTest_Person extends DataObject implements TestOnly{

	private static $db = array(
		"Name" => "Varchar",
		"Age" => "Int"
	);

	private static $has_one = array(
		"Parent" => "ListBulkLoaderTest_Person"
	);

	private static $has_many = array(
		"Children" => "ListBulkLoaderTest_Person"
	);

}
