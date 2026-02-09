(function (Drupal, once) {
  Drupal.behaviors.alleZalenSlider = {
    attach(context) {

      /* ===============================
       * Swiper slider
       * =============================== */
      once('alleZalenSlider', '.alle-zalen-slider', context).forEach(function (slider) {
        new Swiper(slider, {
          loop: true,
          slidesPerView: 1,
          navigation: {
            nextEl: slider.querySelector('.swiper-button-next'),
            prevEl: slider.querySelector('.swiper-button-prev'),
          },
          pagination: {
            el: slider.querySelector('.swiper-pagination'),
            type: 'fraction',
          },
        });
      });

      /* ===============================
       * Tag toggle (+X / Show less)
       * =============================== */
      once('tagToggle', '.alle-zalen-gelegenheden-tags', context).forEach(function (wrapper) {
        const btn = wrapper.querySelector('.tag-toggle');
        if (!btn) return;

        btn.addEventListener('click', function () {
          const limit = parseInt(wrapper.dataset.limit, 10) || 5;
          const hiddenTags = wrapper.querySelectorAll('.tag.is-hidden');

          if (hiddenTags.length) {
            // SHOW MORE
            hiddenTags.forEach(tag => tag.classList.remove('is-hidden'));
            btn.textContent = btn.dataset.less;
            btn.classList.add('is-less');   // 🔴 make text red
          } else {
            // SHOW LESS
            wrapper
              .querySelectorAll('.tag:not(.tag-toggle)')
              .forEach((tag, index) => {
                if (index >= limit) {
                  tag.classList.add('is-hidden');
                }
              });

            btn.textContent = btn.dataset.more;
            btn.classList.remove('is-less'); // ⬅ back to normal
          }
        });
      });

    }
  };
})(Drupal, once);
