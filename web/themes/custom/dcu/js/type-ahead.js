//---- Dummy kode som blot viser resultatvinduet

function typeAhead() {
    var searchElement = document.getElementById('camping-search');
    var resultsElement = document.getElementById('camping-search-results');
    if (searchElement === null || resultsElement === null) {
        return;
    }
    searchElement.addEventListener('keyup', function() {
        if (searchElement.value.toString().length > 2 && resultsElement.className.indexOf('--active') === -1) {
            resultsElement.classList.add('--active');
        } else if (searchElement.value.toString().length < 3 && resultsElement.className.indexOf('--active') !== -1) {
            resultsElement.classList.remove('--active');
        }
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
    typeAhead()
});

//---- Skal måske bruges til den rigtige implementering

/* const searchOptions = [
    {
        title: 'Læsø camping',
        tags: ['læsø', 'camping', 'nødcamping']
    },
    {
        title: 'Libyen "camping"',
        tags: ['libyen', 'camping', 'nødcamping']
    }
]

let results = this.searchOptions.filter(
    (option) => filterValue.every((val) =>
        option.location.toLowerCase().includes(val.toLowerCase())
    )
) */