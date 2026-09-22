/**
 * Nested dialogs and GARAN variant switching for Hyvä themes.
 *
 * Hyvä ships no RequireJS, so the data-mage-init hooks of the shared templates are never initialised there. This
 * script reads the very same configuration out of those attributes and binds the very same DOM, without jQuery and
 * without AMD. It is loaded by the hyva_* layout handles only, which Hyvä adds exclusively while a Hyvä theme is
 * active (Hyva\Theme\Observer\AddLayoutHandles), so Luma keeps using js/dialog.js and js/garan-variant.js.
 *
 * Luma needs jQuery because its swatch renderer triggers a jQuery "change" that native listeners never see. Hyvä
 * renders real radio inputs, so native events are enough here.
 */
(function () {
    'use strict';

    var VARIANT_MODULE = 'CopeX_WarrantyLabel/js/garan-variant',
        CLOSE_SELECTOR = '[data-copex-wl-close]',
        INPUT_SELECTOR = '#product_addtocart_form [name^="super_attribute["]',
        NAME_PATTERN = /^super_attribute\[(\d+)\]$/,
        SAFE_URL_PATTERN = /^https?:\/\//i;

    function safeUrl(url) {
        return SAFE_URL_PATTERN.test(url || '') ? url : '';
    }

    function isBackdropClick(dialog, event) {
        var rect = dialog.getBoundingClientRect();

        return event.target === dialog && (
            event.clientX < rect.left || event.clientX > rect.right ||
            event.clientY < rect.top || event.clientY > rect.bottom
        );
    }

    function openDialog(dialog) {
        var closeButton = dialog.querySelector(CLOSE_SELECTOR);

        if (typeof dialog.showModal === 'function') {
            if (!dialog.open) {
                dialog.showModal();
            }
        } else {
            dialog.setAttribute('open', '');
        }

        if (closeButton) {
            closeButton.focus();
        }
    }

    function closeDialog(dialog) {
        if (typeof dialog.close === 'function') {
            dialog.close();

            return;
        }

        dialog.removeAttribute('open');
        dialog.dispatchEvent(new Event('close'));
    }

    /**
     * The dialog follows its trigger in every template; the id is the fallback for templates that separate them.
     */
    function bindTrigger(trigger) {
        var sibling = trigger.nextElementSibling,
            dialog = sibling && sibling.tagName === 'DIALOG'
                ? sibling
                : document.getElementById(trigger.getAttribute('aria-controls'));

        if (!dialog || trigger.hasAttribute('data-copex-wl-bound')) {
            return;
        }

        trigger.setAttribute('data-copex-wl-bound', '');

        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            openDialog(dialog);
        });

        dialog.addEventListener('click', function (event) {
            if (event.target.closest(CLOSE_SELECTOR) || isBackdropClick(dialog, event)) {
                closeDialog(dialog);
            }
        });

        // Theme scripts may preventDefault() on Escape, which suppresses the native dialog "cancel".
        dialog.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && dialog.open) {
                event.preventDefault();
                closeDialog(dialog);
            }
        });

        dialog.addEventListener('close', function () {
            if (!trigger.closest('[hidden]') && trigger.offsetParent !== null) {
                trigger.focus();
            }
        });
    }

    function bindDialogs(root) {
        Array.prototype.forEach.call(root.querySelectorAll('.copex-wl-trigger[aria-controls]'), bindTrigger);
    }

    function getSelection() {
        var selection = {};

        Array.prototype.forEach.call(document.querySelectorAll(INPUT_SELECTOR), function (input) {
            var match = NAME_PATTERN.exec(input.name || '');

            if (!match || !input.value) {
                return;
            }

            // Radio groups report every button, only the checked one carries the selection.
            if (input.type !== 'radio' && input.type !== 'checkbox') {
                selection[match[1]] = String(input.value);
            } else if (input.checked) {
                selection[match[1]] = String(input.value);
            }
        });

        return selection;
    }

    function findLabel(children, selection) {
        var childIds = Object.keys(children),
            i,
            attributes,
            attributeIds;

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

    function initVariants(element, children) {
        var output = element.querySelector('[data-copex-wl-output]'),
            terms = element.querySelector('[data-copex-wl-terms]'),
            dialog = element.querySelector('dialog'),
            currentLabel = null;

        if (!output) {
            return;
        }

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

        function update() {
            var label = findLabel(children, getSelection());

            if (label === currentLabel) {
                return;
            }

            currentLabel = label;

            if (dialog && dialog.open) {
                closeDialog(dialog);
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

        // Capture phase, because Alpine components between input and document may stop the propagation.
        document.addEventListener('change', function (event) {
            if (event.target && event.target.closest && event.target.closest(INPUT_SELECTOR)) {
                update();
            }
        }, true);

        // Hyvä preselects a swatch without firing "change" when only one option qualifies.
        document.addEventListener('alpine:initialized', update);
        update();
    }

    function init() {
        Array.prototype.forEach.call(document.querySelectorAll('.copex-wl[data-mage-init]'), function (element) {
            var config;

            try {
                config = JSON.parse(element.getAttribute('data-mage-init'));
            } catch (error) {
                return;
            }

            bindDialogs(element);

            if (config[VARIANT_MODULE]) {
                initVariants(element, config[VARIANT_MODULE].children || {});
            }
        });

        // Placements whose template carries no data-mage-init at all.
        bindDialogs(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
