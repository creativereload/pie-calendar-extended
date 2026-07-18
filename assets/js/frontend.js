( function () {
	'use strict';

	// "Load more" pagination for [piecal_layouts]. Delegated so it works for
	// any number of instances on a page, each button carrying its own target
	// wrap, page, nonce, ajax URL, and query/render attributes.
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.pcl-load-more' );
		if ( ! button ) {
			return;
		}

		event.preventDefault();

		// Ignore clicks while a request for this button is in flight.
		if ( button.getAttribute( 'data-loading' ) === '1' ) {
			return;
		}
		button.setAttribute( 'data-loading', '1' );
		button.classList.add( 'is-loading' );

		var nextPage = parseInt( button.getAttribute( 'data-page' ), 10 ) + 1;

		var body = new URLSearchParams();
		body.append( 'action', 'pcl_load_more' );
		body.append( 'nonce', button.getAttribute( 'data-nonce' ) );
		body.append( 'atts', button.getAttribute( 'data-atts' ) );
		body.append( 'page', nextPage );

		fetch( button.getAttribute( 'data-ajaxurl' ), {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( ! result || ! result.success || ! result.data ) {
					return;
				}

				var wrap = document.getElementById( button.getAttribute( 'data-target' ) );
				if ( wrap && result.data.html ) {
					wrap.insertAdjacentHTML( 'beforeend', result.data.html );
				}

				button.setAttribute( 'data-page', result.data.page );

				// No further pages: the button has done its job.
				if ( ! result.data.has_more ) {
					button.parentNode.removeChild( button );
				}
			} )
			.catch( function () {
				// Leave the button in place so the user can retry.
			} )
			.finally( function () {
				button.removeAttribute( 'data-loading' );
				button.classList.remove( 'is-loading' );
			} );
	} );
} )();
