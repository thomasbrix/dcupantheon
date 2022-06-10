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