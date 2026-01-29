function openComfortContent(evt, comfortBedrijf) {
  var i, tabcomfort, comfortlinks;
  tabcomfort = document.querySelectorAll(".tabcomfort");
  for (i = 0; i < tabcomfort.length; i++) {
    tabcomfort[i].style.display = "none";
  }
  comfortlinks = document.querySelectorAll(".comfortlinks");
  for (i = 0; i < comfortlinks.length; i++) {
  comfortlinks[i].className = comfortlinks[i].className.replace(" active", "");
  comfortlinks[i].style.borderBottom = "none";
  comfortlinks[i].style.filter = "opacity(0.3)";
 }
  document.getElementById(comfortBedrijf).style.display = "block";
  evt.currentTarget.className += " active";
  evt.currentTarget.style.borderBottom = "2px solid #FF5F5F"; 
  evt.currentTarget.style.color = "#3b3b3b";
  evt.currentTarget.style.filter = "opacity(1)";
}
document.querySelector("#defaultFaciliteiten").click();