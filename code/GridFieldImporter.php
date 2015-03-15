<?php

/**
 * Adds a way to import data to the grid field's parent list
 */
class GridFieldImporter implements GridField_HTMLProvider, GridField_URLHandler {
	
	
	/**
	 * Fragment to write the button to
	 */
	protected $targetFragment;

	public function __construct($targetFragment = "after") {
		$this->targetFragment = $targetFragment;
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
			//'DeleteRecords' => $deleterecordsfield,
			'UploadField' => $uploadfield
		);

		return array(
			$this->targetFragment =>
				ArrayData::create($data)
					->renderWith("GridFieldImporter")
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
			'importer' => 'handleImport'
		);
	}

	public function handleImport($gridField, $request = null) {
		$controller = $gridField->getForm()->Controller();
		$handler    = new GridFieldImporter_Request($gridField, $this, $controller);

		return $handler->handleRequest($request, DataModel::inst());
	}

	public function importFile($filepath, $gridField, $colmap = null){

		$loader = $this->getLoader();

		//TODO: empty if possible
		$results = $loader->load($filepath);


		$gridField->getForm()->sessionMessage("Imported!" , 'good');
	}

	public function getLoader() {
		//TODO: remove hard-coded value
		return new CSVBulkLoader("MenuProductSelection");
	}

}
