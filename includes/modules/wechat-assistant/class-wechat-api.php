<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPGenius_Wechat_Api {

	private $app_id;
	private $app_secret;

	public function __construct( $app_id, $app_secret ) {
		$this->app_id     = $app_id;
		$this->app_secret = $app_secret;
	}

	/**
	 * 获取 Access Token
	 */
	public function get_access_token() {
		$transient_key = 'w2p_wechat_access_token';
		$token         = get_transient( $transient_key );

		if ( $token ) {
			return $token;
		}

		$url      = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->app_id}&secret={$this->app_secret}";
		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['errcode'] ) && $body['errcode'] != 0 ) {
			return new WP_Error( 'wechat_api_error', $body['errmsg'], array( 'code' => $body['errcode'] ) );
		}

		if ( isset( $body['access_token'] ) ) {
			set_transient( $transient_key, $body['access_token'], $body['expires_in'] - 200 );
			return $body['access_token'];
		}

		return new WP_Error( 'wechat_api_error', __( 'Failed to retrieve access_token, unknown error.', 'wp-genius' ) );
	}

	/**
	 * 获取 JSAPI Ticket
	 */
	public function get_js_ticket() {
		$transient_key = 'w2p_wechat_js_ticket';
		$ticket        = get_transient( $transient_key );

		if ( $ticket ) {
			return $ticket;
		}

		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$url      = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token={$token}&type=jsapi";
		$response = wp_remote_get( $url );
		$body     = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['ticket'] ) && $body['ticket'] ) {
			set_transient( $transient_key, $body['ticket'], $body['expires_in'] - 200 );
			return $body['ticket'];
		}

		return new WP_Error( 'ticket_fail', __( 'Failed to retrieve jsapi_ticket', 'wp-genius' ) );
	}

	/**
	 * 生成 JSSDK 签名包
	 */
	public function sign_package( $url ) {
		$ticket = $this->get_js_ticket();
		if ( is_wp_error( $ticket ) ) {
			return $ticket;
		}

		$nonceStr  = wp_generate_password( 16, false, false );
		$timestamp = time();
		$string    = "jsapi_ticket={$ticket}&noncestr={$nonceStr}&timestamp={$timestamp}&url={$url}";
		$signature = sha1( $string );

		return array(
			'appId'     => $this->app_id,
			'nonceStr'  => $nonceStr,
			'timestamp' => $timestamp,
			'signature' => $signature,
		);
	}

	/**
	 * 上传图片到微信
	 */
	public function upload_media( $file_path, $type = 'image' ) {
		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', sprintf( __( 'Local file not found: %s', 'wp-genius' ), $file_path ) );
		}

		$url = "https://api.weixin.qq.com/cgi-bin/media/upload?access_token={$token}&type={$type}";

		$boundary     = '----' . uniqid( time() );
		$file_name    = basename( $file_path );
		$file_content = file_get_contents( $file_path );
		$mime_type    = mime_content_type( $file_path );

		$payload = "--$boundary\r\n"
				. "Content-Disposition: form-data; name=\"media\"; filename=\"$file_name\"\r\n"
				. "Content-Type: {$mime_type}\r\n\r\n"
				. $file_content . "\r\n"
				. "--$boundary--\r\n";

		$args = array(
			'headers'     => array(
				'Content-Type' => "multipart/form-data; boundary=$boundary",
			),
			'body'        => $payload,
			'httpversion' => '1.0',
			'sslverify'   => false,
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['media_id'] ) ) {
			return $body['media_id'];
		}

		return new WP_Error( 'upload_error', $body['errmsg'] ?? __( 'Upload failed', 'wp-genius' ) );
	}

	/**
	 * 推送文章到草稿箱
	 */
	public function push_to_draft( $post_id ) {
		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$post    = get_post( $post_id );
		$title   = get_post_meta( $post_id, '_w2p_wechat_share_title', true ) ?: $post->post_title;
		$content = apply_filters( 'the_content', $post->post_content );
		$author  = get_the_author_meta( 'display_name', $post->post_author );
		
		// Handle featured image (thumb_media_id)
		$thumb_media_id = '';
		if ( has_post_thumbnail( $post_id ) ) {
			$thumb_id   = get_post_thumbnail_id( $post_id );
			$thumb_path = get_attached_file( $thumb_id );
			if ( $thumb_path ) {
				$media_res = $this->upload_media( $thumb_path, 'image' );
				if ( ! is_wp_error( $media_res ) ) {
					$thumb_media_id = $media_res;
				}
			}
		}

		// Prepare articles
		$article = array(
			'title'          => $title,
			'author'         => $author,
			'digest'         => $post->post_excerpt,
			'content'        => $content,
			'thumb_media_id' => $thumb_media_id,
		);

		$body = array(
			'articles' => array( $article ),
		);

		$url = "https://api.weixin.qq.com/cgi-bin/draft/add?access_token={$token}";

		$response = wp_remote_post( $url, array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $body, JSON_UNESCAPED_UNICODE ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$result = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $result['media_id'] ) ) {
			return $result['media_id'];
		}

		return new WP_Error( 'push_error', $result['errmsg'] ?? __( 'Push failed', 'wp-genius' ) );
	}
}
