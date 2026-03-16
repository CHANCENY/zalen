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

      console.log(ui);
      // Populate hidden fields
      $('.google-place-id').val(ui.item.place_id);
      $('.google-place-address').val(ui.item.address);
      $('.google-place-map-url').val(ui.item.map_url);

      // Also update preview
      var iframe_src = '/google-place/map-preview/' + encodeURIComponent(ui.item.place_id);

      var html = '<div class="google-place-preview-wrapper">' +
        '<div>' + ui.item.label + '</div>' +
        '<iframe src="' + iframe_src + '" width="100%" height="300" style="border:0;" allowfullscreen="" loading="lazy"></iframe>' +
        '</div>';

      $('.google-place-preview').html(html);

    });
  });
});

