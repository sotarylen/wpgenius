<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Actor name matcher
 *
 * Extracts candidate actress names from post title/content and resolves them
 * to Humans taxonomy terms. Matching strategy (smart, no character mapping):
 *
 *  1. Paired pattern 「日文名(中文名)」— the strongest signal in AV posts.
 *     The Japanese name (with kana) is matched against Gfriends and existing
 *     Humans terms; the Chinese partner anchors as an alias of the same term.
 *  2. Japanese names containing kana are extracted verbatim and matched
 *     exactly against the Gfriends index / Humans terms.
 *  3. Chinese names (simplified/traditional) are matched against Humans term
 *     names + human_nickname aliases, then fuzzy-matched against Gfriends.
 *  4. A Japanese name with no match at all creates a new Humans term (the
 *     article itself is the evidence). A Chinese name with no match is only
 *     kept as an alias of its paired Japanese partner.
 *
 * @package WP_Genius
 * @subpackage Modules\ActorScanner
 */
class W2P_Actor_Matcher {

	/**
	 * Gfriends client.
	 *
	 * @var W2P_Gfriends_Client
	 */
	protected $gf;

	/**
	 * Existing Humans term lookup: normalized alias => term_id.
	 *
	 * @var array|null
	 */
	protected $term_alias_map = null;

	/**
	 * Existing Humans terms keyed by term_id.
	 *
	 * @var array|null
	 */
	protected $terms = null;

	/**
	 * Normalized known-name lookup (term aliases + Gfriends CJK names).
	 * Used by rule 4 to recognize standalone Chinese names in text.
	 *
	 * @var array|null
	 */
	protected $known_names = null;

	/**
	 * Constructor.
	 *
	 * @param W2P_Gfriends_Client $gf Gfriends client.
	 */
	public function __construct( $gf ) {
		$this->gf = $gf;
	}

	/**
	 * Load all Humans terms (with role + nickname meta) into a lookup.
	 *
	 * @return array
	 */
	public function load_terms() {
		if ( null !== $this->terms ) {
			return $this->terms;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'humans',
				'hide_empty' => false,
				'number'     => 0,
			)
		);

		$terms = is_wp_error( $terms ) ? array() : $terms;
		$map   = array();

		foreach ( $terms as $term ) {
			$this->terms[ $term->term_id ] = $term;

			// Index term name.
			$this->add_alias( $map, $term->name, $term->term_id );

			// Index nickname aliases (comma / Chinese-comma separated).
			$nick = get_term_meta( $term->term_id, 'human_nickname', true );
			if ( is_string( $nick ) && '' !== trim( $nick ) ) {
				$parts = preg_split( '/[\s,，、;；]+/u', $nick );
				foreach ( $parts as $part ) {
					$part = trim( $part );
					if ( '' !== $part ) {
						$this->add_alias( $map, $part, $term->term_id );
					}
				}
			}
		}

		$this->term_alias_map = $map;
		return $this->terms;
	}

	/**
	 * Add an alias to the lookup map (first occurrence wins, prefer role=演员).
	 *
	 * @param array  $map     Alias map (by reference).
	 * @param string $alias   Alias string.
	 * @param int    $term_id Term id.
	 * @return void
	 */
	protected function add_alias( &$map, $alias, $term_id ) {
		$key = $this->normalize_key( $alias );
		if ( '' === $key ) {
			return;
		}
		if ( ! isset( $map[ $key ] ) ) {
			$map[ $key ] = $term_id;
			return;
		}
		// Prefer 演员 over other roles on collision.
		$existing       = isset( $this->terms[ $map[ $key ] ] ) ? $this->terms[ $map[ $key ] ] : null;
		$candidate      = isset( $this->terms[ $term_id ] ) ? $this->terms[ $term_id ] : null;
		$existing_role  = $existing ? (string) get_term_meta( $existing->term_id, 'human_role', true ) : '';
		$candidate_role = $candidate ? (string) get_term_meta( $candidate->term_id, 'human_role', true ) : '';
		if ( false !== strpos( $candidate_role, '演员' ) && false === strpos( $existing_role, '演员' ) ) {
			$map[ $key ] = $term_id;
		}
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
	 * Extract candidate actress names from a text (title + content).
	 *
	 * Returns an associative array:
	 *   name => [ 'type' => 'japanese'|'chinese', 'count' => int, 'paired' => string|'' ]
	 * where paired is the partner name when the candidate came from a
	 * 「日文名(中文名)」 pair ('' otherwise).
	 *
	 * @param string $text Post title + content.
	 * @return array
	 */
	public function extract_candidates( $text ) {
		$candidates = array();

		// 1. Paired pattern: 日文名(中文名) / 中文名(日文名).
		// Locate each bracket pair, then take the name-shaped run immediately
		// before the opening bracket (avoids greedy prefix bleeding). The raw
		// run may carry stray particles (是/以/還以為…); pick the longest
		// suffix that verifies against Gfriends or existing Humans terms.
		if ( preg_match_all( '/[（(]\s*([\x{3040}-\x{30FF}\x{4E00}-\x{9FA5}]{2,8})\s*[)）]/u', $text, $m, PREG_OFFSET_CAPTURE ) ) {
			$this->load_terms();
			foreach ( $m[1] as $inner_match ) {
				$inner = trim( $inner_match[0] );
				if ( mb_strlen( $inner ) < 2 ) {
					continue;
				}
				$open_pos = (int) $inner_match[1] - 1; // byte offset of '('
				$run      = $this->run_before( $text, $open_pos );
				$outer    = $this->best_suffix_name( $run );

				if ( '' === $outer || mb_strlen( $outer ) < 2 ) {
					continue;
				}

				$has_kana = $this->is_japanese( $outer ) || $this->is_japanese( $inner );
				if ( ! $has_kana ) {
					continue; // pure-CJK pairs without kana are ambiguous (sentences).
				}
				if ( $this->looks_sentence( $outer ) || $this->looks_sentence( $inner ) ) {
					continue;
				}
				// Reject sentence-style Japanese (particles like の/は/が/を/です…)
				// that appear in work titles, e.g. 突然の相部屋(然後ー).
				if ( $this->is_japanese( $outer ) && $this->has_particle( $outer ) ) {
					continue;
				}
				if ( $this->is_japanese( $inner ) && $this->has_particle( $inner ) ) {
					continue;
				}

				$this->bump( $candidates, $outer, $this->is_japanese( $outer ) ? 'japanese' : 'chinese', $inner );
				$this->bump( $candidates, $inner, $this->is_japanese( $inner ) ? 'japanese' : 'chinese', $outer );
			}
		}

		// 2. Quoted names: 「涼森れむ」 etc.
		if ( preg_match_all( '/[「『“"]([\x{3040}-\x{30FF}\x{4E00}-\x{9FA5}]{2,10})[」』”"]/u', $text, $m ) ) {
			foreach ( $m[1] as $name ) {
				$name = trim( $name );
				$len  = mb_strlen( $name );
				if ( $len < 2 || $len > 8 || $this->looks_sentence( $name ) ) {
					continue;
				}
				if ( ! $this->is_japanese( $name ) && $len > 4 ) {
					continue;
				}
				$this->bump( $candidates, $name, $this->is_japanese( $name ) ? 'japanese' : 'chinese' );
			}
		}

		// 3. Standalone kana-bearing names (2-8 chars, delimited). The raw run may
		// include a stray leading particle (以/道/像/著…); trim via suffix
		// verification against Gfriends / Humans terms.
		if ( preg_match_all( '/(?<![\x{4E00}-\x{9FA5}\x{3040}-\x{30FF}])[\x{4E00}-\x{9FA5}]{0,3}[\x{3040}-\x{30FF}]{1,6}[\x{4E00}-\x{9FA5}]{0,3}(?![\x{4E00}-\x{9FA5}\x{3040}-\x{30FF}])/u', $text, $m ) ) {
			foreach ( $m[0] as $run ) {
				$run = trim( $run );
				if ( mb_strlen( $run ) < 2 || mb_strlen( $run ) > 8 ) {
					continue;
				}
				if ( $this->is_particle( $run ) ) {
					continue;
				}
				if ( $this->has_particle( $run ) ) {
					continue; // sentence-style, not a name.
				}
				$cand = $this->best_suffix_name( $run );
				if ( '' === $cand || mb_strlen( $cand ) < 2 ) {
					continue;
				}
				if ( $this->is_substring_of_pair( $cand, $candidates ) ) {
					continue;
				}
				if ( ! isset( $candidates[ $cand ] ) ) {
					$this->bump( $candidates, $cand, 'japanese' );
				}
			}
		}

		// 4. Standalone pure-CJK names (2-4 chars) that are KNOWN actresses
		// (existing Humans term aliases or Gfriends index). This catches titles
		// like 栗山莉緒換東家！ without sweeping arbitrary CJK bigrams.
		$known = $this->get_known_names();
		if ( ! empty( $known ) && preg_match_all( '/(?<![\x{4E00}-\x{9FA5}])[\x{4E00}-\x{9FA5}]{2,4}(?![\x{4E00}-\x{9FA5}])/u', $text, $m ) ) {
			foreach ( $m[0] as $run ) {
				if ( $this->looks_sentence( $run ) ) {
					continue;
				}
				$key = $this->normalize_key( $run );
				if ( '' === $key || ! isset( $known[ $key ] ) ) {
					continue;
				}
				if ( isset( $candidates[ $run ] ) ) {
					continue;
				}
				// Skip a run that is part of an already-matched pair/quoted name
				// (莉莉 inside 秋瀨莉莉), so we don't attach the fragment as well.
				if ( $this->is_substring_of_pair( $run, $candidates ) ) {
					continue;
				}
				$this->bump( $candidates, $run, 'chinese' );
			}
		}

		return $candidates;
	}

	/**
	 * Build (lazily) the known-name lookup: normalized key => true.
	 * Sources: existing Humans term names + nickname aliases, and CJK names in
	 * the Gfriends index.
	 *
	 * @return array
	 */
	public function get_known_names() {
		if ( null !== $this->known_names ) {
			return $this->known_names;
		}

		$known = array();

		// Rule 4 uses ONLY the Gfriends actor index (a curated source) so that
		// noise terms already present in Humans (通过, 月亮, 合集…) are never
		// picked up from arbitrary post text. Existing Humans terms are still
		// matched via the pair/quote rules and the resolve() exact lookup.
		// Gfriends CJK names (strip trailing -N numbering from multi-shot files).
		foreach ( array_keys( $this->gf->get_actor_index() ) as $name ) {
			if ( $this->is_japanese( $name ) ) {
				continue;
			}
			$base = preg_replace( '/-\d+$/u', '', $name );
			$len  = mb_strlen( $base );
			if ( $len >= 2 && $len <= 4 && ! $this->looks_sentence( $base ) ) {
				$known[ $this->normalize_key( $base ) ] = true;
			}
		}

		$this->known_names = $known;
		return $known;
	}

	/**
	 * Walk left from a byte offset collecting a contiguous CJK run.
	 *
	 * @param string $text Full text.
	 * @param int    $from Byte offset of the char after the run (the '(' char).
	 * @return string
	 */
	protected function run_before( $text, $from ) {
		$run = '';
		$pos = $from;
		while ( $pos > 0 ) {
			$char = $this->char_ending_at( $text, $pos );
			if ( '' === $char || ! preg_match( '/[\x{4E00}-\x{9FA5}\x{3040}-\x{30FF}A-Za-z0-9]/u', $char ) ) {
				break;
			}
			$run  = $char . $run;
			$pos -= strlen( $char );
		}
		return $run;
	}

	/**
	 * Get the UTF-8 character ending at byte offset pos.
	 *
	 * @param string $text Text.
	 * @param int    $pos  Byte offset.
	 * @return string
	 */
	protected function char_ending_at( $text, $pos ) {
		$start = $pos;
		while ( $start > 0 ) {
			--$start;
			$b = ord( $text[ $start ] );
			if ( ( $b & 0xC0 ) !== 0x80 ) {
				break;
			}
		}
		if ( $start >= $pos ) {
			return '';
		}
		return substr( $text, $start, $pos - $start );
	}

	/**
	 * Pick the most plausible actress name from a raw run by verifying suffixes
	 * against Gfriends and existing Humans terms. Falls back to shape_name().
	 *
	 * @param string $run Raw CJK run (may include stray particles).
	 * @return string
	 */
	protected function best_suffix_name( $run ) {
		$run = trim( $run );
		if ( '' === $run ) {
			return '';
		}

		// All suffixes of length 2-8, longest first.
		$len      = mb_strlen( $run );
		$suffixes = array();
		for ( $i = 0; $i < $len; $i++ ) {
			$s  = mb_substr( $run, $i );
			$sl = mb_strlen( $s );
			if ( $sl >= 2 && $sl <= 8 ) {
				$suffixes[] = $s;
			}
		}
		usort(
			$suffixes,
			static function ( $a, $b ) {
				return mb_strlen( $b ) <=> mb_strlen( $a );
			}
		);

		foreach ( $suffixes as $suffix ) {
			// Skip pure-kana suffixes when the full run has a kanji prefix: 水戸ありさ
			// must not collapse to ありさ (a different person). Only kanji-bearing
			// suffixes are trustworthy name candidates.
			if ( $this->is_japanese( $run ) && ! $this->has_kanji( $suffix ) ) {
				continue;
			}
			// Gfriends exact?
			if ( ! empty( $this->gf->get_actor( $suffix ) ) ) {
				return $suffix;
			}
			// Existing Humans term / alias exact?
			$key = $this->normalize_key( $suffix );
			if ( isset( $this->term_alias_map[ $key ] ) ) {
				return $suffix;
			}
		}

		return $this->shape_name( $run );
	}

	/**
	 * Shape a raw CJK run into a plausible person name:
	 *  - kana-bearing run: keep the longest suffix matching
	 *    0-3 kanji + 1-6 kana + 0-3 kanji (2-8 chars total).
	 *  - pure CJK: keep the last 2-4 chars.
	 *
	 * @param string $run Raw run.
	 * @return string
	 */
	protected function shape_name( $run ) {
		$run = trim( $run );
		if ( '' === $run ) {
			return '';
		}

		// Strip common single-char sentence particles that may prefix a name
		// (以/道/像/著/是/還/為…). Limited iterations to avoid over-stripping.
		$particles = array( '以', '把', '将', '對', '对', '像', '道', '著', '着', '是', '還', '又', '和', '跟', '向', '从', '從', '於', '于', '被', '让', '叫', '給', '给', '為', '为', '到', '在', '有', '說', '说', '她', '他', '我', '你', '的', '也', '就', '都', '則', '则', '與', '与', '及', '或' );
		for ( $i = 0; $i < 6; $i++ ) {
			$first = mb_substr( $run, 0, 1 );
			if ( '' === $first || ! in_array( $first, $particles, true ) ) {
				break;
			}
			$run = mb_substr( $run, 1 );
		}

		if ( preg_match( '/[\x{3040}-\x{30FF}]/u', $run ) ) {
			// Kana-bearing name: 姓氏(kanji) + 名前(kana), e.g. 松岡すず / 伊奈美いずな.
			// Strategy: locate the LAST kana run, then walk LEFT over kanji to build
			// the surname. Stray particles immediately before the surname (是/以/像/
			// 道/著…) are skipped so we don't keep 以伊奈美いずな instead of 伊奈美いずな.
			if ( preg_match( '/([\x{3040}-\x{30FF}]{1,6})$/u', $run, $m ) ) {
				$kana   = $m[1];
				$before = mb_substr( $run, 0, -mb_strlen( $kana ) );

				// Walk LEFT from the kana collecting kanji, stopping at particles
				// (的/是/為/著…) or at 4 kanji. This keeps 水戸 in 第一次下馬的水戸ありさ
				// and 伊奈美 in 意味著伊奈美いずな.
				$particles = array( '以', '把', '将', '對', '对', '像', '道', '著', '着', '是', '還', '又', '和', '跟', '向', '从', '從', '於', '于', '被', '让', '叫', '給', '给', '為', '为', '到', '在', '有', '說', '说', '她', '他', '我', '你', '的', '也', '就', '都', '則', '则', '與', '与', '及', '或' );
				$kanji     = '';
				$chars     = preg_split( '//u', $before, -1, PREG_SPLIT_NO_EMPTY );
				for ( $j = count( $chars ) - 1; $j >= 0; $j-- ) {
					$ch = $chars[ $j ];
					if ( ! preg_match( '/[\x{4E00}-\x{9FA5}]/u', $ch ) || in_array( $ch, $particles, true ) ) {
						break;
					}
					$kanji = $ch . $kanji;
					if ( mb_strlen( $kanji ) >= 4 ) {
						break;
					}
				}

				if ( '' !== $kanji ) {
					$cand = $kanji . $kana;
					if ( mb_strlen( $cand ) >= 2 && mb_strlen( $cand ) <= 8 ) {
						return $cand;
					}
				}
			}
			// Fallback: scan from the END of the run so the shortest legal suffix
			// wins. Prefer suffixes that CONTAIN kanji (水戸ありさ, 伊奈美いずな);
			// only accept a pure-kana suffix (りさ) when nothing better exists.
			$len                = mb_strlen( $run );
			$pure_kana_fallback = '';
			for ( $i = $len - 1; $i >= 0; $i-- ) {
				$suffix = mb_substr( $run, $i );
				$slen   = mb_strlen( $suffix );
				if ( $slen < 2 || $slen > 8 ) {
					continue;
				}
				if ( ! preg_match( '/^[\x{4E00}-\x{9FA5}]{0,3}[\x{3040}-\x{30FF}]{1,6}[\x{4E00}-\x{9FA5}]{0,3}$/u', $suffix ) ) {
					continue;
				}
				if ( preg_match( '/[\x{4E00}-\x{9FA5}]/u', $suffix ) ) {
					return $suffix;
				}
				if ( '' === $pure_kana_fallback ) {
					$pure_kana_fallback = $suffix;
				}
			}
			return $pure_kana_fallback;
		}
		// Pure CJK: keep last 2-4 chars.
		$len = mb_strlen( $run );
		return $len <= 4 ? $run : mb_substr( $run, $len - 4 );
	}

	/**
	 * Whether a kana run is a substring of an already-found pair candidate.
	 *
	 * @param string $run        Run to check.
	 * @param array  $candidates Candidate map.
	 * @return bool
	 */
	protected function is_substring_of_pair( $run, $candidates ) {
		foreach ( $candidates as $name => $info ) {
			if ( $run === $name ) {
				return true;
			}
			if ( false !== mb_strpos( $name, $run ) || false !== mb_strpos( $run, $name ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Bump a candidate's occurrence count.
	 *
	 * @param array  $candidates Candidate map (by reference).
	 * @param string $name       Candidate name.
	 * @param string $type       'japanese' or 'chinese'.
	 * @param string $paired     Partner name from a pair (optional).
	 * @return void
	 */
	protected function bump( &$candidates, $name, $type, $paired = '' ) {
		$name = trim( $name );
		if ( '' === $name || mb_strlen( $name ) < 2 ) {
			return;
		}
		if ( ! isset( $candidates[ $name ] ) ) {
			$candidates[ $name ] = array(
				'type'   => $type,
				'count'  => 0,
				'paired' => '',
			);
		}
		++$candidates[ $name ]['count'];
		if ( '' === $candidates[ $name ]['paired'] && '' !== $paired ) {
			$candidates[ $name ]['paired'] = $paired;
		}
	}

	/**
	 * Whether a string contains kana (strong Japanese-name signal).
	 *
	 * @param string $name Candidate.
	 * @return bool
	 */
	public function is_japanese( $name ) {
		return (bool) preg_match( '/[\x{3040}-\x{30FF}]/u', $name );
	}

	/**
	 * Whether a string contains at least one CJK kanji character.
	 *
	 * @param string $name Candidate.
	 * @return bool
	 */
	public function has_kanji( $name ) {
		return (bool) preg_match( '/[\x{4E00}-\x{9FA5}]/u', $name );
	}

	/**
	 * Whether a run looks like a sentence fragment rather than a person name.
	 *
	 * @param string $run Candidate run.
	 * @return bool
	 */
	protected function looks_sentence( $run ) {
		if ( mb_strlen( $run ) > 8 ) {
			return true;
		}
		$stop = array(
			'但是',
			'因为',
			'所以',
			'就是',
			'不是',
			'还是',
			'这个',
			'那个',
			'什么',
			'他们',
			'我们',
			'你们',
			'自己',
			'没有',
			'已经',
			'可以',
			'不过',
			'为了',
			'关于',
			'以及',
			'而且',
			'虽然',
			'如果',
			'然后',
			'这样',
			'那样',
			'所有',
			'其中',
			'只是',
			'真的',
			'觉得',
			'知道',
			'看到',
			'面对',
			'作品',
			'新人',
			'女优',
			'演员',
			'偶像',
			'写真',
			'杂志',
			'封面',
			'介绍',
			'最新',
			'本月',
			'今天',
			'明天',
			'还有',
			'现在',
			'不少',
			'一般',
			'一样',
			'出来',
			'起来',
			'开始',
			'最后',
			'前面',
			'后面',
			'里面',
			'外面',
			'时间',
			'时候',
			'朋友',
			'女孩',
			'女人',
			'小姐',
			'大姐',
			'老师',
			'社长',
			'监督',
			'导演',
			'编剧',
			'制作',
			'发售',
			'发行',
			'观看',
			'视频',
			'图片',
			'电影',
			'系列',
			'剧情',
			'内容',
			'版本',
			'人妻',
			'刺青',
			'背部',
			'封面',
			'新人',
			'标题',
			'文章',
			'评论',
			'网友',
			'大家',
			'各位',
			'本人',
			'主角',
			'女主',
			'男主',
			'男优',
			'女演员',
			'男主角',
			'女主角',
			'女星',
			'男星',
			'艺人',
			'明星',
			'女神',
			'宝贝',
			'宝宝',
			'亲爱的',
			'老公',
			'老婆',
			'丈夫',
			'妻子',
			'男友',
			'女友',
			'初恋',
			'前妻',
			'前夫',
			'偶像团体',
			'歌唱',
			'歌手',
			'主持',
			'模特',
			'广告',
			'综艺',
			'节目',
			'剧集',
			'電視',
			'电影',
			'导演',
			'她的',
			'他的',
			'我的',
			'你的',
			'这些',
			'那些',
			'一个',
			'一次',
			'一生',
			'一夜',
			'去年',
			'明年',
			'之后',
			'之前',
			'上方',
			'下方',
		);
		return in_array( $run, $stop, true );
	}

	/**
	 * Whether a pure-kana run is a common particle rather than a name.
	 *
	 * @param string $run Candidate run.
	 * @return bool
	 */
	protected function is_particle( $run ) {
		$particles = array( 'です', 'ます', 'けど', 'から', 'まで', 'でも', 'そして', 'それで', 'だから', 'または', 'また', 'まだ', 'もう', 'ずっと', 'ちょっと', 'とても', 'いつも', 'たぶん', 'きっと', 'やっぱり', 'なるほど', 'つまり', 'いや', 'うん', 'はい', 'いいえ' );
		return in_array( $run, $particles, true );
	}

	/**
	 * Whether a name-like candidate contains Japanese particles that mark it as
	 * a sentence fragment rather than a person name (突然の相部屋, 愛想のいい笑顔…).
	 *
	 * @param string $run Candidate run.
	 * @return bool
	 */
	protected function has_particle( $run ) {
		// Sentence-marking kana: の / は / が / を / に / へ / も / や / です / ます /
		// した / する / して / だっ / なっ / いい / ない / ん — these never appear
		// inside a person name (突然の相部屋, 愛想のいい笑顔, 丁度いい美少女).
		if ( preg_match( '/の|は|が|を|に|へ|も|や|です|ます|した|する|して|だっ|なっ|いい|ない|ん/u', $run ) ) {
			return true;
		}
		// A Chinese word ending with the kana long-vowel mark (所以ー, 然後ー) is a
		// sentence fragment, not a name.
		if ( preg_match( '/[\x{4E00}-\x{9FA5}]+ー$/u', $run ) ) {
			return true;
		}
		// Suffixes that never end a person name.
		if ( preg_match( '/(さん|ちゃん|くん|先生|様|さま)$/u', $run ) ) {
			return true;
		}
		return $this->is_av_jargon( $run );
	}

	/**
	 * AV-industry jargon / common non-name words that slip through kana patterns.
	 *
	 * @param string $run Candidate run.
	 * @return bool
	 */
	protected function is_av_jargon( $run ) {
		$jargon = array(
			'中出し',
			'中出',
			'デリヘル',
			'デリヘル嬢',
			'レイプ',
			'ガチレイプ',
			'主観',
			'寝取られ',
			'相部屋',
			'美少女',
			'人妻',
			'熟女',
			'素人',
			'女優',
			'新人',
			'無修正',
			'有碼',
			'無碼',
			'巨乳',
			'爆乳',
			'美乳',
			'貧乳',
			'童顔',
			'清楚',
			'天然',
			'混浴',
			'大亂交',
			'乱交',
			'オナニー',
			'フェラ',
			'セックス',
			'ハメ撮り',
			'パイパン',
			'潮吹き',
			'顔射',
			'中出',
			'処女',
			'生ハメ',
			'素股',
			'逆レイプ',
			'輪姦',
			'調教',
			'凌辱',
			'放尿',
			'飲尿',
			'おまんこ',
			'まんこ',
			'ちんこ',
			'おっぱい',
			'ちっぱい',
			'スケベ',
			'エロ',
			'アダルト',
			'カップ',
			'コスプレ',
			'濡れ場',
			'ベッドシーン',
			'プライベート',
			'オフ会',
			'撮影会',
		);
		foreach ( $jargon as $w ) {
			if ( false !== mb_strpos( $run, $w ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Resolve a single candidate name to a Humans term id.
	 *
	 * Returns [ 'term_id', 'source', 'gf_name', 'score' ].
	 *
	 * @param string $name Candidate name.
	 * @return array
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

		// 1. Existing Humans term (exact).
		if ( isset( $this->term_alias_map[ $key ] ) ) {
			$result['term_id'] = $this->term_alias_map[ $key ];
			$result['source']  = 'term';
			$result['score']   = 1.0;
			return $result;
		}

		// 2. Gfriends exact.
		$gf_actor = $this->gf->get_actor( $name );
		if ( ! empty( $gf_actor ) ) {
			$result['source']  = 'gfriends_exact';
			$result['gf_name'] = $name;
			$result['score']   = 1.0;
			return $result;
		}

		// 3. Gfriends fuzzy (Chinese name → best guess).
		if ( ! $this->is_japanese( $name ) && mb_strlen( $name ) >= 2 && mb_strlen( $name ) <= 6 ) {
			$hits = $this->gf->fuzzy_search( $name, 1, 0.55 );
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
	 * Resolve all candidates of a post, returning unique resolved actors.
	 *
	 * @param array $candidates Candidate map from extract_candidates().
	 * @return array list of [ 'name', 'type', 'count', 'paired', 'term_id', 'source', 'gf_name', 'score' ]
	 */
	public function resolve_all( $candidates ) {
		$resolved = array();
		$seen     = array();

		// Pass 1: Japanese-name candidates (most reliable) resolve independently.
		$jp_by_name = array();
		foreach ( $candidates as $name => $info ) {
			if ( 'japanese' !== $info['type'] ) {
				continue;
			}
			$r = $this->resolve( $name );
			if ( 'none' === $r['source'] ) {
				// Japanese name with no match: the article itself is the evidence —
				// create a new term. Pure-kana words (デビュー etc.) are not names.
				if ( $this->has_kanji( $name ) ) {
					$r['source']  = 'new';
					$r['gf_name'] = '';
					$r['score']   = 0.6;
				}
			}
			$jp_by_name[ $name ] = $r;
		}

		// Pass 2: all candidates, but Chinese partners anchor to their Japanese
		// partner's resolution (the pair is the strongest signal).
		uksort(
			$candidates,
			static function ( $a, $b ) use ( $candidates ) {
				$ta = $candidates[ $a ]['type'] === 'japanese' ? 0 : 1;
				$tb = $candidates[ $b ]['type'] === 'japanese' ? 0 : 1;
				if ( $ta !== $tb ) {
					return $ta <=> $tb;
				}
				return $candidates[ $b ]['count'] <=> $candidates[ $a ]['count'];
			}
		);

		foreach ( $candidates as $name => $info ) {
			$paired = isset( $info['paired'] ) ? $info['paired'] : '';
			$r      = null;

			if ( 'chinese' === $info['type'] && '' !== $paired && isset( $jp_by_name[ $paired ] ) ) {
				// Anchor: use the Japanese partner's resolution.
				$r = $jp_by_name[ $paired ];
				if ( 'none' === $r['source'] ) {
					continue; // Partner was not a name (片商名 etc.) → drop both.
				}
			} else {
				$r = isset( $jp_by_name[ $name ] ) ? $jp_by_name[ $name ] : $this->resolve( $name );
				if ( 'none' === $r['source'] ) {
					if ( 'japanese' === $info['type'] && $this->has_kanji( $name ) ) {
						$r['source']  = 'new';
						$r['gf_name'] = '';
						$r['score']   = 0.6;
					} else {
						continue; // Chinese / pure-kana name with no match → drop.
					}
				}
				// A standalone Chinese name (no Japanese pair partner) is only kept
				// when it exactly matched an existing term — fuzzy guesses on bare
				// Chinese names mis-hit male actors (中田一平 → 中田由真).
				if ( 'chinese' === $info['type'] && '' === $paired && 'term' !== $r['source'] ) {
					continue;
				}
			}

			// Dedupe by the underlying actor (term id, or gfriends name), not by the
			// surface candidate name — 十束流羽 and 十束るう are the same actress.
			$dedupe_key = 't:' . $r['term_id'] . '|g:' . $r['gf_name'];
			if ( isset( $seen[ $dedupe_key ] ) ) {
				// Merge: attach this candidate name as an extra alias.
				foreach ( $resolved as $i => $entry ) {
					if ( $entry['term_id'] === $r['term_id'] && $entry['gf_name'] === $r['gf_name'] ) {
						if ( ! in_array( $name, $entry['aliases'], true ) ) {
							$resolved[ $i ]['aliases'][] = $name;
						}
						if ( '' === $resolved[ $i ]['paired'] && '' !== $paired ) {
							$resolved[ $i ]['paired'] = $paired;
						}
						$resolved[ $i ]['count'] += $info['count'];
						break;
					}
				}
				continue;
			}
			$seen[ $dedupe_key ] = true;

			$resolved[] = array(
				'name'    => $name,
				'type'    => $info['type'],
				'count'   => $info['count'],
				'paired'  => $paired,
				'aliases' => array(),
				'term_id' => $r['term_id'],
				'source'  => $r['source'],
				'gf_name' => $r['gf_name'],
				'score'   => $r['score'],
			);
		}

		// Pair anchoring: a Chinese partner that failed to resolve on its own is
		// attached to its Japanese partner's term.
		$this->anchor_pairs( $resolved );

		return $resolved;
	}

	/**
	 * Attach unpaired Chinese names to their Japanese partner's term.
	 *
	 * @param array $resolved Resolved entries (by reference).
	 * @return void
	 */
	protected function anchor_pairs( &$resolved ) {
		$by_name = array();
		foreach ( $resolved as $i => $entry ) {
			$by_name[ $entry['name'] ] = $i;
			foreach ( $entry['aliases'] as $alias ) {
				if ( ! isset( $by_name[ $alias ] ) ) {
					$by_name[ $alias ] = $i;
				}
			}
		}

		foreach ( $resolved as $i => $entry ) {
			$partner = isset( $entry['paired'] ) ? $entry['paired'] : '';
			if ( '' === $partner || ! isset( $by_name[ $partner ] ) ) {
				continue;
			}
			$partner_idx   = $by_name[ $partner ];
			$partner_entry = $resolved[ $partner_idx ];
			if ( empty( $partner_entry['term_id'] ) && ! empty( $entry['term_id'] ) ) {
				$resolved[ $partner_idx ]['term_id'] = $entry['term_id'];
				$resolved[ $partner_idx ]['source']  = 'term';
			}
		}
	}

	/**
	 * Record a paired alias for an already-seen term (enrichment hint).
	 *
	 * @param array  $resolved Resolved entries (by reference).
	 * @param array  $r        Resolution result.
	 * @param string $alias    Alias name.
	 * @return void
	 */
	protected function record_alias( &$resolved, $r, $alias ) {
		foreach ( $resolved as $i => $entry ) {
			if ( $entry['term_id'] === $r['term_id'] && $entry['source'] === $r['source'] ) {
				if ( '' === $entry['paired'] && '' !== $alias ) {
					$resolved[ $i ]['paired'] = $alias;
				}
				break;
			}
		}
	}
}
