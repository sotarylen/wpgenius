<?php
/**
 * Actor Matcher & Candidate Resolver (Ponytail Clean Edition)
 *
 * Core engine for extracting, scoring, clustering, and strictly validating
 * actress names from post content first line against existing Humans terms
 * and the Gfriends official index.
 *
 * @package WP_Genius
 * @subpackage Modules\ActorScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class W2P_Actor_Matcher {

	/**
	 * Gfriends client instance.
	 *
	 * @var W2P_Gfriends_Client
	 */
	protected $gf;

	/**
	 * Existing Humans taxonomy terms indexed by term_id.
	 *
	 * @var WP_Term[]|null
	 */
	protected $terms = null;

	/**
	 * Normalized lookup: normalized string => term_id.
	 *
	 * @var array|null
	 */
	protected $term_alias_map = null;

	/**
	 * Constructor.
	 *
	 * @param W2P_Gfriends_Client $gf Gfriends client.
	 */
	public function __construct( W2P_Gfriends_Client $gf ) {
		$this->gf = $gf;
	}

	/**
	 * Load and cache all existing Humans terms into lookup maps.
	 *
	 * @param bool $force Force reload.
	 * @return void
	 */
	public function load_terms( $force = false ) {
		if ( null !== $this->terms && ! $force ) {
			return;
		}

		$all_terms = get_terms(
			array(
				'taxonomy'   => 'humans',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $all_terms ) || ! is_array( $all_terms ) ) {
			$all_terms = array();
		}

		$this->terms          = array();
		$this->term_alias_map = array();

		foreach ( $all_terms as $term ) {
			$this->terms[ $term->term_id ] = $term;

			// Primary term name.
			$this->register_alias( $this->term_alias_map, $term->name, $term->term_id );

			// Slug (URL-decoded).
			$decoded_slug = rawurldecode( $term->slug );
			$this->register_alias( $this->term_alias_map, $decoded_slug, $term->term_id );

			// Description aliases (only if concise name list, not long biographical text).
			if ( ! empty( $term->description ) && mb_strlen( $term->description ) <= 100 ) {
				$desc_aliases = preg_split( '/[\s,，、;；\/|]+/u', $term->description );
				$count        = 0;
				foreach ( $desc_aliases as $da ) {
					if ( $count >= 10 ) {
						break;
					}
					if ( $this->register_alias( $this->term_alias_map, $da, $term->term_id ) ) {
						++$count;
					}
				}
			}

			// human_nickname term meta aliases.
			$nickname = (string) get_term_meta( $term->term_id, 'human_nickname', true );
			if ( '' !== $nickname && mb_strlen( $nickname ) <= 150 ) {
				$aliases = preg_split( '/[\s,，、;；\/|]+/u', $nickname );
				$count   = 0;
				foreach ( $aliases as $alias ) {
					if ( $count >= 10 ) {
						break;
					}
					if ( $this->register_alias( $this->term_alias_map, $alias, $term->term_id ) ) {
						++$count;
					}
				}
			}
		}
	}

	/**
	 * Register an alias in the lookup map with strict validation.
	 *
	 * @param array  $map     Alias map (by reference).
	 * @param string $alias   Alias string.
	 * @param int    $term_id Term ID.
	 * @return bool True if registered.
	 */
	protected function register_alias( &$map, $alias, $term_id ) {
		$alias = trim( (string) $alias );
		// Guard: length between 2 and 20 chars
		$len = mb_strlen( $alias );
		if ( $len < 2 || $len > 20 ) {
			return false;
		}

		// Guard: no URLs, HTML, or sentence punctuation
		if ( preg_match( '/(?:https?:\/\/|\.com|\.cn|\.net|<|>|\[|\]|[。！？?！，,;；:：])/ui', $alias ) ) {
			return false;
		}

		$key = $this->normalize_key( $alias );
		if ( '' === $key || mb_strlen( $key ) < 2 || mb_strlen( $key ) > 20 ) {
			return false;
		}

		if ( ! isset( $map[ $key ] ) ) {
			$map[ $key ] = $term_id;
			return true;
		}

		// Collision resolution: prefer '演员' role over other roles.
		$existing       = isset( $this->terms[ $map[ $key ] ] ) ? $this->terms[ $map[ $key ] ] : null;
		$candidate      = isset( $this->terms[ $term_id ] ) ? $this->terms[ $term_id ] : null;
		$existing_role  = $existing ? (string) get_term_meta( $existing->term_id, 'human_role', true ) : '';
		$candidate_role = $candidate ? (string) get_term_meta( $candidate->term_id, 'human_role', true ) : '';
		if ( false !== strpos( $candidate_role, '演员' ) && false === strpos( $existing_role, '演员' ) ) {
			$map[ $key ] = $term_id;
		}
		return true;
	}

	/**
	 * Normalize a name into a comparable key.
	 *
	 * @param string $name Raw name.
	 * @return string
	 */
	public function normalize_key( $name ) {
		$name = (string) $name;
		$name = preg_replace( '/[\x{200B}-\x{200D}\x{FEFF}\s]+/u', '', $name );
		$name = mb_convert_kana( $name, 'aKV' );
		$name = preg_replace( '/[（(].*?[)）]/u', '', $name );
		$name = preg_replace( '/[！!？?。．·、，,：:；;]/u', '', $name );
		$name = mb_strtolower( $name );
		return trim( $name );
	}

	/**
	 * Extract the first non-empty text line from HTML content.
	 *
	 * @param string $html HTML or text content.
	 * @return string
	 */
	public function get_first_text_line( $html ) {
		if ( empty( $html ) || ! is_string( $html ) ) {
			return '';
		}

		// Replace block tags with newline to respect line structure.
		$text = preg_replace( '/<\/(?:div|p|h[1-6]|li|blockquote|tr|table|section|article)>/iu', "\n", $html );
		$text = preg_replace( '/<(?:br|hr)\s*\/?>/iu', "\n", $text );
		$text = strip_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		$lines = preg_split( '/[\r\n]+/u', $text );
		foreach ( $lines as $line ) {
			$trimmed = trim( preg_replace( '/[\x{200B}-\x{200D}\x{FEFF}\s]+/u', ' ', $line ) );
			if ( '' !== $trimmed ) {
				return $trimmed;
			}
		}

		return '';
	}

	/**
	 * Clean leading metadata prefix from line text (e.g. 女優名：, 主演：, 【女优】).
	 *
	 * @param string $line Raw line text.
	 * @return string
	 */
	public function clean_line_prefix( $line ) {
		return trim( preg_replace( '/^[\s\x{200B}-\x{200D}\x{FEFF}]*【?(?:女優名|女优名|女優|女优|主演|演員|演员|人物|女星|模特|MODEL|ACTRESS|CAST|出演)】?[\s:：、,，-]*/ui', '', $line ) );
	}

	/**
	 * Whether a string contains kana or Japanese marks.
	 *
	 * @param string $name Candidate.
	 * @return bool
	 */
	public function is_japanese( $name ) {
		return (bool) preg_match( '/[\x{3040}-\x{30FF}\x{3005}\x{3006}\x{3007}\x{30FC}\x{30FB}]/u', $name );
	}

	/**
	 * Whether a string contains at least one CJK kanji character or iteration mark.
	 *
	 * @param string $name Candidate.
	 * @return bool
	 */
	public function has_kanji( $name ) {
		return (bool) preg_match( '/[\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}\x{3005}\x{3006}\x{3007}]/u', $name );
	}

	/**
	 * Compute Japanese priority score for a name candidate.
	 * Higher score means stronger Japanese official name indication.
	 *
	 * @param string $name Candidate name.
	 * @return int
	 */
	public function japanese_score( $name ) {
		$score = 0;
		$name  = trim( (string) $name );
		if ( '' === $name ) {
			return 0;
		}

		// 1. Kana (hiragana/katakana) is the absolute strongest signal (+100).
		if ( preg_match( '/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}]/u', $name ) ) {
			$score += 100;
		}

		// 2. Iteration mark 々, 〆, 〇 or long sound mark ー (+50).
		if ( preg_match( '/[\x{3005}\x{3006}\x{3007}\x{30FC}\x{30FB}]/u', $name ) ) {
			$score += 50;
		}

		// 3. Known in Gfriends official index (+40).
		if ( ! empty( $this->gf->get_actor( $name ) ) ) {
			$score += 40;
		}

		// 4. Common Japanese Shinjitai kanji (+10).
		$shinjitai = array( '実', '桜', '絵', '咲', '亜', '恵', '真', '竜', '黒', '広', '沢', '渋', '浜', '滝', '瀬', '辺', '斉', '斎', '穂', '乃', '奈', '莉', '萌', '葵', '栞', '凛', '結', '衣', '美', '佳', '優', '香', '綾', '愛', '沙', '菜', '楓', '柚', '澪' );
		foreach ( $shinjitai as $char ) {
			if ( false !== mb_strpos( $name, $char ) ) {
				$score += 10;
				break;
			}
		}

		// 5. Pure Romaji / English (-10).
		if ( preg_match( '/^[A-Za-z0-9\s_-]+$/', $name ) ) {
			$score -= 10;
		}

		return $score;
	}

	/**
	 * Check if two names are likely aliases of the SAME person.
	 *
	 * @param string $a Name A.
	 * @param string $b Name B.
	 * @return bool
	 */
	public function are_same_person( $a, $b ) {
		$a = trim( (string) $a );
		$b = trim( (string) $b );
		if ( '' === $a || '' === $b || $a === $b ) {
			return true;
		}

		// 1. Existing Term check: if both map to the same term ID.
		$key_a = $this->normalize_key( $a );
		$key_b = $this->normalize_key( $b );
		if ( isset( $this->term_alias_map[ $key_a ] ) && isset( $this->term_alias_map[ $key_b ] ) ) {
			if ( $this->term_alias_map[ $key_a ] === $this->term_alias_map[ $key_b ] ) {
				return true;
			}
		}

		// 2. Gfriends check: if either is a fuzzy match or exact match of the other.
		$sim = $this->gf->similarity_score( $a, $b );
		if ( $sim >= 0.55 ) {
			return true;
		}

		// 3. Expand iteration mark 々 (e.g. 八神七々実 -> 八神七七実).
		$norm_a = preg_replace_callback(
			'/(.)々/u',
			static function ( $m ) {
				return $m[1] . $m[1];
			},
			$a
		);
		$norm_b = preg_replace_callback(
			'/(.)々/u',
			static function ( $m ) {
				return $m[1] . $m[1];
			},
			$b
		);

		if ( $norm_a === $norm_b || $this->gf->similarity_score( $norm_a, $norm_b ) >= 0.55 ) {
			return true;
		}

		// 4. Shared 2+ character surname and similar length (e.g. 八神七々実 vs 八神七七實).
		$pref_a = mb_substr( $a, 0, 2 );
		$pref_b = mb_substr( $b, 0, 2 );
		if ( $pref_a === $pref_b && abs( mb_strlen( $a ) - mb_strlen( $b ) ) <= 1 && mb_strlen( $a ) <= 6 && mb_strlen( $b ) <= 6 ) {
			return true;
		}

		return false;
	}

	/**
	 * Resolve a candidate name against existing Humans terms or Gfriends official index.
	 *
	 * @param string $name Candidate name.
	 * @return array [ 'term_id', 'source', 'gf_name', 'score' ]
	 */
	public function resolve( $name ) {
		$this->load_terms();

		$result = array(
			'term_id' => 0,
			'source'  => 'none',
			'gf_name' => '',
			'score'   => 0.0,
		);

		$key = $this->normalize_key( $name );
		if ( '' === $key ) {
			return $result;
		}

		// 1. Existing Humans term (exact / alias).
		if ( isset( $this->term_alias_map[ $key ] ) ) {
			$result['term_id'] = $this->term_alias_map[ $key ];
			$result['source']  = 'term';
			$result['score']   = 1.0;
			return $result;
		}

		// 2. Gfriends exact match.
		$gf_actor = $this->gf->get_actor( $name );
		if ( ! empty( $gf_actor ) ) {
			$result['source']  = 'gfriends_exact';
			$result['gf_name'] = $name;
			$result['score']   = 1.0;
			return $result;
		}

		// 3. Gfriends fuzzy search (Chinese / Romaji name -> Japanese official name).
		if ( mb_strlen( $name ) >= 2 && mb_strlen( $name ) <= 8 ) {
			$hits = $this->gf->fuzzy_search( $name, 1, 0.65 );
			if ( ! empty( $hits ) ) {
				$top               = $hits[0];
				$result['source']  = 'gfriends_fuzzy';
				$result['gf_name'] = $top['name'];
				$result['score']   = $top['score'];
				return $result;
			}
		}

		return $result;
	}

	/**
	 * Extract actress candidate(s) specifically from the first line of content.
	 *
	 * @param string $content Post content.
	 * @param string $title   Optional post title fallback.
	 * @return array List of resolved actor structures.
	 */
	public function extract_from_first_line( $content, $title = '' ) {
		$this->load_terms();

		$line = $this->get_first_text_line( $content );
		if ( '' === $line && '' !== $title ) {
			$line = $this->get_first_text_line( $title );
		}
		if ( '' === $line ) {
			return array();
		}

		$cleaned = $this->clean_line_prefix( $line );
		if ( '' === $cleaned ) {
			$cleaned = $line;
		}

		// Normalize CJK internal spaces: "八神 七々実" -> "八神七々実"
		$normalized = preg_replace_callback(
			'/([\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}\x{3040}-\x{30FF}\x{3005}\x{3006}\x{3007}])\s+([\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}\x{3040}-\x{30FF}\x{3005}\x{3006}\x{3007}])/u',
			static function ( $m ) {
				return $m[1] . $m[2];
			},
			$cleaned
		);

		$clusters = array();

		// Case 1: Bracket pairs A(B) or A（B）
		if ( preg_match_all( '/([^\s（(\n,，\/／]+?)\s*[（(]([^)）\n]+)[)）]/u', $normalized, $b_matches, PREG_SET_ORDER ) ) {
			foreach ( $b_matches as $bm ) {
				$pair      = array( $bm[1] );
				$sub_inner = preg_split( '/[,\/|，、;；\/／&＋+\t\n]+/u', $bm[2] );
				foreach ( $sub_inner as $si ) {
					$si = trim( $si );
					if ( '' !== $si ) {
						$pair[] = $si;
					}
				}
				$clusters[] = $pair;
			}
		} else {
			// Case 2: Delimiters without brackets
			$sub_tokens = preg_split( '/[,\/|，、;；\/／&＋+\t\n]+/u', $normalized );
			$tokens     = array();
			foreach ( $sub_tokens as $t ) {
				$t = trim( preg_replace( '/[\x{200B}-\x{200D}\x{FEFF}]+/u', '', $t ) );
				if ( ! preg_match( '/^[A-Za-z0-9\s_-]+$/', $t ) ) {
					$t = preg_replace( '/\s+/u', '', $t );
				}
				if ( mb_strlen( $t ) >= 2 && ! in_array( $t, $tokens, true ) ) {
					$tokens[] = $t;
				}
			}

			// If exactly 2 tokens and one is Japanese and one is Chinese/Kanji, or they are aliases -> single person
			if ( count( $tokens ) === 2 ) {
				$t0_jp = $this->is_japanese( $tokens[0] );
				$t1_jp = $this->is_japanese( $tokens[1] );
				if ( ( $t0_jp && ! $t1_jp ) || ( ! $t0_jp && $t1_jp ) || $this->are_same_person( $tokens[0], $tokens[1] ) ) {
					$clusters[] = array( $tokens[0], $tokens[1] );
					$tokens     = array();
				}
			}

			foreach ( $tokens as $token ) {
				$placed = false;
				foreach ( $clusters as $c_idx => $cluster ) {
					if ( $this->are_same_person( $cluster[0], $token ) ) {
						$clusters[ $c_idx ][] = $token;
						$placed               = true;
						break;
					}
				}
				if ( ! $placed ) {
					$clusters[] = array( $token );
				}
			}
		}

		if ( empty( $clusters ) ) {
			return array();
		}

		$actors = array();
		foreach ( $clusters as $cluster ) {
			$clean_cluster = array();
			foreach ( $cluster as $c ) {
				$c = trim( preg_replace( '/[\x{200B}-\x{200D}\x{FEFF}]+/u', '', $c ) );
				if ( ! preg_match( '/^[A-Za-z0-9\s_-]+$/', $c ) ) {
					$c = preg_replace( '/\s+/u', '', $c );
				}
				if ( mb_strlen( $c ) >= 2 && ! in_array( $c, $clean_cluster, true ) ) {
					$clean_cluster[] = $c;
				}
			}

			if ( empty( $clean_cluster ) ) {
				continue;
			}

			// Sort cluster tokens by japanese_score DESC: Japanese official name wins as primary!
			usort(
				$clean_cluster,
				function ( $a, $b ) {
					return $this->japanese_score( $b ) <=> $this->japanese_score( $a );
				}
			);

			$primary       = $clean_cluster[0];
			$cluster_count = count( $clean_cluster );
			$aliases       = array();
			for ( $i = 1; $i < $cluster_count; $i++ ) {
				if ( $clean_cluster[ $i ] !== $primary && ! in_array( $clean_cluster[ $i ], $aliases, true ) ) {
					$aliases[] = $clean_cluster[ $i ];
				}
			}

			// Resolve primary name.
			$res = $this->resolve( $primary );

			// Check aliases if primary not resolved.
			if ( 'none' === $res['source'] && ! empty( $aliases ) ) {
				foreach ( $aliases as $alias ) {
					$r_alias = $this->resolve( $alias );
					if ( 'none' !== $r_alias['source'] ) {
						$res = $r_alias;
						break;
					}
				}
			}

			// Check normalized iteration mark (e.g. 八神七々実 -> 八神七七実).
			if ( 'none' === $res['source'] ) {
				$norm_p = preg_replace_callback(
					'/(.)々/u',
					static function ( $m ) {
						return $m[1] . $m[1];
					},
					$primary
				);
				if ( $norm_p !== $primary ) {
					$r_norm = $this->resolve( $norm_p );
					if ( 'none' !== $r_norm['source'] ) {
						$res = $r_norm;
					}
				}
			}

			// Strict verification: Reject if string is not in existing terms AND not in Gfriends!
			if ( 'none' === $res['source'] ) {
				continue;
			}

			$actors[] = array(
				'name'    => $primary,
				'type'    => $this->is_japanese( $primary ) ? 'japanese' : 'chinese',
				'count'   => 1,
				'paired'  => ! empty( $aliases ) ? $aliases[0] : '',
				'aliases' => $aliases,
				'term_id' => $res['term_id'],
				'source'  => $res['source'],
				'gf_name' => ! empty( $res['gf_name'] ) ? $res['gf_name'] : $primary,
				'score'   => $res['score'],
			);
		}

		return $actors;
	}
}
