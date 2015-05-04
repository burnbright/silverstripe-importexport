<?php

/**
 * @package framework
 * @subpackage tests
 */
class BetterBulkLoaderTest extends SapphireTest {

	protected static $fixture_file = 'importexport/tests/fixtures/BetterBulkLoaderTest.yaml';

	protected $extraDataObjects = array(
		'BetterBulkLoaderTest_Team',
		'BetterBulkLoaderTest_Player',
		'BetterBulkLoaderTest_PlayerContract',
	);

	public function testMappableColumns() {
		$loader = new CsvBetterBulkLoader('BetterBulkLoaderTest_Player');
		$columns = $loader->getMappableColumns();

		$this->markTestIncomplete("Finish me!");
	}

	public function testSkipRecords() {
		$this->markTestIncomplete("Finish me!");
	}

	/**
	 * Test plain import with column auto-detection
	 */
	public function testLoad() {
		$loader = new CsvBetterBulkLoader('BetterBulkLoaderTest_Player');
		$filepath = FRAMEWORK_PATH . '/tests/dev/CsvBulkLoaderTest_PlayersWithHeader.csv';
		$file = fopen($filepath, 'r');
		$compareCount = $this->getLineCount($file);
		fgetcsv($file); // pop header row
		$compareRow = fgetcsv($file);

		$results = $loader->load($filepath);
	
		// Test that right amount of columns was imported
		$this->assertEquals(4, $results->Count(), 'Test correct count of imported data');
		
		// Test that columns were correctly imported
		$obj = DataObject::get_one("BetterBulkLoaderTest_Player", "\"FirstName\" = 'John'");
		$this->assertNotNull($obj);
		$this->assertEquals("He's a good guy", $obj->Biography);
		$this->assertEquals("1988-01-31", $obj->Birthday);
		$this->assertEquals("1", $obj->IsRegistered);
		
		fclose($file);
	}

	/**
	 * Test import with manual column mapping
	 */
	public function testLoadWithColumnMap() {
		$loader = new CsvBetterBulkLoader('BetterBulkLoaderTest_Player');
		$filepath = FRAMEWORK_PATH . '/tests/dev/CsvBulkLoaderTest_Players.csv';
		$file = fopen($filepath, 'r');
		$compareCount = $this->getLineCount($file);
		$compareRow = fgetcsv($file);
		$loader->columnMap = array(
			'FirstName',
			'Biography',
			null, // ignored column
			'Birthday',
			'IsRegistered'
		);
		$loader->hasHeaderRow = false;
		$results = $loader->load($filepath);
	
		// Test that right amount of columns was imported
		$this->assertEquals(4, $results->Count(), 'Test correct count of imported data');
		
		// Test that columns were correctly imported
		$obj = DataObject::get_one("BetterBulkLoaderTest_Player", "\"FirstName\" = 'John'");
		$this->assertNotNull($obj);
		$this->assertEquals("He's a good guy", $obj->Biography);
		$this->assertEquals("1988-01-31", $obj->Birthday);
		$this->assertEquals("1", $obj->IsRegistered);
		
		$obj2 = DataObject::get_one('BetterBulkLoaderTest_Player', "\"FirstName\" = 'Jane'");
		$this->assertNotNull($obj2);
		$this->assertEquals('0', $obj2->IsRegistered);
		
		fclose($file);
	}
	
	/** 
	 * Test plain import with clear_table_before_import  
	 */ 
	public function testDeleteExistingRecords() {
		$loader = new CsvBetterBulkLoader('BetterBulkLoaderTest_Player'); 
		$filepath = FRAMEWORK_PATH . '/tests/dev/CsvBulkLoaderTest_PlayersWithHeader.csv'; 
		$loader->deleteExistingRecords = true;
		$results1 = $loader->load($filepath);
		$this->assertEquals(4, $results1->Count(),
			'Test correct count of imported data on first load'
		); 
	
		//delete existing data before doing second CSV import 
		$results2 = $loader->load($filepath, '512MB', true);
		//get all instances of the loaded DataObject from the database and count them
		$resultDataObject = DataObject::get('BetterBulkLoaderTest_Player');  
	
		$this->assertEquals(4, $resultDataObject->Count(),
			'Test if existing data is deleted before new data is added'
		); 
	}
	
	/**
	 * Test import with manual column mapping and custom column names
	 */
	public function testLoadWithCustomHeaderAndRelation() {
		$loader = new CsvBetterBulkLoader('BetterBulkLoaderTest_Player');
		$filepath = FRAMEWORK_PATH . '/tests/dev/CsvBulkLoaderTest_PlayersWithCustomHeaderAndRelation.csv';
		$file = fopen($filepath, 'r');
		$compareCount = $this->getLineCount($file);
		fgetcsv($file); // pop header row
		$compareRow = fgetcsv($file);
		//set the correct order of relation fields
		$loader->mappableFields = array(
			'FirstName' => 'First Name',
			'Biography' => 'Bio',
			'Birthday' => 'Birthday',
			'Team.Title' => 'Team',
			'Team.TeamSize' => 'Team Size',
			'Contract.Amount' => 'Contract Amount'
 		);
		$loader->columnMap = array(
			'first name' => 'FirstName',
			'bio' => 'Biography',
			'bday' => 'Birthday',
			'teamtitle' => 'Team.Title', // test existing relation
			'teamsize' => 'Team.TeamSize', // test existing relation
			'salary' => 'Contract.Amount' // test relation creation
		);
		$loader->hasHeaderRow = true;
		$loader->transforms = array(
			'Team.Title' => array(
				'relationname' => 'Team',
				'callback' => function ($title) {
					return BetterBulkLoaderTest_Team::get()
							->filter("Title", $title)
							->first();
				}
			)
			// contract should be automatically discovered
		);
		$results = $loader->load($filepath);
		
		// Test that right amount of columns was imported
		$this->assertEquals(1, $results->Count(), 'Test correct count of imported data');
		
		// Test of augumenting existing relation (created by fixture)
		$allTeams = BetterBulkLoaderTest_Team::get('BetterBulkLoaderTest_Team');
		$this->assertEquals(1, $allTeams->count(), "There are now two teams total");
		$testTeam = $allTeams->filter("Title", "My Team")->first();
		$this->assertNotNull($testTeam, "My Team exists");
		$this->assertEquals('20', $testTeam->TeamSize, 'Augumenting existing has_one relation works');
		
		// Test of creating relation
		$testContract = BetterBulkLoaderTest_PlayerContract::get()->first();
		$this->assertNotNull($testContract, "Contract object exists");
		$testPlayer = BetterBulkLoaderTest_Player::get()->filter("FirstName",'John')->first();
		$this->assertNotNull($testPlayer, "Player John exists");
		$this->assertEquals($testPlayer->ContractID, $testContract->ID, 'Creating new has_one relation works');
		
		// Test nested setting of relation properties
		$contractAmount = DBField::create_field('Currency', $compareRow[5])->RAW();
		$this->assertEquals($testPlayer->Contract()->Amount, $contractAmount,
			'Setting nested values in a relation works');
		
		fclose($file);
	}
	
	/**
	 * Test import with custom identifiers by importing the data.
	 * 
	 * @todo Test duplicateCheck callbacks
	 */
	public function testLoadWithIdentifiers() {
		// first load
		$loader = new CsvBetterBulkLoader('BetterBulkLoaderTest_Player');
		$filepath = FRAMEWORK_PATH . '/tests/dev/CsvBulkLoaderTest_PlayersWithId.csv';
		$loader->duplicateChecks = array(
			'ExternalIdentifier' => 'ExternalIdentifier',
			'NonExistantIdentifier' => 'ExternalIdentifier',
			'ExternalIdentifier' => 'ExternalIdentifier',
			'AdditionalIdentifier' => 'ExternalIdentifier'
		);
		$results = $loader->load($filepath);
		$createdPlayers = $results->Created();

		$player = $createdPlayers->First();
		$this->assertEquals($player->FirstName, 'John');
		$this->assertEquals($player->Biography, 'He\'s a good guy',
			'test updating of duplicate imports within the same import works');

		// load with updated data
		$filepath = FRAMEWORK_PATH . '/tests/dev/CsvBulkLoaderTest_PlayersWithIdUpdated.csv';
		$results = $loader->load($filepath);
		
		// HACK need to update the loaded record from the database
		$player = DataObject::get_by_id('BetterBulkLoaderTest_Player', $player->ID);
		$this->assertEquals($player->FirstName, 'JohnUpdated', 'Test updating of existing records works');

		// null values are valid imported
		// $this->assertEquals($player->Biography, 'He\'s a good guy',
		//	'Test retaining of previous information on duplicate when overwriting with blank field');
	}

	public function testDotNotationDuplicateChecks() {
		$this->markTestIncomplete("FINISH ME");
	}
	
	public function testLoadWithCustomImportMethods() {
		$loader = new BetterBulkLoaderTest_CustomLoader('BetterBulkLoaderTest_Player');
		$filepath = FRAMEWORK_PATH . '/tests/dev/CsvBulkLoaderTest_PlayersWithHeader.csv';
		$loader->columnMap = array(
			'FirstName' => '->importFirstName',
			'Biography' => 'Biography', 
			'Birthday' => 'Birthday',
			'IsRegistered' => 'IsRegistered'
		);
		$results = $loader->load($filepath);
		$createdPlayers = $results->Created();
		$player = $createdPlayers->First();
		$this->assertEquals($player->FirstName, 'Customized John');
		$this->assertEquals($player->Biography, "He's a good guy");
		$this->assertEquals($player->IsRegistered, "1");
	}
	
	public function testLoadWithCustomImportMethodDuplicateMap() {
		$loader = new BetterBulkLoaderTest_CustomLoader('BetterBulkLoaderTest_Player');
		$filepath = FRAMEWORK_PATH . '/tests/dev/CsvBulkLoaderTest_PlayersWithHeader.csv';
		$loader->columnMap = array(
			'FirstName' => '->updatePlayer',
			'Biography' => '->updatePlayer', 
			'Birthday' => 'Birthday',
			'IsRegistered' => 'IsRegistered'
		);
		$results = $loader->load($filepath);

		$createdPlayers = $results->Created();
		$player = $createdPlayers->First();

		$this->assertEquals($player->FirstName, "John. He's a good guy. ");
	}

	protected function getLineCount(&$file) {
		$i = 0;
		while(fgets($file) !== false) $i++;
		rewind($file);
		return $i;
	}
	
}

class BetterBulkLoaderTest_CustomLoader extends CsvBulkLoader implements TestOnly {
	
	public function importFirstName(&$obj, $val, $record) {
		$obj->FirstName = "Customized {$val}";
	}

	public function updatePlayer(&$obj, $val, $record) {
		$obj->FirstName .= $val . '. ';
	}
}

class BetterBulkLoaderTest_Team extends DataObject implements TestOnly {
	
	private static $db = array(
		'Title' => 'Varchar(255)',
		'TeamSize' => 'Int',
	);	
	
	private static $has_many = array(
		'Players' => 'BetterBulkLoaderTest_Player',
	);

}

class BetterBulkLoaderTest_Player extends DataObject implements TestOnly {

	private static $db = array(
		'FirstName' => 'Varchar(255)',
		'Biography' => 'HTMLText',
		'Birthday' => 'Date',
		'ExternalIdentifier' => 'Varchar(255)', // used for uniqueness checks on passed property
		'IsRegistered' => 'Boolean'
	);
	
	private static $has_one = array(
		'Team' => 'BetterBulkLoaderTest_Team',
		'Contract' => 'BetterBulkLoaderTest_PlayerContract'
	);

	protected function validate() {
		$result = parent::validate();
		if(!$this->FirstName){
			$result->error("Players must have a FirstName");
		}
		return $result;
	}
	
	/**
	 * Custom setter for "Birthday" property when passed/imported
	 * in different format.
	 *
	 * @param string $val
	 * @param array $record
	 */
	public function setUSBirthday($val, $record = null) {
		$this->Birthday = preg_replace('/^([0-9]{1,2})\/([0-9]{1,2})\/([0-90-9]{2,4})/', '\\3-\\1-\\2', $val);
	}

}

class BetterBulkLoaderTest_PlayerContract extends DataObject implements TestOnly {

	private static $db = array(
		'Amount' => 'Currency',
	);

}
