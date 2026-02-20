<?php
/**
 * Plugin Name: Display ACF Fields
 * Description: Exposes ACF Fields
 * Version: 1.0
 * Author: Jasmine
 */

add_action('rest_api_init', function () {
    register_rest_field('event', 'acf', array(
        'get_callback' => function ($post_arr) {
            return get_fields($post_arr['id']);
        },
        'schema' => null,
    ));
});




/* Custom code to display acf fields in custom post taxonomy Events Category REST API RESPONSE */

add_action('rest_api_init', function () {
    register_rest_field(
        'event-category',
        'acf',
        array(
            'get_callback' => function ($term) {
                return get_fields('event-category_' . $term['id']);
            },
            'schema' => null,
        )
    );
});
