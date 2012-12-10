<?php

/*
Plugin Name: Mailjet for Wordpress
Version: 1.2.5
Plugin URI: https://www.mailjet.com/plugin/wordpress.htm
Description: Use mailjet SMTP to send email, manage lists and contacts within wordpress
Author: Mailjet SAS
Author URI: http://www.mailjet.com/
*/

/**
 *  Copyright 2012  MAILJET  (email : PLUGINS@MAILJET.COM)
 *  Developed by Jonathan Foucher - http://jfoucher.com/

 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License, version 2, as
 *  published by the Free Software Foundation.

 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.

 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */


require('mailjet-utils.php');
require('mailjet-api.php');
require('mailjet-class.php');
require('mailjet-options.php');
require('mailjet-widget.php');
require('views/options-form.php');
require('views/list.php');
require('views/contacts.php');

define ('MJ_HOST', 'in.mailjet.com');
define ('MJ_MAILER', 'X-Mailer:WP-Mailjet/0.1');

$options = new WPMailjet_Options();

//Check plugin is set up properly
if(get_option('mailjet_password') && get_option('mailjet_username')){
    $MailjetApi = new Mailjet(get_option('mailjet_username'), get_option('mailjet_password'));
    global $phpmailer;
    if ( !is_object( $phpmailer ) || !is_a( $phpmailer, 'PHPMailer' ) ) {
        require_once ABSPATH . WPINC . '/class-phpmailer.php';
        require_once ABSPATH . WPINC . '/class-smtp.php';
        $phpmailer = new PHPMailer();
    }

    $WPMailjet = new WPMailjet($MailjetApi, $phpmailer);
    add_action( 'widgets_init', 'wp_mailjet_register_widgets' );

} elseif(get_option('mailjet_enabled') && (!get_option('mailjet_password') || !get_option('mailjet_username'))) {

    /* Display a notice that can be dismissed */
    add_action('admin_notices', 'wp_mailjet_admin_notice');


}

add_action('admin_init', 'wp_mailjet_notice_ignore');
function wp_mailjet_notice_ignore() {
    global $current_user;
    $user_id = $current_user->ID;
    /* If user clicks to ignore the notice, add that to their user meta */
    if ( isset($_GET['wp_mailjet_notice_ignore']) && '1' == $_GET['wp_mailjet_notice_ignore'] ) {
        add_user_meta($user_id, 'wp_mailjet_notice_ignore', 'true', true);
    }
}


function wp_mailjet_admin_notice() {
    global $current_user ;
    $user_id = $current_user->ID;
    /* Check that the user hasn't already clicked to ignore the message */
    if ( ! get_user_meta($user_id, 'wp_mailjet_notice_ignore') ) {
        echo '<div class="error"><p>';
        printf(__('The mailjet plugin is enabled but your credentials are not set. Please <a href="admin.php?page=wp_mailjet_options_top_menu" title="enable Mailjet plugin">do so now</a> to send your emails through <b>Mailjet</b> <a href="%1$s" style="display:block; float:right;">Hide Notice</a>', 'wp-mailjet'), 'admin.php?page=wp_mailjet_options_top_menu?wp_mailjet_notice_ignore=1');
        echo "</p></div>";
    }
}

function wp_mailjet_register_widgets() {
    register_widget( 'MailjetSubscribeWidget' );
}

/**
 * Display settings link on plugins page
 *
 * @param array $links
 * @param string $file
 * @return array
 */

function mailjet_settings_link( $links, $file ) {
    if ( $file != plugin_basename( __FILE__ ))
        return $links;

    $settings_link = '<a href="admin.php?page=wp_mailjet_options_top_menu">' . __( 'Settings', 'wp-mailjet' ) . '</a>';

    array_unshift( $links, $settings_link );

    return $links;
}
add_filter( 'plugin_action_links', 'mailjet_settings_link', 10, 2);

/**
 * Add newly registered user to selected list
 * @param $userid
 */
function on_user_registration($userid) {

    if(get_option('mailjet_password') && get_option('mailjet_username')){
        $MailjetApi = new Mailjet(get_option('mailjet_username'), get_option('mailjet_password'));
        if($list_id = get_option('mailjet_auto_subscribe_list_id')){
            $user = get_userdata( $userid );

            $params = array(
                'method' => 'POST',
                'contact' => $user->data->user_email,
                'id' => $list_id,
            );

            $response = $MailjetApi->listsAddContact($params);

        }
    }
}
add_action( 'user_register', 'on_user_registration');



load_plugin_textdomain ('wp-mailjet', FALSE, dirname (plugin_basename(__FILE__)) . '/i18n');
