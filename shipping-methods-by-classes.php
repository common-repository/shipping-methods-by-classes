<?php
/*
Plugin Name: Shipping Methods by Classes
Text Domain: shipping-methods-by-classes
Description: Disable some shipping methods depending on shipping classes
Author: N.O.U.S. Open Useful and Simple
Author URI: https://www.avecnous.eu
Version: 1.0
*/
namespace ShippingMethodsbyClasses;

\add_filter('woocommerce_get_sections_shipping',  'ShippingMethodsbyClasses\woocommerce_get_sections_shipping', 10, 1 );
\add_filter('woocommerce_get_settings_shipping',  'ShippingMethodsbyClasses\woocommerce_get_settings_shipping', 10, 2 );
\add_filter('woocommerce_package_rates', 'ShippingMethodsbyClasses\woocommerce_package_rates', 100 );
\add_filter('plugin_action_links_shipping-methods-by-classes/shipping-methods-by-classes.php', 'ShippingMethodsbyClasses\settings_link' );

/**
 * Adds a shortcut for settings
 * @param  array $links
 * @return array $links
 */
function settings_link( $links ) {
    $settings_link = '<a href="'.add_query_arg(['page'=>'wc-settings', 'tab'=>'shipping', 'section'=>'shipping-methods-by-classes'], admin_url('admin.php')).'">' . __('Settings') . '</a>';
    // place it before other links
    array_unshift( $links, $settings_link );
    return $links;
}

/**
* Add a subsection to shipping settings in WC
* @param  array $sections
* @return array $sections
*/
function woocommerce_get_sections_shipping($sections){
    $sections['shipping-methods-by-classes'] = __('Shipping Methods by Classes', 'shipping-methods-by-classes');
    return $sections;
}

/**
* Populate subsection in product settings in WC
* @param  array $settings          wp_settings
* @param  string $current_section
* @return array                   $settings wp_settings
*/
function woocommerce_get_settings_shipping($settings, $current_section){
    if ( 'shipping-methods-by-classes' === $current_section ) {
        $settings = array();

        $classes = \WC()->shipping()->get_shipping_classes();
        $zones = \WC_Shipping_Zones::get_zones();

        $shipping_method_instances = [];
        foreach ($zones as $zone_id=>$zone) {
            foreach ($zone['shipping_methods'] as $shipping_method) {
                $shipping_method_instances[$shipping_method->id.':'.$shipping_method->instance_id] = $shipping_method->title.' ('.$zone['zone_name'].')';
            }
        }

        $settings[] = array(
            'title' => __( 'Shipping rates', 'shipping-methods-by-classes' ),
            'desc'    => __( 'Disable these shipping methods', 'shipping-methods-by-classes' ),
            'type'  => 'title',
            'id'    => 'shipping-methods-by-classes',
        );

        foreach ($classes as $class) {
            $settings[] = array(
                'title' => $class->name,
                'type'  => 'title',
                'id'    => 'shipping-methods-by-classes-'.$class->slug,
            );
            foreach ($shipping_method_instances as $instance_id=>$instance_title) {
                $settings[] = array(
                    'title'   => $instance_title,
                    'id'      => 'disable_shipping_methods_by_classes_'.$class->slug.'['.$instance_id.']',
                    'type'    => 'checkbox',
                    'default' => '',
                );
            }
            $settings[] = array(
                'type' => 'sectionend',
                'id'   => 'shipping-methods-by-classes-'.$class->slug,
            );
        }

        $settings[] = array(
            'type' => 'sectionend',
            'id'   => 'shipping-methods-by-classes',
        );
        $settings = apply_filters('woocommerce_settings_archives', $settings);
    }
    return $settings;
}

/**
* Disable shipping rates when an item uses defined shipping class
*
* @param array $rates Array of rates found for the package.
* @return array
*/
function woocommerce_package_rates( $rates ) {
    $cart_items = WC()->cart->get_cart();
    $rate_to_exclude = [];

    foreach($cart_items as $item){
        $class = $item['data']->get_shipping_class();
        if(false != $shippping_class_options = get_option('disable_shipping_methods_by_classes_'.$class)){
            $shippping_class_rules = array_keys(array_filter($shippping_class_options, function($v, $k) {
                return $v == 'yes';
            }, ARRAY_FILTER_USE_BOTH));
            $items_to_exclude[] = $item['data']->get_name();
            $rate_to_exclude = array_merge($rate_to_exclude, $shippping_class_rules);
        }
    }

    foreach ( $rates as $rate_id => $rate ) {
        if ( in_array($rate_id, $rate_to_exclude) ) {
            wc_add_notice( sprintf(__("The following products: %s can't be delivered with the shipping method: %s", 'shipping-methods-by-classes'), implode(', ', $items_to_exclude), $rate->get_label()), 'notice' );
            unset( $rates[ $rate_id ] );
        }
    }

    return $rates;
}
