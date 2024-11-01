<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

// Add the hook action to call on a post save
// add_action( $hook, $function_to_add, $priority, $accepted_args );
add_action('wp_insert_post', 'send_new_post', 10, 3);

// Listen for publishing of a new post
function send_new_post($post_id, $post, $update) {
  if (empty(zlickpay_get_options_data('zp_client_token'))) {
    write_log('<!-- zp client token has not been set -->');
    return;
  }

  if ($post->post_type !== 'post') {
    write_log('POST ID '. $post_id . ' is not a post');
    return;
  }

  if($post->post_status === 'publish') {

	$para_count = zlickpay_get_options_data('zp_previewable_paras');
	if ($para_count > 2) { $para_count = 2; }

    $post_data = array(
      'post_id' => $post_id,
      'post_url' => get_permalink($post),
      'title' => $post->post_title,
      'content' => newsletter_content($post->post_content, $para_count),
      'image_url' => get_the_post_thumbnail_url($post_id),
      'related_posts' => find_related_posts($post_id)
    );

    send_create_newsletter($post_data);
  } else {
    send_delete_newsletter($post_id);
  }

  return true;
}

// Extract the required paragraphs from the Gutenberg blocks
function extract_paragraphs($content, $para_count) {
  $extracted_paragraphs = '';

  if (has_blocks($content)) {
    $extracted_paragraphs = extract_gutenberg_paragraphs($content, $para_count);
  } else {
    $extracted_paragraphs = extract_classic_paragraphs($content, $para_count);
  }

  return $extracted_paragraphs;
}

function extract_gutenberg_paragraphs($content, $para_count) {
  // Decode the content as JSON for Gutenberg blocks
  $blocks = parse_blocks($content);

  $extracted_paragraphs = '';
  $paragraph_count = 0;

  foreach ($blocks as $block) {
    // Check if the block is a paragraph block
    if ($block['blockName'] === 'core/paragraph') {
      // Extract the paragraph content
      $paragraph_content = $block['innerHTML'];

      // Append paragraph content to the result
      $extracted_paragraphs .= $paragraph_content;
      $extracted_paragraphs .= "<br />";
      $paragraph_count++;

      // Break loop when the required paragraphs are extracted
      if ($paragraph_count >= $para_count) {
          break;
      }
    }
  }

  return $extracted_paragraphs;
}

function extract_classic_paragraphs($content, $para_count) {
  $extracted_paragraphs = '';

  // Extract paragraphs from classic editor content
  preg_match_all('/<p>(.*?)<\/p>/', $content, $matches);

  for ($para_index = 0; $para_index < $para_count; $para_index++) {
    if (isset($matches[1][$para_index])) {
      $extracted_paragraphs .= $matches[1][$para_index];
      $extracted_paragraphs .= "<br />";
    }
  }

  return $extracted_paragraphs;
}

function find_related_posts($post_id) {
  $related_posts = array();

  // Define arguments for the query to retrieve latest posts excluding the specific one
  $args = array(
      'post_type' => 'post', // Retrieve posts
      'post_status' => 'publish', // Retrieve only published posts
      'posts_per_page' => 3, // Number of posts to retrieve
      'post__not_in' => array($post_id), // Exclude the specific post
      'orderby' => 'date', // Order by date
      'order' => 'DESC' // Order in descending order (latest first)
  );

  // Query the latest posts
  $query = new WP_Query($args);

  // Check if there are posts
  if ($query->have_posts()) {
      // Output the posts
      while ($query->have_posts()) {
          $query->the_post();
          $image_url = has_post_thumbnail() ? get_the_post_thumbnail_url(null, 'post-thumbnail') : null;

          $post_data = array(
            'title' => get_the_title(),
            'url' => get_permalink(),
            'image_url' => $image_url
          );

          array_push($related_posts, $post_data);
      }
      // Restore original post data
      wp_reset_postdata();
  }

  return $related_posts;
}

function send_create_newsletter($post_data) {
  try {
    $client_token = zlickpay_get_options_data('zp_client_token');
    $client_secret = zlickpay_get_options_data('zp_client_secret');

    $headers = array('client-token' => $client_token, 'client-secret' => $client_secret);
    $newsletter_data = array('newsletter' => $post_data);
    $args = array('headers' => $headers, 'body' => $newsletter_data, 'timeout' => 10);

    $response = wp_remote_post( 'https://portal.zlickpaywall.com/api/newsletters', $args );
    if ( is_wp_error( $response ) ) {
      error_log( 'wp_remote_get failed: ' . $response->get_error_message() );
    } else {
      $body = wp_remote_retrieve_body( $response );
      write_log( 'wp_remote_retrieve_body: ' . $body );
    }

    return true;
  } catch (Exception $e) {
    error_log('Something failed: '. $e->get_error_message());
    return "Something went wrong.";
  }
}

function send_delete_newsletter($post_id) {
  try {
    $client_token = zlickpay_get_options_data('zp_client_token');
    $client_secret = zlickpay_get_options_data('zp_client_secret');

    $headers = array('client-token' => $client_token, 'client-secret' => $client_secret);
    $args = array('headers' => $headers, 'method' => 'DELETE', 'timeout' => 10 );

    $response = wp_remote_post( 'https://portal.zlickpaywall.com/api/newsletters/' . $post_id, $args );
    if ( is_wp_error( $response ) ) {
      error_log( 'wp_remote_get failed: ' . $response->get_error_message() );
    } else {
      $body = wp_remote_retrieve_body( $response );
      write_log( 'wp_remote_retrieve_body: ' . $body );
    }

    return true;
  } catch (Exception $e) {
    error_log('Something failed: '. $e->get_error_message());
    return "Something went wrong.";
  }
}

function newsletter_content($content, $para_count)
{
  // gutenberg editor gives content in paragraphs with many newlines. No <p> tags.
  // classic editor gives a mix of html and new line seperated paragraphs
  $content = zlickpay_replace_wp_file_tag($content);
  $is_block_editor = false;

  if (strpos($content, "<!-- /wp:paragraph -->") > 0) {
    $is_block_editor = true;
  }

  if (!$is_block_editor) {
    $content = wpautop($content);
    remove_filter('the_content', 'wpautop');
  }

  $needle_end = $is_block_editor ? '<!-- /wp:paragraph -->' : '</p>';
  $pre_content = '';

  $needle = "<!--zlick-paywall-->";
  $content_length = strpos($content, $needle);

  if ($content_length === false) {
    $content_length = zlickpay_find_number_paragraphs($content, $para_count, $is_block_editor);
    $pre_content = substr($content, 0, $content_length + strlen($needle_end) );
  } else {
    $pre_content = substr($content, 0, $content_length );
  }
  $post_content = substr($content, strrpos($content, $needle_end) + strlen($needle_end));
  $content = $pre_content . $post_content;

  return str_replace("<!-- /wp:paragraph -->","<br />", $content);
}
