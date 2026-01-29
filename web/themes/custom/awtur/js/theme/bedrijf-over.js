function changeReadMore() {
  var bodyTag = document.getElementById("bodyReadMoreTag");
  var bodyPartial = document.getElementById("bedrijf-body-text-partial");
  var bodyFull = document.getElementById("bedrijf-body-text-full");
  
  bodyTag.classList.toggle("read-more-tag");
  
  if (bodyTag.innerHTML === 'meer weten') {
    bodyTag.innerHTML = 'minder';
    bodyPartial.style.display = 'none';
    bodyFull.style.display = 'block';
  } else {
    bodyTag.innerHTML = 'meer weten';
    bodyPartial.style.display = 'block';
    bodyFull.style.display = 'none';
  }
}