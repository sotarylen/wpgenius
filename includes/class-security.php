<?php
/**
 * Security utilities
 *
 * Provides symmetric encryption/decryption utilities for sensitive data (API keys, database passwords, etc.).
 *
 * Design notes:
 * - The key is derived from wp_salt('auth'), unique per site, with no extra key storage needed;
 * - libsodium is preferred (sodium_crypto_secretbox, PHP 7.2+), with OpenSSL AES-256-GCM as fallback;
 * - Ciphertext carries the version prefix "w2p_enc:v1:"; decrypt() returns null for non-encrypted formats (legacy base64/plaintext),
 *   allowing callers to perform lazy migration;
 * - Each ciphertext uses its own random nonce/IV.
 *
 * @package WP_Genius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class W2P_Crypto
 */
class W2P_Crypto {

	/**
	 * Encrypted value version prefix.
	 *
	 * @var string
	 */
	const PREFIX = 'w2p_enc:v1:';

	/**
	 * Encrypt a plaintext value.
	 *
	 * @param string $plain Plaintext.
	 * @return string Encrypted value ('' when input is empty).
	 */
	public static function encrypt( $plain ) {
		if ( '' === (string) $plain ) {
			return '';
		}

		$key = self::get_key();

		// Preferred: libsodium (bundled with PHP 7.2+).
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( (string) $plain, $nonce, $key );
			return self::PREFIX . 's:' . base64_encode( $nonce . $cipher );
		}

		// Fallback: OpenSSL AES-256-GCM.
		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv     = random_bytes( 12 );
			$tag    = '';
			$cipher = openssl_encrypt( (string) $plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
			if ( false !== $cipher ) {
				return self::PREFIX . 'o:' . base64_encode( $iv . $tag . $cipher );
			}
		}

		// Last resort: base64 obfuscation (only when neither ext is available).
		return self::PREFIX . 'b:' . base64_encode( (string) $plain );
	}

	/**
	 * Decrypt a value previously produced by encrypt().
	 *
	 * @param string $value Encrypted value.
	 * @return string|false|null
	 *   - string: decrypted plaintext;
	 *   - false:  value had an encrypted prefix but decryption failed (tampered / wrong salt);
	 *   - null:   value is not in encrypted format (legacy base64 or plaintext).
	 */
	public static function decrypt( $value ) {
		$value = (string) $value;

		if ( '' === $value || 0 !== strpos( $value, self::PREFIX ) ) {
			return null; // Not encrypted (legacy data).
		}

		$payload = substr( $value, strlen( self::PREFIX ) );
		$algo    = isset( $payload[0] ) ? $payload[0] : '';
		$raw     = base64_decode( substr( $payload, 2 ), true );

		if ( false === $raw ) {
			return false;
		}

		$key = self::get_key();

		switch ( $algo ) {
			case 's': // libsodium
				$nonce_len = defined( 'SODIUM_CRYPTO_SECRETBOX_NONCEBYTES' ) ? SODIUM_CRYPTO_SECRETBOX_NONCEBYTES : 24;
				if ( strlen( $raw ) < $nonce_len ) {
					return false;
				}
				$nonce  = substr( $raw, 0, $nonce_len );
				$cipher = substr( $raw, $nonce_len );
				return sodium_crypto_secretbox_open( $cipher, $nonce, $key );

			case 'o': // OpenSSL AES-256-GCM
				$iv_len  = 12;
				$tag_len = 16;
				if ( strlen( $raw ) < $iv_len + $tag_len ) {
					return false;
				}
				$iv     = substr( $raw, 0, $iv_len );
				$tag    = substr( $raw, $iv_len, $tag_len );
				$cipher = substr( $raw, $iv_len + $tag_len );
				return openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

			case 'b': // Last resort obfuscation.
				return base64_decode( $payload );

			default:
				return false;
		}
	}

	/**
	 * Derive a 32-byte encryption key from the site auth salt.
	 *
	 * @return string
	 */
	private static function get_key() {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}
}
