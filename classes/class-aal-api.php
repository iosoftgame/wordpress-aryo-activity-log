<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class AAL_API {

	protected static function _delete_old_items() {
		global $wpdb;
		
		$logs_lifespan = absint( AAL_Settings::get_option( 'logs_lifespan' ) );
		if ( empty( $logs_lifespan ) )
			return;
		
		$wpdb->query( $wpdb->prepare(
			'DELETE FROM `%1$s`
				WHERE `hist_time` < %2$d',
			$wpdb->activity_log,
			strtotime( '-' . $logs_lifespan . ' days', current_time( 'timestamp' ) )
		) );
	}
	
	public static function erase_all_items() {
		global $wpdb;
		
		$wpdb->query( $wpdb->prepare(
			'TRUNCATE %1$s',
			$wpdb->activity_log
		) );
	}

	public static function insert( $args ) {
		global $wpdb;

		$args = wp_parse_args( $args, array(
			'action'         => '',
			'object_type'    => '',
			'object_subtype' => '',
			'object_name'    => '',
			'object_id'      => '',
			'hist_ip'        => $_SERVER['REMOTE_ADDR'],
			'hist_time'      => current_time( 'timestamp' ),
		) );

		$user = get_user_by( 'id', get_current_user_id() );
		if ( $user ) {
			$args['user_caps'] = strtolower( key( $user->caps ) );
			if ( empty( $args['user_id'] ) )
				$args['user_id']  = $user->ID;
		} else {
			$args['user_caps'] = 'guest';
			if ( empty( $args['user_id'] ) )
				$args['user_id']  = 0;
		}
		
		// TODO: Find better way to Multisite compatibility.
		if ( empty( $args['user_caps'] ) )
			$args['user_caps'] = 'administrator';
		
		// Make sure for non duplicate.
		$check_duplicate = $wpdb->get_row( $wpdb->prepare(
			'SELECT `histid` FROM %1$s
				WHERE `user_caps` = \'%2$s\'
					AND `action` = \'%3$s\'
					AND `object_type` = \'%4$s\'
					AND `object_subtype` = \'%5$s\'
					AND `object_name` = \'%6$s\'
					AND `user_id` = \'%7$s\'
					AND `hist_ip` = \'%8$s\'
					AND `hist_time` = \'%9$s\'
			;',
			$wpdb->activity_log,
			$args['user_caps'],
			$args['action'],
			$args['object_type'],
			$args['object_subtype'],
			$args['object_name'],
			$args['user_id'],
			$args['hist_ip'],
			$args['hist_time']
		) );
		
		if ( $check_duplicate )
			return;

		$wpdb->insert( $wpdb->activity_log,
			array(
				'action'         => $args['action'],
				'object_type'    => $args['object_type'],
				'object_subtype' => $args['object_subtype'],
				'object_name'    => $args['object_name'],
				'object_id'      => $args['object_id'],
				'user_id'        => $args['user_id'],
				'user_caps'      => $args['user_caps'],
				'hist_ip'        => $args['hist_ip'],
				'hist_time'      => $args['hist_time'],
			),
			array( "%s", "%s", "%s", "%s", "%d", "%d", "%s", "%s", "%d" )
		);

		//Notification	
		$email = AAL_Settings::get_option('email_notification');
		
		if(is_email($email)) {
			$item = new stdClass();
			$item = json_decode(json_encode($args), FALSE);
			
			$message = '<h1>' . __('ARYO Activity Log') . '</h1>';
			
			$message .= '<h2>' . __( 'Date', 'aryo-aal' ) . ':</h2><br /> ' . AAL_Activity_Log_List_Table::column_default($item, 'date') . '<br />';
			$message .= '<h2>' . __( 'Author', 'aryo-aal' ) . ':</h2><br /> ' . AAL_Activity_Log_List_Table::column_author($item) . '<br />';
			$message .= '<h2>' . __( 'IP', 'aryo-aal' ) . ':</h2><br /> ' . AAL_Activity_Log_List_Table::column_default($item, 'ip') . '<br />';
			$message .= '<h2>' . __( 'Type', 'aryo-aal' ) . ':</h2><br /> ' . AAL_Activity_Log_List_Table::column_type($item) . '<br />';
			$message .= '<h2>' . __( 'Label', 'aryo-aal' ) . ':</h2><br /> ' . AAL_Activity_Log_List_Table::column_label($item) . '<br />';
			$message .= '<h2>' . __( 'Action', 'aryo-aal' ) . ':</h2><br /> ' . AAL_Activity_Log_List_Table::column_default($item, 'action') . '<br />';
			$message .= '<h2>' . __( 'Description', 'aryo-aal' ) . ':</h2><br /> ' . AAL_Activity_Log_List_Table::column_description($item) . '<br />';

			wp_mail($email, __('ARYO Activity Log') . ' - ' . __('Notification'), $message);
		}

		// Remove old items.
		self::_delete_old_items();
		do_action( 'aal_insert_log', $args );
	}

}

function aal_insert_log( $args = array() ) {
	AAL_API::insert( $args );
}
