var ajaxCallCount = 0;

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
	} else {
		$("#add_form_loading").css("visibility", "visible");
	}
}

$(document).ready(function () {
	$(".submit").click(function () {
		increaseAjaxCallCount();

		var endpoint = 'bookmark';
		var method = 'POST';
		var id = '';
		if($('#bookmarkID').length > 0) {
			endpoint += '/'+ $('#bookmarkID').val();
			method = 'PUT';
			id = '&record_id=' + $('#bookmarkID').val();
		}

		var tags = '';
		$('.tagit-choice .tagit-label').each(function() {
			tags += '&item[tags][]='+$(this).text();
		});
		var dataString = 'url=' + $("input#url").val() + '&description=' +
			$("textarea#description").val() + '&title=' + $("input#title").val() + tags + id;
		$.ajax({
			type: method,
			url: endpoint,
			data: dataString,
			complete: function () {
				decreaseAjaxCallCount();
			},
			success: function (data) {
				if (data.status === 'success') {
					OC.dialogs.message("Bookmark added.", "Success", undefined, [], undefined, true)
					_.delay(function() {
						window.close();
					}, 1e3);
				} else {
					OC.dialogs.alert(t("bookmarks", "Some Error happened."),
							t("bookmarks", "Error"), null, true);
				}
			}
		});
		return false;
	});

	$.get('tag', function (data) {
		$('.tags').tagit({
			allowSpaces: true,
			availableTags: data,
			placeholderText: t('bookmark', 'Tags')
		});
	});
});
