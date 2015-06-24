<?php

/*
Plugin Name: Most Shared Posts Widget
Plugin URI: http://sitepoint.com
Description: Displays most shared posts in an widget
Author: Narayan Prusty
*/

function msp_is_post()
{
  if(is_single() && !is_attachment())
  {
    global $post;

    $last_update = get_post_meta($post->ID, "msp_last_update", true);

    if($last_update)
    {
      if(time() - 21600 > $last_update)
      {
        msp_update($post->ID);
      }
    }
    else
    {
      msp_update($post->ID);
    }
  }
}

add_action("wp", "msp_is_post");

function msp_update($id)
{
  $url = get_permalink($id);

  //facebook shares
  $response = wp_remote_get("https://api.facebook.com/method/links.getStats?format=json&urls=" . $url);
  $body = $response["body"]; 
  $body = json_decode($body);

  if($body[0]->share_count)
  {
    $facebook_count = $body[0]->share_count;
  }
  else
  {
    $facebook_count = 0;
  }


  //twitter shares
  $response = wp_remote_get("http://urls.api.twitter.com/1/urls/count.json?url=" . $url);
  $body = $response["body"]; 
  $body = json_decode($body);

  if($body->count)
  {
    $twitter_count = $body->count;
  }
  else
  {
    $twitter_count = 0;
  }

  $total = $facebook_count + $twitter_count;

  update_post_meta($id, "msp_share_count", $total);
  update_post_meta($id, "msp_last_update", time());
}

class Most_Shared_Post_Widget extends WP_Widget 
{
    public function __construct() 
    {
        parent::__construct("Most_Shared_Post_Widget", "Display Most Shared Posts", array("description" => __("This plugin displays ten most shared posts in an widget")));
    }
          
    public function form($instance) 
    {
        if($instance) 
        {
            $title = esc_attr($instance["title"]);
        } 
        else
        {
            $title = "";
        }
        ?>
  
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php echo "Title"; ?></label>  
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
        </p>
  
        <?php
    }
          
    public function update($new_instance, $old_instance) 
    {
        $instance = $old_instance;
        $instance['title'] = strip_tags($new_instance['title']);
        return $instance;
    }
          
    public function widget($args, $instance) 
    {
        extract($args);
        $title = apply_filters('widget_title', $instance['title']);
        echo $before_widget;
 
        if($title) 
        {
            echo $before_title . $title . $after_title;
        }

        msp_display_widget();
        
        echo $after_widget;
    }
}
 
function msp_register_most_shared_widget() 
{
  register_widget("Most_Shared_Post_Widget");
}
 
add_action("widgets_init", "msp_register_most_shared_widget");

function msp_display_widget()
{
  $posts = get_transient("msp");
  if($posts === false)
  {
    $args = array("posts_per_page" => 10, "meta_key" => "msp_share_count", "orderby" => "meta_value");
    $posts = get_posts($args);
    $json_posts = json_encode($posts);

    echo "<ul>";

    foreach($posts as $post)
    {
      echo "<li><a href='" . get_permalink($post->ID) . "'>" . $post->post_title . "</a></li>";
    }

    echo "</ul>";

    if(count($posts) >= 10)
    {
      set_transient("msp", $json_posts, 21600);
    }
  }
  else
  {
    $posts = json_decode($posts);

    echo "<ul>";

    foreach($posts as $post)
    {
      echo "<li><a href='" . get_permalink($post->ID) . "'>" . $post->post_title . "</a></li>";
    }

    echo "</ul>";
  }
}