<?php
/**
 * Plugin Name: Gemini API Endpoint
 * Description: Common seat calculation functions for events (RSVP + Ticket purchases)
 * Version: 1.1
 * Author: Jasmine
 */

add_action('rest_api_init', function () {

    register_rest_route('events-ai/v1', '/events', array(
        'methods'  => 'GET',
        'callback' => 'get_events_for_ai',
    ));

});

function get_events_for_ai() {

    $args = array(
        'post_type' => 'event',
        'posts_per_page' => 10
    );

    $query = new WP_Query($args);

    $events = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {

            $query->the_post();

            $events[] = array(
                'title' => get_the_title(),
                'date' => get_field('event_date'),
                'price' => get_field('ticket_price'),
                'organizer' => get_field('organizer_name'),
                'link' => get_permalink()
            );
        }
    }

    wp_reset_postdata();

    return $events;
}