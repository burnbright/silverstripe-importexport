<?php


class GridFieldImporter_Request extends RequestHandler
{	
  /**
	 * Gridfield instance
	 * @var GridField 
	 */
	protected $gridField;
	

	protected $component;

	protected $urlSegment = 'importer';
	

	/**
	 * Gridfield Form controller
	 * @var Controller
	 */
	protected $controller;
	

	/**
	 * RequestHandler allowed actions
	 * @var array
	 */
	private static $allowed_actions = array(
		'preview', 'upload', 'import'
	);


	/**
	 * RequestHandler url => action map
	 * @var array
	 */
	private static $url_handlers = array(
		'upload!' => 'upload',
		'$Action/$FileID' => '$Action'
	);
	

	/**
	 * Handler's constructor
	 * 
	 * @param GridField $gridField
	 * @param GridField_URLHandler $component
	 * @param Controller $controller
	 */
	public function __construct($gridField, $component, $controller)
	{
		$this->gridField = $gridField;
		$this->component = $component;
		$this->controller = $controller;		
		parent::__construct();
	}

	/**
	 * Return the original component's UploadField
	 * 
	 * @return UploadField UploadField instance as defined in the component
	 */
	public function getUploadField()
	{
		return $this->component->getUploadField($this->gridField);
	}

	/**
	 * Upload the given file, and import or start preview.
	 * @param  SS_HTTPRequest $request
	 * @return string
	 */
	public function upload(SS_HTTPRequest $request)
	{

		$field = $this->getUploadField();
		$uploadResponse = $field->upload($request);

		//decode response body. ugly hack ;o
		$body = Convert::json2array( $uploadResponse->getBody() );
		$body = array_shift($body);

		//add extra data
		$body['import_url'] = $this->gridField->Link('importer/preview')."/".$body['id'];

		//re-encode
		$response = new SS_HTTPResponse(Convert::raw2json(array($body)));
		
		return $response;
	}

	/**
	 * Action for getting preview interface.
	 * @param  SS_HTTPRequest $request
	 * @return string
	 */
	public function preview(SS_HTTPRequest $request)
	{

		$file = File::get()
			->byID($request->param('FileID'));
		if(!$file){
			return "file not found";
		}

		//TODO: validate file?

		$mapper = new CSVFieldMapper($file->getFullPath());
		$mapper->setMappableCols($this->getMappableColumns());

		$form = $this->MapperForm();
		$form->Fields()->push(
			new LiteralField('mapperfield', $mapper->forTemplate())
		);

		$form->setFormAction($this->Link('import').'/'.$file->ID);

		$content = ArrayData::create(array(
			'File' => $file,
			'MapperForm'=> $form
		))->renderWith('GridFieldImporter_preview');
		
		//$controller = $this->getToplevelController();
		$controller = Controller::curr();
		
		return $controller->customise(array(
			'Content' => $content
		));

	}

	public function MapperForm(){

		$fields = new FieldList(
			CheckboxField::create("HasHeader",
				"This CSV file includes a header row"
			)
		);

		$actions = new FieldList(
			new FormAction("import", "Import CSV")
		);

		$form = new Form($this, __FUNCTION__, $fields, $actions);

		return $form;

	}

	/**
	 * Get all columns that can be mapped to in BulkLoader
	 * @return array
	 */
	protected function getMappableColumns() {
		$class = $this->gridField->getModelClass();
		$map = (array)singleton($class)->fieldLabels(false);
		
		// $has_ones = singleton($this->objectClass)->has_one();
		// $has_manys = singleton($this->objectClass)->has_many();
		// $many_manys = singleton($this->objectClass)->many_many();
		
		// $spec['relations'] = (array)$has_ones + (array)$has_manys + (array)$many_manys;
		//var_dump($map);
		return $map;
	}


	public function import(SS_HTTPRequest $request) {
		
		$file = File::get()
			->byID($request->param('FileID'));
		if(!$file){
			return "file not found";
		}
		//$columnmap = $this->mapFromData($data);

		$this->component->importFile(
			$file->getFullPath(), $this->gridField
			//$columnmap
		);

	}

	protected function mapFromData($data) {
		var_dump($data);
	}

	/**
	 * Pass fileexists request to UploadField
	 * 
	 * @link UploadField->fileexists()
	 */
	public function fileexists(SS_HTTPRequest $request)
	{
		$uploadField = $this->getUploadField();
		return $uploadField->fileexists($request);
	}


	/**
	 * @param string $action
	 * @return string
	 */
	public function Link($action = null) {
		return Controller::join_links(
			$this->gridField->Link(), $this->urlSegment, $action
		);
	}

}
