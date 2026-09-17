/**
 * EU GARAN labels of the checkout (data: window.checkoutConfig.copexWarrantyLabel.garan).
 *
 * Labels are cached PNG images; without an image URL the alt text is shown as visible text.
 */
define([
    'CopeX_WarrantyLabel/js/dialog'
], function (initDialogs) {
    'use strict';

    var data = ((window.checkoutConfig || {}).copexWarrantyLabel || {}).garan || {},
        items = data.itemsByQuoteItemId || {},
        SAFE_URL_PATTERN = /^https?:\/\//i;

    /**
     * @param {String} url
     * @return {String}
     */
    function safeUrl(url) {
        return SAFE_URL_PATTERN.test(url || '') ? url : '';
    }

    /**
     * @return {Boolean}
     */
    function isActive() {
        return data.mode === 'direct' || data.mode === 'nested';
    }

    /**
     * @param {HTMLElement} dialog
     */
    function initDialog(dialog) {
        initDialogs({}, dialog.parentNode);
    }

    /**
     * @param {String|Number} itemId
     * @param {String} scope place of the label on the page, keeps dialog ids unique
     * @return {Array}
     */
    function getLabels(itemId, scope) {
        var labels = isActive() ? items[String(itemId)] || [] : [];

        return labels.map(function (label, index) {
            return {
                productName: label.productName,
                alt: label.alt,
                termsUrl: safeUrl(label.termsUrl),
                pngFull: safeUrl(label.pngFull),
                pngNested: safeUrl(label.pngNested),
                dialogId: 'copex-wl-garan-' + scope + '-' + itemId + '-' + index,
                isNested: data.mode === 'nested',
                infoUrl: data.infoUrl,
                infoLabel: data.infoLabel,
                termsLabel: data.termsLabel,
                closeLabel: data.closeLabel,
                initDialog: initDialog
            };
        });
    }

    return {
        title: data.title || '',
        getLabels: getLabels,

        /**
         * @param {String} scope
         * @return {Array}
         */
        getAllLabels: function (scope) {
            return Object.keys(items).reduce(function (result, itemId) {
                return result.concat(getLabels(itemId, scope));
            }, []);
        }
    };
});
