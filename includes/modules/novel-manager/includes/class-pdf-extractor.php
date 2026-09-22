<?php
/**
 * 纯 PHP 原生 PDF 文本流提取器 (Zero-Dependency Pure PHP PDF Extractor)
 *
 * @package WPGenius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class W2P_Pdf_Extractor
 */
class W2P_Pdf_Extractor {

	/**
	 * 从 PDF 文件中提取纯文本内容
	 *
	 * @param string $file_path PDF 文件绝对路径
	 * @return string 提取的纯文本
	 */
	public function extract_text( $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return '';
		}

		$pdf = file_get_contents( $file_path );
		if ( empty( $pdf ) ) {
			return '';
		}

		// 1. 查找各字体的 BaseFont 与对应 ToUnicode 对象的关联
		$font_to_unicode = array();
		if ( preg_match_all( '/\/BaseFont\/([^\/\s>]+)[\s\S]*?\/ToUnicode\s+(\d+)/', $pdf, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $r ) {
				$parts                        = explode( '+', $r[1] );
				$font_to_unicode[ $parts[0] ] = intval( $r[2] );
			}
		}

		// 2. 解析各 ToUnicode 对象流并建立各字体的 CMap 字典
		$font_cmaps   = array();
		$global_cmap  = array();
		$parsed_uids  = array();

		foreach ( $font_to_unicode as $prefix => $uid ) {
			if ( isset( $parsed_uids[ $uid ] ) ) {
				$font_cmaps[ $prefix ] = $parsed_uids[ $uid ];
				continue;
			}

			$cmap = $this->extract_cmap_by_uid( $pdf, $uid );
			if ( ! empty( $cmap ) ) {
				$font_cmaps[ $prefix ] = $cmap;
				$parsed_uids[ $uid ]   = $cmap;
				$global_cmap          += $cmap;
			}
		}

		// 3. 提取并解压所有内容流 (Content Streams)
		$streams = $this->extract_content_streams( $pdf );
		if ( empty( $streams ) ) {
			unset( $pdf );
			return '';
		}

		unset( $pdf );

		// 4. 从各内容流中根据选定字体或全局 CMap 解析 BT...ET 文本块
		$extracted_text = '';
		foreach ( $streams as $stream_data ) {
			$block_text = $this->parse_stream_text( $stream_data, $font_cmaps, $global_cmap );
			if ( '' !== $block_text ) {
				$extracted_text .= $block_text . "\n\n";
			}
		}

		return trim( $extracted_text );
	}

	/**
	 * 根据对象 UID 精确定位并解压 CMap 字典
	 *
	 * @param string $pdf PDF 全文
	 * @param int    $uid ToUnicode 对象 ID
	 * @return array
	 */
	private function extract_cmap_by_uid( &$pdf, $uid ) {
		$pos = strpos( $pdf, "{$uid} 0 obj" );
		if ( false === $pos ) {
			return array();
		}

		$s_pos = strpos( $pdf, 'stream', $pos );
		if ( false === $s_pos ) {
			return array();
		}

		$s_pos += 6;
		if ( isset( $pdf[ $s_pos ] ) && "\r" === $pdf[ $s_pos ] ) {
			++$s_pos;
		}
		if ( isset( $pdf[ $s_pos ] ) && "\n" === $pdf[ $s_pos ] ) {
			++$s_pos;
		}

		$e_pos = strpos( $pdf, 'endstream', $s_pos );
		if ( false === $e_pos ) {
			return array();
		}

		$raw = substr( $pdf, $s_pos, $e_pos - $s_pos );
		$dec = $this->decompress_stream( $raw );
		if ( empty( $dec ) ) {
			return array();
		}

		$cmap = array();

		// A. 解析 beginbfchar: <0022> <0041>
		if ( preg_match_all( '/<([0-9a-fA-F]+)>\s+<([0-9a-fA-F]+)>/', $dec, $chars, PREG_SET_ORDER ) ) {
			foreach ( $chars as $c ) {
				$cid          = strtoupper( str_pad( $c[1], 4, '0', STR_PAD_LEFT ) );
				$uni          = hexdec( $c[2] );
				$cmap[ $cid ] = mb_chr( $uni, 'UTF-8' );
			}
		}

		// B. 解析 beginbfrange: <0001> <0010> <0020>
		if ( preg_match_all( '/<([0-9a-fA-F]+)>\s+<([0-9a-fA-F]+)>\s+<([0-9a-fA-F]+)>/', $dec, $ranges, PREG_SET_ORDER ) ) {
			foreach ( $ranges as $r ) {
				$start_cid = hexdec( $r[1] );
				$end_cid   = hexdec( $r[2] );
				$start_uni = hexdec( $r[3] );

				if ( $end_cid >= $start_cid && ( $end_cid - $start_cid ) < 5000 ) {
					for ( $i = 0; $i <= ( $end_cid - $start_cid ); $i++ ) {
						$cid_hex          = strtoupper( str_pad( dechex( $start_cid + $i ), 4, '0', STR_PAD_LEFT ) );
						$cmap[ $cid_hex ] = mb_chr( $start_uni + $i, 'UTF-8' );
					}
				}
			}
		}

		return $cmap;
	}

	/**
	 * 提取所有内容流
	 *
	 * @param string $pdf PDF 全文
	 * @return array 解压后的流字符串列表
	 */
	private function extract_content_streams( &$pdf ) {
		$streams = array();
		$offset  = 0;

		while ( false !== ( $pos = strpos( $pdf, 'stream', $offset ) ) ) {
			$stream_start = $pos + 6;
			if ( isset( $pdf[ $stream_start ] ) && "\r" === $pdf[ $stream_start ] ) {
				++$stream_start;
			}
			if ( isset( $pdf[ $stream_start ] ) && "\n" === $pdf[ $stream_start ] ) {
				++$stream_start;
			}

			$end_pos = strpos( $pdf, 'endstream', $stream_start );
			if ( false === $end_pos ) {
				break;
			}

			$raw_stream = substr( $pdf, $stream_start, $end_pos - $stream_start );
			$offset     = $end_pos + 9;

			$decompressed = $this->decompress_stream( $raw_stream );
			if ( false === $decompressed && false !== strpos( $raw_stream, 'BT' ) ) {
				$decompressed = $raw_stream;
			}
			if ( false !== $decompressed && false !== strpos( $decompressed, 'BT' ) ) {
				$streams[] = $decompressed;
			}
		}

		return $streams;
	}

	/**
	 * 解压 FlateDecode 流
	 *
	 * @param string $data 原始流数据
	 * @return string|false
	 */
	private function decompress_stream( $data ) {
		$uncompressed = @gzuncompress( $data );
		if ( false === $uncompressed ) {
			$uncompressed = @gzinflate( $data );
		}
		return $uncompressed;
	}

	/**
	 * 解析流内部的 BT ... ET 文本操作符并组装为段落文本
	 *
	 * @param string $stream_content 流内容
	 * @param array  $font_cmaps     按字体前缀分组的 CMap 字典
	 * @param array  $global_cmap    全局兜底 CMap
	 * @return string 纯文本
	 */
	private function parse_stream_text( $stream_content, &$font_cmaps, &$global_cmap ) {
		if ( ! preg_match_all( '/BT([\s\S]*?)ET/', $stream_content, $bt_matches ) ) {
			return '';
		}

		$lines = array();

		foreach ( $bt_matches[1] as $block ) {
			// 检测当前文本块设置的字体
			$cur_font = '';
			if ( preg_match( '/\/([A-Za-z0-9]+)\s+[\d.]+\s+Tf/', $block, $fm ) ) {
				$cur_font = $fm[1];
			}

			$cmap = isset( $font_cmaps[ $cur_font ] ) ? $font_cmaps[ $cur_font ] : $global_cmap;

			$block_line = '';

			// 1. 匹配 TJ 数组: [<hex> 0 <hex>] TJ 或 [(text) 20 (text)] TJ
			if ( preg_match_all( '/\[(.*?)\]\s*TJ/s', $block, $tj_matches ) ) {
				foreach ( $tj_matches[1] as $tj_inner ) {
					$block_line .= $this->decode_tj_element( $tj_inner, $cmap, $global_cmap ) . ' ';
				}
			}

			// 2. 匹配单项操作符: Tj, ' (下一行并显示), " (设置字距并换行显示)
			if ( preg_match_all( '/(<[0-9a-fA-F\s]+>|\((?:[^\\\\\)]|\\\\.)*\))\s*(?:Tj|\'|")/s', $block, $tj_single ) ) {
				foreach ( $tj_single[1] as $item ) {
					$block_line .= $this->decode_tj_token( $item, $cmap, $global_cmap ) . "\n";
				}
			}

			$clean = trim( $block_line );
			if ( '' !== $clean ) {
				$lines[] = $clean;
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * 解码 TJ 数组内容
	 *
	 * @param string $inner       TJ 括号内部内容
	 * @param array  $cmap        当前字体 CMap 字典
	 * @param array  $global_cmap 全局兜底 CMap
	 * @return string
	 */
	private function decode_tj_element( $inner, &$cmap, &$global_cmap ) {
		$result = '';
		if ( preg_match_all( '/<([0-9a-fA-F\s]+)>|\(((?:[^\\\\\)]|\\\\.)*)\)/s', $inner, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				if ( ! empty( $m[1] ) ) {
					$result .= $this->decode_hex_string( $m[1], $cmap, $global_cmap );
				} elseif ( isset( $m[2] ) ) {
					$result .= $this->decode_literal_string( $m[2] );
				}
			}
		}
		return $result;
	}

	/**
	 * 解码单个 token
	 *
	 * @param string $token       <HEX> 或 (Text)
	 * @param array  $cmap        当前字体 CMap 字典
	 * @param array  $global_cmap 全局兜底 CMap
	 * @return string
	 */
	private function decode_tj_token( $token, &$cmap, &$global_cmap ) {
		$token = trim( $token );
		if ( strpos( $token, '<' ) === 0 && substr( $token, -1 ) === '>' ) {
			$hex = substr( $token, 1, -1 );
			return $this->decode_hex_string( $hex, $cmap, $global_cmap );
		}
		if ( strpos( $token, '(' ) === 0 && substr( $token, -1 ) === ')' ) {
			$lit = substr( $token, 1, -1 );
			return $this->decode_literal_string( $lit );
		}
		return $this->decode_literal_string( $token );
	}

	/**
	 * 将十六进制字符串通过 CMap 转换为字符
	 *
	 * @param string $hex         十六进制
	 * @param array  $cmap        当前字体 CMap 字典
	 * @param array  $global_cmap 全局兜底 CMap
	 * @return string
	 */
	private function decode_hex_string( $hex, &$cmap, &$global_cmap ) {
		$len = strlen( $hex );
		$out = '';

		// 优先以 4 位十六进制 (2 字节 CID) 为单位在 CMap 中检索
		for ( $i = 0; $i < $len; $i += 4 ) {
			$chunk = strtoupper( substr( $hex, $i, 4 ) );
			if ( isset( $cmap[ $chunk ] ) ) {
				$out .= $cmap[ $chunk ];
			} elseif ( isset( $global_cmap[ $chunk ] ) ) {
				$out .= $global_cmap[ $chunk ];
			} elseif ( isset( $cmap[ substr( $chunk, 0, 2 ) ] ) ) {
				$out .= $cmap[ substr( $chunk, 0, 2 ) ];
				$i   -= 2;
			}
		}

		return $out;
	}

	/**
	 * 解码 PDF 字面量字符串
	 *
	 * @param string $literal 字面量
	 * @return string
	 */
	private function decode_literal_string( $literal ) {
		$literal = str_replace( array( '\\(', '\\)', '\\\\' ), array( '(', ')', '\\' ), $literal );
		$literal = preg_replace_callback(
			'/\\\\([0-7]{1,3})/',
			function ( $m ) {
				return chr( octdec( $m[1] ) );
			},
			$literal
		);

		return mb_convert_encoding( $literal, 'UTF-8', 'UTF-8, GB18030, GBK, ISO-8859-1' );
	}
}
