/**
 * Purchase report — admin behaviour.
 *
 * Moves the summary box above the orders table and remembers whether it is
 * open, sends the review-ask choice and "Hide for 30 days" without leaving the
 * page, and sends the summary test email. The widget links work without this
 * script too.
 */
(function ($) {
    'use strict';

    let config = window.pysPurchaseReport || {};
    let OPEN_KEY = 'pysPurchaseReportBoxOpen';

    /**
     * The list hooks print the box inside or below the table navigation;
     * it belongs right above the table, as wide as the table.
     */
    function placeListBox() {
        let $box = $('.pys-pr-list-box').first();
        let $table = $('table.wp-list-table').first();

        if (!$box.length) {
            return;
        }
        if ($table.length) {
            $box.insertBefore($table);
        }
        $box.prop('hidden', false);

        let details = $box.find('.pys-pr-list-box__details').get(0);
        if (!details) {
            return;
        }

        try {
            if (window.localStorage.getItem(OPEN_KEY) === '1') {
                details.open = true;
            }
            details.addEventListener('toggle', function () {
                window.localStorage.setItem(OPEN_KEY, details.open ? '1' : '0');
            });
        } catch (e) {
            // Storage is blocked: the box simply starts closed.
        }
    }

    /**
     * Send a widget action by AJAX and remove its block. The block is dimmed
     * and its links locked until the reply.
     */
    function sendWidgetAction($block, data, removeSelector) {
        if ($block.hasClass('is-loading')) {
            return;
        }
        $block.addClass('is-loading');

        $.post(config.ajaxUrl, data).always(function () {
            $(removeSelector).remove();
        });
    }

    function bindReviewAsk() {
        $(document).on('click', '.pys-pr-review__choice', function (event) {
            event.preventDefault();
            sendWidgetAction($(this).closest('.pys-pr-review'), {
                action: config.reviewAction,
                _ajax_nonce: config.reviewNonce,
                choice: $(this).data('choice')
            }, '.pys-pr-review');
        });
    }

    function bindHidePro() {
        $(document).on('click', '.pys-pr-pro__hide', function (event) {
            event.preventDefault();
            // Hidden for the user, so in the widgets of both stores.
            sendWidgetAction($(this).closest('.pys-pr-pro'), {
                action: config.hideProAction,
                _ajax_nonce: config.hideProNonce
            }, '.pys-pr-pro');
        });
    }

    function bindTestEmail() {
        $(document).on('click', '.pys-pr-settings__test', function () {
            let $button = $(this);
            let $result = $button.siblings('.pys-pr-settings__test-result');
            let period = $button.closest('.pys-pr-settings').find('input[type="radio"][name*="purchase_summary_email_period"]:checked').val();

            $button.prop('disabled', true);
            $result.text(config.sending);

            $.post(config.ajaxUrl, {
                action: config.testAction,
                _ajax_nonce: config.testNonce,
                period: period || ''
            }).done(function (response) {
                $result.text(response && response.data && response.data.message ? response.data.message : config.sendFailed);
            }).fail(function (xhr) {
                let data = xhr.responseJSON && xhr.responseJSON.data;
                $result.text(data && data.message ? data.message : config.sendFailed);
            }).always(function () {
                $button.prop('disabled', false);
            });
        });
    }

    $(function () {
        placeListBox();
        bindReviewAsk();
        bindHidePro();
        bindTestEmail();
    });
})(jQuery);
