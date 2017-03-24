jQuery(document).ready(function($){
	var custom_uploader;

	var basicfieldclass = "facebookmetadata-image";

	$('.'+basicfieldclass).click(function(e) {
		var clickedField = $(this);

		e.preventDefault();

		// If the uploader object has already been created, reopen the dialog
		if (custom_uploader) {
			custom_uploader.open();
			return;
		}

		// Extend the wp.media object
		custom_uploader = wp.media.frames.file_frame = wp.media({
			title: 'Choose Image',
			button: {
			text: 'Choose Image'
			},
			multiple: false
		});

		// When a file is selected, grab the URL and set it as the value in the hidden field
		custom_uploader.on('select', function() {
			attachment = custom_uploader.state().get('selection').first().toJSON();
			$('#'+clickedField.attr("id")+'_url').val(attachment.url);
		});

		// Open the uploader dialog
		custom_uploader.open();
	});

});
