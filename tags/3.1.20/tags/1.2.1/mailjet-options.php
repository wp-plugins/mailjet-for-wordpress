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
        echo __('Mailjet Settings','wp-mailjet');
        echo'</h2>';

        $form = new Mailjet_Options_Form('admin.php?page=wp_mailjet_options_top_menu&action=save_options');

        $desc = '<ol>';
        $desc .= '<li>'.__('<a href="https://www.mailjet.com/signup">Create your Mailjet account</a> or visit your <a href="https://fr.mailjet.com/account/api_keys">account page</a> to get your API keys.','wp-mailjet').'</li>';
        $desc .= '<li>'.__('<a href="https://fr.mailjet.com/contacts/lists/add">Create a new list</a> if you don\'t have one or need a new one.','wp-mailjet').'</li>';
        $desc .= '<li>'.__('<a href="widgets.php">Add</a> the email collection widget to your sidebar or footer.','wp-mailjet').'</li>';
        $desc .= '<li>'.__('<a href="https://fr.mailjet.com/campaigns/create">Create a campaign</a> on mailjet.com to send your newsletter.','wp-mailjet').'</li>';

        $desc .= '</ol>';

        $generalFieldset = new Options_Form_Fieldset(
            __('Mailjet Plugin', 'wp-mailjet'),
            array(),
            $desc
        );

        $form->addFieldset($generalFieldset);

        $generalOptions[] = new Options_Form_Option('mailjet_enabled', __('Enabled', 'wp-mailjet'), 'checkbox', get_option('mailjet_enabled'), __('Enable email through <b>Mailjet</b>', 'wp-mailjet'));
        $generalOptions[] = new Options_Form_Option('mailjet_ssl', __('SSL Enabled', 'wp-mailjet'), 'checkbox', get_option('mailjet_ssl'), __('Enable <b>SSL</b> communication with mailjet.com', 'wp-mailjet'));
        $ports = array(
            array('value' => 25, 'label' => 25),
            array('value' => 465, 'label' => 465),
            array('value' => 587, 'label' => 587),
        );
        $generalOptions[] = new Options_Form_Option('mailjet_port', '', 'select', get_option('mailjet_port'), __('Port to use for SMTP communication', 'wp-mailjet'), false, $ports);
        $generalOptions[] = new Options_Form_Option('mailjet_test', __('Send test email', 'wp-mailjet'), 'checkbox',  get_option('mailjet_test'), __('Send test email now', 'wp-mailjet'));
        $test_email = (get_option('mailjet_test_address') ? get_option('mailjet_test_address') : get_option('admin_email'));
        $generalOptions[] = new Options_Form_Option('mailjet_test_address', __('Recipient of test email', 'wp-mailjet'), 'email', $test_email);
        $from_email = (get_option('mailjet_from_email') ? get_option('mailjet_from_email') : get_option('admin_email'));

        $generalOptions[] = new Options_Form_Option('mailjet_from_email', __('<code>From:</code> email address', 'wp-mailjet'), 'email', $from_email);

        $generalFieldset = new Options_Form_Fieldset(
            __('General Settings', 'wp-mailjet'),
            $generalOptions,
            __('Enable or disable the sending of your emails through your Mailjet account', 'wp-mailjet')
        );

        $form->addFieldset($generalFieldset);


        $apiOptions[] = new Options_Form_Option('mailjet_username', __('API key', 'wp-mailjet'), 'text', get_option('mailjet_username'), null, true);
        $apiOptions[] = new Options_Form_Option('mailjet_password', __('API secret', 'wp-mailjet'), 'text', get_option('mailjet_password'), null, true);

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
        $fields['mailjet_enabled'] = (isset($_POST['mailjet_enabled']) ? 1 : 0);
        $fields['mailjet_test'] = (isset($_POST['mailjet_test']) ? 1 : 0);
        $fields['mailjet_ssl'] = (isset($_POST['mailjet_ssl']) ? 'ssl' : '');
        $fields['mailjet_test_address'] = strip_tags(filter_var($_POST ['mailjet_test_address'], FILTER_VALIDATE_EMAIL));
        $fields['mailjet_from_email'] = strip_tags(filter_var($_POST ['mailjet_from_email'], FILTER_VALIDATE_EMAIL));
        $fields['mailjet_username'] = strip_tags(filter_var($_POST ['mailjet_username'], FILTER_SANITIZE_STRING));
        $fields['mailjet_password'] = strip_tags(filter_var($_POST ['mailjet_password'], FILTER_SANITIZE_STRING));
        $fields['mailjet_port'] = strip_tags(filter_var($_POST ['mailjet_port'], FILTER_SANITIZE_NUMBER_INT));

        $errors = array();
        if ($fields['mailjet_test'] && empty ($fields['mailjet_test_address'])) {
            $errors [] = 'mailjet_test_address';
        }
        if (!empty ($fields ['mailjet_test_address'])) {
            if (!filter_var($fields ['mailjet_test_address'], FILTER_VALIDATE_EMAIL)) {
                $errors [] = 'mailjet_test_address';
            }
        }
        if (empty($fields ['mailjet_username'])) {
            $errors [] = 'mailjet_username';
        }
        if (empty($fields ['mailjet_password'])) {
            $errors [] = 'mailjet_password';
        }

        if (! count ($errors)) {
            update_option('mailjet_enabled', $fields['mailjet_enabled']);
            update_option('mailjet_token'.$_SERVER['REMOTE_ADDR'], json_encode(array('timestamp' => 0)));
            update_option('mailjet_test', $fields['mailjet_test']);
            update_option('mailjet_test_address', $fields ['mailjet_test_address']);
            update_option('mailjet_from_email', $fields ['mailjet_from_email']);
            update_option('mailjet_username', $fields ['mailjet_username']);
            update_option('mailjet_password', $fields ['mailjet_password']);
            update_option('mailjet_ssl', $fields ['mailjet_ssl']);
            update_option('mailjet_port', $fields ['mailjet_port']);



            $configs = array (
                array ('', 25),
                array ('tls', 25),
                array ('ssl', 465),
                array ('tls', 587),
                array ('', 587),
                array ('', 588),
            );

            $host = MJ_HOST;
            $connected = FALSE;
            if (get_option('mailjet_ssl')){
                $protocol = get_option('mailjet_ssl').'://';
            }else{
                $protocol = '';
            }
            $soc = @ fsockopen ($protocol.$host, get_option('mailjet_port'), $errno, $errstr, 5);
            if ($soc) {
                $connected = TRUE;
                $port = get_option('mailjet_port');
                $ssl = get_option('mailjet_ssl');
            }else {
                for ($i = 0; $i < count ($configs); ++$i) {
                    if ($configs [$i] [0]){
                        $protocol = $configs [$i] [0].'://';
                    }else{
                        $protocol = '';
                    }
                    $soc = @ fsockopen ($protocol.$host, $configs [$i] [1], $errno, $errstr, 5);
                    if ($soc) {
                        fclose ($soc);
                        $connected = $i;
                        $port = $configs [$i] [1];
                        $ssl = $configs [$i] [0];
                        break;
                    }
                }
            }

            if ($connected !== FALSE) {
                update_option ('mailjet_ssl', $ssl);

                update_option ('mailjet_port', $port);

                $test_sent = false;
                if ($fields ['mailjet_test']) {
                    $subject = __('Your test mail from Mailjet', 'wp-mailjet');
                    $message = sprintf(__('Your Mailjet configuration is ok!'."\r\n".'SSL: %s Port: %s', 'wp-mailjet'), ($ssl ? 'On' : 'Off'), $port);
                    $enabled = get_option ('mailjet_enabled');
                    update_option ('mailjet_enabled', 1);
                    $test_sent = wp_mail($fields ['mailjet_test_address'], $subject, $message);
                    update_option ('mailjet_enabled', $enabled);
                }

                $sent = '';
                if($test_sent){
                    $sent = __(' and your test message was sent.', 'wp-mailjet');
                }
                if($connected === TRUE){
                    WP_Mailjet_Utils::custom_notice('updated', __('Your settings have been saved successfully', 'wp-mailjet').$sent);
                }elseif($connected >= 0) {
                    WP_Mailjet_Utils::custom_notice('updated', __('Your settings have been saved, but your port and SSL settings were changed as follows to ensure delivery', 'wp-mailjet').$sent);
                }
            } else {
                WP_Mailjet_Utils::custom_notice('error', sprintf (__ ('Please contact Mailjet support to sort this out.<br /><br />%d - %s', 'wp-mailjet'), $errno, $errstr));
            }
        }else{
            //var_dump($errors);
            WP_Mailjet_Utils::custom_notice('error', __('There is an error with your settings. please correct and try again', 'wp-mailjet'));
        }
    }


}