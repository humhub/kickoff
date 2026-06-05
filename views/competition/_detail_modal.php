<?php

/**
 * Generic detail modal + JS handler for any `[data-kickoff-modal]` trigger.
 * Triggers carry `data-modal-url` (required) and optionally `data-modal-title`.
 * Reused for user-history, match-tips and their pagination links.
 */

$modalJs = <<<JS_WRAP
(function (\$) {
    \$(function () {
        var modalEl = document.getElementById('kickoff-detail-modal');
        if (!modalEl) return;
        var \$body = \$(modalEl).find('.modal-body');
        var loadingHtml = '<p class="text-muted text-center">…</p>';

        // Rebind on every script run (HumHub Pjax can re-execute registered JS).
        // The namespaced .off() is the cheap guard against accumulating handlers.
        \$(document).off('click.kickoffDetailModal');
        \$(document).on('click.kickoffDetailModal', '[data-kickoff-modal]', function (e) {
            e.preventDefault();
            var url = \$(this).data('modal-url');
            var titleAttr = \$(this).attr('data-modal-title');
            if (!url) return;
            \$body.html(loadingHtml);
            if (titleAttr) {
                \$(modalEl).find('.modal-title').text(titleAttr);
            }
            var isShown = modalEl.classList.contains('show');
            \$.get(url).done(function (html) {
                \$body.html(html);
            }).fail(function () {
                \$body.html('<p class="text-danger">Could not load.</p>');
            });
            if (isShown) {
                // Modal already open (e.g. user clicked a pagination link inside it) —
                // just refresh body. Calling .show() again would stack a backdrop.
                return;
            }
            var modal = (window.bootstrap && bootstrap.Modal)
                ? bootstrap.Modal.getOrCreateInstance(modalEl)
                : null;
            if (modal) modal.show();
            else \$(modalEl).addClass('show').css('display', 'block');
        });
    });
})(jQuery);
JS_WRAP;
// Unique key so this <script> block is emitted at most once even if the partial
// gets rendered twice in a single page (e.g. nested layouts).
$this->registerJs($modalJs, \yii\web\View::POS_END, 'kickoff-detail-modal');

?>
<div class="modal fade" id="kickoff-detail-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">&nbsp;</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted text-center"><?= Yii::t('KickoffModule.base', 'Loading…') ?></p>
            </div>
        </div>
    </div>
</div>
