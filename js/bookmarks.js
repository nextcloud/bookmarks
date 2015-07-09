var bookmarksPage = 0;
var bookmarksLoading = false;
var dialog;
var bookmarksSorting = 'bookmarks_sorting_recent';
var fullTags = [];
var ajaxCallCount = 0;

$(document).ready(function () {
	getTags();
	watchUrlField();
	$('#bm_import').change(attachSettingEvent);
	$('#add_url').on('keydown keyup change click', watchUrlField);
	$('#app-settings').on('click keydown', toggleSettings);
	$('#bm_export').click(exportBm);
	$('#emptycontent-setting').click(function () {
		if (!$('#app-settings').hasClass('open')) {
			$('#app-settings').click();
		}
	});
	$('.bookmarks_list').scroll(updateOnBottom).empty();
	$('#tag_filter input').tagit({
		allowSpaces: true,
		availableTags: fullTags,
		onTagFinishRemoved: filterTagsChanged,
		placeholderText: t('bookmarks', 'Filter by tag')
	}).tagit('option', 'onTagAdded', filterTagsChanged);
	getBookmarks();
});

function getTags() {
	jQuery.ajax({
		url: 'tag',
		success: function (result) {
			fullTags = result;
		},
		async: false
	});
}

var formatString = (function () {
	var replacer = function (context) {
		return function (s, name) {
			return context[name];
		};
	};

	return function (input, context) {
		return input.replace(/\{(\w+)\}/g, replacer(context));
	};
})();

function increaseAjaxCallCount() {
	ajaxCallCount++;
	if (ajaxCallCount - 1 === 0) {
		updateLoadingAnimation();
	}
}

function decreaseAjaxCallCount() {
	if (ajaxCallCount > 0) {
		ajaxCallCount--;
		updateLoadingAnimation();
	}
}

function updateLoadingAnimation() {
	if (ajaxCallCount === 0) {
		$("#add_form_loading").css("visibility", "hidden");
		$("#bookmark_add_submit").css("color", "");
	} else {
		$("#add_form_loading").css("visibility", "visible");
		$("#bookmark_add_submit").css("color", "transparent");
		
	}
}

function watchClickInSetting(e) {
	if ($('#app-settings').find($(e.target)).length === 0) {
		toggleSettings();
	}
}

function checkURL(url) {
	if (url.substring(0, 3) === "htt") {
		return url;
	}
	return "http://" + url;
}

function toggleSettings() {
	if ($('#app-settings').hasClass('open')) { //Close
		$('#app-settings').switchClass("open", "");
		$('body').unbind('click', watchClickInSetting);
	}
	else {
		$('#app-settings').switchClass("", "open");
		$('body').bind('click', watchClickInSetting);
	}
}
function addFilterTag(event) {
	event.preventDefault();
	$('#tag_filter input').tagit('createTag', $(this).text());
}

function updateTagsList(tag) {
	var html = tmpl("tag_tmpl", tag);
	$('.tag_list').append(html);
}

function filterTagsChanged()
{
	$('#bookmarkFilterTag').val($('#tag_filter input').val());
	$('.bookmarks_list').empty();
	bookmarksPage = 0;
	getBookmarks();
}
function getBookmarks() {
	if (bookmarksLoading) {
		//have patience :)
		return;
	}
	increaseAjaxCallCount();
	bookmarksLoading = true;
	//Update Rel Tags if first page
	if (bookmarksPage === 0) {

		$.ajax({
			type: 'GET',
			url: 'bookmark',
			data: {type: 'rel_tags', tag: $('#bookmarkFilterTag').val(), page: bookmarksPage, sort: bookmarksSorting},
			success: function (tags) {
				$('.tag_list').empty();
				for (var i in tags.data) {
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
		url: 'bookmark',
		data: {type: 'bookmark', tag: $('#bookmarkFilterTag').val(), page: bookmarksPage, sort: bookmarksSorting},
		complete: function () {
			decreaseAjaxCallCount();
		},
		success: function (bookmarks) {
			if (bookmarks.data.length) {
				bookmarksPage += 1;
			}
			$('.bookmark_link').unbind('click', recordClick);
			$('.bookmark_delete').unbind('click', delBookmark);
			$('.bookmark_edit').unbind('click', editBookmark);

			for (var i in bookmarks.data) {
				updateBookmarksList(bookmarks.data[i]);
			}
			checkEmpty();

			$('.bookmark_link').click(recordClick);
			$('.bookmark_delete').click(delBookmark);
			$('.bookmark_edit').click(editBookmark);

			bookmarksLoading = false;
			if (bookmarks.data.length) {
				updateOnBottom();
			}
		}
	});
}

function watchUrlField() {
	var form = $('#add_form');
	var el = $('#add_url');
	var button = $('#bookmark_add_submit');
	form.unbind('submit');
	if (!acceptUrl(el.val())) {
		form.bind('submit', function (e) {
			e.preventDefault();
		});
		button.addClass('disabled');
	}
	else {
		button.removeClass('disabled');
		form.bind('submit', addBookmark);
	}
}

function acceptUrl(url) {
	return url.replace(/^\s+/g, '').replace(/\s+$/g, '') !== '';
}

function addBookmark(event) {
	event.preventDefault();
	var url = $('#add_url').val();
	//If trim is empty
	if (!acceptUrl(url)) {
		return;
	}

	$('#add_url').val('');
	var bookmark = {url: url, description: '', title: '', from_own: 0, added_date: new Date()};
	increaseAjaxCallCount();
	$.ajax({
		type: 'POST',
		url: 'bookmark',
		data: bookmark,
		complete: function () {
			decreaseAjaxCallCount();
		},
		success: function (data) {
			if (data.status === 'success') {
				// First remove old BM if exists
				$('.bookmark_single').filterAttr('data-id', data.item.id).remove();

				var bookmark = $.extend({}, bookmark, data.item);
				updateBookmarksList(bookmark, 'prepend');
				checkEmpty();
				watchUrlField();
			}
		}
	});
}

function delBookmark() {
	var record = $(this).parent().parent();
	OC.dialogs.confirm(t('bookmarks', 'Are you sure you want to remove this bookmark?'),
			t('bookmarks', 'Warning'), function (answer) {
		if (answer) {
			$.ajax({
				type: 'DELETE',
				url: 'bookmark/' + record.data('id'),
				success: function (data) {
					if (data.status === 'success') {
						record.remove();
						checkEmpty();
					}
				}
			});
		}
	});
}

function checkEmpty() {
	if ($('.bookmarks_list').children().length === 0) {
		$("#emptycontent").show();
		$("#bm_export").addClass('disabled');
		$('.bookmarks_list').hide();
	} else {
		$("#emptycontent").hide();
		$("#bm_export").removeClass('disabled');
		$('.bookmarks_list').show();
	}
}
function editBookmark() {
	if ($('.bookmark_single_form').length) {
		$('.bookmark_single_form .reset').click();
	}
	var record = $(this).parent().parent();
	var bookmark = record.data('record');
	var html = tmpl("item_form_tmpl", bookmark);

	record.after(html);
	record.hide();
	var rec_form = record.next().find('form');
	rec_form.find('.bookmark_form_tags ul').tagit({
		allowSpaces: true,
		availableTags: fullTags,
		placeholderText: t('bookmarks', 'Tags')
	});

	rec_form.find('.reset').bind('click', cancelBookmark);
	rec_form.bind('submit', function (event) {
		event.preventDefault();
		var form_values = $(this).serialize();
		$.ajax({
			type: 'PUT',
			url: $(this).attr('action') + "/" + this.elements['record_id'].value,
			data: form_values,
			success: function (data) {
				if (data.status === 'success') {
					//@TODO : do better reaction than reloading the page
					filterTagsChanged();
				} else { // On failure
					//@TODO : show error message?
				}
			}
		});
	});
}

function cancelBookmark(event) {
	event.preventDefault();
	var rec_form = $(this).closest('form').parent();
	rec_form.prev().show();
	rec_form.remove();
}

function updateBookmarksList(bookmark, position) {
	position = typeof position !== 'undefined' ? position : 'append';
	bookmark = $.extend({title: '', description: '', added_date: new Date('now'), tags: []}, bookmark);
	var tags = bookmark.tags;
	var taglist = '';
	for (var i = 0, len = tags.length; i < len; ++i) {
		if (tags[i] !== '')
			taglist = taglist + '<a class="bookmark_tag" href="#">' + escapeHTML(tags[i]) + '</a> ';
	}
	if (!hasProtocol(bookmark.url)) {
		bookmark.url = 'http://' + bookmark.url;
	}

	if (bookmark.added) {
		bookmark.added_date.setTime(parseInt(bookmark.added) * 1000);
	}

	if (!bookmark.title)
		bookmark.title = '';

	var html = tmpl("item_tmpl", bookmark);
	if (position === "prepend") {
		$('.bookmarks_list').prepend(html);
	} else {
		$('.bookmarks_list').append(html);
	}
	var line = $('div[data-id="' + bookmark.id + '"]');
	line.data('record', bookmark);
	if (taglist !== '') {
		line.append('<p class="bookmark_tags">' + taglist + '</p>');
	}
	line.find('a.bookmark_tag').bind('click', addFilterTag);
	line.find('.bookmark_link').click(recordClick);
	line.find('.bookmark_delete').click(delBookmark);
	line.find('.bookmark_edit').click(editBookmark);

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

function recordClick() {
	$.ajax({
		type: 'POST',
		url: 'bookmark/click',
		data: 'url=' + encodeURIComponent($(this).attr('href'))
	});
}

function hasProtocol(url) {
	var regexp = /(ftp|http|https|sftp)/;
	return regexp.test(url);
}

function renameTag() {
	if ($('input[name="tag_new_name"]').length)
		return; // Do nothing if a tag is currenlty edited
	var tagElement = $(this).closest('li');
	tagElement.append('<form><input name="tag_new_name" type="text"></form>');
	var form = tagElement.find('form');
	//tag_el.find('.tags_actions').hide();
	var tagName = tagElement.find('.tag').hide().text();
	tagElement.find('input').val(tagName).focus().bind('blur', function () {
		form.trigger('submit');
	});
	form.bind('submit', submitTagName);
}

function submitTagName(event) {
	event.preventDefault();
	var tagElement = $(this).closest('li');
	var newTagName = tagElement.find('input').val();
	var oldTagName = tagElement.find('.tag').show().text();
	//tag_el.find('.tag_edit').show();
	//tag_el.find('.tags_actions').show();
	tagElement.find('input').unbind('blur');
	tagElement.find('form').unbind('submit').remove();

	if (newTagName !== oldTagName && newTagName !== '') {
		//submit
		$.ajax({
			type: 'POST',
			url: 'tag',
			data: {old_name: oldTagName, new_name: newTagName},
			success: function (bookmarks) {
				if (bookmarks.status === 'success') {
					filterTagsChanged();
				}
			}
		});
	}
}

function deleteTag() {
	var tag_el = $(this).closest('li');
	var old_tag_name = tag_el.find('.tag').show().text();
	OC.dialogs.confirm(t('bookmarks', 'Are you sure you want to remove this tag from every entry?'),
			t('bookmarks', 'Warning'), function (answer) {
		if (answer) {
			$.ajax({
				type: 'DELETE',
				url: 'tag',
				data: {old_name: old_tag_name},
				success: function (bookmarks) {
					if (bookmarks.status === 'success') {
						filterTagsChanged();
					}
				}
			});
		}
	});
}

