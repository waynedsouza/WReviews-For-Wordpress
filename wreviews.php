<?php
/**
 * WReviews
 *
 * @package       WREVIEWS
 * @author        Wayne D'Souza
 * @version       1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:   WReviews
 * Plugin URI:    https://onlinemarketingconsultants.in
 * Description:   a local listing review system, Adds a review submission system with upvote and downvote functionality, and an admin section to approve reviews.
 * Version:       1.0.0
 * Author:        Wayne D'Souza
 * Author URI:    https://onlinemarketingconsultants.in
 * Text Domain:   wreviews
 * Domain Path:   /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// Include your custom code here.



// Create tables for reviews and votes
function review_system_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Reviews Table
    $table_reviews = $wpdb->prefix . 'wpost_reviews';
    $sql_reviews = "CREATE TABLE IF NOT EXISTS $table_reviews (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        post_id BIGINT(20) NOT NULL,
        user_id BIGINT(20) NOT NULL,
        review TEXT NOT NULL,
        author VARCHAR(255) NOT NULL,  -- Add this line for author
        upvotes INT(11) DEFAULT 0,
        downvotes INT(11) DEFAULT 0,
        approved TINYINT(1) DEFAULT 0,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Votes Table
    $table_votes = $wpdb->prefix . 'wreview_votes';
    $sql_votes = "CREATE TABLE IF NOT EXISTS $table_votes (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        review_id BIGINT(20) NOT NULL,
        user_id BIGINT(20) NOT NULL,
        post_id BIGINT(20) NOT NULL,
        vote_type ENUM('upvote', 'downvote') NOT NULL,
        vote_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE (review_id, user_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_reviews);
    dbDelta($sql_votes);
}
register_activation_hook(__FILE__, 'review_system_create_tables');

// Shortcode to display the review system
function review_system_shortcode($atts) {
    global $post;

    ob_start(); ?>
    <div id="review-system" data-post-id="<?php echo $post->ID;?>">
        <h5><a href="#" id="add-review-link">Add A Review</a></h5>
        <small>If you think a deserving local business has been overlooked, please share your thoughts by adding a review.</small>
        <div id="add-review-form" style="display:none;">
            <textarea id="review-text" placeholder="Write your review..."></textarea>
            <button id="submit-review">Submit</button>
        </div>
        <div id="reviews-section">
            <?php review_system_display_reviews($post->ID); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('wreviews', 'review_system_shortcode');

// Display reviews function
function review_system_display_reviews($post_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpost_reviews';
    $reviews = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE post_id = %d AND approved = 1", $post_id));

    if ($reviews) {
        foreach ($reviews as $review) {
            /*
            echo '<div class="review" data-id="' . $review->id . '">';
            echo '<p>' . esc_textarea($review->review) . '</p>';
            echo '<button class="upvote">Upvote (' . $review->upvotes . ')</button>';
            echo '<button class="downvote">Downvote (' . $review->downvotes . ')</button>';
            echo '<h6> Author: '.$review->author.'</h6>';
            echo '</div>';*/
            ?>
            <div class="review"  data-id="<?php echo esc_html($review->id); ?>">    
    <p class="review-text"><?php echo esc_html($review->review); ?></p>
    <p class="review-author"><?php echo esc_html($review->author); ?></p>
    <div class="review-vote">
        <button class="upvote"><i class="fas fa-arrow-up"></i><?php echo esc_html($review->upvotes); ?></button>
        <button class="downvote"><i class="fas fa-arrow-down"></i><?php echo esc_html($review->downvotes); ?></button>
    </div>
</div>
            <?

        }
    } else {
        echo '<p>No reviews yet.</p>';
    }
}

// Handle review submission
function handle_review_submission() {
    global $wpdb;

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'You must be logged in to submit a review']);
        wp_die();
    }

    if (isset($_POST['action']) && $_POST['action'] == 'submit_review') {
        $post_id = intval($_POST['post_id']);
        $review = sanitize_textarea_field($_POST['review']);
        $user_id = get_current_user_id();
        $user_info = get_userdata($user_id);
        $author_name = $user_info->display_name;
        if ($post_id && $review) {
            $table_name = $wpdb->prefix . 'wpost_reviews';
            $wpdb->insert($table_name, [
                'post_id' => $post_id,
            'review' => $review,
            'author' => $author_name,
            'user_id' => $user_id,
            'timestamp' => current_time('mysql')
            ]);

            wp_send_json_success(['message' => 'Review submitted successfully and is pending approval']);
        } else {
            wp_send_json_error(['message' => 'Invalid data']);
        }
    }

    wp_die();
}
add_action('wp_ajax_submit_review', 'handle_review_submission');
add_action('wp_ajax_nopriv_submit_review', 'handle_review_submission');

// Handle voting
function handle_review_voting() {
    global $wpdb;

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'You must be logged in to vote']);
        wp_die();
    }

    if (isset($_POST['action']) && $_POST['action'] == 'vote_review') {
        $review_id = intval($_POST['review_id']);
        $vote_action = sanitize_text_field($_POST['vote_action']);
        $user_id = get_current_user_id();
        $post_id = intval($_POST['post_id']);

        if ($review_id && ($vote_action == 'upvote' || $vote_action == 'downvote')) {
            $table_name = $wpdb->prefix . 'wreview_votes';
            $existing_vote = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE review_id = %d AND user_id = %d", $review_id, $user_id));

            if ($existing_vote > 0) {
                wp_send_json_error(['message' => 'You have already voted']);
            } else {
                $wpdb->insert($table_name, [
                    'review_id' => $review_id,
                    'user_id' => $user_id,
                    'post_id' => $post_id,
                    'vote_type' => $vote_action
                ]);

                // Update the vote count in the reviews table
                $table_reviews = $wpdb->prefix . 'wpost_reviews';
                $review = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_reviews WHERE id = %d", $review_id));

                if ($review) {
                    $new_count = $vote_action == 'upvote' ? $review->upvotes + 1 : $review->downvotes + 1;
                    $wpdb->update($table_reviews, [$vote_action . 's' => $new_count], ['id' => $review_id]);

                    wp_send_json_success(['new_count' => $new_count]);
                } else {
                    wp_send_json_error(['message' => 'Review not found']);
                }
            }
        } else {
            wp_send_json_error(['message' => 'Invalid data']);
        }
    }

    wp_die();
}
add_action('wp_ajax_vote_review', 'handle_review_voting');
add_action('wp_ajax_nopriv_vote_review', 'handle_review_voting');

// Admin page for managing reviews
function review_system_admin_menu() {
    add_menu_page('WReview System', 'WReviews', 'manage_options', 'review-system', 'review_system_admin_page');
}
add_action('admin_menu', 'review_system_admin_menu');

function review_system_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpost_reviews';
    $reviews = $wpdb->get_results("SELECT * FROM $table_name WHERE approved = 0");

    echo '<div class="wrap">';
    echo '<h1>Pending Reviews</h1>';

    if ($reviews) {
        echo '<table class="widefat">';
        echo '<thead><tr><th>ID</th><th>Review</th><th>Post ID</th><th>Approve</th></tr></thead>';
        echo '<tbody>';
        foreach ($reviews as $review) {
            echo '<tr>';
            echo '<td>' . $review->id . '</td>';
            echo '<td>' . esc_textarea($review->review) . '</td>';
            echo '<td>' . $review->post_id . '</td>';
            echo '<td><a href="' . admin_url('admin.php?page=review-system&approve=' . $review->id) . '">Approve</a></td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p>No reviews pending approval.</p>';
    }

    echo '</div>';
}

function review_system_approve_review() {
    global $wpdb;
    if (isset($_GET['approve'])) {
        $review_id = intval($_GET['approve']);
        $table_name = $wpdb->prefix . 'wpost_reviews';
        $wpdb->update($table_name, ['approved' => 1], ['id' => $review_id]);

        wp_redirect(admin_url('admin.php?page=review-system'));
        exit;
    }
}
add_action('admin_init', 'review_system_approve_review');

// Enqueue JS
function review_system_enqueue_scripts() {
    wp_enqueue_script('wreviews-js', plugins_url('/js/wreviews.js', __FILE__), ['jquery'], null, true);
}
add_action('wp_enqueue_scripts', 'review_system_enqueue_scripts');

// Enqueue CSS
function review_system_enqueue_styles() {
    wp_enqueue_style('wreviews-css', plugins_url('/css/wreviews.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'review_system_enqueue_styles');

function custom_enqueue_scripts() {
    wp_enqueue_script('custom-js', plugin_dir_url(__FILE__) . 'js/custom.js', array('jquery'), null, true);
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css');
    wp_localize_script('custom-js', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'custom_enqueue_scripts');


