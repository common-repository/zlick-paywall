<?php

/**
 * Class Zlick_Payments_Db_Handler
 */
class Zlick_Payments_Db_Handler {
	protected $_table_articles = 'zlick_articles';
	protected $_table_subscription = 'zlick_subscription';

	/**
	 * Zlick_Payments_Db_Handler constructor.
	 */
	function __construct() {
	}

	/**
	 * Creates Table.
	 */
	function createDbTables() {
		global $wpdb;
		global $charset_collate;
		$table_name = $wpdb->prefix . $this->_table_articles;
		$sql = "CREATE TABLE `{$table_name}` (
		  `id` int(11) AUTO_INCREMENT,
		  `user_id` VARCHAR(256) NOT NULL,
		  `article_id` VARCHAR(256) NOT NULL,
		  `is_paid` tinyint(4) NOT NULL DEFAULT '0',
		  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		  `updated_at` datetime DEFAULT NULL,
		  PRIMARY KEY(`id`),
	      UNIQUE KEY(user_id,article_id)
		)$charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		dbDelta( $sql );

		$table_name = $wpdb->prefix . $this->_table_subscription;
		$sql = "CREATE TABLE `{$table_name}` (
		  `id` int(11) AUTO_INCREMENT,
		  `user_id` VARCHAR(256) NOT NULL,
		  `subscription_id` VARCHAR(256) NOT NULL,
		  `valid_from` datetime DEFAULT NULL,
		  `valid_to` datetime DEFAULT NULL,
		  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		  `updated_at` datetime DEFAULT NULL,
		  PRIMARY KEY(`id`),
		  UNIQUE KEY(user_id,subscription_id)
		)$charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Drops table.
	 */
	function dropTable() {
		global $wpdb;
		$table_name = $wpdb->prefix . $this->_table_articles;
		$wpdb->query("DROP TABLE IF EXISTS `$table_name`;");

		$table_name = $wpdb->prefix . $this->_table_subscription;
		$wpdb->query("DROP TABLE IF EXISTS `$table_name`;");
	}

	/**
	 * @param $user_id
	 * @param $article_id
	 *
	 * @return false|int|null
	 */
	function register_article($user_id, $article_id) {
		if(!empty($user_id) && !empty($article_id)) {
			global $wpdb;
			$table_name = $wpdb->prefix . $this->_table_articles;
			$response = $wpdb->insert($table_name, array(
				'user_id' => $user_id,
				'article_id' => $article_id,
				'is_paid' => 1
			),array('%s','%s'));
			
			return $response;
		}

		return null;
    }

    /**
     * @param $user_id
     * @param $subscription_id
     *
     * @return false|int|null
     */
    function register_subscription($user_id, $subscription_id, $valid_from, $valid_to) {
        if(!empty($user_id) && !empty($subscription_id)) {
            global $wpdb;
            $table_name = $wpdb->prefix . $this->_table_subscription;
	        $date_from = date("Y-m-d H:i:s",strtotime(trim($valid_from)));
	        $date_to = date("Y-m-d H:i:s",strtotime(trim($valid_to)));
	        $data = array(
		        'user_id' => trim($user_id),
		        'subscription_id' => trim($subscription_id),
		        'valid_from' => $date_from,
		        'valid_to' => $date_to
	        );
            $response = $wpdb->replace($table_name, $data ,array('%s','%s','%s','%s'));

            return $response;
        }

        return null;
    }

	/**
	 * @param $user_id
	 * @param $article_id
	 *
	 * @return array|object|void|null
	 */
	function get_article($user_id, $article_id) {
		if ( ! empty( $user_id ) && ! empty( $article_id ) ) {
			global $wpdb;
			$table_name = $wpdb->prefix . $this->_table_articles;
			$row = $wpdb->get_row(
				$wpdb->prepare("SELECT * FROM {$table_name} WHERE user_id=%s and article_id=%s", $user_id, $article_id)
			);

			return $row;
		}

		return null;
	}

	/**
	 * @param $user_id
	 * @param $article_id
	 *
	 * @return bool
	 */
	function is_article_paid($user_id, $article_id) {
		if ( ! empty( $user_id ) && ! empty( $article_id ) ) {
			global $wpdb;
			$table_name = $wpdb->prefix . $this->_table_articles;
			$row = $wpdb->get_row(
				$wpdb->prepare("SELECT * FROM {$table_name} WHERE user_id=%s and article_id=%s and is_paid=%d", $user_id, $article_id, 1)
			);

			if(!empty($row)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param $user_id
	 * @param $article_id
	 *
	 * @return bool
	 */
	function is_valid_subcription($user_id, $subription_id) {
		if ( !empty( $user_id ) && !empty( $subription_id ) ) {
			global $wpdb;
			$table_name = $wpdb->prefix . $this->_table_subscription;
			$row = $wpdb->get_row(
				$wpdb->prepare("SELECT * FROM {$table_name} WHERE user_id=%s and subscription_id=%s", $user_id, $subription_id)
			);

			if(!empty($row) && (strtotime($row->valid_to) > time()) ) {
				return true;
			}
		}

		return false;
	}

}
