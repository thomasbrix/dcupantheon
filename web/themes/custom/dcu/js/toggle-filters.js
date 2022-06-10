//---- Opens and closes the filter options overlay

function toggleFilters() {
    var filterButton = document.getElementById('filter-options-button');
    var filterElementClose = document.getElementById('filter-options-close');
    var filterElement = document.getElementById('filter-options');
    if (filterButton === null || filterElement === null || filterElementClose === null) {
        return;
    }
    filterButton.addEventListener('click', function() {        
        filterElement.classList.toggle('--active');
    })
    filterElementClose.addEventListener('click', function() {
        filterElement.classList.remove('--active');
    })
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
    toggleFilters()
});