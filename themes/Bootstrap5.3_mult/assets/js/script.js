
$( "div.gpMenu ul.dropdown-menu li a").addClass("dropdown-item");

$( "div.gpMenu ul.navbar-nav ul.dropdown-menu ul.dropdown-menu" ).addClass("submenu");
$( "div.gpMenu ul.navbar-nav ul.dropdown-menu ul.dropdown-menu ul.dropdown-menu").addClass("submenu");

$( "div.gpMenu ul.navbar-nav li.nav-item a" ).addClass("nav-link px-3 px-lg-2");
$( "div.gpMenu ul:not(.dropdown-menu) li.nav-item.dropdown a:not(.dropdown-item)" ).attr("id","dropdown").attr("data-bs-toggle","dropdown");

$( "div.gpMenu ul.dropdown-menu").attr("aria-labelledby", "navbarDropdown");
$( "div.gpMenu ul.dropdown-menu li").removeClass("nav-item");
$( "div.gpMenu ul.dropdown-menu li a").removeClass("nav-link");


/*
$(document).ready(function() {
  if ($(window).width() >= 992) {
    $('div.offcanvas-body.sidebar ul:last').addClass('dropdown-menu-right').attr('data-bs-popper' , 'none');
  }
});
*/



/* --from -- https://github.com/engrasel/bs5-offcanvas-menu/  MIT License---  */

  document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll('.dropdown-menu').forEach(function(element) {
                element.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            })
        });
		
		
/*  --- Copyright (c) 2023 Anil Kumar (https://codepen.io/anil-vinnakoti5/pen/rNZomxX) MIT License --- */

        window.addEventListener("resize", function() {
            "use strict";
            window.location.reload();
        });
        document.addEventListener("DOMContentLoaded", function() {
            // make it as accordion for smaller screens
            if (window.innerWidth < 992) {
                // close all inner dropdowns when parent is closed
                document.querySelectorAll('.navbar .dropdown').forEach(function(everydropdown) {
                    everydropdown.addEventListener('hidden.bs.dropdown', function() {
                        // after dropdown is hidden, then find all submenus
                        this.querySelectorAll('.submenu').forEach(function(everysubmenu) {
                            // hide every submenu as well
                            everysubmenu.style.display = 'none';
                        });
                    })
                });
                document.querySelectorAll('.dropdown-menu a').forEach(function(element) {
                    element.addEventListener('click', function(e) {
                        let nextEl = this.nextElementSibling;
                        if (nextEl && nextEl.classList.contains('submenu')) {
                            // prevent opening link if link needs to open dropdown
                            e.preventDefault();
                            if (nextEl.style.display == 'block') {
                                nextEl.style.display = 'none';
                            } else {
                                nextEl.style.display = 'block';
                            }
                        }
                    });
                })
            }
            // end if innerWidth
        });
        // DOMContentLoaded  end		
		
		
		

		