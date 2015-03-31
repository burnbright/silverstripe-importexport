<?php

/**
 * Peforms bulk loading, but works with a given DataList.
 */
class ListBulkLoader extends BetterBulkLoader {

	/**
	 * The list to insert new DataObjects into.
	 * @var DataList
	 */
	protected $list;

	public function __construct(DataList $list) {
		$this->list = $list;

		parent::__construct($this->list->dataClass());
	}

	public function setList(DataList $list){
		$this->list = $source;

		return $this;
	}

	
	public function getList(){
		return $this->getList();
	}

	/**
	 * Add records to the list.
	 */
	protected function processAll($filepath, $preview = false) {
		$iterator = $this->getSource()->getIterator();
		$results = new BetterBulkLoader_Result();
		foreach($iterator as $record) {
			if($id = $this->processRecord($record, $this->columnMap, $results, $preview)){
				$this->list->add($id);
			}
		}
		
		return $results;
	}

	/**
	 * Override the default deleteExistingRecords method.
	 */
	public function deleteExistingRecords() {
		//TODO: allow chooosing between delete and remove(unlink)
		foreach($this->list as $item) {
			$item->delete();
			$item->destroy();
		}
	}

}
