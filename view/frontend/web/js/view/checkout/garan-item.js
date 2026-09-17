/**
 * EU GARAN labels below the details of one item in the checkout summary (region after_details).
 */
define([
    'uiComponent',
    'CopeX_WarrantyLabel/js/model/garan-config'
], function (Component, garanConfig) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'CopeX_WarrantyLabel/checkout/garan-item'
        },

        /**
         * @param {Object} item quote item of the summary list
         * @return {Array}
         */
        getLabels: function (item) {
            return item && item.item_id ? garanConfig.getLabels(item.item_id, 'item') : [];
        }
    });
});
