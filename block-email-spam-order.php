<?php
/**
 * Plugin Name: Block Spam Orders by Email Pattern
 * Plugin URI: https://jamade.it
 * Description: Blocks spam orders in WooCommerce by rejecting emails that match a specific spam pattern.
 * Version: 1.1
 * Author: Akande Joshua
 * Author URI: https://jamade.it
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit;
}

// Hook into WooCommerce checkout validation
add_action('woocommerce_after_checkout_validation', 'block_spam_emails_by_pattern', 10, 2);

function block_spam_emails_by_pattern($fields, $errors) {
    $email = isset($fields['billing_email']) ? $fields['billing_email'] : '';
    $first_name = isset($fields['billing_first_name']) ? $fields['billing_first_name'] : '';
    $last_name = isset($fields['billing_last_name']) ? $fields['billing_last_name'] : '';

    // Combine first name and last name without spaces
    $name_combination = preg_replace('/\s+/', '', $first_name . $last_name);

    // Regular expression to match the spam email pattern sseen in orders
    $spam_pattern = '/^' . preg_quote($name_combination, '/') . '\.\d+@gmail\.com$/i';

    if (preg_match($spam_pattern, $email)) {
        $errors->add('validation', 'Sorry, we cannot accept this order. Please Contact Support.');
    }
}
