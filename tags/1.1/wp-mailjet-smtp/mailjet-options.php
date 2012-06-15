<?php

class WPMailjet_Options {


    public function __construct()
    {
        // Set Plugin Path
        $this->pluginPath = dirname(__FILE__);

        // Set Plugin URL
        $this->pluginUrl = WP_PLUGIN_URL . '/wp-mailjet';

        add_action('admin_menu', array($this, 'display_menu'));

    }

    public function display_menu()
    {
        add_menu_page(
            'Manage your mailjet lists and settings',
            'Mailjet',
            'manage_options',
            'wp_mailjet_options_top_menu',
            array($this, 'show_settings_menu'),
            plugin_dir_url( __FILE__ ).'/images/mj_logo_small.png',
            101
        );
        if (function_exists('add_submenu_page')) {

            add_submenu_page( 'wp_mailjet_options_top_menu', 'Change your mailjet settings', 'Settings', 'manage_options', 'wp_mailjet_options_top_menu', array($this, 'show_settings_menu') );

        }

    }


    public function show_settings_menu()
    {

        if(!empty($_POST)){
            $this->save_settings();
        }

        echo '<div class="wrap"><div class="icon32"><img src="'.plugin_dir_url( __FILE__ ).'/images/mj_logo_med.png'.'" /></div><h2>';
        echo __('Mailjet Settings');
        echo'</h2>';

        $form = new Mailjet_Options_Form('admin.php?page=wp_mailjet_options_top_menu&action=save_options');

        $desc = '<ol>';
        $desc .= '<li>'.__('<a href="https://www.mailjet.com/signup">Create your Mailjet account</a> or visit your <a href="https://fr.mailjet.com/account/api_keys">account page</a> to get your API keys.').'</li>';
        $desc .= '<li>'.__('<a href="https://fr.mailjet.com/contacts/lists/add">Create a new list</a> if you don\'t have one or need a new one.').'</li>';
        $desc .= '<li>'.__('<a href="widgets.php">Add</a> the email collection widget to your sidebar or footer.').'</li>';
        $desc .= '<li>'.__('<a href="https://fr.mailjet.com/campaigns/create">Create a campaign</a> on mailjet.com to send your newsletter.').'</li>';

        $desc .= '</ol>';

        $generalFieldset = new Options_Form_Fieldset(
            __('Mailjet Plugin', 'wp-mailjet'),
            array(),
            $desc
        );

        $form->addFieldset($generalFieldset);

        $generalOptions[] = new Options_Form_Option('mj_enabled', __('Enabled', 'wp-mailjet'), 'checkbox', 1, __('Enable email through <b>Mailjet</b>', 'wp-mailjet'));
        $generalOptions[] = new Options_Form_Option('mj_test', __('Send test email', 'wp-mailjet'), 'checkbox', 1, __('Send test email now', 'wp-mailjet'));
        $test_email = (get_option('mj_test_address') ? get_option('mj_test_address') : get_option('admin_email'));
        $generalOptions[] = new Options_Form_Option('mj_test_address', __('Recipient of test email', 'wp-mailjet'), 'email', $test_email);
        $from_email = (get_option('mj_from_email') ? get_option('mj_from_email') : get_option('admin_email'));

        $generalOptions[] = new Options_Form_Option('mj_from_email', __('<code>From:</code> email address', 'wp-mailjet'), 'email', $from_email);

        $generalFieldset = new Options_Form_Fieldset(
            __('General Settings', 'wp-mailjet'),
            $generalOptions,
            __('Enable or disable the sending of your emails through your Mailjet account', 'wp-mailjet')
        );

        $form->addFieldset($generalFieldset);


        $apiOptions[] = new Options_Form_Option('mj_username', __('API key', 'wp-mailjet'), 'text', get_option('mj_username'), null, true);
        $apiOptions[] = new Options_Form_Option('mj_password', __('API secret', 'wp-mailjet'), 'text', get_option('mj_password'), null, true);

        $apiFieldset = new Options_Form_Fieldset(
            __('API Settings', 'wp-mailjet'),
            $apiOptions,
            sprintf(__('You can get your API keys from <a href="https://www.mailjet.com/account/api_keys">your mailjet account</a>. Please also make sure the sender address %s is active in <a href="https://www.mailjet.com/account/sender">your account</a>', 'wp-mailjet'), get_option('admin_email'))
        );

        $form->addFieldset($apiFieldset);

        $form->display();

        echo '</div>';
    }


    public function save_settings()
    {
//        echo '<pre>'.print_r($_POST, true).'</pre>';
        $fields['mj_enabled'] = (isset($_POST['mj_enabled']) ? 1 : 0);
        $fields['mj_test'] = (isset($_POST['mj_test']) ? 1 : 0);
        $fields['mj_test_address'] = strip_tags(filter_var($_POST ['mj_test_address'], FILTER_VALIDATE_EMAIL));
        $fields['mj_from_email'] = strip_tags(filter_var($_POST ['mj_from_email'], FILTER_VALIDATE_EMAIL));
        $fields['mj_username'] = strip_tags(filter_var($_POST ['mj_username'], FILTER_SANITIZE_STRING));
        $fields['mj_password'] = strip_tags(filter_var($_POST ['mj_password'], FILTER_SANITIZE_STRING));

        $errors = array();
        if ($fields['mj_test'] && empty ($fields['mj_test_address'])) {
            $errors [] = 'mj_test_address';
        }
        if (!empty ($fields ['mj_test_address'])) {
            if (!filter_var($fields ['mj_test_address'], FILTER_VALIDATE_EMAIL)) {
                $errors [] = 'mj_test_address';
            }
        }
        if (empty($fields ['mj_username'])) {
            $errors [] = 'mj_username';
        }
        if (empty($fields ['mj_password'])) {
            $errors [] = 'mj_password';
        }

        if (! count ($errors)) {
            update_option('mj_enabled', $fields['mj_enabled']);
            update_option('mj_test', $fields['mj_test']);
            update_option('mj_test_address', $fields ['mj_test_address']);
            update_option('mj_from_email', $fields ['mj_from_email']);
            update_option('mj_username', $fields ['mj_username']);
            update_option('mj_password', $fields ['mj_password']);

            $configs = array (array ('ssl://', 465),
                array ('tls://', 587),
                array ('', 587),
                array ('', 588),
                array ('tls://', 25),
                array ('', 25));

            $host = MJ_HOST;
            $connected = FALSE;

            for ($i = 0; $i < count ($configs); ++$i) {
                $soc = @ fsockopen ($configs [$i] [0].$host, $configs [$i] [1], $errno, $errstr, 5);
                if ($soc) {
                    fclose ($soc);
                    $connected = TRUE;
                    break;
                }
            }

            if ($connected) {
                if ('ssl://' == $configs [$i] [0]){
                    update_option ('mj_ssl', 'ssl');
                } elseif ('tls://' == $configs [$i] [0]) {
                    update_option ('mj_ssl', 'tls');
                } else {
                    update_option ('mj_ssl', '');
                }

                update_option ('mj_port', $configs [$i] [1]);
                $test_sent = false;
                if ($fields ['mj_test']) {
                    $subject = __('Your test mail from Mailjet', 'wp-mailjet');
                    $message = __('Your Mailjet configuration is ok!', 'wp-mailjet');
                    $enabled = get_option ('mj_enabled');
                    update_option ('mj_enabled', 1);
                    $test_sent = wp_mail($fields ['mj_test_address'], $subject, $message);
                    update_option ('mj_enabled', $enabled);
                }

                $sent = '';
                if($test_sent){
                    $sent = __(' and your test message was sent.', 'wp-mailjet');
                }

                WP_Mailjet_Utils::custom_notice('updated', __('Your settings have been saved successfully', 'wp-mailjet').$sent);
            } else {
                WP_Mailjet_Utils::custom_notice('error', sprintf (__ ('Please contact Mailjet support to sort this out.<br /><br />%d - %s', 'wp-mailjet'), $errno, $errstr));
            }
        }else{
            WP_Mailjet_Utils::custom_notice('error', __('There is an error with your settings. please correct and try again', 'wp-mailjet'));
        }
    }


}