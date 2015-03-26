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
	public $source;

	/**
	 * Specify a colsure to be run on every imported record.
	 * @var Closure
	 */
	public $recordCallback;

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

		//set up Relation.Field style mappings
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

	protected function setSource(BulkLoaderSource $source) {
		$this->source = $source;

		return $this;
	}

	protected function getSource() {
		return $this->source;
	}

	protected function processAll($filepath, $preview = false) {
		$iterator = $this->getSource()->getIterator();
		$results = new BetterBulkLoader_Result();

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
		$class = $this->objectClass;

		if(!$this->validateRecord($record)){
			$results->addSkipped("Invalid record data.");
			return;
		}
		
		// find existing object, or create new one
		$existingObj = $this->findExistingObject($record, $columnMap);
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
				if (!$preview && !$relationObj->isInDB()){
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

		try{
			// write record
			($preview) ? 0 : $obj->write();
			// save to results
			if($existingObj) {
				if($obj->isChanged()){
					$results->addUpdated($obj);
				}else{
					$results->addSkipped();
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
						E_USER_ERROR
					);
				}
				if($existingRecord) {
					return $existingRecord;
				}
			} else {
				user_error('BulkLoader::processRecord(): Wrong format for $duplicateChecks',
					E_USER_ERROR
				);
			}
		}

		return false;
	}

}
