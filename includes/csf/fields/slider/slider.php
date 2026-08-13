<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access directly.
/**
 *
 * Field: slider
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if ( ! class_exists( 'CSF_Field_slider' ) ) {
  class CSF_Field_slider extends CSF_Fields {

    public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
      parent::__construct( $field, $value, $unique, $where, $parent );
    }

    public function render() {

      $args = wp_parse_args( $this->field, array(
        'max'  => 100,
        'min'  => 0,
        'step' => 1,
        'unit' => '',
      ) );

      echo $this->field_before();

      echo '<div class="w2p-range-group">';
      
      echo '<div class="w2p-range-header">';
      echo '<span class="w2p-range-value"></span>';
      echo '</div>';

      echo '<input type="range" name="'. esc_attr( $this->field_name() ) .'" value="'. esc_attr( $this->value ) .'" class="w2p-range-slider" min="'. esc_attr( $args['min'] ) .'" max="'. esc_attr( $args['max'] ) .'" step="'. esc_attr( $args['step'] ) .'" data-suffix="'. esc_attr( $args['unit'] ) .'"'. $this->field_attributes() .' />';

      echo '</div>';

      echo $this->field_after();

    }

    public function enqueue() {

      if ( ! wp_script_is( 'w2p-range-slider' ) ) {
        wp_enqueue_script( 'w2p-range-slider' );
      }

    }

    public function output() {

      $output    = '';
      $elements  = ( is_array( $this->field['output'] ) ) ? $this->field['output'] : array_filter( (array) $this->field['output'] );
      $important = ( ! empty( $this->field['output_important'] ) ) ? '!important' : '';
      $mode      = ( ! empty( $this->field['output_mode'] ) ) ? $this->field['output_mode'] : 'width';
      $unit      = ( ! empty( $this->field['unit'] ) ) ? $this->field['unit'] : 'px';

      if ( ! empty( $elements ) && isset( $this->value ) && $this->value !== '' ) {
        foreach ( $elements as $key_property => $element ) {
          if ( is_numeric( $key_property ) ) {
            if ( $mode ) {
              $output = implode( ',', $elements ) .'{'. $mode .':'. $this->value . $unit . $important .';}';
            }
            break;
          } else {
            $output .= $element .'{'. $key_property .':'. $this->value . $unit . $important .'}';
          }
        }
      }

      $this->parent->output_css .= $output;

      return $output;

    }

  }
}
