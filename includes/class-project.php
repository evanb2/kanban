<?php



// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;



Kanban_Project::init();



class Kanban_Project extends Kanban_Db
{
	// the instance of this object
	// private static $instance;

	// the common name for this class
	static $slug = 'project';

	// the table name of this class
	protected static $table_name = 'projects';

	// define db table columns and their validation type
	protected static $table_columns = array(
		'title'           => 'text',
		'description'     => 'text',
		'user_id_author'  => 'int',
		'created_dt_gmt'  => 'datetime',
		'modified_dt_gmt' => 'datetime',
		'is_active'       => 'bool',
		'board_id'  	  => 'int'
	);

	protected static $records = array();
	protected static $records_by_board = array();



	static function init()
	{
		add_action( sprintf( 'wp_ajax_save_%s', self::$slug ), array( __CLASS__, 'ajax_save' ) );
		add_action( sprintf( 'wp_ajax_delete_%s', self::$slug ), array( __CLASS__, 'ajax_delete' ) );
	}



	static function ajax_save()
	{
		if ( ! isset( $_POST[Kanban_Utils::get_nonce()] ) || ! wp_verify_nonce( $_POST[Kanban_Utils::get_nonce()], 'kanban-save' ) || ! is_user_logged_in() ) wp_send_json_error();



		if ( !Kanban_User::current_user_has_cap ('write') )
		{
			wp_send_json_error();
		}



		if ( ! isset( $_POST['project']['id'] ) )
		{
			$_POST['project']['id'] = '';
			$_POST['project']['created_dt_gmt'] = Kanban_Utils::mysql_now_gmt();
		}



		do_action( 'kanban_project_ajax_save_before', $_POST['project']['id'] );



		$_POST['project']['modified_dt_gmt'] = Kanban_Utils::mysql_now_gmt();
		$_POST['project']['user_id_author'] = get_current_user_id();



		$is_successful = self::_replace( $_POST['project'] );



		$project_id = ! empty( $_POST['project']['id'] ) ? $_POST['project']['id'] : self::_insert_id();



		$post_data = self::_get_row( 'id', $project_id );

		if ( ! $post_data ) wp_send_json_error();



		do_action( 'kanban_project_ajax_save_after', $post_data );



		if ( $is_successful )
		{
			wp_send_json_success( array(
				'message' => sprintf( '%s saved', self::$slug ),
				self::$slug => $post_data
			) );
		}
		else
		{
			wp_send_json_error( array(
				'message' => sprintf( 'Error saving %s', self::$slug )
			) );
		}
	}



	static function ajax_delete()
	{
		if ( ! isset( $_POST[Kanban_Utils::get_nonce()] ) || ! wp_verify_nonce( $_POST[Kanban_Utils::get_nonce()], 'kanban-save' ) || ! is_user_logged_in() ) wp_send_json_error();



		if ( !Kanban_User::current_user_has_cap ('write') )
		{
			wp_send_json_error();
		}



		do_action( 'kanban_project_ajax_delete_before', $_POST['project']['id'] );



		$is_successful = self::delete( $_POST['project']['id'] );



		do_action( 'kanban_project_ajax_delete_after', $_POST['project']['id'] );



		if ( $is_successful )
		{
			wp_send_json_success( array(
				'message' => sprintf( '%s deleted', self::$slug )
			) );
		}
		else
		{
			wp_send_json_error( array(
				'message' => sprintf( 'Error deleting %s', self::$slug )
			) );
		}
	}



	// extend parent, so it's accessible from other classes
	static function replace( $data )
	{
		return self::_replace( $data );
	}



	protected static function delete( $id )
	{
		return self::_update(
			array( 'is_active' => 0 ),
			array( 'id' => $id )
		);
	}



	static function get_all($board_id = NULL)
	{
		if ( empty( self::$records ) )
		{
			$sql = "SELECT `projects`.*,
					(
						SELECT COUNT(`id`)
						FROM `%s` tasks
						WHERE `tasks`.`project_id` = `projects`.`id`
						AND `tasks`.`is_active` = 1
					)
					AS 'task_count'
					FROM `%s` projects
					WHERE `projects`.`is_active` = 1
			;";

			$sql = apply_filters( 'kanban_project_get_all_sql', $sql );

			global $wpdb;
			self::$records = $wpdb->get_results(
				sprintf( 
					$sql,
					Kanban_Task::table_name(),
					self::table_name()
				)
			);



			self::$records = Kanban_Utils::build_array_with_id_keys( self::$records, 'id' );

			foreach ( self::$records as $key => $record )
			{
				self::$records[$key]->title = Kanban_Utils::str_for_frontend( self::$records[$key]->title );
			}


			$boards = Kanban_Board::get_all();
			self::$records_by_board = array_fill_keys(array_keys($boards), array());

			foreach ( self::$records as $key => $record )
			{
				if ( !isset(self::$records_by_board[$record->board_id]) ) continue;
				self::$records_by_board[$record->board_id][$key] = $record;
			}

		}

		if ( is_null($board_id) )
		{
			return apply_filters(
				'kanban_project_get_all_return',
				self::$records
			);
		}

		return apply_filters(
			'kanban_project_get_all_return',
			isset(self::$records_by_board[$board_id]) ? self::$records_by_board[$board_id] : array()
		);
	}



	// define the db schema
	static function db_table()
	{
		return 'CREATE TABLE ' . self::table_name() . ' (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					title varchar(256) NOT NULL,
					description text NOT NULL,
					user_id_author bigint(20) NOT NULL,
					created_dt_gmt datetime NOT NULL,
					modified_dt_gmt datetime NOT NULL,
					is_active BOOLEAN NOT NULL DEFAULT TRUE,
					board_id bigint(20) NOT NULL DEFAULT 1,
					UNIQUE KEY  (id),
					KEY is_active (is_active)
				)';
	} // db_table




	/**
	 * get the instance of this class
	 * @return object the instance
	 */
	// public static function get_instance()
	// {
	// 	if ( ! self::$instance )
	// 	{
	// 		self::$instance = new self();
	// 	}
	// 	return self::$instance;
	// }



	/**
	 * construct that can't be overwritten
	 */
	private function __construct() { }

}
