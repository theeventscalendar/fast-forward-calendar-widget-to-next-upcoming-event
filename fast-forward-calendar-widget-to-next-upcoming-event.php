<?php
/**
 * Plugin Name: The Events Calendar — Fast Forward Calendar Widget to Next Upcoming Event
 * Description: In instances of the mini calendar widget, empty months will be skipped.
 * Version: 1.0.0
 * Author: Modern Tribe, Inc.
 * Author URI: http://m.tri.be/1x
 * License: GPLv2 or later
 */

defined( 'WPINC' ) or die;

class Tribe_Advance_Minical {

	protected $target_date = false;

	/**
	 * If an optional target date is provided that will be used to set the month.
	 *
	 * @param bool $target_date
	 * @return void
	 */
	public function __construct( $target_date = false ) {
		
		if ( is_admin() )
			return;
		
		$this->target_date = $target_date;
		
		add_action( 'wp_loaded', array( $this, 'set_target_date' ) );
		add_filter( 'widget_display_callback', array( $this, 'advance_minical' ), 20, 2 );
	}

	/**
	 * Filter out spurious date formats.
	 *
	 * @return void
	 */
	public function set_target_date() {
	
		if ( ! is_string( $this->target_date ) || 1 !== preg_match( '#^\d{4}-\d{2}(-\d{2})?$# ', $this->target_date ) ) {
			$this->target_date = $this->next_upcoming_date();
		}
	}
	
	/**
	 * Perform the "fast-forwarding" of months.
	 *
	 * @return object
	 */
	public function advance_minical( $instance, $widget ) {
		
		if ( 'tribe-mini-calendar' !== $widget->id_base || isset( $instance['eventDate'] ) )
			return $instance;
		
		if ( date( 'Y-m' ) === $this->target_date )
			return;
		
		add_action( 'tribe_before_get_template_part', array( $this, 'modify_list_query' ), 5 );
		
		$instance['eventDate'] = $this->target_date;
		
		return $instance;
	}
	
	/**
	 * Perform the "fast-forwarding" of months.
	 *
	 * @return void
	 */
	public function modify_list_query( $template ) {

		if ( false === strpos( $template, 'mini-calendar/list.php' ) )
			return;
		
		add_action( 'parse_query', array( $this, 'amend_list_query' ) );
	}
	
	/**
	 * Modify the mini calendar widget's query.
	 *
	 * @return void
	 */
	public function amend_list_query( $query ) {
	
		// Run this once only.
		remove_action( 'parse_query', array( $this, 'amend_list_query' ) );

		$the_query = $query->query_vars;

		$the_query['start_date'] = $this->target_date . '-01';
		$last_day                = Tribe__Date_Utils::get_last_day_of_month( strtotime( $the_query['start_date'] ) );
		$the_query['end_date']   = substr_replace( $the_query['start_date'], $last_day, -2 );
		$the_query['end_date']   = tribe_end_of_day( $the_query['end_date'] );

		$query->query_vars = $the_query;
	}
	
	/**
	 * Find date of the next upcoming event.
	 *
	 * @return string
	 */
	protected function next_upcoming_date() {
		
		$next_event = tribe_get_events( array(
			'eventDisplay'   => 'list',
			'posts_per_page' => 1,
			'start_date'     => date( 'Y-m-d' )
		));

		$start_date = date( 'Y-m' );
		
		// Prevent calendar from rewinding to the start of a currently ongoing event.
		if ( ! empty( $next_event ) || isset( $next_event[0] ) ) {
			$next_event_date = tribe_get_start_date( $next_event[0]->ID, false, 'Y-m' );
			$start_date      = ( $next_event_date > $start_date ) ? $next_event_date : $start_date;
		}
		
		return $start_date;
	}
}

new Tribe_Advance_Minical();
