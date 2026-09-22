/**
 * Legal guarantee notice above the place order button (data: window.checkoutConfig.copexWarrantyLabel.notice).
 */
define([
    'uiComponent',
    'CopeX_WarrantyLabel/js/dialog'
], function (Component, initDialogs) {
    'use strict';

    var ASPECT_RATIO = 841.89 / 595.28,
        notice = ((window.checkoutConfig || {}).copexWarrantyLabel || {}).notice || {};

    return Component.extend({
        defaults: {
            template: 'CopeX_WarrantyLabel/checkout/notice',
            dialogId: 'copex-wl-notice-checkout'
        },

        notice: notice,

        /**
         * @return {Boolean}
         */
        isVisible: function () {
            return notice.visible === true && notice.mode !== 'off' && notice.mode !== '';
        },

        /**
         * @return {Boolean}
         */
        isNested: function () {
            return notice.mode === 'nested' || notice.mode === 'dialog_only';
        },

        /**
         * False in "dialog_only": the shop places its own trigger.
         *
         * @return {Boolean}
         */
        hasTrigger: function () {
            return notice.mode === 'nested';
        },

        /**
         * @return {Number}
         */
        getMinWidth: function () {
            return parseInt(notice.minWidthPx, 10) || 0;
        },

        /**
         * @return {Number}
         */
        getImageHeight: function () {
            return Math.round(this.getMinWidth() * ASPECT_RATIO);
        },

        /**
         * @return {String}
         */
        getWrapperStyle: function () {
            return '--copex-wl-min-width: ' + this.getMinWidth() + 'px;';
        },

        /**
         * Called after the dialog is rendered; binds the trigger that precedes it.
         *
         * @param {HTMLElement} dialog
         */
        initDialog: function (dialog) {
            initDialogs({}, dialog.parentNode);
        }
    });
});
