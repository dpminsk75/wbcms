function updateDebugInfo() {
    var count = $('#wb-hidden-inputs input').length;
    $('#debug-count').text(count);
    $('#wb-drop-placeholder').toggle(count === 0);
}

window.allowDrop = function (ev) {
    ev.preventDefault();
};

window.drag = function (ev) {
    var tr = ev.target.closest('tr');
    var data = {
        nmid: tr.dataset.nmid,
        vendorCode: tr.dataset.vendorcode,
        title: tr.dataset.title
    };
    ev.dataTransfer.setData('text', JSON.stringify(data));
};

window.drop = function (ev) {
    ev.preventDefault();
    $('#wb-drop-zone').removeClass('wb-drop-zone--over');
    try {
        var data = JSON.parse(ev.dataTransfer.getData('text'));
        addCard(data.nmid, data.vendorCode, data.title);
    } catch (e) {}
};

function addCard(nmId, vendorCode, title) {
    if ($('#wb-selected-list li[data-nmid="' + nmId + '"]').length > 0) return;

    $('#wb-selected-list').append(
        '<li class="wb-selected-item" data-nmid="' + nmId + '">' +
            '<div class="wb-selected-info">' +
                '<span class="wb-selected-nmid">' + nmId + '</span>' +
                '<span class="wb-selected-vendor">' + vendorCode + '</span>' +
                '<div class="wb-selected-title">' + title + '</div>' +
            '</div>' +
            '<button type="button" class="wb-remove-btn wb-remove-card" title="Удалить">&times;</button>' +
        '</li>'
    );
    $('#wb-hidden-inputs').append('<input type="hidden" name="Tag[wbCardIds][]" value="' + nmId + '" data-nmid="' + nmId + '">');
    updateDebugInfo();
}

$(document).on('click', '.wb-remove-card', function () {
    var nmId = $(this).closest('li').data('nmid');
    $('input[data-nmid="' + nmId + '"]').remove();
    $(this).closest('li').remove();
    updateDebugInfo();
});

$('#wb-search-btn').on('click', function () {
    $.pjax.reload({
        container: '#wb-grid-pjax',
        data: {
            'WbCardSearch[nmID]': $('#wbsearch-nmid').val(),
            'WbCardSearch[vendorCode]': $('#wbsearch-vendorcode').val(),
            'WbCardSearch[title]': $('#wbsearch-title').val()
        }
    });
});

$(document).on('click', '#add-all-visible', function () {
    $('#wb-grid-pjax table tbody tr[data-nmid]').each(function () {
        var row = $(this);
        addCard(row.data('nmid'), row.data('vendorcode'), row.data('title'));
    });
});

$('#wb-drop-zone').on('dragenter', function () {
    $(this).addClass('wb-drop-zone--over');
});

$('#wb-drop-zone').on('dragleave', function (ev) {
    if (ev.target === this) {
        $(this).removeClass('wb-drop-zone--over');
    }
});

updateDebugInfo();