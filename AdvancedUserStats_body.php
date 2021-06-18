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
	public function genAUStable( $days, $limit ) {
		$conn = \MediaWiki\MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $conn->getConnectionRef(DB_REPLICA);
		//$dbr = wfGetDB( DB_SLAVE );
		$date = time() - ( 60 * 60 * 24 * $days );
		$dateString = $dbr->timestamp( $date );

		/*
		revision
		rev_actor (zatím nula, bude fungovat v dalších verzích)

		revision_actor_temp
		revactor_rev	revactor_actor	revactor_timestamp	revactor_page

		actor
		actor_id | actor_user | actor_name

		revision_comment_temp
		revcomment_rev    revcomment_comment_id

		comment
		comment_id  comment_text
		*/

		$wherePatrol = "WHERE logging.log_type='patrol' AND logging.log_params LIKE '%\"6::auto\";i:0%' AND user.user_name IS NOT NULL ";
		$whereUndo = "WHERE comment.comment_text LIKE '%Zrušena verze%' AND user.user_name IS NOT NULL ";
		$whereRollback = "WHERE comment.comment_text LIKE '%vráceny do předchozího stavu%' AND user.user_name IS NOT NULL ";
		if ( $days > 0 ) {
			$wherePatrol .= "AND logging.log_timestamp > '$dateString' ";
			$whereUndo .= "AND revision.rev_timestamp > '$dateString' ";
			$whereRollback .= "AND revision.rev_timestamp > '$dateString' ";
		}

		// načti patrolace
		$sql = "SELECT actor.actor_user AS userid, user.user_name AS username, user.user_real_name AS userrealname, ";
		$sql .= "GROUP_CONCAT(logging.log_page) AS pages, COUNT(logging.log_page) AS pcount ";
		$sql .= "FROM logging ";
		$sql .= "INNER JOIN actor ON(logging.log_actor = actor.actor_id) ";
		$sql .= "INNER JOIN user ON(actor.actor_user = user.user_id) ";
		$sql .= "$wherePatrol GROUP BY logging.log_actor ORDER BY pcount DESC";
		$output = $this->prepareTableOutput( $sql, 'patrol', $limit, $dbr );

		// načti undo
		$sql = "SELECT actor.actor_user AS userid, user.user_name AS username, user.user_real_name AS userrealname,";
		$sql .= "comment.comment_text AS comment, GROUP_CONCAT(revision.rev_page) AS pages, COUNT(revision.rev_page) AS pcount ";
		$sql .= "FROM revision ";
		$sql .= "INNER JOIN revision_actor_temp ON(revision_actor_temp.revactor_page = revision.rev_page AND revision_actor_temp.revactor_rev = revision.rev_id) ";
		$sql .= "INNER JOIN revision_comment_temp ON(revision_comment_temp.revcomment_comment_id = revision.rev_comment_id) ";
		$sql .= "INNER JOIN comment ON(comment.comment_id = revision_comment_temp.revcomment_comment_id) ";
		$sql .= "INNER JOIN actor ON(revision_actor_temp.revactor_actor = actor.actor_id) ";
		$sql .= "INNER JOIN user ON(actor.actor_user = user.user_id) ";
		$sql .= "$whereUndo GROUP BY actor.actor_user ORDER BY pcount DESC";
		$output .= $this->prepareTableOutput( $sql, 'undo', $limit, $dbr );
		
		// načti rollback
		$sql = "SELECT actor.actor_user AS userid, user.user_name AS username, user.user_real_name AS userrealname,";
		$sql .= "comment.comment_text AS comment, GROUP_CONCAT(revision.rev_page) AS pages, COUNT(revision.rev_page) AS pcount ";
		$sql .= "FROM revision ";
		$sql .= "INNER JOIN revision_actor_temp ON(revision_actor_temp.revactor_page = revision.rev_page AND revision_actor_temp.revactor_rev = revision.rev_id) ";
		$sql .= "INNER JOIN revision_comment_temp ON(revision_comment_temp.revcomment_comment_id = revision.rev_comment_id) ";
		$sql .= "INNER JOIN comment ON(comment.comment_id = revision_comment_temp.revcomment_comment_id) ";
		$sql .= "INNER JOIN actor ON(revision_actor_temp.revactor_actor = actor.actor_id) ";
		$sql .= "INNER JOIN user ON(actor.actor_user = user.user_id) ";
		$sql .= "$whereRollback GROUP BY actor.actor_user ORDER BY pcount DESC";
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
				else $output .= "<li>$page (odstraněno)</li>";
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
		$this->checkPermissions();
		$this->setHeaders();
		$out = $this->getOutput();
		$out->addModules('ext.AdvancedUserStats');
		$out->addWikiMsg( 'advanceduserstats-info' );
		$conn = \MediaWiki\MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $conn->getConnectionRef(DB_REPLICA);
		//$dbr = wfGetDB( DB_SLAVE );
		
		// display special page
		$config = $this->getConfig();
		$AUSreports = $config->get( 'AUSreports' );
		if ( !is_array( $AUSreports ) ) {
			// default values
			$AUSreports = array(
				array( 7, 50 ),
				array( 30, 50 ),
				array( 0, 50 )
			);
		}
		foreach ( $AUSreports as $scoreReport ) {
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
