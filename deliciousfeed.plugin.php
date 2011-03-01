<?php
/**
 * DeliciousFeed Plugin: Show the posts from Delicious feed
 */

class DeliciousFeed extends Plugin
{
	private static function default_options( )
	{
		return array(
			'user_id' => '',
			'bookmark_tags' => '',
			'bookmark_count' => '15',
			'cache_expiry' => 1800
		);
	}

	public function action_block_form_deliciousfeed( $form, $block )
	{
		// Load defaults
		foreach ( self::default_options( ) as $k => $v ) {
			if ( !isset( $block->$k ) )
				$block->$k = $v;
		}

		$form->append( 'text', 'user_id', $block, _t( 'Delicious Username', 'deliciousfeed' ) );
		$form->user_id->add_validator( 'validate_username' );
		$form->user_id->add_validator( 'validate_required' );

		$form->append( 'text', 'bookmark_tags', $block, _t( 'Tags (seperate by space)', 'deliciousfeed' ) );

		$form->append( 'text', 'bookmark_count', $block, _t( '&#8470; of Posts', 'deliciousfeed' ) );
		$form->bookmark_count->add_validator( 'validate_uint' );
		$form->bookmark_count->add_validator( 'validate_required' );

		$form->append( 'text', 'cache_expiry', $block, _t( 'Cache Expiry (in seconds)', 'deliciousfeed' ) );
		$form->cache_expiry->add_validator( 'validate_uint' );
		$form->cache_expiry->add_validator( 'validate_required' );
	}

	public function validate_username( $username )
	{
		if ( preg_match( '/[A-Za-z0-9._]+/', $username ) === false ) {
			return array( _t( 'Your Delicious username is not valid.', 'deliciousfeed' ) );
		}
		return array( );
	}

	public function validate_uint( $value )
	{
		if ( !ctype_digit( $value ) || strstr( $value, '.' ) || $value < 0 ) {
			return array( _t( 'This field must be positive integer.', 'deliciousfeed' ) );
		}
		return array( );
	}

	private static function build_api_url( $block )
	{
		$url = 'http://feeds.delicious.com/v2/json/' . $block->user_id;
		if ( $block->bookmark_tags ) {
			$url .= '/' . urlencode( $block->bookmark_tags );
		}
		$url .= '?count=' . $block->bookmark_count;
		return $url;
	} 

	private static function get_external_content( $url )
	{
		// Get JSON content via Delicious API
		$call = new RemoteRequest( $url );
		$call->set_timeout( 5 );
		$result = $call->execute( );
		if ( Error::is_error( $result ) ) {
			throw new Exception( _t( 'Unable to contact Delicious.', 'deliciousfeed' ) );
		}
		return $call->get_response_body( );
	}

	private static function parse_data( $block, $data )
	{
		// Decode JSON
		$feed = json_decode( $data );
		if ( !is_array( $feed ) ) {
			// Response is not JSON
			throw new Exception( _t( 'Response is not correct, maybe Delicious server is down or API is changed.', 'deliciousfeed' ) );
		} else {
			$deliciousfeed = array( );
			foreach( $feed as $i => $link ) {
				$delicious_post = new DeliciousPost( $link );
				$deliciousfeed[ ] = $delicious_post;
			}
		}

		return $deliciousfeed;
	}

	public function action_block_content_deliciousfeed( $block, $theme )
	{
		// Load defaults
		foreach ( self::default_options( ) as $k => $v ) {
			if ( !isset( $block->$k ) )
				$block->$k = $v;
		}

		$cache_name = 'deliciousfeed_' . md5( serialize( array(
			$block->user_id,
			$block->bookmark_tags,
			$block->bookmark_count,
			) ) );
		
		if ( $block->user_id != '' ) {
			if ( Cache::has( $cache_name ) ) {
				$block->bookmarks = Cache::get( $cache_name );
			}
			else {
				try {
					$url = self::build_api_url( $block );
					$data = self::get_external_content( $url );
					$bookmarks = self::parse_data( $block, $data );
					$block->bookmarks = $bookmarks;
					// Do cache
					Cache::set( $cache_name, $block->images, $block->cache );
				}
				catch ( Exception $e ) {
					$block->error = $e->getMessage( );
				}
			}
		}
		else {
			$block->error = _t( 'DeliciousFeed Plugin is not configured properly.', 'deliciousfeed' );
		}
	}

	/**
	 * On plugin init, add the template included with this plugin to the available templates in the theme
	 */
	public function action_init( )
	{
		$this->load_text_domain( 'deliciousfeed' );
		$this->add_template( 'block.deliciousfeed', dirname( __FILE__ ) . '/block.deliciousfeed.php' );
	}

	public function filter_block_list( $block_list )
	{
		$block_list[ 'deliciousfeed' ] = _t( 'DeliciousFeed', 'deliciousfeed' );
		return $block_list;
	}
}

class DeliciousPost
{
	public $data = array( );

	public function __construct( stdClass $data )
	{
		foreach( $data as $name => $value ) {
			$this->data[ $name ] = $value;
		}
	}
	
	public function __get( $name )
	{
		switch ( $name ) {
			case 'url':
				return $this->data[ 'u' ];
				break;
			case 'title':
				return htmlspecialchars( $this->data[ 'd' ] );
				break;
			case 'desc':
				return htmlspecialchars( $this->data[ 'n' ] );
				break;
			case 'tags':
				return htmlspecialchars( $this->data[ 't' ] );
				break;
			case 'tags_text':
				return htmlspecialchars( implode( ' ', $this->data[ 't' ] ) );
				break;
			case 'timestamp':
				return $this->data[ 'dt' ];
				break;
			default:
				return FALSE;
				break;
		}
	}
	
	public function __set( $name, $value )
	{
		$this->data[ $name ] = $value;
		return $this->data[ $name ];
	}
}
?>
