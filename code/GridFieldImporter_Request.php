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
	 * Parent handler to link up to
	 * @var RequestHandler
	 */
	protected $requestHandler;
	

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
	 * @param RequestHandler $handler
	 */
	public function __construct($gridField, $component, $handler)
	{
		$this->gridField = $gridField;
		$this->component = $component;
		$this->requestHandler = $handler;		
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
		//don't return buttons at all
		unset($body['buttons']);

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
			new FormAction("import", "Import CSV"),
			new FormAction("cancel", "Cancel")
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
		
		return $map;
	}

	/**
	 * Import the current file
	 * @param  SS_HTTPRequest $request
	 */
	public function import(SS_HTTPRequest $request) {

		if($request->postVar('action_import')){

			$file = File::get()
				->byID($request->param('FileID'));
			if(!$file){
				return "file not found";
			}
			$colmap = Convert::raw2sql($request->postVar('mappings'));
			
			if($colmap){
				$this->component->importFile(
					$file->getFullPath(),
					$this->gridField,
					$colmap
				);
			}

		}

		$controller = $this->getToplevelController();

		$url = method_exists($this->requestHandler, "Link") ?
			$this->requestHandler->Link() :
			$controller->Link();

		$controller->redirect($url);
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

	/**
	 * @see GridFieldDetailForm_ItemRequest::getTopLevelController
	 * @return Controller
	 */
	protected function getToplevelController() {
		$c = $this->requestHandler;
		while($c && $c instanceof GridFieldDetailForm_ItemRequest) {
			$c = $c->getController();
		}

		if(!$c){
			$c = Controller::curr();
		}

		return $c;
	}

}
