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

		// 特殊数词归一化：廿 (20), 卅 (30), 卌 (40)
		$special_map = array(
			'廿' => '二十',
			'卅' => '三十',
			'卌' => '四十',
		);
		$str         = strtr( $str, $special_map );

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

		// 遍历解析（支持进位单位词与直读数码流式解析）
		$total   = 0;
		$current = 0;
		$chars   = preg_split( '//u', $str, -1, PREG_SPLIT_NO_EMPTY );

		foreach ( $chars as $char ) {
			if ( isset( $digits[ $char ] ) ) {
				$current = $current * 10 + $digits[ $char ];
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
		if ( preg_match( '/(?:^|[\s(（【\[])(?:(?:第\s*)?([0-9零一二两三四五六七八九十百千万廿卅卌]+)\s*[卷部集册]|[卷部集册]\s*(?:第\s*)?([0-9零一二两三四五六七八九十百千万廿卅卌]+))\s*(.*?)(?=\s*第|\s*$)/u', $line, $m ) ) {
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
		if ( preg_match( '/^(?:(?:第\s*)?([0-9零一二两三四五六七八九十百千万廿卅卌]+)\s*[卷部集册]|[卷部集册]\s*(?:第\s*)?([0-9零一二两三四五六七八九十百千万廿卅卌]+)|(?:Vol(?:ume)?\.?\s*([0-9]+)))\s*(.*?)$/ui', $line, $m ) ) {
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

		// 3. 番外/外传类独立分卷行
		if ( preg_match( '/^[【\[（\(《\s]*(?:番外|番外篇|外传|后传|前传|别传|新传|特别篇|作品相关)[\s】\]）\)》]*(?:[\s:：·\-_]+(.*?))?$/u', $line, $m ) ) {
			$vol_title = isset( $m[1] ) ? trim( $m[1] ) : '';
			$vol_title = preg_replace( '/^[·\s\-_:：|]+|[·\s\-_:：|]+$/u', '', $vol_title );
			$full_name = '番外' . ( $vol_title ? ' ' . $vol_title : '' );
			return array(
				'vol_idx'  => 99,
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

		// 3. 番外/外传章节：番外1 / 番外篇·第一章 / 外传 第一章 -> 99000 + 序号
		if ( preg_match( '/(?:番外|外传|后传|前传)(?:篇)?[\s·\-_:：]*第?\s*([0-9零一二两三四五六七八九十百千万廿卅卌]+)\s*[章节话回折集篇幕]?/u', $test_title, $m ) ) {
			$num = self::chinese_to_arabic( $m[1] );
			return 99000 + ( $num ?: 1 );
		}
		if ( preg_match( '/^(?:番外|外传|后传|前传)/u', $test_title ) ) {
			return 99001;
		}

		// 4. 标准格式：第X章 / 第X节 / 第X回 / 第X话 / 第X折 / 第X集 / 第X篇 / 第X幕
		if ( preg_match( '/第\s*([0-9零一二两三四五六七八九十百千万廿卅卌]+)\s*[章节回话折集篇幕]/u', $test_title, $m ) ) {
			return self::chinese_to_arabic( $m[1] );
		}

		// 5. 数字开头：如 "123 回归都市" 或 "123、回归" 或 "123.回归"
		if ( preg_match( '/^(\d+)[\s、.．_\-:：]/u', $test_title, $m ) ) {
			return intval( $m[1] );
		}

		// 6. 回X / 卷X / 篇X
		if ( preg_match( '/^[回卷篇]\s*([0-9零一二两三四五六七八九十百千万廿卅卌]+)/u', $test_title, $m ) ) {
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

		// 排除误判词根：当检测到以“第X节”开头紧跟常用量词/词素，或“第X部”开头紧跟分/队/门/位/长等，排除为非章节标题
		if ( preg_match( '/^第\s*[0-9零一二两三四五六七八九十百千万廿卅卌]+\s*节\s*(课|点|日|天|次|步|个|分钟|秒|轮|期|名)/u', $test_line ) ) {
			return false;
		}
		if ( preg_match( '/^第\s*[0-9零一二两三四五六七八九十百千万廿卅卌]+\s*部\s*(分|队|门|位|长)/u', $test_line ) ) {
			return false;
		}

		// 特殊章节名判断（排除独立简介标记，使其归入小说简介提取）
		if ( preg_match( '/^(楔子|序章|序言|前言|人物介绍|作品相关|引子|尾声|后记|完结感言|后续|终章|大结局|番外)/u', $test_line ) ) {
			return true;
		}

		// 第X章 / 第X节 / 第X回 / 第X话 / 第X折 / 第X集 / 第X篇 / 第X幕 / 第X部
		if ( preg_match( '/^第\s*[0-9零一二两三四五六七八九十百千万廿卅卌]+\s*[章节回话折集篇幕部]/u', $test_line ) ) {
			return true;
		}

		// 数字开头且后面带有中文或空格/标点：如 "1 第一章" 或 "1、初入江湖" 或 "01 梦觉渡头"
		if ( preg_match( '/^\d+[\s、.．_\-:：]/u', $test_line ) ) {
			return true;
		}

		// 回X / 卷X / 篇X 开头
		if ( preg_match( '/^[回卷篇]\s*[0-9零一二两三四五六七八九十百千万廿卅卌]+/u', $test_line ) ) {
			return true;
		}

		// Chapter X
		if ( preg_match( '/^Chapter\s*\d+/ui', $test_line ) ) {
			return true;
		}

		return false;
	}

	/**
	 * 清洗小说书名（去除作者名、版本修饰、状态括号与杂质符号）
	 *
	 * @param string $raw_name 原始名称或文件名
	 * @param string $author   已知作者名（可选）
	 * @return string 清洗后的纯净书名
	 */
	public static function clean_novel_title( $raw_name, $author = '' ) {
		if ( empty( $raw_name ) ) {
			return '';
		}

		$title = trim( (string) $raw_name );

		// 0. 去除常见文件扩展名
		$title = preg_replace( '/\.(?:txt|docx|doc|pdf|epub)$/iu', '', $title );

		// 1. 优先提取《》内的纯书名（如《斗破苍穹》作者：天蚕土豆.txt -> 提取《斗破苍穹》）
		if ( preg_match( '/《([^》]{1,50})》/u', $title, $m ) ) {
			$title = trim( $m[1] );
		}

		// 2. 剥离前置分类/类型修饰括号块，如 【玄幻】、【都市修真】、[科幻] 等
		$title = preg_replace( '/^[\[【(（][^\]】)）]{1,10}[\]】)）]\s*/u', '', $title );

		// 3. 剥离版本/状态修饰括号块，如 (精校全本)、[完结]、【TXT精校】、(校对版)、(第1-500章)
		$mod_pattern = '/[\(（\[【][^\)）\]】]*(?:Checked|checked|精校|校对|全本|全集|完结|完本|整理|未删减|更新|TXT|txt|分卷|第[0-9一二三四五六七八九十]+卷|[0-9]+-[0-9]+章)[^\)）\]】]*[\)）\]】]/u';
		$title       = preg_replace( $mod_pattern, '', $title );

		// 4. 剥离作者后缀或连字符分隔部分
		if ( ! empty( $author ) ) {
			$escaped_author = preg_quote( trim( $author ), '/' );
			$title          = preg_replace( '/[-_\s]*(?:作者|著|文)?[:：\s]*' . $escaped_author . '.*$/u', '', $title );
		}
		// 常见通用作者剥离模式（如 " - 卖报小郎君"、" 作者：辰东"）
		$title = preg_replace( '/[-_\s]+(?:作者|著|文)?[:：\s]*[^\s_\-\(\)\[\]（）【】]{2,20}$/u', '', $title );
		$title = preg_replace( '/(?:作者|著|文)[:：\s]+[^\s_\-\(\)\[\]（）【】]{2,20}$/u', '', $title );

		// 5. 剥离书名号、括号残留及多余首尾符号
		$title = preg_replace( '/^[《【\[(（\s\-_]+|[》】\])）\s\-_]+$/u', '', $title );

		return trim( $title );
	}

	/**
	 * 从文档头部或前置文本行中提取作者、纯书名、分类与小说简介
	 *
	 * @param array|string $pre_chapter_lines 首章前文本行（数组或单文本）
	 * @param string       $filename          文件名（用于辅助提取作者与书名）
	 * @return array array( 'author' => string, 'intro' => string, 'title' => string, 'category' => string )
	 */
	public static function extract_author_and_intro( $pre_chapter_lines, $filename = '' ) {
		if ( is_string( $pre_chapter_lines ) ) {
			$lines = preg_split( '/\r\n|\r|\n/u', $pre_chapter_lines );
			if ( ! is_array( $lines ) || empty( $lines ) ) {
				$normalized = str_replace( array( "\r\n", "\r" ), "\n", $pre_chapter_lines );
				$lines      = explode( "\n", $normalized );
			}
		} elseif ( is_array( $pre_chapter_lines ) ) {
			$lines = $pre_chapter_lines;
		} else {
			$lines = array();
		}

		$author            = '';
		$clean_title       = '';
		$category          = '';
		$intro_lines       = array();
		$current_intro_len = 0;

		// 1. 从文件名识别作者与书名（如《斗破苍穹》作者：天蚕土豆.txt、斗罗大陆(唐家三少著).txt、大奉打更人 - 卖报小郎君.txt 等）
		if ( ! empty( $filename ) ) {
			$raw_fn = pathinfo( $filename, PATHINFO_FILENAME );

			// 提取文件名中的作者
			if ( preg_match( '/(?:作者|著|文)[\s:：]+([^\s_\-\(\)\[\]（）【】]+)/u', $raw_fn, $m ) ) {
				$author = trim( $m[1] );
			} elseif ( preg_match( '/[\(（\[【]([^\s_\-\(\)\[\]（）【】]+)\s*(?:著|文|作品)[\)）\]】]/u', $raw_fn, $m ) ) {
				$author = trim( $m[1] );
			} elseif ( preg_match( '/^《?([^》]+)》?\s*[-_]\s*([^\s_\-\(\)\[\]（）【】]+)$/u', $raw_fn, $m ) ) {
				$clean_title = trim( $m[1] );
				$author      = trim( $m[2] );
			}

			// 如果书名尚未由上面确定，提取并清洗书名
			if ( empty( $clean_title ) ) {
				$clean_title = self::clean_novel_title( $raw_fn, $author );
			}
		}

		// 2. 遍历前置行识别作者、分类、清洗元数据、精准定位简介
		$in_intro_block   = false;
		$explicit_intro   = array();
		$metadata_pattern = '/(校对|精校|排版|制作|首发|字数|更新时间|最后更新|TXT下载|整理制作|更多精校|版权声明|录入|来源|首发网)/u';

		foreach ( $lines as $raw_line ) {
			$line = self::clean_line( $raw_line );
			if ( $line === '' ) {
				continue;
			}

			// 关键防护：一旦遇到章节标题或分卷标题，说明已进入正文阶段，立即终止头部与简介扫描！
			if ( self::is_chapter_heading( $line ) || self::extract_volume( $line ) !== null ) {
				break;
			}

			// A. 匹配文本中的作者行（支持 "作者：XXX"、"【作　者】XXX"、"著：XXX"、"文 / XXX"、"文：XXX"）
			if ( empty( $author ) ) {
				if ( preg_match( '/^(?:【?\s*(?:作\s*者|著\s*者|文\s*\/\s*|文\s*：|著)\s*】?)\s*[:：]?\s*([^\s,，。]+)/u', $line, $am ) ) {
					$author = trim( preg_replace( '/^[【\[(（\s]+|[】\])）\s]+$/u', '', $am[1] ) );
					continue;
				} elseif ( preg_match( '/^([^:：\s]{2,10})\s+(?:著|编著|作品)$/u', $line, $am ) ) {
					$author = trim( $am[1] );
					continue;
				}
			} else {
				// 已有作者时，跳过重复出现的作者行
				if ( preg_match( '/^(?:【?\s*(?:作\s*者|著\s*者|文\s*\/\s*|文\s*：|著)\s*】?)\s*[:：]?/u', $line ) || preg_match( '/(?:著|编著|作品)$/u', $line ) ) {
					continue;
				}
			}

			// B. 识别分类/类型（如 "分类：玄幻魔法"、"类别：仙侠修真"、"类型：都市"）
			if ( empty( $category ) ) {
				if ( preg_match( '/^(?:【?\s*(?:分\s*类|类\s*别|类\s*型|属\s*性)\s*】?)\s*[:：]\s*([^\s,，。]+)/u', $line, $cm ) ) {
					$category = trim( preg_replace( '/^[【\[(（\s]+|[】\])）\s]+$/u', '', $cm[1] ) );
					continue;
				}
			}

			// C. 排除纯书名行（如《书名》或 书名：XXX）
			if ( preg_match( '/^(?:【?\s*书\s*名\s*】?)\s*[:：]?\s*(.+)$/u', $line, $tm ) ) {
				if ( empty( $clean_title ) ) {
					$clean_title = self::clean_novel_title( $tm[1], $author );
				}
				continue;
			}
			if ( preg_match( '/^《([^》]{1,40})》$/u', $line, $tm ) ) {
				if ( empty( $clean_title ) ) {
					$clean_title = self::clean_novel_title( $tm[1], $author );
				}
				continue;
			}

			// D. 排除校对/排版等元数据行（通常短于 50 字符）
			if ( preg_match( $metadata_pattern, $line ) && mb_strlen( $line, 'UTF-8' ) < 50 ) {
				continue;
			}

			// E. 检测显式简介标记（如 "内容简介"、"【作品简介】"、"简介："、"文案"）
			if ( preg_match( '/^(?:【?\s*(?:内容简介|作品简介|书籍简介|文案|简介)\s*】?)\s*[:：]?\s*(.*)$/u', $line, $im ) ) {
				$in_intro_block = true;
				if ( ! empty( $im[1] ) ) {
					$explicit_intro[]   = trim( $im[1] );
					$current_intro_len += mb_strlen( $im[1], 'UTF-8' );
				}
				continue;
			}

			// F. 收集简介内容
			if ( $in_intro_block ) {
				$explicit_intro[]   = $line;
				$current_intro_len += mb_strlen( $line, 'UTF-8' );
			} else {
				$intro_lines[]      = $line;
				$current_intro_len += mb_strlen( $line, 'UTF-8' );
			}

			// 字数上限防膨胀兜底：简介长度达到或超过 1200 字符时立即终止扫描
			if ( $current_intro_len >= 1200 ) {
				break;
			}
		}

		$final_intro_arr = ! empty( $explicit_intro ) ? $explicit_intro : $intro_lines;
		$intro           = trim( implode( "\n\n", $final_intro_arr ) );

		// 确保书名纯净无瑕
		if ( ! empty( $clean_title ) ) {
			$clean_title = self::clean_novel_title( $clean_title, $author );
		}

		return array(
			'author'   => $author,
			'intro'    => $intro,
			'title'    => $clean_title,
			'category' => $category,
		);
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

			// 1. 判断分卷归属（显式分卷优先且独立识别，杜绝向后盲目串改未选章节）
			if ( '' !== $raw_volume && '-' !== $raw_volume ) {
				if ( '正文' === $raw_volume ) {
					$current_vol_name = '正文';
					$current_vol_idx  = 1;
				} else {
					$current_vol_name = $raw_volume;
					if ( preg_match( '/(?:番外|外传|后传|前传|别传|新传|特别篇|作品相关)/u', $raw_volume ) ) {
						$current_vol_idx = 99;
					} else {
						$vol_info = self::extract_volume( $raw_volume );
						if ( $vol_info && ! empty( $vol_info['vol_idx'] ) ) {
							$current_vol_idx = intval( $vol_info['vol_idx'] );
						} elseif ( ! preg_match( '/[0-9一二两三四五六七八九十百千万廿卅卌]/u', $raw_volume ) ) {
							// 无明确分卷数字标号时，统一归入 99
							$current_vol_idx = 99;
						} else {
							if ( ! isset( $custom_vol_map[ $raw_volume ] ) ) {
								$custom_vol_map[ $raw_volume ] = $auto_vol_idx++;
							}
							$current_vol_idx = $custom_vol_map[ $raw_volume ];
						}
					}
				}
			} else {
				// 分卷为空或 '-' 时，尝试从章节标题中流式识别分卷；若未识别且当前分卷为空则兜底为“正文”
				$vol_info = self::extract_volume( $title );
				if ( $vol_info && ! empty( $vol_info['vol_idx'] ) ) {
					$current_vol_idx  = intval( $vol_info['vol_idx'] );
					$current_vol_name = $vol_info['vol_name'];
				} elseif ( empty( $current_vol_name ) ) {
					$current_vol_name = '正文';
					$current_vol_idx  = 1;
				}
			}

			// 2. 提取章节号
			$chap_num = self::extract_chapter_number( $title );
			if ( 0 === $chap_num ) {
				$use_vol_idx  = ( 99 === $current_vol_idx ) ? 99 : 0;
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

			// 特殊规则：当处于番外卷 (vol_idx === 99) 时，若章节号为普通正数序号 ( > 0 && < 90000 )，提升为 99000 + $chap_num
			if ( 99 === $use_vol_idx && $use_chap_idx > 0 && $use_chap_idx < 90000 ) {
				$use_chap_idx = 99000 + $use_chap_idx;
			}

			$c['vol_idx']       = $use_vol_idx;
			$c['chap_num']      = $use_chap_idx;
			$c['volume']        = $current_vol_name;
			$c['chapter_index'] = self::format_chapter_index( $use_vol_idx, $use_chap_idx );

			$result[] = $c;
		}

		return $result;
	}

	/**
	 * 刷新指定小说及前台静态页面缓存
	 *
	 * @param int $novel_id 小说文章 ID
	 */
	public static function purge_novel_cache( $novel_id ) {
		$novel_id = absint( $novel_id );
		if ( $novel_id <= 0 ) {
			return;
		}

		// 1. 清除 WordPress 原生文章与对象缓存
		clean_post_cache( $novel_id );

		// 2. 刷新 WP Super Cache 页面静态文件缓存
		if ( function_exists( 'wpsc_delete_post_cache' ) ) {
			wpsc_delete_post_cache( $novel_id );
		} elseif ( function_exists( 'wp_cache_post_change' ) ) {
			wp_cache_post_change( $novel_id );
		}

		// 3. 兼容主流缓存插件（LiteSpeed Cache 等）
		if ( has_action( 'litespeed_purge_post' ) ) {
			do_action( 'litespeed_purge_post', $novel_id );
		}
	}
}

