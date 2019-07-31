brichards/wp-cli-random-content
================

Use WP-CLI to generate random posts, taxonomies, and users for a WordPress site.

Makes use of [mospaw/nonsentences](https://github.com/mospaw/nonsentences), a revival of Jeff Holman's nonsense generator, by Chris Mospaw. The sentence and title generators reply on the word lists in the `nonsentences/db` folder.

Quick links: [Installing](#installing) | [Contributing](#contributing) | [Usage](#usage)

## Installing

Installing this package requires WP-CLI v0.23.0 or greater. Update to the latest stable release with `wp cli update`.

Once you've done so, you can install this package with `wp package install brichards/wp-cli-random-content`.

## Contributing

Code and ideas are more than welcome.

Please [open an issue](https://github.com/brichards/wp-cli-random-content/issues) with questions, feedback, and violent dissent. Pull requests are expected to include test coverage.

## Usage

### Generate Posts

`wp random generate`

Generate some truly random posts of a given `post_type` and control every facet of the generation.

Each post will have a random number of paragraphs comprised of a random number of sentences. The publish date will be random (within a min/max range). The post author will be random.

Just look at all these options!

**Options**

* `--count=<number>`: Number of posts to generate (default: `100`).
* `--post_type=<type>`: The type of post to generate (default: `post`).
* `--post_status=<status>`: The status of the generated posts (default: `publish`).
* `--post_author=<login>`: The author of the generated posts (default: `random`).
* `--min_date=<yyyy-mm-dd>`: The oldest possible `publish_date` per post (default: `current date`).
* `--max_date=<yyyy-mm-dd>`: The newest possible `publish_date` per post (default: `current date`).
* `--max_depth=<number>`: Maximum depth of children for hierarchical post types (default: `1`).
* `--min_length=<number>`: The minimum number of paragraphs per post (default: `1`).
* `--max_length=<number>`: The maximum number of paragraphs per post (default: `10`).
* `--min_terms=<number>`: The minimum number of terms per post (default: `0`).
* `--max_terms=<number>`: The maximum number of terms per post (default: `5`).
* `--taxonomies=<taxonomy>`: The taxonomy/ies to use for attached terms (default: `category,post_tag`).
* `--set_thumbnail=<boolean>`: Whether or not to set the post thumbnail for generated posts (default: `true`).
* `--require_thumb=<boolean>`: Force a thumbnail (`true`) or allow a 20% chance for no thumbnail (`false`) per post (default: `false`).
* `--thumb_keywords=<string>`: Specific search keywords (comma separated) to refine image selection (default: `none`).
* `--thumb_size=<string>`: Specific image dimensions in WxH format (e.g. 1024x768) to limit downloaded image dimensions. An empty value defaults to the largest size available (default: none).
* `--with_terms=<boolean>`: Generate random terms before generating posts (default: `false`).

### Generate Terms

`wp random generate_terms`

**Options**

* `--taxonomy=<taxonomy>`: Which taxonomy/ies to use when generating terms (default: `category,post_tag`).
* `--count=<number>`: Number of terms to generate per taxonomy (default: `20`).
* `--max_depth=<number>`: Maximum depth of children for hierarchical taxonomies (default: `1`).

### Generate Users

`wp random generate_users`

**Options**

* `--count=<number>`: Number of users to generate (default: `100`).
* `--role=<type>`: The user role for all generated users (default: `author`).
