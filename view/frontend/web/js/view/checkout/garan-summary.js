/**
 * EU GARAN labels of all items directly above the place order button; visible without opening the
 * item list, which lives in a slide-in modal on mobile.
 */
define([
    'uiComponent',
    'CopeX_WarrantyLabel/js/model/garan-config'
], function (Component, garanConfig) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'CopeX_WarrantyLabel/checkout/garan-summary'
        },

        /**
         * @return {Object}
         */
        initialize: function () {
            this._super();
            this.labels = garanConfig.getAllLabels('summary');
            this.title = garanConfig.title;

            return this;
        }
    });
});
