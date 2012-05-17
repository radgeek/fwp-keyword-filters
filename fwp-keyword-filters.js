function initializeFWPKeywordActionParams () {
	updateFWPKeywordActionParams(/*what=*/ this, /*duration=*/ 0);
}
function refreshFWPKeywordActionParams () {
	updateFWPKeywordActionParams(/*what=*/ this, /*duration=*/ 'slow');
}

function updateFWPKeywordActionParams (what, duration) {
	if (typeof(duration)=='undefined') {
		duration = 'slow';
	}
	
	var context = jQuery(what).closest('.keyword-filters-row');
	
	context.find('.keyword-filters-param:visible').hide(duration, function () {
		context.find('.keyword-filters-param-' + what.value).show(duration);
	});
} /* keyword_filters_param_refresh () */

function removeFWPKeywordFilter () {
	var slug = this.id.replace(/^keyword-filters-remove-/, '');
	jQuery('#keyword-filters-row-'+slug).hide('fast', function () {
		jQuery(this).remove();
	});
	return false;
}

jQuery(document).ready( function () {
	jQuery('.keyword-filters-row').each ( function () {
		var slug = this.id.replace(/^keyword-filters-row-/, '');
		var topRows = jQuery(this).find('.keyword-filters-top-row');
		topRows.css('float', 'left');
		jQuery('<div style="float: left">'
			+'<a id="keyword-filters-remove-'+slug+'" class="remove-it" href="#"><span class="x">X</span> Remove</a>'
			+'</div>').insertAfter(topRows);
		jQuery('#keyword-filters-remove-'+slug).click ( removeFWPKeywordFilter );
	} );
	jQuery('#keyword-filters-row-new-1, #keyword-filters-row-new-2').each ( function() {
		var slug = this.id.replace(/^keyword-filters-row-/, '');
		jQuery('<p style="clear: both; font-size: 1.05em; font-weight: bold;"><a id="keyword-filters-add-'+slug+'" href="#">+ Add Another Filter</a></p>').insertAfter(this);
		jQuery(this).hide();
		jQuery('#keyword-filters-add-'+slug).click ( function () {
			// new-1 or new-2
			var counter = jQuery('#feedwordpress-keyword-filters-num');
			var slug = this.id.replace(/^keyword-filters-add-/, '');
			var num = parseInt(counter.val());

			// Set up contents from paradigm element
			var newContents = document.getElementById('keyword-filters-row-'+slug).innerHTML;
			var re = new RegExp('([_\\-])'+slug, 'g');
			var re2 = new RegExp('(\\[)'+slug+'(\\])', 'g');
			newContents = newContents.replace(re, '$1'+num);
			newContents = newContents.replace(re2, '['+num+']');
			
			// Set up element frame
			var newFilter = document.createElement('div');
			newFilter.setAttribute('id', 'keyword-filters-row-' + num);
			newFilter.setAttribute('class', 'keyword-filters-row');
			newFilter.setAttribute('style', 'display: none; clear: both');
			
			// Fill element frame with contents
			newFilter.innerHTML += newContents;

			// Put it into the document and hook in triggers
			jQuery(newFilter).insertBefore('#keyword-filters-row-'+slug);
			jQuery('#keyword-filters-action-'+num).each( initializeFWPKeywordActionParams );
			jQuery('#keyword-filters-action-'+num).change( refreshFWPKeywordActionParams );
			jQuery('#keyword-filters-remove-'+num).click( removeFWPKeywordFilter );

			// The big reveal
			jQuery(newFilter).show();
			
			// Advance the counter
			num = num + 1;
			counter.val(num);
			
			return false;
		} );
	} );

	// Set up param-box flipping
	jQuery('.feedwordpress-keyword-filters-action').change( refreshFWPKeywordActionParams );
	jQuery('.feedwordpress-keyword-filters-action').each( initializeFWPKeywordActionParams );
	
	
} );

