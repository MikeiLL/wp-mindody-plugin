<?php
namespace MZ_Mindbody\Inc\Events;

use MZ_Mindbody\Inc\Core as Core;
use MZ_Mindbody\Inc\Libraries as Libraries;
use MZ_Mindbody\Inc\Schedule as Schedule;
use MZ_Mindbody\Inc\Common\Interfaces as Interfaces;

/**
 * Class that is extended for Events Display Shortcode(s)
 */
class Retrieve_Events extends Interfaces\Retrieve {

    /**
     * Holder for events array returned by MBO API
     *
     * @since    2.4.7
     * @access   public
     * @var      array $events Array of events returned from MBO API
     */
    public $events_result;

    /**
     * Attributes sent to shortcode.
     *
     * @since    2.4.7
     * @access   public
     * @var      array    $atts    Shortcode attributes filtered via shortcode_atts().
     */
    public $atts;

    /**
     * Holds the time frame for the instance.
     *
     * @since    2.4.7
     * @access   public
     * @var      array    $time_frame    StartDateTime and endDateTime for MBO API call.
     */
    public $time_frame;

    /**
     * Holds the current day, with offset, based on "offset" attribute/parameter.
     *
     * set by time_frame() and used by sort_classes_by_date_and_time()
     *
     * @since    2.4.7
     * @access   public
     * @var      string    $current_day_offset    Formatted Datetime object.
     */
    public $current_day_offset;

    /**
     * Return Time Frame for request to MBO API
     *
     * @since 2.4.7
     *
     * Default time_frame is two dates, start of current week as set in WP, and seven days from "now.
     *
     * @return array or start and end dates as required for MBO API
     */
    public function time_frame($timestamp = null){
        $timestamp = isset($timestamp) ? $timestamp : current_time( 'timestamp' );
        $start_time = new \Datetime( date_i18n('Y-m-d', $current_week['start']) );
        $end_time = new \Datetime( date_i18n('Y-m-d', $seven_days_from_now) );
        $current_day_offset = new \Datetime( date_i18n('Y-m-d') );

        // If we are going in future or past based on offset
        if ( !empty($this->atts['offset']) ) {
            // Insure that we have an absolute number, because attr may be negative
            $abs = abs($this->atts['offset']);
            $di = new \DateInterval('P'.$abs.'W');
            // If it's a negative number, invert the interval
            if ($this->atts['offset'] < 0) $di->invert = 1;
            $start_time->add($di);
            $end_time->add($di);
            $current_day_offset->add($di);
        }

        // Set current_day_offset for filtering by sort_classes_by_date_and_time().
        $this->current_day_offset = $current_day_offset;

        return array('StartDateTime'=> $start_time->format('Y-m-d'), 'EndDateTime'=> $end_time->format('Y-m-d'));
    }

    /**
     * Return data from MBO api, store it in a transient and
     * as object attribute.
     *
     * @since 2.4.7
     *
     * @param @eventsIDs array of Events IDs to return info for
     *
     *
     * @return array of MBO schedule data
     */
    public function get_mbo_results( $eventsIDs = array() ){

        $mb = $this->instantiate_mbo_API();

        if ( !$mb || $mb == 'NO_SOAP_SERVICE' ) return false;

        // All events members?
        $all = (0 == count($eventsIDs));

        // Make specific transient for specific events members
        $transient_string = ($all) ? 'events' : 'events' . implode('_', $eventsIDs );

        $transient_string = $this->generate_transient_name($transient_string);

        if ( false === get_transient( $transient_string ) ) {
            // If there's not a transient already, call the API and create one

            if ($this->mbo_account !== 0) {
                // If account has been specified in shortcode, update credentials
                $mb->sourceCredentials['SiteIDs'][0] = $this->mbo_account;
            }

            if ($all) {
                $this->events_result = $mb->GetEvents();
            } else {
                $this->events_result = $mb->GetEvents( array('EventsIDs'=> $eventsIDs ));
            }

            set_transient($transient_string, $this->events_result, 60 * 60 * 12);

        } else {
            $this->events_result = get_transient( $transient_string );
        }
        return $this->events_result;
    }

    /**
     * Sort Events array by MBO SortOrder, then by LastName
     *
     * @since 2.4.7
     *
     * @param @timestamp defaults to current time
     * @source https://stackoverflow.com/a/2875637/2223106
     *
     *
     * @return array of MBO schedule data, sorted by SortOrder, then LastName
     */
    public function sort_events_by_time(){

        // Obtain a list of columns
        foreach ($this->events_result['GetEventsResult']['EventsMembers']['Events'] as $key => $row) {
            $important[$key]  = $row['SortOrder'];
            $basic[$key] = $row['LastName'];
        }

        array_multisort($important, SORT_NUMERIC, SORT_ASC,
            $basic, SORT_REGULAR, SORT_ASC,
            $this->events_result['GetEventsResult']['EventsMembers']['Events']);

        return $this->events_result;
    }

}