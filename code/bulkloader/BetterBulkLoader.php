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

	public $transforms = array();

	/**
	 * Specify a colsure to be run on every imported record.
	 * @var Closure
	 */
	public $recordCallback;

	/**
	 * The default behaviour for linking relations
	 * @var boolean
	 */
	protected $relationLinkDefault = true;

	/**
	 * The default behaviour creating relations
	 * @var boolean
	 */
	protected $relationCreateDefault = true;

	/**
	 * Cache the result of getMappableColumns
	 * @var array
	 */
	protected $mappableFields_cache;

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

	/**
	 * Set the default behaviour for linking existing relation objects.
	 * @param boolean $default
	 * @return BulkLoader
	 */
	public function setRelationLinkDefault($default) {
		$this->relationLinkDefault = $default;
		return $this;	
	}

	/**
	 * Set the default behaviour for creating new relation objects.
	 * @param boolean $default
	 * @return BulkLoader
	 */
	public function setRelationCreateDefault($default) {
		$this->relationCreateDefault = $default;
		return $this;	
	}

	public function load($filepath = null) {
		if($this->deleteExistingRecords) {
			$this->deleteExistingRecords();
		}
		$this->mappableFields_cache = $this->getMappableColumns();

		return $this->processAll($filepath);
	}

	/**
	 * Delete all existing records
	 */
	public function deleteExistingRecords() {
		DataObject::get($this->objectClass)->removeAll();
	}

	/**
	 * Get the DataList of objects this loader applies to.
	 * @return DataList
	 */
	public function getDataList() {
		$class = $this->objectClass;
		return $class::get();
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
	 * Import the given record
	 */
	protected function processRecord($record, $columnMap, &$results, $preview = false) {
		if(!is_array($record) || empty($record) || !array_filter($record)){
			$results->addSkipped("Empty/invalid record data.");
			return;
		}
		//map incoming record according to the standardisation mapping (columnMap)
		$record = $this->columnMapRecord($record);
		//skip if required data is not present
		if(!$this->hasRequiredData($record)){
			$results->addSkipped("Required data is missing.");
			return;
		}
		$modelClass = $this->objectClass;
		$placeholder = new $modelClass();

		//populate placeholder object with transformed data
		foreach($this->mappableFields_cache as $field => $label){
			//skip empty fields
			if(!isset($record[$field]) || empty($record[$field])){
				continue;
			}
			$this->transformField($placeholder, $field, $record[$field]);
		}
		//find existing duplicate of placeholder data
		$obj = null;
		$existing = null;
		if(!$placeholder->ID && !empty($this->duplicateChecks)){
			$data = $placeholder->getQueriedDatabaseFields();
			//don't match on ID, ClassName or RecordClassName
			unset($data['ID'], $data['ClassName'], $data['RecordClassName']);
			$existing = $this->findExistingObject($data);
		}
		if($existing){
			$obj = $existing;
			$obj->update($data);
		}else{
			$obj = $placeholder;
		}
		$changed = $existing && $obj->isChanged();
		//try/catch for potential write() ValidationException
		try{
			// write obj record
			$obj->write();
			// save to results
			if($existing) {
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
		$adjustedmap = $this->columnMap;
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
	 * Check if the given mapped record has the required data.
	 * @param  array $mappedrecord
	 * @return boolean
	 */
	protected function hasRequiredData($mappedrecord) {
		if(!is_array($mappedrecord) || empty($mappedrecord) || !array_filter($mappedrecord)){
			return false;
		}
		foreach ($this->transforms as $field => $t) {
			if(
				is_array($t) &&
				isset($t['required']) &&
				$t['required'] === true &&
				(!isset($mappedrecord[$field]) ||
				empty($mappedrecord[$field]))
			){
				return false;
			}
		}
		
		return true;
	}

	/**
	 * Perform field transformation or setting of data on placeholder.
	 * @param  DataObject $placeholder
	 * @param  string $field
	 * @param  mixed $value
	 */
	protected function transformField($placeholder, $field, $value){
		$callback = isset($this->transforms[$field]['callback']) &&
					is_callable($this->transforms[$field]['callback']) ?
					$this->transforms[$field]['callback'] : null;
		//handle relations
		if($this->isRelation($field)){
			$relation = null;
			$relationName = null;
			//get the list that relation is added to/checked on
			$relationlist = isset($this->transforms[$field]['list']) &&
							$this->transforms[$field]['list'] instanceof SS_List ?
							$this->transforms[$field]['list'] : null;
			//get/make relation via callback
			if($callback){
				$relation = $callback($value, $placeholder);
				$relationName = $field;
				//convert any use of dot notation
				if(strpos($field, '.') !== false){
					list($relationName, $columnName) = explode('.', $field);
				}
			}
			//get/make relation via dot notation
			else if(strpos($field, '.') !== false){
				list($relationName, $columnName) = explode('.', $field);
				if($relationClass = $placeholder->getRelationClass($relationName)){
					if(!$relationlist){
						$relationlist = $relationlist;
					}
					$relation = $relationClass::get()
									->filter($columnName, $value)
									->first();
					//create empty relation object
					//and set the given value on the appropriate column
					if(!$relation){
						$relation = $placeholder->{$relationName}();
					}
					//set data on relation
					$relation->{$columnName} = $value;
				}
			}
			//link and create relation objects
			$linkexisting = isset($this->transforms[$field]['link']) ?
								(bool)$this->transforms[$field]['link'] : 
								$this->relationLinkDefault;
			$createnew = isset($this->transforms[$field]['create']) ?
								(bool)$this->transforms[$field]['create'] :
								$this->relationCreateDefault;
			//ditch relation if we aren't linking
			if(!$linkexisting && $relation && $relation->isInDB()){
				$relation = null;
			}
			//fail validation gracefully
			try{
				//write relation object, if configured
				if($createnew && $relation && !$relation->isInDB()){
					$relation->write();
				}
				//write changes to existing relations
				else if($relation && $relation->isInDB() && $relation->isChanged()){
					$relation->write();
				}
				//add relation to relationlist, if it exists
				if($relationlist && !$relationlist->byID($relation->ID)){
					$relationlist->add($relation);
				}
			}catch(ValidationException $e){
				$relation = null;
			}
			//add the relation id to the placeholder
			if($relationName && $relation && $relation->exists()){
				$placeholder->{$relationName."ID"} = $relation->ID;
			}
		}
		//handle data fields
		else{
			//transform field value via callback
			//(callback can also update placeholder directly)
			if($callback){
				$value = $callback($value, $placeholder);
			}
			//set field value
			$placeholder->update(array(
				$field => $value
			));
		}
	}

	/**
	 * Detect if a given record field is a relation field.
	 * @param  string  $field
	 * @return boolean
	 */
	protected function isRelation($field){
		//get relation name from dot notation
		if(strpos($field, '.') !== false){
			list($field, $columnName) = explode('.', $field);
		}
		$has_ones = singleton($this->objectClass)->has_one();
		//check if relation is present in has ones
		return isset($has_ones[$field]);
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
		// checking for existing records (only if not already found)
		foreach($this->duplicateChecks as $fieldName => $duplicateCheck) {
			//plain duplicate checks on fields and relations
			if(is_string($duplicateCheck)) {
				$fieldName = $duplicateCheck;
				//If the dupilcate check is a dot notation, then convert to ID relation
				if(strpos($duplicateCheck, '.') !== false){
					list($relationName, $columnName) = explode('.', $duplicateCheck);
					$fieldName = $relationName."ID";
				}

				//TODO: also convert plain relation names to include ID

				//skip current duplicate check if field value is empty
				if(!isset($record[$fieldName]) || empty($record[$fieldName])) {
					continue;
				}
				$existingRecord = $this->getDataList()
									->filter($fieldName, $record[$fieldName])
									->first();
				if($existingRecord) {
					return $existingRecord;
				}
			}
			//callback duplicate checks
			elseif(
				is_array($duplicateCheck) &&
				isset($duplicateCheck['callback']) &&
				is_callable($duplicateCheck['callback'])
			) {
				$callback = $duplicateCheck['callback'];
				if($existingRecord = $callback($fieldName, $record)) {
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
		$scaffolded = $this->scaffoldMappableFields();
		//TODO: blacklist  or whitelist fields to be mappable
		//TODO: add labels for transformables
		if(!empty($this->transforms)){
			$transformables = array_keys($this->transforms);
			$transformables = array_combine($transformables, $transformables);
			$scaffolded = array_merge($transformables, $scaffolded);
		}

		return $scaffolded;
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
							$this->formatMappingFieldLabel($relationship, $title);
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
		return sprintf("%s: %s", $relationship, $title);
	}

}
