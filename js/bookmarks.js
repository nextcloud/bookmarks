var bookmarks_page = 0;
var bookmarks_loading = false;
var dialog;
var bookmarks_sorting = 'bookmarks_sorting_recent';

var bookmark_view = 'image';

$(document).ready(function() {
	watchUrlField();
	$('.centercontent').click(clickSideBar);
	$('#view_type input').click(clickSwitchView);
	
	$('#bm_import').change(attachSettingEvent);
	$('#add_url').on('keydown keyup change click', watchUrlField);
  $('#settingsbtn').on('click keydown', function() {
		if( $('#bookmark_settings').hasClass('open'))
			$('#bookmark_settings').switchClass( "open", "" );
		else
			$('#bookmark_settings').switchClass( "", "open");
	});
	$('#bm_export').click(exportBm);

	$(window).resize(function () {
		fillWindow($('.bookmarks_list'));
	});
	$(window).resize();
	$('.bookmarks_list').scroll(updateOnBottom).empty().width($('#rightcontent').width());
	$('#tag_filter input').tagit({
		allowSpaces: true,
		availableTags: fullTags,
		onTagFinishRemoved: filterTagsChanged
	}).tagit('option', 'onTagAdded', filterTagsChanged);
	getBookmarks();
	
	if(init_sidebar != 'true')
		toggleSideBar();
	bookmark_view = init_view;
	switchView();
});


var formatString = (function() {
	var replacer = function(context) {
		return function(s, name) {
			return context[name];
		};
	};

	return function(input, context) {
		return input.replace(/\{(\w+)\}/g, replacer(context));
	};
})();

function clickSideBar() {
	$.post(OC.filePath('bookmarks', 'ajax', 'changescreen.php'), {sidebar: $('.right_img').is(':visible')});
	toggleSideBar();
}

function toggleSideBar(){
	var left_pan_visible = $('.right_img').is(':visible');
	anim_duration = 1000;
	if( left_pan_visible) { // then show the left panel
		$('#rightcontent').animate({'left':'32.5em'},{duration: anim_duration, queue: false });
		$('.bookmarks_list').animate({'width': '-=15em'},{duration: anim_duration, queue: false });
		$('#leftcontent').animate({'margin-left':'0', 'opacity': 1},{duration: anim_duration, queue: false, complete: function() { $(window).trigger('resize'); }});
		$('.right_img').hide();
		$('.left_img').show();
		
	} else { // hide the left panel
		$('#rightcontent').animate({'left':'15.5em'},{duration: anim_duration, queue: false });
		$('.bookmarks_list').animate({'width': '+=15em'},{duration: anim_duration, queue: false });
		$('#leftcontent').animate({'margin-left':'-17em', 'opacity': 0.5 },{duration: anim_duration, queue: false, complete: function() { $(window).trigger('resize'); } });
		$('.left_img').hide();
		$('.right_img').show();
	}
}

function clickSwitchView(){
	$.post(OC.filePath('bookmarks', 'ajax', 'changescreen.php'), {view:bookmark_view});
	switchView();
}

function switchView(){
	if(bookmark_view == 'list') { //Then switch to img
		$('.bookmarks_list').addClass('bm_view_img');
		$('.bookmarks_list').removeClass('bm_view_list');
		$('#view_type input.image').hide();
		$('#view_type input.list').show();
		bookmark_view = 'image';
	} else { // Then Image
		$('.bookmarks_list').addClass('bm_view_list');
		$('.bookmarks_list').removeClass('bm_view_img');
		$('#view_type input.list').hide();
		$('#view_type input.image').show();
		bookmark_view = 'list';
	}
	filterTagsChanged(); //Refresh the view
}
function addFilterTag(event) {
	event.preventDefault();
	$('#tag_filter input').tagit('createTag', $(this).text());
}

function updateTagsList(tag) {
	html = tmpl("tag_tmpl", tag);
	$('.tag_list').append(html);
}

function filterTagsChanged()
{
	$('#bookmarkFilterTag').val($('#tag_filter input:hidden').val());
	$('.bookmarks_list').empty();
	bookmarks_page = 0;
	getBookmarks();
}
function getBookmarks() {
	if(bookmarks_loading) {
		//have patience :)
		return;
	}
	bookmarks_loading = true;
	//Update Rel Tags if first page
	if(bookmarks_page == 0) {

		$.ajax({
			type: 'GET',
			url: OC.filePath('bookmarks', 'ajax', 'updateList.php'),
			data: { type:'rel_tags', tag: $('#bookmarkFilterTag').val(), page:bookmarks_page, sort:bookmarks_sorting },
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
	}
	$.ajax({
		type: 'GET',
		url: OC.filePath('bookmarks', 'ajax', 'updateList.php'),
		data: { type:'bookmark', tag: $('#bookmarkFilterTag').val(), page:bookmarks_page, sort:bookmarks_sorting },
		success: function(bookmarks){
			if (bookmarks.data.length) {
				bookmarks_page += 1;
			}
			$('.bookmark_link').unbind('click', recordClick);
			$('.bookmark_delete').unbind('click', delBookmark);
			$('.bookmark_edit').unbind('click', editBookmark);

			for(var i in bookmarks.data) {
				updateBookmarksList(bookmarks.data[i]);
			}
			checkEmpty();

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
	var oc_dialog= $('#edit_dialog form').clone().dialog({
		width : 620,
		height: 350,
		title: t('bookmark', 'Edit bookmark'),
		modal: true,
		close : function(event, ui) {
			$(this).dialog('destroy').remove();
		}
	});

	$('.ui-dialog').bookmark_dialog({
		on_success: function(){
			oc_dialog.dialog('destroy').remove();
			filterTagsChanged();
		},
		record: record
	});
}

function watchUrlField(){
	var form = $('#add_form');
	var el = $('#add_url');
	var button = $('#bookmark_add_submit');
	form.unbind('submit');
	if(! acceptUrl(el.val()) ) {
		form.bind('submit',function(e){e.preventDefault()});
		button.addClass('disabled');
	}
	else{
		button.removeClass('disabled');
		form.bind('submit',addBookmark);
	}
}

function acceptUrl(url) {
	return url.replace(/^\s+/g,'').replace(/\s+$/g,'') != '';
}

function addBookmark(event) {
	event.preventDefault();
	url = $('#add_url').val();
	//If trim is empty
	if(! acceptUrl(url) ) {
		return;
	}
	
	$('#add_url').val('');
	bookmark = { url: url, description:'', title:'', from_own: '1'};
	$.ajax({
		type: 'POST',
		url: OC.filePath('bookmarks', 'ajax', 'editBookmark.php'),
		data: bookmark,
		success: function(data){
			if (data.status == 'success') {
				bookmark.id = data.id;
				bookmark.title = data.title
				bookmark.added_date = new Date();
				updateBookmarksList(bookmark, 'prepend');
				checkEmpty();
			}
		}
	});
}

function delBookmark(event) {
	var record = $(this).parent().parent();
	$.ajax({
		type: 'POST',
		url: OC.filePath('bookmarks', 'ajax', 'delBookmark.php'),
		data: { id: record.data('id') },
		success: function(data){
			if (data.status == 'success') {
				record.remove();
				checkEmpty();
			}
		}
	});
}

function checkEmpty() {
	if($('.bookmarks_list').is(':empty')) {
		$("#firstrun").show();
	} else {
		$("#firstrun").hide();
	}
}
function editBookmark(event) {
	if($('.bookmark_single_form').length){
		$('.bookmark_single_form .reset').click();
	}
	var record = $(this).parent().parent();
	bookmark =  record.data('record');
	html = tmpl("item_form_tmpl", bookmark);
	
	record.after(html);
	record.hide();
	rec_form = record.next().find('form');
	rec_form.find('.bookmark_form_tags ul').tagit({
				allowSpaces: true,
				availableTags: fullTags,
				placeholderText: t('bookmark', 'Tags')
			});
	rec_form.bind('submit',submitBookmark);
	rec_form.find('.reset').bind('click',cancelBookmark);
}

function cancelBookmark(event) {
	event.preventDefault();
	rec_form = $(this).closest('form').parent();
	rec_form.prev().show();
	rec_form.remove();
}

function submitBookmark(event) {
	event.preventDefault();
	form_values = $(this).serialize();
	$.ajax({
		type: 'POST',
		url: $(this).attr('action'),
		data: form_values,
		success: function(data){
			if(data.status == 'success'){
				//@TODO : do better reaction than reloading the page
				filterTagsChanged();
			} else { // On failure
				//@TODO : show error message?
			}
		}
	});
}

function updateBookmarksList(bookmark, position) {
	position = typeof position !== 'undefined' ? position : 'append';
	bookmark = $.extend({title:'', description:'', added_date: new Date('now'), tags:[] }, bookmark);
	tags = bookmark.tags;
	var taglist = '';
	for ( var i=0, len=tags.length; i<len; ++i ){
		if(tags[i] != '')
			taglist = taglist + '<a class="bookmark_tag" href="#">' + encodeEntities(tags[i]) + '</a> ';
	}
	if(!hasProtocol(bookmark.url)) {
		bookmark.url = 'http://' + bookmark.url;
	}
	
	if(bookmark.added) {
		bookmark.added_date.setTime(parseInt(bookmark.added)*1000);
	}
	
	if(bookmark_view == 'image') { //View in images
		service_url = formatString(shot_provider, {url: encodeEntities(bookmark.url), title: bookmark.title, width: 200});
		bookmark['service_url'] = service_url;
		html = tmpl("img_item_tmpl", bookmark);
		$('.bookmarks_list').append(html);
		$('div[data-id="'+ bookmark.id +'"]').data('record', bookmark);
		if(taglist != '') {
			$('div[data-id="'+ bookmark.id +'"]').append('<p class="bookmark_tags">' + taglist + '</p>');
		}
		$('div[data-id="'+ bookmark.id +'"] a.bookmark_tag').bind('click', addFilterTag);
	}
	else {
		html = tmpl("item_tmpl", bookmark);
		if(position == "prepend") {
			$('.bookmarks_list').prepend(html);
		} else {
			$('.bookmarks_list').append(html);
		}
		line = $('div[data-id="'+ bookmark.id +'"]');
		line.data('record', bookmark);
		if(taglist != '') {
			line.append('<p class="bookmark_tags">' + taglist + '</p>');
		}
		line.find('a.bookmark_tag').bind('click', addFilterTag);
		line.find('.bookmark_link').click(recordClick);
		line.find('.bookmark_delete').click(delBookmark);
		line.find('.bookmark_edit').click(editBookmark);
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
	if($('input[name="tag_new_name"]').length) return; // Do nothing if a tag is currenlty edited
	tag_el = $(this).closest('li');
	tag_el.append('<form><input name="tag_new_name" type="text"></form>');
	var form = tag_el.find('form');
	tag_el.find('.tags_actions').hide();
	tag_name = tag_el.find('.tag').hide().text();
	tag_el.find('input').val(tag_name).focus().bind('blur',function() {
		form.trigger('submit');
	});
	form.bind('submit',submitTagName);
}

function submitTagName(event) {
	event.preventDefault();
	tag_el = $(this).closest('li')
	new_tag_name = tag_el.find('input').val();
	old_tag_name = tag_el.find('.tag').show().text();
	tag_el.find('.tag_edit').show();
	tag_el.find('.tags_actions').show();
	tag_el.find('input').unbind('blur');
	tag_el.find('form').unbind('submit').remove();

	if(new_tag_name != old_tag_name && new_tag_name != '') {
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
}

function deleteTag(event){
	tag_el = $(this).closest('li');
	var old_tag_name = tag_el.find('.tag').show().text();
	OC.dialogs.confirm(t('bookmarks', 'Are you sure you want to remove this tag from every entry?'),
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

