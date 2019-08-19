<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

if ( ! class_exists( 'WPS_Random_Posts' ) ) {

class WPS_Random_Posts extends WP_CLI_Command {

	protected $nonsentences;

	function __construct() {
		require_once plugin_dir_path( __FILE__ ) . '/nonsentences/nonsentences.php';
		$this->nonsentences = new Nonsentences();
	}

	/**
	 * Generate some truly random posts.
	 *
	 * Create a specific number of posts
	 * of a specific post_type
	 * with a specific post_status
	 * by a random post_author (or specific author)
	 * within a post_date range (min/max date)
	 * with a max_depth (for hierarchichal post types)
	 * with a random number of body paragraphs (min/max length)
	 * with a random number of terms (min/max terms)
	 * from a specific set of taxonomies
	 * and a random featured image (or no image).
	 *
	 * ## OPTIONS
	 * [--count=<number>]
     * : How many posts to generate.
     * ---
     * default: 100
     * ---
     *
     * [--post_type=<type>]
     * : The type of the generated posts.
     * ---
     * default: post
     * ---
     *
     * [--post_status=<status>]
     * : The status of the generated posts.
     * ---
     * default: publish
     * ---
	 *
     * [--post_author=<login>]
     * : The author of the generated posts.
     * ---
     * default: random
     * ---
	 *
     * [--min_date=<yyyy-mm-dd>]
     * : The oldest date of the generated posts. Default: current date
	 *
	 * [--max_date=<yyyy-mm-dd>]
     * : The newest date of the generated posts. Default: current date
     *
     * [--max_depth=<number>]
     * : For hierarchical post types, generate child posts down to a certain depth.
     * ---
     * default: 1
     * ---
     *
     * [--min_length=<number>]
     * : The minimum length of the generated posts (in paragraphs).
     * ---
     * default: 1
     * ---
	 *
	 * [--max_length=<number>]
     * : The maximum length of the generated posts (in paragraphs).
     * ---
     * default: 10
     * ---
	 *
     * [--min_terms=<number>]
     * : The minimum number of terms to attach to a generated post.
     * ---
     * default: 0
     * ---
	 *
	 * [--max_terms=<number>]
     * : The maximum number of terms to attach to a generated post.
     * ---
     * default: 5
     * ---
     *
     * [--taxonomies=<taxonomy>]
     * : The taxonomy/ies to use for attaching terms.
     * ---
     * default: category,post_tag
     * ---
     *
     * [--set_thumbnail=<boolean>]
     * : Whether or not to set a post thumbnail for generated posts. Default: true
     *
     * [--require_thumb=<boolean>]
     * : True enforces thumbnails on every generated post. False introduces chance that a posts has no thumbnail (20% chance of being empty).
     * ---
     * default: false
     * ---
     *
     * [--thumb_keywords=<string>]
     * : Specific search keywords (comma separated) to refine image selection.
     * ---
     * default:
     * ---
     *
     * [--thumb_size=<string>]
     * : Specific image dimensions in WxH format (e.g. 1024x768) to limit downloaded image dimensions. An empty value defaults to the largest size available.
     * ---
     * default:
     * ---
     *
     * [--with_terms=<boolean>]
     * : Generate random terms before generating posts. Default: false
     *
	 */
	public function generate( $args = array(), $assoc_args = array() ) {

		$this->assoc_args = wp_parse_args( $assoc_args, array(
			'count'          => 100,
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'post_author'    => 'random',
			'min_date'       => current_time( 'mysql' ),
			'max_date'       => current_time( 'mysql' ),
			'max_depth'      => 1,
			'min_length'     => 1,
			'max_length'     => 10,
			'min_terms'      => 0,
			'max_terms'      => 5,
			'taxonomies'     => 'category,post_tag',
			'set_thumbnail'  => true,
			'require_thumb'  => false,
			'thumb_keywords' => '',
			'thumb_size'     => '',
			'with_terms'     => false,
		) );

		// Confirm post type is valid
		if ( ! post_type_exists( $this->assoc_args['post_type'] ) ) {
			WP_CLI::error( sprintf( 'The %s post type does not exist.', $this->assoc_args['post_type'] ) );
		}

		// Generate terms if desired
		if ( true === $this->assoc_args['with_terms'] ) {
			$this->generate_terms( null, array( 'taxonomy' => $this->assoc_args['taxonomies'] ) );
		}

		// Force taxonomies value into an array.
		$this->assoc_args['taxonomies'] = explode(',', $this->assoc_args['taxonomies'] );

		// Establish hierarchy
		$hierarchical     = get_post_type_object( $this->assoc_args['post_type'] )->hierarchical;
		$previous_post_id = 0;
		$current_depth    = 1;
		$this->assoc_args['post_parent'] = 0;

		$progress = \WP_CLI\Utils\make_progress_bar( sprintf( 'Generating %1$d %2$s posts', $this->assoc_args['count'], $this->assoc_args['post_type'] ), $this->assoc_args['count'] );

		for ( $i = 0; $i < $this->assoc_args['count']; $i++ ) {

			// Update hierarchy
			if ( $hierarchical ) {
				if ( $this->maybe_make_child() && $current_depth < $assoc_args['max_depth'] ) {
					$this->assoc_args['post_parent'] = $previous_post_id;
					$current_depth++;
				} elseif( $this->maybe_reset_depth() ) {
					$current_depth = 1;
					$this->assoc_args['post_parent'] = 0;
				}
			}

			$post_id = $this->insert_post();
			$this->set_post_terms( $post_id );
			$this->maybe_set_featured_image( $post_id );
			$previous_post_id = $post_id;
			$progress->tick();
		}

		$progress->finish();
	}

	/**
	 * Insert a post into the database.
	 *
	 * @since  1.0.0
	 *
	 * @return integer|WP_Error Post ID or error object.
	 */
	private function insert_post() {

		// Setup nonsentences
		$this->nonsentences->min_paragraphs = $this->assoc_args['min_length'];
		$this->nonsentences->max_paragraphs = $this->assoc_args['max_length'];

		$post = wp_insert_post( array(
			'post_type'    => $this->assoc_args['post_type'],
			'post_status'  => $this->assoc_args['post_status'],
			'post_parent'  => $this->assoc_args['post_parent'],
			'post_title'   => $this->nonsentences->title(),
			'post_content' => $this->nonsentences->paragraphs(),
			'post_author'  => $this->get_random_author(),
			'post_date'    => $this->get_random_post_date( $this->assoc_args['min_date'], $this->assoc_args['max_date'] ),
		), true );

		if ( is_wp_error( $post ) ) {
			WP_CLI::warning( $post->get_error_message() );
			return;
		}

		return $post;
	}

	/**
	 * Get a random non-subscriber user.
	 *
	 * @since  1.0.0
	 *
	 * @return integer User ID.
	 */
	private function get_random_author() {

		if ( 'random' !== $this->assoc_args['post_author'] ) {
			return $this->assoc_args['post_author'];
		}

		$users = get_users( array(
			'role__not_in' => array( 'subscriber' ),
			'orderby' => 'post_count',
			'fields' => 'ID',
			'number' => 20,
		) );

		return $users[ mt_rand( 0, ( count( $users ) - 1 ) ) ];

	}

	/**
	 * Get a random date from between two dates.
	 *
	 * @since  1.0.0
	 *
	 * @param  integer|string $min_date Oldest date in date range.
	 * @param  integer|string $max_date Newest date in date range.
	 * @return string                   Date formatted as YYYY-MM-DD.
	 */
	private function get_random_post_date( $min_date = 0, $max_date = 0 ) {

		$date_start   = is_int( $min_date ) ? $min_date : strtotime( $min_date );
		$date_end     = is_int( $max_date ) ? $max_date : strtotime( $max_date );
		$days_between = absint( ( $date_end - $date_start ) / DAY_IN_SECONDS );
		$random_day   = mt_rand( 0, $days_between );
		$random_date  = date( 'Y-m-d', $date_start + ( $random_day * DAY_IN_SECONDS ) );

		return $random_date;
	}

	/**
	 * Set terms for a post.
	 *
	 * @since 1.0.0
	 *
	 * @param integer $post_id Post ID.
	 */
	private function set_post_terms( $post_id = 0 ) {

		foreach ( $this->assoc_args['taxonomies'] as $taxonomy ) {

			if ( ! taxonomy_exists( $taxonomy ) ) {
				WP_CLI::error( sprintf( 'The %s taxonomy does not exist.', $taxonomy ) );
			}

			// Establish number of terms to be added
			$term_count = mt_rand( $this->assoc_args['min_terms'], $this->assoc_args['max_terms'] );

			// Bail if we're adding 0 terms
			if ( 0 === $term_count ) {
				continue;
			}

			// Get all terms first...
			$terms = get_terms( array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
			) );

			// ...then randomize and pluck off however many we need (but not more than exist)
			shuffle( $terms );
			$terms = array_slice( $terms, 0, min( $term_count, count( $terms ) ) );

			$set_terms = wp_set_object_terms( $post_id, array_map( 'absint', $terms ), $taxonomy );

			if ( is_wp_error( $set_terms ) ) {
				WP_CLI::warning( $set_terms->get_error_message() );
				continue;
			}
		}
	}

	private function maybe_set_featured_image( $post_id ) {
		$featured_image = false;

		// If a thumbnail is not required, see if we should set one (20% chance of leaving empty)
		$leave_empty = $this->assoc_args['require_thumb'] ? 0 : ( mt_rand( 1, 5 ) == 1 );

		if ( true === $this->assoc_args['set_thumbnail'] && ! $leave_empty ) {

			$attachment_id = $this->download_image( $post_id );

			if ( empty( $attachment_id ) ) {
				return;
			}

			set_post_thumbnail( $post_id, $attachment_id );
		}
	}

	private function download_image( $post_id = 0 ) {

		// Require media files
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$image_url = $this->get_remote_image_url( $post_id );

		// Setup file
		$tmp  = download_url( $image_url );
		$type = image_type_to_extension( exif_imagetype( $tmp ) );
		$file = array(
			'name'     => 'placeholder-' . mt_rand( 1, 40982 ) . $type,
			'tmp_name' => $tmp,
		);

		if ( is_wp_error( $tmp ) ) {
			@unlink( $tmp );
			WP_CLI::warning( $tmp->get_error_message() );
			return null;
		}

		$attachment_id = media_handle_sideload( $file, $post_id, $this->nonsentences->sentences( 1, 1 ) );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
			WP_CLI::warning( $attachment_id->get_error_message() );
			return null;
		}

		return $attachment_id;
	}

	/**
	 * Get a random image from Unsplash.me.
	 *
	 * Will utilize post title for keyword if none specified.
	 *
	 * @since  1.0.0
	 *
	 * @param  integer $post_id Post ID
	 * @return string           Image URL.
	 */
	private function get_remote_image_url( $post_id = 0 ) {

		// Use last word of title as thumbnail keyword if no keyword specified
		$keywords = $this->assoc_args['thumb_keywords'];
		if ( empty( $keywords ) ) {
			$title_words = explode( ' ', get_the_title( $post_id ) );
			$keywords = array_pop( $title_words );
		}

		// Setup file URL
		$url  = "https://source.unsplash.com/random/";
		$url .= $this->assoc_args['thumb_size'] ? "{$this->assoc_args['thumb_size']}/" : '';
		$url .= "?{$keywords}";

		return $url;
	}

	/**
	 * Generate random terms.
	 */
	public function generate_terms( $args = array(), $assoc_args = array() ) {

		$assoc_args = wp_parse_args( $assoc_args, array(
			'count'     => 20,
			'taxonomy'  => 'category,post_tag',
			'max_depth' => 1,
		) );

		// Force taxonomies value into an array.
		$assoc_args['taxonomy'] = explode(',', $assoc_args['taxonomy'] );

		foreach ( $assoc_args['taxonomy'] as $taxonomy ) {

			if ( ! taxonomy_exists( $taxonomy ) ) {
				WP_CLI::error( sprintf( 'The %s taxonomy does not exist.', $taxonomy ) );
			}

			// Establish hierarchy
			$hierarchical     = get_taxonomy( $taxonomy )->hierarchical;
			$previous_term_id = 0;
			$current_depth    = 1;
			$term_parent      = 0;

			$progress = \WP_CLI\Utils\make_progress_bar( sprintf( 'Generating %1$d %2$s terms', $assoc_args['count'], $taxonomy ), $assoc_args['count'] );

			for ( $i = 0; $i < $assoc_args['count']; $i++ ) {

				// Update hierarchy
				if ( $hierarchical ) {
					if ( $this->maybe_make_child() && $current_depth < $assoc_args['max_depth'] ) {
						$term_parent = $previous_term_id;
						$current_depth++;
					} elseif( $this->maybe_reset_depth() ) {
						$current_depth = 1;
						$term_parent = 0;
					}
				}

				$noun = $this->nonsentences->get_word( 'nouns' );

				$term = wp_insert_term(
					ucfirst( $noun['plural'] ),
					$taxonomy,
					array(
						'slug'        => $noun['plural'],
						'description' => $this->nonsentences->sentences( 1, 5 ),
						'parent'      => $term_parent,
					)
				);

				if ( ! is_wp_error( $term ) ) {
					$previous_term_id = $term['term_id'];
				}

				$progress->tick();
			}

			$progress->finish();
		}
	}

	private function maybe_make_child() {
		// 50% chance of making child post
		return ( mt_rand( 1, 2 ) == 1 );
	}

	private function maybe_reset_depth() {
		// 33% chance of reseting to root depth
		return ( mt_rand( 1, 3 ) == 3 );
	}

	public function generate_users( $args, $assoc_args ) {

		$this->assoc_args = wp_parse_args( $assoc_args, array(
			'count' => 100,
			'role'  => 'author',
		) );

		$role = $this->assoc_args['role'];
		if ( ! empty( $role ) ) {
			self::validate_role( $role );
		}

		$user_count = count_users();
		$total = $user_count['total_users'];
		$limit = $this->assoc_args['count'] + $total;

		$progress = \WP_CLI\Utils\make_progress_bar( sprintf( 'Generating %d users', $this->assoc_args['count'] ), $this->assoc_args['count'] );

		for ( $i = $total; $i < $limit; $i++ ) {

			$first_name = $this->nonsentences->get_word( 'names-first' );
			$last_name = $this->nonsentences->get_word( 'names-last' );
			$login = sprintf( '%s_%s_%d', $first_name, $last_name, $i );
			$name = "{$first_name} {$last_name}";

			$user_id = wp_insert_user( array(
				'user_login'   => $login,
				'user_pass'    => $login,
				'user_email'   => "{$login}@example.com",
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'nickname'     => $name,
				'display_name' => $name,
				'description'  => $this->nonsentences->sentences( 1, 5 ),
				'role'         => $role,
			) );

			if ( false === $role ) {
				delete_user_option( $user_id, 'capabilities' );
				delete_user_option( $user_id, 'user_level' );
			}

			$progress->tick();
		}

		$progress->finish();
	}

	/**
	 * Check whether the role is valid.
	 *
	 * @since  1.0.0
	 *
	 * @param  string $role User role.
	 * @return bool         True if valud user role, error if false.
	 */
	private static function validate_role( $role ) {
		if ( ! empty( $role ) && is_null( get_role( $role ) ) ) {
			WP_CLI::error( sprintf( "Role doesn't exist: %s", $role ) );
		}
	}

	/**
	 * Generate random comments.
	 *
	 * Create a random number of comments (min/max)
	 * for posts of a specific post_type, or a specific post ID,
	 * by a random comment_author
	 * within a date range (min/max)
	 * with a max_depth (for hierarchichal comments)
	 * with a random number of body paragraphs (min/max).
	 *
	 * ## OPTIONS
	 * [--post_type=<type>]
     * : The type of posts to target.
     * ---
     * default: post
     * ---
	 *
	 * [--post_count=<number>]
     * : How many posts to comment on.
     * ---
     * default: 100
     * ---
     *
	 * [--post_id=<ID>]
     * : Target a specific post only.
     * ---
     * default: all
     * ---
     *
	 * [--min_count=<number>]
     * : Minimum number of comments to generate per post.
     * ---
     * default: 0
     * ---
     *
	 * [--max_count=<number>]
     * : Max number of comments to generate per post
     * ---
     * default: 50
     * ---
     *
     * [--comment_type=<type>]
     * : The type of the generated comments.
     * ---
     * default: post
     * ---
     *
     * [--comment_status=<status>]
     * : The status of the generated comments.
     * ---
     * default: publish
     * ---
	 *
     * [--max_depth=<number>]
     * : For threaded comments, generate child comments down to a certain depth (default is pulled from Settings > Discussion).
     *
     * [--min_length=<number>]
     * : The minimum length of the generated comments (in paragraphs).
     * ---
     * default: 1
     * ---
	 *
	 * [--max_length=<number>]
     * : The maximum length of the generated comments (in paragraphs).
     * ---
     * default: 5
     * ---
	 */
	public function generate_comments( $args = array(), $assoc_args = array() ) {

		$this->assoc_args = wp_parse_args( $assoc_args, [
			'post_type'      => 'post',
			'post_id'        => 0,
			'post_count'     => 100,
			'min_count'      => 0,
			'max_count'      => 50,
			'comment_status' => 'publish',
			'min_date'       => false,
			'max_date'       => current_time( 'mysql' ),
			'max_depth'      => get_option( 'thread_comments_depth' ),
			'min_length'     => 1,
			'max_length'     => 5,
		] );

		$posts = [];

		// Confirm post type is valid
		if ( ! post_type_exists( $this->assoc_args['post_type'] ) ) {
			WP_CLI::error( sprintf( 'The %s post type does not exist.', $this->assoc_args['post_type'] ) );
		}

		// Fetch one post if given specific ID
		if ( 0 != $this->assoc_args['post_id'] ) {
			$post = get_post($this->assoc_args['post_id'] );

			// Confirm post ID exists
			if ( ! ( $post instanceof WP_Post ) ) {
				WP_CLI::error( sprintf( 'The post ID %d does not exist.', $this->assoc_args['post_id'] ) );
			}

			$this->assoc_args['post_type'] = $post->post_type;
			$posts = [ $post ];

		// Otherwise, get a lot of posts
		} else {
			$posts = get_posts(
				[
					'post_type' => $this->assoc_args['post_type'],
					'posts_per_page' =>  $this->assoc_args['post_count'],
				]
			);
		}

		// Setup nonsentences
		$this->nonsentences->min_paragraphs = $this->assoc_args['min_length'];
		$this->nonsentences->max_paragraphs = $this->assoc_args['max_length'];

		// Add comments to each post
		foreach( $posts as $post ) {

			$this->assoc_args['post_id'] = $post->ID;
			$this->assoc_args['previous_comment_id'] = 0;
			$this->assoc_args['comment_parent'] = 0;
			$this->assoc_args['current_depth'] = 0;
			$this->assoc_args['min_date'] = $post->post_date;

			// Pick a random number of comments to generate from min/max
			$comment_count = rand( $this->assoc_args['min_count'], $this->assoc_args['max_count'] );

			$progress = \WP_CLI\Utils\make_progress_bar( sprintf(
				_n( 'Generating %1$d comment on %2$s %3$d: %4$s.', 'Generating %1$d comments on %2$s %3$d: %4$s.', $comment_count, 'wp-cli-random-content' ),
				$comment_count,
				$post->post_type,
				$post->ID,
				$post->post_title
			), count( $posts ) );

			for ( $i = 0; $i < $comment_count; $i++ ) {
				$this->maybe_update_comment_hierarchy();
				$comment_id = $this->insert_comment();
				$this->assoc_args['previous_comment_id'] = $comment_id;
				$progress->tick();
			}

			$progress->finish();
		}
	}

	private function insert_comment() {

		// Setup user data
		$first_name = $this->nonsentences->get_word( 'names-first' );
		$last_name = $this->nonsentences->get_word( 'names-last' );
		$comment_author = "{$first_name} {$last_name}";
		$username = sanitize_title( $comment_author );
		$comment_author_email = "{$username}@example.com";
		$comment_author_url = $this->maybe_reset_depth() ? "https://{$username}.example.com" : 'https://';
		$comment_date = $this->get_random_post_date( $this->assoc_args['min_date'] , $this->assoc_args['max_date'] );

		return wp_insert_comment( [
			'comment_post_ID' => $this->assoc_args['post_id'],
			'comment_author' => $comment_author,
			'comment_author_email' => $comment_author_email,
			'comment_author_url' => $comment_author_url,
			'comment_content' => $this->nonsentences->paragraphs(),
			'comment_type' => '',
			'comment_approved' => 1,
			'comment_parent' => $this->assoc_args['comment_parent'],
			'user_id' => 0,
			'comment_author_IP' => '127.0.0.1',
			'comment_agent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.10) Gecko/2009042316 Firefox/3.0.10 (.NET CLR 3.5.30729)',
			'comment_date' => $comment_date,
		] );
	}

	private function maybe_update_comment_hierarchy() {

		// Bail if comments aren't threaded
		if ( ! get_option( 'thread_comments', 0 ) ) {
			WP_CLI::error( 'No threaded comments' );
		}

		if ( $this->maybe_make_child() && $this->assoc_args['current_depth'] < $this->assoc_args['max_depth'] ) {
			$this->assoc_args['comment_parent'] = $this->assoc_args['previous_comment_id'];
			$this->assoc_args['current_depth']++;
		} elseif ( $this->maybe_reset_depth() ) {
			$this->assoc_args['comment_parent'] = 0;
			$this->assoc_args['current_depth'] = 1;
		}
	}
}
WP_CLI::add_command( 'random', 'WPS_Random_Posts' );

}
