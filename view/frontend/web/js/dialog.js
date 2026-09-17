/**
 * Nested display of the EU legal guarantee notice / GARAN label.
 *
 * Binds every `.copex-wl-trigger[aria-controls]` inside the element to its native <dialog>:
 * click (Enter/Space on the button) opens it modally and focuses the close button;
 * ESC, the close button and a click on the backdrop close it and return focus to the trigger.
 */
define([], function () {
    'use strict';

    var BOUND_ATTRIBUTE = 'data-copex-wl-bound',
        CLOSE_SELECTOR = '[data-copex-wl-close]';

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
     * @param {HTMLElement} trigger
     */
    function bindTrigger(trigger) {
        var dialog = document.getElementById(trigger.getAttribute('aria-controls'));

        if (!dialog || trigger.hasAttribute(BOUND_ATTRIBUTE)) {
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
    return function (config, element) {
        var root = element || document;

        Array.prototype.forEach.call(root.querySelectorAll('.copex-wl-trigger[aria-controls]'), bindTrigger);
    };
});
