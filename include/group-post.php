<?php

/*
 * Translations groups for posts, pages and custom post types
 *
 * @since 0.2
 */
class Lingotek_Group_Post extends Lingotek_Group {

	const SAME_AS_SOURCE = 'SAME_AS_SOURCE'; // pref constant used for downloaded translations

	/*
	 * set a translation term for an object
	 *
	 * @since 0.2
	 *
	 * @param int $object_id post id
	 * @param object $language
	 * @param string $document_id translation term name (Lingotek document id)
	 */
	public static function create($object_id, $language, $document_id) {
		$data = array(
			'lingotek' => array(
				'type'         => get_post_type($object_id),
				'source'       => $object_id,
				'status'       => 'importing',
				'translations' => array()
			),
			$language->slug => $object_id // for Polylang
		);

		self::_create($object_id, $document_id, $data, 'post_translations');
	}

	/*
	 * returns content type fields
	 *
	 * @since 0.2
	 *
	 * @param string $post_type
	 * @return array
	 */
	static public function get_content_type_fields($post_type, $post_ID = NULL) {
		$arr = 'attachment' == $post_type ?
			array(
				'post_title'   => __('Title', 'wp-lingotek'),
				'post_excerpt' => __('Caption', 'wp-lingotek'),
				'metas'        => array('_wp_attachment_image_alt' => __('Alternative Text', 'wp-lingotek')),
				'post_content' => __('Description', 'wp-lingotek'),
			) :
			array(
				'post_title'   => __('Title', 'wp-lingotek'),
				'post_name'    => __('Slug', 'wp-lingotek'),
				'post_content' => __('Content', 'wp-lingotek'),
				'post_excerpt' => __('Excerpt', 'wp-lingotek')
			);

		// if the user hasn't visited the custom fields tab, and hasn't saved actions for custom 
		// fields, and uploaded a post, check the wpml file for settings 
		if ($post_ID) {
			self::get_updated_meta_values($post_ID);
		}
		// add the custom fields from the lingotek_custom_fields option
		$custom_fields = get_option('lingotek_custom_fields', array());

		if (isset($custom_fields)) {
			foreach ($custom_fields as $cf => $setting) {
				if ('translate' == $setting)
					$arr['metas'][$cf] = $cf;
			}
		}

		// allow plugins to modify the fields to translate
		return apply_filters('lingotek_post_content_type_fields', $arr, $post_type);
	}

	/*
	 * returns custom fields from the wpml-config.xml file
	 *
	 * @since 0.2
	 *
	 * @param string $post_type
	 * @return array
	 */
	static public function get_custom_fields_from_wpml() {
		$wpml_config = PLL_WPML_Config::instance();
		$arr = array();

		if (isset($wpml_config->tags['custom-fields'])) {
			foreach ($wpml_config->tags['custom-fields'] as $context) {
				foreach ($context['custom-field'] as $cf) {
					$arr[$cf['value']] = $cf['attributes']['action'];
				}
			}
		}

		// allow plugins to modify the fields to translate
		return apply_filters('lingotek_post_content_type_fields_from_wpml', $arr);
	}

	/*
	 * returns meta (custom) fields from the wp-postmeta database table
	 *
	 * @since 0.2
	 *
	 * @return array
	 */
	static public function get_custom_fields_from_wp_postmeta($post_ID = NULL) {
		$custom_fields = get_option('lingotek_custom_fields', array());
		$arr = array();
		$keys = array();

		if ($post_ID) {
			$p = get_post($post_ID);
			$posts [] = $p;
		}
		else {
			$posts = get_posts(array(
				'posts_per_page' => -1,
				'post_type' => 'post'
			));
			$pages = get_posts(array(
				'posts_per_page' => -1,
				'post_type' => 'page'
			));
			$posts = array_merge($posts, $pages);
		}

		foreach ($posts as $post) {
			$metadata = has_meta($post->ID);
			foreach ($metadata as $key => $value) {
				if ($value['meta_key'] === '_encloseme' || $value['meta_key'] === '_edit_last' || $value['meta_key'] === '_edit_lock' || $value['meta_key'] === '_wp_trash_meta_status' || $value['meta_key'] === '_wp_trash_meta_time') {
					unset($metadata[$key]);
				}
				if (in_array($value['meta_key'], $keys)) {
					unset($metadata[$key]);
				}
				$keys [] = $value['meta_key'];
			}
			$arr = array_merge($arr, $metadata);
		}
		// allow plugins to modify the fields to translate
		return apply_filters('lingotek_post_custom_fields', $arr);
	}

	/*
	 * updates meta (custom) fields values in the lingotek_custom_fields option 
	 *
	 * @since 0.2
	 *
	 * @return array
	 */
	public static function get_updated_meta_values($post_ID = NULL) {
		$custom_fields_from_wpml = self::get_custom_fields_from_wpml();
		$custom_fields_from_postmeta = self::get_custom_fields_from_wp_postmeta($post_ID);
		$custom_fields_from_lingotek = get_option('lingotek_custom_fields', array());
		$custom_fields = array();
		$items = array();

		foreach ($custom_fields_from_postmeta as $cf) {
  		// no lingotek setting
  		if (!array_key_exists($cf['meta_key'], $custom_fields_from_lingotek)) {
    		// no lingotek setting, but there's a wpml setting
    		if (array_key_exists($cf['meta_key'], $custom_fields_from_wpml)) {
      		$custom_fields[$cf['meta_key']] = $custom_fields_from_wpml[$cf['meta_key']];
      		$arr = array(
        		'meta_key' => $cf['meta_key'],
        		'setting' => $custom_fields_from_wpml[$cf['meta_key']],
      		);
    		}
    		// no lingotek setting, no wpml setting, so save default setting of ignore
    		else {
      		$custom_fields[$cf['meta_key']] = 'ignore';
      		$arr = array(
        		'meta_key' => $cf['meta_key'],
        		'setting' => 'ignore',
      		);
    		}
  		}
  		// lingotek already has this field setting saved
  		else {
    		$custom_fields[$cf['meta_key']] = $custom_fields_from_lingotek[$cf['meta_key']]; 
    		$arr = array(
      		'meta_key' => $cf['meta_key'],
      		'setting' => $custom_fields_from_lingotek[$cf['meta_key']],
    		);
  		}
  		$items [] = $arr;
		}

		if ($post_ID) {
			$custom_fields = array_merge($custom_fields_from_lingotek, $custom_fields);
		}
		update_option('lingotek_custom_fields', $custom_fields);
		return $items;
	}

	/*
	 * returns cached meta (custom) fields values in the lingotek_custom_fields option 
	 *
	 * @since 0.2
	 *
	 * @return array
	 */

	public static function get_cached_meta_values() {
		$custom_fields_from_lingotek = get_option('lingotek_custom_fields', array());
		$items = array();

		foreach ($custom_fields_from_lingotek as $key => $setting) {
			$arr = array(
				'meta_key' => $key,
				'setting' => $setting,
			);

			$items [] = $arr;
		}
		return $items;
	}

	/*
	 * returns the content to translate
	 *
	 * @since 0.2
	 *
	 * @param object $post
	 * @return string json encoded content to translate
	 */
	public static function get_content($post) {
		$fields = self::get_content_type_fields($post->post_type, $post->ID);
		$content_types = get_option('lingotek_content_type');
		foreach (array_keys($fields) as $key) {
			if ('metas' == $key) {
				foreach (array_keys($fields['metas']) as $meta) {
					$value = get_post_meta($post->ID, $meta, true);
					if ($value)
						$arr['metas'][$meta] = $value;
				}
			}

			// send slug for translation only if it has been modified
			elseif('post_name' == $key && empty($content_types[$post->post_type]['fields'][$key])) {
				$default_slug = sanitize_title($post->post_title); // default slug created by WP
				if ($default_slug != $post->post_name)
					$arr['post'][$key] = $post->$key;
			}

			elseif (empty($content_types[$post->post_type]['fields'][$key])) {
				$arr['post'][$key] = $post->$key;
			}
		}
		
		return json_encode($arr);
	}

	public static function is_valid_auto_upload_post_status($post_status) {
		$prefs = Lingotek_Model::get_prefs();
		$valid_statuses = $prefs['auto_upload_post_statuses'];
		$valid = array_key_exists($post_status, $valid_statuses) && $valid_statuses[$post_status];
		return $valid;
	}

	/*
	 * requests translations to Lingotek TMS
	 *
	 * @since 0.1
	 */
	public function request_translations() {
		if (isset($this->source)) {
			$language = $this->pllm->get_post_language((int) $this->source);
			$this->_request_translations($language);
		}
	}

	/*
	 * create a translation downloaded from Lingotek TMS
	 *
	 * @since 0.1
	 * @uses Lingotek_Group::safe_translation_status_update() as the status can be automatically set by the TMS callback
	 *
	 * @param string $locale
	 */
	public function create_translation($locale) {
		$client = new Lingotek_API();

		if (false === ($translation = $client->get_translation($this->document_id, $locale)))
			return;

		self::$creating_translation = true;
		$prefs = Lingotek_Model::get_prefs(); // need an array by default

		$translation = json_decode($translation, true); // wp_insert_post expects array
		$tr_post = $translation['post'];

		$post = get_post($this->source); // source post
		$tr_post['post_status'] = ($prefs['download_post_status'] === self::SAME_AS_SOURCE)? $post->post_status : $prefs['download_post_status']; // status

		// update existing translation
		if ($tr_id = $this->pllm->get_post($this->source, $locale)) {
			$tr_post['ID'] = $tr_id;
			wp_update_post($tr_post);

			$this->safe_translation_status_update($locale, 'current');
		}

		// create new translation
		else {
			unset($post->post_name); // forces the creation of a new default slug if not translated by Lingotek
			$tr_post = array_merge((array) $post , $tr_post); // copy all untranslated fields from the original post
			$tr_post['ID'] = null; // will force the creation of a new post

			// translate parent
			$tr_post['post_parent'] = ($post->post_parent && $tr_parent = $this->pllm->get_translation('post', $post->post_parent, $locale)) ? $tr_parent : 0;

			if ('attachment' == $post->post_type) {
				$tr_id = wp_insert_attachment($tr_post);
				add_post_meta($tr_id, '_wp_attachment_metadata', get_post_meta($this->source, '_wp_attachment_metadata', true));
				add_post_meta($tr_id, '_wp_attached_file', get_post_meta($this->source, '_wp_attached_file', true));
			}
			else {
				$tr_id = wp_insert_post($tr_post);
			}

			if ($tr_id) {
				$tr_lang = $this->pllm->get_language($locale);
				$this->pllm->set_post_language($tr_id, $tr_lang);

				$this->safe_translation_status_update($locale, 'current', array($tr_lang->slug => $tr_id));
				wp_set_object_terms($tr_id, $this->term_id, 'post_translations');

				// assign terms and metas
				$GLOBALS['polylang']->sync->copy_post_metas($this->source, $tr_id, $tr_lang->slug);

				// copy or ignore metas
				$custom_fields = get_option('lingotek_custom_fields', array());
				foreach ($custom_fields as $key => $setting) {
					if ('copy' === $setting) {
						$source_meta = current(get_post_meta($post->ID, $key))	;
						update_post_meta($tr_id, $key, $source_meta);
					}
					elseif ('ignore' === $setting) {
						delete_post_meta($tr_id, $key);
					}
				}

				// translate metas
				if (!empty($translation['metas'])) {
					foreach ($translation['metas'] as $key => $meta)
						update_post_meta($tr_id, $key, $meta);
				}
			}
		}

		self::$creating_translation = false;
	}

	/*
	 * checks if content should be automatically uploaded
	 *
	 * @since 0.2
	 *
	 * @return bool
	 */
	public function is_automatic_upload() {
		return 'automatic' == Lingotek_Model::get_profile_option('upload', get_post_type($this->source), $this->get_source_language());
	}

	/*
	 * get the the language of the source post
	 *
	 * @since 0.2
	 *
	 * @return object
	 */
	public function get_source_language() {
		return $this->pllm->get_post_language($this->source);
	}
}
