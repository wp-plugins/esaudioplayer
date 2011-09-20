var esplayer_jquery_mobile_prepared = false;

// description : jQuery mobile initialization
// argument : void
jQuery(document).bind("mobileinit", function(){
	jQuery.mobile.loadingMessage = '';
	jQuery.mobile.ajaxEnabled = false;
	jQuery.mobile.ajaxLinksEnabled = false;
	jQuery.mobile.ajaxFormsEnabled = false;
	jQuery.mobile.autoInitializePage = false;
	esplayer_jquery_mobile_prepared = true;
});
