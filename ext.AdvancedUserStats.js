/**
 * Javascripts for AdvancedUserStats
 * @ingroup Extensions
 * @author Josef Martiňák
 * @license MIT
 * @file
 */
 
( function ( mw, $ ) {

	// Add onclick - zobrazit / skýt detaily
	$('.AUSdetailsToggle').click( function() {
		if( $(this).next().css("display") == "none" ) {
			$(this).next().css("display", "block" );
		}
		else {
			$(this).next().css("display", "none" );
		}
		return false;
	});

}( mediaWiki, jQuery ) );