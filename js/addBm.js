$(document).ready(function() {
	$('#bookmark_add_submit').click(addBookmark);
	$('#url-ro img').click(editUrl);
	$('#url').keypress(changeUrl);
	$('#addBm').submit(bookletSubmit);
	$('#tags').tagit({
		allowSpaces: true,
		availableTags: sampleTags
	});
});

function addBookmark(event) {
	var url = $('#bookmark_add_url').val();
	var tags = $('#bookmark_add_tags').val();
	$.ajax({
		type: 'POST',
		url: 'ajax/addBookmark.php',
		data: 'url=' + encodeURI(url) + '&tags=' + encodeURI(tags),
		success: function(data){ 
			window.close();
		}
	});
}
function editUrl(event) {
	$('#url').slideToggle();
}
function changeUrl(event) {
	$('#url-ro code').text($('#url').val());
}
function bookletSubmit(event) {
	event.preventDefault();
	$.ajax({
		type: 'POST',
		url: $('#addBm').attr('action'),
		data: $('#addBm').serialize(),
		success: function(data){ 
			if(data.status == 'success'){
				self.close();
			}
		}
	});
}