<?php
/**
 * Plugin Name: Event Seats Manager
 * Description: Common seat calculation functions for events (RSVP + Ticket purchases)
 * Version: 1.1
 * Author: Jasmine
 */

if (!defined('ABSPATH')) exit;


/**
 * Count RSVP
 */
function event_get_rsvp_count($event_id)
{
    $query = new WP_Query([
        'post_type'      => 'rsvp',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'   => 'event_id',
                'value' => $event_id,
            ],
        ],
    ]);

    return (int) $query->found_posts;
}


/**
 * Count tickets sold
 */
function event_get_ticket_quantity($event_id)
{
    $query = new WP_Query([
        'post_type'      => 'ticket_order',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            [
                'key'   => 'event_id',
                'value' => $event_id,
            ],
        ],
    ]);

    $total = 0;

    foreach ($query->posts as $post) {
        $qty = get_post_meta($post->ID, 'quantity', true);
        $total += intval($qty);
    }

    return $total;
}


/**
 * Get remaining seats
 */
function event_get_remaining_seats($event_id)
{
    $limit = (int) get_post_meta($event_id, 'rsvp_limit', true);

    $rsvp_count   = event_get_rsvp_count($event_id);
    $ticket_count = event_get_ticket_quantity($event_id);

    $remaining = $limit - ($rsvp_count + $ticket_count);

    return max($remaining, 0);
}


/**
 * Seat summary helper (NEW)
 * Used by other plugins like RSVP and Razorpay
 */
function event_get_seat_summary($event_id)
{
    $limit      = (int) get_post_meta($event_id, 'rsvp_limit', true);
    $rsvp       = event_get_rsvp_count($event_id);
    $tickets    = event_get_ticket_quantity($event_id);
    $remaining  = event_get_remaining_seats($event_id);

    return [
        'event_id'        => $event_id,
        'total_seats'     => $limit,
        'rsvp_count'      => $rsvp,
        'tickets_sold'    => $tickets,
        'remaining_seats' => $remaining
    ];
}


/**
 * REST API to fetch seats
 */
add_action('rest_api_init', function () {

    register_rest_route('events/v1', '/event-seats/(?P<id>\d+)', [
        'methods'  => 'GET',
        'callback' => 'event_get_seats_api',
        'permission_callback' => '__return_true',
    ]);
});


function event_get_seats_api($request)
{
    $event_id = intval($request['id']);

    if (!$event_id || !get_post($event_id)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Invalid event ID'
        ], 404);
    }

    $data = event_get_seat_summary($event_id);

    return new WP_REST_Response([
        'success' => true,
        'data' => $data
    ], 200);
}