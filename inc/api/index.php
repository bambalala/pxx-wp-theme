<?php
/**
* Get a limited part of the content - sans html tags and shortcodes -
* according to the amount written in $limit. Make sure words aren't cut in the middle
* @param string $content - content
* @param int $limit - number of characters
* @return string - the shortened content
*/
function shortContent($content, $limit) {
   $content = strippedContent($content); /* if the limit is more than the length, this will be returned */
   $ret = $content;
   if (mb_strlen($content) >= $limit) {
      $ret = mb_substr($content, 0, $limit);
      // make sure not to cut the words in the middle:
      // 1. first check if the substring already ends with a space
      if (mb_substr($ret, -1) !== ' ') {
         // 2. If it doesn't, find the last space before the end of the string
         $space_pos_in_substr = mb_strrpos($ret, ' ');
         // 3. then find the next space after the end of the string(using the original string)
         $space_pos_in_content = mb_strpos($content, ' ', $limit);
         // 4. now compare the distance of each space position from the limit
         if ($space_pos_in_content != false && $space_pos_in_content - $limit <= $limit - $space_pos_in_substr) {
            /* if the closest space is in the original string, take the substring from there*/
            $ret = mb_substr($content, 0, $space_pos_in_content);
         } else {
            // else take the substring from the original string, but with the earlier (space) position
            $ret = mb_substr($content, 0, $space_pos_in_substr);
         }
      }
   }
   return $ret . '...';
}
/**
* Get a cleaned from HTML tags version of the content
* @param string $content - content
* @return string - the cleaned content
*/
function strippedContent($content) {
   /* sometimes there are <p> tags that separate the words, and when the tags are removed,
   * words from adjoining paragraphs stick together.
   * so replace the end <p> tags with space, to ensure unstickinees of words */
   $content = strip_tags($content);
   $content = strip_shortcodes($content);
   $content = trim(preg_replace('/\s+/', ' ', $content));
   return $content;
}

/**
 * [custom] API Settings
 */
add_action('rest_api_init', 'idzAddVideoToPost_register' );

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
            'get_callback'    => 'idzAddVideoToPostByObject',
            'update_callback' => null,
            'schema'          => null,
        )
    );
}

function idzAddVideoToPostByObject($postObject) {
  $postId = strval($postObject['id']);
  return idzAddVideoToPost();
}

function idzAddVideoToPost($postId) {
  $result = new stdClass();
  $thumb = get_post_meta($postId, 'thumb', true);
  if ( has_post_thumbnail() ) {
      $thumb_id = get_post_thumbnail_id();
      $thumb_url = wp_get_attachment_image_src($thumb_id, 'wpst_thumb_large', true);
      $poster = $thumb_url[0];
  }else{
      $poster = $thumb;
  }
  $result->poster = $poster;
  $result->video_url = get_post_meta($postId, 'video_url', true);
  $result->embed_code = strval(get_post_meta($postId, 'embed', true));
  $result->duration = get_post_meta($postId, 'duration', true);
  $result->likes = get_post_meta($postId, 'likes_count', true);
  $result->dislikes = get_post_meta($postId, 'dislikes_count', true);
  $result->comments_number = get_comments_number($postId);
  $result->views = get_post_meta($postId, 'post_views_count', true);
  $result->actors = wp_get_post_terms($postId, 'actors');
  $result->tags = wp_get_post_tags($postId);
  return $result;
}

function idzCleanField() {
  return '[Hidden]';
};

/**
 * [custom] API methods
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'custom/v2', '/front_posts/', array(
            'methods' => 'GET',
            'callback' => 'getFrontPosts'
    ) );
});
function getFrontPosts(){
  $result = array(
    'latest'=>preparePosts(array('type'=>'latest')),
    'mostViewed'=>preparePosts(array('type'=>'most-viewed')),
    'mostRated'=>preparePosts(array('type'=>'most-rated')),
    'mostCommented'=>preparePosts(array('type'=>'most-commented', 'per_page'=>12)),
  );

  return $result;
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'custom/v2', '/posts/', array(
            'methods' => 'GET',
            'callback' => 'getPosts'
    ) );
});
function getPosts(WP_REST_Request $request){
  return preparePosts($request);
}

function preparePosts($request){
  $type = $request['type'];

  if (isset($request['per_page'])) {
    $posts_per_page = $request['per_page'];
  } else {
    $posts_per_page = 1;
  }

  if (isset($request['page'])) {
    $page = $request['page'];
  } else {
    $page = 1;
  }

  if (isset($request['slug'])) {
    $slug = $request['slug'];
  }

  if (isset($request['ids'])) {
    $ids = $request['ids'];
  } else {
    $ids = array();
  }

  $query = array(
    'posts_per_page'=>$posts_per_page,
    'paged'=>$page,
  );

  if(sizeof($ids) > 0) {
    $query['post__in'] = $ids;
  }

  switch($type) {
    case 'latest' :
      $query['orderby'] = 'date';
      $query['order'] = 'DESC';
      break;
    case 'most-viewed' :
      $query['meta_key'] = 'post_views_count';
      $query['orderby'] = 'meta_value_num';
      $query['order'] = 'DESC';
      break;
    case 'most-rated' :
      $query['meta_key'] = 'likes_count';
      $query['orderby'] = 'meta_value_num';
      $query['order'] = 'DESC';
      break;
    case 'most-commented' :
      $query['orderby'] = 'comment_count';
      $query['order'] = 'DESC';
      break;
    case 'longest' :
      $query['meta_key'] = 'duration';
      $query['orderby'] = 'meta_value_num';
      $query['order'] = 'DESC';
      break;
    case 'popular' :
      $query['orderby'] = 'meta_value_num';
      $query['order'] = 'DESC';
      $query['meta_query'] = array(
        'relation'  => 'OR',
        array(
          'key'     => 'rate',
          'compare' => 'NOT EXISTS'
        ),
        array(
          'key'     => 'rate',
          'compare' => 'EXISTS'
        )
      );
      break;
    case 'random' :
      $query['orderby'] = 'rand';
      $query['order'] = 'DESC';
      break;
    case 'by_slug' :
      $query['name'] = $slug;
      break;
    case 'taxonomy_slug' :
      $query['orderby'] = 'date';
      $query['order'] = 'DESC';
      $query['tax_query'] = array(
        array(
          'taxonomy' => $request['taxonomy_name'],
          'field'    => 'slug',
          'terms'    => $request['taxonomy_slug'],
        ),
      );
      break;
    default;
  }

  $meta_query = new WP_Query($query);

  if($meta_query->have_posts()) {
    //Define and empty array
    $data = array();
    // Store each post's title in the array
    while($meta_query->have_posts()) {
      $meta_query->the_post();
      $data[] = array(
        'id'=>get_the_ID(),
        'date'=>get_the_date(),
        'slug'=>basename(get_permalink()),
        'title'=>get_the_title(),
        'content'=>strippedContent(get_the_content()),
        'excerpt'=>get_the_excerpt(),
        'short_content'=>shortContent(get_the_content(), 140),
        //'img'=>wp_get_attachment_url(get_post_thumbnail_id($post->ID), 'thumbnail'),
        'video_meta'=>idzAddVideoToPost(get_the_ID()),
      );
    }
    // Return the data
    return array(
      'total'=>$meta_query->found_posts,
      'posts'=>$data,
    );
  } else {
    // If there is no post
    return false;
  }
}

/*
 * Config
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'custom/v2', '/config/', array(
            'methods' => 'GET',
            'callback' => 'getConfig'
    ) );
});
function getConfig(){
  return array(
    'wp_theme_url'=>get_theme_file_uri(),
    'pages'=>json_decode(file_get_contents(get_template_directory() . '/inc/api/pages.json')),
    'header'=>json_decode(file_get_contents(get_template_directory() . '/inc/api/header.json')),
    'footer'=>json_decode(file_get_contents(get_template_directory() . '/inc/api/footer.json')),
    'sidebar'=>array(
      'tags'=>json_decode(file_get_contents(get_template_directory() . '/inc/api/sidebar/tags.json')),
      'actors'=>json_decode(file_get_contents(get_template_directory() . '/inc/api/sidebar/actors.json')),
      'livecams'=>json_decode(file_get_contents(get_template_directory() . '/inc/api/sidebar/livecams.json')),
      'resources'=>json_decode(file_get_contents(get_template_directory() . '/inc/api/sidebar/resources.json')),
    )
  );
}
