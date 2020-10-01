/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (Component,
              rendererList) {
        'use strict';
        rendererList.push(
            {
                type: 'erip',
                component: 'Expresspay_Erip/js/view/payment/method-renderer/erip-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);