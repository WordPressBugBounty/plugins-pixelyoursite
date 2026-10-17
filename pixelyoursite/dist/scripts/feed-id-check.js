/**
 * Product feed ID check — WooCommerce tab behaviour.
 *
 * Everything here is client-side only: rows expand/collapse, links open the
 * right ID settings card, and "Apply suggested settings" pre-fills form fields.
 * Nothing is saved until the page's own Save Changes button is clicked.
 */
(function ($) {
    'use strict';

    var msgs = (window.pysFeedIdCheck && window.pysFeedIdCheck.messages) || {};
    var applied = {}; // box id => { slug, prev: {key => value}, added: {key => option value}, html: original box HTML }

    function flash($el) {
        if (!$el || !$el.length) { return; }
        $el.addClass('pfc-field-flash');
        setTimeout(function () { $el.removeClass('pfc-field-flash'); }, 1800);
    }

    function scrollTo($el) {
        if (!$el || !$el.length) { return; }
        var top = $el.offset().top - 90;
        $('html, body').animate({ scrollTop: top }, 300);
    }

    /* Expand a collapsible PYS card (card-style5 or card-style6) if its body is hidden. */
    function expandCard($card) {
        if (!$card || !$card.length) { return; }
        var $body = $card.children('.card-body');
        var $header = $card.children('.card-header');
        if ($body.length && !$body.is(':visible')) {
            $body.slideDown(300);
            $header.addClass('header-opened');
        }
    }

    /* Open the check card, its first "differ" row, and highlight it (notice button lands here). */
    function openFromHash() {
        if (window.location.hash !== '#pys-feed-id-check') { return; }
        var $card = $('#pys-feed-id-check');
        if (!$card.length) { return; }
        expandCard($card);
        var $row = $card.find('.pfc-row').filter(function () {
            return $(this).find('.pfc-row-header .pfc-status.pfc-bad').length > 0;
        }).first();
        if ($row.length) {
            toggleRow($row, true);
        }
        setTimeout(function () {
            scrollTo($row.length ? $row : $card);
            flash($row.length ? $row : $card);
        }, 350);
        // Drop the hash so a later save/reload doesn't reopen the card.
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', window.location.pathname + window.location.search);
        }
    }

    function toggleRow($row, forceOpen) {
        var $body = $row.children('.card-body');
        var $header = $row.children('.pfc-row-header');
        var open = forceOpen === true ? true : (forceOpen === false ? false : !$body.is(':visible'));
        if (open) { $body.slideDown(250); } else { $body.slideUp(250); }
        $header.toggleClass('header-opened', open).attr('aria-expanded', open ? 'true' : 'false');
        $row.toggleClass('open', open);
    }

    /* Find the ID settings sub-card for a module slug ('fblogic' = the Integration card). */
    function settingsCardFor(slug) {
        var $field;
        if (slug === 'fblogic') {
            $field = $('input[name="pys[facebook][woo_content_id_logic]"]').first();
        } else {
            $field = $('#pys_' + slug + '_woo_content_id');
        }
        if (!$field.length) { return $(); }
        return $field.closest('.card.card-style6');
    }

    function openSettings(slug) {
        var $sub = settingsCardFor(slug);
        if (!$sub.length) { return; }
        expandCard($sub.closest('.woo-id-settings'));
        expandCard($sub);
        setTimeout(function () { scrollTo($sub); flash($sub); }, 320);
    }

    /* Find a <select> option by value without building a selector from the value. */
    function optionFor($select, value) {
        return $select.find('option').filter(function () { return this.value === String(value); });
    }

    function fieldFor(slug, key) {
        return $('#pys_' + slug + '_' + key);
    }

    function setField(slug, key, value, state) {
        var $f = fieldFor(slug, key);
        if (!$f.length) { return null; }
        var prev;
        if ($f.is(':checkbox')) {
            prev = $f.prop('checked');
            $f.prop('checked', !!value).trigger('change');
        } else if ($f.is('select')) {
            prev = $f.val();
            if (!optionFor($f, value).length) {
                $f.append($('<option>', { value: value, text: value }));
                if (state) { state.added[key] = value; }
            }
            $f.val(value).trigger('change');
        } else {
            prev = $f.val();
            $f.val(value).trigger('input').trigger('change');
        }
        flash($f.closest('.select-standard-wrap, .secondary-switch, .primary-switch').length ? $f.closest('.select-standard-wrap, .secondary-switch, .primary-switch') : $f);
        return prev;
    }

    function restoreField(slug, key, prev, added) {
        var $f = fieldFor(slug, key);
        if (!$f.length) { return; }
        if ($f.is(':checkbox')) {
            $f.prop('checked', !!prev).trigger('change');
        } else {
            $f.val(prev).trigger('change');
            if (added !== undefined) { optionFor($f, added).remove(); }
        }
    }

    function updateUnsavedHint() {
        var any = Object.keys(applied).length > 0;
        var $bar = $('.save-settings');
        var $btn = $('#pys-save-settings');
        var $hint = $bar.find('.pfc-unsaved');
        if (any) {
            if (!$hint.length) {
                $hint = $('<span class="pfc-unsaved"></span>').text(msgs.unsaved || 'Applied, not saved yet →');
                $hint.insertBefore($btn);
            }
            $btn.addClass('pfc-highlight');
        } else {
            $hint.remove();
            $btn.removeClass('pfc-highlight');
        }
    }

    function applySuggestion($box) {
        var slug = $box.data('pfc-module');
        var boxId = String($box.data('pfc-box') || slug);
        var values = $box.data('pfc-values');
        if (typeof values === 'string') {
            try { values = JSON.parse(values); } catch (e) { values = null; }
        }
        if (!slug || !values) { return; }

        // One applied suggestion per module at a time: revert a previous one for the same module first.
        Object.keys(applied).forEach(function (id) { if (applied[id].slug === slug) { revertSuggestion(id); } });

        var state = { slug: slug, prev: {}, added: {}, html: $box.prop('outerHTML') };
        Object.keys(values).forEach(function (key) {
            var p = setField(slug, key, values[key], state);
            if (p !== null) { state.prev[key] = p; }
        });
        applied[boxId] = state;

        var $strip = $('<div class="alert alert-success pfc-applied-box"><span class="pfc-alert-text"></span></div>').attr('data-pfc-box', boxId);
        $strip.find('.pfc-alert-text')
            .text((msgs.applied || 'Applied to the fields below. Click Save Changes (bottom right) to keep them, or') + ' ')
            .append($('<button type="button" class="pfc-undo pfc-revert"></button>').attr('data-pfc-box', boxId).text(msgs.revert || 'revert'))
            .append('.');
        $box.replaceWith($strip);
        updateUnsavedHint();
    }

    function revertSuggestion(boxId) {
        var state = applied[boxId];
        if (!state) { return; }
        Object.keys(state.prev).forEach(function (key) { restoreField(state.slug, key, state.prev[key], state.added[key]); });
        var $strip = $('.pfc-applied-box[data-pfc-box="' + boxId + '"]').first();
        if ($strip.length) { $strip.replaceWith(state.html); }
        delete applied[boxId];
        updateUnsavedHint();
    }

    $(function () {
        $(document).on('click keydown', '.pfc-row-header', function (e) {
            if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') { return; }
            if ($(e.target).closest('a, button').length) { return; }
            e.preventDefault();
            toggleRow($(this).closest('.pfc-row'));
        });

        $(document).on('click', '.pfc-open-settings', function (e) {
            e.preventDefault();
            openSettings($(this).data('pfc-module'));
        });

        $(document).on('click', '.pfc-apply', function (e) {
            e.preventDefault();
            applySuggestion($(this).closest('.pfc-suggest'));
        });

        $(document).on('click', '.pfc-revert', function (e) {
            e.preventDefault();
            revertSuggestion($(this).attr('data-pfc-box'));
        });

        openFromHash();
        $(window).on('hashchange', openFromHash);
    });
})(jQuery);
