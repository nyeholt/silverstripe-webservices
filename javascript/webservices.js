
;(function ($) {
	window.SSWebServices = (function () {
		var securityId = $('#SecurityID').val();
		if (!securityId) {
			securityId = $('input[name=SecurityID]').val();
		}
		
		var getService = function (name, method, params, cb) {
			params['SecurityID'] = securityId;
			$.get('jsonservice/' + name + '/' + method, params, cb);
		}
		
		var postService = function (name, method, params, cb) {
			params['SecurityID'] = securityId;
			$.post('jsonservice/' + name + '/' + method, params, cb);
		}
		
		return {
			get: getService,
			post: postService
		};
	})();
})(jQuery);