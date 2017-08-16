<?php

/**
 * SpecialPage file for AdvancedUserStats
 * Displays stats from logging table and reverts
 * @ingroup Extensions
 * @author Josef Martiňák
 * @license MIT
 * @file
 */


class AdvancedUserStats extends SpecialPage {
	public function __construct() {
		parent::__construct( 'AdvancedUserStats', 'editinterface' );
	}

	/// Generates a "AdvancedUserStats" table for a given LIMIT and date range
	/**
	 * Function generates AdvancedUserStats tables in HTML format (not wikiText)
	 *
	 * @param $days int Days in the past to run report for
	 * @param $limit int Maximum number of users to return (default 50)
	 * @return Html Table representing the requested AdvancedUserStats.
	 */
	function genAUStable( $days, $limit ) {
		$dbr = wfGetDB( DB_SLAVE );
		$date = time() - ( 60 * 60 * 24 * $days );
		$dateString = $dbr->timestamp( $date );
		$wherePatrol = "WHERE logging.log_type='patrol' AND logging.log_params LIKE '%\"6::auto\";i:0%' AND user.user_name IS NOT NULL ";
		$whereUndo = "WHERE revision.rev_comment LIKE '%Zrušena verze%' AND user.user_name IS NOT NULL ";
		$whereRollback = "WHERE revision.rev_comment LIKE '%vráceny do předchozího stavu%' AND user.user_name IS NOT NULL ";
		if ( $days > 0 ) {
			$wherePatrol .= "AND logging.log_timestamp > '$dateString' ";
			$whereUndo .= "AND revision.rev_timestamp > '$dateString' ";
			$whereRollback .= "AND revision.rev_timestamp > '$dateString' ";
		}
		
		// načti patrolace
		$sql = "SELECT logging.log_user AS userid, user.user_name AS username, user.user_real_name AS userrealname, ";
		$sql .= "GROUP_CONCAT(logging.log_page) AS pages, COUNT(logging.log_page) AS pcount ";
		$sql .= "FROM logging LEFT JOIN user ON(logging.log_user = user.user_id) ";
		$sql .= "$wherePatrol GROUP BY logging.log_user ORDER BY pcount DESC";
		$output = $this->prepareTableOutput( $sql, 'patrol', $limit, $dbr );

		// načti undo
		$sql = "SELECT revision.rev_user AS userid, user.user_name AS username, user.user_real_name AS userrealname,";
		$sql .= "revision.rev_comment AS comment, GROUP_CONCAT(revision.rev_page) AS pages, COUNT(revision.rev_page) AS pcount ";
		$sql .= "FROM revision LEFT JOIN user ON(revision.rev_user = user.user_id) ";
		$sql .= "$whereUndo GROUP BY revision.rev_user ORDER BY pcount DESC";
		$output .= $this->prepareTableOutput( $sql, 'undo', $limit, $dbr );
		
		// načti rollback
		$sql = "SELECT revision.rev_user AS userid, user.user_name AS username, user.user_real_name AS userrealname,";
		$sql .= "revision.rev_comment AS comment, GROUP_CONCAT(revision.rev_page) AS pages, COUNT(revision.rev_page) AS pcount ";
		$sql .= "FROM revision LEFT JOIN user ON(revision.rev_user = user.user_id) ";
		$sql .= "$whereRollback GROUP BY revision.rev_user ORDER BY pcount DESC";
		$output .= $this->prepareTableOutput( $sql, 'rollback', $limit, $dbr );

		return $output;
	}
	
	/**
	 * Prepare table
	 *
	 * @param $sql
	 * @param $type=patrol|undo|rollback
	 * @return $limit 
	 */	
	function prepareTableOutput( $sql, $type, $limit = '', $dbr ) {
		$sortable = ' sortable';	// '' pro netrizenou tabulku
		$altrow = '';
		if( $limit ) $sql .= " LIMIT $limit";
		$res = $dbr->query( $sql );
		$output = "\n<table class=\"wikitable advanceduserstats plainlinks{$sortable}\" >\n";
		$output .= "<tr class='header'><th style='width:300px;'>" . $this->msg( 'advanceduserstats-username' )->text() . "</th>";
		$output .= "<th>" . $this->msg( 'advanceduserstats-' . $type )->text() . "</th></tr>";
		foreach ( $res as $row ) {
			// Use real name if option used and real name present
			if( !empty( $row->username ) ) {
				$tmp = Linker::userLink( $row->userid, $row->username );
				if ( $row->userrealname !== '' ) $tmp .= " (" . $row->userrealname . ")";
			}
			else continue;
			$output .= "<tr class='{$altrow}'><td>";
			$output .= $tmp . "</td><td>";
			$output .= substr_count( $row->pages, ',' ) + 1;
			$output .= "&nbsp;&nbsp;<a class='AUSdetailsToggle' href='" . $row->pages . "'>detail</a>";
			$output .= "<div class='AUSdetails'>";
			$pages = array_unique( explode(',', $row->pages ) );
			$output .= "<ul>";
			foreach ( $pages as $page ) {
				if( $t = Title::newFromID( $page ) ) {
					$output .= "<li><a href='" . $t->getFullUrl() . "?action=history' target='_blank'>" . $t->getPrefixedText() . "</a></li>";
				}
				else $output .= "<li>$page</li>";
			}
			$output .= "</ul>";
			$output .= "</div>";
			$output .= "</td></tr>";
			if ( $altrow == '' && empty( $sortable ) ) {
				$altrow = 'odd ';
			} else {
				$altrow = '';
			}
		}
		$output .= "</table>\n";
		$dbr->freeResult( $res );
		
		return $output;
	}		
	
	function execute( $par ) {
		global $wgAUSreports;
		$this->checkPermissions();
		$this->setHeaders();
		$out = $this->getOutput();
		$out->addModules('ext.AdvancedUserStats');
		$out->addWikiMsg( 'advanceduserstats-info' );
		$dbr = wfGetDB( DB_SLAVE );
		
		// display special page
		if ( !is_array( $wgAUSreports ) ) {
			$wgAUSreports = array(
				array( 7, 50 ),
				array( 30, 50 ),
				array( 0, 50 )
			);
		}
		/*
		$dropdown = "<br><form><select id='AUSswitch'>\n";
		$dropdown .= "<option value='patrol'>" . $this->msg( 'advanceduserstats-patrol' )->text() . "</option>";
		$dropdown .= "<option value='undo'>" . $this->msg( 'advanceduserstats-undo' )->text() . "</option>";
		$dropdown .= "<option value='revert'>" . $this->msg( 'advanceduserstats-revert' )->text() . "</option>";
		$dropdown .= "</select></form>";
		$out->addHTML($dropdown);
		*/
		foreach ( $wgAUSreports as $scoreReport ) {
			list( $days, $revs ) = $scoreReport;
			if ( $days > 0 ) {
				$reportTitle = $this->msg( 'advanceduserstats-days' )->numParams( $days )->text();
			} else {
				$reportTitle = $this->msg( 'advanceduserstats-allrevisions' )->text();
			}
			//$reportTitle .= " " . $this->msg( 'advanceduserstats-top' )->numParams( $revs )->text();
			$title = Xml::element( 'h2', array( 'class' => 'advanceduserstats-title' ), $reportTitle ) . "\n";
			$out->addHTML( $title );
			$out->addHTML( $this->genAUStable( $days, $revs ) );
		}

		return true;
	}
	
	protected function getGroupName() {
		return 'wiki';
	}
}
