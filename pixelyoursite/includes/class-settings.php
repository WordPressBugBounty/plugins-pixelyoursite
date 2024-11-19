<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

abstract class Settings {

	/**
     * Options section slug
     *
	 * @var string
	 */
    private $slug;

    /**
     * Options values
     *
     * @var array
     */
    private $values = array();

    /**
     * Database option key
     *
     * @var string
     */
    private $option_key = '';

    /**
     * Default options values
     *
     * @var array
     */
    private $defaults = array();

    /**
     * List of all options
     *
     * @var array
     */
    private $options = array();

    private $defaults_json_path;
    
    /**
     * Constructor
     *
     * @param string $slug
     */
    public function __construct( $slug ) {
        $this->slug = $slug;
        $this->option_key = 'pys_' . $slug;
    }

    public function getSlug() {
        return $this->slug;
    }

	/**
	 * Load options fields and options defaults from specified files
	 *
	 * @param string $fields   Path to options fields file
	 * @param string $defaults Path to options defaults file
	 */
    public function locateOptions( $fields, $defaults ) {
        
        $this->loadJSON( $fields, false );
        $this->loadJSON( $defaults, true );
        
        $this->defaults_json_path = $defaults;

    }
    
    public function resetToDefaults() {
	
	    if ( ! file_exists( $this->defaults_json_path ) ) {
		    return;
	    }
	
	    $content = file_get_contents( $this->defaults_json_path );
	    $values  = json_decode( $content, true );
     
	    $this->updateOptions( $values );
	    
    }

	/**
	 * Load options fields or options defaults from specified file
	 *
	 * @param string $file
	 * @param bool   $is_defaults
	 */
	private function loadJSON( $file, $is_defaults ) {

		if ( ! file_exists( $file ) ) {
			return;
		}

		$content = file_get_contents( $file );
		$values = json_decode( $content, true );

		if ( null === $values ) {
			return;
		}

		if ( $is_defaults ) {
			$this->defaults = $values;
		} else {
			$this->options = $values;
		}

	}
	
	/**
	 * Add new option field
	 *
	 * @param string $key
	 * @param string $field_type
	 * @param mixed  $default
	 */
	public function addOption( $key, $field_type, $default ) {
		$this->options[ $key ] = $field_type;
		$this->defaults[ $key ] = $default;
	}

	/**
	 * Gets an option value or its default value
	 *
	 * @param  string $key      Option key
	 * @param  mixed  $fallback Option fallback value if no default is set
	 *
	 * @return mixed The value specified for the option or a default value for the option.
	 */
    public function getOption( $key, $fallback = null ) {

        $this->maybeLoad();

        // get option default if unset
        if ( ! isset( $this->values[ $key ] ) ) {
            $this->values[ $key ] = isset( $this->defaults[ $key ] )
                ? $this->defaults[ $key ] : null;
        }

        // use fall back value if default is not set
        if ( null === $this->values[ $key ] && ! is_null( $fallback ) ) {
            $this->values[ $key ] = $fallback;
        }

        return $this->values[ $key ];

    }
    public function setOption($key, $value){
        $this->maybeLoad();
        if (isset($value) ) {
            $this->values[ $key ] = $value;
        }
    }
	/**
	 * Load values from database
	 *
	 * @param bool $force Force options load
	 */
	private function maybeLoad( $force = false ) {

		if ( $force || empty( $this->values ) ) {
			$this->values = get_option( $this->option_key, null );
		}

		// if there are no settings defined, use default values
		if ( ! is_array( $this->values ) ) {
			$this->values = $this->defaults;
		}

	}

    public function reloadOptions() {
        $this->maybeLoad( true );
    }

	/**
	 * Sanitize and save options
	 *
	 * @param null|array $values Optional. If set, options values will be received from param instead of $_POST.
	 */
    public function updateOptions( $values = null ) {

        $this->maybeLoad();

	    if ( is_array( $values ) ) {
		    $form_data = $values;
	    } else {
		    $form_data = isset( $_POST['pys'][ $this->slug ] ) ? $_POST['pys'][ $this->slug ] : array();
	    }

	    // save posted fields
        foreach ( $form_data as $key => $value ) {

	        if ( isset( $this->options[ $key ] ) ) {
		        $this->values[ $key ] = $this->sanitize_form_field( $key, $value );
	        }

        }

        update_option( $this->option_key, $this->values );

    }
	
	/**
	 * Sanitize form field
	 *
	 * @param string $key   Field key
	 * @param array  $value Field value
	 *
	 * @return mixed Sanitized field value
	 */
	private function sanitize_form_field( $key, $value ) {

	    $type = $this->options[ $key ];

		// look for very specific sanitization filter
		$filter_name = "{$this->option_key}_settings_sanitize_{$key}_field";
		if ( has_filter( $filter_name ) ) {
			return apply_filters( $filter_name, $value );
		}

		// look for a sanitize_FIELDTYPE_field method
		if ( is_callable( array( $this, 'sanitize_' . $type . '_field' ) ) ) {
			return $this->{'sanitize_' . $type . '_field'}( $value );
		}

		// fallback to text
		return $this->sanitize_text_field( $value );

	}

	/**
	 * Output text input
	 *
	 * @param        $key
	 * @param string $placeholder
	 * @param bool   $disabled
	 * @param bool   $hidden
     * @param bool   $empty
	 */
    public function render_text_input( $key, $placeholder = '', $disabled = false, $hidden = false, $empty = false) {

        $attr_name = "pys[$this->slug][$key]";
        $attr_id = 'pys_' . $this->slug . '_' . $key;
        $attr_value = $empty == false ? $this->getOption( $key ) : "";
		
		$classes = array( 'form-control' );
		
		if( $hidden ) {
		    $classes[] = 'form-control-hidden';
        }
		
		$classes = implode( ' ', $classes );
		
		?>

        <input <?php disabled( $disabled ); ?> type="text" name="<?php esc_attr_e( $attr_name ); ?>"
                                               id="<?php esc_attr_e( $attr_id ); ?>"
                                               value="<?php esc_attr_e( $attr_value ); ?>"
                                               placeholder="<?php esc_attr_e( $placeholder ); ?>"
                                               class="<?php esc_attr_e( $classes ); ?>">

		<?php

	}
	
	/**
	 * Output pixel ID input (text)
	 *
	 * @param        $key
	 * @param string $placeholder
	 * @param int    $index
	 */
	public function render_pixel_id( $key, $placeholder = '', $index = 0 ) {
        
        $attr_name = "pys[$this->slug][$key][]";
        $attr_id = 'pys_' . $this->slug . '_' . $key . '_' . $index;
		
		$values = (array) $this->getOption( $key );
		$attr_value = isset( $values[ $index ] ) ? $values[ $index ] : null;
  
		?>
        
        <input type="text" name="<?php esc_attr_e( $attr_name ); ?>"
               id="<?php esc_attr_e( $attr_id ); ?>"
               value="<?php esc_attr_e( $attr_value ); ?>"
               placeholder="<?php esc_attr_e( $placeholder ); ?>"
               class="form-control">
		
		<?php
		
	}

    /**
     * Output text area input array item
     *
     * @param        $key
     * @param string $placeholder
     * @param int    $index
     */
    public function render_text_area_array_item( $key, $placeholder = '', $index = 0 ) {

        $attr_name = "pys[$this->slug][$key][]";
        $attr_id = 'pys_' . $this->slug . '_' . $key . '_' . $index;

        $values = (array) $this->getOption( $key );
        $attr_value = isset( $values[ $index ] ) ? $values[ $index ] : null;

        ?>

        <textarea type="text" name="<?php esc_attr_e( $attr_name ); ?>"
                  id="<?php esc_attr_e( $attr_id ); ?>"
                  placeholder="<?php esc_attr_e( $placeholder ); ?>"
                  class="form-control"><?php esc_attr_e( $attr_value ); ?></textarea>

        <?php
    }

    /**
     * Output text input array item
     *
     * @param        $key
     * @param string $placeholder
     * @param int    $index
     */
    public function render_text_input_array_item( $key, $placeholder = '', $index = 0,$hidden = false ) {

        $attr_name = "pys[$this->slug][$key][]";
        $attr_id = 'pys_' . $this->slug . '_' . $key . '_' . $index;

        $values = (array) $this->getOption( $key );
        $attr_value = isset( $values[ $index ] ) ? $values[ $index ] : null;

        ?>

        <input type=<?=$hidden? "hidden": "text"?> name="<?php esc_attr_e( $attr_name ); ?>"
               id="<?php esc_attr_e( $attr_id ); ?>"
               value="<?php esc_attr_e( $attr_value ); ?>"
               placeholder="<?php esc_attr_e( $placeholder ); ?>"
               class="form-control">
        <?php
    }
	
	/**
	 * Output text area input
	 *
	 * @param        $key
	 * @param string $placeholder
	 * @param bool   $disabled
	 * @param bool   $hidden
	 */
	public function render_text_area_input( $key, $placeholder = '', $disabled = false, $hidden = false ) {

		$attr_name = "pys[$this->slug][$key]";
		$attr_id = 'pys_' . $this->slug . '_' . $key;
		$attr_value = $this->getOption( $key );

		$classes = array( 'form-control' );

		if( $hidden ) {
			$classes[] = 'form-control-hidden';
		}

		$classes = implode( ' ', $classes );

		?>

        <textarea <?php disabled( $disabled ); ?> name="<?php esc_attr_e( $attr_name ); ?>"
              id="<?php esc_attr_e( $attr_id ); ?>" rows="5"
              placeholder="<?php esc_attr_e( $placeholder ); ?>"
              class="<?php esc_attr_e( $classes ); ?>"><?php esc_html_e( $attr_value ); ?></textarea>

		<?php

	}
	
	/**
	 * Output checkbox input stylized as switcher
	 *
	 * @param      $key
	 * @param bool $collapse
	 * @param bool $disabled
	 */
    public function render_switcher_input( $key, $collapse = false, $disabled = false, $default = false) {

	    $attr_name = "pys[$this->slug][$key]";
	    $attr_id = 'pys_' . $this->slug . '_' . $key;
	    $attr_value = $this->getOption( $key );
	    
	    $classes = array( 'custom-switch' );
	    
	    if ( $collapse ) {
	        $classes[] = 'collapse-control';
        }
        
        if ( $disabled ) {
	        $classes[] = 'disabled';
            $attr_name = "";
            $attr_value = $default;
        }
        
        $classes = implode( ' ', $classes );

        ?>

        <div class="<?php esc_attr_e( $classes ); ?>">

            <?php if ( ! $disabled ) : ?>
                <input type="hidden" name="<?php esc_attr_e( $attr_name ); ?>" value="0">
            <?php endif; ?>
            
            <?php if ( $collapse ) : ?>
                <input type="checkbox" name="<?php esc_attr_e( $attr_name ); ?>" value="1" <?php disabled( $disabled,
		            true ); ?> <?php checked( $attr_value, true ); ?>
                       id="<?php esc_attr_e( $attr_id ); ?>"
                       class="custom-switch-input"
                       data-target="pys_<?php esc_attr_e( $this->slug ); ?>_<?php esc_attr_e( $key ); ?>_panel">
            <?php else : ?>
                <input type="checkbox" name="<?php esc_attr_e( $attr_name ); ?>" value="1" <?php disabled( $disabled,
		            true ); ?> <?php checked( $attr_value, true ); ?> id="<?php esc_attr_e( $attr_id ); ?>"
                       class="custom-switch-input">
            <?php endif; ?>
            
            <label class="custom-switch-btn" for="<?php esc_attr_e( $attr_id ); ?>"></label>
        </div>

        <?php

    }

	public function render_switcher_input_array( $key, $index = 0) {

		$attr_name  = "pys[$this->slug][$key][]";
		$attr_id = 'pys_' . $this->slug . '_' . $key."_".$index;
		$attr_values = (array)$this->getOption( $key );
		$value = "index_".$index;
		$valueIndex = array_search($value,$attr_values);

		$classes = array( 'custom-switch' );

		$classes = implode( ' ', $classes );

		?>

        <div class="<?php esc_attr_e( $classes ); ?>">
            <input type="checkbox"
                   name="<?php esc_attr_e( $attr_name ); ?>"
                   value="<?=$value?>"
				<?=$valueIndex !== false ? "checked" : "" ?>
                   id="<?php esc_attr_e( $attr_id ); ?>"
                   class="custom-switch-input">

            <label class="custom-switch-btn" for="<?php esc_attr_e( $attr_id ); ?>"></label>
        </div>

		<?php

	}
	
	/**
	 * Output checkbox input
	 *
	 * @param      $key
	 * @param      $label
	 * @param bool $disabled
	 */
	public function render_checkbox_input( $key, $label, $disabled = false ) {
  
		$attr_name  = "pys[$this->slug][$key]";
		$attr_value = $this->getOption( $key );
		
		?>

        <label class="custom-control custom-checkbox">
            <input type="hidden" name="<?php esc_attr_e( $attr_name ); ?>" value="0">
            <input type="checkbox" name="<?php esc_attr_e( $attr_name ); ?>" value="1"
                   class="custom-control-input" <?php disabled( $disabled, true ); ?> <?php checked( $attr_value,
                true ); ?>>
            <span class="custom-control-indicator"></span>
            <span class="custom-control-description"><?php echo wp_kses_post( $label ); ?></span>
        </label>
        
		<?php
		
	}
	
	/**
	 * Output radio input
	 *
	 * @param      $key
	 * @param      $value
	 * @param      $label
	 * @param bool $disabled
	 */
	public function render_radio_input( $key, $value, $label, $disabled = false, $with_pro_badge = false, $need_cog = false ) {
  
		$attr_name = "pys[$this->slug][$key]";
 
		?>

        <label class="custom-control custom-radio">
            <input type="radio" name="<?php esc_attr_e( $attr_name ); ?>" <?php disabled( $disabled, true ); ?>
                   class="custom-control-input" <?php checked( $this->getOption( $key ), $value ); ?>
                   value="<?php esc_attr_e( $value ); ?>">
            <span class="custom-control-indicator"></span>
            <span class="custom-control-description"><?php echo wp_kses_post( $label ); ?></span>
	        <?php if ( $need_cog ) {
                renderCogBadge();
            } ?>
            <?php if ( $with_pro_badge ) {
                renderProBadge();
            } ?>
        </label>

		<?php
		
	}
	
	/**
	 * Output number input
	 *
	 * @param  string    $key
	 * @param string $placeholder
	 * @param bool $disabled
	 */
	public function render_number_input( $key, $placeholder = '', $disabled = false,$max = null,$min = 0, $step = 'any') {

        $attr_name  = "pys[$this->slug][$key]";
        $attr_id    = 'pys_' . $this->slug . '_' . $key;
        $attr_value = $this->getOption( $key );

        ?>
        <div class="input-number-wrapper">
            <button class="decrease"><i class="fa fa-minus"></i></button>
            <input <?php disabled( $disabled ); ?> type="number" name="<?php esc_attr_e( $attr_name ); ?>"
                                                   id="<?php esc_attr_e( $attr_id ); ?>"
                                                   value="<?php esc_attr_e( $attr_value ); ?>"
                                                   placeholder="<?php esc_attr_e( $placeholder ); ?>"
                                                   min="<?=$min?>" class="form-control"
                <?php if($max != null) : ?> max="<?=$max?>" <?php endif; ?>
                                                   step="<?=$step?>"
            >
            <button class="increase"><i class="fa fa-plus"></i></button>
        </div>
		
		<?php
		
	}
	
	/**
	 * Output select input
	 *
	 * @param      $key
	 * @param      $options
	 * @param bool $disabled
	 * @param null $visibility_target
	 * @param null $visibility_value
	 */
	public function render_select_input( $key, $options, $disabled = false, $visibility_target = null,
        $visibility_value = null ) {
  
		$attr_name = "pys[$this->slug][$key]";
		$attr_id = 'pys_' . $this->slug . '_' . $key;
		
		$classes = array( 'form-control-sm' );
		
		if ( $visibility_target ) {
		    $classes[] = 'controls-visibility';
        }
		
		$classes = implode( ' ', $classes );
		
		?>

        <select class="<?php esc_attr_e( $classes ); ?>" id="<?php esc_attr_e( $attr_id ); ?>"
                name="<?php esc_attr_e( $attr_name ); ?>" <?php disabled( $disabled ); ?>
                data-target="<?php esc_attr_e( $visibility_target ); ?>"
                data-value="<?php esc_attr_e( $visibility_value ); ?>" autocomplete="off">

            <option value="" disabled selected>Please, select...</option>
			
			<?php foreach ( $options as $option_key => $option_value ) : ?>
                <option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $option_key,
					esc_attr( $this->getOption( $key ) ) ); ?> <?php disabled( $option_key,
					'disabled' ); ?>><?php echo esc_attr( $option_value ); ?></option>
			<?php endforeach; ?>
        </select>
		
		<?php
	}
	
	/**
	 * Output multi select input
	 *
	 * @param      $key
	 * @param      $values
	 * @param bool $disabled
	 */
	public function render_multi_select_input( $key, $values, $disabled = false ) {

		$attr_name = "pys[$this->slug][$key][]";
		$attr_id = 'pys_' . $this->slug . '_' . $key;
		
		$selected  = $this->getOption( $key );
		
		?>

        <input type="hidden" name="<?php esc_attr_e( $attr_name ); ?>" value="">
        <select class="form-control pys-pysselect2" name="<?php esc_attr_e( $attr_name ); ?>"
                id="<?php esc_attr_e( $attr_id ); ?>" <?php disabled( $disabled ); ?> style="width: 100%;"
                multiple>
			
			<?php foreach ( $values as $option_key => $option_value ) : ?>
                <option value="<?php echo esc_attr( $option_key ); ?>"
					<?php selected( in_array( $option_key, $selected ) ); ?>
					<?php disabled( $option_key, 'disabled' ); ?>
                >
					<?php echo esc_attr( $option_value ); ?>
                </option>
			<?php endforeach; ?>

        </select>
		
		<?php
	}
	
	/**
	 * Output tags select input
	 *
	 * @param      $key
	 * @param bool $disabled
	 */
	public function render_tags_select_input( $key, $disabled = false ) {

		$attr_name = "pys[$this->slug][$key][]";
		$attr_id = 'pys_' . $this->slug . '_' . $key;

		$tags = $this->getOption( $key );
		$tags = is_array( $tags ) ? array_filter( $tags ) : array();

		?>

        <input type="hidden" name="<?php esc_attr_e( $attr_name ); ?>" value="">
        <select class="form-control pys-tags-pysselect2" name="<?php esc_attr_e( $attr_name ); ?>"
                id="<?php esc_attr_e( $attr_id ); ?>" <?php disabled( $disabled ); ?> style="width: 100%;"
                multiple>

			<?php foreach ( $tags as $tag ) : ?>
                <option value="<?php echo esc_attr( $tag ); ?>" selected>
					<?php echo esc_attr( $tag ); ?>
                </option>
			<?php endforeach; ?>

        </select>

		<?php
	}
    public function render_checkbox_blacklist_input_array( $key, $label, $value, $disabled = false ) {

        $attr_name  = "pys[$this->slug][$key][]";
        $attr_values = (array)$this->getOption( $key );

        $isChecked = in_array($value,$attr_values);
        ?>

        <label class="custom-control custom-checkbox">
            <input type="checkbox" name="<?php esc_attr_e( $attr_name ); ?>" value="<?=$value?>"
                   class="custom-control-input" <?php disabled( $disabled, true ); ?>
                <?php echo $isChecked ? "checked" : ""?>>
            <span class="custom-control-indicator"></span>
            <span class="custom-control-description"><?php echo wp_kses_post( $label ); ?></span>
        </label>

        <?php

    }
	/**
	 * Sanitize text field value
	 *
	 * @param $value
	 *
	 * @return string
	 */
	public function sanitize_text_field( $value ) {

		$value = is_null( $value ) ? '' : $value;

		return wp_kses_post( trim( stripslashes( $value ) ) );

	}
	
	/**
	 * Sanitize textarea field value
	 *
	 * @param $value
	 *
	 * @return string
	 */
	public function sanitize_textarea_field( $value ){

		$value = is_null( $value ) ? '' : $value;

		return trim( stripslashes( $value ) );

	}
	
	/**
	 * Sanitize number field value
	 *
	 * @param $value
	 *
	 * @return int
	 */
	public function sanitize_number_field( $value ) {
		return (int) $value;
	}
	
	/**
	 * Sanitize checkbox field value
	 *
	 * @param $value
	 *
	 * @return bool
	 */
	public function sanitize_checkbox_field( $value ) {

		if ( is_bool( $value ) || is_numeric( $value ) ) {
			return (bool) $value;
		} else {
			return false;
		}

	}
	
	/**
	 * Sanitize radio field value
	 *
	 * @param $value
	 *
	 * @return null|string
	 */
	public function sanitize_radio_field( $value ) {
		return ! is_null( $value ) ? trim( stripslashes( $value ) ) : null;
	}
	
	/**
	 * Sanitize select field value
	 *
	 * @see deepSanitizeTextField()
	 *
	 * @param $value
	 *
	 * @return array|string
	 */
	public function sanitize_select_field( $value ) {
		
		$value = is_null( $value ) ? '' : $value;
		
		return deepSanitizeTextField( stripslashes( $value ) );
		
	}
	
	/**
	 * Sanitize tags select value
	 *
	 * @see deepSanitizeTextField()
	 *
	 * @param $value
	 *
	 * @return array
	 */
	public function sanitize_multi_select_field( $value ) {
		return is_array( $value ) ? array_map( 'PixelYourSite\deepSanitizeTextField', $value ) : array();
	}
	
	/**
	 * @param $value
	 *
	 * @return array
	 */
	public function sanitize_tag_select_field( $value ) {
		return is_array( $value ) ? array_map( 'PixelYourSite\deepSanitizeTextField', $value ) : array();
    }
	
	/**
	 * Sanitize array field value
	 *
	 * @param $values
	 *
	 * @return array
	 */
	public function sanitize_array_field( $values ) {
		
		$values = is_array( $values ) ? $values : array();
		$sanitized = array();
		
		foreach ( $values as $key => $value ) {
			
			$new_value = $this->sanitize_text_field( $value );
			
			if ( ! empty( $new_value ) && ! in_array( $new_value, $sanitized ) ) {
				$sanitized[ $key ] = $new_value;
			}
			
		}
		
		return $sanitized;
		
	}

/**
* Sanitize array field value
*
* @param $values
*
* @return array
*/
    public function sanitize_array_textarea_field( $values ) {

        $values = is_array( $values ) ? $values : array();
        $sanitized = array();

        foreach ( $values as $key => $value ) {

            $new_value = $this->sanitize_textarea_field( $value );

            if ( ! empty( $new_value ) && ! in_array( $new_value, $sanitized ) ) {
                $sanitized[ $key ] = $new_value;
            }

        }

        return $sanitized;
    }
	/**
	 * Sanitize array field value with duplicates value
	 *
	 * @param $values
	 *
	 * @return array
	 */
	public function sanitize_array_v_field( $values ) {

		$values = is_array( $values ) ? $values : array();
		$sanitized = array();

		foreach ( $values as $key => $value ) {

			$new_value = $this->sanitize_text_field( $value );

			if ( ! empty( $new_value ) ) {
				$sanitized[ $key ] = $new_value;
			}

		}

		return $sanitized;

	}
    public function render_checkbox_input_array( $key, $label, $index = 0, $disabled = false ) {

        $attr_name  = "pys[$this->slug][$key][]";
        $attr_values = (array)$this->getOption( $key );
        $value = "index_".$index;
        $valueIndex = array_search($value,$attr_values);

        ?>

        <label class="custom-control custom-checkbox">
            <input type="checkbox" name="<?php esc_attr_e( $attr_name ); ?>" value="<?=$value?>"
                   class="custom-control-input" <?php disabled( $disabled, true ); ?>
                <?=$valueIndex !== false ? "checked" : "" ?>>
            <span class="custom-control-indicator"></span>
            <span class="custom-control-description"><?php echo wp_kses_post( $label ); ?></span>
        </label>

        <?php

    }
    function renderDummyTextInput( $placeholder = '' ) {
        ?>

        <input type="text" disabled="disabled" placeholder="<?php esc_html_e( $placeholder ); ?>" class="form-control">

        <?php
    }
    function renderDummyNumberInput($default = 0) {
        ?>

        <input type="number" disabled="disabled" min="0" max="100" class="form-control" value="<?=$default?>">

        <?php
    }

    function renderDummySwitcher($isEnable = false) {
        $attr = $isEnable ? " checked='checked'" : "";
        ?>

        <div class="custom-switch disabled">
            <input type="checkbox" value="1" <?=$attr?> disabled="disabled" class="custom-switch-input">
            <label class="custom-switch-btn"></label>
        </div>

        <?php
    }

    function renderDummyCheckbox( $label, $with_pro_badge = false ) {
        ?>

        <label class="custom-control custom-checkbox <?php echo $with_pro_badge ? 'custom-checkbox-badge' : ''; ?>">
            <input type="checkbox" value="1"
                   class="custom-control-input" disabled="disabled">
            <span class="custom-control-indicator"></span>
            <span class="custom-control-description">
            <?php echo wp_kses_post( $label ); ?>
            <?php if ( $with_pro_badge ) {
                renderProBadge();
            } ?>
        </span>
        </label>

        <?php
    }

    function renderDummyRadioInput( $label, $checked = false ) {
        ?>

        <label class="custom-control custom-radio">
            <input type="radio" disabled="disabled"
                   class="custom-control-input" <?php checked( $checked ); ?>>
            <span class="custom-control-indicator"></span>
            <span class="custom-control-description"><?php echo wp_kses_post( $label ); ?></span>
        </label>

        <?php
    }

    function renderDummyTagsFields( $tags = array() ) {
        ?>

        <select class="form-control pys-tags-pysselect2" disabled="disabled" style="width: 100%;" multiple>

            <?php if(!empty($tags)){
                foreach ( $tags as $tag ) : ?>
                    <option value="<?php echo esc_attr( $tag ); ?>" selected>
                        <?php echo esc_attr( $tag ); ?>
                    </option>
                <?php endforeach;
                }
            ?>

        </select>

        <?php
    }

    function renderDummySelectInput( $value, $full_width = false ) {

        $attr_width = $full_width ? 'width: 100%;' : '';

        ?>

        <select class="form-control form-control-sm" disabled="disabled" autocomplete="off" style="<?php esc_attr_e( $attr_width ); ?>">
            <option value="" disabled selected><?php esc_html_e( $value ); ?></option>
        </select>

        <?php
    }

    function renderProBadge( $url = null,$label = "Pro Feature" ) {

        if ( ! $url ) {
            $url = 'https://www.pixelyoursite.com/';
        }

        $url = untrailingslashit( $url ) . '/?utm_source=pys-free-plugin&utm_medium=pro-badge&utm_campaign=pro-feature';

        echo '&nbsp;<a href="' . esc_url( $url ) . '" target="_blank" class="badge badge-pill badge-pro">'.$label.' <i class="fa fa-external-link" aria-hidden="true"></i></a>';
    }
    public function convertTimeToSeconds($timeValue = 24, $type = 'hours')
    {
        switch ($type){
            case 'hours':
                $time = $timeValue * 60 * 60;
                break;
            case 'minute':
                $time = $timeValue * 60;
                break;
            case 'seconds':
                $time = $timeValue;
                break;
        }
        return $time;
    }


    public function renderValueOptionsBlock($context, $useEnable = true) {
        if(empty($context)) return;

        $prefixes = ['purchase', 'complete_registration'];
        if (count(array_filter($prefixes, function($prefix) use ($context) {
                return strpos($context, $prefix) !== false;
            })) > 0) {
            $priceText = 'Order\'s total';
            $percentText = 'Percent of the order\'s total';
        } else{
            $priceText = 'Products price (subtotal)';
            $percentText = 'Percent of the products price (subtotal)';
        }
        ?>
        <div class="row">
            <div class="col">
                <div class="flex-align-center">
                    <?php if(!is_null($this->getOption($context.'_value_enabled')) ||$useEnable) { $this->render_switcher_input( $context.'_value_enabled', true ); } ?>
                    <h4 class="indicator-label title-options-block">Value parameter settings:</h4>
                    <?php renderPopoverButton( $context.'_event_value' ); ?>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col">

                <div <?php if(!is_null($this->getOption($context.'_value_enabled'))){ renderCollapseTargetAttributes( $context.'_value_enabled', PYS() );} ?>>
                    <div class="collapse-inner pt-0">
                        <div class="custom-controls-stacked radio-stacked">
                            <?php $this->render_radio_input( $context.'_value_option', 'price', $priceText ); ?>

                            <?php if (strpos($context, 'edd_') !== 0) { ?>
                                <?php  if ( !isPixelCogActive() ) { ?>
                                    <?php $this->render_radio_input( $context.'_value_option', 'cog',
                                        'Price minus Cost of Goods', true, false, true ); ?>
                                <?php } else { ?>
                                    <?php $this->render_radio_input( $context.'_value_option', 'cog',
                                        'Price minus Cost of Goods', false ); ?>
                                <?php } ?>
                            <?php } ?>
                            <div class="custom-control compact-radio-number">
                                <?php renderDummyRadioInput( $percentText ); ?>
                                <div class="form-inline">
                                    <?php renderDummyNumberInput( 0 ); ?>
                                </div>
                            </div>
                            <div class="custom-control compact-radio-number">
                                <?php $this->render_radio_input( $context.'_value_option', 'global',
                                    'Use Global value' ); ?>
                                <div class="form-inline">
                                    <?php $this->render_number_input( $context.'_value_global' ); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php
    }
}