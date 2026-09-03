<?php
/**
 * Novel Manager — Helper Class
 *
 * 共享工具类：中文数字转换、分卷提取、章节序号提取、序号格式化。
 *
 * @package WP_Genius
 * @subpackage Modules/NovelManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class W2P_Novel_Helper {

	/**
	 * 将中文数字或混合数字字符串转为阿拉伯数字整数
	 * 支持：小写 (一二三)、大写 (壹贰叁)、两/廿/卅/〇/零，百千万亿等单位
	 *
	 * @param string $str 中文数字字符串
	 * @return int|null 转换成功返回整数，失败返回 null
	 */
	public static function chinese_to_arabic( $str ) {
		if ( empty( $str ) ) {
			return null;
		}

		$str = trim( str_replace( array( ' ', '　', "\t" ), '', $str ) );

		// 纯数字直接转换
		if ( is_numeric( $str ) ) {
			return intval( $str );
		}

		// 大写数字转小写
		$upper_map = array(
			'壹' => '一',
			'贰' => '二',
			'叁' => '三',
			'肆' => '四',
			'伍' => '五',
			'陆' => '六',
			'柒' => '七',
			'捌' => '八',
			'玖' => '九',
			'拾' => '十',
			'佰' => '百',
			'仟' => '千',
			'萬' => '万',
			'億' => '亿',
		);
		$str       = strtr( $str, $upper_map );

		// 特殊词转换
		if ( $str === '廿' ) {
			return 20;
		}
		if ( $str === '卅' ) {
			return 30;
		}

		// 以 "十" 开头补 "一"，如 "十五" -> "一十五"
		if ( mb_substr( $str, 0, 1, 'UTF-8' ) === '十' ) {
			$str = '一' . $str;
		}

		$digits = array(
			'零' => 0,
			'〇' => 0,
			'一' => 1,
			'二' => 2,
			'两' => 2,
			'三' => 3,
			'四' => 4,
			'五' => 5,
			'六' => 6,
			'七' => 7,
			'八' => 8,
			'九' => 9,
		);

		$units = array(
			'十' => 10,
			'百' => 100,
			'千' => 1000,
			'万' => 10000,
			'亿' => 100000000,
		);

		// 单字符判断
		if ( mb_strlen( $str, 'UTF-8' ) === 1 ) {
			if ( isset( $digits[ $str ] ) ) {
				return $digits[ $str ];
			}
			if ( isset( $units[ $str ] ) ) {
				return $units[ $str ];
			}
			return null;
		}

		// 遍历解析
		$total   = 0;
		$current = 0;
		$chars   = preg_split( '//u', $str, -1, PREG_SPLIT_NO_EMPTY );

		foreach ( $chars as $char ) {
			if ( isset( $digits[ $char ] ) ) {
				$current = $digits[ $char ];
			} elseif ( isset( $units[ $char ] ) ) {
				$unit_val = $units[ $char ];
				if ( $current === 0 ) {
					$current = 1;
				}

				if ( $unit_val >= 10000 ) {
					$total   = ( $total + $current ) * $unit_val;
					$current = 0;
				} else {
					$total  += $current * $unit_val;
					$current = 0;
				}
			}
		}

		$total += $current;
		return $total > 0 ? $total : null;
	}

	/**
	 * 清洗文本行：去除行首行尾的半角/全角空格、制表符及各类不可见 Unicode 字符
	 *
	 * @param string $str 待清洗的字符串
	 * @return string
	 */
	public static function clean_line( $str ) {
		return preg_replace( '/^[\p{Z}\s\x{200b}\x{feff}]+|[\p{Z}\s\x{200b}\x{feff}]+$/u', '', (string) $str );
	}

	/**
	 * 从文本行中识别并提取分卷信息
	 *
	 * @param string $line 文本行或章节标题
	 * @return array|null 包含 [ 'vol_idx' => int, 'vol_name' => string ] 或 null
	 */
	public static function extract_volume( $line ) {
		$line = self::clean_line( $line );
		if ( empty( $line ) ) {
			return null;
		}

		// 1. 复合形式：第X卷/卷X/第X部/部X [卷名] 第Y章 [章名]
		if ( preg_match( '/(?:^|[\s(（【\[])(?:(?:第\s*)?([0-9零一二两三四五六七八九十百千万]+)\s*[卷部集册]|[卷部集册]\s*(?:第\s*)?([0-9零一二两三四五六七八九十百千万]+))\s*(.*?)(?=\s*第|\s*$)/u', $line, $m ) ) {
			$vol_num_str = ! empty( $m[1] ) ? $m[1] : ( ! empty( $m[2] ) ? $m[2] : '1' );
			$vol_num     = self::chinese_to_arabic( $vol_num_str ) ?: 1;
			$vol_title   = isset( $m[3] ) ? trim( $m[3] ) : '';
			$vol_title   = preg_replace( '/^[·\s\-_:：|]+|[·\s\-_:：|]+$/u', '', $vol_title );

			$full_name = '第' . $vol_num . '卷' . ( $vol_title ? ' ' . $vol_title : '' );
			return array(
				'vol_idx'  => $vol_num,
				'vol_name' => $full_name,
			);
		}

		// 2. 独立分卷行：第X卷 / 卷X / 第X部 / 部X / Volume X / Vol.X
		if ( preg_match( '/^(?:(?:第\s*)?([0-9零一二两三四五六七八九十百千万]+)\s*[卷部集册]|[卷部集册]\s*(?:第\s*)?([0-9零一二两三四五六七八九十百千万]+)|(?:Vol(?:ume)?\.?\s*([0-9]+)))\s*(.*?)$/ui', $line, $m ) ) {
			$vol_num_str = ! empty( $m[1] ) ? $m[1] : ( ! empty( $m[2] ) ? $m[2] : ( ! empty( $m[3] ) ? $m[3] : '1' ) );
			$vol_num     = self::chinese_to_arabic( $vol_num_str ) ?: 1;
			$vol_title   = isset( $m[4] ) ? trim( $m[4] ) : '';
			$vol_title   = preg_replace( '/^[·\s\-_:：|]+|[·\s\-_:：|]+$/u', '', $vol_title );

			$full_name = '第' . $vol_num . '卷' . ( $vol_title ? ' ' . $vol_title : '' );
			return array(
				'vol_idx'  => $vol_num,
				'vol_name' => $full_name,
			);
		}

		return null;
	}

	/**
	 * 从标题或文本中提取章节序号
	 *
	 * @param string $title 章节标题
	 * @return int|null 章节序号；若为序言/楔子返回 0；若为番外返回 99000+；无法提取返回 null
	 */
	public static function extract_chapter_number( $title ) {
		$title = self::clean_line( $title );
		if ( empty( $title ) ) {
			return null;
		}

		// 容错前导装饰括号：如 【第一回】 或 [第一回]
		$test_title = preg_replace( '/^[【\[（\(《\s]+/u', '', $title );

		// 1. 序言/楔子/作品相关 -> 0
		if ( preg_match( '/^(楔子|序章|序言|前言|简介|内容简介|人物介绍|作品相关|引子)/u', $test_title ) ) {
			return 0;
		}

		// 2. 尾声/后记/完结感言/终章 -> 99999
		if ( preg_match( '/^(尾声|后记|完结感言|后续|终章|大结局)/u', $test_title ) ) {
			return 99999;
		}

		// 3. 番外章节：番外1 / 番外篇 第X章 -> 99000 + 序号
		if ( preg_match( '/番外/u', $test_title ) ) {
			if ( preg_match( '/番外\s*(\d+)/u', $test_title, $m ) ) {
				return 99000 + intval( $m[1] );
			}
			if ( preg_match( '/番外\s*第\s*([0-9零一二两三四五六七八九十百千万]+)\s*[章节话回折集篇幕]/u', $test_title, $m ) ) {
				$num = self::chinese_to_arabic( $m[1] );
				return 99000 + ( $num ?: 1 );
			}
			return 99001;
		}

		// 4. 标准格式：第X章 / 第X节 / 第X回 / 第X话 / 第X折 / 第X集 / 第X篇 / 第X幕
		if ( preg_match( '/第\s*([0-9零一二两三四五六七八九十百千万]+)\s*[章节回话折集篇幕]/u', $test_title, $m ) ) {
			return self::chinese_to_arabic( $m[1] );
		}

		// 5. 数字开头：如 "123 回归都市" 或 "123、回归" 或 "123.回归"
		if ( preg_match( '/^(\d+)[\s、.．_\-:：]/u', $test_title, $m ) ) {
			return intval( $m[1] );
		}

		// 6. 回X / 卷X / 篇X
		if ( preg_match( '/^[回卷篇]\s*([0-9零一二两三四五六七八九十百千万]+)/u', $test_title, $m ) ) {
			return self::chinese_to_arabic( $m[1] );
		}

		// 7. 纯英文 Chapter X
		if ( preg_match( '/^Chapter\s*(\d+)/ui', $test_title, $m ) ) {
			return intval( $m[1] );
		}

		return null;
	}

	/**
	 * 格式化章节排序索引
	 *
	 * @param int    $vol_idx   分卷序号 (如 1)
	 * @param int    $chap_idx  章节序号 (如 5)
	 * @param string $format    格式掩码 (如 "01-00001")
	 * @param string $connector 连接符 (如 "-")
	 * @return string 格式化后的字符串 (如 "01-00005")
	 */
	public static function format_chapter_index( $vol_idx, $chap_idx, $format = '01-00001', $connector = '-' ) {
		$parts = explode( '-', $format );
		if ( count( $parts ) >= 2 ) {
			$vol_pad  = strlen( $parts[0] );
			$chap_pad = strlen( $parts[1] );
		} else {
			$vol_pad  = 2;
			$chap_pad = 5;
		}

		$vol_part  = str_pad( (string) intval( $vol_idx ), $vol_pad, '0', STR_PAD_LEFT );
		$chap_part = str_pad( (string) intval( $chap_idx ), $chap_pad, '0', STR_PAD_LEFT );

		return $vol_part . $connector . $chap_part;
	}

	/**
	 * 判断一行文本是否是章节标题行（用于纯文本 TXT 解析）
	 *
	 * @param string $line 待检测的文本行
	 * @return bool
	 */
	public static function is_chapter_heading( $line ) {
		$line = self::clean_line( $line );
		$len  = mb_strlen( $line, 'UTF-8' );

		// 标题通常不会过长（小于 60 字符）
		if ( $len === 0 || $len > 60 ) {
			return false;
		}

		// 排除标点结尾的长段落
		if ( preg_match( '/[。！？!?…]$/u', $line ) && $len > 25 ) {
			return false;
		}

		// 容错前导装饰括号：如 【第一回】 或 [第一回]
		$test_line = preg_replace( '/^[【\[（\(《\s]+/u', '', $line );

		// 特殊章节名判断
		if ( preg_match( '/^(楔子|序章|序言|前言|简介|内容简介|人物介绍|作品相关|引子|尾声|后记|完结感言|后续|终章|大结局|番外)/u', $test_line ) ) {
			return true;
		}

		// 第X章 / 第X节 / 第X回 / 第X话 / 第X折 / 第X集 / 第X篇 / 第X幕
		if ( preg_match( '/^第\s*[0-9零一二两三四五六七八九十百千万]+\s*[章节回话折集篇幕]/u', $test_line ) ) {
			return true;
		}

		// 数字开头且后面带有中文或空格/标点：如 "1 第一章" 或 "1、初入江湖" 或 "01 梦觉渡头"
		if ( preg_match( '/^\d+[\s、.．_\-:：]/u', $test_line ) ) {
			return true;
		}

		// 回X / 卷X / 篇X 开头
		if ( preg_match( '/^[回卷篇]\s*[0-9零一二两三四五六七八九十百千万]+/u', $test_line ) ) {
			return true;
		}

		// Chapter X
		if ( preg_match( '/^Chapter\s*\d+/ui', $test_line ) ) {
			return true;
		}

		return false;
	}

	/**
	 * 批量重新计算并更新章节数组的章节索引号 (00-00000 格式)
	 * 优先尊重显式分卷，若为默认正文则智能从章节标题中流式识别分卷并向下继承
	 *
	 * @param array $chapters 章节数组
	 * @return array 重新计算分配 chapter_index, vol_idx, chap_num 后的章节数组
	 */
	public static function recalculate_chapter_indexes( $chapters ) {
		if ( empty( $chapters ) || ! is_array( $chapters ) ) {
			return array();
		}

		$current_vol_idx  = 1;
		$current_vol_name = '正文';
		$custom_vol_map   = array();
		$auto_vol_idx     = 1;
		$chap_counter     = 1;
		$result           = array();

		foreach ( $chapters as $c ) {
			$raw_volume = isset( $c['volume'] ) ? trim( sanitize_text_field( $c['volume'] ) ) : '';
			$title      = isset( $c['title'] ) ? trim( sanitize_text_field( $c['title'] ) ) : '';

			// 1. 判断分卷归属
			// A. 如果章节指定了明确的非“正文”分卷（例如用户手动批量设置或已识别的分卷）
			if ( ! empty( $raw_volume ) && '正文' !== $raw_volume && '-' !== $raw_volume ) {
				$current_vol_name = $raw_volume;
				$vol_info         = self::extract_volume( $raw_volume );
				if ( $vol_info && ! empty( $vol_info['vol_idx'] ) ) {
					$current_vol_idx = intval( $vol_info['vol_idx'] );
				} else {
					if ( ! isset( $custom_vol_map[ $raw_volume ] ) ) {
						$custom_vol_map[ $raw_volume ] = $auto_vol_idx++;
					}
					$current_vol_idx = $custom_vol_map[ $raw_volume ];
				}
			} else {
				// B. 如果分卷为空或默认“正文”，尝试从章节标题中流式识别分卷
				$vol_info = self::extract_volume( $title );
				if ( $vol_info && ! empty( $vol_info['vol_idx'] ) ) {
					$current_vol_idx  = intval( $vol_info['vol_idx'] );
					$current_vol_name = $vol_info['vol_name'];
				}
			}

			// 2. 提取章节号
			$chap_num = self::extract_chapter_number( $title );
			if ( 0 === $chap_num ) {
				$use_vol_idx  = 0;
				$use_chap_idx = 0;
			} elseif ( 99999 === $chap_num ) {
				$use_vol_idx  = $current_vol_idx;
				$use_chap_idx = 99999;
			} elseif ( $chap_num !== null ) {
				$use_vol_idx  = $current_vol_idx;
				$use_chap_idx = $chap_num;
				if ( $chap_num > 0 && $chap_num < 90000 ) {
					$chap_counter = $chap_num + 1;
				}
			} else {
				$use_vol_idx  = $current_vol_idx;
				$use_chap_idx = $chap_counter++;
			}

			$c['vol_idx']       = $use_vol_idx;
			$c['chap_num']      = $use_chap_idx;
			$c['volume']        = $current_vol_name;
			$c['chapter_index'] = self::format_chapter_index( $use_vol_idx, $use_chap_idx );

			$result[] = $c;
		}

		return $result;
	}
}
