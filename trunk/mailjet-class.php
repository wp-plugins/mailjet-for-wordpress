<?php

class WPMailjet {

    protected $api;
    protected $phpmailer;


    public function __construct($api, $phpMailer)
    {
        // Set Plugin Path
        $this->pluginPath = dirname(__FILE__);

        // Set Plugin URL
        $this->pluginUrl = WP_PLUGIN_URL . '/wp-mailjet';

        $this->api = $api;
        $this->phpmailer = $phpMailer;

        add_action('phpmailer_init',array($this, 'phpmailer_init_smtp'));

        add_action('admin_menu', array($this, 'display_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

    }

    public function enqueue_scripts(){
        wp_register_script('mailjet_js', plugins_url('/js/mailjet.js', __FILE__), array('jquery'));
        wp_enqueue_script( 'mailjet_js');
    }

    public function display_menu()
    {
        if (function_exists('add_submenu_page')) {

            add_submenu_page( 'wp_mailjet_options_top_menu', 'Manage your Mailjet lists', 'Lists', 'manage_options', 'wp_mailjet_options_contacts_menu', array($this, 'show_contacts_menu') );
            add_submenu_page( 'wp_mailjet_options_top_menu', 'Manage your Mailjet campaigns', 'Campaigns', 'manage_options', 'wp_mailjet_options_campaigns_menu', array($this, 'show_campaigns_menu') );
            add_submenu_page( 'wp_mailjet_options_top_menu', 'View your Mailjet statistics', 'Statistics', 'manage_options', 'wp_mailjet_options_stats_menu', array($this, 'show_stats_menu') );
        }

    }


    function phpmailer_init_smtp (PHPMailer $phpmailer)
    {
        if (! get_option ('mailjet_enabled') || 0 == get_option('mailjet_enabled')) return;

        $phpmailer->Mailer = 'smtp';
        $phpmailer->SMTPSecure = get_option ('mailjet_ssl');

        $phpmailer->Host = MJ_HOST;
        $phpmailer->Port = get_option ('mailjet_port');

        $phpmailer->SMTPAuth = TRUE;
        $phpmailer->Username = get_option('mailjet_username');
        $phpmailer->Password = get_option('mailjet_password');


        $from_email = (get_option('mailjet_from_email') ? get_option('mailjet_from_email') : get_option('admin_email'));
        $phpmailer->From = $from_email;
        $phpmailer->Sender = $from_email;
        $phpmailer->AddCustomHeader(MJ_MAILER);

    }

    private function _get_auth_token()
    {
        if($op = get_option('mailjet_token'.$_SERVER['REMOTE_ADDR'])){
            $op = json_decode($op);

            if($op->timestamp > time()-3600)
                return $op->token;
        }

        if(!defined('WPLANG')){
            $locale = 'en';
        }else {
            $locale = substr(WPLANG, 0, 2);
            if (!in_array($locale, array('en', 'fr', 'es', 'de'))){
                $locale = 'en';
            }
        }

        $body = array(
            'allowed_access[0]' => 'stats',
            'allowed_access[1]' => 'contacts',
            'allowed_access[2]' => 'campaigns',
            'lang' => $locale,
            'default_page'=> 'campaigns',
            'type' => 'page',
            'apikey' => get_option('mailjet_username'),
        );

        $params = array(
            'headers' => array( 'Authorization' => 'Basic '.base64_encode(get_option('mailjet_username').':'.get_option('mailjet_password'))),
            'body' => $body,
        );

        $res = wp_remote_post('http://api.mailjet.com/0.1/apiKeyauthenticate?output=json', $params);

        if(is_array($res)) {
            $resp = json_decode($res['body']);
            if ($resp->status == 'OK') {
                update_option('mailjet_token'.$_SERVER['REMOTE_ADDR'], json_encode(array('token' => $resp->token, 'timestamp' => time())));
                return $resp->token;
            }
        }
    }

    public function show_campaigns_menu()
    {
        $token = $this->_get_auth_token();

        echo '<div class="wrap"><div class="icon32"><img src="'.plugin_dir_url( __FILE__ ).'/images/mj_logo_med.png'.'" /></div><h2>';
        echo __('Campaigns');
        echo'</h2></div>';
        echo '<iframe width="980px" height="1200" src="https://www.mailjet.com/campaigns?t='.$token.'"></iframe>';
    }

    public function show_stats_menu()
    {
        $token = $this->_get_auth_token();

        echo '<div class="wrap"><div class="icon32"><img src="'.plugin_dir_url( __FILE__ ).'/images/mj_logo_med.png'.'" /></div><h2>';
        echo __('Statistics');
        echo'</h2></div>';
        echo '<iframe width="980px" height="1200" src="https://www.mailjet.com/stats?t='.$token.'"></iframe>';

    }



    public function show_contacts_menu()
    {

        $action = (isset($_REQUEST['action']) ? $_REQUEST['action'] : null);

        switch($action){
            case 'edit':
                $this->show_list_contacts($_REQUEST['list']);
                break;
            case 'delete':
                if(isset($_REQUEST['list'])){
                    $this->delete_list($_REQUEST['list']);
                }
                $this->show_all_lists();
                break;
            case 'delete_contact':
                $this->delete_contact($_REQUEST['contact'], $_REQUEST['list']);
                $this->show_list_contacts($_REQUEST['list']);
                break;
            case 'add_contact':
                $this->show_add_contacts($_REQUEST['label'], $_REQUEST['list']);
                break;
            case 'add_list':
                $this->show_add_list();
                break;
            case 'save_contacts':
                $this->save_contacts();
                $this->show_list_contacts($_REQUEST['list']);
                break;
            case 'delete_contacts':
                $this->show_list_contacts($_REQUEST['list']);
                break;
            case 'save_list':
                $this->save_list();
                break;
            case 'mass_action':
                $this->mass_action();
                break;
            default:
                $this->show_all_lists();
        }

    }


    protected function save_contacts()
    {

        if (isset($_POST) && isset($_POST['contact_email'])){
            $contacts = join(',', $_POST['contact_email']);

            $params = array(
                'method' => 'POST',
                'contacts' => $contacts,
                'id' => $_POST['list_id']
            );

            $response = $this->api->listsAddManyContacts($params);

        }
    }

    protected function save_list()
    {
        if (isset($_POST) && isset($_POST['name']) && isset($_POST['title'])){
            if(!preg_match('/^[a-z0-9]+$/i', $_POST['name'])){
                WP_Mailjet_Utils::custom_notice('error', __('Only alphanumeric characters may be used for the list name'));
                $this->show_add_list($_POST['name'], $_POST['title']);
            }else{
                $params = array(
                    'method' => 'POST',
                    'label' => $_POST['title'],
                    'name' => $_POST['name']
                );
                $response = $this->api->listsCreate($params);

                $this->show_all_lists();
            }

        }else{
            $this->show_add_list();
        }
    }


    protected function show_add_contacts($label, $list_id)
    {

        wp_register_script('mailjet_js', plugins_url('/js/mailjet.js', __FILE__), array('jquery'));
        wp_enqueue_script( 'mailjet_js');

        echo '<div class="wrap"><div class="icon32"><img src="'.plugin_dir_url( __FILE__ ).'/images/mj_logo_med.png'.'" /></div><h2>';
        echo __('Add contact to list '.$label);
        echo'</h2></div>';
        echo '<form method="post" action="admin.php?page=wp_mailjet_options_contacts_menu&action=save_contacts&list='.$list_id.'&label='.$label.'">
        <div class="contactAdd" id="firstContactAdded">
            <label class="hide-if-no-js" style="" id="title-prompt-text">Contact email
                <input type="email" name="contact_email[]" size="30" tabindex="1" value="" autocomplete="off">
            </label>
        </div>
        <a id="addContact" href="#">'.__('More').'</a>';
        submit_button('Save contacts');
        echo'<input type="hidden" name="list_id" value="'.$list_id.'" /></form>';

    }

    protected function show_add_list($name='', $title='')
    {

        echo '<div class="wrap"><div class="icon32"><img src="'.plugin_dir_url( __FILE__ ).'/images/mj_logo_med.png'.'" /></div><h2>';
        echo __('Create new list');
        echo'</h2></div>';
        echo '<form method="post" action="admin.php?page=wp_mailjet_options_contacts_menu&action=save_list">';

        echo '
        <table class="form-table">
        <tbody>
            <tr valign="top">
                <th scope="row" class="listAdd">
                    <label for="list-title">List title</label>
                </th>
                <td>
                    <input type="text" name="title" id="list-title" size="30" tabindex="1" value="'.$title.'" autocomplete="off" required="required">

                </td>
            </tr>
            <tr>
                <th scope="row" class="listAdd">
                    <label style="" for="list-name" id="name-prompt-text">List name (List name used as name@lists.mailjet.com)</label>

                </th>
                <td>
                    <input type="text" name="name" size="30" tabindex="1" value="'.$name.'" id="list-name" autocomplete="off" required="required">

                </td>
            </tr>
        </tbody>
        </table>';
        submit_button('Create list');
        echo'</form>';

    }

    protected function delete_contact($id, $list_id)
    {

        $params = array(
            'id' => $list_id,
            'contact' => $id,
            'method' => 'POST',
        );
        $response = $this->api->listsRemovecontact($params);
        if($response && $response->status) {
            add_action( 'admin_notices', array('WP_Mailjet_Utils', 'custom_notice'), 10, 2 );
            do_action('admin_notices', 'updated', __('Your contact was successfully deleted.'));
        }else{
            add_action( 'admin_notices', array($this, 'custom_notice'), 10, 2 );
            do_action('admin_notices', 'error', __('Your contact could not be deleted.'));
        }
    }

    protected function delete_list($id)
    {
        $params = array(
            'id' => $id,
            'method' => 'POST',
        );
        $response = $this->api->listsDelete($params);
        if($response && $response->status) {
            add_action( 'admin_notices', array('WP_Mailjet_Utils', 'custom_notice'), 10, 2 );
            do_action('admin_notices', 'updated', sprintf(__('Your list <b>%s</b> was successfully deleted.'), $_REQUEST['label']));
        }else{
            add_action( 'admin_notices', array('WP_Mailjet_Utils', 'custom_notice'), 10, 2 );
            do_action('admin_notices', 'error', sprintf(__('Your list <b>%s</b> could not be deleted.'), $_REQUEST['label']));
        }
    }

    protected function show_all_lists()
    {
        wp_register_script('mailjet_js', plugins_url('/js/mailjet.js', __FILE__), array('jquery'));
        wp_enqueue_script( 'mailjet_js');
        echo '<div class="wrap"><div class="icon32"><img src="'.plugin_dir_url( __FILE__ ).'/images/mj_logo_med.png'.'" /></div><h2>';
        echo __('Mailjet Lists');
        echo' <a href="admin.php?page=wp_mailjet_options_contacts_menu&action=add_list" class="add-new-h2">'.__('Add new').'</a>';
        echo'</h2>
        <form method="post" action="admin.php?page=wp_mailjet_options_contacts_menu&action=delete">
        ';

        $wp_list_table = new Mailjet_List_Table($this->api);

        $wp_list_table->prepare_items();

        $wp_list_table->display();
        echo '</form></div>';
    }

    protected function show_all_contacts()
    {

        echo '<div class="wrap"><div class="icon32"><img src="'.plugin_dir_url( __FILE__ ).'/images/mj_logo_med.png'.'" /></div><h2>';
        echo __('Mailjet Contacts');
        echo'</h2>
        ';

        $wp_list_table = new Mailjet_All_Contacts_Table($this->api);

        $wp_list_table->prepare_items();

        $wp_list_table->display();
        echo '</div>';
    }

    protected function show_list_contacts($list_id)
    {
        wp_register_script('mailjet_js', plugins_url('/js/mailjet.js', __FILE__), array('jquery'));
        wp_enqueue_script( 'mailjet_js');
        $label  = (isset($_REQUEST['label']) ? $_REQUEST['label'] : 'list '.$list_id);
        echo '<div class="wrap"><div class="icon32"><img src="'.plugin_dir_url( __FILE__ ).'/images/mj_logo_med.png'.'" /></div><h2>';
        echo __('Edit contacts for '.$label);
        echo' <a href="admin.php?page=wp_mailjet_options_contacts_menu&action=add_contact&list='.$list_id.'&label='.$label.'" class="add-new-h2">'.__('Add new').'</a>
        </h2>
        <form method="post" action="admin.php?page=wp_mailjet_options_contacts_menu&list='.$list_id.'&label='.$label.'">';

        $wp_list_table = new Mailjet_Contacts_Table($this->api, $list_id);

        $wp_list_table->prepare_items();

        $wp_list_table->display();
        echo '</form></div>';
    }






}
