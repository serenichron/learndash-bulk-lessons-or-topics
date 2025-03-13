// File: js/admin.js
jQuery(document).ready(function($) {
    $('#csv_file').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.file-name').remove();
        $(this).after('<span class="file-name">' + fileName + '</span>');
    });

    $('#select-all').on('change', function() {
        $('input[name="confirm[]"]').prop('checked', $(this).prop('checked'));
    });
});