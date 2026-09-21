/**
 * Nested display of the EU legal guarantee notice / GARAN label.
 *
 * Binds every `.copex-wl-trigger[aria-controls]` in the document to its native <dialog>:
 * click (Enter/Space on the button) opens it modally and focuses the close button;
 * ESC, the close button and a click on the backdrop close it and return focus to the trigger.
 */
define([], function () {
    'use strict';

    var BOUND_ATTRIBUTE = 'data-copex-wl-bound',
        CLOSE_SELECTOR = '[data-copex-wl-close]',
        duplicates = 0;

    /**
     * @param {HTMLDialogElement} dialog
     * @param {MouseEvent} event
     * @return {Boolean}
     */
    function isBackdropClick(dialog, event) {
        var rect = dialog.getBoundingClientRect();

        return event.target === dialog && (
            event.clientX < rect.left || event.clientX > rect.right ||
            event.clientY < rect.top || event.clientY > rect.bottom
        );
    }

    /**
     * @param {HTMLDialogElement} dialog
     */
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

    /**
     * @param {HTMLDialogElement} dialog
     */
    function closeDialog(dialog) {
        if (typeof dialog.close === 'function') {
            dialog.close();

            return;
        }

        dialog.removeAttribute('open');
        dialog.dispatchEvent(new Event('close'));
    }

    /**
     * Counts instead of comparing with getElementById(): that returns the first element in document order, so the
     * copy rendered last would go unnoticed whenever it happens to precede the others.
     *
     * @param {String} id
     * @return {Boolean}
     */
    function isRepeatedId(id) {
        return document.querySelectorAll('[id="' + id.replace(/["\\]/g, '\\$&') + '"]').length > 1;
    }

    /**
     * The dialog follows its trigger in every template. Knockout regions may render a template more than once —
     * Magento renders "before-place-order" inside every payment method — so a document-wide id lookup alone would
     * open the copy of a payment method that is not displayed. The neighbour wins whatever its id is, which keeps
     * this independent of the order of the "attr" and "afterRender" bindings; a missing or repeated id is replaced.
     * The ids are plain strings in the view models, so Knockout never writes them again.
     *
     * @param {HTMLElement} trigger
     * @return {HTMLDialogElement|null}
     */
    function claimDialog(trigger) {
        var id = trigger.getAttribute('aria-controls'),
            dialog = trigger.nextElementSibling;

        if (!dialog || dialog.tagName !== 'DIALOG') {
            return document.getElementById(id);
        }

        if (!dialog.id || isRepeatedId(dialog.id)) {
            duplicates++;
            dialog.id = id + '-' + duplicates;
            trigger.setAttribute('aria-controls', dialog.id);
        }

        return dialog;
    }

    /**
     * @param {HTMLElement} trigger
     */
    function bindTrigger(trigger) {
        var dialog;

        if (trigger.hasAttribute(BOUND_ATTRIBUTE)) {
            return;
        }

        dialog = claimDialog(trigger);

        if (!dialog) {
            return;
        }

        trigger.setAttribute(BOUND_ATTRIBUTE, '');

        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            openDialog(dialog);
        });

        dialog.addEventListener('click', function (event) {
            if (event.target.closest(CLOSE_SELECTOR) || isBackdropClick(dialog, event)) {
                closeDialog(dialog);
            }
        });

        // Theme scripts call preventDefault() on Escape keydown, which suppresses the native dialog "cancel".
        // Handle Escape on the dialog itself so it always closes (seen with themes that bind their own Escape handler).
        dialog.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && dialog.open) {
                event.preventDefault();
                closeDialog(dialog);
            }
        });

        dialog.addEventListener('close', function () {
            // The trigger may have been hidden meanwhile, e.g. by a variant change on the product page.
            if (!trigger.closest('[hidden]') && trigger.offsetParent !== null) {
                trigger.focus();
            }
        });
    }

    /**
     * @param {Object} config
     * @param {HTMLElement} element
     */
    return function () {
        // Scans the whole document, not just the element: in "dialog only" mode the trigger is one the shop placed
        // itself, outside every module element. bindTrigger is guarded, so repeated calls bind each trigger once.
        Array.prototype.forEach.call(document.querySelectorAll('.copex-wl-trigger[aria-controls]'), bindTrigger);
    };
});
