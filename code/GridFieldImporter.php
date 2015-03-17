<?php

/**
 * Adds a way to import data to the grid field's parent list
 */
class GridFieldImporter implements GridField_HTMLProvider, GridField_URLHandler {
	
	
	/**
	 * Fragment to write the button to
	 */
	protected $targetFragment;

	protected $loaderClass = "CSVBulkLoader";

	protected $recordcallback;

	public function __construct($targetFragment = "after") {
		$this->targetFragment = $targetFragment;
	}

	public function setLoaderClass($class){
		$this->loaderClass = $class;

		return $this;
	}

	public function setRecordCallback($callback) {
		$this->recordcallback = $callback;

		return $this;
	}

	public function getHTMLFragments($gridField) {
		$button = new GridField_FormAction(
			$gridField, 
			'import', 
			_t('TableListField.CSVIMPORT', 'Import from CSV'),
			'import', 
			null
		);
		$button->setAttribute('data-icon', 'upload-csv');
		$button->addExtraClass('no-ajax');

		$uploadfield = $this->getUploadField($gridField);

		$data = array(
			'Button' => $button,
			'UploadField' => $uploadfield
		);

		$importerHTML = ArrayData::create($data)
					->renderWith("GridFieldImporter");

		Requirements::javascript('importexport/javascript/GridFieldImporter.js');

		return array(
			$this->targetFragment => $importerHTML
		);
	}

	/**
	 * Returned a configured UploadField instance
	 * embedded in the gridfield heard
	 * @param  GridField $gridField Current GridField
	 * @return UploadField          Configured UploadField instance
	 */
	public function getUploadField($gridField) {
		$uploadField = UploadField::create(
				$gridField->Name."_ImportUploadField", 'Upload CSV'
			)
			->setForm($gridField->getForm())
			->setConfig('url', $gridField->Link('importer/upload'))
			->setConfig('edit_url', $gridField->Link('importer/import'))
			->setConfig('allowedMaxFileNumber', 1)
			->setConfig('changeDetection', false)
			->setConfig('canPreviewFolder', false)
			->setConfig('canAttachExisting', false)
			->setConfig('overwriteWarning', false)
			->setAllowedExtensions(array('csv'))
			//TODO: don't store temp CSV in assets
			->setFolderName('csvImports')
			->addExtraClass("import-upload-csv-field");
			
		return $uploadField;
	}

	public function getActions($gridField) {
		return array('importer');
	}

	public function getURLHandlers($gridField) {
		return array(
			'importer' => 'handleImporter'
		);
	}

	/**
	 * Pass importer requests to a new GridFieldImporter_Request
	 */
	public function handleImporter($gridField, $request = null) {
		$controller = $gridField->getForm()->getController();
		$handler    = new GridFieldImporter_Request($gridField, $this, $controller);

		return $handler->handleRequest($request, DataModel::inst());
	}

	public function importFile($filepath, $gridField, $colmap = null){
		$loader = $this->getLoader($gridField);
		//set or merge in given col map
		if($colmap){
			$loader->columnMap = $loader->columnMap ?
				array_merge($loader->columnMap, $colmap) : $colmap;
		}
		$results = $loader->load($filepath);
		//TODO: handle validation/loading issues

		$gridField->getForm()
			->sessionMessage($this->getLoadResultsMessage($results), 'good');
	}

	public function getLoader($gridField) {
		$class = $this->loaderClass;
		$loader = new $class($gridField->getModelClass());
		if($this->recordcallback && property_exists($class, 'recordCallback')){
			$loader->recordCallback = $this->recordcallback;
		}
		return $loader;
	}

	/**
	 * Genenrate a human-readable result message.
	 * 
	 * @see ModelAdmin::import()
	 * @param  BulkLoader_Result $results
	 * @return string
	 */
	protected function getLoadResultsMessage(BulkLoader_Result $results) {
		$message = '';
		if($results->CreatedCount()) $message .= _t(
			'ModelAdmin.IMPORTEDRECORDS', "Imported {count} records.",
			array('count' => $results->CreatedCount())
		);
		if($results->UpdatedCount()) $message .= _t(
			'ModelAdmin.UPDATEDRECORDS', "Updated {count} records.",
			array('count' => $results->UpdatedCount())
		);
		if($results->DeletedCount()) $message .= _t(
			'ModelAdmin.DELETEDRECORDS', "Deleted {count} records.",
			array('count' => $results->DeletedCount())
		);
		if(!$results->CreatedCount() && !$results->UpdatedCount()) {
			$message .= _t('ModelAdmin.NOIMPORT', "Nothing to import");
		}

		return $message;
	}

}
