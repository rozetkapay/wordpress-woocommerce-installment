jQuery(document).ready(function($) {
    'use strict';

    if (typeof rp_mobile_instruction === 'undefined' || !rp_mobile_instruction) {
        return;
    }

    var bankName = rp_mobile_instruction.bank_name || '';
    var modal = $(
        '<div class="rp-mobile-instruction-modal">' +
            '<div class="rp-modal-content">' +
                '<div class="rp-modal-icon">📱</div>' +
                '<h3 class="rp-modal-title">Підтвердіть оплату частинами</h3>' +
                '<p class="rp-modal-text">' +
                    'Перевірте свій смартфон та підтвердіть розстрочку ' +
                    'в мобільному додатку <strong>' + bankName + '</strong>' +
                '</p>' +
                '<div class="rp-instruction-steps">' +
                    '<div class="rp-step">' +
                        '<span class="rp-step-number">1</span>' +
                        '<span>Відкрийте додаток ' + bankName + '</span>' +
                    '</div>' +
                    '<div class="rp-step">' +
                        '<span class="rp-step-number">2</span>' +
                        '<span>Знайдіть push-повідомлення про розстрочку</span>' +
                    '</div>' +
                    '<div class="rp-step">' +
                        '<span class="rp-step-number">3</span>' +
                        '<span>Підтвердіть операцію</span>' +
                    '</div>' +
                '</div>' +
                '<div class="rp-modal-buttons">' +
                    '<button type="button" class="rp-modal-close">Зрозуміло</button>' +
                    '<button type="button" class="rp-modal-later">Закрити</button>' +
                '</div>' +
                '<div class="rp-auto-close-timer">' +
                    'Автоматично закриється через <span class="rp-timer">15</span> сек.' +
                '</div>' +
            '</div>' +
        '</div>'
    );

    $('body').append(modal);

    function closeModal() {
        modal.fadeOut(300, function() {
            $(this).remove();
        });
    }

    modal.on('click', '.rp-modal-close, .rp-modal-later', function() {
        clearInterval(timer);
        closeModal();
    });

    modal.on('click', function(e) {
        if (e.target === this) {
            clearInterval(timer);
            closeModal();
        }
    });

    var timeLeft = 15;
    var timer = setInterval(function() {
        timeLeft--;
        modal.find('.rp-timer').text(timeLeft);

        if (timeLeft <= 0) {
            clearInterval(timer);
            closeModal();
        }
    }, 1000);
});
