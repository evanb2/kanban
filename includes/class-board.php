<?php

/**
 * the class for rendering our kanban board
 */



// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;



// instantiate the plugin
Kanban_Board::init();



class Kanban_Board extends Kanban_Db
{
	// the instance of this object
	private static $instance;

	// the common name for this class
	static $slug = 'board';

	// the table name of this class
	protected static $table_name = 'boards';

	// define db table columns and their validation type
	protected static $table_columns = array(
		'title'           => 'text',
		'description'     => 'text',
		'created_dt_gmt'  => 'datetime',
		'modified_dt_gmt' => 'datetime',
		'user_id_author'  => 'int',
		'is_active'       => 'bool',
		'position' => 'int'
	);

	static $boards = array();



	// add actions and filters
	static function init()
	{
		// send board data to the board template
		add_filter( 'template_include', array( __CLASS__, 'send_page_data_to_template' ), 100 ); // must be higher than template

		add_action( 'kanban_board_render_js_templates', array( __CLASS__, 'render_js_templates' ), 1, 1 );
	}



	/**
	 * load data needed for board's javascript
	 * @param  string $template the passed in template path
	 * @return string           the same template path
	 */
	static function send_page_data_to_template( $template )
	{
		// make sure we're looking at the board
		if ( ! isset( Kanban_Template::get_instance()->slug ) || Kanban_Template::get_instance()->slug != self::$slug ) return $template;



		// get the template data
		global $wp_query;

		// attach our object to the template data
		$wp_query->query_vars['kanban'] = (object) array();

			// add passed alert
		$wp_query->query_vars['kanban']->alert = ! empty( $_GET['alert'] ) ? stripcslashes( $_GET['alert'] ) : '';

		$wp_query->query_vars['kanban']->text = apply_filters(
				'kanban_board_text',
				include(__DIR__ . '/inc-board-text.php')
			);


		// get the current user from the allowed users
		// $wp_query->query_vars['kanban']->current_user = Kanban_User::get_current_user();

		$wp_query->query_vars['kanban']->current_user_id = get_current_user_id();

		$wp_query->query_vars['kanban']->boards = array();



		$boards = Kanban_Board::get_all();

		foreach ($boards as $board_id => $board)
		{
			$allowed_users = Kanban_User::get_allowed_users($board_id);

			if ( !isset($allowed_users[$wp_query->query_vars['kanban']->current_user_id]) ) continue;



			$board_to_add = (object) array(
				'title' => $board->title,
				'id' => $board_id
			);

			// add default filters
			$board_to_add->filters = array(
				'user_id_assigned' => NULL,
				'project_id'       => NULL
			);

			$board_to_add->search = array(
				'$(".task-title", $task).text()',
				'$task.attr("data-id")'
			);

			// get all data for the javascript
			$board_to_add->settings = Kanban_Option::get_all($board_id);

			$board_to_add->allowed_users = Kanban_User::get_allowed_users($board_id);

			$board_to_add->estimates = Kanban_Estimate::get_all($board_id);
			$board_to_add->statuses = Kanban_Status::get_all($board_id);

			$board_to_add->projects = Kanban_Project::get_all($board_id);
			$board_to_add->tasks = Kanban_Task::get_all($board_id);

			// figure out percentages here (easier, quicker than in js)
			$board_to_add->col_percent_w = count( $board_to_add->statuses ) > 0 ? 100/(count( $board_to_add->statuses )) : 100;
			$board_to_add->status_w = count( $board_to_add->statuses ) > 0 ? 100/(count( $board_to_add->statuses )-2) : 0;

			apply_filters(
				'kanban_board_send_page_data_to_template_each_board',
				$board_to_add
			);

			$wp_query->query_vars['kanban']->boards[$board_id] = $board_to_add;
		}



		$current_board = Kanban_Board::get_current_by('GET');

		// make sure current board is available to user
		if ( !isset($wp_query->query_vars['kanban']->boards[$current_board->id]) )
		{
			$current_board = reset($wp_query->query_vars['kanban']->boards);
		}

		$wp_query->query_vars['kanban']->current_board_id = $current_board->id;

		// in case of discrepency in url
		$_GET['board_id'] = $wp_query->query_vars['kanban']->current_board_id;



		apply_filters(
			'kanban_board_query_vars',
			$wp_query->query_vars['kanban']
		);

		return $template;
	}



	static function render_js_templates($board)
	{
		// Automatically load router files
		$js_templates = glob( Kanban::$instance->settings->path . '/templates/board/t-*.php' );

		$js_templates = apply_filters(
			'kanban_board_render_js_templates_before',
			$js_templates
		);

		foreach ( $js_templates as $js_template ) : ?>
		<script type="text/html" class="template" data-board-id="<?php echo $board->id ?>" data-basename="<?php echo basename($js_template, '.php'); ?>">

		<?php include $js_template; ?>

		</script>
		<?php endforeach;
	}




	static function get_all()
	{
		if ( empty( self::$boards ) )
		{
			$sql = apply_filters(
				'kanban_board_get_all_sql',
				"SELECT * FROM `%s` WHERE `is_active` = 1 ORDER BY `position` ASC;"
			);

			global $wpdb;
			self::$boards = $wpdb->get_results(
				sprintf( 
					$sql,
					self::table_name()
				)
			);

			self::$boards = Kanban_Utils::build_array_with_id_keys( self::$boards, 'id' );

		}

		return apply_filters(
			'kanban_board_get_all_return',
			self::$boards
		);
	}



	static function get_current ($board_id = NULL)
	{
		// if one isn't passed, but is set elsewhere
		if ( is_null($board_id) && isset($_REQUEST['board_id']) )
		{
			$board_id = $_REQUEST['board_id'];
		}

		$boards = self::get_all();

		// if the one we want exists
		if ( !is_null($board_id) && isset($boards[$board_id]) )
		{
			return $boards[$board_id];
		}

		// otherwise, pass the first one
		return reset($boards);
	}



	static function get_current_by ($method = 'GET')
	{
		$board_id = NULL;

		if ( $method == 'GET' && isset($_GET['board_id']) )
		{
			$board_id = $_GET['board_id'];
		}

		if ( $method == 'POST' && isset($_POST['board_id']) )
		{
			$board_id = $_POST['board_id'];
		}

		return self::get_current($board_id);
	}



	// extend parent, so it's accessible from other classes
	static function replace( $data )
	{
		return self::_replace( $data );
	}



	// extend parent, so it's accessible from other classes
	static function delete( $where )
	{
		return self::_delete( $where );
	}



	// extend parent, so it's accessible from other classes
	static function insert_id()
	{
		return self::_insert_id();
	}



	// define the db schema
	static function db_table()
	{
		return 'CREATE TABLE ' . self::table_name() . ' (
					id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
					title text NOT NULL,
					description text NOT NULL,
					created_dt_gmt datetime NOT NULL,
					modified_dt_gmt datetime NOT NULL,
					user_id_author bigint(20) NOT NULL,
					position bigint(20) NOT NULL,
					is_active BOOLEAN NOT NULL DEFAULT TRUE,
					UNIQUE KEY id (id),
					KEY is_active (is_active)
				)';
	} // db_table



	/**
	 * get the instance of this class
	 * @return object the instance
	 */
	public static function get_instance()
	{
		if ( ! self::$instance )
		{
			self::$instance = new self();
		}
		return self::$instance;
	}



	/**
	 * construct that can't be overwritten
	 */
	private function __construct() {}



} // Kanban_Board
