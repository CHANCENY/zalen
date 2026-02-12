//Tabs Media, Comfort and Thema 
 function zaalComfortContent(evt, comfortZaal) {
  var i, tabcomfort, comfortlinks;
  tabcomfort = document.querySelectorAll(".zaal-tabcomfort");
  for (i = 0; i < tabcomfort.length; i++) {
    tabcomfort[i].style.display = "none";
  }
  comfortlinks = document.querySelectorAll(".zaalcomfortlinks");
  for (i = 0; i < comfortlinks.length; i++) {
  comfortlinks[i].className = comfortlinks[i].className.replace(" active", "");
  comfortlinks[i].style.borderBottom = "none";
  comfortlinks[i].style.filter = "opacity(0.3)";
 }
  document.getElementById(comfortZaal).style.display = "block";
  evt.currentTarget.className += " active";
  evt.currentTarget.style.borderBottom = "3px solid var(--brand-color)"; 
  evt.currentTarget.style.color = "#3b3b3b";
  evt.currentTarget.style.filter = "opacity(1)";
}
document.querySelector("#defaultComfortLink").click();

//Body Read More link
function zaalReadMore() {
  var bodyTag = document.getElementById("zaalReadMoreTag");
  var bodyPartial = document.getElementById("zaal-body-text-partial");
  var bodyFull = document.getElementById("zaal-body-text-full");
  
  bodyTag.classList.toggle("zaal-read-more");
  
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
  