( function ( mw, $ ) {
	function render( pages ) {
		if ( !pages || !pages.length ) {
			return '<em>Bez záznamů.</em>';
		}
		var html = '<ul class="aus-detail-list">';
		pages.forEach( function ( p ) {
			var titleObj = mw.Title.newFromText( p.title, p.ns );
			var href = titleObj ? titleObj.getUrl() : '#';
			var text = titleObj ? titleObj.getPrefixedText() : p.title;
			html += '<li><a href="' + mw.html.escape( href ) + '" target="_blank" rel="noopener">' + mw.html.escape( text ) + '</a> (' + mw.html.escape( String( p.edits ) ) + ')</li>';
		} );
		html += '</ul>';
		return html;
	}

	function showError( $box, err ) {
		var msg = 'Chyba při načítání detailu.';
		// Try to extract API error info
		if ( err && err.error && err.error.info ) {
			msg += ' ' + err.error.info;
		}
		$box.html( '<strong>' + mw.html.escape( msg ) + '</strong>' );
		// Also log full error to console for debugging
		if ( window.console && console.error ) {
			console.error( 'AdvancedUserStats detail error:', err );
		}
	}

	function bind( $content ) {
		$content.find( '.AUSdetailsToggle' ).off( 'click.aus' ).on( 'click.aus', function ( e ) {
			e.preventDefault();

			var $a = $( this );
			var $box = $a.next( '.AUSdetails' );

			if ( $box.data( 'loaded' ) ) {
				$box.toggle();
				return;
			}

			$box.show().html( '<em>Načítám…</em>' );

			new mw.Api().get( {
				action: 'advanceduserstatsdetail',
				type: $a.data( 'type' ),
				userid: $a.data( 'user-id' ),
				days: $a.data( 'days' ) || 0,
				limit: $a.data( 'limit' ) || 200,
				format: 'json',
				formatversion: 2
			} ).then( function ( data ) {
				$box.data( 'loaded', 1 ).html( render( data.pages ) );
			}, function ( code, details ) {
				// MediaWiki API rejects with (code, details)
				showError( $box, details );
			} );
		} );
	}

	mw.hook( 'wikipage.content' ).add( bind );
}( mediaWiki, jQuery ) );