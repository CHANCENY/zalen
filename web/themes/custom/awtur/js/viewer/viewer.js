(function ($, Drupal) {
  Drupal.behaviors.viewerJSBehavior = {
    attach: function (context, settings) {

      if ($('.room-photos', context).length > 0) {
        $('.room-photos', context).each(function () {
          new Viewer(this, {
            toolbar: true,
            navbar: false,
            title: false,
          });
        });
      }
    }
  };
})(jQuery, Drupal);
