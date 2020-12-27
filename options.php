<?php
defined('ABSPATH') or die;

class NpMetaOptions {
    /**
     * Get post meta option of specified post
     *
     * @param int    $post_id
     * @param string $name
     *
     * @return false|mixed
     */
    public static function get($post_id, $name) {
        return get_post_meta($post_id, '_' . $name, true);
    }

    /**
     * Update post meta option of specified post
     *
     * @param int    $post_id
     * @param string $name
     * @param mixed  $value
     */
    public static function update($post_id, $name, $value) {
        update_post_meta($post_id, '_' . $name, $value);
    }

    /**
     * Action on save_post
     * Save selected post meta options
     *
     * @param int $post_id
     *
     * @return int
     */
    public static function saveAction($post_id) {
        if (!isset($_POST['np_meta_options']) || !wp_verify_nonce($_POST['np_meta_options'], 'np_meta_options')) {
            return $post_id;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $post_id;
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'page' || $_POST['post_type'] !== 'page') {
            return $post_id;
        }

        if (!current_user_can('edit_page', $post_id)) {
            return $post_id;
        }

        $np_template = stripslashes(_arr($_POST, 'np_template', ''));
        if (in_array($np_template, array('', 'html', 'html-header-footer'))) {
            self::update($post_id, 'np_template', $np_template);
        }
        return $post_id;
    }

    /**
     * Action on add_meta_boxes
     * Add Nicepage options metabox
     */
    public static function addMetaBoxAction() {
        global $post;
        if (!$post || !np_data_provider($post->ID)->isNicepage()) {
            return;
        }

        add_meta_box(
            'nicepage_page_meta_box',
            __('Nicepage Options', 'nicepage'),
            'NpMetaOptions::printMetaBox',
            'page',
            'side',
            'low'
        );
    }

    /**
     * Print Nicepage options metabox
     *
     * @param WP_Post $post
     */
    public static function printMetaBox($post) {
        if (!$post || !isset($post->ID)) {
            return;
        }

        wp_nonce_field('np_meta_options', 'np_meta_options');

        // @codingStandardsIgnoreStart

        $selected = NpMetaOptions::get($post->ID, 'np_template');
?>
        <p><strong>Template</strong></p>
        <p class="meta-options named">
            <select name="np_template" id="np_template">
                <option <?php echo $selected === 'html' ? 'selected' : ''; ?> value="html">Nicepage Header and Footer</option>
                <option <?php echo $selected === 'html-header-footer' ? 'selected' : ''; ?> value="html-header-footer">Theme Header and Footer</option>
                <option <?php echo $selected !== 'html' && $selected !== 'html-header-footer'? 'selected' : ''; ?> value="">Theme Template</option>
            </select>
        </p>
        <p>
            Nicepage Template does not have sidebars, header, footer, uses full width
        </p>
<?php
        $forms = NpMetaOptions::get($post->ID, 'np_forms');
        if ($forms) {
?>
            <p><strong><?php echo count($forms) > 1 ? __('Forms', 'nicepage') : __('Form', 'nicepage'); ?></strong></p>
<?php
            $plugin_forms = get_posts(
                array(
                    'post_type' => 'wpcf7_contact_form',
                    'numberposts' => -1,
                )
            );

            foreach ($forms as $form) {
?>
                <p class="meta-options named">
                    <select name="np_forms[<?php echo $form['id']; ?>]">
                        <option value=""><?php echo __('&mdash; Select &mdash;', 'nicepage'); ?></option>

                        <?php foreach (array_reverse($plugin_forms) as $plugin_form): ?>
                            <option <?php echo (int) $form['id'] === (int) $plugin_form->ID ? 'selected' : ''; ?> value="<?php echo $plugin_form->ID; ?>"><?php echo $plugin_form->post_title; ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
<?php
            }
        }
        // @codingStandardsIgnoreEnd
    }
}

add_action('add_meta_boxes', 'NpMetaOptions::addMetaBoxAction');
add_action('save_post', 'NpMetaOptions::saveAction');