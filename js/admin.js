// File: js/admin.js
jQuery(document).ready(function ($) {
  $('#csv_file').on('change', function () {
    var fileName = $(this).val().split('\\').pop();
    $(this).next('.file-name').remove();
    $(this).after('<span class="file-name">' + fileName + '</span>');
  });

  $('#select-all').on('change', function () {
    $('input[name="confirm[]"]').prop('checked', $(this).prop('checked'));
  });

  const $form = $('.gen_template_form');

  const types = ['question', 'quiz', 'topic', 'lesson', 'course', 'group'];
  $form.find('#include').on('change', e => {
    const index = types.findIndex(type => type === e.target.value);
    types.slice(0, index + 1).forEach(type => $form.find(`.${type}-field`).show());
    types.slice(index + 1).forEach(type => $form.find(`.${type}-field`).hide());
  });

  $form.on('submit', async function (e) {
    e.preventDefault();

    const res = await fetch(this.dataset.url, {
      method: 'POST',
      body: new FormData(this),
    });
    const url = URL.createObjectURL(await res.blob());
    location.assign(url);
  });
});
