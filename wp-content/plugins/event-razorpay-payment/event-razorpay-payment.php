<?php

/**
 * Plugin Name: Event Razorpay Payments
 * Description: Razorpay payment integration for event tickets
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;


add_action('rest_api_init', function () {

    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');

    add_filter('rest_pre_serve_request', function ($value) {

        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        return $value;
    });
}, 15);


add_action('init', function () {

    register_post_type('ticket_order', [
        'label' => 'Ticket Orders',
        'public' => false,
        'show_ui' => true,
        'menu_icon' => 'dashicons-tickets-alt',
        'supports' => ['title'],
    ]);
});

class Event_Razorpay_Payments
{

    private $key_id = 'rzp_test_SNSjx85bb24zE8';
    private $key_secret = 'MhrfjQxQEsp6MEO6qH7EqBgs';

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST routes
     */
    public function register_routes()
    {

        register_rest_route('events/v1', '/create-order', [
            'methods'  => ['POST', 'OPTIONS'],
            'callback' => [$this, 'create_order'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('events/v1', '/verify-payment', [
            'methods'  => ['POST', 'OPTIONS'],
            'callback' => [$this, 'verify_payment'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Create Razorpay order
     */
    public function create_order($request)
    {

        $amount = intval($request->get_param('amount'));
        $event_id = intval($request->get_param('event_id'));

        if (!$amount) {
            return new WP_Error('invalid_amount', 'Amount required');
        }

        try {

            $api = new Api($this->key_id, $this->key_secret);

            $order = $api->order->create([
                'receipt' => 'event_' . time(),
                'amount' => $amount,
                'currency' => 'INR',
                'notes' => [
                    'event_id' => $event_id,
                ],
            ]);

            return [
                'success' => true,
                'orderId' => $order['id'],
                'amount' => $amount,
                'key' => $this->key_id,
            ];
        } catch (Exception $e) {

            return new WP_Error(
                'order_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Verify Razorpay payment
     */
    public function verify_payment($request)
    {

        $params = $request->get_json_params();
        

        $order_id   = sanitize_text_field($params['razorpay_order_id'] ?? '');
        $payment_id = sanitize_text_field($params['razorpay_payment_id'] ?? '');
        $signature  = sanitize_text_field($params['razorpay_signature'] ?? '');

        $email      = sanitize_email($params['email'] ?? '');
        $event_id   = intval($params['event_id'] ?? 0);
        $quantity   = intval($params['quantity'] ?? 1);

        try {

            $api = new Api($this->key_id, $this->key_secret);

            $attributes = [
                'razorpay_order_id' => $order_id,
                'razorpay_payment_id' => $payment_id,
                'razorpay_signature' => $signature
            ];

            $api->utility->verifyPaymentSignature($attributes);

            /**
             * Save RSVP / ticket
             */
            $post_id = wp_insert_post([
                'post_type' => 'ticket_order',
                'post_title' => 'Ticket Order ' . $payment_id,
                'post_status' => 'publish'
            ]);

            if ($post_id) {

                update_post_meta($post_id, 'event_id', $event_id);
                update_post_meta($post_id, 'email', $email);
                update_post_meta($post_id, 'payment_id', $payment_id);
                update_post_meta($post_id, 'order_id', $order_id);
                update_post_meta($post_id, 'quantity', $quantity);
                update_post_meta($post_id, 'payment_status', 'paid');
                update_post_meta($post_id, 'payment_method', 'razorpay');
            }

            return [
                'success' => true,
                'message' => 'Payment verified'
            ];
        } catch (SignatureVerificationError $e) {

            return new WP_Error(
                'invalid_signature',
                'Payment verification failed'
            );
        } catch (Exception $e) {

            return new WP_Error(
                'verify_error',
                $e->getMessage()
            );
        }
    }
}

new Event_Razorpay_Payments();


add_action('add_meta_boxes', function () {

    add_meta_box(
        'ticket_order_details',
        'Ticket Order Details',
        function ($post) {

            $event_id = get_post_meta($post->ID, 'event_id', true);
            $email = get_post_meta($post->ID, 'email', true);
            $payment_id = get_post_meta($post->ID, 'payment_id', true);
            $order_id = get_post_meta($post->ID, 'order_id', true);
            $quantity = get_post_meta($post->ID, 'quantity', true);

            echo '<table class="widefat striped">';
            echo '<tr><th>Event ID</th><td>' . esc_html($event_id) . '</td></tr>';
            echo '<tr><th>Email</th><td>' . esc_html($email) . '</td></tr>';
            echo '<tr><th>Payment ID</th><td>' . esc_html($payment_id) . '</td></tr>';
            echo '<tr><th>Order ID</th><td>' . esc_html($order_id) . '</td></tr>';
            echo '<tr><th>Quantity</th><td>' . esc_html($quantity) . '</td></tr>';
            echo '</table>';
        },
        'ticket_order',
        'normal',
        'high'
    );
});
