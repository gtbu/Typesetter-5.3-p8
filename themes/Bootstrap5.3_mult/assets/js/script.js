document.addEventListener("DOMContentLoaded", function() {
     
    // Add 'dropdown-item' class
    document.querySelectorAll('div.gpMenu ul.dropdown-menu li a').forEach(el => {
        el.classList.add('dropdown-item');
    });

    // Add 'submenu' class to nested dropdowns
    document.querySelectorAll('div.gpMenu ul.navbar-nav ul.dropdown-menu ul.dropdown-menu').forEach(el => {
        el.classList.add('submenu');
    });

    document.querySelectorAll('div.gpMenu ul.navbar-nav ul.dropdown-menu ul.dropdown-menu ul.dropdown-menu').forEach(el => {
        el.classList.add('submenu');
    });

    // Add nav-link classes
    document.querySelectorAll('div.gpMenu ul.navbar-nav li.nav-item a').forEach(el => {
        el.classList.add('nav-link', 'px-3', 'px-lg-2');
    });

    // Set attributes for dropdown toggle
    document.querySelectorAll('div.gpMenu ul:not(.dropdown-menu) li.nav-item.dropdown a:not(.dropdown-item)').forEach(el => {
        el.id = 'dropdown';
        el.setAttribute('data-bs-toggle', 'dropdown');
    });

    // Set aria-labelledby
    document.querySelectorAll('div.gpMenu ul.dropdown-menu').forEach(el => {
        el.setAttribute('aria-labelledby', 'navbarDropdown');
    });

    // Remove classes from dropdown menu items
    document.querySelectorAll('div.gpMenu ul.dropdown-menu li').forEach(el => {
        el.classList.remove('nav-item');
    });

    document.querySelectorAll('div.gpMenu ul.dropdown-menu li a').forEach(el => {
        el.classList.remove('nav-link');
    });
});


/*
$(document).ready(function() {
  if ($(window).width() >= 992) {
    $('div.offcanvas-body.sidebar ul:last').addClass('dropdown-menu-right').attr('data-bs-popper' , 'none');
  }
});

document.addEventListener("DOMContentLoaded", function() {
    if (window.innerWidth >= 992) {
        const menu = document.querySelector('div.offcanvas-body.sidebar ul:last-of-type');
        if (menu) {
            menu.classList.add('dropdown-menu-right');
            menu.setAttribute('data-bs-popper', 'none');
        }
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
		
		
		

		