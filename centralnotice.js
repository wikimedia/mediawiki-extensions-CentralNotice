function selectProjects( selectAll ) {
	var selectBox = document.getElementById('projects[]');
	var firstSelect = selectBox.options.length - 1;
	for (var i = firstSelect; i >= 0; i--) {
		selectBox.options[i].selected = selectAll;
	}
}
function selectLanguages( selectAll ) {
	var selectBox = document.getElementById('project_languages[]');
	var firstSelect = selectBox.options.length - 1;
	for (var i = firstSelect; i >= 0; i--) {
		selectBox.options[i].selected = selectAll;
	}
}
function top10Languages() {
	var selectBox = document.getElementById('project_languages[]');
	var top10 = new Array('en','de','fr','it','pt','ja','es','pl','ru','nl');
	selectLanguages(false);
	for (var i = 0; i < selectBox.options.length; i++) {
		var lang = selectBox.options[i].value;
		if (top10.toString().indexOf(lang)!==-1) {
			selectBox.options[i].selected = true;
		}
	}
}
function insertButton( buttonType ) {
	var bannerField = document.getElementById('templateBody');
	switch( buttonType ) {
	case 'translate':
		var buttonValue = '[<a href="http://meta.wikimedia.org/wiki/CentralNotice">{{int:centralnotice-shared-help-translate}}</a>]';
		break;
	case 'hide':
		var buttonValue = '[<a href="#" onclick="toggleNotice();return false">{{int:centralnotice-shared-hide}}</a>]';
		break;
	case 'close':
		var buttonValue = '<a href="#" onclick="toggleNotice();return false">{{MediaWiki:centralnotice-shared-close}}</a>';
		break;
	}
	if (document.selection) {
		// IE support
		bannerField.focus();
		sel = document.selection.createRange();
		sel.text = buttonValue;
	} else if (bannerField.selectionStart || bannerField.selectionStart == '0') {
		// Mozilla support
		var startPos = bannerField.selectionStart;
		var endPos = bannerField.selectionEnd;
		bannerField.value = bannerField.value.substring(0, startPos)
		+ buttonValue
		+ bannerField.value.substring(endPos, bannerField.value.length);
	} else {
		bannerField.value += buttonValue;
	}
	bannerField.focus();
}
function validateBannerForm( form ) {
	var output = '';
	var pos = form.templateBody.value.indexOf("document.write");
	if( pos > -1 ) {
		output += documentWriteError + '\n';
	}
	if( output ) {
		alert( output );
		return false;
	}
	return true;
}
// Handle revealing the geoMultiSelector when the geotargetted checkbox is checked
( function( $ ) {
	$(document).ready(function() {
		$("#geotargeted").click(function () {
			if ($('#geotargeted:checked').val() !== undefined) {
				$("#geoMultiSelector").fadeIn('fast');
			} else {
				$("#geoMultiSelector").fadeOut('fast');
			}
		});
	});
})(jQuery);
