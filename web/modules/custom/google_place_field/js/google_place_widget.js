jQuery(document).ready(function ($) {
  $('.google-place-search').each(function () {
    var $input = $(this);
    var $wrapper = $input.closest('.js-form-item');

    $input.on('autocompleteopen', function () {
      // Nothing needed on open
    });

    // Override the select behavior
    $input.on('autocompleteselect', function (event, ui) {
      // ui.item contains the selected suggestion object

      if (!ui.item) return;

      // Populate hidden fields
      $('.google-place-id').val(ui.item.place_id);
      $('.google-place-address').val(ui.item.address);
      $('.google-place-map-url').val(ui.item.map_url);

      // Also update preview
      var html = '<div class="google-place-preview-wrapper">' +
        '<div>' + ui.item.label + '</div>' +
        '<a href="' + ui.item.map_url + '" target="_blank">View map</a>' +
        '</div>';

      $('.google-place-preview').html(html);

    });
  });
});

