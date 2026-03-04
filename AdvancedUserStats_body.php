<?php

use MediaWiki\Linker\Linker;
use MediaWiki\Title\Title;
use MediaWiki\Xml\Xml;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;

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
		$services = MediaWikiServices::getInstance();
		$cache = $services->getMainWANObjectCache();
		$cacheKey = $cache->makeKey( 'advanceduserstats', 'summary', 'v2', (string)$days, (string)$limit );

		return $cache->getWithSetCallback(
			$cacheKey,
			600,
			function () use ( $services, $days, $limit ) {
				$lb = $services->getDBLoadBalancer();
				$dbr = $lb->getConnection( DB_REPLICA );
		//$dbr = wfGetDB( DB_SLAVE );
		$date = time() - ( 60 * 60 * 24 * $days );
		$dateString = $dbr->timestamp( $date );

		$wherePatrol = "WHERE logging.log_type='patrol' AND logging.log_params LIKE '%\"6::auto\";i:0%' AND user.user_name IS NOT NULL ";
		$whereUndo = "WHERE comment.comment_text LIKE '%Zrušena verze%' AND user.user_name IS NOT NULL ";
		$whereRollback = "WHERE comment.comment_text LIKE '%vráceny do předchozího stavu%' AND user.user_name IS NOT NULL ";
		if ( $days > 0 ) {
			$wherePatrol .= "AND logging.log_timestamp > '$dateString' ";
			$whereUndo .= "AND revision.rev_timestamp > '$dateString' ";
			$whereRollback .= "AND revision.rev_timestamp > '$dateString' ";
		}

				// načti patrolace (souhrn)
				$sql = "SELECT actor.actor_user AS userid, user.user_name AS username, user.user_real_name AS userrealname, ";
				$sql .= "COUNT(DISTINCT logging.log_page) AS pcount ";
		$sql .= "FROM logging ";
		$sql .= "INNER JOIN actor ON(logging.log_actor = actor.actor_id) ";
		$sql .= "INNER JOIN user ON(actor.actor_user = user.user_id) ";
		$sql .= "$wherePatrol GROUP BY logging.log_actor ORDER BY pcount DESC";
		$output = $this->prepareTableOutput( $dbr, $sql, 'patrol', $limit, $days );

				// načti undo (souhrn)
				$sql = "SELECT actor.actor_user AS userid, user.user_name AS username, user.user_real_name AS userrealname,";
				$sql .= "COUNT(DISTINCT revision.rev_page) AS pcount ";
		$sql .= "FROM revision ";
// MW 1.39 used revision_comment_temp; MW 1.45 typically stores rev_comment_id directly on revision.
if ( $dbr->tableExists( 'revision_comment_temp', __METHOD__ ) ) {
	$sql .= "INNER JOIN revision_comment_temp ON(revision_comment_temp.revcomment_rev = revision.rev_id) ";
	$sql .= "INNER JOIN comment ON(comment.comment_id = revision_comment_temp.revcomment_comment_id) ";
} else {
	$sql .= "INNER JOIN comment ON(comment.comment_id = revision.rev_comment_id) ";
}
		$sql .= "INNER JOIN actor ON(revision.rev_actor = actor.actor_id) ";
		$sql .= "INNER JOIN user ON(actor.actor_user = user.user_id) ";
		$sql .= "$whereUndo GROUP BY actor.actor_user HAVING user.user_name IS NOT NULL AND user.user_real_name LIKE '%' ORDER BY pcount DESC";
		$output .= $this->prepareTableOutput( $dbr, $sql, 'undo', $limit, $days );

				// načti rollback (souhrn)
				$sql = "SELECT actor.actor_user AS userid, user.user_name AS username, user.user_real_name AS userrealname,";
				$sql .= "COUNT(DISTINCT revision.rev_page) AS pcount ";
		$sql .= "FROM revision ";
// MW 1.39 used revision_comment_temp; MW 1.45 typically stores rev_comment_id directly on revision.
if ( $dbr->tableExists( 'revision_comment_temp', __METHOD__ ) ) {
	$sql .= "INNER JOIN revision_comment_temp ON(revision_comment_temp.revcomment_rev = revision.rev_id) ";
	$sql .= "INNER JOIN comment ON(comment.comment_id = revision_comment_temp.revcomment_comment_id) ";
} else {
	$sql .= "INNER JOIN comment ON(comment.comment_id = revision.rev_comment_id) ";
}
		//$sql .= "INNER JOIN revision_actor_temp ON(revision_actor_temp.revactor_page = revision.rev_page AND revision_actor_temp.revactor_rev = revision.rev_id) ";
		$sql .= "INNER JOIN actor ON(revision.rev_actor = actor.actor_id) ";
		$sql .= "INNER JOIN user ON(actor.actor_user = user.user_id) ";
		$sql .= "$whereRollback GROUP BY actor.actor_user HAVING user.user_name IS NOT NULL AND user.user_real_name LIKE '%' ORDER BY pcount DESC";
		$output .= $this->prepareTableOutput( $dbr, $sql, 'rollback', $limit, $days );

				return $output;
			}
		);
	}
	
	/**
	 * Prepare table
	 *
	 * @param $sql
	 * @param $type=patrol|undo|rollback
	 * @return $limit 
	 */	
	function prepareTableOutput( $dbr, $sql, $type, $limit = '', $days = 0 ) {
		$sortable = ' sortable';	// '' pro netrizenou tabulku
		$altrow = '';
		if( $limit ) $sql .= " LIMIT $limit";
		$res = $dbr->query( $sql );
		$output = "\n<table class=\"wikitable advanceduserstats plainlinks{$sortable}\" >\n";
		$output .= "<tr class='header'><th style='width:300px;'>" . $this->msg( 'advanceduserstats-username' )->text() . "</th>";
		$output .= "<th>" . $this->msg( 'advanceduserstats-' . $type )->text() . "</th></tr>";
		foreach ( $res as $row ) {
			// Use real name if real name present
			if( !empty( $row->username ) ) {
				$tmp = Linker::userLink( $row->userid, $row->username );
				if ( $row->userrealname !== '' ) $tmp .= " (" . $row->userrealname . ")";
			}
			else continue;
			$output .= "<tr class='{$altrow}'><td>";
			$output .= $tmp . "</td><td>";
			$output .= (int)$row->pcount;
			$output .= "&nbsp;&nbsp;" . Html::element(
				'a',
				[
					'href' => '#',
					'class' => 'AUSdetailsToggle',
					'data-type' => $type,
					'data-user-id' => (string)$row->userid,
					'data-days' => (string)$days,
				],
				'detail'
			);
			$output .= Html::rawElement(
				'div',
				[ 'class' => 'AUSdetails', 'style' => 'display:none' ],
				''
			);
			$output .= "</td></tr>";
			if ( $altrow == '' && empty( $sortable ) ) {
				$altrow = 'odd ';
			} else {
				$altrow = '';
			}
		}
		$output .= "</table>\n";
		#$dbr->freeResult( $res );
		$res->free();
		
		return $output;
	}		
	
	function execute( $par ) {
		$this->checkPermissions();
		$this->setHeaders();
		$out = $this->getOutput();
		$out->addModules('ext.AdvancedUserStats');
		$out->addWikiMsg( 'advanceduserstats-info' );
		$lb = \MediaWiki\MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnection( DB_REPLICA );
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