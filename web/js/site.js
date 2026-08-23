var Linechart;
let labelsVisible = true;

$('.btn-toggle-expand').on('click', function() {
    // Ищем контейнер, который идет ПЕРЕД родителем кнопки
    var btn = $(this);
    var container = btn.parent().prev('.expandable-container');
    
    container.toggleClass('is-expanded');
    
    if (container.hasClass('is-expanded')) {
        btn.text('Свернуть');
    } else {
        btn.text('Увидеть больше');
        // Скроллим к началу именно этого блока
        $('html, body').animate({
            scrollTop: container.offset().top - 50
        }, 300);
    }
});

function showCopyToast() {
    var toastEl = document.getElementById('copyToast');
    if (toastEl) {
        var toast = bootstrap.Toast.getOrCreateInstance(toastEl);
        toast.show();
    }
}
function copyToClipboard(elementId) {
    var text = document.getElementById(elementId).innerText;

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function() {
            showCopyToast(); // <--- Вставляем сюда
            console.log('Успешно скопировано через Clipboard API');
        }).catch(err => {
            console.error('Ошибка при копировании: ', err);
        });
    } else {
        var textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.left = "-9999px";
        textArea.style.top = "0";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            var successful = document.execCommand('copy');
            if (successful) {
                showCopyToast(); // <--- И сюда
                console.log('Успешно скопировано через fallback');
            }
        } catch (err) {
            console.error('Не удалось скопировать даже через fallback', err);
        }
        
        document.body.removeChild(textArea);
    }
}
/*
function setDateRange(period) {
    // Если нужно просто установить "Сегодня" в поле "До"
    if (period === 'today') {
        const today = new Date();
        const formattedToday = formatDate(today);
        
        $('#dpfilterform-date_to').val(formattedToday).trigger('change');
        if (typeof $('#date_to').kvDatepicker === 'function') {
            $('#dpfilterform-date_to').kvDatepicker('update', formattedToday);
        }
        return; // Выходим из функции, чтобы не менять поле "От"
    }

    // --- Дальше идет ваша стандартная логика для периодов ---
    
    let valTo = $('#dpfilterform-date_to').val();
    let baseDate = valTo ? new Date(valTo) : new Date();
    if (isNaN(baseDate.getTime())) baseDate = new Date();

    let dateFrom = new Date(baseDate.getTime());
    let newTo = null;

    // Вспомогательная функция форматирования
    function formatDate(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    if (period === 'year') {
        dateFrom.setFullYear(baseDate.getFullYear() - 1);
        dateFrom.setDate(dateFrom.getDate() + 1);
    } else if (period === 'quarter') {
        dateFrom.setMonth(baseDate.getMonth() - 3);
        dateFrom.setDate(dateFrom.getDate() + 1);
    } else if (period === 'last_year') {
        const lastYear = baseDate.getFullYear() - 1;
        dateFrom = new Date(lastYear, 0, 1);
        newTo = new Date(lastYear, 11, 31);
    }

    // Обновляем поле "От"
    $('#dpfilterform-date_from').val(formatDate(dateFrom)).trigger('change');
    if (typeof $('#dpfilterform-date_from').kvDatepicker === 'function') {
        $('#dpfilterform-date_from').kvDatepicker('update', formatDate(dateFrom));
    }

    // Обновляем поле "До" только если это "Прошлый год"
    if (newTo) {
        $('#dpfilterform-date_to').val(formatDate(newTo)).trigger('change');
        if (typeof $('#dpfilterform-date_to').kvDatepicker === 'function') {
            $('#dpfilterform-date_to').kvDatepicker('update', formatDate(newTo));
        }
    }
}
*/
/*
function setDateRange(period) {
    const $from = $('#dpfilterform-date_from');
    const $to = $('#dpfilterform-date_to');

    function formatDate(d) {
        return d.toISOString().split('T')[0]; // Быстрый способ получить YYYY-MM-DD
    }

    // Если период 'today', обновляем только поле "До"
    if (period === 'today') {
        const todayStr = formatDate(new Date());
        updateKvPicker($to, todayStr);
        return;
    }

    let baseDate = new Date($to.val() || new Date());
    if (isNaN(baseDate.getTime())) baseDate = new Date();

    let dateFrom = new Date(baseDate);
    let newTo = null;

    if (period === 'year') {
        dateFrom.setFullYear(baseDate.getFullYear() - 1);
        dateFrom.setDate(dateFrom.getDate() + 1);
    } else if (period === 'quarter') {
        dateFrom.setMonth(baseDate.getMonth() - 3);
        dateFrom.setDate(dateFrom.getDate() + 1);
    } else if (period === 'last_year') {
        const lastYear = baseDate.getFullYear() - 1;
        dateFrom = new Date(lastYear, 0, 1);
        newTo = new Date(lastYear, 11, 31);
    }

    // Обновляем "От"
    updateKvPicker($from, formatDate(dateFrom));

    // Обновляем "До", если нужно
    if (newTo) {
        updateKvPicker($to, formatDate(newTo));
    }
}
*/
function setDateRange(period) {
    const $from = $('input[name$="[date_from]"]');
    const $to = $('input[name$="[date_to]"]');

    // Функция форматирования через локальное время, а не UTC
    function formatDate(d) {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function updateKvPicker($el, val) {
        $el.val(val).trigger('change');
        if (typeof $el.kvDatepicker === 'function') {
            $el.kvDatepicker('update', val);
        }
    }

    if (period === 'today') {
        const todayStr = formatDate(new Date());
        updateKvPicker($to, todayStr);
//        updateKvPicker($from, todayStr); // Если нужно за сегодня, ставим оба поля
        return;
    }

    let baseDate = $to.val() ? new Date($to.val()) : new Date();
    if (isNaN(baseDate.getTime())) baseDate = new Date();

    let dateFrom = new Date(baseDate);
    let newTo = null;

    if (period === 'year') {
        dateFrom.setFullYear(baseDate.getFullYear() - 1);
        dateFrom.setDate(dateFrom.getDate() + 1);
    } else if (period === 'quarter') {
        dateFrom.setMonth(baseDate.getMonth() - 3);
        dateFrom.setDate(dateFrom.getDate() + 1);
    } else if (period === 'last_year') {
        const lastYear = baseDate.getFullYear() - 1;
        // Задаем даты жестко: 1 января и 31 декабря
        dateFrom = new Date(lastYear, 0, 1);
        newTo = new Date(lastYear, 11, 31);
    }

    updateKvPicker($from, formatDate(dateFrom));

    if (newTo) {
        updateKvPicker($to, formatDate(newTo));
    }
}

// Универсальный помощник для обновления виджета Kartik
function updateKvPicker($el, value) {
    $el.val(value).trigger('change');
    // Проверяем наличие плагина именно на этом элементе
    if ($el.data('kvDatepicker')) {
        $el.kvDatepicker('update', value);
    }
}


function toggleWidth(btn) {
    const element = btn.closest('[class*="col-md-"]');
    
    if (element) {
        element.classList.toggle('col-md-6');
        element.classList.toggle('col-md-12');
    }
}

window.toggleLabels = function(chart, btn) {
    if (!chart) {
        console.warn("Объект графика не передан в toggleLabels");
        return;
    }

    // Определяем текущее состояние (смотрим по первой серии)
    const firstSeries = chart.series.getIndex(0);
    if (!firstSeries) return;
    
    const isVisible = firstSeries.bulletsContainer.get("visible");

    // 1. Переключаем видимость во всех сериях
    chart.series.each(function(series) {
        const container = series.bulletsContainer;
        if (container) {
            isVisible ? container.hide() : container.show();
        }
    });

    // 2. Визуализация кнопки через Bootstrap 5
    if (btn) {
        // Если были видны (isVisible = true), значит сейчас скрываем -> закрашиваем кнопку
        btn.classList.toggle('btn-outline-secondary', isVisible);
        btn.classList.toggle('btn-secondary', !isVisible);
        btn.classList.toggle('active', !isVisible);
    }
};