(function($) {

	//hide upload field
	var importerpanel = $("div.csv-importer");
	//hiddenfields.hide();

	$.entwine('ss', function($) {
		$('.ss-gridfield button.toggle-csv-fields.action').entwine({
			onclick: function(){
				importerpanel.toggle();
			}
		});

	});

	$(".import-upload-csv-field").on('fileuploaddone', function(e,data){

		e.preventDefault();

		//TODO: immediately redirect to CSV import UI
		console.log(data.result[0]);

		//TODO: get proper url
		var url = "http://localhost/bluecarrot_new/admin/menus/Menu/EditForm/field/Menu/item/1/ItemEditForm/field/ProductSelections/importer/preview/166";

		//window.location.href = url;
		//$('.cms-container').loadPanel(url, "Title", {});
		
		// $.ajax({
		// 	url: url,
		// 	dataType: 'html',
		// 	success: function(data) {
		// 		importerpanel.html(data);
		// 	}
		// });

	});

}(jQuery));