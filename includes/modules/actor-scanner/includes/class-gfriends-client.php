<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gfriends data source client
 *
 * Fetches and caches the Gfriends Filetree.json index (actors + avatar files)
 * from the remote GitHub repository, and provides lookup / avatar-URL helpers.
 *
 * @package WP_Genius
 * @subpackage Modules\ActorScanner
 */
class W2P_Gfriends_Client {

	/**
	 * Remote file tree URL (compressed to save bandwidth).
	 */
	const FILE_TREE_URL = 'https://raw.githubusercontent.com/gfriends/gfriends/master/Filetree.json';

	/**
	 * Base URL of the avatar content directory (jsDelivr CDN mirror).
	 */
	const CONTENT_BASE_CDN = 'https://cdn.jsdelivr.net/gh/gfriends/gfriends@master/Content/';

	/**
	 * Base URL of the avatar content directory (raw GitHub).
	 */
	const CONTENT_BASE_RAW = 'https://raw.githubusercontent.com/gfriends/gfriends/master/Content/';

	/**
	 * Transient key prefix for cached data.
	 */
	const CACHE_PREFIX = 'w2p_gfriends_';

	/**
	 * Cache lifetime in seconds (default 24 hours).
	 *
	 * @var int
	 */
	protected $cache_ttl = DAY_IN_SECONDS;

	/**
	 * Raw Filetree content (lazy-loaded).
	 *
	 * @var array|null
	 */
	protected $filetree = null;

	/**
	 * Built actor index (name => entries).
	 *
	 * @var array|null
	 */
	protected $actor_index = null;

	/**
	 * First-character lookup buckets.
	 *
	 * @var array|null
	 */
	protected $char_index = null;

	/**
	 * Constructor.
	 *
	 * @param int $cache_ttl Optional cache lifetime in seconds.
	 */
	public function __construct( $cache_ttl = 0 ) {
		if ( $cache_ttl > 0 ) {
			$this->cache_ttl = (int) $cache_ttl;
		}
	}

	/**
	 * Get the full Filetree (cached). Structure:
	 * [ 'Information' => [...], 'Content' => [ Company => [ 'ActorName.jpg' => 'file?t=ts', ... ], ... ] ]
	 *
	 * @param bool $force Refresh the cache.
	 * @return array
	 */
	public function get_filetree( $force = false ) {
		if ( null !== $this->filetree ) {
			return $this->filetree;
		}

		$cache_key = self::CACHE_PREFIX . 'filetree';
		$data      = $force ? false : get_transient( $cache_key );

		if ( false === $data || ! is_array( $data ) ) {
			$data = $this->fetch_filetree();
			if ( ! empty( $data ) ) {
				set_transient( $cache_key, $data, $this->cache_ttl );
			}
		}

		$this->filetree = is_array( $data ) ? $data : array();
		return $this->filetree;
	}

	/**
	 * Download and parse Filetree.json from the remote repository.
	 *
	 * @return array
	 */
	protected function fetch_filetree() {
		$response = wp_remote_get(
			self::FILE_TREE_URL,
			array(
				'timeout'    => 60,
				'user-agent' => 'WP-Genius-ActorScanner/1.0',
				'headers'    => array( 'Accept-Encoding' => 'gzip' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			W2P_Logger::error( 'Gfriends Filetree fetch failed: ' . ( is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_code( $response ) ), 'actor-scanner' );
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data['Content'] ) ) {
			W2P_Logger::error( 'Gfriends Filetree parse failed (invalid JSON or missing Content).', 'actor-scanner' );
			return array();
		}

		return $data;
	}

	/**
	 * Build (and cache) a normalized actor index from the Filetree.
	 *
	 * Each actor maps to a list of entries: [ 'company' => string, 'file' => string, 'url' => string, 'priority' => int ].
	 * The index is stored as a JSON file under wp-content/uploads/w2p-actor-scanner/
	 * (it is far too large for a transient/options row).
	 *
	 * @param bool $force Refresh cache.
	 * @return array
	 */
	public function get_actor_index( $force = false ) {
		if ( null !== $this->actor_index ) {
			return $this->actor_index;
		}

		$index_file = $this->index_file_path();

		if ( ! $force && file_exists( $index_file ) ) {
			$age = time() - filemtime( $index_file );
			if ( $age < $this->cache_ttl ) {
				$json = file_get_contents( $index_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				$data = json_decode( $json, true );
				if ( is_array( $data ) ) {
					$this->actor_index = $data;
					return $data;
				}
			}
		}

		$index = $this->build_actor_index();
		if ( ! empty( $index ) ) {
			$this->save_index_file( $index );
		}

		$this->actor_index = $index;
		return $index;
	}

	/**
	 * Absolute path of the cached actor index JSON file.
	 *
	 * @return string
	 */
	protected function index_file_path() {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . 'w2p-actor-scanner/gfriends-index.json';
	}

	/**
	 * Persist the actor index to disk.
	 *
	 * @param array $index Actor index.
	 * @return void
	 */
	protected function save_index_file( $index ) {
		$file = $this->index_file_path();
		$dir  = dirname( $file );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions
		file_put_contents( $file, wp_json_encode( $index, JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Build actor index from Filetree: actor display name => avatar entries.
	 *
	 * @return array
	 */
	protected function build_actor_index() {
		$filetree = $this->get_filetree();
		$content  = isset( $filetree['Content'] ) ? $filetree['Content'] : array();
		$index    = array();

		foreach ( $content as $company => $files ) {
			$priority = $this->company_priority( $company );

			foreach ( $files as $file => $value ) {
				$real_file = $this->resolve_real_file( $file, $value );
				$name      = $this->actor_name_from_file( $real_file );

				if ( '' === $name ) {
					continue;
				}

				if ( ! isset( $index[ $name ] ) ) {
					$index[ $name ] = array();
				}

				$index[ $name ][] = array(
					'company'  => $company,
					'file'     => $real_file,
					'url'      => $this->avatar_url( $company, $real_file ),
					'priority' => $priority,
				);
			}
		}

		// Sort entries per actor: best (lowest priority value) first.
		foreach ( $index as $name => $entries ) {
			usort(
				$entries,
				static function ( $a, $b ) {
					return $a['priority'] <=> $b['priority'];
				}
			);
			$index[ $name ] = $entries;
		}

		return $index;
	}

	/**
	 * Resolve the actual avatar file name from a Filetree entry.
	 *
	 * @param string $key   Filetree key.
	 * @param string $value Filetree value.
	 * @return string
	 */
	protected function resolve_real_file( $key, $value ) {
		$real = $value;
		// Strip cache-busting query.
		$real = preg_replace( '/\?t=\d+$/', '', $real );
		if ( '' === $real ) {
			$real = $key;
		}
		return $real;
	}

	/**
	 * Extract actor name from an avatar file name (strip ext / AI-Fix prefix / trailing -N).
	 *
	 * @param string $file File name.
	 * @return string
	 */
	public function actor_name_from_file( $file ) {
		$name = basename( $file );
		$name = preg_replace( '/\.(jpe?g|png|webp|gif)$/i', '', $name );
		$name = preg_replace( '/^AI-Fix-/', '', $name );
		return trim( $name );
	}

	/**
	 * Compute a priority for a company directory (lower is better).
	 *
	 * @param string $company Company directory name.
	 * @return int
	 */
	protected function company_priority( $company ) {
		$company = trim( $company );

		if ( false !== strpos( $company, 'HandStorage' ) || false !== strpos( $company, '人工' ) ) {
			return 0;
		}

		// Numeric-prefixed official studios: 0-9.
		if ( preg_match( '/^\d/', $company ) ) {
			return 1;
		}

		// Letter-prefixed official studios (a-z).
		if ( preg_match( '/^[a-zA-Z]/', $company ) ) {
			return 2;
		}

		// z-/y-/x-/v-/u- aggregator prefixes (lower priority, in order).
		$prefix_order = array(
			'u-' => 6,
			'v-' => 5,
			'x-' => 4,
			'y-' => 3,
			'z-' => 2,
		);
		foreach ( $prefix_order as $prefix => $score ) {
			if ( 0 === strpos( $company, $prefix ) ) {
				return $score + 10; // Keep behind official sources.
			}
		}

		return 10;
	}

	/**
	 * Build an avatar content URL for a company + file.
	 *
	 * @param string $company Company directory.
	 * @param string $file    Avatar file name.
	 * @param bool   $cdn     Use the CDN mirror (default true).
	 * @return string
	 */
	public function avatar_url( $company, $file, $cdn = true ) {
		$base = $cdn ? self::CONTENT_BASE_CDN : self::CONTENT_BASE_RAW;
		return $base . rawurlencode( $company ) . '/' . rawurlencode( $file );
	}

	/**
	 * Get avatar entries for an exact actor name.
	 *
	 * @param string $name Exact actor name.
	 * @return array
	 */
	public function get_actor( $name ) {
		$index = $this->get_actor_index();
		return isset( $index[ $name ] ) ? $index[ $name ] : array();
	}

	/**
	 * Get the best avatar URL for an actor.
	 *
	 * @param string $name Actor name.
	 * @param bool   $cdn  Prefer CDN mirror.
	 * @return string
	 */
	public function get_best_avatar_url( $name, $cdn = true ) {
		$entries = $this->get_actor( $name );
		if ( empty( $entries ) ) {
			return '';
		}
		$best = $entries[0];
		return $this->avatar_url( $best['company'], $best['file'], $cdn );
	}

	/**
	 * Count of actors in the index.
	 *
	 * @return int
	 */
	public function count_actors() {
		return count( $this->get_actor_index() );
	}

	/**
	 * Build a first-character → names lookup to narrow fuzzy matching.
	 *
	 * @return array
	 */
	public function get_char_index() {
		if ( null !== $this->char_index ) {
			return $this->char_index;
		}
		$index = array();
		foreach ( array_keys( $this->get_actor_index() ) as $name ) {
			$first = mb_substr( $name, 0, 1 );
			if ( ! isset( $index[ $first ] ) ) {
				$index[ $first ] = array();
			}
			$index[ $first ][] = $name;
		}
		$this->char_index = $index;
		return $index;
	}

	/**
	 * Fuzzy-search actor names for a query (Chinese/Japanese name guess).
	 *
	 * Uses a first-character bucket + sequence similarity; returns candidates
	 * sorted by score (best first). The score combines character-sequence
	 * similarity with a shared-prefix bonus.
	 *
	 * @param string $query Query name (e.g. simplified/traditional Chinese name).
	 * @param int    $limit Max results.
	 * @param float  $min_score Minimum score to include (0..1).
	 * @return array list of [ 'name' => string, 'score' => float ]
	 */
	public function fuzzy_search( $query, $limit = 5, $min_score = 0.5 ) {
		$query = trim( $query );
		if ( '' === $query ) {
			return array();
		}

		$char_index = $this->get_char_index();
		$first      = mb_substr( $query, 0, 1 );

		// Candidate pool: names sharing the first character (or exact match bucket fallback).
		$pool = isset( $char_index[ $first ] ) ? $char_index[ $first ] : array();

		// Also scan a broader pool when the bucket is tiny.
		if ( count( $pool ) < 3 ) {
			$pool = array_keys( $this->get_actor_index() );
		}

		$results   = array();
		$query_len = mb_strlen( $query );

		foreach ( $pool as $name ) {
			// Exact normalized match is handled elsewhere; still cheap to keep.
			$score = $this->similarity_score( $query, $name );
			if ( $score >= $min_score ) {
				$results[] = array(
					'name'  => $name,
					'score' => $score,
				);
			}
		}

		usort(
			$results,
			static function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);

		return array_slice( $results, 0, $limit );
	}

	/**
	 * Compute a similarity score between two names (0..1, prefix-boosted).
	 *
	 * @param string $a Name A.
	 * @param string $b Name B.
	 * @return float
	 */
	public function similarity_score( $a, $b ) {
		$a = mb_strtolower( trim( $a ) );
		$b = mb_strtolower( trim( $b ) );

		if ( '' === $a || '' === $b ) {
			return 0.0;
		}
		if ( $a === $b ) {
			return 1.0;
		}

		// Character bigram overlap (robust for CJK without a mapping table).
		$bigrams_a = $this->bigrams( $a );
		$bigrams_b = $this->bigrams( $b );

		$inter = count( array_intersect( $bigrams_a, $bigrams_b ) );
		$union = count( array_unique( array_merge( $bigrams_a, $bigrams_b ) ) );

		$score = $union > 0 ? ( $inter / $union ) : 0.0;

		// Shared-prefix bonus: names that start with the same 2 chars are usually
		// the same actress in CJK (三上悠…). Add up to +0.35.
		$pref_a = mb_substr( $a, 0, 2 );
		$pref_b = mb_substr( $b, 0, 2 );
		if ( $pref_a === $pref_b && mb_strlen( $pref_a ) === 2 ) {
			$score += 0.35;
		}

		return min( 1.0, $score );
	}

	/**
	 * Character bigrams of a string (for similarity).
	 *
	 * @param string $str Input string.
	 * @return array
	 */
	protected function bigrams( $str ) {
		$len = mb_strlen( $str );
		$out = array();
		for ( $i = 0; $i < $len - 1; $i++ ) {
			$out[] = mb_substr( $str, $i, 2 );
		}
		return $out;
	}
}
