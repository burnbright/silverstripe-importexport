(function($) {

	$("div.csv-importer").entwine({
		onmatch: function() {
			this.hide();
		}
	});

	$.entwine('ss', function($) {
		$('.ss-gridfield button.toggle-csv-fields.action').entwine({
			onclick: function(){
				//change entwine scope
				$('div.csv-importer').entwine('.', function($){
					this.toggle();
				});
			}
		});
	});

	$(".import-upload-csv-field").entwine({
		onmatch: function() {
			this.on('fileuploaddone', function(e,data){
				e.preventDefault();
				window.location.href = data.result[0].import_url;
			});
		}
	});

}(jQuery));