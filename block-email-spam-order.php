<?php
/**
 * Plugin Name: Block Spam Orders with Advanced Analytics
 * Plugin URI: https://jits.ng
 * Description: Blocks spam orders in WooCommerce by rejecting emails that match a specific spam pattern. Tracks blocked attempts with advanced analytics.
 * Version: 1.6
 * Author: Akande Joshua
 * Author URI: https://jits.ng
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Hook into WooCommerce checkout validation
add_action('woocommerce_after_checkout_validation', 'block_spam_emails_by_combined_pattern', 10, 2);

function block_spam_emails_by_combined_pattern($fields, $errors) {
    $email = isset($fields['billing_email']) ? $fields['billing_email'] : '';
    $full_name = isset($fields['billing_full_name']) ? $fields['billing_full_name'] : '';
    $first_name = isset($fields['billing_first_name']) ? $fields['billing_first_name'] : '';
    $last_name = isset($fields['billing_last_name']) ? $fields['billing_last_name'] : '';

    $patterns_to_check = [];

    if ($first_name && $last_name) {
        $name_combination = preg_replace('/\s+/', '', $first_name . $last_name);
        $patterns_to_check[] = '/^' . preg_quote($name_combination, '/') . '\.\d+@gmail\.com$/i';
    }

    if ($full_name) {
        $name_combination = preg_replace('/\s+/', '', $full_name);
        $patterns_to_check[] = '/^' . preg_quote($name_combination, '/') . '\.\d+@gmail\.com$/i';
    }

    foreach ($patterns_to_check as $pattern) {
        if (preg_match($pattern, $email)) {
            // Log the block event with a timestamp
            $blocked_logs = get_option('spam_block_logs', []);
            $blocked_logs[] = ['time' => current_time('timestamp')];
            update_option('spam_block_logs', $blocked_logs);

            // Block the email and show an error message
            $errors->add('validation', 'Sorry, we cannot accept this order. Please contact support.');
            return;
        }
    }
}

// Enqueue JavaScript for client-side validation
add_action('wp_enqueue_scripts', 'enqueue_spam_block_script');

function enqueue_spam_block_script() {
    if (is_checkout()) {
        wp_enqueue_script('spam-block-checkout', plugin_dir_url(__FILE__) . 'spam-block.js', ['jquery'], '1.0', true);

        // Pass validation data to JavaScript
        wp_localize_script('spam-block-checkout', 'spamBlockConfig', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'error_message' => 'Sorry, we cannot accept this order. Please contact support.',
        ]);
    }
}

// Handle AJAX request for client-side validation
add_action('wp_ajax_validate_spam_email', 'ajax_validate_spam_email');
add_action('wp_ajax_nopriv_validate_spam_email', 'ajax_validate_spam_email');

function ajax_validate_spam_email() {
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $full_name = isset($_POST['full_name']) ? sanitize_text_field($_POST['full_name']) : '';
    $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';

    $patterns_to_check = [];

    if ($first_name && $last_name) {
        $name_combination = preg_replace('/\s+/', '', $first_name . $last_name);
        $patterns_to_check[] = '/^' . preg_quote($name_combination, '/') . '\.\d+@gmail\.com$/i';
    }

    if ($full_name) {
        $name_combination = preg_replace('/\s+/', '', $full_name);
        $patterns_to_check[] = '/^' . preg_quote($name_combination, '/') . '\.\d+@gmail\.com$/i';
    }

    foreach ($patterns_to_check as $pattern) {
        if (preg_match($pattern, $email)) {
            wp_send_json_error(['message' => 'Spam email detected.']);
        }
    }

    wp_send_json_success();
}

// Admin menu for analytics page
add_action('admin_menu', 'spam_block_analytics_menu');

function spam_block_analytics_menu() {
    add_menu_page(
        'Spam Block Analytics',
        'Spam Block Analytics',
        'manage_options',
        'spam-block-analytics',
        'render_spam_block_analytics_page',
        'dashicons-chart-bar',
        20
    );
}

function render_spam_block_analytics_page() {
    $blocked_logs = get_option('spam_block_logs', []);
    $blocked_today = 0;
    $blocked_yesterday = 0;
    $blocked_last_7_days = 0;
    $blocked_last_30_days = 0;

    $now = current_time('timestamp');
    $start_of_today = strtotime('today midnight', $now);
    $start_of_yesterday = strtotime('yesterday midnight', $now);
    $start_of_last_7_days = strtotime('-7 days', $now);
    $start_of_last_30_days = strtotime('-30 days', $now);

    foreach ($blocked_logs as $log) {
        if ($log['time'] >= $start_of_today) {
            $blocked_today++;
        }
        if ($log['time'] >= $start_of_yesterday && $log['time'] < $start_of_today) {
            $blocked_yesterday++;
        }
        if ($log['time'] >= $start_of_last_7_days) {
            $blocked_last_7_days++;
        }
        if ($log['time'] >= $start_of_last_30_days) {
            $blocked_last_30_days++;
        }
    }

    echo '<div class="wrap">';
    echo '<h1>Spam Block Analytics</h1>';
    echo '<p>View the number of blocked attempts over various time periods.</p>';
    echo '<ul>';
    echo '<li><strong>Blocked Today:</strong> ' . $blocked_today . '</li>';
    echo '<li><strong>Blocked Yesterday:</strong> ' . $blocked_yesterday . '</li>';
    echo '<li><strong>Blocked Last 7 Days:</strong> ' . $blocked_last_7_days . '</li>';
    echo '<li><strong>Blocked Last 30 Days:</strong> ' . $blocked_last_30_days . '</li>';
    echo '<li><strong>Total Blocked:</strong> ' . count($blocked_logs) . '</li>';
    echo '</ul>';
    echo '</div>';
}

// Dashboard widget for quick analytics
add_action('wp_dashboard_setup', 'spam_block_dashboard_widget');

function spam_block_dashboard_widget() {
    wp_add_dashboard_widget(
        'spam_block_dashboard_widget',
        'Spam Block Analytics',
        'render_spam_block_dashboard_widget'
    );
}

function render_spam_block_dashboard_widget() {
    $blocked_logs = get_option('spam_block_logs', []);
    $blocked_today = 0;

    $now = current_time('timestamp');
    $start_of_today = strtotime('today midnight', $now);

    foreach ($blocked_logs as $log) {
        if ($log['time'] >= $start_of_today) {
            $blocked_today++;
        }
    }

    echo '<p><strong>Blocked Today:</strong> ' . $blocked_today . '</p>';
    echo '<p><strong>Total Blocked:</strong> ' . count($blocked_logs) . '</p>';
}
