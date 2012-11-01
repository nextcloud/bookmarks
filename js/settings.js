$(document).ready(function() {
	$('#bm_import_submit').click(attachSettingEvent);
});


function attachSettingEvent(event) {
	event.preventDefault();
	fileUpload($(this).closest('form'), $('#upload'));
}
function fileUpload(form, result_div) {
		
	var uploadEventHandler = function () {
		var data = {};
		try{
			data = $.parseJSON(iframe.contents().text());
		}catch (e){}
		if(data.status == 'error') {
			list = $("<ul></ul>").addClass('setting_error_list');
			console.log(data);
			$.each(data.data,function(index, item){
				list.append($( "<li></li>" ).text(item));
			});
			result_div.html(list);
		} else {
			result_div.text(t('bookmark', 'Import completed successfully.'));
		}
	};
		
	// Create the iframe...
	var iframe;
	if($('#upload_iframe').length === 1)
		iframe = $('#upload_iframe')
	else {
		iframe = $('<iframe></iframe>').attr({
			id: 'upload_iframe',
			name: 'upload_iframe',
			width: '0',
			height: '0',
			border: '0',
			style: 'display:none'
		}).bind('load',uploadEventHandler);
		form.append(iframe);
	}

	// Set properties of form...
	form.attr({
		target: 'upload_iframe',
		method: 'post',
		enctype: 'multipart/form-data',
		encoding: 'multipart/form-data'
	});

	// Submit the form...
	form.submit();

	result_div.text(t('bookmark', 'Uploading...'));
}