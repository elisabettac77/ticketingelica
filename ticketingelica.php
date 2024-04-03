<?php

/**
 * Plugin Name: ticketingelicaelica
 * Plugin URI: https://elica-webservices.it/
 * Description: A small plugin to provide ticketing functionalities
 * Version: 1.0
 * Author: Elisabetta Carrara
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: ticketingelica
 */
// Register Agent role with Editor-like capabilities
add_role(
    'agent',
    __('Agent'),
    array(
        'edit_posts' => true, // Edit posts
        'edit_others_posts' => true, // Edit others' posts
        'publish_posts' => true, // Publish posts
        'manage_categories' => true, // Manage categories
        'upload_files' => true, // Upload files
        // Add other capabilities as needed for Agent role
    )
);

// Register Customer role with Author-like capabilities
add_role(
    'customer',
    __('Customer'),
    array(
        'write_posts' => true, // Write posts
        'edit_own_posts' => true, // Edit own posts
        'upload_files' => true, // Upload files (optional for Author-like role)
        // Add other capabilities as needed for Customer role
    )
);

// Restrict publish_posts capability for Agent and Customer roles
add_action('admin_init', 'restrict_publish_caps');
function restrict_publish_caps()
{
    $agent_role = get_role('agent');
    $customer_role = get_role('customer');

    $customer_role->remove_cap('publish_posts');
}
// Grant publish_posts for standard Posts, Tickets, and Replies (for Agents)
// Grant publish_posts for Tickets (for Customers)
add_action('admin_init', 'grant_publish_caps');
function grant_publish_caps()
{
    $agent_role = get_role('agent');
    $customer_role = get_role('customer');

    $agent_role->add_cap('publish_posts'); // Standard Posts (built-in)
    $agent_role->add_cap('publish_posts', 'ticket'); // Ticket CPT
    $agent_role->add_cap('publish_posts', 'reply');   // Reply CPT
    $customer_role->add_cap('publish_posts', 'ticket'); // Ticket CPT (for creating their own tickets)
    $customer_role->add_cap('publish_posts', 'reply');   // Reply CPT
}

// Function to remove capability from customer role
// Function to remove capability from customer role (modify this)
function remove_customer_priority_capability()
{
    $customer_role = get_role('customer'); // Get customer role object
    $customer_role->remove_cap('manage_ticket_priority'); // Remove custom capability
}

function create_ticket_cpt()
{

    $labels = array(
        'name'                => _x('Tickets', 'Post Type General Name', 'ticketingelica'),
        'singular_name'       => _x('Ticket', 'Post Type Singular Name', 'ticketingelica'),
        'menu_name'           => __('Tickets', 'ticketingelica'),
        'parent_item_colon'   => __('Parent Ticket:', 'ticketingelica'),
        'all_items'           => __('All Tickets', 'ticketingelica'),
        'view_item'           => __('View Ticket', 'ticketingelica'),
        'add_new_item'        => __('Add New Ticket', 'ticketingelica'),
        'add_new'             => __('Add New', 'ticketingelica'),
        'edit_item'           => __('Edit Ticket', 'ticketingelica'),
        'update_item'         => __('Update Ticket', 'ticketingelica'),
        'search_items'        => __('Search Tickets', 'ticketingelica'),
        'not_found'           => __('No tickets found', 'ticketingelica'),
        'not_found_in_trash'  => __('No tickets found in Trash', 'ticketingelica'),
    );

    $args = array(
        'label'               => __('ticket', 'ticketingelica'),
        'description'         => __('Tickets for events', 'ticketingelica'),
        'labels'              => $labels,
        'supports'            => array('title', 'editor'),
        'public'              => true,
        'has_archive'         => true,
        'rewrite'             => array('slug' => 'ticket'),
        'menu_icon'           => 'dashicons-tag',
    );

    register_post_type('ticket', $args);
}

add_action('init', 'create_ticket_cpt');

// Function to add the meta box
function add_ticket_metabox()
{
    add_meta_box(
        'ticket_metabox', // Unique ID
        __('Ticket Details', 'ticketingelica'), // Title
        'ticket_metabox_callback', // Callback function
        'ticket', // Screen (your CPT slug)
        'normal', // Context
        'high' // Priority
    );
}

// Callback function to display the meta box content
function ticket_metabox_callback($post)
{
    wp_nonce_field(basename(__FILE__), 'ticket_nonce'); // Security nonce

    // Get existing meta values
    $subject = get_post_meta($post->ID, 'subject', true);
    $type = get_post_meta($post->ID, 'type', true);
    $priority = get_post_meta($post->ID, 'priority', true); // Get existing priority

    // Subject field (text area)
    echo '<label for="subject">Subject:</label>';
    echo '<textarea id="subject" name="subject" rows="5" cols="50">' . esc_attr($subject) . '</textarea>';
    echo '<br><br>';

    // Type selector
    echo '<label for="type">Type:</label>';
    echo '<select id="type" name="type">';
    echo '<option value="commercial"' . selected($type, 'commercial', false) . '>Commercial</option>';
    echo '<option value="technical"' . selected($type, 'technical', false) . '>Technical</option>';
    echo '<option value="presales"' . selected($type, 'presales', false) . '>Presales</option>';
    echo '<option value="gdpr_requests"' . selected($type, 'gdpr_requests', false) . '>GDPR Requests</option>';
    echo '</select>';
    echo '<br><br>';

    // Priority selector
    $priority_terms = get_terms(array('taxonomy' => 'ticket_priority')); // Get all priority terms

    echo '<label for="priority">Priority:</label>';
    echo '<select id="priority" name="priority">';
    foreach ($priority_terms as $term) {
        echo '<option value="' . $term->slug . '"' . selected($priority, $term->slug, false) . '>' . $term->name . '</option>';
    }
    echo '</select>';
    echo '<br><br>';
}


// Save meta box data
add_action('save_post', 'save_ticket_metabox');

// Function to save meta box data
function save_ticket_metabox($post_id)
{
    // Verify nonce and autosave
    if (!isset($_POST['ticket_nonce']) || !wp_verify_nonce($_POST['ticket_nonce'], basename(__FILE__))) {
        return;
    }
    if (wp_is_post_autosave($post_id)) {
        return;
    }

    // Check user capability (edit_terms for priority)
    if (!current_user_can('edit_terms')) {
        return; // Don't save priority if user cannot edit terms
    }

    // Sanitize and save subject field
    $subject = sanitize_textarea_field($_POST['subject']);
    update_post_meta($post_id, 'subject', $subject);

    // Sanitize and save type
    $type = sanitize_text_field($_POST['type']);
    update_post_meta($post_id, 'type', $type);

    // Sanitize and save priority (only if user can edit terms)
    if (current_user_can('edit_terms')) {
        $priority = sanitize_text_field($_POST['priority']);
        update_post_meta($post_id, 'priority', $priority);
    }
}

// Function to register custom taxonomies
function register_ticket_categories()
{

    $labels = array(
        'name'                       => _x('Categories', 'Taxonomy General Name', 'ticketingelica'),
        'singular_name'              => _x('Category', 'Taxonomy Singular Name', 'ticketingelica'),
        'menu_name'                  => __('Categories', 'ticketingelica'),
        'all_items'                  => __('All Categories', 'ticketingelica'),
        'edit_item'                  => __('Edit Category', 'ticketingelica'),
        'update_item'                 => __('Update Category', 'ticketingelica'),
        'add_new_item'                => __('Add New Category', 'ticketingelica'),
        'new_item_name'               => __('New Category Name', 'ticketingelica'),
        'parent_item'                 => __('Parent Category', 'ticketingelica'),
        'parent_item_colon'           => __('Parent Category:', 'ticketingelica'),
        'search_items'                => __('Search Categories', 'ticketingelica'),
        'popular_items'               => __('Popular Categories', 'ticketingelica'),
        'separate_items_with_commas'  => __('Separate categories with commas', 'ticketingelica'),
        'add_or_remove_items'         => __('Add or remove categories', 'ticketingelica'),
        'choose_from_most_used'       => __('Choose from most used categories', 'ticketingelica'),
        'not_found'                   => __('No categories found', 'ticketingelica'),
        'hierarchical'                => true, // Set to true for hierarchical categories
        'label'                      => __('Category', 'ticketingelica'), //Displayed name for singular category on taxonomies screen
        'rewrite'                     => array('slug' => 'ticket-category'), // Category slug in permalinks
    );

    $args = array(
        'labels'                     => $labels,
        'show_ui'                     => true,
        'show_in_menu'                 => true,
        'hierarchical'                => true, // Set to true for hierarchical categories
        'rewrite'                     => array('slug' => 'ticket-category'),
    );

    register_taxonomy('ticket_category', 'ticket', $args); // Register category taxonomy for 'ticket' CPT

}

// Hook to register taxonomies on init
add_action('init', 'register_ticket_categories');

// Function to register custom taxonomies (add this inside your existing function)
function register_ticket_tags()
{

    $labels = array(
        'name'                       => _x('Tags', 'Taxonomy General Name', 'ticketingelica'),
        'singular_name'              => _x('Tag', 'Taxonomy Singular Name', 'ticketingelica'),
        'menu_name'                  => __('Tags', 'ticketingelica'),
        'all_items'                  => __('All Tags', 'ticketingelica'),
        'edit_item'                  => __('Edit Tag', 'ticketingelica'),
        'update_item'                 => __('Update Tag', 'ticketingelica'),
        'add_new_item'                => __('Add New Tag', 'ticketingelica'),
        'new_item_name'               => __('New Tag Name', 'ticketingelica'),
        'search_items'                => __('Search Tags', 'ticketingelica'),
        'popular_items'               => __('Popular Tags', 'ticketingelica'),
        'separate_items_with_commas'  => __('Separate tags with commas', 'ticketingelica'),
        'add_or_remove_items'         => __('Add or remove tags', 'ticketingelica'),
        'choose_from_most_used'       => __('Choose from most used tags', 'ticketingelica'),
        'not_found'                   => __('No tags found', 'ticketingelica'),
        'hierarchical'                => false, // Set to false for tags (flat structure)
    );

    $args = array(
        'labels'                     => $labels,
        'show_ui'                     => true,
        'show_in_menu'                 => true,
        'hierarchical'                => false, // Set to false for tags (flat structure)
    );

    register_taxonomy('ticket_tag', 'ticket', $args); // Register tag taxonomy for 'ticket' CPT
}
// Hook to register taxonomies on init
add_action('init', 'register_ticket_tags');


// Function to register custom taxonomies (add this inside your existing function)
function register_ticket_priority()
{

    $priority_labels = array(
        'name'                       => _x('Priorities', 'Taxonomy General Name', 'ticketingelica'),
        'singular_name'              => _x('Priority', 'Taxonomy Singular Name', 'ticketingelica'),
        'menu_name'                  => __('Priorities', 'ticketingelica'),
        'all_items'                  => __('All Priorities', 'ticketingelica'),
        'edit_item'                  => __('Edit Priority', 'ticketingelica'),
        'update_item'                 => __('Update Priority', 'ticketingelica'),
        'add_new_item'                => __('Add New Priority', 'ticketingelica'),
        'new_item_name'               => __('New Priority Name', 'ticketingelica'),
        'search_items'                => __('Search Priorities', 'ticketingelica'),
        'popular_items'               => __('Popular Priorities', 'ticketingelica'),
        'separate_items_with_commas'  => __('Separate priorities with commas', 'ticketingelica'),
        'add_or_remove_items'         => __('Add or remove priorities', 'ticketingelica'),
        'choose_from_most_used'       => __('Choose from most used priorities', 'ticketingelica'),
        'not_found'                   => __('No priorities found', 'ticketingelica'),
    );

    $priority_args = array(
        'labels'                     => $priority_labels,
        'show_ui'                     => true,
        'show_in_menu'                 => true,
        'hierarchical'                => false, // Set to false for priorities (flat structure)
        'rewrite'                     => false, // Don't create rewrite rules (priorities won't have own URLs)
        'capabilities' => array(
            'manage_terms' => 'manage_ticket_priority', // Custom capability for priority terms
            'edit_terms'   => 'edit_ticket_priority',
            'delete_terms' => 'delete_ticket_priority',
        ),
    );

    register_taxonomy('ticket_priority', 'ticket', $priority_args); // Register priority taxonomy with custom capabilities
}
// Hook to register taxonomies on init
add_action('init', 'register_ticket_priority');

// Function to register the Reply CPT
function register_reply_cpt()
{

    $labels = array(
        'name'                => _x('Replies', 'Post Type General Name', 'ticketingelica'),
        'singular_name'       => _x('Reply', 'Post Type Singular Name', 'ticketingelica'),
        'menu_name'            => __('Replies', 'ticketingelica'),
        'name_admin_bar'        => __('Reply', 'ticketingelica'),
        'archives'            => __('Reply Archives', 'ticketingelica'),
        'parent_item_colon'   => __('Parent Reply:', 'ticketingelica'),
        'all_items'            => __('All Replies', 'ticketingelica'),
        'add_new_item'          => __('Add New Reply', 'ticketingelica'),
        'add_new'              => __('Add New Reply', 'ticketingelica'),
        'edit_item'            => __('Edit Reply', 'ticketingelica'),
        'update_item'           => __('Update Reply', 'ticketingelica'),
        'view_item'            => __('View Reply', 'ticketingelica'),
        'search_items'          => __('Search Replies', 'ticketingelica'),
        'not_found'            => __('No replies found', 'ticketingelica'),
        'not_found_in_trash'  => __('No replies found in Trash', 'ticketingelica'),
        'featured_image'        => __('Featured Image', 'ticketingelica'),
        'set_featured_image'    => __('Set featured image', 'ticketingelica'),
        'remove_featured_image' => __('Remove featured image', 'ticketingelica'),
        'use_featured_image'    => __('Use as featured image', 'ticketingelica'),
        'menu_icon'             => 'dashicons-megaphone', // Change this if desired
    );

    $args = array(
        'labels'              => $labels,
        'public'              => false, // Set to false to hide from main menu (optional)
        'show_ui'             => true,
        'show_in_menu'         => true,
        'capability_type'     => 'post',
        'has_archive'          => false, // Set to false to disable archive page (optional)
        'hierarchical'          => false, // Set to false for replies (not hierarchical)
        'supports'            => array('editor'), // Supports the Gutenberg editor
    );

    register_post_type('reply', $args);
}

// Hook to register CPT on init
add_action('init', 'register_reply_cpt');

// Function to add the meta box
function add_reply_metabox()
{
    add_meta_box(
        'reply_metabox', // Unique ID
        __('Reply Details', 'ticketingelica'), // Title
        'reply_metabox_callback', // Callback function
        'reply', // Screen (your CPT slug)
        'normal', // Context
        'high' // Priority
    );
}

// Hook to add meta box on init
add_action('admin_init', 'add_reply_metabox');
// Callback function to display the meta box content
function reply_metabox_callback($post)
{
    wp_nonce_field(basename(__FILE__), 'reply_nonce'); // Security nonce

    // Get existing meta value
    $subject = get_post_meta($post->ID, 'subject', true); // Get existing subject

    // Subject field (text input)
    echo '<label for="subject">Subject:</label>';
    echo '<input type="text" id="subject" name="subject" value="' . esc_attr($subject) . '">';
    echo '<br><br>';
}
// Function to save meta box data
function save_reply_metabox($post_id)
{
    // Verify nonce and autosave
    if (!isset($_POST['reply_nonce']) || !wp_verify_nonce($_POST['reply_nonce'], basename(__FILE__))) {
        return;
    }
    if (wp_is_post_autosave($post_id)) {
        return;
    }

    // Sanitize and save subject field
    $subject = sanitize_text_field($_POST['subject']);
    update_post_meta($post_id, 'subject', $subject);
}

// Hook to save meta box data on post save
add_action('save_post_reply', 'save_reply_metabox');
