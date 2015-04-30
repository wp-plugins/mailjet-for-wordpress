<?php

if (!class_exists('WP_List_Table'))
	require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');

class Mailjet_Contacts_Table extends WP_List_Table
{
	protected $api;
	protected $list_id;

	function __construct($api, $list_id)
	{
		parent::__construct(array(
			'singular'=>	'wp_mailjet_contact', //Singular label
			'plural' =>		'wp_mailjet_contacts', //plural label, also this well be one of the table css class
			'ajax' =>		false //We won't support Ajax for this table
		));

		$this->api = $api;
		$this->list_id = $list_id;
	}

	function get_columns()
	{
		return $columns= array(
			'cb' =>								'<input type="checkbox" />',
			'col_mailjet_contact_email' =>		__('Email'),
			'col_mailjet_contact_id' =>			__('ID'),
			'col_mailjet_contact_created_at' => __('Created on'),
			'col_mailjet_contact_last_activity' => __('Last Activity'),
			'col_mailjet_contact_sent' =>		__('Messages sent'),
			'col_mailjet_contact_active' =>		__('Active'),
//'actions' => '<a href="#">Edit</a>',
		);
	}

	public function get_sortable_columns()
	{
		return $sortable = array(
			'col_mailjet_contact_email' =>			array('email', false),
			'col_mailjet_contact_created_at' =>		array('created_at', false),
			'col_mailjet_contact_last_activity' =>	array('last_activity', false),
			'col_mailjet_contact_sent' =>			array('sent', false),
			'col_mailjet_contact_active' =>			array('active', false),
			'col_mailjet_contact_id' =>				array('id', true),
		);
	}

	function column_cb($item)
	{
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			/*$1%s*/ $this->_args['singular'],  //Let's simply repurpose the table's singular label ("movie")
			/*$2%s*/ $item['id']                //The value of the checkbox should be the record's id
		);
	}

	function column_col_mailjet_contact_email($item)
	{
		//Build row actions
		$actions = array(
//'edit' => sprintf('<a href="?page=%s&action=%s&contact=%s">Edit</a>',$_REQUEST['page'],'edit_contact',$item['id']),
			'delete'    => sprintf('<a href="?page=%s&action=%s&contact=%s&list=%s">Delete</a>', $_REQUEST['page'], 'delete_contact', $item['id'], $this->list_id),
		);

		//Return the title contents
		return sprintf('%1$s <span style="color:silver">(id:%2$s)</span>%3$s',
			/*$1%s*/ $item['email'],
			/*$2%s*/ $item['id'],
			/*$3%s*/ $this->row_actions($actions)
		);
	}

	function column_default($item, $column_name)
	{
		switch($column_name)
		{
			case 'col_mailjet_contact_id':
				return $item['id'];

			case 'col_mailjet_contact_email':
				return $item['email'];

			case 'col_mailjet_contact_created_at':
				return date_i18n(get_option('date_format'), $item['created_at']);

			case 'col_mailjet_contact_last_activity':
				return ($item['last_activity'] ? date_i18n(get_option('date_format'), $item['last_activity']) : '-');

			case 'col_mailjet_contact_sent':
				return $item['sent'];

			case 'col_mailjet_contact_active':
				return ($item['active'] ? __('Yes') : __('No'));

			default:
				return print_r($item, true); //Show the whole array for troubleshooting purposes
		}
	}

	function get_bulk_actions()
	{
		$actions = array('delete_contacts' => 'Remove from list');

		return $actions;
	}

	function process_bulk_action()
	{
		//Detect when a bulk action is being triggered.
		if ('delete_contacts' === $this->current_action())
		{
			$contact_ids = $_POST['wp_mailjet_contact'];

			if (!empty($contact_ids))
			{
				foreach ($contact_ids as $id)
				{
					$params = array(
						'method' => 'POST',
						'contact' => $id,
						'id' => $_REQUEST['list'],
					);

					$this->api->listsRemovecontact($params);
				}
			}
		}
	}

	function prepare_items()
	{
		$orderby =	!empty($_GET["orderby"]) ? mysql_real_escape_string($_GET["orderby"]) : 'ASC';
		$order =	!empty($_GET["order"]) ? mysql_real_escape_string($_GET["order"]) : '';
		$api_order ='id ASC';

		if (!empty($orderby) & !empty($order))
			$api_order = $orderby . ' ' . $order;

		$totalitems = count($this->api->listsContacts(array('id' => $this->list_id))->result);

		//How many to display per page?
		$perpage = 20;

		//Which page is this?
		$paged = $this->get_pagenum();

		if (empty($paged) || !is_numeric($paged) || $paged <= 0 )
			$paged = 1;

		$totalpages = ceil($totalitems / $perpage);

		$offset = 0;

		if (!empty($paged) && !empty($perpage))
		{
			$offset=($paged - 1) * $perpage;

			if($paged > $totalpages)
			{
				$offset = ($totalpages - 1) * $perpage;
				$paged = $totalpages;
			}
		}

		/* -- Register the pagination -- */
		$this->set_pagination_args(array(
			'total_items' =>	$totalitems,
			'total_pages' =>	$totalpages,
			'per_page' =>		$perpage,
		) );
		//The pagination links are automatically built according to those parameters

		/* -- Register the Columns -- */
		$columns =	$this->get_columns();
		$hidden =	array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array($columns, $hidden, $sortable);

		$this->process_bulk_action();

		/* -- Fetch the items -- */
		$params = array(
			'id' =>		$this->list_id,
			'limit' =>	$perpage,
			'orderby' =>$api_order,
			'start' =>	$offset
		);

		$contacts = $this->api->listsContacts($params)->result;
		$this->items = array_map(array($this, 'convert_to_array'), $contacts);
	}

	public function convert_to_array($el)
	{
		return (array) $el;
	}
}