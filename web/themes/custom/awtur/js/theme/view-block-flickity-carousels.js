;console.log("Hi view-block flickity-carousels");

//;(function () {
//
//  //if (document.getElementById("block-awtur-account-menu")) {
//  //  //addPopupSearch(document.getElementById("block-awtur-account-menu").getElementsByTagName("a"), "user/login")
//  //};
//
//})();

//(function ($, Drupal, drupalSettings) {
//  'use strict';
//
//  Drupal.behaviors.mybehavior = {
//    attach: function (context, settings) {
//      console.log(settings.fluffiness.cuddlySlider.foo);
//    }
//  };
//
//})(jQuery, Drupal, drupalSettings);

(function (Drupal, drupalSettings, once) {

  //'use strict';

  Drupal.behaviors.viewBlockFlickity = {

    attach: function (context, settings) {

      //console.log(settings);
      once('viewBlockFlickity', '.node__content.zaal-beknopt .field--type-image, ', context).forEach(function (element) {

        //var elem = document.querySelector('.main-carousel');
        //var flkty = new Flickity( elem, {
        //  // options
        //  cellAlign: 'left',
        //  contain: true
        //});
        // element argument can be a selector string //for an individual element
        //var flkty = new Flickity( '.main-carousel', {
        //  // options
        //});

        //console.log(element);
        //let width = element.offsetWidth;
        //let height = element.offsetHeight;
        element.style.display = 'block';
        element.style.width = 100 + '%';
        element.style.height = 160 + 'px';
        let child = element.children;
        for (let item in child) {
          if( child.hasOwnProperty(item) ) {
            child[item].style.padding = '2px';
            child[item].style.height = '160px';
          };
        };
        child = element.getElementsByTagName('img');
        for (let item in child) {
          if( child.hasOwnProperty(item) ) {
            //console.log(item);
            //child[item].style.display = 'inline-block';
            child[item].style.height = '100%';
            child[item].style.objectFit = 'contain';
          };
        };
        //console.log(element.children, child);
        let flkty = new Flickity(element, {
          // options, defaults listed
          accessibility: true,// enable keyboard navigation, pressing left & right keys
          adaptiveHeight: false,// set carousel height to the selected slide
          autoPlay: true,//false,// advances to the next cell
          // if true, default is 3 seconds
          // or set time between advances in milliseconds i.e. `autoPlay: 1000` will advance every 1 second
          cellAlign: 'center',// alignment of cells, 'center', 'left', or 'right'
          // or a decimal 0-1, 0 is beginning (left) of container, 1 is end (right)
          cellSelector: undefined,// specify selector for cell elements
          contain: false,// will contain cells to container
          // so no excess scroll at beginning or end
          // has no effect if wrapAround is enabled
          draggable: '>1',// enables dragging & flicking
          // if at least 2 cells
          dragThreshold: 3,// number of pixels a user must scroll horizontally to start dragging
          // increase to allow more room for vertical scroll for touch devices
          freeScroll: false,// enables content to be freely scrolled and flicked
          // without aligning cells
          friction: 0.2,// smaller number = easier to flick farther
          groupCells: false,// group cells together in slides
          initialIndex: 0,// zero-based index of the initial selected cell
          lazyLoad: false,//true,// enable lazy-loading images
          // set img data-flickity-lazyload="src.jpg" // set to number to load images adjacent cells
          percentPosition: true,// sets positioning in percent values, rather than pixels
          // Enable if items have percent widths // Disable if items have pixel widths, like images
          prevNextButtons: true,// creates and enables buttons to click to previous & next cells
          pageDots: true,// create and enable page dots
          resize: true,// listens to window resize events to adjust size & positions
          rightToLeft: false,// enables right-to-left layout
          setGallerySize: true,// sets the height of gallery
          // disable if gallery already has height set with CSS
          watchCSS: false,// watches the content of :after of the element
          // activates if #element:after { content: 'flickity' }
          wrapAround: true,//false,// at end of cells, wraps-around to first for infinite scrolling
        });

      })

    }

  };

})(Drupal, drupalSettings, once);
