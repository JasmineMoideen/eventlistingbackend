<?php
/**
 * Plugin Name: Count RSVP Against Each Event
 * Description: Count RSVP Against Each Event
 * Version: 1.0
 * Author: Jasmine
 */
 add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=rsvp', // parent = RSVP menu
        'Event RSVP Count',
        'Event RSVP Count',
        'manage_options',
        'event-rsvp-count',
        'render_event_rsvp_count_page'
    );
});

function render_event_rsvp_count_page()
{
    global $wpdb;

    // Get counts grouped by event_id
    $results = $wpdb->get_results("
        SELECT pm.meta_value AS event_id, COUNT(*) AS total
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key = 'event_id'
        AND p.post_type = 'rsvp'
        AND p.post_status = 'publish'
        GROUP BY pm.meta_value
        ORDER BY total DESC
    ");

    echo '<div class="wrap">';
    echo '<h1>Event RSVP Count</h1>';

    if (empty($results)) {
        echo '<p>No RSVPs found.</p>';
        echo '</div>';
        return;
    }

    echo '<table class="widefat striped">';
    echo '<thead>
            <tr>
                <th>Event</th>
                <th>Event ID</th>
                <th>RSVP Count</th>
            </tr>
          </thead>';
    echo '<tbody>';

    foreach ($results as $row) {
        $event_title = get_the_title($row->event_id);

        echo '<tr>';
        echo '<td>' . esc_html($event_title ?: 'Unknown Event') . '</td>';
        echo '<td>' . esc_html($row->event_id) . '</td>';
        echo '<td><strong>' . esc_html($row->total) . '</strong></td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}