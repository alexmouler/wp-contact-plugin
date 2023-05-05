<?php

if(!defined('ABSPATH')) {
  die('You cannot be here');
}

use Carbon_Fields\Container;
use Carbon_Fields\Field;

add_action('after_setup_theme', 'load_carbon_fields');
function load_carbon_fields() {
  \Carbon_Fields\Carbon_Fields::boot();
}

add_action('carbon_fields_register_fields', 'create_options_page');
function create_options_page() {
  //Copied from Carbon Fields docs
  Container::make( 'theme_options', __( 'Contact Form' ) )
    ->set_page_menu_position(100)
    ->set_icon( 'dashicons-email-alt' )
    ->add_fields( array(
      Field::make( 'checkbox', 'contact_plugin_active', __( 'Active' ) ),
      Field::make( 'text', 'contact_plugin_recipients', __( 'Recipient Email' ) )
        ->set_attribute( 'placeholder', 'yourname@example.com' )
        ->set_help_text( 'The email that the form is submitted to' ),
      Field::make( 'textarea', 'contact_plugin_message', __( 'Confirmation Message' ) )
        ->set_attribute( 'placeholder', 'eg. Thanks for your submission' )
        ->set_help_text( 'Type the message you want the submitter to receive' )
    ) );
}