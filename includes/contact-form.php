<?php

if(!defined('ABSPATH')) {
  die('You cannot be here');
}

add_shortcode('contact', 'show_contact_form');
add_action('rest_api_init', 'create_rest_endpoint');
add_action('init', 'create_submissions_page');
add_action('add_meta_boxes', 'create_meta_box');
add_filter('manage_submission_posts_columns', 'custom_submission_columns');
add_action('manage_submission_posts_custom_column', 'fill_submission_columns', 10, 2); //Priority 10 (loads after WP loads all columns). accepts 2 arguments
add_action('admin_init', 'setup_search');

function setup_search() {
  //Apply search filter (search by metadata other than name) only to submissions
  global $typenow;
  if($typenow === 'submission') {
    add_filter('posts_search', 'submission_search_override', 10, 2);
  }
}

function console_log($output, $with_script_tags = true) {
    $js_code = 'console.log(' . json_encode($output, JSON_HEX_TAG) . 
');';
    if ($with_script_tags) {
        $js_code = '<script>' . $js_code . '</script>';
    }
    echo $js_code;
}

function submission_search_override($search, $query) {
  //Override submission search to allow for searching by email, phone, etc.
  global $wpdb;
  if ($query->is_main_query() && !empty($query->query['s'])) {
    $sql    = "
      or exists (
          select * from {$wpdb->postmeta} where post_id={$wpdb->posts}.ID
          and meta_key in ('name','email','phone','message','Form URL (for distinguishing general messages from inquiries about specific pages or posts)')
          and meta_value like %s
      )
  ";
  console_log("wpdb:");
  console_log($wpdb->postmeta);
    $like   = '%' . $wpdb->esc_like($query->query['s']) . '%';
    $search = preg_replace(
          "#\({$wpdb->posts}.post_title LIKE [^)]+\)\K#",
          $wpdb->prepare($sql, $like),
          $search
    );
  }
  return $search;
}

function fill_submission_columns($column, $post_id) {
  //Populates columns in the back end display with submission data
  switch($column) {
    case 'name':
      echo esc_html(get_post_meta($post_id, 'name', true));
      break;
    case 'email':
      echo esc_html(get_post_meta($post_id, 'email', true));
      break;
    case 'phone':
      echo esc_html(get_post_meta($post_id, 'phone', true));
      break;
    case 'message':
      echo esc_html(get_post_meta($post_id, 'message', true));
      break;
    case 'form-url':
      echo esc_html(get_post_meta($post_id, 'Form URL (for distinguishing general messages from inquiries about specific pages or posts)', true));
      break;
  }
}

function custom_submission_columns($columns) {
  //Creates columns in the back end display of submissions
  $columns = [
    'cb' => $columns['cb'],
    'name' => __('Name', 'contact-plugin'),
    'email' => __('Email', 'contact-plugin'),
    'phone' => __('Phone', 'contact-plugin'),
    'message' => __('Message', 'contact-plugin'),
    'form-url' => __('Form URL', 'contact-plugin')
  ];

  return $columns;
}

function create_meta_box() {
  //Container for form data that cannot be edited like custom fields
  add_meta_box('custom_contact_form', 'Submission', 'display_submission', 'submission');
}

function display_submission() {
  //Displays form submission data in Submission page in back end
  $postmetas = get_post_meta(get_the_ID());
  unset($postmetas['_edit_lock']);

  echo '<ul>';
  foreach($postmetas as $key => $value) {
    echo '<li><strong>' . ucfirst($key) . ':</strong><br>' . esc_html($value[0]) . '</li>';
  }
  echo '</ul>';
}

function create_submissions_page() {
  //Creates page storing form submissions in back end via custom post type
  $args = [
    'public' => true,
    'has_archive' => true,
    'menu_icon' => 'dashicons-email-alt',
    'menu_position' => 99,
    'publicly_queryable' => false,
    'labels' => [
      'name' => 'Submissions',
      'singular_name' => 'Submission',
      'edit_item' => 'View Submission'
    ],
    'capability_type' => 'post',
    'capabilities' => [ 'create_posts' => false ],
    'map_meta_cap' => true, //Users are allowed to view submissions but not edit
    'supports' => false
  ];
  register_post_type('submission', $args);
}

function show_contact_form() {
  include MY_PLUGIN_PATH . '/includes/templates/contact-form.php';
}

function create_rest_endpoint() {
  register_rest_route('v1/contact-form', 'submit', [
    'methods' => 'POST',
    'callback' => 'handle_inquiry'
  ]);
}

function handle_inquiry($data) {
  //Called when form is submitted
  $params = $data->get_params();

  $field_name = sanitize_text_field($params['name']);
  $field_email = sanitize_email($params['email']);
  $field_phone = sanitize_text_field($params['phone']);
  $field_message = sanitize_textarea_field($params['message']);

  if (!wp_verify_nonce($params['_wpnonce'], 'wp_rest')) {
    return new WP_Rest_Response('Message not sent', 422);
  }

  unset($params['_wpnonce']);
  //unset($params['_wp_http_referer']);

  //Send email message
  $admin_email = get_bloginfo('admin_email');
  $admin_name = get_bloginfo('name');

  $recipient_email = get_plugin_options('contact_plugin_recipients');
  if (!$recipient_email) {
    //If no email is set in the plug in settings page, default to admin email
    $recipient_email = $admin_email;
  }

  $headers = [];
  $headers[] = "From: {$admin_name} <{$admin_email}>";
  $headers[] = "Reply-to: {$field_name} <{$field_email}>";
  $headers[] = "Content-Type: text/html";

  $subject = "New inquiry from {$field_name}";

  $message = '';
  $message .= "<h1>Message has been sent from {$field_name}</h1>";

  //Store form data in back end
  $postarr = [
    'post_title' => $field_name,
    'post_type' => 'submission',
    'post_status' => 'publish'
  ];
  $post_id = wp_insert_post($postarr);

  foreach($params as $label => $value) {
    switch($label) {
      case 'message':
        $value = sanitize_textarea_field($value);
        break;
      case 'email':
        $value = sanitize_email($value);
        break;
      case '_wp_http_referer':
        $label = 'Form URL (for distinguishing general messages from inquiries about specific pages or posts)';
        $value = sanitize_text_field($value);
        break;
      default:
        $value = sanitize_text_field($value);
    }
    $message .= '<strong>' . sanitize_text_field(ucfirst($label)). ':</strong> ' . $value . '<br>';
    add_post_meta($post_id, sanitize_text_field($label), $value);
  }

  wp_mail($recipient_email, $subject, $message, $headers);

  //Set confirmation message
  $confirmation_message = "Your message was sent successfully!";
  if (get_plugin_options('contact_plugin_message')) {
    $confirmation_message = get_plugin_options('contact_plugin_message');
    $confirmation_message = str_replace('{name}', $field_name, $confirmation_message);
    $confirmation_message = str_replace('{email}', $field_email, $confirmation_message);
    $confirmation_message = str_replace('{phone}', $field_phone, $confirmation_message);
    $confirmation_message = str_replace('{message}', $field_message, $confirmation_message);
  }
  
  //Return successful response
  return new WP_Rest_Response($confirmation_message, 200);
}