<?php

ModelAdmin::add_extension("ImportAdminExtension");
$remove = Config::inst()->get('ModelAdmin','removelegacyimporters');
if($remove === "scaffolded"){
	Config::inst()->update("ModelAdmin", 'model_importers', array());
}
