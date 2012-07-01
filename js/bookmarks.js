var bookmarks_page = 0;
var bookmarks_loading = false;

var bookmarks_sorting = 'bookmarks_sorting_recent';

var bookmark_view = 'image';

$(document).ready(function() {
	$('.bookmarks_list').addClass('bm_view_img');
	$('#view_type input').click(switchView);
	$('#bookmark_add_submit').click(addBookmark);
	$(window).resize(function () {
		fillWindow($('.bookmarks_list'));
	});
	$(window).resize();
	$('.bookmarks_list').scroll(updateOnBottom).empty().width($('#rightcontent').width());
	$('#tag_filter input').tagit({
		allowSpaces: true,
		availableTags: fullTags,
		onTagRemoved: filterTagsChanged
	}).tagit('option', 'onTagAdded', filterTagsChanged);

	getBookmarks();
});

function switchView(){
	$(this).hide();
	if($(this).hasClass('image')) { //Then List
		$('.bookmarks_list').addClass('bm_view_list');
		$('.bookmarks_list').removeClass('bm_view_img');
		$('#view_type input.list').show();
		bookmark_view = 'list';
	} else { // Then Image
		$('.bookmarks_list').addClass('bm_view_img');
		$('.bookmarks_list').removeClass('bm_view_list');
		$('#view_type input.image').show();
		bookmark_view = 'image';
	}
	filterTagsChanged(); //Refresh the view
}
function addFilterTag(event) {
	event.preventDefault();
	$('#tag_filter input').tagit('createTag', $(this).text());
}

function updateTagsList(tag) {
	$('.tag_list').append('<li><a href="" class="tag">'+tag['tag']+'</a>'+
		'<p class="tags_actions">'+
			'<span class="tag_edit">'+
				'<img class="svg" src="'+OC.imagePath('core', 'actions/rename')+'" title="Edit">'+
			'</span>'+
			'<span class="tag_delete">'+
				'<img class="svg" src="'+OC.imagePath('core', 'actions/delete')+'" title="Delete">'+
			'</span>'+
		'</p>'+
		'<em>'+tag['nbr']+'</em>'+
	'</li>');

}

function filterTagsChanged()
{
	$('#bookmarkFilterTag').val($('#tag_filter input:hidden').val());
	bookmarks_page = 0;
	$('.bookmarks_list').empty();
	getBookmarks();
}
function getBookmarks() {
	if(bookmarks_loading) {
		//have patience :)
		return;
	}	
	//Update Rel Tags
	$.ajax({
		type: 'POST',
		url: OC.filePath('bookmarks', 'ajax', 'updateList.php') + '&type=rel_tags',
		data: {tag: $('#bookmarkFilterTag').val(), page:bookmarks_page, sort:bookmarks_sorting },
		success: function(tags){
			$('.tag_list').empty();
			for(var i in tags.data) {
				updateTagsList(tags.data[i]);
			}
			$('.tag_list .tag_edit').click(renameTag);
			$('.tag_list .tag_delete').click(deleteTag);
			$('.tag_list a.tag').click(addFilterTag);


		}
	});
	
	$.ajax({
		type: 'POST',
		url: OC.filePath('bookmarks', 'ajax', 'updateList.php') + '&type=bookmark',
		data: {tag: $('#bookmarkFilterTag').val(), page:bookmarks_page, sort:bookmarks_sorting },
		success: function(bookmarks){
			if (bookmarks.data.length) {
				bookmarks_page += 1;
			}
			$('.bookmark_link').unbind('click', recordClick);
			$('.bookmark_delete').unbind('click', delBookmark);
			$('.bookmark_edit').unbind('click', editBookmark);

			for(var i in bookmarks.data) {
				updateBookmarksList(bookmarks.data[i]);
				$("#firstrun").hide();
			}
			if($('.bookmarks_list').is(':empty')) {
				$("#firstrun").show();
			}

			$('.bookmark_link').click(recordClick);
			$('.bookmark_delete').click(delBookmark);
			$('.bookmark_edit').click(editBookmark);

			bookmarks_loading = false;
			if (bookmarks.data.length) {
				updateOnBottom()
			}
		}
	});
}


function createEditDialog(record){
	dialog_html = $('#edit_dialog').html();
	var dialog = $(dialog_html).dialog({
		width : 620,
		height: 450,
		title: t('bookmark', 'Edit bookmark'),
		modal: true,
		close : function(event, ui) {
			$(this).dialog('destroy').remove();
		}
	});

	$('.ui-dialog').bookmark_dialog({
		on_success: function(){
			dialog.dialog('destroy').remove();
			filterTagsChanged();
		},
		record: record
	});
}

function addBookmark(event) {
	createEditDialog();
}

function delBookmark(event) {
	var record = $(this).parent().parent();
	$.ajax({
		type: 'POST',
		url: OC.filePath('bookmarks', 'ajax', 'delBookmark.php'),
		data: 'id=' + record.data('id'),
		success: function(data){
			if (data.status == 'success') {
				record.remove();
				if($('.bookmarks_list').is(':empty')) {
					$("#firstrun").show();
				}
			}
		}
	});
}

function editBookmark(event) {
	var record = $(this).parent().parent();
	bookmark =  record.data('record');
	createEditDialog(bookmark);
}

function updateBookmarksList(bookmark) {
	var tags = bookmark.tags;
	var taglist = '';
	for ( var i=0, len=tags.length; i<len; ++i ){
		if(tags[i] != '')
			taglist = taglist + '<a class="bookmark_tag" href="#">' + encodeEntities(tags[i]) + '</a> ';
	}
	if(!hasProtocol(bookmark.url)) {
		bookmark.url = 'http://' + bookmark.url;
	}
	if(bookmark.title == '') bookmark.title = bookmark.url;

	if(bookmark_view == 'image') { //View in images
		$('.bookmarks_list').append(
			'<div class="bookmark_single" data-id="' + bookmark.id +'" >' +
				'<p class="shot"><img src="'+ "http://screenshots.bookmarkly.com/thumb?url="+encodeEntities(bookmark.url) +'"></p>'+
				'<p class="bookmark_actions">' +
					'<span class="bookmark_edit">' +
						'<img class="svg" src="'+OC.imagePath('core', 'actions/rename')+'" title="Edit">' +
					'</span>' +
					'<span class="bookmark_delete">' +
						'<img class="svg" src="'+OC.imagePath('core', 'actions/delete')+'" title="Delete">' +
					'</span>&nbsp;' +
				'</p>' +
				'<p class="bookmark_title">'+
					'<a href="' + encodeEntities(bookmark.url) + '" target="_blank" class="bookmark_link">' + encodeEntities(bookmark.title) + '</a>' +
				'</p>' +
				'<p class="bookmark_url"><a href="' + encodeEntities(bookmark.url) + '" target="_blank" class="bookmark_link">' + encodeEntities(bookmark.url) + '</a></p>' +
			'</div>'
		);
		$('div[data-id="'+ bookmark.id +'"]').data('record', bookmark);
		if(taglist != '') {
			$('div[data-id="'+ bookmark.id +'"]').append('<p class="bookmark_tags">' + taglist + '</p>');
		}
		$('div[data-id="'+ bookmark.id +'"] a.bookmark_tag').bind('click', addFilterTag);
	}
	else {
		$('.bookmarks_list').append(
			'<div class="bookmark_single" data-id="' + bookmark.id +'" >' +
				'<p class="bookmark_actions">' +
					'<span class="bookmark_edit">' +
						'<img class="svg" src="'+OC.imagePath('core', 'actions/rename')+'" title="Edit">' +
					'</span>' +
					'<span class="bookmark_delete">' +
						'<img class="svg" src="'+OC.imagePath('core', 'actions/delete')+'" title="Delete">' +
					'</span>&nbsp;' +
				'</p>' +
				'<p class="bookmark_title">'+
					'<a href="' + encodeEntities(bookmark.url) + '" target="_blank" class="bookmark_link">' + encodeEntities(bookmark.title) + '</a>' +
				'</p>' +
				'<p class="bookmark_url"><a href="' + encodeEntities(bookmark.url) + '" target="_blank" class="bookmark_link">' + encodeEntities(bookmark.url) + '</a></p>' +
			'</div>'
		);
		$('div[data-id="'+ bookmark.id +'"]').data('record', bookmark);
		if(taglist != '') {
			$('div[data-id="'+ bookmark.id +'"]').append('<p class="bookmark_tags">' + taglist + '</p>');
		}
		$('div[data-id="'+ bookmark.id +'"] a.bookmark_tag').bind('click', addFilterTag);
	}

}

function updateOnBottom() {
	//check wether user is on bottom of the page
	var top = $('.bookmarks_list>:last-child').position().top;
	var height = $('.bookmarks_list').height();
	// use a bit of margin to begin loading before we are really at the
	// bottom
	if (top < height * 1.2) {
		getBookmarks();
	}
}

function recordClick(event) {
	$.ajax({
		type: 'POST',
		url: OC.filePath('bookmarks', 'ajax', 'recordClick.php'),
		data: 'url=' + encodeURIComponent($(this).attr('href')),
	});
}

function encodeEntities(s){
	try {
		return $('<div/>').text(s).html();
	} catch (ex) {
		return "";
	}
}

function hasProtocol(url) {
    var regexp = /(ftp|http|https|sftp)/;
    return regexp.test(url);
}

function renameTag(event) {
 tag_el = $(this).closest('li');
 tag_el.append('<input name="tag_new_name" type="text">');

 tag_name = tag_el.find('.tag').hide().text();
 tag_el.find('input').val(tag_name).bind('blur',submitTagName);

}

function submitTagName(event) {
	tag_el = $(this).closest('li')
	new_tag_name = tag_el.find('input').val();
	old_tag_name = tag_el.find('.tag').show().text();
	tag_el.find('input').remove();

	//submit
	$.ajax({
		type: 'POST',
		url: OC.filePath('bookmarks', 'ajax', 'renameTag.php'),
		data: { old_name: old_tag_name, new_name:  new_tag_name},
		success: function(bookmarks){
			if (bookmarks.status =='success') {
				filterTagsChanged();
			}
		}
	});
}

function deleteTag(event){
	tag_el = $(this).closest('li');
	var old_tag_name = tag_el.find('.tag').show().text();
	OC.dialogs.confirm(t('bookmarks', 'Are you sure you want to remove this tag fro every entry?'),
	 t('bookmarks', 'Warning'), function(answer) {
		if(answer) {
			$.ajax({
				type: 'POST',
				url: OC.filePath('bookmarks', 'ajax', 'delTag.php'),
				data: { old_name: old_tag_name},
				success: function(bookmarks){
					if (bookmarks.status =='success') {
						filterTagsChanged();
					}
				}
			});
		}
	});
}