<?php
/*
Plugin Name: FWP+: Keyword Filters
Plugin URI: http://feedwordpress.radgeek.com/
Description: simple and flexible keyword or category filtering for FeedWordPress syndicated posts
Version: 2014.0707
Author: Charles Johnson
Author URI: http://radgeek.com/
License: GPL
*/

/**
 * @package FWPKeywordFilters
 * @version 2012.0601
 */

// Get the path relative to the plugins directory in which FWP is stored
preg_match (
	'|'.preg_quote(WP_PLUGIN_DIR).'/(.+)$|',
	dirname(__FILE__),
	$ref
);

if (isset($ref[1])) :
	$fwpkf_path = $ref[1];
else : // Something went wrong. Let's just guess.
	$fwpkf_path = 'fwp-keyword-filters';
endif;

class fwpkfMatchablePost {
	var $post;
	var $texts;
	var $cats;
	var $matches;

	function __construct ($post) {
		// Keep for reference
		$this->post = $post;
		$this->matches = 0;
		$this->texts = NULL;
		$this->cats = NULL;
	}

	function compile_patterns ($regex) {
		$pattern = $regex['pattern'];
		$mods = $regex['mods'];

		// Make use of our standard regex representation
		return "\007${pattern}\007${mods}";
	}

	function match ($patterns, $mFields) {
		// Prefix to restrict possible methods
		$mFields = 'fields_'.$mFields;

		// Let's get the text or the categories to match against.
		$fields = (is_callable(array($this, $mFields)) ? $this->{$mFields}() : array());

		$regexen = array_map(array($this, 'compile_patterns'), $patterns);

		// Determine whether we can match ALL the patterns
		$matched = true;
		foreach ($regexen as $regex) :
			$matchedHere = false;
			foreach ($fields as $scan) :
				if (preg_match($regex, $scan)) :
					$matchedHere = true;
					break;
				endif;
			endforeach;
			$matched = ($matched AND $matchedHere);
		endforeach;

		return $matched;
	}

	function fields_text () {
		if (is_null($this->texts)) :
			$this->texts = array();
			$this->texts[] = $this->post->title();
			$this->texts[] = strip_tags($this->post->title());
			$this->texts[] = $this->post->content();
			$this->texts[] = strip_tags($this->post->content());
		endif;
		return $this->texts;
	}

	function fields_category () {
		if (is_null($this->cats)) :
			$this->cats = array();
			$post_cats = $this->post->get_categories();
			
			// If we have any categories, then loop through them to get both
			// label and term.
			if (is_array($post_cats)) :
				foreach ($post_cats as $cat) :
					$this->cats[] = $cat->get_label();
					$this->cats[] = $cat->get_term();
				endforeach;
			endif;
		endif;
		return $this->cats;
	}
}

class FWPKeywordFilters {
	var $terms;
	var $matches;
	var $filtered;
	
	function FWPKeywordFilters () {
		global $fwpkf_path;
		if (!is_admin()) {
		$this->name = strtolower(get_class($this));
		
		// Set up functionality. Future-proof for when syndicated_item becomes syndicated_entry
		add_filter('syndicated_item', array(&$this, 'syndicated_entry'), 1000, 2);
		add_filter('syndicated_entry', array(&$this, 'syndicated_entry'), 1000, 2);
		add_filter('syndicated_post', array(&$this, 'syndicated_post'), 1000, 2);

		// Set up configuration UI
		add_action('feedwordpress_admin_page_posts_meta_boxes', array(&$this, 'add_settings_box'), 100, 1);
		add_action('feedwordpress_admin_page_posts_save', array(&$this, 'save_settings'), 100, 2);
		add_action('admin_print_scripts', array(&$this, 'admin_print_scripts'));
		wp_register_script('fwp-keyword-filters', WP_PLUGIN_URL.'/'.$fwpkf_path.'/fwp-keyword-filters.js');
		add_action('wp_enqueue_scripts', 'admin_enqueue_scripts');

		// Set up diagnostics
		add_filter('feedwordpress_diagnostics', array(&$this, 'diagnostics'), 10, 2);
		add_filter('syndicated_feed_special_settings', array(&$this, 'special_settings'), 10, 2);
		}
	}

	function special_settings ($settings, $source) {
		return array_merge($settings, array(
		'keyword filters',
		));
	} /* FWPKeywordFilters::special_settings () */

	function admin_print_scripts () {
		wp_enqueue_script(
			'fwp-keyword-filters',
			NULL,
			array('jquery')
		);
	}

	function diagnostics ($diag, $page) {
		$diag['Syndicated Post Details']['keyword_filters:scan'] = 'as posts are scanned for keywords';
		$diag['Syndicated Post Details']['keyword_filters:match'] = 'when posts are matched by keyword and actions are applied';
		return $diag;
	}
		
	function syndicated_entry ($entry, $obj) {
		if (apply_filters('fwp_keyword_filters_defer_processing', false, $entry, $obj)) :
			// Maybe we could do something cool here like recording
			// the exact data from off the feed, etc. But for now...
			FeedWordPress::diagnostic(
				'keyword_filters:scan',
				"Deferring scan of item [${iGuid}] for keywords"
			);

			return $entry;
		endif;
		
		$mp = new fwpkfMatchablePost($obj);

		$localKeys = maybe_unserialize($obj->link->setting(
			'keyword filters', NULL,
			array()
		));
		
		$merge = $obj->link->setting('add/keyword_filters', NULL, 'yes');
		if ($merge=='yes') :
			$globalKeys = maybe_unserialize(get_option('feedwordpress_keyword_filters', array()));
			$allWords = array_unique(array_merge(array_keys($globalKeys), array_keys($localKeys)));
		else :
			$allWords = array_keys($localKeys);
		endif;
		
		$keys = array();
		foreach ($allWords as $word) :
			$keys[$word] = array();
			if (($merge=='yes') and isset($globalKeys[$word])) :
				$keys[$word] = array_merge($keys[$word], $globalKeys[$word]);
			endif;
			if (isset($localKeys[$word])) :
				$keys[$word] = array_merge($keys[$word], $localKeys[$word]);
			endif;
		endforeach;
		
		$this->filtered = NULL;
		$this->terms = array(
			'category' => array(),
			'post_tag' => array(),
		);

		foreach ($keys as $word => $action) :
			if ($word != '-') :
				$this->processRule($word, $action, $mp);
			endif;
		endforeach;
		
		if (isset($keys['-'])) :
			$this->processRule('-', $keys['-'], $mp);
		endif;

		if ($this->filtered) :
			$entry = NULL; // Filter it out.
		endif;
		return $entry;
	} /* FWPKeywordFilters::syndicated_item () */

	function processRule ($word, $actions, $mp) {
		if ($word=='-') :
			if ($mp->matches > 0) :
				$word = '/$^/'; // never matched
			else :
				$word = '/.*/'; // always matches
			endif;
		endif;
		
		$comparisonFields = 'text'; $patterns = NULL;
		if (preg_match("\007^(category|text)::(.*)$\007", $word, $ref)) :
			$comparisonFields = $ref[1];
			switch ($comparisonFields) :
			case 'category' :
				// Exact & complete matches only, but ignore case and space
				$patterns = array(array(
					"pattern" => '^\s*'.preg_replace(
						"/\s+/",
						"\\s+",
						preg_quote(
							strtolower(trim($ref[2]))
						)
					).'\s*$',
					"mods" => "i",
				));
				break;
			case 'text' :
				$word = $ref[2];
			default :
				// Pass the buck down to the next if-then structure
			endswitch;
		endif;

		if (is_null($patterns)) :
			if (preg_match("\007^\s*/(.*)/\s*([a-z]*)\s*\$\007i", $word, $ref)) :
				// PCRE literal expression
				$patterns = array(array(
				"pattern" => $ref[1], "mods" => $ref[2]
				));
			else :
				// List of keywords to search for.
				$words = preg_split('/\s+/', $word, -1, PREG_SPLIT_NO_EMPTY);
				$patterns = array();
				foreach ($words as $w) :
					if (preg_match("\007^\s*/(.*)/\s*([a-z]*)\s*\$\007i", $w, $ref)) :
						$patterns[] = array(
						"pattern" => $ref[1],
						"mods" => $ref[2],
						);
					else :
						$patterns[] = array(
						"pattern" => '\b'.preg_quote($w).'\b',
						"mods" => 'i',
						);
					endif;
				endforeach;
			endif;
		endif;

		$diagRegexes = array();
		foreach ($patterns as $regex) :
			$diagRegexes[] = '/'.$regex['pattern'].'/'.$regex['mods'];
		endforeach;

		$iGuid = esc_html($mp->post->guid());
		$iWord = esc_html($word);
		$iRegexes = esc_html(implode(' + ', $diagRegexes));
		$iMethod = esc_html($comparisonFields);

		FeedWordPress::diagnostic(
			'keyword_filters:scan',
			"Scanning item [${iGuid}] for $iMethod keyword $iWord (PCRE: ${iRegexes})"
		);

		$matched = $mp->match($patterns, $comparisonFields);

		if ($matched) :
			$mp->matches = $mp->matches + 1;

			FeedWordPress::diagnostic(
			'keyword_filters:match',
			'Matched item ['.esc_html($mp->post->guid()).'] against keyword "'.esc_html($word).'". Applying: '.esc_html(implode(",", $actions))
			);

			foreach ($actions as $action) :
				$bits = explode(":", $action, 2);
				$verb = $bits[0];
				if (isset($bits[1])) : $param = $bits[1]; endif;
				
				switch ($verb) :
				case 'filter' :
					if (is_null($this->filtered)) :
						$this->filtered = true;
					endif;
					break;
				case 'include' :
					$this->filtered = false;
					break;
				case 'category' :
				case 'post_tag' :
					$terms = array_map('trim', explode(",", $param));
					$this->terms[$verb] = array_merge($this->terms[$verb], $terms);
				endswitch;
			endforeach;
		endif;
	} /* FWPKeywordFilters::processRule () */

	function syndicated_post ($post, $obj) {
		if (!$this->filtered) :
			foreach ($this->terms as $tax => $terms) :
				if (!isset($post['tax_input'][$tax])) :
					$post['tax_input'][$tax] = array();
				endif;
				
				$post['tax_input'][$tax] = array_merge(
					$post['tax_input'][$tax],
					$terms
				);
			endforeach;
		else :
			// This should never happen...
			$post = NULL;
		endif;
		return $post;
	} /* FWPKeywordFilters::syndicated_post () */

	function add_settings_box ($page) {
		add_meta_box(
			/*id=*/ "feedwordpress_{$this->name}_box",
			/*title=*/ __('Keyword Filters'),
			/*callback=*/ array(&$this, 'display_settings'),
			/*page=*/ $page->meta_box_context(),
			/*context=*/ $page->meta_box_context()
		);
	} /* FWPKeywordFilters::add_settings_box () */
	
	function display_settings ($page, $box = NULL) {
		$keys = maybe_unserialize($page->setting("keyword filters", array(), array("fallback" => false)));
		if ($page->for_feed_settings()) :
			$globalKeys = maybe_unserialize(get_option('feedwordpress_keyword_filters', array()));
		endif;
		
		$ifNoneMatch = array();
		if (isset($keys['-'])) :
			$ifNoneMatch = $keys['-'];
			unset($keys['-']);
		endif;
		$i = 0;
		?>
		<?php if ($page->for_feed_settings()) : ?>
		<table class="twofer">
		<tbody>
		<tr>
		<td class="primary">
		<?php
		endif;
		?>
		
		<table class="edit-form narrow">
		<tbody>
		<tr><th>Keyword Filters</th>
		<td>

		<?php
		foreach ($keys as $word => $actions) :
			foreach ($actions as $action) :
				$this->display_keyword_row($i, $word, $action, $page);
			endforeach;
		endforeach;
		
		// blank for new entry
		$j = "new-1";
		$this->display_keyword_row($j, '', '', $page);
		?>
		</td>
		</tr>

		<tr><th>Default</th>
		<td><?php
		foreach ($ifNoneMatch as $action) :
			$this->display_keyword_row($i, '-', $action, $page);
		endforeach;
		
		// blank for new entry
		$j = "new-2";
		$this->display_keyword_row($j, '-', '', $page);
		?>

		<input id="feedwordpress-keyword-filters-num"
		type="hidden"
		name="feedwordpress_keyword_filters_num"
		value="<?php print $i; ?>"
		/>

		</td></tr>
		</tbody>
		</table>

		<?php
		if ($page->for_feed_settings()) :
			// For checkboxes below
			$checked = array('yes' => '', 'no' => '');
			$checked[$page->setting('add/keyword_filters', 'yes')] = ' checked="checked"';
			
			// For link below
			$siteWideHref = 'admin.php?page='.$GLOBALS['fwp_path'].'/'.basename($page->filename);
			?>
			</td>
			<td class="secondary">
			<h4>Site-wide Filters</h4>
			<p><?php if (is_array($globalKeys) and count($globalKeys) > 1) : ?>You have set
			up a number of filters
			<?php elseif (is_array($globalKeys) and count($globalKeys) > 0) :?>You have set up
			at least one filter
			<?php else : ?>If you set up filters
			<?php endif; ?>
			in the <a href="<?php print esc_html($siteWideHref); ?>">site-wide settings</a>,
			should <?php print $page->these_posts_phrase(); ?> be run
			through those filters, in addition to the filters you set
			up here?</p>
			
			<ul class="settings">
			<li><p><label><input type="radio" name="add_global_keyword_filters" value="yes" <?php print $checked['yes']; ?> /> Yes. Run <?php print $page->these_posts_phrase(); ?> through all the filters.</label></p></li>
			<li><p><label><input type="radio" name="add_global_keyword_filters" value="no" <?php print $checked['no']; ?> /> No. Only use the keyword filters I set up on the left. Do not use the global defaults for <?php print $page->these_posts_phrase(); ?></label></p></li>
			</ul>
			</td>
			</tr>
			</tbody>
			</table> <!-- class="twofer" -->
		<?php
		endif;
		?>
		<?php
	} /* FWPKeywordFilters::display_settings () */
	
	function display_keyword_row (&$i, $word, $action, $page) {
		if ($word=='-') :
			$label = "Posts where no keywords appear";
			$wordType = 'hidden';

			// No split necessary.
			$wordPrefix = '';
		else :
			$label = "Posts containing";
			$wordType = 'text';

			// Split into field::match pattern
			$wordParts = array_reverse(explode('::', $word, 2));
			$word = $wordParts[0];
			$wordPrefix = (isset($wordParts[1]) ? $wordParts[1] : 'text');
		endif;

		$psel = array();
		$prefixes = array('text', 'category');
		foreach ($prefixes as $p) :
			if ($wordPrefix==$p) :
				$psel[$p] = ' selected="selected"';
			else :
				$psel[$p] = '';
			endif;
		endforeach;		

		// Split up the action into verb and (possibly) parameter
		$bits = explode(":", $action, 2);
		$verb = $bits[0];
		
		$verbs = array(
		'', 'include', 'filter', 'category', 'post_tag'
		);
		
		$sel = $ids = $class = array();
		foreach ($verbs as $v) :
			$ids[$v] = 'keyword-filters-param-'.$v.'-'.$i;
			$class[$v] = 'keyword-filters-param keyword-filters-param-'.$v;
			if ($verb==$v) :
				$sel[$v] = ' selected="selected"';
			else :
				$sel[$v] = '';
				$class[$v] .= " hide_if_js hide-if-js";
			endif;
		endforeach;
		?>
		<div class="keyword-filters-row" style="clear: both" id="keyword-filters-row-<?php print $i; ?>">
		<div class="keyword-filters-top-row"><label><?php print $label; ?>
		<input type="<?php print $wordType; ?>"
		name="feedwordpress_keyword_filters_keyword[<?php print $i; ?>]"
		value="<?php print $word; ?>"
		placeholder="keyword" size="8" /></label>

		<?php if ('hidden'==$wordType) : ?>
		<input type="hidden" name="feedwordpress_keyword_filters_prefix[<?php print $i; ?>]" value="" />
		<?php else : ?>
		<label>in their
		<select size="1" name="feedwordpress_keyword_filters_prefix[<?php print $i; ?>]">
		  <option value="text"<?php print $psel['text']; ?>>text</option>
		  <option value="category"<?php print $psel['category']; ?>>categories</option>
		</select></label>
		<?php endif; ?>

		<label>get <select class="feedwordpress-keyword-filters-action"
		id="keyword-filters-action-<?php print $i; ?>"
		name="feedwordpress_keyword_filters_action[<?php print $i; ?>]"
		size="1">
		  <option value="">- select action -</option>
		  <option value="include"<?php print $sel['include']; ?>>syndicated to this website</option>
		  <option value="filter"<?php print $sel['filter']; ?>>filtered out</option>
		  <option value="category"<?php print $sel['category']; ?>>assigned categories</option>
		  <option value="post_tag"<?php print $sel['post_tag']; ?>>assigned tags</option>
		</select></label>

		<span class="<?php print $class['']; ?>" id="<?php print $ids['']; ?>">
		&nbsp; <!-- nonzero size -->
		</span>
		
		<span class="<?php print $class['include']; ?>" id="<?php print $ids['include']; ?>">
		&nbsp; <!-- nonzero size -->
		</span>
		
		<span class="<?php print $class['filter']; ?>" id="<?php print $ids['filter']; ?>">
		&nbsp; <!-- nonzero size -->
		</span>

		<span class="<?php print $class['post_tag']; ?>" id="<?php print $ids['post_tag']; ?>">
		<input type="text" name="feedwordpress_keyword_filters_param[<?php print $i; ?>][post_tag][]"
		value="<?php
		if (preg_match('/^post_tag:(.*)$/', $action, $ref)) :
			print esc_html($ref[1]);
		endif;
		?>"
		placeholder="first tag, second tag, ..." />
		</span>
		</div>
		
		<div style="margin-top: 0.5em; margin-bottom: 1.5em; clear: both;" class="<?php print $class['category']; ?>" id="<?php print $ids['category']; ?>">
		<?php
		if (preg_match('/^category:(.*)$/', $action, $ref)) :
			$checked = array_map('intval', array_map('trim', explode(',', $ref[1])));
		else :
			$checked = array();
		endif;
		
		fwp_category_box(
			/*checked=*/ $checked,
			/*object=*/ 'matching '.$page->these_posts_phrase(),
			/*tags=*/ array(),
			/*params=*/ array(
				'taxonomy' => 'category',
				'prefix' => 'keyword-filters-category-'.$i,
				'checkbox_name' => "feedwordpress_keyword_filters_param[$i][category]",
			)
		);
		?>
		</div>
		</div>
		
		<?php
		// Advance for numeric rows, not for "new" placeholders
		if (is_numeric($i)) :
			$i++;
		endif;
	}
	
	function save_settings ($post, $page) {
		$keys = array();
		foreach ($post['feedwordpress_keyword_filters_keyword'] as $i => $word) :
			$word = trim($word);
			if (isset($post['feedwordpress_keyword_filters_prefix'])
			and isset($post['feedwordpress_keyword_filters_prefix'][$i])
			and strlen($post['feedwordpress_keyword_filters_prefix'][$i]) > 0) :
				$word = $post['feedwordpress_keyword_filters_prefix'][$i] . '::' . $word;
			endif;

			if (strlen($word) > 0) :
				if (isset($post['feedwordpress_keyword_filters_action'][$i])) :
					$action = trim($post['feedwordpress_keyword_filters_action'][$i]);
					if (strlen($action) > 0) :
						if (isset($post['feedwordpress_keyword_filters_param'][$i][$action])) :
							$param = implode(",", $post['feedwordpress_keyword_filters_param'][$i][$action]);
							$action .= ':'.$param;
						endif;
								
						if (!isset($keys[$word])) :
							$keys[$word] = array();
						endif;
						
						$keys[$word][] = $action;
					endif;
				endif;
			endif;
		endforeach;

		$page->update_setting('keyword filters', serialize($keys));
		
		if ($page->for_feed_settings() and isset($post['add_global_keyword_filters'])) :
			$page->update_setting('add/keyword_filters', $post['add_global_keyword_filters']);
		endif;
	}
} /* FWPKeywordFilters */
 
$fwpKeywordFilters = new FWPKeywordFilters;

