<?php

/**
 * PostgreSQL 15 support
 *
 */

include_once('./classes/database/Postgres.php');

class Postgres15 extends Postgres {

	var $major_version = 15;

	/**
	 * Constructor
	 * @param $conn The database connection
	 */
	function __construct($conn) {
		parent::__construct($conn);
	}

	// Help functions

	function getHelpPages() {
		include_once('./help/PostgresDoc15.php');
		return $this->help_page;
	}


	// Capabilities

}
?>
