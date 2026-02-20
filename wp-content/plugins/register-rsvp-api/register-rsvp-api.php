<?php

/**
 * Plugin Name: Register RSVP API
 * Description: Register RSVP API
 * Version: 1.0
 * Author: Jasmine
 */
// Register rsvp custom post type
add_action('init', function () {
    register_post_type('rsvp', array(
        'label' => 'RSVPs',
        'public' => false,
        'show_ui' => true,
        'supports' => array('title',),
        'show_in_rest' => true,
    ));
});
// Register RSVP API
add_action('rest_api_init', function () {

    register_rest_route('events/v1', '/rsvp', [
        'methods'  => 'POST',
        'callback' => 'save_event_rsvp',
        'permission_callback' => '__return_true',
    ]);
});

function save_event_rsvp($request)
{
    // ---------- Get params ----------
    $params = $request->get_json_params();
    if (empty($params)) {
        $params = $request->get_params();
    }

    // ---------- Sanitize ----------
    $event_id = intval($params['event_id'] ?? 0);
    $name     = sanitize_text_field($params['name'] ?? '');
    $email    = strtolower(sanitize_email($params['email'] ?? ''));

    // ---------- Basic validation ----------
    if (!$event_id || empty($name) || empty($email)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Missing required fields.',
        ], 400);
    }

    if (!is_email($email)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Please enter a valid email address.',
        ], 400);
    }

    $event_title = get_the_title($event_id);

    if (!$event_title) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Invalid event.',
        ], 404);
    }

    // ---------- Duplicate check ----------
    $existing = new WP_Query([
        'post_type'      => 'rsvp',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'   => 'event_id',
                'value' => $event_id,
            ],
            [
                'key'   => 'email',
                'value' => $email,
            ],
        ],
    ]);

    if ($existing->have_posts()) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'This email has already RSVP’d for this event.',
        ], 409);
    }

    // ---------- Create RSVP ----------
    $post_id = wp_insert_post([
        'post_title'  => $name . ' RSVP - ' . $event_title,
        'post_type'   => 'rsvp',
        'post_status' => 'publish',
    ]);

    if (is_wp_error($post_id) || !$post_id) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Failed to submit RSVP.',
        ], 500);
    }

    // ---------- Save meta ----------
    update_post_meta($post_id, 'event_id', $event_id);
    update_post_meta($post_id, 'event_name', $event_title);
    update_post_meta($post_id, 'name', $name);
    update_post_meta($post_id, 'email', $email);

    /* ---------- SEND EMAILS ---------- */

    // Email to attendee
    $user_subject = "RSVP Confirmation - " . $event_title;

    $user_message =
        "Hi $name,\n\n" .
        "You have successfully RSVP'd for:\n\n" .
        "Event: $event_title\n\n" .
        "We look forward to seeing you there.\n\n" .
        "Thank you.";

    wp_mail(
        $email,
        $user_subject,
        $user_message,
        ['Content-Type: text/plain; charset=UTF-8']
    );

    // Email to admin
    $admin_subject = "New RSVP Received";

    $admin_message =
        "A new RSVP has been submitted:\n\n" .
        "Event: $event_title\n" .
        "Name: $name\n" .
        "Email: $email\n";

    wp_mail(
        get_option('admin_email'),
        $admin_subject,
        $admin_message
    );

    // ---------- Success response ----------
    return new WP_REST_Response([
        'success' => true,
        'message' => 'RSVP submitted successfully.',
    ], 200);
}

add_action('add_meta_boxes', function () {
    add_meta_box(
        'rsvp_details',
        'RSVP Details',
        'render_rsvp_details',
        'rsvp',
        'normal',
        'high'
    );
});


function render_rsvp_details($post)
{
    $event_name = get_post_meta($post->ID, 'event_name', true);
    $event_id   = get_post_meta($post->ID, 'event_id', true);
    $name       = get_post_meta($post->ID, 'name', true);
    $email      = get_post_meta($post->ID, 'email', true);

    echo '<table class="widefat striped">';
    echo '<tr><th>Event</th><td>' . esc_html($event_name) . '</td></tr>';
    echo '<tr><th>Event ID</th><td>' . esc_html($event_id) . '</td></tr>';
    echo '<tr><th>Name</th><td>' . esc_html($name) . '</td></tr>';
    echo '<tr><th>Email</th><td>' . esc_html($email) . '</td></tr>';
    echo '</table>';
}
