;(function () {

  function addPopup (els) {
    var temp;
    for(var i in els) {
      if (!els.hasOwnProperty(i)) continue;
      temp = els[i].getElementsByTagName("a")[0];
      temp.classList.add("use-ajax");
      temp.setAttribute("data-dialog-type", "modal");
      temp.setAttribute("data-dialog-options", '{"width":"300","height":"auto","dialogClass":"tat-popup"}');
    };
  };

  function addPopupCanvas (els) {
    var temp;
    for(var i in els) {
      if (!els.hasOwnProperty(i)) continue;
      temp = els[i].getElementsByTagName("a")[0];
      temp.classList.add("use-ajax");
      temp.setAttribute("data-dialog-type", "dialog");
      temp.setAttribute("data-dialog-renderer", "off_canvas");
      temp.setAttribute("data-dialog-options", '{"width":"180","height":"auto","dialogClass":"tat-popup","title":"Bod per voertuig"}');
    };
  };

  function addPopupSearch (els, link) {
    var temp;
    for(var i in els) {
      if (!els.hasOwnProperty(i)) continue;
      if (els[i].href.indexOf(link) + 1) {
        console.log('href is', els[i].href);
        temp = els[i];
        temp.classList.add("use-ajax");
        temp.setAttribute("data-dialog-type", "modal");
        temp.setAttribute("data-dialog-options", '{"width":"300","height":"auto","dialogClass":"tat-popup"}');
      }
    };
  };

  if (document.getElementById("block-awtur-account-menu")) {
    addPopupSearch(document.getElementById("block-awtur-account-menu").getElementsByTagName("a"), "user/login")
    
  };

})();
