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

$(function () {
	$(".submit").click(function () {
		increaseAjaxCallCount();
        var tags = '';
        $('.tagit-choice .tagit-label').each(function() {
            tags += '&item[tags][]='+$(this).text();
        });
        var dataString = 'url=' + $("input#url").val() + '&description=' +
            $("textarea#description").val() + '&title=' + $("input#title").val() + tags;
		$.ajax({
			type: "POST",
			url: "bookmark",
			data: dataString,
			complete: function () {
				decreaseAjaxCallCount();
			},
			success: function (data) {
				if (data.status === 'success') {
					$('#bookmarklet_form').html("");
					OC.dialogs.alert(
							t("bookmarks", "Bookmark added. You can close the window now."),
							t("bookmarks", "Bookmark added successfully"), closeWindow, true);
				} else {
					OC.dialogs.alert(t("bookmarks", "Some Error happened."),
							t("bookmarks", "Error"), null, true);
				}
			}
		});
		return false;
	});
    $.get('tag', function(data) {
        $('.tags').tagit({
            allowSpaces: true,
            availableTags : data,
            placeholderText: t('bookmark', 'Tags')
        });
    });
});

function closeWindow() {
	window.close();
}