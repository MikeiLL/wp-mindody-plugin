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
    public function sort_events_by_sort_order(){

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
