
// ---- Polyfills

/**
 * Element.closest() polyfill
 */
if (!Element.prototype.matches) {
	Element.prototype.matches =
		Element.prototype.msMatchesSelector ||
		Element.prototype.webkitMatchesSelector;
}

if (!Element.prototype.closest) {
	Element.prototype.closest = function(s) {
		var el = this;

		do {
		if (Element.prototype.matches.call(el, s)) return el;
		el = el.parentElement || el.parentNode;
		} while (el !== null && el.nodeType === 1);
		return null;
	};
}





// ---- Functions ---- \\

/**
 * Toggles the targeted submenu and closes all others
 * Used on full width megamenus
 *
 * @param {{htmlElement}}
 */
function toggleSubmenu(el) {
	let submenu = el.closest('.js-submenu');

	if(submenu.classList.contains('is-active-menu')) {
		// Add active class
		submenu.classList.remove('is-active-menu');
	}
	else {
		// Remove active class from other submenus
		document.querySelectorAll('.js-submenu').forEach(menuItem => {
			menuItem.classList.remove('is-active-menu');
		});

		// Add active class
		submenu.classList.add('is-active-menu');
	}
}


/**
 * Toggles the targeted submenu and closes all others
 * Used on dropdown menus that need offsets
 *
 * @param {{htmlElement}}
 */
function toggleDropdownSubmenu(el) {
	let leftOffset = el.offsetLeft;
	let submenuOffset = (leftOffset + 130) + "px";
	let submenu = el.closest('.js-submenu');
	let submenuNavigation = submenu.querySelector('.navigation__submenu');
	if (submenuNavigation === null) {
		submenuNavigation = submenu.querySelector('.navigation__main');
	}
  // Position the submenu below the menu item
	submenuNavigation.style.left = submenuOffset;
	if(submenu.classList.contains('is-active-menu')) {
		// Add active class
		submenu.classList.remove('is-active-menu');
	}
	else {
		// Remove active class from other submenus
		document.querySelectorAll('.js-submenu').forEach(menuItem => {
			menuItem.classList.remove('is-active-menu');
		});
		// Add active class
		submenu.classList.add('is-active-menu');
	}
}


/**
 * Toggles nav groups by adding or removing state class
 *
 * @param {*} navGroupLabel - nav-group label element that was clicked
 */
function toggleNavGroup(navGroupLabel) {
	let group = navGroupLabel.closest('.js-nav-group')

	if(group.classList.contains('is-active-nav-group')) {
		// Add active class
		group.classList.remove('is-active-nav-group');
	}
	else {
		// Add active class
		group.classList.add('is-active-nav-group');
	}
}


/**
 * Toggles navigation on small devices and toggles active state on toggle button.
 */
function toggleNav() {
	let nav = document.querySelector('.js-navigation');
	let toggle = document.querySelector('.js-nav-toggle');

	if(nav.classList.contains('is-nav-open')) {
		nav.classList.remove('is-nav-open');
		toggle.classList.remove('is-toggled');
	}
	else {
		nav.classList.add('is-nav-open');
		toggle.classList.add('is-toggled');
	}
}

/**
* Toggles user navigation
*/
function toggleUserNav(item) {
	if (item.classList.contains('is-nav-open')) {
		item.classList.remove('is-nav-open');
		item.classList.remove('is-toggled');
	} else {
		item.classList.add('is-nav-open');
		item.classList.add('is-toggled');
	}
}



// ---- Life cycle ----\\

// TODO remove ready once it is possible to define global functions
/**
 * Checks for ready state and calls callback
 *
 * @param {function} fn - Callback function
 */
function ready(fn) {
	if (document.readyState != 'loading'){
		fn();
	} else {
		document.addEventListener('DOMContentLoaded', fn);
	}
}

/**
 * Fire function on scroll
 */
ready(function() {

	// Submenu event listener
	document.querySelectorAll('.js-toggle-submenu').forEach(menuItem => {
		menuItem.addEventListener('click', () => {
			toggleSubmenu(menuItem)
		});
	});

	// Camping submenu event listener
	document.querySelectorAll('.js-toggle-submenu-camping').forEach(menuItem => {
		menuItem.addEventListener('click', () => {
			toggleDropdownSubmenu(menuItem)
		});
	});

	// Nav group event listener
	document.querySelectorAll('.js-toggle-nav-group').forEach(menuItem => {
		menuItem.addEventListener('click', () => {
			toggleNavGroup(menuItem)
		});
	});


	// Nav toggle event listener
	document.querySelector('.js-nav-toggle').addEventListener('click', (toggle) => {
		toggleNav()
	})

	// User navigation toggle event listener
	document.querySelectorAll('.js-user-toggle').forEach(item => {
	  item.addEventListener('click', (toggle) => {
			toggleUserNav(item);
    });
	});
});
