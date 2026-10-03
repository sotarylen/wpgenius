<?php
/**
 * Album Importer — Directory Name Parser
 *
 * 把图集目录名解析成结构化元数据。设计立场：**只提取高置信字段，绝不猜测**。
 * 任何一段解析失败只留空并记 warning，由人工在待导入列表里确认。
 *
 * 真实样本（本地 Processing 目录实测）：
 *   [XIUREN秀人网] 2020.07.09 NO.2312 陆萱萱 性感制服[75+1P／174MB]
 *   [XIUREN秀人网] 2019.08.30 NO.1657 陆萱萱
 *   [XIUREN秀人网] 2019.12.15 NO.1850 黑丝双人诱_惑 就是阿朱啊&陆萱萱
 *   [XIUREN秀人网]  2021.04.08 No.3283 陆萱萱 [78P772MB]
 *   [YouWu尤物] 2016.04.07 VOL.001 悦儿Yuky
 *
 * 模特（humans）与工作室（photostudio）**不在本类里判定**——本类只吐 token，
 * 由上层用站点既有词条字典去匹配，命中才算数，避免瞎猜污染词条库。
 *
 * @package WP_Genius
 * @subpackage Modules/AlbumImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class W2P_Album_Name_Parser
 */
class W2P_Album_Name_Parser {

	/**
	 * 结构性目录名——这些是「图片层」目录，不是图集名。
	 * 命中即标「疑似结构性目录名」，需人工确认。
	 */
	const STRUCTURAL_NAMES = array(
		'webp',
		'jpg',
		'jpeg',
		'png',
		'gif',
		'avif',
		'images',
		'image',
		'photos',
		'photo',
		'pic',
		'pics',
		'thumb',
		'thumbs',
		'cover',
		'covers',
		'原图',
		'已分组',
		'无水印',
		'封面',
		'图片',
	);

	/**
	 * 解析一个目录名。
	 *
	 * @param string $dirname 目录名（纯名字，不是路径）。
	 * @return array {
	 *     @type string title      清洗后的标题（用户决策：直接采用目录名）
	 *     @type string studio     前缀 [xxx] 内的原始串，未识别为 ''
	 *     @type string date       发行日期 YYYYMMDD，未识别为 ''
	 *     @type string number     编号数字串（保留前导零），未识别为 ''
	 *     @type string remainder  摘掉 studio/日期/编号后的残余串，作描述默认值
	 *     @type array  tokens     残余串细分出的候选词，供上层用词条字典匹配模特
	 *     @type array  warnings   解析告警（已国际化的短句）
	 *     @type bool   structural 是否疑似结构性目录名（图片层而非图集名）
	 * }
	 */
	public static function parse( $dirname ) {
		$warnings = array();
		$raw      = (string) $dirname;

		$title = self::build_title( $raw, $warnings );

		// 解析用的归一化副本：全角英数与全角空格转半角，中文不受影响。
		$rest = self::normalize_for_parse( $raw );

		// 1. 前缀工作室：[xxx] 或 【xxx】（归一化后统一成了 []）
		$studio = '';
		if ( preg_match( '/^[\[\(]([^\]\)]+)[\]\)]\s*/u', $rest, $m ) ) {
			$studio = trim( $m[1] );
			$rest   = substr( $rest, strlen( $m[0] ) );
		} else {
			$warnings[] = __( 'No leading [studio] tag found.', 'wp-genius' );
		}

		// 2. 发行日期
		$date = '';
		if ( preg_match( '#(\d{4})[.\-/](\d{1,2})[.\-/](\d{1,2})#u', $rest, $m ) ) {
			$date = sprintf( '%04d%02d%02d', (int) $m[1], (int) $m[2], (int) $m[3] );
			$rest = preg_replace( '#' . preg_quote( $m[0], '#' ) . '#u', ' ', $rest, 1 );
		} else {
			$warnings[] = __( 'No release date found.', 'wp-genius' );
		}

		// 3. 编号：NO.2312 / No.3283 / VOL.001（大小写不敏感，保留前导零）
		$number = '';
		if ( preg_match( '/(?:NO|VOL)\.?\s*(\d+)/iu', $rest, $m ) ) {
			$number = (string) $m[1];
			$rest   = preg_replace( '/' . preg_quote( $m[0], '/' ) . '/iu', ' ', $rest, 1 );
		} else {
			$warnings[] = __( 'No serial number found.', 'wp-genius' );
		}

		// 4. 残余的方括号段一律是元信息噪声（前缀工作室已在第 1 步摘掉），整段丢弃。
		//    不丢的话 "[75+1P／174MB]" 会粘在前一个词上，把 "性感制服" 切成 "性感制服[75+1P/174MB]"。
		$rest = preg_replace( '/[\[\(][^\]\)]*[\]\)]/u', ' ', $rest );

		$remainder = self::cleanup_spaces( $rest );

		return array(
			'title'      => $title,
			'studio'     => $studio,
			'date'       => $date,
			'number'     => $number,
			'remainder'  => $remainder,
			'tokens'     => self::split_tokens( $remainder ),
			'warnings'   => $warnings,
			'structural' => self::is_structural_name( $raw ),
		);
	}

	/**
	 * 是否为结构性目录名（图片层，而非图集名）。
	 *
	 * 两类命中：① 名字在 STRUCTURAL_NAMES 白名单里；② 名字完全不含字母与中文（纯数字/符号）。
	 *
	 * @param string $dirname 目录名。
	 * @return bool
	 */
	public static function is_structural_name( $dirname ) {
		$raw = trim( (string) $dirname );
		if ( '' === $raw ) {
			return true;
		}

		$key = strtolower( preg_replace( '/[.\-_\s]+/u', '', $raw ) );
		if ( in_array( $key, self::STRUCTURAL_NAMES, true ) ) {
			return true;
		}

		// 既无拉丁字母也无假名/汉字 → 没有可用的语义（如 "12345"、"001"）
		if ( ! preg_match( '/[A-Za-z\x{3040}-\x{30ff}\x{4e00}-\x{9fff}]/u', $raw ) ) {
			return true;
		}

		return false;
	}

	/**
	 * 构造标题：规整空白 + 剔除文件名非法字符 + 剥掉尾部纯元信息方括号段。
	 *
	 * 刻意不做全角转半角——保留目录名的原始形态，只去掉明确属于噪声的尾部数量/体积标注
	 * （如 [75+1P／174MB]、[131P]、[527M]）。含中文的方括号段一律保留。
	 *
	 * @param string $raw       原始目录名。
	 * @param array  $warnings  引用：追加告警。
	 * @return string
	 */
	private static function build_title( $raw, &$warnings ) {
		// 文件名非法字符（目录名理论上不会有，纯防御）。
		$title = str_replace( array( '/', '\\', ':', '*', '?', '"', '<', '>', '|' ), '', $raw );
		$title = self::cleanup_spaces( $title );

		$max   = 3;
		$round = 0;

		while ( $round < $max && preg_match( '/[\[\(]([^\]\)]*)[\]\)]\s*$/u', $title, $m ) ) {
			$segment = $m[0];

			// 含中文/假名的方括号段可能是标题的一部分，不剥。
			if ( preg_match( '/[\x{3040}-\x{30ff}\x{4e00}-\x{9fff}]/u', $segment ) ) {
				break;
			}

			$trimmed = rtrim( substr( $title, 0, -strlen( $segment ) ) );
			if ( '' === $trimmed ) {
				break;
			}

			$title = $trimmed;
			++$round;
		}

		if ( '' === $title ) {
			$title      = self::cleanup_spaces( $raw );
			$warnings[] = __( 'Title became empty after cleaning; kept the raw directory name.', 'wp-genius' );
		}

		return $title;
	}

	/**
	 * 解析用归一化：全角英数/空格/括号转半角，中文不受影响。
	 *
	 * @param string $raw 原始串。
	 * @return string
	 */
	private static function normalize_for_parse( $raw ) {
		$s = (string) $raw;

		// 全角英数字与全角空格 → 半角（mb 函数缺失时静默降级，仅影响识别率）。
		if ( function_exists( 'mb_convert_kana' ) ) {
			$s = mb_convert_kana( $s, 'as' );
		}

		// mb_convert_kana 不保证覆盖全部全角标点，补一轮显式映射。
		$s = strtr(
			$s,
			array(
				'［' => '[',
				'］' => ']',
				'【' => '[',
				'】' => ']',
				'（' => '(',
				'）' => ')',
				'：' => ':',
				'．' => '.',
				'／' => '/',
				'　' => ' ',
			)
		);

		return self::cleanup_spaces( $s );
	}

	/**
	 * 把残余串切成候选词 token，供词条字典匹配模特。
	 *
	 * @param string $remainder 残余串。
	 * @return array
	 */
	private static function split_tokens( $remainder ) {
		$rest = self::cleanup_spaces( $remainder );
		if ( '' === $rest ) {
			return array();
		}

		$rest = strtr(
			$rest,
			array(
				'&' => ' ',
				'，' => ' ',
				',' => ' ',
				'、' => ' ',
				'/' => ' ',
				'／' => ' ',
				'+' => ' ',
				'_' => ' ',
				'·' => ' ',
				'　' => ' ',
			)
		);

		$tokens = array();
		foreach ( explode( ' ', self::cleanup_spaces( $rest ) ) as $token ) {
			$token = trim( $token );
			if ( '' === $token ) {
				continue;
			}
			// 含数字的 token 一律丢掉。这批数据里的模特名（陆萱萱 / 妲己Toxic / 悦儿Yuky / KellyBaby）
			// 都不含数字，而目录名里的数量体积标注（"7800P"、"100套"、"233组1200"）全都含数字。
			// 放过去会被 sanitize_title 削成退化 slug，撞上不相干的词条（实测 "100套" → slug "100" → 命中错误词条）。
			if ( preg_match( '/[0-9]/', $token ) ) {
				continue;
			}
			$tokens[] = $token;
		}

		return array_values( array_unique( $tokens ) );
	}

	/**
	 * 规整空白：连续空白压成单个半角空格，去除首尾空白。
	 *
	 * @param string $s 输入。
	 * @return string
	 */
	private static function cleanup_spaces( $s ) {
		$s = preg_replace( '/\s+/u', ' ', (string) $s );
		return trim( (string) $s );
	}
}
