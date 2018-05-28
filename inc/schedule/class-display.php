<?php
namespace MZ_Mindbody\Inc\Schedule;

use MZ_Mindbody;
use MZ_Mindbody\Inc\Core as Core;
use MZ_Mindbody\Inc\Common as Common;
use MZ_Mindbody\Inc\Common\Interfaces as Interfaces;

class Display extends Interfaces\ShortCode_Script_Loader
{

    /**
     * If shortcode script has been enqueued.
     *
     * @since    2.4.7
     * @access   public
     *
     * @used in handleShortcode, addScript
     * @var      boolean $addedAlready True if shorcdoe scripts have been enqueued.
     */
    static $addedAlready = false;

    // Table styling option holders
    /**
     *
     *
     * @since    2.4.7
     * @access   public
     *
     * @var      string
     */
    public $table_class;
    /**
     *
     *
     * @since    2.4.7
     * @access   public
     *
     * @var      string
     */
    public $horizontal_class;

    /**
     *
     *
     * @since    2.4.7
     * @access   public
     *
     * @var      string
     */
    public $grid_class;

    /**
     * Text for Mode Select Button when not yet toggled
     *
     * @since    2.4.7
     * @access   public
     *
     * @var      string $initial_button_text
     */
    public $initial_button_text;

    /**
     * Text for Mode Select Button when toggled
     *
     * @since    2.4.7
     * @access   public
     *
     * @var      string $swap_button_text
     */
    public $swap_button_text;

    /**
     *
     *
     * @since    2.4.7
     * @access   public
     *
     * @var      string
     */
    public $data_target; // Which modal target to use for modal pop-up,

    /**
     *
     *
     * @since    2.4.7
     * @access   public
     *
     * @var      string
     */
    public $class_modal_link; // which modal include display file to select


    /**
     * Instance of Retrieve_Schedule.
     *
     * @since    2.4.7
     * @access   public
     * @populated in handleShortcode
     * @used in handleShortcode, localizeScript, display_schedule
     * @var      object $schedule_object Instance of Retrieve_Schedule.
     */
    public $schedule_object;

    /**
     * Site ID
     *
     * Used in Display Schedule sent to studioID in show schedule button in teacher modal.
     * Might be same as account.
     *
     * @since    2.4.7
     * @access   public
     * @var      int $siteID
     */
    public $siteID;

    /**
     * Shortcode attributes.
     *
     * @since    2.4.7
     * @access   public
     *
     * @used in handleShortcode, localizeScript, display_schedule
     * @var      array $atts Shortcode attributes function called with.
     */
    public $atts;

    /**
     * Data to send to template
     *
     * @since    2.4.7
     * @access   public
     *
     * @used in handleShortcode, display_schedule
     * @var      @array    $data    array to send template.
     */
    public $template_data;

    /**
     * Which type of schedule to display
     *
     * Will be 'horizontal' (default), 'grid' or 'both'.
     *
     * @since    2.4.7
     * @access   public
     *
     * @used in handleShortcode, display_schedule
     * @var      @astring    $display_type    Which type of schedule to display.
     */
    public $display_type;


    public function handleShortcode($atts, $content = null)
    {

        $this->atts = shortcode_atts(array(
            'type' => 'week',
            'location' => '', // stop using this eventually, in preference "int, int" format
            'locations' => array(1),
            'account' => 0,
            'filter' => 0,
            'hide_cancelled' => 0,
            'grid' => 0,
            'advanced' => 0,
            'this_week' => 0,
            'hide' => array(),
            'class_types' => '', // migrating to session_types
            'session_types' => '',
            'show_registrants' => 0,
            'calendar_format' => 'horizontal',
            'class_type' => 'Enrollment',
            'show_registrants' => 0,
            'hide_cancelled' => 1,
            'registrants_count' => 0,
            'classesByDateThenTime' => array(),
            'mode_select' => 0,
            'unlink' => 0,
            'offset' => 0
        ), $atts);

        // Set siteID to option if not set explicitly in shortcode
        $this->siteID = (isset($atts['account'])) ? $atts['account'] : Core\Init::$basic_options['mz_mindbody_siteID'];

        $show_registrants = ($this->atts['show_registrants'] == 1) ? true : false;
        // Are we displaying registrants?
        $this->data_target = $show_registrants ? 'registrantModal' : 'mzModal';
        $this->class_modal_link = MZ_Mindbody\PLUGIN_NAME_URL . 'inc/frontend/views/modals/modal_descriptions.php';

        // Turn Session/Class Types into an Array and call it session_types
        $this->atts['session_types'] = $this->atts['class_types'] = explode(',', trim($this->atts['class_types'], ' '));

        ob_start();

        $template_loader = new Core\Template_Loader();
        $this->schedule_object = new Retrieve_Schedule($this->atts);

        // Call the API and if fails, return error message.
        if (false == $this->schedule_object->get_mbo_results()) return "<div>" . __("Mindbody plugin settings error.", 'mz-mindbody-api') . "</div>";

        /*
         * Configure the display type based on shortcode atts.
         */
        $this->display_type = (!empty($atts['grid'])) ? 'grid' : 'horizontal';

        // If mode_select is on, render both grid and horizontal
        if (!empty($atts['mode_select'])) $this->display_type = 'both';

        // Define styling variables based on shortcode attribute values
        $this->table_class = ($this->atts['filter'] == 1) ? 'mz-schedule-filter' : 'mz-schedule-table';

        if ($this->atts['mode_select'] == 1):
            $this->grid_class = ' mz_hidden';
            $this->horizontal_class = '';
            $this->initial_button_text = __('Grid View', 'mz-mindbody-api');
            $this->swap_button_text = __('Horizontal View', 'mz-mindbody-api');
        elseif ($this->atts['mode_select'] == 2):
            $this->horizontal_class = ' mz_hidden';
            $this->grid_class = '';
            $this->initial_button_text = __('Horizontal View', 'mz-mindbody-api');
            $this->swap_button_text = __('Grid View', 'mz-mindbody-api');
        else:
            $this->horizontal_class = $this->grid_class = '';
            $this->initial_button_text = 0;
            $this->swap_button_text = 0;
        endif;

        // Add Style with script adder
        self::addScript();

        /*
         *
         * Determine which type(s) of schedule(s) need to be configured
         *
         * The schedules are not class objects because they change depending on
         * (date) offset value when they are called.
         */

        // Initialize the variables, so won't be un-set:
        $horizontal_schedule = '';
        $grid_schedule = '';

        if ($this->display_type == 'grid' || $this->display_type == 'both'):
            $grid_schedule = $this->schedule_object->sort_classes_by_time_then_date();
        endif;
        if ($this->display_type == 'horizontal' || $this->display_type == 'both'):
            $horizontal_schedule = $this->schedule_object->sort_classes_by_date_then_time();
        endif;

        $week_names = array(
            __('Sunday', 'mz-mindbody-api'),
            __('Monday', 'mz-mindbody-api'),
            __('Tuesday', 'mz-mindbody-api'),
            __('Wednesday', 'mz-mindbody-api'),
            __('Thursday', 'mz-mindbody-api'),
            __('Friday', 'mz-mindbody-api'),
            __('Saturday', 'mz-mindbody-api'),
        );

        /*
         * If wordpress-configured week starts on Monday instead of Sunday,
         * we shift our week names array
         */
        if ( Core\Init::$start_of_week != 0 ) {
            array_push($week_names, array_shift($week_names));
        }

        $this->template_data = array(
            'atts' => $this->atts,
            'data_target' => $this->data_target,
            'grid_class' => $this->grid_class,
            'horizontal_class' => $this->horizontal_class,
            'initial_button_text' => $this->initial_button_text,
            'swap_button_text' => $this->swap_button_text,
            'time_format' => $this->schedule_object->time_format,
            'date_format' => $this->schedule_object->date_format,
            'data_nonce' => wp_create_nonce('mz_schedule_display_nonce'),
            'data_target' => $this->data_target,
            'class_modal_link' => $this->class_modal_link,
            'siteID' => $this->siteID,
            'week_names' => $week_names,
            'start_date' => $this->schedule_object->start_date,
            'display_type' => $this->display_type,
            'table_class' => $this->table_class,
            'horizontal_schedule' => $horizontal_schedule,
            'grid_schedule' => $grid_schedule
        );

        $template_loader->set_template_data($this->template_data);
        $template_loader->get_template_part('schedule_container');

        return ob_get_clean();
    }

    public function addScript()
    {
        if (!self::$addedAlready) {
            self::$addedAlready = true;

            wp_register_style('mz_mindbody_style', MZ_Mindbody\PLUGIN_NAME_URL . 'dist/styles/main.css');
            wp_enqueue_style('mz_mindbody_style');

            wp_register_script('mz_mbo_bootstrap_script', MZ_Mindbody\PLUGIN_NAME_URL . 'dist/scripts/main.js', array('jquery'), 1.0, true);
            wp_enqueue_script('mz_mbo_bootstrap_script');

            wp_register_script('mz_display_schedule_script', MZ_Mindbody\PLUGIN_NAME_URL . 'dist/scripts/schedule-display.js', array('jquery', 'mz_mbo_bootstrap_script'), 1.0, true);
            wp_enqueue_script('mz_display_schedule_script');

            if ($this->atts['filter'] == 1):
                wp_register_script('filterTable', MZ_Mindbody\PLUGIN_NAME_URL . 'dist/scripts/mz_filtertable.js', array('jquery'), null, true);
                wp_enqueue_script('filterTable');
                $this->localizeFilterScript();
            endif;

            $this->localizeScript();

        }
    }

    public function localizeScript()
    {
        $protocol = isset($_SERVER["HTTPS"]) ? 'https://' : 'http://';
        $nonce = wp_create_nonce('mz_schedule_display_nonce');
        $params = array(
            'ajaxurl' => admin_url('admin-ajax.php', $protocol),
            'nonce' => $nonce,
            'atts' => $this->atts,
            'staff_preposition' => __('with', 'mz-mindbody-api'),
            'initial' => $this->initial_button_text,
            'mode_select' => $this->atts['mode_select'],
            'swap' => $this->swap_button_text,
            'registrants_header' => __('Registrants', 'mz-mindbody-api'),
            'get_registrants_error' => __('Error retreiving class details.', 'mz-mindbody-api'),
            'error' => __('Sorry but there was an error retrieving the schedule.', 'mz-mindbody-api'),
            'sub_by_text' => __('(Substitute)', 'mz-mindbody-api'),
            'no_bio' => __('No biography listed for this staff member.', 'mz-mindbody-api')
        );
        wp_localize_script('mz_display_schedule_script', 'mz_mindbody_schedule', $params);

    }

    public function localizeFilterScript()
    {
        $protocol = isset($_SERVER["HTTPS"]) ? 'https://' : 'http://';
        $nonce = wp_create_nonce('mz_schedule_display_nonce');
        $params = array(
            'filter_default' => __('by teacher, class type', 'mz-mindbody-api'),
            'quick_1' => __('morning', 'mz-mindbody-api'),
            'quick_2' => __('afternoon', 'mz-mindbody-api'),
            'quick_3' => __('evening', 'mz-mindbody-api'),
            'label' => __('Filter', 'mz-mindbody-api'),
            'selector' => __('All Locations', 'mz-mindbody-api'),
            'Locations_dict' => $this->schedule_object->locations_dictionary
        );
        wp_localize_script('filterTable', 'mz_filter_script', $params);

    }

    /**
     * Ajax function to return mbo schedule
     *
     * @since 2.4.7
     *
     *
     *
     * @return @json json_encode() version of HTML from template
     */
    public function display_schedule()
    {

        check_ajax_referer($_REQUEST['nonce'], "mz_schedule_display_nonce", false);

        $atts = $_REQUEST['atts'];

        $result['type'] = "success";

        $template_loader = new Core\Template_Loader();

        $this->schedule_object = new Retrieve_Schedule($atts);

        // Call the API and if fails, return error message.
        if (false == $this->schedule_object->get_mbo_results()) return "<div>" . __("Mindbody plugin settings error.", 'mz-mindbody-api') . "</div>";

        // Register attributes
        $this->handleShortcode($atts);

        // Update the data array
        $this->template_data['time_format'] = $this->schedule_object->time_format;
        $this->template_data['date_format'] = $this->schedule_object->date_format;

        $template_loader->set_template_data($this->template_data);

        // Initialize the variables, so won't be un-set:
        $horizontal_schedule = '';
        $grid_schedule = '';

        if ($this->display_type == 'grid' || $this->display_type == 'both'):
            ob_start();
            $grid_schedule = $this->schedule_object->sort_classes_by_time_then_date();
            // Update the data array
            $template_loader->get_template_part('grid_schedule');
            $this->template_data['grid_schedule'] = $grid_schedule;
            $result['grid'] = ob_get_clean();
        endif;

        if ($this->display_type == 'horizontal' || $this->display_type == 'both'):
            ob_start();
            $horizontal_schedule = $this->schedule_object->sort_classes_by_date_then_time();
            // Update the data array
            $this->template_data['horizontal_schedule'] = $horizontal_schedule;
            $template_loader->get_template_part('horizontal_schedule');
            $result['horizontal'] = ob_get_clean();
        endif;

        $result['message'] = __('Error. Please try again.', 'mz-mindbody-api');

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            $result = json_encode($result);
            echo $result;
        } else {
            header("Location: " . $_SERVER["HTTP_REFERER"]);
        }

        die();

    }

}

?>
