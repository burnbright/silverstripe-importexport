(function($) {

	//hide upload field
	var importerpanel = $("div.csv-importer");
	importerpanel.hide();

	$.entwine('ss', function($) {
		$('.ss-gridfield button.toggle-csv-fields.action').entwine({
			onclick: function(){
				importerpanel.toggle();
			}
		});

	});

	$(".import-upload-csv-field").on('fileuploaddone', function(e,data){
		e.preventDefault();
		window.location.href = data.result[0].import_url;
	});

}(jQuery));