
function externalLinks() {   for(var c = document.getElementsByTagName("a"), a = 0;a < c.length;a++) {     var b = c[a];     
b.getAttribute("href") && b.hostname !== location.hostname && (b.rel = "nofollow noopener")   } } ; externalLinks();


// menu -classes
//$( "div.gpMenu ul.dropdown-menu li a").addClass("dropdown-item"); 
//$( "div.gpMenu ul.navbar-nav li.nav-item a").addClass("nav-link"); 


$(document).ready(function() {
  $("ul.navbar-nav > li > ul.dropdown-menu > li > ul.dropdown-menu").addClass("submenu");
});


document.addEventListener("DOMContentLoaded", function() {
  var dropdownMenus = document.querySelectorAll("ul.navbar-nav > li > ul.dropdown-menu > li > ul.dropdown-menu");
  for (var i = 0; i < dropdownMenus.length; i++) {
    dropdownMenus[i].classList.add("submenu");
  }
});


// Replace div.gpMenu ul.dropdown-menu li a with dropdown-item class
var dropdownItems = document.querySelectorAll("div.gpMenu ul.dropdown-menu li a");
for (var i = 0; i < dropdownItems.length; i++) {
  dropdownItems[i].classList.add("dropdown-item");
}

// Replace div.gpMenu ul.navbar-nav li.nav-item a with nav-link class
var navLinks = document.querySelectorAll("div.gpMenu ul.navbar-nav li.nav-item a");
for (var i = 0; i < navLinks.length; i++) {
  navLinks[i].classList.add("nav-link");
}


 // JavaScript/jQuery code to handle dropdowns
  document.addEventListener("DOMContentLoaded", function() {
    var dropdownToggle = document.querySelector(".navbar .dropdown-toggle");
    if (dropdownToggle) {
      dropdownToggle.addEventListener("click", function(e) {
        e.preventDefault();
        var dropdownMenu = dropdownToggle.nextElementSibling;
        if (dropdownMenu.style.display === "block") {
          dropdownMenu.style.display = "none";
        } else {
          dropdownMenu.style.display = "block";
        }
      });
    }
  });
  

// (c) 2020-2022 Written by Simon Köhler in Panama
// github.com/koehlersimon
// simon-koehler.com
document.addEventListener('click',function(e){
  // Hamburger menu
  if(e.target.classList.contains('hamburger-toggle')){
    e.target.children[0].classList.toggle('active');
  }
})

