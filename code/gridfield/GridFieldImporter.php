<?php

/**
 * Adds a way to import data to the GridField's DataList
 */
class GridFieldImporter implements GridField_HTMLProvider, GridField_URLHandler {
		
	/**
	 * Fragment to write the button to
	 * @var string
	 */
	protected $targetFragment;

	/**
	 * Type of BulkLoader to load with
	 * @var string
	 */
	protected $loaderClass = null;

	/**
	 * Can the user clear records
	 * @var boolean
	 */
	protected $canClearData = true;

	public function __construct($targetFragment = "after") {
		$this->targetFragment = $targetFragment;
	}

	/**
	 * Set the type of BulkLoader to handle imports
	 * @param string $class
	 */
	public function setLoaderClass($class){
		$this->loaderClass = $class;

		return $this;
	}

	/**
	 * @param boolean $canclear
	 */
	public function setCanClearData($canclear = true) {
		$this->canClearData = $canClearData;
	}

	public function getCanClearData() {
		return $this->canClearData;
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
	 * Return a configured UploadField instance
	 * 
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
			->setFolderName('csvImports') //TODO: don't store temp CSV in assets
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

	/**
	 * Get the BulkLoader
	 */
	public function getLoader($gridField) {
		$class = $this->loaderClass;
		$gridlist = $gridField->getList();
		//choose default if no loader class set
		if(!$class){
			$class = ($gridlist instanceof HasManyList) ?
				"ListBulkLoader" : "BetterBulkLoader";
		}
		//set the correct constructor argument
		$arg = (is_subclass_of($class, "ListBulkLoader")) ?
			$gridlist : $gridField->getModelClass();

		$loader = new $class($arg);
		$loader->setSource(new CsvBulkLoaderSource());

		return $loader;
	}

}
