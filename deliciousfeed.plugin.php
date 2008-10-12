<?php
/**
 * DeliciousFeed Plugin: Show the posts from Delicious feed
 * Usage: <?php $theme->deliciousfeed(); ?>
 */

class DeliciousFeed extends Plugin
{
	private $config = array();
	private $class_name = '';
	private $default_options = array(
		'user_id' => '',
		'tags' => '',
		'num_item' => '15',
		'cache_expiry' => 1800
	);

	/**
	 * Required plugin information
	 * @return array The array of information
	 **/
	public function info()
	{
		return array(
			'name' => 'DeliciousFeed',
			'version' => '0.5-0.3-pre',
			'url' => 'http://code.google.com/p/bcse/wiki/DeliciousFeed',
			'author' => 'Joel Lee',
			'authorurl' => 'http://blog.bcse.info/',
			'license' => 'Apache License 2.0',
			'description' => 'Display your latest bookmarks on your blog.',
			'copyright' => '2008'
		);
	}

	/**
	 * Add update beacon support
	 **/
	public function action_update_check()
	{
	 	Update::add('DeliciousFeed', 'b0a81efa-c59a-41f2-b71e-b2f41d0885f1', $this->info->version);
	}

	/**
	 * Add actions to the plugin page for this plugin
	 * @param array $actions An array of actions that apply to this plugin
	 * @param string $plugin_id The string id of a plugin, generated by the system
	 * @return array The array of actions to attach to the specified $plugin_id
	 **/
	public function filter_plugin_config($actions, $plugin_id)
	{
		if ($plugin_id === $this->plugin_id()) {
			$actions[] = _t('Configure', $this->class_name);
		}

		return $actions;
	}

	/**
	 * Respond to the user selecting an action on the plugin page
	 * @param string $plugin_id The string id of the acted-upon plugin
	 * @param string $action The action string supplied via the filter_plugin_config hook
	 **/
	public function action_plugin_ui($plugin_id, $action)
	{
		if ($plugin_id === $this->plugin_id()) {
			switch ($action) {
				case _t('Configure', $this->class_name):
					$ui = new FormUI($this->class_name);

					$user_id = $ui->append('text', 'user_id', 'option:' . $this->class_name . '__user_id', _t('Delicious Username', $this->class_name));
					$user_id->add_validator('validate_username');
					$user_id->add_validator('validate_required');

					$tags = $ui->append('text', 'tags', 'option:' . $this->class_name . '__tags', _t('Tags (seperate by space)', $this->class_name));

					$num_item = $ui->append('text', 'num_item', 'option:' . $this->class_name . '__num_item', _t('&#8470; of Posts', $this->class_name));
					$num_item->add_validator('validate_uint');
					$num_item->add_validator('validate_required');

					$cache_expiry = $ui->append('text', 'cache_expiry', 'option:' . $this->class_name . '__cache_expiry', _t('Cache Expiry (in seconds)', $this->class_name));
					$cache_expiry->add_validator('validate_uint');
					$cache_expiry->add_validator('validate_required');

					// When the form is successfully completed, call $this->updated_config()
					$ui->append('submit', 'save', _t('Save', $this->class_name));
					$ui->set_option('success_message', _t('Options saved', $this->class_name));
					$ui->out();
					break;
			}
		}
	}

	public function validate_username($username)
	{
		if (!ctype_alnum($username)) {
			return array(_t('Your Delicious username is not valid.', $this->class_name));
		}
		return array();
	}

	public function validate_uint($value)
	{
		if (!ctype_digit($value) || strstr($value, '.') || $value < 0) {
			return array(_t('This field must be positive integer.', $this->class_name));
		}
		return array();
	}

	private function plugin_configured($params = array())
	{
		if (empty($params['user_id']) ||
			empty($params['num_item']) ||
			empty($params['cache_expiry'])) {
			return false;
		}
		return true;
	}

	private function load_feeds($params = array())
	{
		$cache_name = $this->class_name . '__' . md5(serialize($params));
		
		if (Cache::has($cache_name)) {
			// Read from cache
			return Cache::get($cache_name);
		}
		else {
			$url = 'http://feeds.delicious.com/v2/json/' . $params['user_id'];
			if ($params['tags']) {
				$url .= '/' . urlencode($params['tags']);
			}
			$url .= '?count=' . $params['num_item'];

			try {
				// Get JSON content via Delicious API
				$call = new RemoteRequest($url);
				$call->set_timeout(5);
				$result = $call->execute();
				if (Error::is_error($result)) {
					throw Error::raise(_t('Unable to contact Delicious.', $this->class_name));
				}
				$response = $call->get_response_body();

				// Decode JSON
				$deliciousfeed = json_decode($response);
				if (!is_array($deliciousfeed)) {
					// Response is not JSON
					throw Error::raise(_t('Response is not correct, maybe Delicious server is down or API is changed.', $this->class_name));
				} else {
					// Transform to DeliciousPost objects
					$serial = serialize($deliciousfeed);
					$serial = str_replace('O:8:"stdClass":', 'O:13:"DeliciousPost":', $serial);
					$deliciousfeed = unserialize($serial);
				}

				// Do cache
				Cache::set($cache_name, $deliciousfeed, $params['cache_expiry']);

				return $deliciousfeed;
			}
			catch (Exception $e) {
				return $e->getMessage();
			}
		}
	}

	/**
	 * Add Delicious posts to the available template vars
	 * @param Theme $theme The theme that will display the template
	 **/
	public function theme_deliciousfeed($theme, $params = array())
	{
		$params = array_merge($this->config, $params);

		if ($this->plugin_configured($params)) {
			$theme->deliciousfeed = $this->load_feeds($params);
		}
		else {
			$theme->deliciousfeed = _t('DeliciousFeed Plugin is not configured properly.', $this->class_name);
		}

		return $theme->fetch('deliciousfeed');
	}

	/**
	 * On plugin activation, set the default options
	 */
	public function action_plugin_activation($file)
	{
		if (realpath($file) === __FILE__) {
			$this->class_name = strtolower(get_class($this));
			foreach ($this->default_options as $name => $value) {
				$current_value = Options::get($this->class_name . '__' . $name);
				if (is_null($current_value)) {
					Options::set($this->class_name . '__' . $name, $value);
				}
			}
		}
	}

	/**
	 * On plugin init, add the template included with this plugin to the available templates in the theme
	 */
	public function action_init()
	{
		$this->class_name = strtolower(get_class($this));
		foreach ($this->default_options as $name => $value) {
			$this->config[$name] = Options::get($this->class_name . '__' . $name);
		}
		$this->load_text_domain($this->class_name);
		$this->add_template('deliciousfeed', dirname(__FILE__) . '/deliciousfeed.php');
	}
}

class DeliciousPost extends stdClass
{
	public $u = '';
	public $d = '';
	public $t = array();
	public $dt = '';
	public $n = '';

	public function __get($name) {
		switch ($name) {
			case 'url':
				return $this->u;
				break;
			case 'title':
				return htmlspecialchars($this->d);
				break;
			case 'desc':
				return htmlspecialchars($this->n);
				break;
			case 'tags':
				return htmlspecialchars($this->t);
				break;
			case 'tags_text':
				return htmlspecialchars(implode(' ', $this->t));
				break;
			case 'timestamp':
				return $this->dt;
				break;
			default:
				return FALSE;
				break;
		}
	}
}
?>
