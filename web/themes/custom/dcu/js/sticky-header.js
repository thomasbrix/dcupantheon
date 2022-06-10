/**
 * Sets fixed state for header when offset is reached
 */
function setHeaderStateOnScroll() {
	let verticalOffset = window.pageYOffset ||document.documentElement.scrollTop || document.body.scrollTop || 0;
	let width = Math.max(
		document.body.scrollWidth,
		document.documentElement.scrollWidth,
		document.body.offsetWidth,
		document.documentElement.offsetWidth,
		document.documentElement.clientWidth
	)
	let offset 			= 0;
	let bannerHeight 	= 180;
	let spacing 		= 22;

	// Change offset for desktop
	if(width > 1200) {
		offset = bannerHeight + spacing * 2
	}

	if(document.querySelector('.js-header')) {
		if(verticalOffset > offset) {
			document.querySelector('.js-header').classList.add('is-fixed');
		}
		else {
			document.querySelector('.js-header').classList.remove('is-fixed');
		}
	}
}



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
	window.onscroll = function() {
		setHeaderStateOnScroll()
	}
});