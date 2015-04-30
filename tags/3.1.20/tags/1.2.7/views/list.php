<?php

if (!class_exists('WP_List_Table'))
	require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');

class Mailjet_List_Table extends WP_List_Table
{
	protected $api;

	function __construct($api)
	{
		parent::__construct(array(
			'singular'=> 'wp_mailjet_list', //Singular label
			'plural' => 'wp_mailjet_lists', //plural label, also this well be one of the table css class
			'ajax' => false					//We won't support Ajax for this table
			));

		$this->api = $api;
	}

	function extra_tablenav( $which )
	{
		if ($which == "top")
		{
			//The code that goes before the table is here
		}

		if ( $which == "bottom")
		{
			//The code that goes after the table is there
		}
	}

	function get_columns()
	{
		return $columns = array(
			'cb' => '<input type="checkbox" />',
					'col_mailjet_list_name' => __('Name'),
					'col_mailjet_list_num_contacts' => __('Number of Contacts'),
					'col_mailjet_list_id' => __('ID'),
//					'actions' => '<a href="#">Edit</a>',
			);
	}

	public function get_sortable_columns()
	{
		return $sortable = array(
			'col_mailjet_list_id' =>			array('id', true),
			'col_mailjet_list_name' =>			array('label', false),
			'col_mailjet_list_num_contacts' =>	array('subscribers', false)
		);
	}

	function get_bulk_actions()
	{
		$actions = array('delete' => 'Delete');

		return $actions;
	}

	function process_bulk_action()
	{
		//Detect when a bulk action is being triggered.
		if ('delete' === $this->current_action())
		{
			if (isset($_POST['wp_mailjet_list']))
			{
				$list_ids = $_POST['wp_mailjet_list'];

				if (!empty($list_ids))
				{
					foreach ($list_ids as $id)
					{
						//delete list with API
						$params = array('method' => 'POST', 'id' => $id);
						$this->api->listsDelete($params);
					}
				}
			}
		}
	}

	function prepare_items()
	{
		$screen = get_current_screen();

		$orderby =		!empty($_GET["orderby"]) ? mysql_real_escape_string($_GET["orderby"]) : 'ASC';
		$order =		!empty($_GET["order"]) ? mysql_real_escape_string($_GET["order"]) : '';
		$api_order =	'id ASC';

		if (!empty($orderby) & !empty($order))
			$api_order = $orderby.' '.$order;

		$l = $this->api->listsAll();

		if (!$l || !$l->status == 'OK')
		{
			$this->items = array();
			return;
		}
		$totalitems = count($l->lists);

		//How many to display per page?
		$perpage = 20;

		//Which page is this?
		$paged = $this->get_pagenum();

		if (empty($paged) || !is_numeric($paged) || $paged <= 0)
			$paged = 1;

		$totalpages = ceil($totalitems / $perpage);

		$offset = 0;

		if (!empty($paged) && !empty($perpage))
		{
			$offset = ($paged - 1) * $perpage;

			if ($paged > $totalpages)
			{
				$offset = ($totalpages - 1) * $perpage;
				$paged = $totalpages;
			}
		}

		/* Register the pagination */
		$this->set_pagination_args(array('total_items' => $totalitems, 'total_pages' => $totalpages, 'per_page' => $perpage) );

		//The pagination links are automatically built according to those parameters

		/* Register the Columns */
		$columns =	$this->get_columns();
		$hidden =	array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array($columns, $hidden, $sortable);

		$this->process_bulk_action();

		/* Fetch the items */
		$params = array('limit' => $perpage, 'orderby' => $api_order, 'start' => $offset);

		$this->items = array_map(array($this, 'convert_to_array'),$this->api->listsAll($params)->lists);
	}

	function column_cb($item)
	{
		return sprintf('<input type="checkbox" name="%1$s[]" value="%2$s" />',
			/*$1%s*/ $this->_args['singular'],	//Let's simply repurpose the table's singular label ("movie")
			/*$2%s*/ $item['id']				//The value of the checkbox should be the record's id
			);
	}

	function column_col_mailjet_list_name($item)
	{
		//Build row actions
		$actions = array(
			'edit' => sprintf('<a href="?page=%s&action=%s&list=%s&label=%s">Edit</a>', $_REQUEST['page'], 'edit', $item['id'], $item['label']),
			'delete' => sprintf('<a href="?page=%s&action=%s&list=%s&label=%s">Delete</a>', $_REQUEST['page'], 'delete', $item['id'], $item['label']));

		//Return the title contents
		return sprintf('%1$s <span style="color:silver">(id:%2$s)</span>%3$s',
			/*$1%s*/ $item['label'],
			/*$2%s*/ $item['id'],
			/*$3%s*/ $this->row_actions($actions)
		);
	}

	function column_default($item, $column_name)
	{
		switch($column_name)
		{
			case 'col_mailjet_list_id':
				return $item['id'];
			case 'col_mailjet_list_name':
				return $item['label'];
			case 'col_mailjet_list_num_contacts':
				return $item['subscribers'];
			default:
				return print_r($item, true); //Show the whole array for troubleshooting purposes
		}
	}

	public function convert_to_array($el)
	{
		return (array) $el;
	}
}