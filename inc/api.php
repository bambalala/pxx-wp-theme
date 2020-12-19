<?php
/**
 * [custom] API Settings
 */
function idzAddVideoToPost($postObject) {
  $postId = strval($postObject['id']);
  $result = new stdClass();

  //Thumbnail
  $thumb = get_post_meta($post->ID, 'thumb', true);
  if ( has_post_thumbnail() ) {
      $thumb_id = get_post_thumbnail_id();
      $thumb_url = wp_get_attachment_image_src($thumb_id, 'wpst_thumb_large', true);
      $poster = $thumb_url[0];
  }else{
      $poster = $thumb;
  }
  $result->poster = $poster;

  // Version
  $result->_version = '1.0.0';
  //Video URL
  $result->video_url = get_post_meta($postId, 'video_url', true);
  //Embed code
  $result->embed_code = strval(get_post_meta($postId, 'embed', true));
  //Duration
  $result->duration = get_post_meta($postId, 'duration', true);
  //Likes
  $result->likes = get_post_meta($postId, 'likes_count', true);
  //Dislikes
  $result->dislikes = get_post_meta($postId, 'dislikes_count', true);
  //Views
  $result->views = get_post_meta($postId, 'post_views_count', true);
  //Actors
  $result->actors = wp_get_post_terms($postId, 'actors');

  return $result;
}

function idzCleanField() {
  return '[Hidden]';
};

function idzAddVideoToPost_register(){
    register_rest_field( array('post'),
        'yoast_head',
        array(
            'get_callback'    => 'idzCleanField',
            'update_callback' => null,
            'schema'          => null,
        )
    );
    register_rest_field( array('post'),
        'video_meta',
        array(
            'get_callback'    => 'idzAddVideoToPost',
            'update_callback' => null,
            'schema'          => null,
        )
    );
}

add_action('rest_api_init', 'idzAddVideoToPost_register' );
