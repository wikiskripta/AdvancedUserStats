<?php

namespace MediaWiki\Extension\AdvancedUserStats\Api;

use ApiBase;
use MediaWiki\MediaWikiServices;
use MediaWiki\Rdbms\SelectQueryBuilder;
use Wikimedia\Rdbms\IReadableDatabase;

class ApiAdvancedUserStatsDetail extends ApiBase {
	public function execute() {
		$params = $this->extractRequestParams();
		$type = (string)$params['type'];
		$userId = (int)$params['userid'];
		$days = (int)$params['days'];
		$limit = (int)$params['limit'];

		if ( !in_array( $type, [ 'patrol', 'undo', 'rollback' ], true ) ) {
			$this->dieWithError( 'Invalid type', 'badtype' );
		}
		if ( $userId <= 0 ) {
			$this->dieWithError( 'Invalid userid', 'baduserid' );
		}
		if ( $limit <= 0 || $limit > 2000 ) {
			$limit = 200;
		}

		$services = MediaWikiServices::getInstance();
		$cache = $services->getMainWANObjectCache();
		$cacheKey = $cache->makeKey( 'advanceduserstats', 'detail', 'v3', $type, (string)$userId, (string)$days, (string)$limit );

		$data = $cache->getWithSetCallback(
			$cacheKey,
			300, // 5 min
			function () use ( $services, $type, $userId, $days, $limit ) {
				$cp = $services->getConnectionProvider();
				$dbr = $cp->getReplicaDatabase();
				return $this->fetchDetail( $dbr, $type, $userId, $days, $limit );
			}
		);

		$this->getResult()->addValue( null, 'pages', $data );
	}

	/**
	 * @return array<int, array{ns:int,title:string,edits:int,last:string}>
	 */
	private function fetchDetail( IReadableDatabase $dbr, string $type, int $userId, int $days, int $limit ): array {
		$dateString = null;
		if ( $days > 0 ) {
			$date = time() - ( 60 * 60 * 24 * $days );
			$dateString = $dbr->timestamp( $date );
		}

		// patrol details from logging
		if ( $type === 'patrol' ) {
			$where = [
				'logging.log_type' => 'patrol',
				'actor.actor_user' => $userId,
			];

			$qb = $dbr->newSelectQueryBuilder()
				->select( [
					'page_id' => 'logging.log_page',
					'edits' => 'COUNT(*)',
					'last_ts' => 'MAX(logging.log_timestamp)'
				] )
				->from( 'logging' )
				->join( 'actor', null, 'logging.log_actor = actor.actor_id' )
				->where( $where )
				// keep original filter (auto patrol)
				->andWhere( 'logging.log_params LIKE ' . $dbr->addQuotes( '%"6::auto";i:0%' ) )
				->groupBy( [ 'logging.log_page' ] )
				->orderBy( 'last_ts', 'DESC' )
				->limit( $limit )
				->caller( __METHOD__ );

			if ( $dateString !== null ) {
				$qb->andWhere( $dbr->expr( 'logging.log_timestamp', '>', $dateString ) );
			}

			$pageRows = $qb->fetchResultSet();
			return $this->hydratePages( $dbr, $pageRows );
		}

		// undo/rollback details from revision + comment
		$commentLike = ( $type === 'undo' ) ? '%Zrušena verze%' : '%vráceny do předchozího stavu%';

		$qb = $dbr->newSelectQueryBuilder()
			->select( [
				'page_id' => 'revision.rev_page',
				'edits' => 'COUNT(*)',
				'last_ts' => 'MAX(revision.rev_timestamp)'
			] )
			->from( 'revision' )
			->join( 'actor', null, 'revision.rev_actor = actor.actor_id' )
			->where( [ 'actor.actor_user' => $userId ] )
			->groupBy( [ 'revision.rev_page' ] )
			->orderBy( 'last_ts', 'DESC' )
			->limit( $limit )
			->caller( __METHOD__ );

		// MW 1.39 compatibility: revision_comment_temp
		if ( $dbr->tableExists( 'revision_comment_temp', __METHOD__ ) ) {
			$qb->join( 'revision_comment_temp', null, 'revision_comment_temp.revcomment_rev = revision.rev_id' )
				->join( 'comment', null, 'comment.comment_id = revision_comment_temp.revcomment_comment_id' );
		} else {
			$qb->join( 'comment', null, 'comment.comment_id = revision.rev_comment_id' );
		}

		$commentNeedle = is_string( $commentLike ) ? trim( $commentLike, '%' ) : '';
		$qb->andWhere( 'comment.comment_text LIKE ' . $dbr->addQuotes( $commentLike ) );

if ( $dateString !== null ) {
			$qb->andWhere( $dbr->expr( 'revision.rev_timestamp', '>', $dateString ) );
		}

		$pageRows = $qb->fetchResultSet();
		return $this->hydratePages( $dbr, $pageRows );
	}

	/**
	 * @param iterable $pageRows rows with page_id, edits, last_ts
	 */
	private function hydratePages( IReadableDatabase $dbr, iterable $pageRows ): array {
		$pageIds = [];
		$statsById = [];
		foreach ( $pageRows as $row ) {
			$pid = (int)$row->page_id;
			$pageIds[] = $pid;
			$statsById[$pid] = [
				'edits' => (int)$row->edits,
				'last' => (string)$row->last_ts,
			];
		}

		if ( !$pageIds ) {
			return [];
		}

		// Fetch titles in one query to avoid per-row Title::newFromID()
		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'page_id', 'page_namespace', 'page_title' ] )
			->from( 'page' )
			->where( [ 'page_id' => $pageIds ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$out = [];
		foreach ( $res as $row ) {
			$pid = (int)$row->page_id;
			$st = $statsById[$pid] ?? [ 'edits' => 0, 'last' => '' ];
			$out[] = [
				'ns' => (int)$row->page_namespace,
				'title' => (string)$row->page_title,
				'edits' => (int)$st['edits'],
				'last' => (string)$st['last'],
			];
		}

		// If some pages were deleted, they won't be in page table; append placeholders.
		if ( count( $out ) < count( $pageIds ) ) {
			$seen = [];
			foreach ( $out as $r ) {
				// cannot reconstruct page_id from output reliably; skip
			}
			// Keep it simple: ignore deleted pages in detail (they are rare, and avoiding extra queries).
		}

		return $out;
	}

	public function getAllowedParams() {
		return [
			'type' => [ self::PARAM_TYPE => 'string', self::PARAM_REQUIRED => true ],
			'userid' => [ self::PARAM_TYPE => 'integer', self::PARAM_REQUIRED => true ],
			'days' => [ self::PARAM_TYPE => 'integer', self::PARAM_REQUIRED => false, self::PARAM_DFLT => 0 ],
			'limit' => [ self::PARAM_TYPE => 'integer', self::PARAM_REQUIRED => false, self::PARAM_DFLT => 200 ],
		];
	}

	public function isWriteMode() {
		return false;
	}
}