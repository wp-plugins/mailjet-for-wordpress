<?php


class MailjetSubscribeWidget extends WP_Widget
{
    protected $api;

    function __construct()
    {

        //No dependency injection possible, so we have to use this:
        $this->api = new Mailjet(get_option('mailjet_username'), get_option('mailjet_password'));
        $apiLists = $this->api->listsAll();
        if($apiLists){
            $this->lists = $apiLists->lists;
        }else{
            $this->lists = array();
        }

        $widget_ops = array('classname' => 'MailjetSubscribeWidget', 'description' => __('Allows your visitors to subscribe to one of your lists') );
        parent::__construct( false, 'Subscribe to our newsletter', $widget_ops );
        add_action( 'wp_ajax_mailjet_subscribe_ajax_hook', array($this, 'mailjet_subscribe_from_widget') );
        add_action( 'wp_ajax_nopriv_mailjet_subscribe_ajax_hook', array($this, 'mailjet_subscribe_from_widget'));

        wp_enqueue_script( 'ajax-example', plugin_dir_url( __FILE__ ) . 'js/ajax.js', array( 'jquery' ) );
        wp_localize_script( 'ajax-example', 'WPMailjet', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'ajax-example-nonce' )
        ) );
    }

    function form($instance)
    {
        $instance = wp_parse_args( (array) $instance, array( 'title' => '', 'list_id' => '' , 'button_text' => '' ) );
        $title = $instance['title'];
        $list_id = $instance['list_id'];
        $button_text = $instance['button_text'];
        ?>
    <p>
        <label for="<?php echo $this->get_field_id('title'); ?>">
            <?php echo __('Title:') ?>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
        </label>
    </p>
    <p>
        <label for="<?php echo $this->get_field_id('button_text'); ?>">
            <?php echo __('Button text:') ?>
            <input class="widefat" id="<?php echo $this->get_field_id('button_text'); ?>" name="<?php echo $this->get_field_name('button_text'); ?>" type="text" value="<?php echo esc_attr($button_text); ?>" />
        </label>
    </p>

    <p>
        <label for="<?php echo $this->get_field_id('list_id'); ?>">
            <?php echo __('List:') ?>
            <select class="widefat" id="<?php echo $this->get_field_id('list_id'); ?>" name="<?php echo $this->get_field_name('list_id'); ?>">
                <?php foreach($this->lists as $list) { ?>
                <option value="<?php echo $list->id?>"<?php echo ($list->id == esc_attr($list_id) ? ' selected="selected"' : '') ?>><?php echo $list->label?></option>
                <?php } ?>
            </select>
        </label>
    </p>
    <?php
    }

    function update($new_instance, $old_instance)
    {
        $instance = $old_instance;
        $instance['title'] = $new_instance['title'];
        $instance['list_id'] = $new_instance['list_id'];
        $instance['button_text'] = $new_instance['button_text'];
        return $instance;
    }


    public function mailjet_subscribe_from_widget(){
        $email = $_POST['email'];
        $list_id = $_POST['list_id'];
        $params = array(
            'method' => 'POST',
            'contact' => $email,
            'id' => $_POST['list_id']
        );
        # Call
        $response = $this->api->listsAddContact($params);
        $list = $this->api->listsStatistics(array('id' => $list_id))->statistics;
        if($response){
            echo sprintf(__("<p class=\"success\">Thanks for subscribing to <b>%s</b>, %s</p>"), $list->label, $email);
        }else{
            echo sprintf(__("<p class=\"error\">Sorry %s we couldn't subscribe you to <b>%s</b> at this time</p>"), $email, $list->label);
        }
        die();
     }


    function widget($args, $instance)
    {
        extract($args, EXTR_SKIP);

        echo $before_widget;
        $title = empty($instance['title']) ? ' ' : apply_filters('widget_title', $instance['title']);
        $list_id = $instance['list_id'];
        $button_text = $instance['button_text'];
        if (!empty($title))
            echo $before_title . $title . $after_title;;

        // WIDGET CODE GOES HERE
        echo '
        <form class="subscribe-form">
            <input id="email" name="email" value="" type="email" placeholder="'.__('your@email.com',' wp-mailjet').'" />
            <input name="action" type="hidden" value="mailjet_subscribe_ajax_hook" />
            <input name="list_id" type="hidden" value="'.$list_id.'" />
            <input name="submit" type="submit" class="mailjet-subscribe" value="'.$button_text.'">
        </form>
        <div class="response">
        </div>';

        echo $after_widget;
    }

}



