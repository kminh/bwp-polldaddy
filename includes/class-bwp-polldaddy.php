<?php

/**
 * Copyright (c) 2015 Khang Minh <betterwp.net>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Template function to display polldaddy polls
 *
 * @since 1.1.0
 */
function bwp_polldaddy($args = array())
{
	global $bwp_polldaddy;

	echo $bwp_polldaddy->render_polls($args);
}

function bwp_get_poll($id = '')
{
	global $bwp_polldaddy;

	$polls = $bwp_polldaddy->get_cached_polls();

	if (!empty($id) && isset($polls) && is_array($polls))
	{
		foreach ($polls as $poll)
		{
			if ($id == $poll->id)
				return $poll;
		}
	}

	return false;
}

function bwp_get_polls($limit = 1, $orderby = 'id', $order = 'desc')
{
	global $bwp_polldaddy;

	$polls = $bwp_polldaddy->get_cached_polls();

	if (!isset($polls) || !is_array($polls)) return array();

	if ('rand' == $orderby)
	{
		// random order
		shuffle($polls);
		return !empty($limit) ? array_slice($polls, 0, $limit) : $polls;
	}

	$orderby      = empty($orderby)
		|| !in_array($orderby, array('created', 'responses')) ? 'id' : $orderby;

	$sorted_polls = array();

	foreach ($polls as $poll)
		$sorted_polls[$poll->{$orderby}] = $poll;

	if ('asc' == $order)
		ksort($sorted_polls);
	else
		krsort($sorted_polls);

	return !empty($limit) ? array_slice($sorted_polls, 0, $limit) : $sorted_polls;
}

class BWP_Polldaddy extends BWP_Framework_V3
{
	var $api_host   = 'https://api.polldaddy.com/';
	var $cache_time = 43200;
	var $is_ssl     = false;

	/**
	 * Polldaddy API key used to access all Polldaddy contents
	 *
	 * Without this the plugin will be put into a semi-deactivated mode and no
	 * actions are carried out.
	 *
	 * @var string
	 * @since 1.1.0
	 */
	private $_api_key = '';

	/**
	 * Usercode used to access all Polldaddy contents
	 *
	 * Without this the plugin will be put into a semi-deactivated mode and no
	 * actions are carried out.
	 *
	 * @var string
	 * @since 1.1.0
	 */
	private $_user_code = '';

	/**
	 * Whether the plugin is ready for use
	 *
	 * @var string
	 * @since 1.1.0
	 */
	private $_activated = false;

	/**
	 * Hold poll data fetched from PollDaddy
	 *
	 * @var array
	 * @since 1.1.0
	 */
	private $_polls = array();

	/**
	 * Constructor
	 */
	public function __construct(array $meta, BWP_WP_Bridge $bridge = null)
	{
		parent::__construct($meta, $bridge);

		// basic version checking
		if (!$this->check_required_versions())
			return;

		// default options
		$options = array(
			'input_api_key'      => '',
			'input_update_time'  => 12,
			'select_time_type'   => 3600,
			'enable_ssl'         => '',
			'enable_auto_update' => 'yes',
			/* non-inputable option */
			'last_updated'       => '',
			'last_update_code'   => '', // @since 1.1.0
			'input_usercode'     => ''
		);

		$this->add_option_key('BWP_POLLDADDY_OPTION_GENERAL', 'bwp_polldaddy_general',
			__('Better WordPress Polldaddy Polls Settings', $this->domain));

		if (!defined('BWP_POLLDADDY_POLLS'))
			define('BWP_POLLDADDY_POLLS', 'bwp_polldaddy_polls');

		$this->build_properties('BWP_POLLDADDY', $options,
			dirname(dirname(__FILE__)) . '/bwp-polldaddy.php',
			'http://betterwp.net/wordpress-plugins/bwp-polldaddy/', false);
	}

	protected function load_libraries()
	{
		require_once dirname(__FILE__) . '/common-functions.php';
	}

	private function _reset_account()
	{
		$this->_user_code = $this->options['input_usercode'];
		$this->_api_key   = $this->options['input_api_key'];
	}

	protected function pre_init_properties()
	{
		// frequently used urls
		$this->add_url('polldaddy_api_register', 'http://polldaddy.com/register/', false);
		$this->add_url('polldaddy_contact', 'http://polldaddy.com/feedback/', false);
		$this->add_url('polldaddy_ssl', 'http://blog.polldaddy.com/2010/05/07/ssl-support-for-polls/', false);

		$this->cache_time = (int) $this->options['input_update_time'] * (int) $this->options['select_time_type'];

		$this->_reset_account();
		$this->_activated = !empty($this->_user_code) && !empty($this->_api_key)
			? true : false;
	}

	protected function pre_init_hooks()
	{
		add_action('widgets_init', 'bwp_polldaddy_register_widget');

		// @deprecated 1.1.0
		add_shortcode(apply_filters('bwp_polldaddy_shortcode_tag', 'bwpdaddy'),
			array($this, 'poll_shortcode'));

		// @since 1.1.0 better name for polldaddy shortcode
		add_shortcode(apply_filters('bwp_polldaddy_shortcode_tag', 'bwp-polldaddy'),
			array($this, 'poll_shortcode'));

		// @since 1.1.0 use cron to update polls instead
		add_filter('cron_schedules', array($this, 'add_cron_schedules'));

		if ($this->_activated)
			add_action('bwp_polldaddy_refresh', array($this, 'refresh'));

		// update plugin when version changes
		add_action('bwp_polldaddy_upgrade', array($this, 'upgrade_plugin'), 10, 2);
	}

	protected function enqueue_media()
	{
		if (is_admin())
			wp_enqueue_style('bwp-polldaddy', BWP_POLLDADDY_CSS . '/bwp-polldaddy-widget.css');
	}

	protected function init_properties()
	{
		$this->is_ssl = 'yes' == $this->options['enable_ssl'] ? true : false;
		$this->_polls = get_option(BWP_POLLDADDY_POLLS);
	}

	/**
	 * Build the Menus
	 */
	protected function build_menus()
	{
		add_options_page(
			__('Better WordPress Polldaddy Polls', $this->domain),
			'BWP Polldaddy',
			BWP_POLLDADDY_CAPABILITY,
			BWP_POLLDADDY_OPTION_GENERAL,
			array($this, 'show_option_pages')
		);
	}

	private function _build_container($for)
	{
		$return = array();

		switch ($for)
		{
			default:
			case 'stats':
				if ('yes' != $this->options['enable_auto_update'])
				{
					// auto update is disabled
					$return[] = __('Your polls will not be updated automatically.', $this->domain);
				}
				else
				{
					if (!empty($this->options['last_updated']))
					{
						// show last update message if any
						$return[] = sprintf(__('Last update time: <em><strong>%s</strong></em>.', $this->domain),
							date('Y-m-d H:i:s', $this->options['last_updated']));
					}

					if (!empty($this->options['last_update_code'])
						&& $this->options['last_update_code'] != 'ok'
					) {
						// show last update message if any
						$return[] = sprintf(__('Last update was unsuccessful. '
							. 'Error message was: <em><strong>%s</strong></em>', $this->domain),
							$this->_get_error_message($this->options['last_update_code']));
					}

					$cache_time        = $this->cache_time;
					$time_until_update = (int) $this->options['last_updated']
						+ (int) $cache_time - current_time('timestamp');
					$time_until_update = 0 > $time_until_update ? 0 : $time_until_update;

					// show next update time
					$return[] = sprintf(__('Next update in '
						. 'approximately <em><strong>%s</strong></em>.', $this->domain),
						$this->_sec2hms($time_until_update, true));
				}

				// show number of polls cached in database
				$this->_polls = is_array($this->_polls) ? $this->_polls : array();
				$return[] = sprintf(
					__('You currently have <em><strong>%d</strong></em> cached poll(s) in database.', $this->domain),
					count($this->_polls)
				);

				break;
		}

		return $return;
	}

	private function _get_error_message($error_code)
	{
		$message = '';

		switch ($error_code)
		{
			case 'no_connect':
				$message .= __('Could not connect to PollDaddy API host, please try again.', $this->domain);
				break;

			case 'no_response':
				$message .= __('Could not get a response from PollDaddy, please try again.', $this->domain);
				break;

			case 'unspecified':
				$message .= __('There was a problem retrieving polls, please try again.', $this->domain);
				break;

			case 'empty':
				$message .= __('No polls found, please make sure that '
					. 'you have created at least one poll and try again.', $this->domain);
				break;

			default:
				$message = esc_html($error_code);
				break;
		}

		return $message;
	}

	/**
	 * @param bool $suppress whether to not add any error if failed to update polls
	 */
	private function _admin_update_polls($suppress = false)
	{
		if ($this->refresh(true))
		{
			// only show a message if succeeded, any error message will be
			// shown directly on the option page, this is to ensure that
			// error messages are shown for cron jobs as well
			$this->add_notice(__('Successfully clear cached polls and '
				. 'retrieve updated polls from PollDaddy.', $this->domain
			));
		}
		elseif (!$suppress)
		{
			$this->add_error($this->_get_error_message($this->options['last_update_code']));
		}
	}

	private function _admin_get_usercode($api_key)
	{
		if (empty($api_key))
		{
			// API key is invalid, show an error
			$this->add_error(__('Please enter a valid API key.', $this->domain));

			return false;
		}

		$user_code = $this->_get_usercode($api_key);

		if (!empty($user_code))
		{
			// UserCode is valid, update necessary options
			$this->update_plugin_options(BWP_POLLDADDY_OPTION_GENERAL, array(
				'input_api_key'  => $api_key,
				'input_usercode' => $user_code
			));

			// reset account info and fully activate this plugin
			$this->_reset_account();
			$this->_activated = true;

			// show success message
			$this->add_notice(__('A UserCode has been retrieved successfully.', $this->domain));

			// try to fetch polls right after, suppressing errors
			$this->_admin_update_polls(true);
		}
		else
		{
			// UserCode is invalid, update necessary options
			$this->update_plugin_options(BWP_POLLDADDY_OPTION_GENERAL, array(
				'input_usercode' => ''
			));

			// reset account info and deactivate the plugin
			$this->_reset_account();
			$this->_activated = false;

			// UserCode is invalid, show an error message
			$this->add_error(sprintf(
					__('A UserCode could not be retrieved using provided API key: <strong>%s</strong>. '
					. 'Please double-check your API key and try again.<br />'
					. 'If this issue persists, consider '
					. '<a href="%s" target="_blank">contacting Polldaddy team</a> '
					. 'for support.', $this->domain),
					esc_html($api_key), $this->get_url('polldaddy_contact')
				)
			);
		}
	}

	public function add_cron_schedules($schedules)
	{
		$schedules['bwp-polldaddy'] = array(
			'interval' => $this->cache_time,
			'display'  => __('BWP Polldaddy', $this->domain)
		);

		return $schedules;
	}

	public function add_manual_update_button($button)
	{
		$button = str_replace(
			'</p>',
			'&nbsp; <input type="submit" class="button-secondary action" '
				. 'name="manual_update" value="'
				. __('Clear cached Polls', $this->domain) . '" /></p>',
			$button);

		return $button;
	}

	public function handle_get_usercode()
	{
		// user is requesting usercode from Polldaddy based on API key
		$api_key = isset($_POST['input_api_key'])
			? trim(strip_tags(stripslashes($_POST['input_api_key'])))
			: '';

		$this->_admin_get_usercode($api_key);

		return !$this->has_error();
	}

	public function handle_manual_update()
	{
		// manually update poll data
		$this->_admin_update_polls();

		return !$this->has_error();
	}

	public function handle_dynamic_general_options(array $options)
	{
		if (!empty($options['input_api_key'])
			&& $this->current_options['input_api_key'] != $options['input_api_key']
		) {
			// if api key was changed we check for its validity, this
			// should update the options in db AND set appropriate
			// properties such as Polldaddy account and activated status
			$this->_admin_get_usercode($options['input_api_key']);

			// usercode is invalid
			if ($this->has_error())
				return false;
		}
		elseif (empty($options['input_api_key']))
		{
			// if api key is empty, show an error message and return false to
			// not update it
			$this->add_error(__('Please enter a valid API key.', $this->domain));
			return false;
		}

		return $options;
	}

	protected function build_option_page()
	{
		$page        = $this->get_current_admin_page();
		$option_page = $this->current_option_page;

		if (empty($page) || $page !== BWP_POLLDADDY_OPTION_GENERAL)
			return;

		$form_options = array();

		if (!$this->_activated)
		{
			// plugin is semi-deactivated, user must provide an API key to
			// fully activate the plugin
			$form = array(
				'items' => array(
					'input'
				),
				'item_labels' => array(
					__('Polldaddy API key', $this->domain)
				),
				'item_names' => array(
					'input_api_key'
				),
				'input' => array(
					'input_api_key'     => array(
						'size' => 45,
						'label' => '<br />' . sprintf(
							__('In order to get and display polls from Polldaddy '
							. 'you need a valid pair of <strong>API key</strong> and <strong>UserCode</strong>.<br />'
							. 'A free API key can be registered <a href="%s" target="_blank">on Polldaddy\'s website</a>.<br />'
							. 'To get a UserCode, enter your API key and press the "Get UserCode" button below.', $this->domain),
							$this->get_url('polldaddy_api_register')
						)
					)
				),
				'container' => array(
					'input_api_key' => '<input type="submit" class="button-primary" '
					. 'name="get_usercode" value="'
					. __('Get UserCode', $this->domain) . '" />'
				)
			);

			$form_options = array(
				'input_api_key',
			);

			// this form does not have any submit button
			add_filter('bwp_option_submit_button', create_function('', 'return "";'));
		}
		elseif ($this->_activated)
		{
			// plugin is activated, show all possible options get default
			// options used for current form
			$form = array(
				'items' => array(
					'heading',
					'input',
					'input',
					'checkbox',
					'heading',
					'checkbox',
					'input',
					'heading'
				),
				'item_labels' => array(
					__('PollDaddy Settings'),
					__('PollDaddy API key', $this->domain),
					__('PollDaddy UserCode', $this->domain),
					__('Enable SSL for Poll Contents', $this->domain),
					__('Plugin Functionality', $this->domain),
					__('Automatically update polls?', $this->domain),
					__('Update polls every', $this->domain),
					__('Poll Stats', $this->domain)
				),
				'item_names' => array(
					'heading_account',
					'input_api_key',
					'input_usercode',
					'enable_ssl',
					'heading_poll',
					'enable_auto_update',
					'input_update_time',
					'heading_stats'
				),
				'heading' => array(
					'heading_account' => '<em>'
					. __('Customize PollDaddy-related settings here.', $this->domain)
					. '</em>',
					'heading_poll'    => '<em>'
					. __('Control how this plugin should operate.', $this->domain)
					. '</em>',
					'heading_stats'   => '<em>'
					. __('Some PollDaddy Polls stats for your site.', $this->domain)
					. '</em>'
				),
				'select' => array(
					'select_time_type' => array(
						__('second(s)', $this->domain) => 1,
						__('minute(s)', $this->domain) => 60,
						__('hour(s)', $this->domain)   => 3600,
						__('day(s)', $this->domain)    => 86400
					)
				),
				'checkbox'	=> array(
					'enable_auto_update' => array(__('If you rarely edit or add new polls, '
					. 'you might want to disable this and '
					. 'update your polls manually instead.', $this->domain) => ''),
					'enable_ssl' => array(sprintf(__('Only enable this setting if you have a paid Polldaddy account. '
					. 'More info <a href="%s" target="_blank">here</a>.', $this->domain), $this->get_url('polldaddy_ssl')) => '')
				),
				'input' => array(
					'input_api_key'    => array(
						'size' => 45,
						'label' => '<br />' . sprintf(
							__('You can register for a different API key <a href="%s">here</a>.', $this->domain),
							$this->get_url('polldaddy_api_register')
						)
					),
					'input_usercode'    => array(
						'size'     => 45,
						'disabled' => ' disabled="disabled"',
						'label'    => '<br /> '
						. __('UserCode is automatically retrieved from Polldaddy, '
						. 'based on provided API key.<br />'
						. 'If you update your API key, make sure that you press "Get new UserCode" '
						. 'to update your UserCode as well.', $this->domain)
					),
					'input_update_time' => array(
						'size'  => 5,
						'label' => __('&mdash;', $this->domain)
					)
				),
				'container' => array(
					'input_update_time' => '<em><strong>' . __('Note', $this->domain) . '</strong>: '
					. __('Requesting data from Polldaddy&#8217;s API server can be slow. '
					. 'It is therefore recommended that you '
					. 'set the update interval to at least 12 hours.', $this->domain)
					. '</em>',
					'heading_stats' => $this->_build_container('stats')
				),
				'inline_fields' => array(
					'input_update_time' => array(
						'select_time_type' => 'select'
					)
				),
				'inline' => array(
					'input_usercode' => '<br /><br />'
					. '<input class="button-secondary" type="submit" '
					. 'name="get_usercode" value="' . __('Get new UserCode', $this->domain) . '"/>'
				),
				'formats' => array(
					'input_update_time' => 'int',
					'select_time_type'  => 'int'
				)
			);

			$form_options = array(
				'input_api_key',
				'input_usercode',
				'input_update_time',
				'select_time_type',
				'enable_auto_update',
				'enable_ssl',
				'last_updated',
				'last_update_code'
			);

			add_filter('bwp_option_page_submit_options', array($this, 'handle_dynamic_general_options'));

			$this->current_option_page->register_custom_submit_action('manual_update');
			add_filter('bwp_option_submit_button', array($this, 'add_manual_update_button'));
			add_filter('bwp_option_page_custom_action_manual_update', array($this, 'handle_manual_update'));
		}

		// custom form action for both forms
		$this->current_option_page->register_custom_submit_action('get_usercode');
		add_filter('bwp_option_page_custom_action_get_usercode', array($this, 'handle_get_usercode'));

		$option_page->init($form, $form_options);
	}

	public function show_option_pages()
	{
		$this->current_option_page->generate_html_form();
	}

	public function refresh($forced = false)
	{
		// try to retrieve polls from Polldaddy and log response code as well
		// as last updated time for stats
		$polls = $this->_get_polls();

		// update code from polldaddy
		$update_code = '';

		if ($polls && is_array($polls))
		{
			$update_code = 'ok';

			// polls updated successfully, store 'ok' response code and updated
			// poll data
			$this->_polls = $polls;
			update_option(BWP_POLLDADDY_POLLS, $this->_polls);
		}
		else
		{
			// poll update failed, store error code from Polldaddy
			$update_code = $polls;
		}

		$this->update_plugin_options(BWP_POLLDADDY_OPTION_GENERAL, array(
			'last_updated'     => current_time('timestamp'),
			'last_update_code' => $update_code
		));

		$this->options['last_update_code'] = $update_code;

		if ($update_code != 'ok')
			return false;

		return true;
	}

	public function get_cached_polls()
	{
		return $this->_polls;
	}

	private function _render_poll($poll)
	{
		$poll_host    = 'http://polldaddy.com';
		$poll_js_host = $this->is_ssl
			? 'https://secure.polldaddy.com'
			: 'http://static.polldaddy.com';

		$html  = '<script type="text/javascript" charset="utf-8" '
			. 'src="' . $poll_js_host . '/p/' . $poll->id . '.js"></script>' . "\n";
		$html .= '<noscript><a href="' . $poll_host . '/poll/' . $poll->id . '/">'
			. $poll->content . '</a></noscript>' . "\n";

		return $html;
	}

	public function render_polls($args = array())
	{
		extract(wp_parse_args($args, array(
			'id'      => '',
			'limit'   => 1,
			'orderby' => 'id',
			'order'   => 'desc'
		)));

		$output = '';

		if (!empty($id))
		{
			// get and display a single poll
			$poll = bwp_get_poll($id);

			if (!$poll)
				return '';

			return $this->_render_poll($poll);
		}
		else
		{
			// get and display multiple polls, default to show 1
			$polldaddy_polls = bwp_get_polls($limit, $orderby, $order);

			foreach ($polldaddy_polls as $poll)
				$output .= $this->_render_poll($poll);
		}

		return $output;
	}

	public function poll_shortcode($atts)
	{
		return $this->render_polls(shortcode_atts(array(
			'id'      => '',
			'limit'   => 1,
			'orderby' => '',
			'order'   => 'desc'
		), $atts));
	}

	private function _sec2hms($sec, $padHours = false)
	{
		$hours = intval(intval($sec) / 3600);
		if ($hours > 1)
		{
			// unit should be hour
			return $hours . ' ' . __('hour(s)', $this->domain);
		}

		$minutes = intval($sec / 60);
		if ($minutes > 1)
		{
			// unit should be minute
			return $minutes . ' ' . __('minute(s)', $this->domain);
		}

		return $sec . ' ' . __('second(s)', $this->domain);
	}

	private function _do_request($request)
	{
		$request  = json_encode($request);
		$response = '';
		$result   = '';

		$response = wp_remote_post(
			$this->api_host,
			array(
				'headers' => array(
					'Content-Type'   => 'application/json; charset=utf-8',
					'Content-Length' => strlen($request)
				),
				'body' => $request
			)
		);

		if (is_wp_error($response))
			return $response->get_error_message();

		$result = wp_remote_retrieve_body($response);

		return json_decode($result);
	}

	private function _get_polls()
	{
		if (!$this->_activated)
			return false;

		$request = array(
			'pdRequest' => array(
				'partnerGUID' => $this->_api_key,
				'userCode'    => $this->_user_code,
				'demands'     => array(
					'demand'  => array(
						'id'  => 'GetPolls'
					)
				)
			)
		);

		$response = $this->_do_request($request);

		if (is_string($response))
		{
			// other error messages not related to PollDaddy
			return $response;
		}

		if (isset($response->pdResponse->errors->error[0]))
		{
			// request did not succeed, get first Polldaddy error message
			return $response->pdResponse->errors->error[0]->content;
		}
		elseif (!isset($response->pdResponse->demands->demand[0]->polls))
		{
			// this should not happen, but if it does fire it under unspecified
			return 'unspecified';
		}

		$polls = $response->pdResponse->demands->demand[0]->polls;
		if (!isset($polls->poll) || empty($polls->total))
		{
			// no polls found
			return 'empty';
		}

		return $response->pdResponse->demands->demand[0]->polls->poll;
	}

	/**
	 * Gets UserCode from Polldaddy server
	 *
	 * @return mixed
	 * @access private
	 */
	private function _get_usercode($api_key)
	{
		$request = array(
			'pdAccess' => array(
				'partnerGUID'   => $api_key,
				'partnerUserID' => 0,
				'demands'       => array(
					'demand' => array(
						'id' => 'GetUserCode'
					)
				)
			)
		);

		$response = $this->_do_request($request);

		if (empty($response->pdResponse->userCode))
			return false;

		return $response->pdResponse->userCode;
	}

	public function upgrade_plugin($from, $to)
	{
		if (!$from || version_compare($from, '1.1.0', '<'))
		{
			// @since 1.1.0 `user_code` option is removed and `input_usercode`
			// doesn't have any N/A value
			unset($this->options['user_code']);

			if (false === strpos($this->options['input_usercode'], '$P'))
				$this->options['input_usercode'] = '';

			update_option(BWP_POLLDADDY_OPTION_GENERAL, $this->options);

			// @since 1.1.0 use cron to update polls instead
			wp_schedule_event(time(), 'bwp-polldaddy', 'bwp_polldaddy_refresh');
		}
	}

	public function install()
	{
		wp_schedule_event(time(), 'bwp-polldaddy', 'bwp_polldaddy_refresh');
	}

	public function uninstall()
	{
		wp_clear_scheduled_hook('bwp_polldaddy_refresh');
	}
}
