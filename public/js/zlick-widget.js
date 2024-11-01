
function xwwwfurlenc(srcjson){
    var urljson = "";
    var keys = Object.keys(srcjson);
    for(var i=0; i <keys.length; i++){
        urljson += (keys[i]) + "=" + (srcjson[keys[i]]);
        if(i < (keys.length-1)) {
            urljson+=decodeURIComponent("%26");
        }
    }
    return urljson;
}

function getCookieByName(name) {
    var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    if (match) return match[2];
}

function passSupportedParamsToWidget(zlickWidgetPurpose){

    try {
        const widgetDiv = document.getElementById("zlick-widget");
        let vuePaywallElement = document.createElement("zlick-paywall");

        vuePaywallElement.savedTokens = {
            zpArticles: getCookieByName('zp_articles')
        }
        vuePaywallElement.pluginVersion = zlick_payments_ajax.zp_plugin_version;
        vuePaywallElement.clientToken = zlick_payments_ajax.client_token;
        vuePaywallElement.purpose = zlickWidgetPurpose;
        vuePaywallElement.clientUserId = zlick_payments_ajax.user_id || '';
		vuePaywallElement.selectedPricePlans = zlick_payments_ajax.zp_price_plans || [];
		vuePaywallElement.relatedCategories = zlick_payments_ajax.zp_related_categories || [];
		vuePaywallElement.contentCategories = zlick_payments_ajax.zp_content_categories || [];
        vuePaywallElement.product = {
                id: zlick_payments_ajax.article_id, // Unique ID of the product
                name: zlick_payments_ajax.zp_article_name
            }
        vuePaywallElement.subscription = {
                id: zlick_payments_ajax.article_id // Unique ID of the product
            }
        vuePaywallElement.onAuthenticated = function (params) {
            console.log("access_info_signed: ", params);
            console.log(params.access_info_signed);
            var post_data = {
                'action': 'zp_authenticate_article',
                'zp_user_id': zlick_payments_ajax.user_id,
                'zp_article_id': zlick_payments_ajax.article_id,
                'zp_signed': params.access_info_signed,
                'zp_nonce' : zlick_payments_ajax.zp_ajax_nonce,
				'zp_signed_payment_info': params.signed_payment_info,
				'zp_sync_wp_users_with_subscribers': params.sync_wp_users_with_subscribers
            };

            var xhr = new XMLHttpRequest();
            xhr.withCredentials = true;
            xhr.open('POST', zlick_payments_ajax.ajax_url + '?action=zp_authenticate_article', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function()
            {
                if (xhr.status==200)
                {
                    if (xhr.responseText === 'reload') {
                        if (window._zp_delayed_reload === true) {
                            setTimeout(() => {
                                window.location.search += `&zp_reload=${params.zp_reload}`;
                            }, 3000);
                        } else {
                            window.location.search += `&zp_reload=${params.zp_reload}`;
                        }
                    }
                } else {
                    return "something went wrong.";
            }
            }
            xhr.send(xwwwfurlenc(post_data));
        }
		vuePaywallElement.onAccessExpired = function (params) {
            console.log("onAccessExpired callback triggered: ", params);
            console.log(params.unsigned_payment_info);
            var post_data = {
                'action': 'zp_access_expired',
                'zp_nonce' : zlick_payments_ajax.zp_ajax_nonce,
				'zp_signed_payment_info': params.signed_payment_info,
				'zp_sync_wp_users_with_subscribers': params.sync_wp_users_with_subscribers
            };

            var xhr = new XMLHttpRequest();
            xhr.withCredentials = true;
            xhr.open('POST', zlick_payments_ajax.ajax_url + '?action=zp_access_expired', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function()
            {
                if (xhr.status==200)
                {
                    if (xhr.responseText === 'reload') {
                        if (window._zp_delayed_reload === true) {
                            setTimeout(() => {
                                window.location.search += `&zp_reload=${params.zp_reload}`;
                            }, 3000);
                        } else {
                            window.location.search += `&zp_reload=${params.zp_reload}`;
                        }
                    }
                } else {
                    return "something went wrong.";
            }
            }
            xhr.send(xwwwfurlenc(post_data));
        }
        vuePaywallElement.onPaymentComplete = function ({ data }) {
            // window._zp_delayed_reload = true;
        }
        vuePaywallElement.onFailure = (data)=>{
            console.log('onFailure', data)
        }
        // Patch for client bug two widgets shown for some reason.
        while (widgetDiv.lastElementChild) {
            widgetDiv.removeChild(widgetDiv.lastElementChild);
        }
        widgetDiv.appendChild(vuePaywallElement);
    } catch(error){
        console.log("got error before attaching props :", error)
    }
}

function renderZlickWidget(zlickWidgetPurpose) {
    passSupportedParamsToWidget(zlickWidgetPurpose);
}

jQuery( document ).ready(function($) {
	renderZlickWidget('subscribe');
});
