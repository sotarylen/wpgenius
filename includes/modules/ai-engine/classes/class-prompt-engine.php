<?php
/**
 * Prompt Engine
 *
 * 提示词模板引擎
 *
 * @package WP_Genius
 * @subpackage Modules/AIEngine/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class W2P_AI_Prompt_Engine
 */
class W2P_AI_Prompt_Engine {

	/**
	 * Database table name
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'w2p_ai_prompts';
	}

	/**
	 * Get prompt by ID
	 *
	 * @param int $prompt_id Prompt ID.
	 * @return array|null
	 */
	public function get_prompt( int $prompt_id ): ?array {
		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}w2p_ai_prompts WHERE id = %d",
				$prompt_id
			),
			ARRAY_A
		);

		if ( $result && ! empty( $result['variables'] ) ) {
			$result['variables'] = json_decode( $result['variables'], true );
		}

		return $result;
	}

	/**
	 * Get all prompts
	 *
	 * @param string $type Prompt type filter.
	 * @return array
	 */
	public function get_prompts( string $type = '' ): array {
		global $wpdb;

		$where      = '';
		$query_args = array();
		if ( ! empty( $type ) ) {
			$where        = ' WHERE type = %s';
			$query_args[] = $type;
		}

		$results = $wpdb->get_results(
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders -- 动态 WHERE 片段值经 prepare 占位符传递（运行时占位符与参数匹配）。
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}w2p_ai_prompts {$where} ORDER BY name ASC",
				$query_args
			),
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		foreach ( $results as &$result ) {
			if ( ! empty( $result['variables'] ) ) {
				$result['variables'] = json_decode( $result['variables'], true );
			}
		}

		return $results ?? array();
	}

	/**
	 * Save prompt
	 *
	 * @param array $data Prompt data.
	 * @return int|false Prompt ID or false on failure.
	 */
	public function save_prompt( array $data ) {
		global $wpdb;

		$defaults = array(
			'name'        => '',
			'type'        => 'custom',
			'template'    => '',
			'variables'   => array(),
			'provider'    => null,
			'model'       => null,
			'temperature' => 0.7,
			'max_tokens'  => 2000,
		);

		$data = wp_parse_args( $data, $defaults );

		$insert_data = array(
			'name'        => sanitize_text_field( $data['name'] ),
			'type'        => sanitize_text_field( $data['type'] ),
			'template'    => wp_kses_post( $data['template'] ),
			'variables'   => wp_json_encode( $data['variables'] ),
			'provider'    => sanitize_text_field( $data['provider'] ),
			'model'       => sanitize_text_field( $data['model'] ),
			'temperature' => floatval( $data['temperature'] ),
			'max_tokens'  => absint( $data['max_tokens'] ),
		);

		if ( ! empty( $data['id'] ) ) {
			// Update existing
			$wpdb->update(
				$this->table_name,
				$insert_data,
				array( 'id' => absint( $data['id'] ) )
			);
			return absint( $data['id'] );
		} else {
			// Insert new
			$wpdb->insert( $this->table_name, $insert_data );
			return $wpdb->insert_id;
		}
	}

	/**
	 * Delete prompt
	 *
	 * @param int $prompt_id Prompt ID.
	 * @return bool
	 */
	public function delete_prompt( int $prompt_id ): bool {
		global $wpdb;

		$deleted = $wpdb->delete(
			$this->table_name,
			array( 'id' => $prompt_id ),
			array( '%d' )
		);

		return $deleted > 0;
	}

	/**
	 * Process template with variables
	 *
	 * @param string $template Template string.
	 * @param array  $variables Variables to replace.
	 * @return string
	 */
	public function process_template( string $template, array $variables = array() ): string {
		// Replace {{variable}} placeholders
		preg_match_all( '/\{\{(\w+)\}\}/', $template, $matches );

		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $index => $var_name ) {
				$value    = $variables[ $var_name ] ?? '';
				$template = str_replace( $matches[0][ $index ], $value, $template );
			}
		}

		return $template;
	}

	/**
	 * Extract variables from template
	 *
	 * @param string $template Template string.
	 * @return array
	 */
	public function extract_variables( string $template ): array {
		preg_match_all( '/\{\{(\w+)\}\}/', $template, $matches );

		return array_unique( $matches[1] ?? array() );
	}

	/**
	 * Get default prompts
	 *
	 * @return array
	 */
	public function get_default_prompts(): array {
		return array(
			array(
				'name'      => __( 'Blog Post', 'wp-genius' ),
				'type'      => 'blog_post',
				'template'  => "Write a comprehensive blog post about {{topic}}.\n\nRequirements:\n- Title should be engaging and SEO-friendly\n- Include an introduction paragraph\n- Use H2 and H3 subheadings\n- Provide valuable, actionable information\n- Include a conclusion with call-to-action\n- Word count: {{word_count}} words\n\nTarget audience: {{audience}}\n\nTone: {{tone}}",
				'variables' => array(
					'topic'      => array(
						'type'     => 'text',
						'label'    => 'Topic',
						'required' => true,
					),
					'word_count' => array(
						'type'    => 'number',
						'label'   => 'Word Count',
						'default' => 1500,
					),
					'audience'   => array(
						'type'    => 'text',
						'label'   => 'Target Audience',
						'default' => 'General readers',
					),
					'tone'       => array(
						'type'    => 'select',
						'label'   => 'Tone',
						'options' => array( 'Professional', 'Casual', 'Informative', 'Persuasive' ),
						'default' => 'Professional',
					),
				),
			),
			array(
				'name'      => __( 'Product Description', 'wp-genius' ),
				'type'      => 'product',
				'template'  => "Write a compelling product description for:\n\nProduct: {{product_name}}\nCategory: {{category}}\nKey Features: {{features}}\n\nRequirements:\n- Highlight unique selling points\n- Use persuasive language\n- Include benefits, not just features\n- Keep it concise but engaging\n- Length: {{length}}",
				'variables' => array(
					'product_name' => array(
						'type'     => 'text',
						'label'    => 'Product Name',
						'required' => true,
					),
					'category'     => array(
						'type'     => 'text',
						'label'    => 'Category',
						'required' => true,
					),
					'features'     => array(
						'type'     => 'textarea',
						'label'    => 'Key Features',
						'required' => true,
					),
					'length'       => array(
						'type'    => 'select',
						'label'   => 'Length',
						'options' => array( 'Short (100 words)', 'Medium (200 words)', 'Long (300+ words)' ),
						'default' => 'Medium (200 words)',
					),
				),
			),
			array(
				'name'      => __( 'Social Media Post', 'wp-genius' ),
				'type'      => 'social',
				'template'  => "Create a {{platform}} post about {{topic}}.\n\nPlatform: {{platform}}\nTopic: {{topic}}\nGoal: {{goal}}\n\nRequirements:\n- Follow platform best practices\n- Include relevant hashtags\n- Engaging hook in first line\n- Clear call-to-action\n- Character limit: {{char_limit}}",
				'variables' => array(
					'platform'   => array(
						'type'     => 'select',
						'label'    => 'Platform',
						'options'  => array( 'Twitter/X', 'LinkedIn', 'Instagram', 'Facebook' ),
						'required' => true,
					),
					'topic'      => array(
						'type'     => 'text',
						'label'    => 'Topic',
						'required' => true,
					),
					'goal'       => array(
						'type'    => 'text',
						'label'   => 'Goal',
						'default' => 'Engagement',
					),
					'char_limit' => array(
						'type'    => 'number',
						'label'   => 'Character Limit',
						'default' => 280,
					),
				),
			),
			array(
				'name'      => __( 'SEO Article', 'wp-genius' ),
				'type'      => 'seo',
				'template'  => "Write an SEO-optimized article for the keyword: {{keyword}}\n\nRequirements:\n- Title tag: {{keyword}} - {{title_suffix}}\n- Meta description: Compelling, under 160 characters\n- H1: Include primary keyword naturally\n- Introduction: Hook readers in first 100 words\n- Use secondary keywords: {{secondary_keywords}}\n- Include FAQ section with {{faq_count}} questions\n- Word count: {{word_count}}\n- Internal link suggestions: {{internal_topics}}\n- External authority sources to cite",
				'variables' => array(
					'keyword'            => array(
						'type'     => 'text',
						'label'    => 'Primary Keyword',
						'required' => true,
					),
					'title_suffix'       => array(
						'type'    => 'text',
						'label'   => 'Title Suffix',
						'default' => 'Complete Guide',
					),
					'secondary_keywords' => array(
						'type'        => 'text',
						'label'       => 'Secondary Keywords',
						'placeholder' => 'keyword1, keyword2, keyword3',
					),
					'faq_count'          => array(
						'type'    => 'number',
						'label'   => 'FAQ Questions',
						'default' => 5,
					),
					'word_count'         => array(
						'type'    => 'number',
						'label'   => 'Word Count',
						'default' => 2000,
					),
					'internal_topics'    => array(
						'type'  => 'text',
						'label' => 'Related Internal Topics',
					),
				),
			),
		);
	}
}

// Legacy alias for backward compatibility (pre-1.2.0 class name).
if ( ! class_exists( 'AI_Prompt_Engine', false ) ) {
	class_alias( 'W2P_AI_Prompt_Engine', 'AI_Prompt_Engine' );
}
