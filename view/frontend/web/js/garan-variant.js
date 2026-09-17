/**
 * EU GARAN label of the selected variant of a configurable product.
 *
 * The server provides a map childId => {attributes: {attributeId: optionId}, label: {alt, pngFull, pngNested,
 * termsUrl}} of the qualifying variants; this module swaps image URLs, alt texts and the terms link.
 * Swatch renderers (Luma and MageSuite server-side swatches) update the super_attribute inputs and trigger a
 * jQuery "change" event, which is only visible to jQuery listeners.
 */
define([
    'jquery',
    'CopeX_WarrantyLabel/js/dialog'
], function ($, initDialogs) {
    'use strict';

    var INPUT_SELECTOR = '#product_addtocart_form [name^="super_attribute["]',
        NAME_PATTERN = /^super_attribute\[(\d+)\]$/,
        SAFE_URL_PATTERN = /^https?:\/\//i;

    /**
     * @param {String} url
     * @return {String}
     */
    function safeUrl(url) {
        return SAFE_URL_PATTERN.test(url || '') ? url : '';
    }

    /**
     * @return {Object} attributeId => optionId of the current selection
     */
    function getSelection() {
        var selection = {};

        $(INPUT_SELECTOR).each(function () {
            var match = NAME_PATTERN.exec(this.name || '');

            if (match && this.value) {
                selection[match[1]] = String(this.value);
            }
        });

        return selection;
    }

    /**
     * @param {Object} children
     * @param {Object} selection
     * @return {Object|null}
     */
    function findLabel(children, selection) {
        var childIds = Object.keys(children),
            i,
            attributes,
            attributeIds;

        /**
         * @param {String} attributeId
         * @return {Boolean}
         */
        function matches(attributeId) {
            return selection[attributeId] === String(attributes[attributeId]);
        }

        for (i = 0; i < childIds.length; i++) {
            attributes = children[childIds[i]].attributes || {};
            attributeIds = Object.keys(attributes);

            if (attributeIds.length && attributeIds.every(matches)) {
                return children[childIds[i]].label || null;
            }
        }

        return null;
    }

    /**
     * @param {Object} config
     * @param {HTMLElement} element
     */
    return function (config, element) {
        var children = config.children || {},
            output = element.querySelector('[data-copex-wl-output]'),
            terms = element.querySelector('[data-copex-wl-terms]'),
            dialog = element.querySelector('dialog'),
            currentLabel = null;

        if (!output) {
            return;
        }

        /**
         * Image of one label variant, or its text fallback when no image URL is available.
         *
         * @param {String} variant
         * @param {String} url
         * @param {String} alt
         */
        function showImage(variant, url, alt) {
            var image = output.querySelector('[data-copex-wl-img="' + variant + '"]'),
                fallback = output.querySelector('[data-copex-wl-fallback="' + variant + '"]');

            if (image) {
                if (url) {
                    image.setAttribute('src', url);
                } else {
                    image.removeAttribute('src');
                }

                image.hidden = !url;
            }

            if (fallback) {
                fallback.textContent = alt;
                fallback.hidden = Boolean(url);
            }
        }

        /**
         * Show the label of the selected variant, hide it without a complete, qualifying selection.
         */
        function update() {
            var label = findLabel(children, getSelection());

            if (label === currentLabel) {
                return;
            }

            currentLabel = label;

            if (dialog && dialog.open) {
                dialog.close();
            }

            if (!label) {
                output.hidden = true;

                return;
            }

            showImage('full', safeUrl(label.pngFull), label.alt || '');
            showImage('nested', safeUrl(label.pngNested), label.alt || '');

            Array.prototype.forEach.call(output.querySelectorAll('[data-copex-wl-alt]'), function (target) {
                target.setAttribute(target.getAttribute('data-copex-wl-alt'), label.alt || '');
            });

            if (terms) {
                terms.hidden = !safeUrl(label.termsUrl);
                terms.setAttribute('href', terms.hidden ? '#' : label.termsUrl);
            }

            output.hidden = false;
        }

        /**
         * Theme/configurable widgets may stop the propagation of "change", so a delegated document listener alone
         * is not enough: bind the inputs directly (jQuery-triggered events) and listen natively in the capture
         * phase (native events). Swatch renderers create their inputs on init, hence the re-binding.
         */
        function bindInputs() {
            $(INPUT_SELECTOR).each(function () {
                if (!this.hasAttribute('data-copex-wl-bound')) {
                    this.setAttribute('data-copex-wl-bound', '');
                    $(this).on('change', update);
                }
            });
        }

        initDialogs({}, element);
        bindInputs();
        document.addEventListener('change', function (event) {
            if (event.target && $(event.target).is(INPUT_SELECTOR)) {
                update();
            }
        }, true);
        $(document).on('change', INPUT_SELECTOR, update);
        $(document).on('swatch.initialized configurable.initialized', function () {
            bindInputs();
            update();
        });
        update();
    };
});
