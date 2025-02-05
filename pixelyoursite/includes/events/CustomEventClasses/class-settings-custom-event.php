<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

abstract class SettingsCustomEvent {

    private $slug = null;

    /**
     * Constructor
     *
     * @param string $slug
     * @param object $event
     */
    public function __construct($slug) {
        $this->slug = $slug;
    }

    public function getSlug() {
        return $this->slug;
    }

    public function render_text_input( $key, $placeholder = '',$conditional_type = null, $disabled = false, $hidden = false, $empty = false) {
        $i = $this->getConditionIndex();
        if(!empty($conditional_type)){
            $attr_name = "pys[event][$this->slug][$i][$conditional_type][$key]";
            $attr_id    = 'pys_event_' . $this->slug . '_' . $i . '_'.$conditional_type. '_' . $key;
        } else {
            $attr_name = "pys[event][$this->slug][$i][$key]";
            $attr_id    = 'pys_event_' . $this->slug . '_' . $i . '_' . $key;
        }

        $attr_value = $this->getParam($key);

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
     * Output select input
     *
     * @param      $key
     * @param      $options
     * @param bool $disabled
     * @param null $visibility_target
     * @param null $visibility_value
     */
    public function render_select_input( $key, $options, $conditional_type = null, $classes = '', $disabled = false, $visibility_target = null,
                                         $visibility_value = null ) {

        $disabled_array = array(
            'url_parameters',
            'landing_page',
            'source'
        );
        $i = $this->getConditionIndex();
        if(!empty($conditional_type)){
            $attr_name = "pys[event][$this->slug][$i][$conditional_type][$key]";
            $attr_id    = 'pys_event_' . $this->slug . '_' . $i . '_'.$conditional_type. '_' . $key;
        } else {
            $attr_name = "pys[event][$this->slug][$i][$key]";
            $attr_id    = 'pys_event_' . $this->slug . '_' . $i . '_' . $key;
        }
        $attr_value = $this->getParam($key);
        ?>

        <select class="form-control-sm <?php esc_attr_e( $classes ); ?>" id="<?php esc_attr_e( $attr_id ); ?>"
                name="<?php esc_attr_e( $attr_name ); ?>" <?php disabled( $disabled ); ?>
                data-target="<?php esc_attr_e( $visibility_target ); ?>"
                data-value="<?php esc_attr_e( $visibility_value ); ?>" autocomplete="off" style="width: 100%;">

            <option value="" disabled selected>Please, select...</option>

            <?php foreach ( $options as $option_key => $option_value ) : ?>
                <option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $option_key,
                    esc_attr( $attr_value ) ); ?> <?php disabled(in_array($option_key, $disabled_array, true)); ?>><?php echo esc_attr( $option_value ); ?></option>
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
    public function render_multi_select_input( $key, $values, $disabled = false ,$placeholder = "" , $classes = '', $pysselect2 = true ) {
        $i = $this->getConditionIndex();
        $attr_name = "pys[event][$this->slug][$i][$key][]";
        $attr_id    = 'pys_event_' . $this->slug . '_' . $i . '_' . $key;
        $selected  = $this->getParam($key) ? $this->getParam($key) : array();
        if ( !empty( $classes ) ) {
            $classes = ' ' . $classes;
        } else {
            $classes = $pysselect2 ? 'pys-pysselect2' : '';
        }
        ?>

        <input type="hidden" name="<?php esc_attr_e( $attr_name ); ?>" value="">
        <select class="form-control <?php esc_attr_e( $classes ); ?>"
                data-placeholder="<?=$placeholder?>"
                name="<?php esc_attr_e( $attr_name ); ?>"
                id="<?php esc_attr_e( $attr_id ); ?>" <?php disabled( $disabled ); ?> style="width: 100%;"
                multiple>
            <?php foreach ( $values as $option_key => $option_value ) : ?>
                <option value="<?php echo esc_attr( $option_key ); ?>"
                    <?php selected(  in_array($option_key,$selected)  ); ?>
                    <?php disabled( $option_key, 'disabled' ); ?>
                >
                    <?php echo esc_attr( $option_value ); ?>
                </option>
            <?php endforeach; ?>

        </select>

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
    public function render_radio_input( $key, $value, $label, $disabled = false ) {
        $i = $this->getConditionIndex();
        $attr_name = "pys[event][$this->slug][$i][$key]";
        $attr_value = $this->getParam($key);
        ?>

        <label class="custom-control custom-radio">
            <input type="radio" name="<?php esc_attr_e( $attr_name ); ?>" <?php disabled( $disabled, true ); ?>
                   class="custom-control-input" <?php checked( $attr_value, $value ); ?>
                   value="<?php esc_attr_e( $value ); ?>">
            <span class="custom-control-indicator"></span>
            <span class="custom-control-description"><?php echo wp_kses_post( $label ); ?></span>
        </label>

        <?php

    }
}