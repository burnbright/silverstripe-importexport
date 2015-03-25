<?php

/**
 * Store result information about a BulkLoader import.
 */
class BetterBulkLoader_Result extends BulkLoader_Result {

	/**
	 * @return int
	 */
	public function SkippedCount() {
		return count($this->skipped);
	}

	/**
	 * @param string $message Reason for skipping
	 */
	public function addSkipped($message = null) {
		$this->skipped[] = array(
			'Message' => $message
		);
	}

	/**
	 * Get an array of messages describing the result.
	 * @return array messages
	 */
	public function getMessageList() {

		$output =  array();
		if($this->CreatedCount()) {
			$output['created'] = _t(
				'BulkLoader.IMPORTEDRECORDS', "Imported {count} records.",
				array('count' => $this->CreatedCount())
			);
		}
		if($this->UpdatedCount()) {
			$output['updated'] = _t(
				'BulkLoader.UPDATEDRECORDS', "Updated {count} records.",
				array('count' => $this->UpdatedCount())
			);
		}
		if($this->DeletedCount()) {
			$output['deleted'] =  _t(
				'BulkLoader.DELETEDRECORDS', "Deleted {count} records.",
				array('count' => $this->DeletedCount())
			);
		}
		if(!$this->CreatedCount() && !$this->UpdatedCount()) {
			$output['empty'] = _t('BulkLoader.NOIMPORT', "Nothing to import");
		}

		return $output;
	}

	/**
	 * Genenrate a human-readable result message.
	 * 
	 * @return string
	 */
	public function getMessage() {
		return implode("\n", $this->getMessageList());
	}

	public function getMessageType() {
		//if only updated / added, then good
		//if deleted, then warning
		//if skipped, then warning
		//if only skipped then bad
		//if nothing happened, then bad
		
		return "good";
	}

}