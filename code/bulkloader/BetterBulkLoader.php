<?php
/**
 * The bulk loader allows large-scale uploads to SilverStripe via the ORM.
 *
 * Data comes from a given BulkLoaderSource, providing an iterator of records.
 *
 * Incoming data can be mapped to fields, based on a given mapping,
 * and it can be used to find or create related objects to be linked to.
 *
 * Failed record imports will be marked as skipped.
 */
class BetterBulkLoader extends BulkLoader {

	/**
	 * Bulk loading source
	 * @var BulkLoaderSource
	 */
	protected $source;

	/**
	 * Specify a colsure to be run on every imported record.
	 * @var Closure
	 */
	public $recordCallback;

	/**
	 * Write new relations to DB when they don't exist.
	 * @var boolean
	 */
	protected $writeNewRelations = true;

	/**
	 * Set the BulkLoaderSource for this BulkLoader.
	 * @param BulkLoaderSource $source
	 */
	public function setSource(BulkLoaderSource $source) {
		$this->source = $source;

		return $this;
	}

	/**
	 * Get the BulkLoaderSource for this BulkLoader
	 * @return BulkLoaderSource $source
	 */
	public function getSource() {
		return $this->source;
	}

	public function load($filepath = null) {
		//TODO: remove this stuff out?
		increase_time_limit_to(3600);
		increase_memory_limit_to('512M');
		
		if($this->deleteExistingRecords) {
			//TODO: report on number of records deleted
			$this->deleteExistingRecords();
		}

		return $this->processAll($filepath);
	}

	public function deleteExistingRecords(){
		DataObject::get($this->objectClass)->removeAll();
	}

	/**
	 * Import all records from the source.
	 * 
	 * @param  string  $filepath
	 * @param  boolean $preview 
	 * @return BulkLoader_Result
	 */
	protected function processAll($filepath, $preview = false) {
		if(!$this->source){
			user_error("No source has been configured for the bulk loader",
				E_USER_WARNING
			);
		}
		$results = new BetterBulkLoader_Result();
		$iterator = $this->getSource()->getIterator();
		foreach($iterator as $record) {
			$this->processRecord($record, $this->columnMap, $results, $preview);
		}
		
		return $results;
	}

	/**
	 * Import an individual record from the source.
	 * 
	 * @param  array  $record
	 * @param  array  $columnMap
	 * @param  BulkLoader_Result  &$results
	 * @param  boolean $preview 
	 * @return int|null
	 */
	protected function processRecord($record, $columnMap, &$results, $preview = false) {
		if(!$this->validateRecord($record)){
			$results->addSkipped("Empty/invalid record data.");
			return;
		}
		//map incoming record according to the columnMap
		$record = $this->columnMapRecord($record);

		// find existing object, or create new one
		$existingObj = $this->findExistingObject($record);

		$class = $this->objectClass;
		$obj = ($existingObj) ? $existingObj : new $class();

		if($this->recordCallback){
			$recordCallback = $this->recordCallback;
			$recordCallback($obj);
		}
		
		// first run: find/create any relations and store them on the object
		// we can't combine runs, as other columns might rely on the relation being present
		foreach($record as $fieldName => $val) {
			// don't bother querying of value is not set
			if($this->isNullValue($val)){
				continue;
			}
			$relationName = $this->getRelationName($fieldName);
			//don't proceed any further if relation does not exit on obj
			if(!$obj->getRelationClass($relationName)) {
				continue;
			}
			//get the relation object
			$relationObj = null;
			if(isset($this->relationCallbacks[$fieldName]['callback'])){
					$method = $this->relationCallbacks[$fieldName]['callback'];
				if($this->hasMethod($method)) {
					$relationObj = $this->{$method}($obj, $val, $record);
				} elseif($obj->hasMethod($method)) {
					$relationObj = $obj->{$method}($val, $record);
				}
			}else{
				$relationComponent = $obj->getComponent($relationName);
				if($relationComponent->exists()){
					$relationObj = $relationComponent;
				}
			}
			//set relation id on obj
			if($relationObj){
				//write new relation to db
				if (
					($this->writeNewRelations || (
						isset($this->relationCallbacks[$fieldName]['writeNew']) && 
						$this->relationCallbacks[$fieldName]['writeNew']
					)) && 
					!$relationObj->isInDB() && 
					!$preview
				){
					$relationObj->write();
				}
				$obj->{"{$relationName}ID"} = $relationObj->ID;
			}
		}

		// second run: save data
		foreach($record as $fieldName => $val) {
			// break out of the loop if we are previewing
			if ($preview) {
				break;
			}
			// look up the mapping to see if this needs to map to callback
			$mapped = $this->columnMap && isset($this->columnMap[$fieldName]);
			if($mapped && strpos($this->columnMap[$fieldName], '->') === 0) {
				$funcName = substr($this->columnMap[$fieldName], 2);
				$this->$funcName($obj, $val, $record);
			} else if($obj->hasMethod("import{$fieldName}")) {
				$obj->{"import{$fieldName}"}($val, $record);
			} else {
				//DataObject update method supports the dot notation
				$obj->update(array($fieldName => $val));
			}
		}

		$changed = $obj->isChanged();
		try{
			// write record
			($preview) ? 0 : $obj->write();
			// save to results
			if($existingObj) {
				if($changed){
					$results->addUpdated($obj);
				}else{
					$results->addSkipped("No data was changed.");
				}
			} else {
				$results->addCreated($obj);
			}
		}catch(ValidationException $e) {
			$results->addSkipped($e->getMessage());
		}

		$objID = $obj->ID;
		// reduce memory usage
		$obj->destroy();
		unset($existingObj);
		unset($obj);
		
		return $objID;
	}

	/**
	 * Convert the record's keys to appropriate columnMap keys.
	 * @return array record
	 */
	protected function columnMapRecord($record){
		$adjustedmap = $this->getAdjustedMap();

		$newrecord = array();
		foreach($record as $field => $value){
			if(isset($adjustedmap[$field])){
				$newrecord[$adjustedmap[$field]] = $value;
			}else{
				$newrecord[$field] = $value;
			}
		}

		return $newrecord;
	}

	/**
	 * If the mapping goes to a "->" callback,
	 * then 
	 * 
	 * if the map goes to a callback, use the same key value as the map
	 * value, rather than function name as multiple keys may use the 
	 * same callback
	 */
	protected function getAdjustedMap(){
		$map = array();
		foreach($this->columnMap as $k => $v) {
			if(strpos($v, "->") === 0) {
				$map[$k] = $k;
			} else {
				$map[$k] = $v;
			}
		}

		return $map;
	}

	/**
	 * Basic record checks to ensure they conform to
	 * expected BulkLoaderSource format.
	 * 
	 * @param  array $record
	 * @return boolean
	 */
	protected function validateRecord($record){
		if(!is_array($record)){
			return false;
		}
		if(empty($record)){
			return false;
		}

		return true;
	}

	/**
	 * Given a record field name, find out if this is a relation name
	 * and return the name.
	 * @param string
	 * @return string
	 */
	protected function getRelationName($recordField) {
		$relationName = null;
		if(isset($this->relationCallbacks[$recordField])){
			$relationName = $this->relationCallbacks[$recordField]['relationname'];
		}
		if(strpos($recordField, '.') !== false){
			list($relationName, $columnName) = explode('.', $recordField);
		}

		return $relationName;
	}

	/**
	 * Find an existing objects based on one or more uniqueness columns 
	 * specified via {@link self::$duplicateChecks}.
	 *
	 * @param array $record data
	 *
	 * @return mixed
	 */
	public function findExistingObject($record) {
		$class = $this->objectClass;
		$singleton = singleton($class);
		// checking for existing records (only if not already found)
		foreach($this->duplicateChecks as $fieldName => $duplicateCheck) {

			if(is_string($duplicateCheck)) {
				$field = Convert::raw2sql($duplicateCheck);
				if(!isset($record[$field]) || empty($record[$field])) {
					//skip current duplicate check if field value is empty
					continue;
				}
				$existingRecord = $class::get()
									->filter($field, $record[$field])
									->first();

				if($existingRecord) {
					return $existingRecord;
				}
			} elseif(is_array($duplicateCheck) && isset($duplicateCheck['callback'])) {
				$callback = $duplicateCheck['callback'];
				if($this->hasMethod($callback)) {
					$existingRecord = $this->{$callback}($record[$fieldName], $record);
				} elseif($singleton->hasMethod($callback)) {
					$existingRecord = $singleton->{$callback}($record[$fieldName], $record);
				} else {
					user_error("BulkLoader::processRecord():"
							. " {$duplicateCheck['callback']} not found on importer or object class.",
						E_USER_WARNING
					);
				}
				if($existingRecord) {
					return $existingRecord;
				}
			}else {
				user_error('BulkLoader::processRecord(): Wrong format for $duplicateChecks',
					E_USER_WARNING
				);
			}
		}

		return false;
	}

	/**
	 * Get the field-label mapping of fields that data can be mapped into.
	 * @return array
	 */
	public function getMappableColumns() {

		//TODO: allow defining a subset of allowed mappings/columns
		//extract fields from:
			//column map
			//relationcallbacks
			//duplicate checks
			//..and get human readable titles
		
		return $this->scaffoldMappableFields();
	}

	/**
	 * Generate a field-label list of fields that data can be mapped into.
	 * @param $includerelations
	 * @return array
	 */
	public function scaffoldMappableFields($includerelations = true) {
		$map = $this->getMappableFieldsForClass($this->objectClass);
		//set up 'dot notation' (Relation.Field) style mappings
		if($includerelations){
			if($has_ones = singleton($this->objectClass)->has_one()){
				foreach($has_ones as $relationship => $type){
					$fields = $this->getMappableFieldsForClass($type);
					foreach($fields as $field => $title){
						$map[$relationship.".".$field] = 
							$this->formatMappingFieldLabel($relationship,$title);
					}
				}
			}
			if($has_manys = singleton($this->objectClass)->has_one()){
				foreach($has_manys as $relationship => $type){
					$fields = $this->getMappableFieldsForClass($type);
					foreach($fields as $field => $title){
						$map[$relationship.".".$field] = 
							$this->formatMappingFieldLabel($relationship,$title);
					}
				}
			}
			if($many_manys = singleton($this->objectClass)->has_one()){
				foreach($many_manys as $relationship => $type){
					$fields = $this->getMappableFieldsForClass($type);
					foreach($fields as $field => $title){
						$map[$relationship.".".$field] = 
							$this->formatMappingFieldLabel($relationship,$title);
					}
				}
			}
		}

		return $map;
	}

	/**
	 * Get the fields and labels for a given class, sorted naturally.
	 * @param  string $class
	 * @return array fields
	 */
	protected function getMappableFieldsForClass($class) {
		$fields = (array)singleton($class)->fieldLabels(false);
		natcasesort($fields);
		return $fields;
	}

	/**
	 * Format mapping field laabel
	 * @param  string $relationship
	 * @param  string $title
	 */
	protected function formatMappingFieldLabel($relationship, $title){
		//TODO: allow customisation
		return sprintf("%s: %s", $relationship, $title);
	}

}
