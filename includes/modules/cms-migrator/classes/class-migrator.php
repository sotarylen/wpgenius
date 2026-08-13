<?php
/**
 * CMS Migrator Core Logic
 *
 * @package WP_Genius
 * @subpackage Modules/CMSMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CMS_Migrator
 */
class CMS_Migrator {

	/**
	 * DB Connector
	 *
	 * @var CMS_DB_Connector
	 */
	private $db;

	/**
	 * Media Importer
	 *
	 * @var CMS_Media_Importer
	 */
	private $media;

	/**
	 * Logger
	 *
	 * @var CMS_Migration_Logger
	 */
	private $logger;

	/**
	 * Migration status
	 *
	 * @var string
	 */
	private $status = 'idle';

	/**
	 * Migrated items log
	 *
	 * @var array
	 */
	private $migrated = array();

	/**
	 * Current mappings
	 *
	 * @var array
	 */
	private $mappings = array();

	/**
	 * Batch limit per type
	 *
	 * @var int
	 */
	private $batch_limit = 0;

	/**
	 * Constructor
	 *
	 * @param CMS_DB_Connector    $db Database connector.
	 * @param CMS_Media_Importer  $media Media importer.
	 * @param CMS_Migration_Logger $logger Logger.
	 */
	public function __construct( CMS_DB_Connector $db, CMS_Media_Importer $media, CMS_Migration_Logger $logger ) {
		$this->db     = $db;
		$this->media  = $media;
		$this->logger = $logger;
	}

	/**
	 * Start migration
	 *
	 * @param array $types Array of content types to migrate.
	 * @param array $mappings Post type and taxonomy mappings.
	 * @param int   $batch_limit Max items per content type (0 = unlimited).
	 * @return true|WP_Error
	 */
	public function start_migration( array $types = array(), array $mappings = array(), $batch_limit = 0 ) {
		if ( $this->is_running() ) {
			return new WP_Error( 'already_running', __( 'Migration is already in progress.', 'wp-genius' ) );
		}

		$this->set_status( 'running' );
		$this->logger->clear();
		$this->migrated    = array();
		$this->mappings    = $mappings;
		$this->batch_limit = $batch_limit;

		if ( empty( $types ) ) {
			$types = array( 'books', 'albums', 'models', 'studios' );
		}

		$this->logger->log( 'Migration started for: ' . implode( ', ', $types ), 'info' );

		$this->register_post_types();

		// Initialize per-type progress.
		$this->init_type_progress( $types );

		try {
			$ordered = array( 'books', 'albums', 'models', 'studios' );
			foreach ( $ordered as $type ) {
				if ( 'stopped' === $this->status ) {
					break;
				}
				if ( ! in_array( $type, $types, true ) ) {
					$this->set_type_status( $type, 'skipped' );
					continue;
				}

				$this->set_type_status( $type, 'running' );

				switch ( $type ) {
					case 'books':
						$this->migrate_books();
						break;
					case 'albums':
						$this->migrate_albums();
						break;
					case 'models':
						$this->migrate_models();
						break;
					case 'studios':
						$this->migrate_studios();
						break;
				}

				if ( 'stopped' !== $this->status ) {
					$this->set_type_status( $type, 'completed' );
				}
			}

			$this->set_status( 'completed' );
			$this->logger->log( 'Migration completed successfully!', 'success' );
			$this->save_migration_log();
			return true;
		} catch ( \Exception $e ) {
			$this->set_status( 'failed' );
			$this->logger->log( 'Migration failed: ' . $e->get_error_message(), 'error' );
			return new WP_Error( 'migration_failed', $e->getMessage() );
		}
	}

	/**
	 * Get post type from mappings
	 *
	 * @param string $type CMS content type.
	 * @return string WordPress post type.
	 */
	private function get_post_type( $type ) {
		return $this->mappings[ $type ]['post_type'] ?? $type;
	}

	/**
	 * Get taxonomy from mappings
	 *
	 * @param string $type CMS content type.
	 * @return string Taxonomy name or empty.
	 */
	private function get_taxonomy( $type ) {
		return $this->mappings[ $type ]['taxonomy'] ?? '';
	}

	/**
	 * Assign taxonomy term to a post
	 *
	 * @param int    $post_id Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @param string $term_name Term name.
	 * @return void
	 */
	private function assign_taxonomy( $post_id, $taxonomy, $term_name ) {
		if ( empty( $taxonomy ) || empty( $term_name ) || ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$term = term_exists( $term_name, $taxonomy );
		if ( ! $term ) {
			$term = wp_insert_term( $term_name, $taxonomy );
		}

		if ( ! is_wp_error( $term ) ) {
			$term_id = is_array( $term ) ? $term['term_id'] : $term;
			wp_set_object_terms( $post_id, (int) $term_id, $taxonomy );
		}
	}

	/**
	 * Initialize per-type progress tracking
	 *
	 * @param array $types Content types to track.
	 * @return void
	 */
	private function init_type_progress( array $types ) {
		$progress          = get_option( 'w2p_cms_migration_progress', array() );
		$progress['types'] = array();
		foreach ( $types as $type ) {
			$progress['types'][ $type ] = array(
				'status'  => 'pending',
				'current' => 0,
				'total'   => 0,
			);
		}
		update_option( 'w2p_cms_migration_progress', $progress );
	}

	/**
	 * Set status for a specific type
	 *
	 * @param string $type Content type.
	 * @param string $status New status.
	 * @return void
	 */
	private function set_type_status( $type, $status ) {
		$progress = get_option( 'w2p_cms_migration_progress', array() );
		if ( ! isset( $progress['types'][ $type ] ) ) {
			$progress['types'][ $type ] = array();
		}
		$progress['types'][ $type ]['status'] = $status;
		update_option( 'w2p_cms_migration_progress', $progress );
	}

	/**
	 * Stop migration
	 *
	 * @return true
	 */
	public function stop_migration() {
		$this->set_status( 'stopped' );
		$this->logger->log( 'Migration stopped by user.', 'warning' );
		$this->save_migration_log();
		return true;
	}

	/**
	 * Rollback migration
	 *
	 * @return true|WP_Error
	 */
	public function rollback() {
		$migrated = get_option( 'w2p_cms_migrated_items', array() );

		if ( empty( $migrated ) ) {
			return new WP_Error( 'nothing_to_rollback', __( 'No migrated items to rollback.', 'wp-genius' ) );
		}

		$deleted = 0;
		foreach ( $migrated as $item ) {
			if ( isset( $item['post_id'] ) && $item['post_id'] > 0 ) {
				$result = wp_delete_post( $item['post_id'], true );
				if ( $result ) {
					++$deleted;
				}
			}
		}

		delete_option( 'w2p_cms_migrated_items' );
		delete_option( 'w2p_cms_migration_progress' );

		return true;
	}

	/**
	 * Check if migration is running
	 *
	 * @return bool
	 */
	public function is_running() {
		$progress = get_option( 'w2p_cms_migration_progress', array() );
		return isset( $progress['status'] ) && 'running' === $progress['status'];
	}

	/**
	 * Set migration status
	 *
	 * @param string $status New status.
	 * @return void
	 */
	private function set_status( $status ) {
		$this->status       = $status;
		$progress           = get_option( 'w2p_cms_migration_progress', array() );
		$progress['status'] = $status;
		update_option( 'w2p_cms_migration_progress', $progress );
	}

	/**
	 * Update progress for a specific type
	 *
	 * @param string $type Content type.
	 * @param int    $current Current item number.
	 * @param int    $total Total items.
	 * @return void
	 */
	private function update_type_progress( $type, $current, $total ) {
		$progress = get_option( 'w2p_cms_migration_progress', array() );
		if ( ! isset( $progress['types'][ $type ] ) ) {
			$progress['types'][ $type ] = array();
		}
		$progress['types'][ $type ]['current'] = $current;
		$progress['types'][ $type ]['total']   = $total;
		update_option( 'w2p_cms_migration_progress', $progress );
	}

	/**
	 * Apply batch limit to an array of items
	 *
	 * @param array $items Items to limit.
	 * @return array Limited items.
	 */
	private function apply_batch_limit( array $items ) {
		if ( $this->batch_limit > 0 && count( $items ) > $this->batch_limit ) {
			$this->logger->log(
				sprintf( 'Batch limit applied: %d items → %d items.', count( $items ), $this->batch_limit ),
				'info'
			);
			return array_slice( $items, 0, $this->batch_limit );
		}
		return $items;
	}

	/**
	 * Register custom post types
	 *
	 * @return void
	 */
	private function register_post_types() {
		$post_types = array(
			'book'    => array(
				'label'    => __( 'Books', 'wp-genius' ),
				'labels'   => array(
					'singular_name' => __( 'Book', 'wp-genius' ),
				),
				'supports' => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			),
			'chapter' => array(
				'label'        => __( 'Chapters', 'wp-genius' ),
				'labels'       => array(
					'singular_name' => __( 'Chapter', 'wp-genius' ),
				),
				'supports'     => array( 'title', 'editor', 'custom-fields' ),
				'hierarchical' => true,
			),
			'album'   => array(
				'label'    => __( 'Albums', 'wp-genius' ),
				'labels'   => array(
					'singular_name' => __( 'Album', 'wp-genius' ),
				),
				'supports' => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			),
			'model'   => array(
				'label'    => __( 'Models', 'wp-genius' ),
				'labels'   => array(
					'singular_name' => __( 'Model', 'wp-genius' ),
				),
				'supports' => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			),
			'studio'  => array(
				'label'    => __( 'Studios', 'wp-genius' ),
				'labels'   => array(
					'singular_name' => __( 'Studio', 'wp-genius' ),
				),
				'supports' => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			),
		);

		foreach ( $post_types as $post_type => $args ) {
			if ( ! post_type_exists( $post_type ) ) {
				$defaults = array(
					'public'      => true,
					'show_ui'     => true,
					'menu_icon'   => 'dashicons-book',
					'has_archive' => true,
					'rewrite'     => array( 'slug' => $post_type ),
					'supports'    => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
				);
				register_post_type( $post_type, wp_parse_args( $args, $defaults ) );
			}
		}
	}

	/**
	 * Migrate books
	 *
	 * @return void
	 */
	private function migrate_books() {
		$this->logger->log( 'Starting books migration...', 'info' );

		$books = $this->db->get_books();
		if ( is_wp_error( $books ) ) {
			$this->logger->log( 'Failed to fetch books: ' . $books->get_error_message(), 'error' );
			$this->set_type_status( 'books', 'failed' );
			return;
		}

		$total     = count( $books );
		$books     = $this->apply_batch_limit( $books );
		$count     = count( $books );
		$post_type = $this->get_post_type( 'books' );
		$taxonomy  = $this->get_taxonomy( 'books' );

		$this->logger->log( "Found {$total} books to migrate (limit: {$count}) → {$post_type}.", 'info' );
		$this->update_type_progress( 'books', 0, $count );

		foreach ( $books as $index => $book ) {
			if ( 'stopped' === $this->status ) {
				break;
			}

			$this->update_type_progress( 'books', $index + 1, $total );

			$book_id = $book['id'] ?? $book['book_id'] ?? 0;
			$title   = $book['title'] ?? $book['book_title'] ?? __( 'Untitled Book', 'wp-genius' );

			$existing = get_posts(
				array(
					'post_type'   => $post_type,
					'meta_key'    => '_w2p_cms_source_id',
					'meta_value'  => $book_id,
					'numberposts' => 1,
					'fields'      => 'ids',
				)
			);

			if ( ! empty( $existing ) ) {
				$this->logger->log( "Book '{$title}' already migrated, skipping.", 'warning' );
				continue;
			}

			$post_data = array(
				'post_title'   => $title,
				'post_content' => $book['description'] ?? $book['content'] ?? '',
				'post_status'  => 'publish',
				'post_type'    => $post_type,
			);

			$post_id = wp_insert_post( $post_data );

			if ( is_wp_error( $post_id ) ) {
				$this->logger->log( "Failed to create book '{$title}': " . $post_id->get_error_message(), 'error' );
				continue;
			}

			update_post_meta( $post_id, '_w2p_cms_source_id', $book_id );
			update_post_meta( $post_id, '_book_author', $book['author'] ?? '' );
			update_post_meta( $post_id, '_book_isbn', $book['isbn'] ?? '' );
			update_post_meta( $post_id, '_book_genre', $book['genre'] ?? '' );
			update_post_meta( $post_id, '_book_year', $book['year'] ?? '' );

			$summary = $this->db->get_book_summary( $book_id );
			if ( ! is_wp_error( $summary ) && ! empty( $summary ) ) {
				update_post_meta( $post_id, '_book_summary', $summary );
			}

			$plot_stages = $this->db->get_book_plot_stages( $book_id );
			if ( ! is_wp_error( $plot_stages ) && ! empty( $plot_stages ) ) {
				update_post_meta( $post_id, '_book_plot_stages', $plot_stages );
			}

			$script = $this->db->get_book_script( $book_id );
			if ( ! is_wp_error( $script ) && ! empty( $script ) ) {
				update_post_meta( $post_id, '_book_tran2script', $script );
			}

			// Assign genre to taxonomy.
			$genre = $book['genre'] ?? '';
			if ( ! empty( $genre ) && ! empty( $taxonomy ) ) {
				$this->assign_taxonomy( $post_id, $taxonomy, $genre );
			}

			$this->migrate_chapters( $book_id, $post_id );

			$this->migrated[] = array(
				'type'    => 'book',
				'source'  => $book_id,
				'post_id' => $post_id,
			);

			$this->logger->log( "Migrated book: {$title} (ID: {$post_id})", 'success' );
		}

		$this->save_migrated_items();
	}

	/**
	 * Migrate chapters for a book
	 *
	 * @param int $cms_book_id CMS book ID.
	 * @param int $wp_book_id WordPress book post ID.
	 * @return void
	 */
	private function migrate_chapters( $cms_book_id, $wp_book_id ) {
		$chapters = $this->db->get_chapters( $cms_book_id );
		if ( is_wp_error( $chapters ) || empty( $chapters ) ) {
			return;
		}

		$this->logger->log( 'Migrating ' . count( $chapters ) . " chapters for book ID {$cms_book_id}...", 'info' );

		foreach ( $chapters as $chapter ) {
			if ( 'stopped' === $this->status ) {
				break;
			}

			$chapter_number = $chapter['chapter_number'] ?? 0;
			// translators: %1: placeholder。
			$chapter_title   = $chapter['chapter_title'] ?? sprintf( __( 'Chapter %d', 'wp-genius' ), $chapter_number );
			$chapter_content = $chapter['content'] ?? $chapter['chapter_content'] ?? '';

			$post_data = array(
				'post_title'   => $chapter_title,
				'post_content' => $chapter_content,
				'post_status'  => 'publish',
				'post_type'    => 'chapter',
				'post_parent'  => $wp_book_id,
				'menu_order'   => $chapter_number,
			);

			$post_id = wp_insert_post( $post_data );

			if ( is_wp_error( $post_id ) ) {
				$this->logger->log( "Failed to create chapter '{$chapter_title}': " . $post_id->get_error_message(), 'error' );
				continue;
			}

			update_post_meta( $post_id, '_w2p_cms_source_id', $chapter['id'] ?? 0 );
			update_post_meta( $post_id, '_chapter_number', $chapter_number );
			update_post_meta( $post_id, '_chapter_book_id', $cms_book_id );

			$this->migrated[] = array(
				'type'    => 'chapter',
				'source'  => $chapter['id'] ?? 0,
				'post_id' => $post_id,
			);

			$this->logger->log( "Migrated chapter: {$chapter_title}", 'success' );
		}
	}

	/**
	 * Migrate albums
	 *
	 * @return void
	 */
	private function migrate_albums() {
		$this->logger->log( 'Starting albums migration...', 'info' );

		$albums = $this->db->get_albums();
		if ( is_wp_error( $albums ) ) {
			$this->logger->log( 'Failed to fetch albums: ' . $albums->get_error_message(), 'error' );
			$this->set_type_status( 'albums', 'failed' );
			return;
		}

		$total     = count( $albums );
		$albums    = $this->apply_batch_limit( $albums );
		$count     = count( $albums );
		$post_type = $this->get_post_type( 'albums' );

		$this->logger->log( "Found {$total} albums to migrate (limit: {$count}) → {$post_type}.", 'info' );
		$this->update_type_progress( 'albums', 0, $count );

		foreach ( $albums as $index => $album ) {
			if ( 'stopped' === $this->status ) {
				break;
			}

			$this->update_type_progress( 'albums', $index + 1, $total );

			$album_id = $album['id'] ?? 0;
			$title    = $album['title'] ?? $album['name'] ?? __( 'Untitled Album', 'wp-genius' );

			$existing = get_posts(
				array(
					'post_type'   => $post_type,
					'meta_key'    => '_w2p_cms_source_id',
					'meta_value'  => $album_id,
					'numberposts' => 1,
					'fields'      => 'ids',
				)
			);

			if ( ! empty( $existing ) ) {
				$this->logger->log( "Album '{$title}' already migrated, skipping.", 'warning' );
				continue;
			}

			$post_data = array(
				'post_title'   => $title,
				'post_content' => $album['description'] ?? '',
				'post_status'  => 'publish',
				'post_type'    => $post_type,
			);

			$post_id = wp_insert_post( $post_data );

			if ( is_wp_error( $post_id ) ) {
				$this->logger->log( "Failed to create album '{$title}': " . $post_id->get_error_message(), 'error' );
				continue;
			}

			$cover_url = $album['cover'] ?? $album['cover_image'] ?? '';
			if ( ! empty( $cover_url ) ) {
				$attachment_id = $this->media->import_image( $cover_url, $post_id );
				if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
					set_post_thumbnail( $post_id, $attachment_id );
				}
			}

			$images = $album['images'] ?? array();
			if ( ! empty( $images ) ) {
				$image_ids = array();
				foreach ( $images as $image_url ) {
					$image_id = $this->media->import_image( $image_url, $post_id );
					if ( $image_id && ! is_wp_error( $image_id ) ) {
						$image_ids[] = $image_id;
					}
				}
				update_post_meta( $post_id, '_album_images', $image_ids );
			}

			update_post_meta( $post_id, '_w2p_cms_source_id', $album_id );
			update_post_meta( $post_id, '_album_date', $album['date'] ?? '' );

			$this->migrated[] = array(
				'type'    => 'album',
				'source'  => $album_id,
				'post_id' => $post_id,
			);

			$this->logger->log( "Migrated album: {$title} (ID: {$post_id})", 'success' );
		}

		$this->save_migrated_items();
	}

	/**
	 * Migrate models
	 *
	 * @return void
	 */
	private function migrate_models() {
		$this->logger->log( 'Starting models migration...', 'info' );

		$models = $this->db->get_models();
		if ( is_wp_error( $models ) ) {
			$this->logger->log( 'Failed to fetch models: ' . $models->get_error_message(), 'error' );
			$this->set_type_status( 'models', 'failed' );
			return;
		}

		$total     = count( $models );
		$models    = $this->apply_batch_limit( $models );
		$count     = count( $models );
		$post_type = $this->get_post_type( 'models' );

		$this->logger->log( "Found {$total} models to migrate (limit: {$count}) → {$post_type}.", 'info' );
		$this->update_type_progress( 'models', 0, $count );

		foreach ( $models as $index => $model ) {
			if ( 'stopped' === $this->status ) {
				break;
			}

			$this->update_type_progress( 'models', $index + 1, $total );

			$model_id = $model['id'] ?? 0;
			$title    = $model['name'] ?? $model['title'] ?? __( 'Untitled Model', 'wp-genius' );

			$existing = get_posts(
				array(
					'post_type'   => $post_type,
					'meta_key'    => '_w2p_cms_source_id',
					'meta_value'  => $model_id,
					'numberposts' => 1,
					'fields'      => 'ids',
				)
			);

			if ( ! empty( $existing ) ) {
				$this->logger->log( "Model '{$title}' already migrated, skipping.", 'warning' );
				continue;
			}

			$post_data = array(
				'post_title'   => $title,
				'post_content' => $model['bio'] ?? $model['description'] ?? '',
				'post_status'  => 'publish',
				'post_type'    => $post_type,
			);

			$post_id = wp_insert_post( $post_data );

			if ( is_wp_error( $post_id ) ) {
				$this->logger->log( "Failed to create model '{$title}': " . $post_id->get_error_message(), 'error' );
				continue;
			}

			$cover_url = $model['cover'] ?? $model['avatar'] ?? '';
			if ( ! empty( $cover_url ) ) {
				$attachment_id = $this->media->import_image( $cover_url, $post_id );
				if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
					set_post_thumbnail( $post_id, $attachment_id );
				}
			}

			update_post_meta( $post_id, '_w2p_cms_source_id', $model_id );

			$this->migrated[] = array(
				'type'    => 'model',
				'source'  => $model_id,
				'post_id' => $post_id,
			);

			$this->logger->log( "Migrated model: {$title} (ID: {$post_id})", 'success' );
		}

		$this->save_migrated_items();
	}

	/**
	 * Migrate studios
	 *
	 * @return void
	 */
	private function migrate_studios() {
		$this->logger->log( 'Starting studios migration...', 'info' );

		$studios = $this->db->get_studios();
		if ( is_wp_error( $studios ) ) {
			$this->logger->log( 'Failed to fetch studios: ' . $studios->get_error_message(), 'error' );
			$this->set_type_status( 'studios', 'failed' );
			return;
		}

		$total     = count( $studios );
		$studios   = $this->apply_batch_limit( $studios );
		$count     = count( $studios );
		$post_type = $this->get_post_type( 'studios' );

		$this->logger->log( "Found {$total} studios to migrate (limit: {$count}) → {$post_type}.", 'info' );
		$this->update_type_progress( 'studios', 0, $count );

		foreach ( $studios as $index => $studio ) {
			if ( 'stopped' === $this->status ) {
				break;
			}

			$this->update_type_progress( 'studios', $index + 1, $total );

			$studio_id = $studio['id'] ?? 0;
			$title     = $studio['name'] ?? $studio['title'] ?? __( 'Untitled Studio', 'wp-genius' );

			$existing = get_posts(
				array(
					'post_type'   => $post_type,
					'meta_key'    => '_w2p_cms_source_id',
					'meta_value'  => $studio_id,
					'numberposts' => 1,
					'fields'      => 'ids',
				)
			);

			if ( ! empty( $existing ) ) {
				$this->logger->log( "Studio '{$title}' already migrated, skipping.", 'warning' );
				continue;
			}

			$post_data = array(
				'post_title'   => $title,
				'post_content' => $studio['description'] ?? '',
				'post_status'  => 'publish',
				'post_type'    => $post_type,
			);

			$post_id = wp_insert_post( $post_data );

			if ( is_wp_error( $post_id ) ) {
				$this->logger->log( "Failed to create studio '{$title}': " . $post_id->get_error_message(), 'error' );
				continue;
			}

			update_post_meta( $post_id, '_w2p_cms_source_id', $studio_id );

			$this->migrated[] = array(
				'type'    => 'studio',
				'source'  => $studio_id,
				'post_id' => $post_id,
			);

			$this->logger->log( "Migrated studio: {$title} (ID: {$post_id})", 'success' );
		}

		$this->save_migrated_items();
	}

	/**
	 * Save migrated items to database
	 *
	 * @return void
	 */
	private function save_migrated_items() {
		$existing = get_option( 'w2p_cms_migrated_items', array() );
		$merged   = array_merge( $existing, $this->migrated );
		update_option( 'w2p_cms_migrated_items', $merged );
	}

	/**
	 * Save migration log
	 *
	 * @return void
	 */
	private function save_migration_log() {
		$log_entries     = $this->logger->get_entries();
		$progress        = get_option( 'w2p_cms_migration_progress', array() );
		$progress['log'] = $log_entries;
		update_option( 'w2p_cms_migration_progress', $progress );
	}
}
