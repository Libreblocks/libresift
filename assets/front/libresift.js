/**
 * LibreSift front end: turns filter forms into instant filtering.
 *
 * Every filter is a normal GET form (works without JavaScript). With this
 * script, changes update the address (?f_city=sp) and refresh the LibreForge
 * listings through the REST API, without reloading the page.
 */
( function () {
	'use strict';

	var data = window.libresiftData || {};
	var prefix = data.prefix || 'f_';
	var timer = null;
	var controller = null;

	function forms() {
		return Array.prototype.slice.call( document.querySelectorAll( '[data-libresift-filter]' ) );
	}

	/** Value of one filter form as a string for the address ("" when empty). */
	function formValue( form ) {
		var control = form.getAttribute( 'data-control' );

		if ( control === 'checkboxes' ) {
			return Array.prototype.slice
				.call( form.querySelectorAll( 'input[type="checkbox"]:checked' ) )
				.map( function ( input ) {
					return input.value;
				} )
				.join( ',' );
		}

		if ( control === 'range' ) {
			var inputs = form.querySelectorAll( '.ls-range input' );
			var min = inputs[ 0 ] ? inputs[ 0 ].value : '';
			var max = inputs[ 1 ] ? inputs[ 1 ].value : '';

			return min === '' && max === '' ? '' : min + '-' + max;
		}

		if ( control === 'radio' ) {
			var checked = form.querySelector( 'input[type="radio"]:checked' );
			return checked ? checked.value : '';
		}

		var field = form.querySelector( 'select, input[type="search"]' );
		return field ? field.value.trim() : '';
	}

	/** Current address with the filter values of every form on the page. */
	function buildUrl() {
		var url = new URL( window.location.href );

		Array.prototype.slice.call( url.searchParams.keys() ).forEach( function ( key ) {
			if ( key.indexOf( prefix ) === 0 || key.indexOf( 'lfp_' ) === 0 ) {
				url.searchParams.delete( key );
			}
		} );

		forms().forEach( function ( form ) {
			var value = formValue( form );

			if ( value !== '' ) {
				url.searchParams.set( prefix + form.getAttribute( 'data-libresift-filter' ), value );
			}
		} );

		return url;
	}

	function paramsOf( url ) {
		var params = {};

		url.searchParams.forEach( function ( value, key ) {
			if ( key.indexOf( prefix ) === 0 || key.indexOf( 'lfp_' ) === 0 ) {
				params[ key ] = value;
			}
		} );

		return params;
	}

	/** Listings controlled by the filters: the targeted ones, or every listing when a filter has no target. */
	function listings() {
		var all = Array.prototype.slice.call( document.querySelectorAll( '[data-libreforge-loop][data-lf-settings]' ) );
		var targets = forms().map( function ( form ) {
			return form.getAttribute( 'data-target' ) || '';
		} );

		if ( targets.indexOf( '' ) !== -1 ) {
			return all;
		}

		return all.filter( function ( listing ) {
			return targets.indexOf( listing.getAttribute( 'data-libreforge-loop' ) ) !== -1;
		} );
	}

	function setBusy( nodes, busy ) {
		nodes.forEach( function ( node ) {
			node.classList.toggle( 'ls-is-loading', busy );
			node.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
		} );
	}

	function updateResets( url ) {
		var active = Object.keys( paramsOf( url ) ).some( function ( key ) {
			return key.indexOf( prefix ) === 0;
		} );

		document.querySelectorAll( '[data-libresift-reset]' ).forEach( function ( link ) {
			link.hidden = ! active;
		} );
	}

	/** Re-renders the given listings with the filters of `url`. */
	function refresh( url, only ) {
		var nodes = only || listings();

		if ( ! nodes.length || ! data.endpoint ) {
			window.location.href = url.toString();
			return;
		}

		if ( controller ) {
			controller.abort();
		}

		controller = window.AbortController ? new AbortController() : null;
		setBusy( nodes, true );

		window
			.fetch( data.endpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				credentials: 'same-origin',
				signal: controller ? controller.signal : undefined,
				body: JSON.stringify( {
					params: paramsOf( url ),
					listings: nodes.map( function ( node ) {
						return { payload: node.getAttribute( 'data-lf-settings' ), signature: node.getAttribute( 'data-lf-signature' ) };
					} ),
				} ),
			} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( response.status );
				}
				return response.json();
			} )
			.then( function ( result ) {
				nodes.forEach( function ( node ) {
					var id = node.getAttribute( 'data-libreforge-loop' );
					var html = result.listings && result.listings[ id ];

					if ( typeof html === 'string' ) {
						var holder = document.createElement( 'div' );
						holder.innerHTML = html;
						var fresh = holder.querySelector( '[data-libreforge-loop]' );

						if ( fresh ) {
							node.replaceWith( fresh );
						}
					}
				} );

				document.dispatchEvent( new CustomEvent( 'libresift:updated', { detail: { url: url.toString() } } ) );
			} )
			.catch( function ( error ) {
				if ( error && error.name === 'AbortError' ) {
					return;
				}
				setBusy( nodes, false );
				window.location.href = url.toString(); // Fall back to a normal page load.
			} );

		window.history.pushState( { libresift: true }, '', url.toString() );
		updateResets( url );
	}

	function apply() {
		refresh( buildUrl() );
	}

	function schedule( delay ) {
		window.clearTimeout( timer );
		timer = window.setTimeout( apply, delay );
	}

	document.addEventListener( 'change', function ( event ) {
		var form = event.target.closest && event.target.closest( '[data-libresift-filter]' );

		if ( form && form.getAttribute( 'data-apply' ) === 'instant' ) {
			schedule( 50 );
		}
	} );

	document.addEventListener( 'input', function ( event ) {
		var form = event.target.closest && event.target.closest( '[data-libresift-filter]' );

		if ( form && form.getAttribute( 'data-apply' ) === 'instant' && event.target.type === 'search' ) {
			schedule( 450 );
		}
	} );

	document.addEventListener( 'submit', function ( event ) {
		if ( event.target.matches && event.target.matches( '[data-libresift-filter]' ) ) {
			event.preventDefault();
			apply();
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		var reset = event.target.closest && event.target.closest( '[data-libresift-reset]' );

		if ( reset ) {
			event.preventDefault();

			forms().forEach( function ( form ) {
				form.querySelectorAll( 'input[type="checkbox"], input[type="radio"]' ).forEach( function ( input ) {
					input.checked = input.type === 'radio' && input.value === '';
				} );
				form.querySelectorAll( 'input[type="number"], input[type="search"]' ).forEach( function ( input ) {
					input.value = '';
				} );
				form.querySelectorAll( 'select' ).forEach( function ( select ) {
					select.selectedIndex = 0;
				} );
			} );

			apply();
			return;
		}

		// Pagination inside a filtered listing loads without leaving the page.
		var link = event.target.closest && event.target.closest( '.lf-pagination a' );
		var listing = link && link.closest( '[data-libreforge-loop][data-lf-settings]' );

		if ( link && listing && forms().length ) {
			event.preventDefault();

			var url = buildUrl();
			var target = new URL( link.href );
			var key = 'lfp_' + listing.getAttribute( 'data-libreforge-loop' );

			if ( target.searchParams.get( key ) ) {
				url.searchParams.set( key, target.searchParams.get( key ) );
			}

			refresh( url, [ listing ] );
			listing.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}
	} );

	// Back/forward buttons: show the state of that address.
	window.addEventListener( 'popstate', function () {
		window.location.reload();
	} );
} )();
