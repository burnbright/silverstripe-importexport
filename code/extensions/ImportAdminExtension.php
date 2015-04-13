<?php

class ImportAdminExtension extends Extension{
	
	/**
	 * Prevent existing import form from showing up
	 * @todo: there should be a better way to disable from an extension, rather than
	 * disabling it after it has been created
	 */
	public function updateImportForm(&$form){
		if(Config::inst()->get('ModelAdmin','removelegacyimporters') === true){
			$form = null;
		}
	}

	/**
	 * Add in new bulk GridFieldImporter
	 */
	public function updateEditForm($form){
		if(Config::inst()->get('ModelAdmin','addbetterimporters') === true){
			$modelclass = $this->owner->modelClass;
			$grid = $form->Fields()->fieldByName($modelclass);
			$config =  $grid->getConfig();
			//don't proceed if there is already an importer
			if($config->getComponentByType("GridFieldImporter")){
				return;
			}
			$config->addComponent(new GridFieldImporter('before'));
		}
	}

}
