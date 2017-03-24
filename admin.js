jQuery(document).ready(function($){
	var custom_uploader;

	var basicfieldname = "facebookmetadata-postmeta-image";

	$('#'+basicfieldname).click(function(e) {
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
			$('#'+basicfieldname+'_url').val(attachment.url);
		});

		// Open the uploader dialog
		custom_uploader.open();
	});

});