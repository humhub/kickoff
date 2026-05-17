<?php

/**
 * Generic detail modal + JS handler for any `[data-kickoff-modal]` trigger.
 * Triggers carry `data-modal-url` (required) and optionally `data-modal-title`.
 * Reused for user-history, match-tips and their pagination links.
 */

$modalJs = <<<JS
(function (\$) {
    \$(function () {
        var modalEl = document.getElementById('kickoff-detail-modal');
        if (!modalEl) return;
        var \$body = \$(modalEl).find('.modal-body');
        var loadingHtml = '<p class="text-muted text-center">…</p>';

        \$(document).on('click', '[data-kickoff-modal]', function (e) {
            e.preventDefault();
            var url = \$(this).data('modal-url');
            var titleAttr = \$(this).attr('data-modal-title');
            if (!url) return;
            \$body.html(loadingHtml);
            if (titleAttr) {
                \$(modalEl).find('.modal-title').text(titleAttr);
            }
            var modal = (window.bootstrap && bootstrap.Modal)
                ? bootstrap.Modal.getOrCreateInstance(modalEl)
                : null;
            if (modal) modal.show();
            else \$(modalEl).addClass('show').css('display', 'block');
            \$.get(url).done(function (html) {
                \$body.html(html);
            }).fail(function () {
                \$body.html('<p class="text-danger">Could not load.</p>');
            });
        });
    });
})(jQuery);
JS;
$this->registerJs($modalJs);

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
