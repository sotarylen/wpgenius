<?php
/**
 * Actor Deduplicator & Term Merger (High Performance Edition)
 *
 * 4-step governance engine for cleaning duplicate Humans taxonomy terms:
 *  1. Identify duplicate clusters (same normalized name, or JP/TC aliases of same actress)
 *  2. Determine winner (Japanese official name > avatar present > post count > older ID)
 *  3. Migrate post relationships in batch and merge term meta (nicknames, roles, avatar)
 *  4. Delete obsolete duplicate terms and refresh caches
 *
 * @package WP_Genius
 * @subpackage Modules\ActorScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class W2P_Actor_Deduplicator {

	/**
	 * Gfriends client instance.
	 *
	 * @var W2P_Gfriends_Client
	 */
	protected $gf;

	/**
	 * Matcher instance.
	 *
	 * @var W2P_Actor_Matcher
	 */
	protected $matcher;

	/**
	 * Constructor.
	 *
	 * @param W2P_Gfriends_Client $gf      Gfriends client.
	 * @param W2P_Actor_Matcher   $matcher Matcher instance.
	 */
	public function __construct( W2P_Gfriends_Client $gf, W2P_Actor_Matcher $matcher ) {
		$this->gf      = $gf;
		$this->matcher = $matcher;
	}

	/**
	 * Step 1: Scan all Humans terms and identify duplicate clusters.
	 *
	 * @return array List of duplicate clusters: [ [ 'winner' => WP_Term, 'duplicates' => WP_Term[], 'type' => string ], ... ]
	 */
	public function scan_duplicates() {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 );
		}

		$this->matcher->load_terms( true );

		$all_terms = get_terms(
			array(
				'taxonomy'   => 'humans',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $all_terms ) || ! is_array( $all_terms ) ) {
			return array();
		}

		$term_infos = array();
		foreach ( $all_terms as $t ) {
			$role   = (string) get_term_meta( $t->term_id, 'human_role', true );
			$nick   = (string) get_term_meta( $t->term_id, 'human_nickname', true );
			$avatar = (int) get_term_meta( $t->term_id, 'human_avatar', true );

			$aliases = array( $t->name );
			if ( '' !== $nick ) {
				$parts = preg_split( '/[\s,，、;；\/|]+/u', $nick );
				foreach ( $parts as $p ) {
					$p = trim( $p );
					if ( '' !== $p && ! in_array( $p, $aliases, true ) ) {
						$aliases[] = $p;
					}
				}
			}

			// Also add description aliases if present
			if ( ! empty( $t->description ) ) {
				$desc_parts = preg_split( '/[\s,，、;；\/|]+/u', $t->description );
				foreach ( $desc_parts as $dp ) {
					$dp = trim( $dp );
					if ( '' !== $dp && mb_strlen( $dp ) <= 10 && ! in_array( $dp, $aliases, true ) ) {
						$aliases[] = $dp;
					}
				}
			}

			$term_infos[ $t->term_id ] = array(
				'term'           => $t,
				'term_id'        => (int) $t->term_id,
				'name'           => $t->name,
				'slug'           => $t->slug,
				'count'          => (int) $t->count,
				'role'           => $role,
				'nick'           => $nick,
				'aliases'        => $aliases,
				'avatar'         => $avatar,
				'japanese_score' => $this->matcher->japanese_score( $t->name ),
				'norm_key'       => $this->matcher->normalize_key( $t->name ),
			);
		}

		$clusters = array();
		$visited  = array();

		// Cluster Strategy A: Exact Normalized Name Matches
		$by_norm = array();
		foreach ( $term_infos as $tid => $info ) {
			$k = $info['norm_key'];
			if ( '' === $k ) {
				continue;
			}
			if ( ! isset( $by_norm[ $k ] ) ) {
				$by_norm[ $k ] = array();
			}
			$by_norm[ $k ][] = $tid;
		}

		foreach ( $by_norm as $key => $tids ) {
			if ( count( $tids ) > 1 ) {
				$cluster_terms = array();
				foreach ( $tids as $tid ) {
					$cluster_terms[] = $term_infos[ $tid ];
					$visited[ $tid ] = true;
				}
				$clusters[] = array(
					'type'  => 'exact_name',
					'terms' => $cluster_terms,
				);
			}
		}

		// Cluster Strategy B: Japanese / Chinese Aliases & Same-Person Matching
		$remaining_tids = array_diff( array_keys( $term_infos ), array_keys( $visited ) );
		$unclustered    = array();
		foreach ( $remaining_tids as $tid ) {
			$unclustered[] = $term_infos[ $tid ];
		}

		for ( $i = 0; $i < count( $unclustered ); $i++ ) {
			$info_a = $unclustered[ $i ];
			if ( isset( $visited[ $info_a['term_id'] ] ) ) {
				continue;
			}

			$group = array( $info_a );

			for ( $j = $i + 1; $j < count( $unclustered ); $j++ ) {
				$info_b = $unclustered[ $j ];
				if ( isset( $visited[ $info_b['term_id'] ] ) ) {
					continue;
				}

				if ( $this->are_terms_same_person( $info_a, $info_b ) ) {
					$group[]                      = $info_b;
					$visited[ $info_b['term_id'] ] = true;
				}
			}

			if ( count( $group ) > 1 ) {
				$visited[ $info_a['term_id'] ] = true;
				$clusters[]                    = array(
					'type'  => 'alias_person',
					'terms' => $group,
				);
			}
		}

		// Structure final resolution for each cluster
		$resolved_clusters = array();
		foreach ( $clusters as $c ) {
			$selection            = $this->select_winner( $c['terms'] );
			$resolved_clusters[] = array(
				'type'       => $c['type'],
				'winner'     => $selection['winner'],
				'duplicates' => $selection['duplicates'],
				'all_terms'  => $c['terms'],
				'plan'       => $selection['plan'],
			);
		}

		return $resolved_clusters;
	}

	/**
	 * Check if two term info structures represent the SAME actress.
	 *
	 * @param array $a Term info A.
	 * @param array $b Term info B.
	 * @return bool
	 */
	protected function are_terms_same_person( $a, $b ) {
		// 1. Direct name match via are_same_person.
		if ( $this->matcher->are_same_person( $a['name'], $b['name'] ) ) {
			return true;
		}

		// 2. Cross-alias check: does A contain B's name/alias, or B contain A's name/alias?
		foreach ( $a['aliases'] as $alias_a ) {
			if ( '' === trim( $alias_a ) ) {
				continue;
			}
			$norm_a = $this->matcher->normalize_key( $alias_a );
			foreach ( $b['aliases'] as $alias_b ) {
				if ( '' === trim( $alias_b ) ) {
					continue;
				}
				$norm_b = $this->matcher->normalize_key( $alias_b );
				if ( $norm_a === $norm_b || $this->matcher->are_same_person( $alias_a, $alias_b ) ) {
					return true;
				}
			}
		}

		// 3. Gfriends actor index exact match: do both map to the same Gfriends official actress?
		$gf_a = ! empty( $this->gf->get_actor( $a['name'] ) ) ? $a['name'] : '';
		$gf_b = ! empty( $this->gf->get_actor( $b['name'] ) ) ? $b['name'] : '';
		if ( '' !== $gf_a && '' !== $gf_b && $gf_a === $gf_b ) {
			return true;
		}

		return false;
	}

	/**
	 * Step 2: Determine winner term and prepare migration plan.
	 *
	 * @param array $terms List of term info arrays in cluster.
	 * @return array [ 'winner' => array, 'duplicates' => array[], 'plan' => array ]
	 */
	public function select_winner( array $terms ) {
		usort(
			$terms,
			static function ( $a, $b ) {
				// 1. Japanese Score
				if ( $a['japanese_score'] !== $b['japanese_score'] ) {
					return $b['japanese_score'] <=> $a['japanese_score'];
				}
				// 2. Avatar
				$a_has_avatar = ( $a['avatar'] > 0 ) ? 1 : 0;
				$b_has_avatar = ( $b['avatar'] > 0 ) ? 1 : 0;
				if ( $a_has_avatar !== $b_has_avatar ) {
					return $b_has_avatar <=> $a_has_avatar;
				}
				// 3. Post Count
				if ( $a['count'] !== $b['count'] ) {
					return $b['count'] <=> $a['count'];
				}
				// 4. Smaller ID
				return $a['term_id'] <=> $b['term_id'];
			}
		);

		$winner     = $terms[0];
		$duplicates = array_slice( $terms, 1 );

		// Aggregate aliases, roles, avatar
		$all_nicknames = $winner['aliases'];
		$all_roles     = ! empty( $winner['role'] ) ? preg_split( '/[\s,，、;；\/|]+/u', $winner['role'] ) : array( '演员' );
		$best_avatar   = $winner['avatar'];

		foreach ( $duplicates as $dup ) {
			// Merge aliases
			foreach ( $dup['aliases'] as $al ) {
				if ( '' !== $al && ! in_array( $al, $all_nicknames, true ) ) {
					$all_nicknames[] = $al;
				}
			}
			// Merge roles
			if ( ! empty( $dup['role'] ) ) {
				$dup_roles = preg_split( '/[\s,，、;；\/|]+/u', $dup['role'] );
				foreach ( $dup_roles as $dr ) {
					if ( '' !== $dr && ! in_array( $dr, $all_roles, true ) ) {
						$all_roles[] = $dr;
					}
				}
			}
			// Avatar fallback
			if ( ! $best_avatar && $dup['avatar'] > 0 ) {
				$best_avatar = $dup['avatar'];
			}
		}

		// Filter out winner's primary name from nicknames
		$final_nicknames = array();
		foreach ( $all_nicknames as $al ) {
			if ( $al !== $winner['name'] && ! in_array( $al, $final_nicknames, true ) ) {
				$final_nicknames[] = $al;
			}
		}

		return array(
			'winner'     => $winner,
			'duplicates' => $duplicates,
			'plan'       => array(
				'winner_id'       => $winner['term_id'],
				'winner_name'     => $winner['name'],
				'duplicate_ids'   => wp_list_pluck( $duplicates, 'term_id' ),
				'duplicate_names' => wp_list_pluck( $duplicates, 'name' ),
				'merged_nickname' => implode( ', ', $final_nicknames ),
				'merged_role'     => implode( ',', array_filter( array_unique( $all_roles ) ) ),
				'merged_avatar'   => $best_avatar,
			),
		);
	}

	/**
	 * Step 3 & 4: Execute merge for a single cluster (High performance batch SQL).
	 *
	 * @param array $cluster Resolved cluster.
	 * @return array
	 */
	public function merge_cluster( array $cluster ) {
		global $wpdb;

		$plan      = isset( $cluster['plan'] ) ? $cluster['plan'] : $cluster;
		$winner_id = (int) $plan['winner_id'];
		$dup_ids   = array_map( 'absint', $plan['duplicate_ids'] );
		$dup_ids   = array_values( array_filter( $dup_ids ) );

		if ( ! $winner_id || empty( $dup_ids ) ) {
			return array(
				'success'        => false,
				'winner_id'      => 0,
				'posts_migrated' => 0,
				'deleted_terms'  => 0,
			);
		}

		// 1. Update Winner Meta
		if ( ! empty( $plan['merged_nickname'] ) ) {
			update_term_meta( $winner_id, 'human_nickname', $plan['merged_nickname'] );
		}
		if ( ! empty( $plan['merged_role'] ) ) {
			update_term_meta( $winner_id, 'human_role', $plan['merged_role'] );
		}
		if ( ! empty( $plan['merged_avatar'] ) ) {
			update_term_meta( $winner_id, 'human_avatar', (int) $plan['merged_avatar'] );
		}

		$winner_term = get_term( $winner_id, 'humans' );
		if ( $winner_term && '' === trim( (string) $winner_term->description ) && ! empty( $plan['merged_nickname'] ) ) {
			wp_update_term( $winner_id, 'humans', array( 'description' => $plan['merged_nickname'] ) );
		}

		// 2. High-speed SQL: Get winner term_taxonomy_id and duplicate term_taxonomy_ids
		$winner_tt_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = 'humans' LIMIT 1",
				$winner_id
			)
		);

		if ( ! $winner_tt_id ) {
			return array(
				'success'        => false,
				'winner_id'      => $winner_id,
				'posts_migrated' => 0,
				'deleted_terms'  => 0,
			);
		}

		$dup_placeholders = implode( ',', array_fill( 0, count( $dup_ids ), '%d' ) );
		$dup_tt_ids       = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'humans' AND term_id IN ({$dup_placeholders})",
				$dup_ids
			)
		);
		$dup_tt_ids       = array_map( 'absint', $dup_tt_ids );

		$posts_migrated = 0;
		if ( ! empty( $dup_tt_ids ) ) {
			$tt_placeholders = implode( ',', array_fill( 0, count( $dup_tt_ids ), '%d' ) );

			// Find distinct objects
			$affected_posts = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ({$tt_placeholders})",
					$dup_tt_ids
				)
			);
			$posts_migrated = count( $affected_posts );

			if ( $posts_migrated > 0 ) {
				// Re-link objects to winner term_taxonomy_id (INSERT IGNORE)
				$insert_values = array();
				foreach ( $affected_posts as $p_id ) {
					$insert_values[] = $wpdb->prepare( '(%d, %d, 0)', $p_id, $winner_tt_id );
				}
				$wpdb->query( "INSERT IGNORE INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id, term_order) VALUES " . implode( ',', $insert_values ) ); // phpcs:ignore WordPress.DB.PreparedSQL

				// Delete relationships with duplicate tt_ids
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ({$tt_placeholders})",
						$dup_tt_ids
					)
				);
			}
		}

		// 3. Delete Duplicate Terms
		$deleted_count = 0;
		foreach ( $dup_ids as $did ) {
			$res = wp_delete_term( $did, 'humans' );
			if ( $res && ! is_wp_error( $res ) ) {
				$deleted_count++;
			}
		}

		// 4. Update Winner Count
		wp_update_term_count_now( array( $winner_id ), 'humans' );
		clean_term_cache( $winner_id, 'humans' );

		return array(
			'success'        => true,
			'winner_id'      => $winner_id,
			'winner_name'    => $plan['winner_name'],
			'posts_migrated' => $posts_migrated,
			'deleted_terms'  => $deleted_count,
			'deleted_names'  => isset( $plan['duplicate_names'] ) ? $plan['duplicate_names'] : array(),
		);
	}

	/**
	 * Merge all duplicate clusters in batch.
	 *
	 * @return array
	 */
	public function merge_all() {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 );
		}

		$clusters = $this->scan_duplicates();
		$stats    = array(
			'clusters_merged' => 0,
			'posts_migrated'  => 0,
			'deleted_terms'   => 0,
			'log'             => array(),
		);

		foreach ( $clusters as $cluster ) {
			$res = $this->merge_cluster( $cluster );
			if ( ! empty( $res['success'] ) ) {
				$stats['clusters_merged']++;
				$stats['posts_migrated'] += $res['posts_migrated'];
				$stats['deleted_terms']  += $res['deleted_terms'];
				$stats['log'][]           = $res;
			}
		}

		return $stats;
	}
}
