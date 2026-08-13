<?php
/**
 * CMS Database Connector
 *
 * @package WP_Genius
 * @subpackage Modules/CMSMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CMS_DB_Connector
 */
class CMS_DB_Connector {

	/**
	 * Database connection
	 *
	 * @var mysqli|null
	 */
	private $connection = null;

	/**
	 * Table prefix
	 *
	 * @var string
	 */
	private $prefix = 'n8n_';

	/**
	 * Table mappings
	 *
	 * @var array
	 */
	private $tables = array(
		'books'      => 'n8n_book_list',
		'chapters'   => 'n8n_book_chapters_content',
		'chapter_list' => 'n8n_book_chapters_list',
		'summaries'  => 'n8n_book_summary',
		'plot_stages' => 'n8n_book_plot_stages',
		'phased_summaries' => 'n8n_book_phased_summaries',
		'scripts'    => 'n8n_book_tran2script',
		'albums'     => 'n8n_albums',
		'models'     => 'n8n_album_models',
		'studios'    => 'n8n_album_studios',
	);

	/**
	 * Test database connection
	 *
	 * @param string $host Database host.
	 * @param string $port Database port.
	 * @param string $name Database name.
	 * @param string $user Database user.
	 * @param string $pass Database password.
	 * @return true|WP_Error
	 */
	public function test_connection( $host, $port, $name, $user, $pass ) {
		if ( empty( $host ) || empty( $name ) || empty( $user ) ) {
			return new WP_Error( 'missing_credentials', __( 'Please fill in all database connection fields.', 'wp-genius' ) );
		}

		$this->disconnect();

		if ( ! function_exists( 'mysqli_connect' ) ) {
			return new WP_Error( 'no_mysqli', __( 'mysqli extension is not available.', 'wp-genius' ) );
		}

		$this->connection = @new mysqli( $host, $user, $pass, $name, (int) $port );

		if ( $this->connection->connect_error ) {
			$error_no = $this->connection->connect_errno;
			$this->disconnect();
			return new WP_Error( 'connection_failed', sprintf( __( 'Connection failed (Error %d). Check credentials.', 'wp-genius' ), $error_no ) );
		}

		$this->connection->set_charset( 'utf8mb4' );
		return true;
	}

	/**
	 * Get database connection
	 *
	 * @return mysqli|null
	 */
	public function get_connection() {
		if ( null === $this->connection ) {
			$settings = get_option( 'w2p_cms_migrator_settings', array() );
			$this->test_connection(
				$settings['db_host'] ?? '',
				$settings['db_port'] ?? '3306',
				$settings['db_name'] ?? '',
				$settings['db_user'] ?? '',
				$settings['db_pass'] ?? ''
			);
		}
		return $this->connection;
	}

	/**
	 * Disconnect from database
	 *
	 * @return void
	 */
	public function disconnect() {
		if ( $this->connection ) {
			$this->connection->close();
			$this->connection = null;
		}
	}

	/**
	 * Get table statistics
	 *
	 * @return array|WP_Error
	 */
	public function get_table_stats() {
		$conn = $this->get_connection();
		if ( ! $conn ) {
			return new WP_Error( 'no_connection', __( 'Database not connected.', 'wp-genius' ) );
		}

		$stats = array();

		// Books count.
		$result = $conn->query( 'SELECT COUNT(*) as count FROM ' . $this->tables['books'] );
		$row = $result->fetch_assoc();
		$stats['books'] = (int) $row['count'];

		// Chapters count.
		$result = $conn->query( 'SELECT COUNT(*) as count FROM ' . $this->tables['chapters'] );
		$row = $result->fetch_assoc();
		$stats['chapters'] = (int) $row['count'];

		// Albums count.
		$result = $conn->query( 'SELECT COUNT(*) as count FROM ' . $this->tables['albums'] );
		$row = $result->fetch_assoc();
		$stats['albums'] = (int) $row['count'];

		// Models count.
		$result = $conn->query( 'SELECT COUNT(*) as count FROM ' . $this->tables['models'] );
		$row = $result->fetch_assoc();
		$stats['models'] = (int) $row['count'];

		// Studios count.
		$result = $conn->query( 'SELECT COUNT(*) as count FROM ' . $this->tables['studios'] );
		$row = $result->fetch_assoc();
		$stats['studios'] = (int) $row['count'];

		// Images count (from albums table, assuming image field).
		$stats['images'] = $stats['albums'];

		return $stats;
	}

	/**
	 * Preview data from a specific table
	 *
	 * @param string $type Data type to preview.
	 * @param int    $limit Number of rows to preview.
	 * @return array|WP_Error
	 */
	public function preview_data( $type, $limit = 10 ) {
		$conn = $this->get_connection();
		if ( ! $conn ) {
			return new WP_Error( 'no_connection', __( 'Database not connected.', 'wp-genius' ) );
		}

		$table_map = array(
			'books'    => $this->tables['books'],
			'chapters' => $this->tables['chapters'],
			'albums'   => $this->tables['albums'],
			'models'   => $this->tables['models'],
			'studios'  => $this->tables['studios'],
		);

		if ( ! isset( $table_map[ $type ] ) ) {
			return new WP_Error( 'invalid_type', __( 'Invalid data type.', 'wp-genius' ) );
		}

		$table = $table_map[ $type ];
		$limit = absint( $limit );

		$result = $conn->query( "SELECT * FROM {$table} LIMIT " . (int) $limit );
		if ( ! $result ) {
			return new WP_Error( 'query_failed', $conn->error );
		}

		$data = array();
		while ( $row = $result->fetch_assoc() ) {
			$data[] = $row;
		}

		return $data;
	}

	/**
	 * Get all books
	 *
	 * @return array|WP_Error
	 */
	public function get_books() {
		$conn = $this->get_connection();
		if ( ! $conn ) {
			return new WP_Error( 'no_connection', __( 'Database not connected.', 'wp-genius' ) );
		}

		$result = $conn->query( 'SELECT * FROM ' . $this->tables['books'] );
		if ( ! $result ) {
			return new WP_Error( 'query_failed', $conn->error );
		}

		$data = array();
		while ( $row = $result->fetch_assoc() ) {
			$data[] = $row;
		}
		return $data;
	}

	/**
	 * Get chapters for a book
	 *
	 * @param int $book_id Book ID.
	 * @return array|WP_Error
	 */
	public function get_chapters( $book_id ) {
		$conn = $this->get_connection();
		if ( ! $conn ) {
			return new WP_Error( 'no_connection', __( 'Database not connected.', 'wp-genius' ) );
		}

		$book_id = absint( $book_id );

		$stmt = $conn->prepare(
			'SELECT c.*, cl.title as chapter_title, cl.chapter_number ' .
			'FROM ' . $this->tables['chapters'] . ' c ' .
			'LEFT JOIN ' . $this->tables['chapter_list'] . ' cl ON c.book_id = cl.book_id AND c.chapter_number = cl.chapter_number ' .
			'WHERE c.book_id = ? ' .
			'ORDER BY c.chapter_number ASC'
		);
		$stmt->bind_param( 'i', $book_id );
		$result = $stmt->execute() ? $stmt->get_result() : null;
		$stmt->close();
		if ( ! $result ) {
			return new WP_Error( 'query_failed', $conn->error );
		}

		$data = array();
		while ( $row = $result->fetch_assoc() ) {
			$data[] = $row;
		}
		return $data;
	}

	/**
	 * Get book summary
	 *
	 * @param int $book_id Book ID.
	 * @return string|WP_Error
	 */
	public function get_book_summary( $book_id ) {
		$conn = $this->get_connection();
		if ( ! $conn ) {
			return new WP_Error( 'no_connection', __( 'Database not connected.', 'wp-genius' ) );
		}

		$book_id = absint( $book_id );

		$stmt = $conn->prepare( 'SELECT summary FROM ' . $this->tables['summaries'] . ' WHERE book_id = ? LIMIT 1' );
		$stmt->bind_param( 'i', $book_id );
		$result = $stmt->execute() ? $stmt->get_result() : null;
		$stmt->close();

		if ( ! $result || $result->num_rows === 0 ) {
			return '';
		}

		$row = $result->fetch_assoc();
		return $row['summary'] ?? '';
	}

	/**
	 * Get book plot stages
	 *
	 * @param int $book_id Book ID.
	 * @return string|WP_Error
	 */
	public function get_book_plot_stages( $book_id ) {
		$conn = $this->get_connection();
		if ( ! $conn ) {
			return new WP_Error( 'no_connection', __( 'Database not connected.', 'wp-genius' ) );
		}

		$book_id = absint( $book_id );

		$stmt = $conn->prepare( 'SELECT plot_stages FROM ' . $this->tables['plot_stages'] . ' WHERE book_id = ? LIMIT 1' );
		$stmt->bind_param( 'i', $book_id );
		$result = $stmt->execute() ? $stmt->get_result() : null;
		$stmt->close();

		if ( ! $result || $result->num_rows === 0 ) {
			return '';
		}

		$row = $result->fetch_assoc();
		return $row['plot_stages'] ?? '';
	}

	/**
	 * Get book script
	 *
	 * @param int $book_id Book ID.
	 * @return string|WP_Error
	 */
	public function get_book_script( $book_id ) {
		$conn = $this->get_connection();
		if ( ! $conn ) {
			return new WP_Error( 'no_connection', __( 'Database not connected.', 'wp-genius' ) );
		}

		$book_id = absint( $book_id );

		$stmt = $conn->prepare( 'SELECT script FROM ' . $this->tables['scripts'] . ' WHERE book_id = ? LIMIT 1' );
		$stmt->bind_param( 'i', $book_id );
		$result = $stmt->execute() ? $stmt->get_result() : null;
		$stmt->close();
		if ( ! $result || $result->num_rows === 0 ) {
			return '';
		}

		$row = $result->fetch_assoc();
		return $row['script'] ?? '';
	}

	/**
	 * Get all albums
	 *
	 * @return array|WP_Error
	 */
	public function get_albums() {
		$conn = $this->get_connection();
		if ( ! $conn ) {
			return new WP_Error( 'no_connection', __( 'Database not connected.', 'wp-genius' ) );
		}

		$result = $conn->query( 'SELECT * FROM ' . $this->tables['albums'] );
		if ( ! $result ) {
			return new WP_Error( 'query_failed', $conn->error );
		}

		$data = array();
		while ( $row = $result->fetch_assoc() ) {
			$data[] = $row;
		}
		return $data;
	}

	/**
	 * Get all models
	 *
	 * @return array|WP_Error
	 */
	public function get_models() {
		$conn = $this->get_connection();
		if ( ! $conn ) {
			return new WP_Error( 'no_connection', __( 'Database not connected.', 'wp-genius' ) );
		}

		$result = $conn->query( 'SELECT * FROM ' . $this->tables['models'] );
		if ( ! $result ) {
			return new WP_Error( 'query_failed', $conn->error );
		}

		$data = array();
		while ( $row = $result->fetch_assoc() ) {
			$data[] = $row;
		}
		return $data;
	}

	/**
	 * Get all studios
	 *
	 * @return array|WP_Error
	 */
	public function get_studios() {
		$conn = $this->get_connection();
		if ( ! $conn ) {
			return new WP_Error( 'no_connection', __( 'Database not connected.', 'wp-genius' ) );
		}

		$result = $conn->query( 'SELECT * FROM ' . $this->tables['studios'] );
		if ( ! $result ) {
			return new WP_Error( 'query_failed', $conn->error );
		}

		$data = array();
		while ( $row = $result->fetch_assoc() ) {
			$data[] = $row;
		}
		return $data;
	}

	/**
	 * Get album models
	 *
	 * @param int $album_id Album ID.
	 * @return array|WP_Error
	 */
	public function get_album_models( $album_id ) {
		$conn = $this->get_connection();
		if ( ! $conn ) {
			return new WP_Error( 'no_connection', __( 'Database not connected.', 'wp-genius' ) );
		}

		$album_id = absint( $album_id );

		$stmt = $conn->prepare(
			'SELECT m.* FROM ' . $this->tables['models'] . ' m ' .
			'INNER JOIN n8n_album_models_link l ON m.id = l.model_id ' .
			'WHERE l.album_id = ?'
		);
		$stmt->bind_param( 'i', $album_id );
		$result = $stmt->execute() ? $stmt->get_result() : null;
		$stmt->close();
		if ( ! $result ) {
			return new WP_Error( 'query_failed', $conn->error );
		}

		$data = array();
		while ( $row = $result->fetch_assoc() ) {
			$data[] = $row;
		}
		return $data;
	}
}
