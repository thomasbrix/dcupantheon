function toggleActive(elementClass, toggleClass) {
    var buttons = document.getElementsByClassName(elementClass);
    for (i = 0; i < buttons.length; i++) {
        buttons[i].addEventListener('click', function(e) {
            e.stopPropagation();
            this.classList.toggle(toggleClass);
        })
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

ready(function() {
    toggleActive('js-filter', '--active');
});