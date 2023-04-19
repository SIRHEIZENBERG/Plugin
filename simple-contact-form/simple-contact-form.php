<?php

/**
 * Plugin Name: Contact Form Plugin
 *Plugin URI: https://yourdomain.com/plugins/contact-form-plugin/
 * Description: This is a custom plugin to create a contact form and display submissions in the admin panel.
 * Version: 1.0.0
 * Author: Malcolm Macharia
 * Author URI: https://example.com/
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    die('You cannot be here');
}

class SimpleContactForm
{

    public function __construct()
    {
        //create custom post type
        add_action('init', array($this, 'create_custom_post_type'));

        //load assets css/js
        add_action('wp_enqueue_scripts', array($this, 'load_assets'));

        //add short code
        add_shortcode('contact-form', array($this, 'load_shortcode'));

        //load javascript
        add_action('wp_footer', array($this, 'load_scripts'));

        //register rest api
        add_action('rest_api_init', array($this, 'register_rest_api'));

        //add meta boxes
        add_action('add_meta_boxes', array($this, 'create_meta_box'));
    }

    public function create_custom_post_type()
    {
        $args = [

            'public' => true,
            'has_archive' => true,
            'menu_position' => 30,
            'labels' => [

                'name' => 'Submissions',
                'singular_name' => 'Submission',
                'edit_item' => 'View Submission'

            ],
            'supports' => false,
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => false,
            ),
            'map_meta_cap' => true,
        ];

        register_post_type('simple_contact_form', $args);
    }

    public function create_meta_box()
    {
        add_meta_box('custom_contact_form', 'Submission', array($this, 'display_submission'), 'simple_contact_form');
    }

    public function display_submission()
    {

        $postmetas = get_post_meta(get_the_ID());

        unset($postmetas['_edit_lock']);

        echo '<ul>';

        foreach ($postmetas as $key => $value) {

            echo '<li><strong>' . ucfirst($key) . ':</strong> ' . $value[0] . '</li>';
        }

        echo '</ul>';
    }

    public function load_assets()
    {
        wp_enqueue_style(
            'simple-contact-form',
            plugin_dir_url(__FILE__) . 'css/simple-contact-form.css',
            array(),
            1,
            'all'
        );

        wp_enqueue_script(
            'simple-contact-form',
            plugin_dir_url(__FILE__) . 'js/simple-contact-form.js',
            array('jquery'),
            1,
            true
        );

        wp_enqueue_script(
            'recaptcha',
            'https://www.google.com/recaptcha/api.js',
            array(),
            null,
            true
        );
    }

    public function sanitize_form_data($data)
{
    // Sanitize the form data
    $sanitized_data = array();

    foreach ($data as $key => $value) {
        $sanitized_data[$key] = filter_var(trim($value), FILTER_SANITIZE_STRING);
    }

    return $sanitized_data;
}

    public function load_shortcode()
    { ?>

        <body>
            <form method="post" id="simple-contact-form__form" class="needs-validation" novalidate>
                <div id="success-message"></div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name">Name:</label>
                            <input type="text" name="name" id="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address:</label>
                            <input type="email" name="email" id="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number:</label>
                            <input type="tel" name="phone" id="phone" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="message">Message:</label>
                            <textarea name="message" id="message" rows="5" class="form-control" style="resize: none;" required></textarea>
                        </div>
                        <div class="cf-form-group">
                            <div class="g-recaptcha" id="recaptcha-checkbox" data-sitekey="6Le4aZolAAAAAJLu9halzwxS32QuTphLBZ5QxYMc"></div>
                            <div class="cf-form-error-message"></div>
                        </div>

                    </div>
                </div>
                <button type="submit" id="send-button" class="btn btn-primary btn-lg btn-block mt-3" name="submit">Send</button>
            </form>
        </body
    <?php }

    public function load_scripts()
    { ?>
        <script>
            var nonce = '<?php echo wp_create_nonce('wp_rest'); ?>';

            (function($) {
                $('#simple-contact-form__form').submit(function(event) {

                    event.preventDefault();

                    var form = $(this).serialize();

                    $.ajax({
                        method: 'post',
                        url: '<?php echo get_rest_url(null, 'simple-contact-form/v1/send-email'); ?>',
                        headers: {
                            'X-WP-Nonce': nonce
                        },
                        data: form
                    })

                })

            })(jQuery)
        </script>
<?php }

    public function register_rest_api()
    {

        register_rest_route(
            'simple-contact-form/v1',
            'send-email',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'handle_contact_form')
            ),
        );
    }


    function verify_recaptcha($captcha_response)
    {
        $secret_key = 'YOUR_SECRET_KEY_HERE';
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $captcha_data = array(
            'secret' => $secret_key,
            'response' => $captcha_response
        );
        $options = array(
            'http' => array(
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($captcha_data)
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $result_array = json_decode($result, true);

        return $result_array['success'];
    }

    public function handle_contact_form($data)
    {

        $headers = $data->get_headers();

        $name = sanitize_text_field($data['name']);
        $email = sanitize_email($data['email']);
        $phone = sanitize_text_field($data['phone']);
        $message = sanitize_textarea_field($data['message']);

        $headers = $data->get_headers();
        $params = $data->get_params();
        $nonce = $headers['x_wp_nonce'][0];

        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_rest_response("message not sent.", 422);
        } else {
            ("message sent");
        }

        $postarray = [
            'post_title' => $params['name'],
            'post_type' => 'simple_contact_form',
            'post_status' => 'publish'
        ];
        $post_id = wp_insert_post($postarray);

        foreach ($params as $label => $value) {
            $message = '<strong>' . ucfirst($label) . '</strong>' . $value . '<br/>';

            add_post_meta($post_id, $label, $value);
        }
    }
}

new SimpleContactForm;
